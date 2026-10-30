<?php

namespace App\Console\Commands;

use App\Jobs\PublishSmartAutocutStory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DispatchSmartAutocutStories extends Command
{
    protected $signature = 'smart-autocut:dispatch-stories {--dry-run : Preview without creating or dispatching Story jobs}';

    protected $description = 'Create idempotent Facebook/Instagram Story jobs after Smart Autocut feed content has published.';

    public function handle(): int
    {
        foreach (['contents', 'content_media', 'social_accounts', 'content_destinations', 'content_story_destinations'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->error("Required table missing: {$table}");
                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        if (! config('services.meta.stories_enabled', false)) {
            $this->warn('Story autopublishing is disabled by SMARTCUT_STORIES_ENABLED=false.');
            return self::SUCCESS;
        }
        $enabledSince = config('services.meta.stories_enabled_since');
        if (! is_string($enabledSince) || trim($enabledSince) === '') {
            $this->error('SMARTCUT_STORIES_ENABLED_SINCE is missing; refusing to backfill historical posts as Stories.');
            return self::FAILURE;
        }

        $eligible = DB::table('content_destinations as d')
            ->join('contents as c', 'c.id', '=', 'd.content_id')
            ->join('social_accounts as a', 'a.id', '=', 'd.social_account_id')
            ->where('c.source', 'smart_autocut')
            ->where('d.status', 'published')
            ->where('d.updated_at', '>=', $enabledSince)
            ->whereIn('d.provider', ['facebook', 'instagram'])
            ->where('a.status', 'active')
            ->orderBy('d.id')
            ->get([
                'd.id as source_destination_id',
                'd.content_id',
                'd.social_account_id',
                'd.provider',
            ]);

        $created = 0;
        foreach ($eligible as $source) {
            $mediaUrl = DB::table('content_media')
                ->where('content_id', $source->content_id)
                ->where('type', 'video')
                ->whereNotNull('public_url')
                ->orderBy('id')
                ->value('public_url');

            if (! is_string($mediaUrl) || trim($mediaUrl) === '') {
                $this->warn("SKIP destination {$source->source_destination_id}: no public video URL.");
                continue;
            }

            $exists = DB::table('content_story_destinations')
                ->where('source_destination_id', $source->source_destination_id)
                ->exists();
            if ($exists) {
                continue;
            }

            if ($dryRun) {
                $this->line("WOULD CREATE story for destination {$source->source_destination_id} ({$source->provider})");
                continue;
            }

            $now = now('UTC')->format('Y-m-d H:i:s');
            $inserted = DB::table('content_story_destinations')->insertOrIgnore([
                'source_destination_id' => $source->source_destination_id,
                'content_id' => $source->content_id,
                'social_account_id' => $source->social_account_id,
                'provider' => $source->provider,
                'media_url' => $mediaUrl,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created += (int) $inserted;
        }

        if ($dryRun) {
            $this->info('Dry-run complete; no Story rows or jobs were created.');
            return self::SUCCESS;
        }

        if ($created > 0) {
            $this->info("Created {$created} new Story job(s) from already-published Smart Autocut posts.");
        }

        // Pending is claimed atomically before dispatch. Duplicate scheduler runs/jobs cannot both claim it.
        $pending = DB::table('content_story_destinations')
            ->where('status', 'pending')
            ->where('attempts', '<', 12)
            ->orderBy('id')
            ->limit(20)
            ->get(['id']);

        foreach ($pending as $story) {
            $now = now('UTC')->format('Y-m-d H:i:s');
            $claimed = DB::table('content_story_destinations')
                ->where('id', $story->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'queued',
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => $now,
                ]);

            if (! $claimed) {
                continue;
            }

            try {
                PublishSmartAutocutStory::dispatch((int) $story->id);
                $this->line("Queued Story job #{$story->id}.");
            } catch (Throwable $e) {
                DB::table('content_story_destinations')->where('id', $story->id)->update([
                    'status' => 'pending',
                    'error_code' => 'QUEUE_DISPATCH_FAILED',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
                ]);
                $this->error("Could not queue Story #{$story->id}: {$e->getMessage()}");
            }
        }

        DB::table('content_story_destinations')
            ->where('status', 'pending')
            ->where('attempts', '>=', 12)
            ->update([
                'status' => 'failed',
                'error_code' => 'PROCESSING_RETRY_LIMIT',
                'error_message' => 'Meta did not finish processing the Story container after 12 dispatches. Inspect the saved provider reference before any manual retry.',
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);

        return self::SUCCESS;
    }
}
