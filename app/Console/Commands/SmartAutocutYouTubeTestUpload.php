<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Jobs\PublishSocialContent;
use App\Domain\Social\Enums\SocialProvider;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SmartAutocutYouTubeTestUpload extends Command
{
    protected $signature = 'smart-autocut:youtube-test-upload {content_id : Smart Autocut content ID} {social_account_id : Connected YouTube social account ID}';

    protected $description = 'Queue one Smart Autocut video to YouTube privately for integration testing.';

    public function handle(): int
    {
        if (strtolower((string) config('services.youtube.default_privacy_status', 'private')) !== 'private') {
            $this->error('Safety stop: set YOUTUBE_DEFAULT_PRIVACY_STATUS=private before running a test upload.');
            return self::FAILURE;
        }

        $contentId = (int) $this->argument('content_id');
        $accountId = (int) $this->argument('social_account_id');

        $content = DB::table('contents')
            ->where('id', $contentId)
            ->where('source', 'smart_autocut')
            ->first(['id', 'user_id']);

        if (!$content) {
            $this->error('Content not found, or it is not a Smart Autocut clip.');
            return self::FAILURE;
        }

        $account = SocialAccount::query()->find($accountId);
        if (!$account || $account->provider !== SocialProvider::YOUTUBE) {
            $this->error('The supplied social account ID is not a connected YouTube account.');
            return self::FAILURE;
        }
        if ($account->status->value !== 'active') {
            $this->error('The YouTube account is not active. Reconnect it before testing.');
            return self::FAILURE;
        }
        if ((int) $content->user_id !== (int) $account->user_id) {
            $this->error('Content and YouTube account belong to different users.');
            return self::FAILURE;
        }

        $hasVideo = DB::table('content_media')
            ->where('content_id', $contentId)
            ->where('type', 'video')
            ->whereNotNull('public_url')
            ->exists();
        if (!$hasVideo) {
            $this->error('The clip has no video media with a public URL.');
            return self::FAILURE;
        }

        if (DB::table('content_destinations')
            ->where('content_id', $contentId)
            ->where('social_account_id', $accountId)
            ->exists()) {
            $this->error('This clip already has a destination for this YouTube account. Choose a different clip to avoid duplicate uploads.');
            return self::FAILURE;
        }

        try {
            $destinationId = DB::transaction(function () use ($contentId, $account) {
                $exists = DB::table('content_destinations')
                    ->where('content_id', $contentId)
                    ->where('social_account_id', $account->id)
                    ->exists();
                if ($exists) {
                    throw new RuntimeException('A destination already exists for this clip and channel.');
                }

                $now = now('UTC')->format('Y-m-d H:i:s');
                return DB::table('content_destinations')->insertGetId([
                    'content_id' => $contentId,
                    'social_account_id' => $account->id,
                    'provider' => 'youtube',
                    'status' => 'scheduled',
                    'scheduled_at' => $now,
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

            PublishSocialContent::dispatch((int) $destinationId);

            $this->info("Queued private YouTube test upload. destination_id={$destinationId}; content_id={$contentId}; account_id={$accountId}.");
            $this->line('Check content_destinations.status, external_media_id and provider_response after the queue worker processes it.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Unable to queue test upload: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
