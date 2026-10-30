#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(pwd)"
PHP_BIN="/www/server/php/84/bin/php"

if [[ ! -f "$APP_DIR/artisan" || ! -f "$APP_DIR/composer.json" ]]; then
  echo "ERROR: Run this script from /www/wwwroot/smartcut-backend (Laravel project root)." >&2
  exit 1
fi
if [[ ! -x "$PHP_BIN" ]]; then
  echo "ERROR: PHP 8.4 executable not found at $PHP_BIN" >&2
  exit 1
fi
if [[ ! -f routes/console.php || ! -f config/services.php ]]; then
  echo "ERROR: Expected Laravel scheduler/config files were not found. No changes made." >&2
  exit 1
fi

# Fail early if the project schema names used by the feature differ from what we know.
if ! grep -Rqs "Schema::create('content_media'\|Schema::table('content_media'" database/migrations; then
  echo "WARNING: Could not confirm content_media schema from migration source. Script will add files; verify the content_media.public_url column before enabling scheduler." >&2
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="storage/app/story-autopublish-backup-${STAMP}"
mkdir -p "$BACKUP_DIR"
for file in routes/console.php config/services.php .env; do
  cp -a "$file" "$BACKUP_DIR/$(basename "$file")"
done
for file in app/Console/Commands/DispatchSmartAutocutStories.php app/Jobs/PublishSmartAutocutStory.php; do
  if [[ -f "$file" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$file")"
    cp -a "$file" "$BACKUP_DIR/$file"
  fi
done
for file in database/migrations/*create_content_story_destinations_table.php; do
  if [[ -f "$file" ]]; then
    cp -a "$file" "$BACKUP_DIR/$(basename "$file")"
  fi
done

echo "Backup created: $BACKUP_DIR"
mkdir -p app/Console/Commands app/Jobs database/migrations

cat > database/migrations/2026_10_29_200000_create_content_story_destinations_table.php <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_story_destinations')) {
            return;
        }

        Schema::create('content_story_destinations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // One Story job per already-published feed destination prevents duplicate Stories.
            $table->unsignedBigInteger('source_destination_id')->unique();
            $table->unsignedBigInteger('content_id')->index();
            $table->unsignedBigInteger('social_account_id')->index();
            $table->string('provider', 32)->index();
            $table->text('media_url');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Instagram container ID or Facebook video-upload ID, used to safely resume processing.
            $table->string('provider_reference', 191)->nullable();
            $table->string('external_media_id', 191)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_story_destinations');
    }
};
PHP

cat > app/Console/Commands/DispatchSmartAutocutStories.php <<'PHP'
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
PHP

cat > app/Jobs/PublishSmartAutocutStory.php <<'PHP'
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PublishSmartAutocutStory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Avoid blind queue retries after Meta might have accepted a publish request.
    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(public readonly int $storyId)
    {
    }

    public function handle(): void
    {
        $now = now('UTC')->format('Y-m-d H:i:s');
        $claimed = DB::table('content_story_destinations')
            ->where('id', $this->storyId)
            ->where('status', 'queued')
            ->update([
                'status' => 'publishing',
                'processing_started_at' => $now,
                'updated_at' => $now,
            ]);

        if (! $claimed) {
            return;
        }

        $story = DB::table('content_story_destinations as s')
            ->join('social_accounts as a', 'a.id', '=', 's.social_account_id')
            ->where('s.id', $this->storyId)
            ->first([
                's.id', 's.provider', 's.media_url', 's.provider_reference',
                'a.provider_account_id', 'a.access_token', 'a.status as account_status',
            ]);

        if (! $story) {
            $this->failStory('SOCIAL_ACCOUNT_NOT_FOUND', 'The connected social account for this Story no longer exists.');
            return;
        }
        if ($story->account_status !== 'active') {
            $this->failStory('SOCIAL_ACCOUNT_INACTIVE', 'Reconnect or reactivate the social account before publishing Stories.');
            return;
        }
        if (! $story->provider_account_id || ! $story->access_token) {
            $this->failStory('SOCIAL_CREDENTIALS_MISSING', 'The connected social account is missing its provider ID or access token.');
            return;
        }
        if (! in_array($story->provider, ['facebook', 'instagram'], true)) {
            $this->failStory('UNSUPPORTED_PROVIDER', 'Only Facebook Page video Stories and Instagram video Stories are supported by this release.');
            return;
        }

        try {
            $version = (string) config('services.meta.graph_api_version', 'v26.0');
            if (! preg_match('/^v\d+\.\d+$/', $version)) {
                throw new \RuntimeException('services.meta.graph_api_version must look like v26.0.');
            }

            if ($story->provider === 'instagram') {
                $this->publishInstagramStory($story, $version);
            } else {
                $this->publishFacebookStory($story, $version);
            }
        } catch (Throwable $e) {
            Log::warning('Smart Autocut Story publishing failed.', [
                'story_id' => $this->storyId,
                'provider' => $story->provider,
                'error' => $e->getMessage(),
            ]);
            $this->failStory('META_STORY_PUBLISH_FAILED', mb_substr($e->getMessage(), 0, 2000));
        }
    }

    private function publishInstagramStory(object $story, string $version): void
    {
        $base = "https://graph.facebook.com/{$version}";
        $token = (string) $story->access_token;
        $accountId = rawurlencode((string) $story->provider_account_id);
        $containerId = $story->provider_reference;

        if (! $containerId) {
            $created = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
                ->post("{$base}/{$accountId}/media", [
                    'media_type' => 'STORIES',
                    'video_url' => $story->media_url,
                    'access_token' => $token,
                ]);
            $body = $this->jsonOrThrow($created, 'Instagram Story container creation');
            $containerId = $body['id'] ?? null;
            if (! is_string($containerId) || $containerId === '') {
                throw new \RuntimeException('Meta did not return an Instagram Story container ID.');
            }
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'provider_reference' => $containerId,
                'provider_response' => json_encode(['container_created' => true, 'container_id' => $containerId]),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        }

        // Give Meta a short processing window per job. If still processing, resume next minute
        // using the saved container ID rather than creating another container or another Story.
        $ready = false;
        for ($i = 0; $i < 8; $i++) {
            $statusResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
                ->get("{$base}/" . rawurlencode((string) $containerId), [
                    'fields' => 'status_code,status',
                    'access_token' => $token,
                ]);
            $status = $this->jsonOrThrow($statusResponse, 'Instagram Story container status check');
            $code = strtoupper((string) ($status['status_code'] ?? ''));

            if ($code === 'FINISHED') {
                $ready = true;
                break;
            }
            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new \RuntimeException('Instagram Story container processing ended with status ' . ($code ?: 'unknown') . '. ' . ($status['status'] ?? ''));
            }

            if ($i < 7) {
                sleep(5);
            }
        }

        if (! $ready) {
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'status' => 'pending',
                'error_code' => 'INSTAGRAM_CONTAINER_PROCESSING',
                'error_message' => 'Meta is still processing the same Story container; the scheduler will resume it without creating another container.',
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        $published = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
            ->post("{$base}/{$accountId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $token,
            ]);
        $body = $this->jsonOrThrow($published, 'Instagram Story publishing');
        $mediaId = $body['id'] ?? null;
        if (! is_string($mediaId) || $mediaId === '') {
            throw new \RuntimeException('Instagram accepted the publish request but returned no media ID; inspect Meta before manually retrying.');
        }

        $this->markPublished($mediaId, $body);
    }

    private function publishFacebookStory(object $story, string $version): void
    {
        $base = "https://graph.facebook.com/{$version}";
        $token = (string) $story->access_token;
        $pageId = rawurlencode((string) $story->provider_account_id);
        $videoId = $story->provider_reference;

        if (! $videoId) {
            $started = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
                ->post("{$base}/{$pageId}/video_stories", [
                    'upload_phase' => 'start',
                    'access_token' => $token,
                ]);
            $body = $this->jsonOrThrow($started, 'Facebook Story upload initialization');
            $videoId = $body['video_id'] ?? null;
            $uploadUrl = $body['upload_url'] ?? null;
            if (! is_string($videoId) || $videoId === '' || ! is_string($uploadUrl) || $uploadUrl === '') {
                throw new \RuntimeException('Meta did not return the Facebook Story video ID and upload URL.');
            }
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'provider_reference' => $videoId,
                'provider_response' => json_encode(['upload_started' => true, 'video_id' => $videoId]),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        } else {
            // Never guess or reconstruct the upload URL. If the first job failed after the start phase,
            // stop safely rather than creating a second remote upload session.
            throw new \RuntimeException('Facebook Story upload already has a saved video ID but did not finish. Automatic restart was stopped to prevent duplicate or inconsistent Story uploads. Inspect Meta and retry deliberately.');
        }

        $parts = parse_url($uploadUrl);
        if (! $parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), ['rupload.facebook.com'], true)) {
            throw new \RuntimeException('Meta returned an unexpected Facebook Story upload URL; refusing to send the media there.');
        }

        $uploaded = Http::withHeaders([
                'Authorization' => 'OAuth ' . $token,
                'file_url' => (string) $story->media_url,
            ])
            ->connectTimeout(15)->timeout(60)
            ->post($uploadUrl);
        $uploadBody = $this->jsonOrThrow($uploaded, 'Facebook Story hosted video upload');
        if (($uploadBody['success'] ?? false) !== true && ($uploadBody['success'] ?? null) !== 'true') {
            throw new \RuntimeException('Facebook video Story upload did not return success=true.');
        }

        $finished = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(45)
            ->post("{$base}/{$pageId}/video_stories", [
                'upload_phase' => 'finish',
                'video_id' => $videoId,
                'access_token' => $token,
            ]);
        $finishBody = $this->jsonOrThrow($finished, 'Facebook Story final publishing phase');
        if (($finishBody['success'] ?? false) !== true && ($finishBody['success'] ?? null) !== 'true') {
            throw new \RuntimeException('Facebook did not confirm the Story publish phase.');
        }

        $this->markPublished((string) $videoId, $finishBody);
    }

    private function jsonOrThrow($response, string $operation): array
    {
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || isset($body['error'])) {
            $error = is_array($body) ? ($body['error'] ?? $body) : [];
            $message = is_array($error) ? (string) ($error['message'] ?? '') : '';
            $code = is_array($error) ? ($error['code'] ?? null) : null;
            throw new \RuntimeException($operation . ' failed (HTTP ' . $response->status() . ', Meta code ' . ($code ?? 'n/a') . '): ' . ($message !== '' ? $message : mb_substr((string) $response->body(), 0, 1000)));
        }
        return $body;
    }

    private function markPublished(string $mediaId, array $response): void
    {
        DB::table('content_story_destinations')->where('id', $this->storyId)->update([
            'status' => 'published',
            'external_media_id' => $mediaId,
            'error_code' => null,
            'error_message' => null,
            'provider_response' => json_encode($response),
            'published_at' => now('UTC')->format('Y-m-d H:i:s'),
            'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
        Log::info('Smart Autocut Story published.', ['story_id' => $this->storyId, 'external_media_id' => $mediaId]);
    }

    private function failStory(string $code, string $message): void
    {
        DB::table('content_story_destinations')->where('id', $this->storyId)->update([
            'status' => 'failed',
            'error_code' => $code,
            'error_message' => mb_substr($message, 0, 2000),
            'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        // This callback runs for queue-level failures outside handle(). Preserve state if an API call
        // may already have reached Meta; do not blindly republish a Story.
        DB::table('content_story_destinations')->where('id', $this->storyId)
            ->whereIn('status', ['queued', 'publishing'])
            ->update([
                'status' => 'failed',
                'error_code' => 'QUEUE_JOB_FAILED',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
    }
}
PHP

python3 <<'PY'
from pathlib import Path

config_path = Path('config/services.php')
s = config_path.read_text()
marker = "'meta' => ["
if marker not in s:
    pos = s.rfind('];')
    if pos < 0:
        raise SystemExit('ERROR: Could not find final array terminator in config/services.php')
    block = "\n    /* Meta Graph API used for Facebook and Instagram Stories. */\n    'meta' => [\n        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v26.0'),\n        'stories_enabled' => filter_var(env('SMARTCUT_STORIES_ENABLED', false), FILTER_VALIDATE_BOOL),\n        'stories_enabled_since' => env('SMARTCUT_STORIES_ENABLED_SINCE'),\n    ],\n"
    s = s[:pos] + block + s[pos:]
    config_path.write_text(s)
    print('Added services.meta configuration')
else:
    start = s.index(marker)
    close = s.find('],', start)
    if close < 0:
        raise SystemExit('ERROR: Could not safely find the end of the services.meta array')
    close += 2
    block = s[start:close]
    additions = []
    if "'graph_api_version'" not in block:
        additions.append("        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v26.0'),")
    if "'stories_enabled'" not in block:
        additions.append("        'stories_enabled' => filter_var(env('SMARTCUT_STORIES_ENABLED', false), FILTER_VALIDATE_BOOL),")
    if "'stories_enabled_since'" not in block:
        additions.append("        'stories_enabled_since' => env('SMARTCUT_STORIES_ENABLED_SINCE'),")
    if additions:
        close_without_comma = block.rfind(']')
        before = block[:close_without_comma].rstrip()
        if before and not before.endswith(','):
            before += ','
        block = before + '\n' + '\n'.join(additions) + '\n    ],'
        s = s[:start] + block + s[close:]
        config_path.write_text(s)
        print('Updated services.meta configuration')
    else:
        print('services.meta configuration already complete')

console_path = Path('routes/console.php')
s = console_path.read_text()
line = "\nSchedule::command('smart-autocut:dispatch-stories')\n    ->everyMinute()\n    ->withoutOverlapping();\n"
if "smart-autocut:dispatch-stories" not in s:
    s = s.rstrip() + "\n" + line
    console_path.write_text(s)
    print('Scheduled Story dispatcher every minute')
else:
    print('Story dispatcher schedule already exists; left it unchanged')
PY

# Enable on first install, but only for new posts published after this exact UTC cutoff.
INSTALL_SINCE="$(date -u '+%Y-%m-%d %H:%M:%S')"
python3 - "$INSTALL_SINCE" <<'PYENV'
from pathlib import Path
import sys

p = Path('.env')
lines = p.read_text().splitlines() if p.exists() else []
values = {}
for line in lines:
    if line and not line.lstrip().startswith('#') and '=' in line:
        k, v = line.split('=', 1)
        values[k.strip()] = v
for key, default in [
    ('META_GRAPH_API_VERSION', 'v26.0'),
    ('SMARTCUT_STORIES_ENABLED', 'true'),
    ('SMARTCUT_STORIES_ENABLED_SINCE', sys.argv[1]),
]:
    if key not in values or values[key].strip() == '':
        lines.append(f'{key}={default}')
p.write_text('\n'.join(lines).rstrip() + '\n')
print('Meta Story configuration initialized (existing values preserved).')
PYENV

echo "===== Syntax check ====="
for file in \
  database/migrations/2026_10_29_200000_create_content_story_destinations_table.php \
  app/Console/Commands/DispatchSmartAutocutStories.php \
  app/Jobs/PublishSmartAutocutStory.php \
  config/services.php routes/console.php; do
  "$PHP_BIN" -l "$file"
done

echo "===== Apply database migration ====="
"$PHP_BIN" artisan migrate --force

echo "===== Clear config/scheduler caches ====="
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan schedule:clear-cache || true
"$PHP_BIN" artisan queue:restart

echo "===== Verify command and scheduler ====="
"$PHP_BIN" artisan list --raw | grep -F 'smart-autocut:dispatch-stories'
"$PHP_BIN" artisan smart-autocut:dispatch-stories --dry-run

echo
cat <<EOF
PATCH FINISHED
Backup: $BACKUP_DIR

New behavior:
- Runs every minute.
- Only targets Facebook/Instagram destinations already marked published for contents.source=smart_autocut.
- Creates one Story record per source destination (unique constraint prevents duplicate records).
- Uses the existing public video URL from content_media.public_url.
- Stories publish separately; a Story error does not change the original post's status.
- Instagram processing can resume with the saved container ID.
- Does not modify the Colab downloader or existing feed/Reel publisher.

Important: first install enables Stories only for posts with content_destinations.updated_at at/after SMARTCUT_STORIES_ENABLED_SINCE; historical published posts are intentionally excluded. Set SMARTCUT_STORIES_ENABLED=false to pause it. The publisher uses SocialAccount.access_token and SocialAccount.provider_account_id, and requires Meta permissions for Page/Instagram Story publishing. This release supports video Stories only.
EOF
