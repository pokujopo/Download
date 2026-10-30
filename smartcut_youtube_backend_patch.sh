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

cd "$APP_DIR"

for key in GOOGLE_YOUTUBE_CLIENT_ID GOOGLE_YOUTUBE_CLIENT_SECRET GOOGLE_YOUTUBE_REDIRECT_URI; do
  if ! grep -Eq "^${key}=.+" .env; then
    echo "ERROR: .env is missing a non-empty ${key}. Add it before running this patch." >&2
    exit 1
  fi
done

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="storage/app/youtube-integration-backup-${STAMP}"
mkdir -p "$BACKUP_DIR"
for file in \
  composer.json composer.lock \
  app/Domain/Social/Enums/SocialProvider.php \
  app/Providers/AppServiceProvider.php \
  config/services.php routes/api.php routes/web.php \
  app/Console/Commands/ScheduleSmartAutocutRotation.php; do
  if [[ -f "$file" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$file")"
    cp -a "$file" "$BACKUP_DIR/$file"
  fi
done

echo "Backup created: $BACKUP_DIR"

echo "===== Installing Google API PHP client ====="
if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: Composer was not found in PATH. No application files have been changed yet." >&2
  exit 1
fi
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_PROCESS_TIMEOUT=600 "$PHP_BIN" "$(command -v composer)" require 'google/apiclient:^2.20' --no-interaction

echo "===== Creating YouTube OAuth and publisher files ====="
mkdir -p app/Domain/Social/Services \
  app/Http/Controllers/Social \
  app/Infrastructure/Social/YouTube \
  app/Console/Commands

cat > app/Domain/Social/Services/YouTubeOAuthService.php <<'PHP'
<?php

namespace App\Domain\Social\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class YouTubeOAuthService
{
    private const STATE_TTL = 600;
    private const UPLOAD_SCOPE = 'https://www.googleapis.com/auth/youtube.upload';
    private const READ_SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    public function createAuthorizationUrl(int $userId): string
    {
        $this->assertConfigured();

        $state = Str::random(64);
        Cache::put(
            $this->stateKey($state),
            ['user_id' => $userId],
            now()->addSeconds(self::STATE_TTL)
        );

        return 'https://accounts.google.com/o/oauth2/v2/auth?'
            . http_build_query([
                'client_id' => config('services.youtube.client_id'),
                'redirect_uri' => config('services.youtube.redirect_uri'),
                'response_type' => 'code',
                'scope' => self::UPLOAD_SCOPE . ' ' . self::READ_SCOPE,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
                'state' => $state,
            ]);
    }

    public function handleCallback(string $state, ?string $code): SocialAccount
    {
        $this->assertConfigured();

        if ($state === '') {
            throw new RuntimeException('YouTube OAuth state is missing.');
        }

        $oauth = Cache::pull($this->stateKey($state));
        if (!$oauth || empty($oauth['user_id'])) {
            throw new RuntimeException('YouTube OAuth session expired or is invalid. Please reconnect.');
        }

        if (!$code) {
            throw new RuntimeException('YouTube authorization code is missing.');
        }

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.youtube.client_id'),
                'client_secret' => config('services.youtube.client_secret'),
                'redirect_uri' => config('services.youtube.redirect_uri'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->throw()
            ->json();

        $accessToken = $tokenResponse['access_token'] ?? null;
        if (!$accessToken || !is_numeric($tokenResponse['expires_in'] ?? null)) {
            throw new RuntimeException('Google did not return a valid YouTube access token.');
        }

        $channelResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get('https://youtube.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ])
            ->throw()
            ->json();

        $channel = $channelResponse['items'][0] ?? null;
        $channelId = $channel['id'] ?? null;
        $channelTitle = $channel['snippet']['title'] ?? null;

        if (!$channelId || !$channelTitle) {
            throw new RuntimeException('No YouTube channel was returned for this Google account. Check that the Google account has a YouTube channel.');
        }

        $existing = SocialAccount::query()
            ->where('user_id', (int) $oauth['user_id'])
            ->where('provider', 'youtube')
            ->where('provider_account_id', (string) $channelId)
            ->first();

        $refreshToken = $tokenResponse['refresh_token'] ?? $existing?->refresh_token;
        if (!$refreshToken) {
            throw new RuntimeException('Google did not return a refresh token. Reconnect and approve access when prompted so scheduled uploads can work offline.');
        }

        $scopes = preg_split('/\s+/', trim((string) ($tokenResponse['scope'] ?? (self::UPLOAD_SCOPE . ' ' . self::READ_SCOPE)))) ?: [];
        $snippet = $channel['snippet'] ?? [];

        return SocialAccount::updateOrCreate(
            [
                'user_id' => (int) $oauth['user_id'],
                'provider' => 'youtube',
                'provider_account_id' => (string) $channelId,
            ],
            [
                'username' => $snippet['customUrl'] ?? $channelTitle,
                'display_name' => $channelTitle,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds((int) $tokenResponse['expires_in']),
                'status' => 'active',
                'scopes' => $scopes,
                'metadata' => [
                    'channel_id' => (string) $channelId,
                    'channel_title' => $channelTitle,
                    'thumbnail_url' => $snippet['thumbnails']['default']['url'] ?? null,
                    'connected_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    private function assertConfigured(): void
    {
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (!config("services.youtube.{$key}")) {
                throw new RuntimeException('YouTube OAuth is not configured. Check GOOGLE_YOUTUBE_CLIENT_ID, GOOGLE_YOUTUBE_CLIENT_SECRET, and GOOGLE_YOUTUBE_REDIRECT_URI.');
            }
        }
    }

    private function stateKey(string $state): string
    {
        return "youtube_oauth:state:{$state}";
    }
}
PHP

cat > app/Http/Controllers/Social/YouTubeOAuthController.php <<'PHP'
<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\YouTubeOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class YouTubeOAuthController extends Controller
{
    public function __construct(private YouTubeOAuthService $oauth)
    {
    }

    public function connect(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'url' => $this->oauth->createAuthorizationUrl((int) $request->user()->id),
            ]);
        } catch (Throwable $e) {
            Log::warning('YouTube OAuth URL creation failed.', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'YouTube connection is not configured correctly.',
            ], 422);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect($this->frontendUrl() . '/settings/social-accounts?youtube=cancelled');
        }

        try {
            $account = $this->oauth->handleCallback(
                state: (string) $request->query('state', ''),
                code: $request->query('code') ? (string) $request->query('code') : null,
            );

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?youtube=connected&account='
                . urlencode((string) $account->id)
            );
        } catch (Throwable $e) {
            Log::warning('YouTube OAuth callback failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect($this->frontendUrl() . '/settings/social-accounts?youtube=failed');
        }
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
PHP

cat > app/Infrastructure/Social/YouTube/YouTubeApiClient.php <<'PHP'
<?php

namespace App\Infrastructure\Social\YouTube;

use App\Domain\Social\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class YouTubeApiClient
{
    private const CHUNK_SIZE = 1048576;

    public function uploadVideo(
        SocialAccount $account,
        string $publicUrl,
        string $caption,
        array $metadata = [],
    ): array {
        $accessToken = $this->ensureAccessToken($account);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'smartcut-youtube-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary video file on the server.');
        }

        try {
            $this->downloadVideo($publicUrl, $temporaryPath);
            $fileSize = filesize($temporaryPath);
            if (!is_int($fileSize) || $fileSize <= 0) {
                throw new RuntimeException('The source video downloaded as an empty file.');
            }

            $client = new Client();
            $client->setApplicationName((string) config('app.name', 'Smart Autocut'));
            $client->setClientId((string) config('services.youtube.client_id'));
            $client->setClientSecret((string) config('services.youtube.client_secret'));
            $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
            $client->setAccessToken([
                'access_token' => $accessToken,
                'created' => time(),
                'expires_in' => 3600,
            ]);
            $client->setHttpClient(new \GuzzleHttp\Client([
                'timeout' => 1200,
                'connect_timeout' => 30,
            ]));

            $title = trim((string) ($metadata['youtube_title'] ?? $metadata['title'] ?? ''));
            if ($title === '') {
                $title = trim(preg_replace('/\s+/u', ' ', strip_tags($caption)) ?? '');
            }
            if ($title === '') {
                $title = 'Smart Autocut Short';
            }
            $title = Str::limit($title, 100, '');
            $description = Str::limit(trim($caption), 5000, '');
            $privacyStatus = strtolower((string) config('services.youtube.default_privacy_status', 'private'));
            if (!in_array($privacyStatus, ['private', 'unlisted', 'public'], true)) {
                throw new RuntimeException('YOUTUBE_DEFAULT_PRIVACY_STATUS must be private, unlisted, or public.');
            }

            $snippet = new VideoSnippet();
            $snippet->setTitle($title);
            $snippet->setDescription($description);
            $snippet->setCategoryId((string) config('services.youtube.category_id', '22'));

            $tags = $metadata['youtube_tags'] ?? $metadata['tags'] ?? [];
            if (is_array($tags)) {
                $tags = array_values(array_filter(array_map(
                    static fn ($tag) => is_string($tag) ? trim($tag) : '',
                    $tags
                ), static fn (string $tag) => $tag !== ''));
                if ($tags !== []) {
                    $snippet->setTags(array_slice($tags, 0, 30));
                }
            }

            $videoStatus = new VideoStatus();
            $videoStatus->setPrivacyStatus($privacyStatus);

            $video = new Video();
            $video->setSnippet($snippet);
            $video->setStatus($videoStatus);

            $client->setDefer(true);
            try {
                $youtube = new YouTube($client);
                $insertRequest = $youtube->videos->insert('snippet,status', $video);
                $upload = new MediaFileUpload(
                    $client,
                    $insertRequest,
                    'video/mp4',
                    null,
                    true,
                    self::CHUNK_SIZE
                );
                $upload->setFileSize($fileSize);

                $handle = fopen($temporaryPath, 'rb');
                if ($handle === false) {
                    throw new RuntimeException('Could not read the temporary video file.');
                }

                try {
                    $uploadStatus = false;
                    while (!$uploadStatus && !feof($handle)) {
                        $chunk = fread($handle, self::CHUNK_SIZE);
                        if ($chunk === false) {
                            throw new RuntimeException('Could not read the next video upload chunk.');
                        }
                        $uploadStatus = $upload->nextChunk($chunk);
                    }
                } finally {
                    fclose($handle);
                }
            } finally {
                $client->setDefer(false);
            }

            if (!is_array($uploadStatus) || empty($uploadStatus['id'])) {
                throw new RuntimeException('YouTube upload ended without returning a video ID.');
            }

            return [
                'video_id' => (string) $uploadStatus['id'],
                'title' => $uploadStatus['snippet']['title'] ?? $title,
                'privacy_status' => $uploadStatus['status']['privacyStatus'] ?? $privacyStatus,
                'url' => 'https://www.youtube.com/watch?v=' . $uploadStatus['id'],
            ];
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function ensureAccessToken(SocialAccount $account): string
    {
        if (!$account->access_token) {
            $account->update(['status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED]);
            throw new RuntimeException('YouTube reauthorization is required because the access token is missing. Reconnect the channel.');
        }

        if ($account->token_expires_at && $account->token_expires_at->isAfter(now()->addMinutes(5))) {
            return (string) $account->access_token;
        }

        if (!$account->refresh_token) {
            $account->update(['status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED]);
            throw new RuntimeException('YouTube reauthorization is required because the refresh token is missing. Reconnect the channel.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.youtube.client_id'),
                'client_secret' => config('services.youtube.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        $body = $response->json() ?? [];
        if (!$response->successful()) {
            if (($body['error'] ?? null) === 'invalid_grant') {
                $account->update(['status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED]);
                throw new RuntimeException('YouTube reauthorization is required. Reconnect the channel.');
            }
            throw new RuntimeException(
                'YouTube token refresh failed (HTTP ' . $response->status() . ').',
                $response->status()
            );
        }

        if (empty($body['access_token']) || !is_numeric($body['expires_in'] ?? null)) {
            throw new RuntimeException('Google returned an invalid refreshed YouTube token.');
        }

        $account->update([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $body['expires_in']),
        ]);
        $account->refresh();

        return (string) $account->access_token;
    }

    private function downloadVideo(string $url, string $destinationPath): void
    {
        $parts = parse_url($url);
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('YouTube source video URL must be an HTTPS public URL.');
        }

        $host = strtolower((string) $parts['host']);
        if (
            in_array($host, ['localhost', 'localhost.localdomain'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')
        ) {
            throw new RuntimeException('Local or internal URLs cannot be used as YouTube source video URLs.');
        }

        $response = Http::withOptions([
                'sink' => $destinationPath,
                'allow_redirects' => ['max' => 3],
            ])
            ->timeout(900)
            ->connectTimeout(30)
            ->get($url);
        $response->throw();
    }
}
PHP

cat > app/Infrastructure/Social/YouTube/YouTubeProvider.php <<'PHP'
<?php

namespace App\Infrastructure\Social\YouTube;

use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\Contracts\SocialProviderContract;
use App\Domain\Social\DTOs\PublishContext;
use App\Domain\Social\Enums\SocialProvider;
use App\Models\SocialAccount;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\RequestException as LaravelRequestException;
use RuntimeException;
use Throwable;

final class YouTubeProvider implements SocialProviderContract
{
    public function __construct(private YouTubeApiClient $apiClient)
    {
    }

    public function validate(PublishContext $context): void
    {
        $account = $this->account($context);

        if ($account->provider !== SocialProvider::YOUTUBE) {
            throw new RuntimeException('Social account is not a YouTube account.');
        }
        if (!$account->access_token) {
            throw new RuntimeException('YouTube access token is missing. Reconnect the channel.');
        }
        if (!$account->provider_account_id) {
            throw new RuntimeException('YouTube channel ID is missing.');
        }
        if (!$this->videoMedia($context)) {
            throw new RuntimeException('YouTube publishing requires a video with a public URL.');
        }
    }

    public function publish(PublishContext $context): PublishResult
    {
        $account = $this->account($context);
        $media = $this->videoMedia($context);

        try {
            $response = $this->apiClient->uploadVideo(
                account: $account,
                publicUrl: (string) $media['public_url'],
                caption: $context->caption,
                metadata: is_array($media['metadata'] ?? null) ? $media['metadata'] : [],
            );

            return new PublishResult(
                success: true,
                externalMediaId: $response['video_id'] ?? null,
                providerResponse: $response,
            );
        } catch (Throwable $e) {
            $status = (int) $e->getCode();
            if ($e instanceof LaravelRequestException) {
                $status = $e->response->status();
            }
            $message = $e->getMessage();
            $lowerMessage = strtolower($message);

            if (
                $status === 401
                || str_contains($lowerMessage, 'reauthorization is required')
                || str_contains($lowerMessage, 'invalid_grant')
            ) {
                $errorCode = 'INVALID_ACCESS_TOKEN';
            } elseif (
                $status === 403
                && (
                    str_contains($lowerMessage, 'insufficient authentication scopes')
                    || str_contains($lowerMessage, 'insufficientpermissions')
                    || str_contains($lowerMessage, 'insufficient permission')
                )
            ) {
                $errorCode = 'MISSING_PERMISSION';
            } else {
                $errorCode = 'YOUTUBE_PUBLISH_FAILED';
            }

            $retryable = $e instanceof ConnectException
                || $e instanceof LaravelConnectionException
                || in_array($status, [408, 429, 500, 502, 503, 504], true);

            if ($e instanceof GoogleServiceException && $status === 0) {
                $retryable = false;
            }

            return new PublishResult(
                success: false,
                errorCode: $errorCode,
                errorMessage: mb_substr($message, 0, 1000),
                providerResponse: [
                    'http_status' => $status ?: null,
                    'exception' => class_basename($e),
                ],
                retryable: $retryable,
            );
        }
    }

    public function getStatus(PublishContext $context): PublishResult
    {
        throw new RuntimeException('YouTube post status checking is not implemented yet.');
    }

    public function disconnect(string $providerAccountId): void
    {
        // OAuth revocation/disconnect can be added separately.
    }

    private function account(PublishContext $context): SocialAccount
    {
        $account = SocialAccount::query()->find($context->socialAccountId);
        if (!$account) {
            throw new RuntimeException('Social account not found.');
        }

        return $account;
    }

    private function videoMedia(PublishContext $context): ?array
    {
        foreach ($context->media as $media) {
            if (($media['type'] ?? null) === 'video' && !empty($media['public_url'])) {
                return $media;
            }
        }

        return null;
    }
}
PHP

cat > app/Console/Commands/SmartAutocutYouTubeTestUpload.php <<'PHP'
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
PHP

python3 <<'PY'
from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    p = Path(path)
    s = p.read_text()
    if new in s:
        print(f"Already applied: {label}")
        return
    if old not in s:
        raise SystemExit(f"ERROR: Could not safely patch {path} ({label}); original files are backed up.")
    p.write_text(s.replace(old, new, 1))
    print(f"Patched: {label}")


replace_once(
    'app/Domain/Social/Enums/SocialProvider.php',
    "    case TIKTOK = 'tiktok';",
    "    case TIKTOK = 'tiktok';\n    case YOUTUBE = 'youtube';",
    'YouTube provider enum'
)

replace_once(
    'app/Providers/AppServiceProvider.php',
    "use App\\Infrastructure\\Social\\Instagram\\InstagramProvider;",
    "use App\\Infrastructure\\Social\\Instagram\\InstagramProvider;\nuse App\\Infrastructure\\Social\\YouTube\\YouTubeProvider;",
    'YouTube provider import'
)
replace_once(
    'app/Providers/AppServiceProvider.php',
    "                'instagram' => $app->make(\n                    InstagramProvider::class\n                ),\n            ])",
    "                'instagram' => $app->make(\n                    InstagramProvider::class\n                ),\n\n                'youtube' => $app->make(\n                    YouTubeProvider::class\n                ),\n            ])",
    'YouTube provider resolver registration'
)

services = Path('config/services.php')
s = services.read_text()
youtube_block = "\n    /*\n    |--------------------------------------------------------------------------\n    | YouTube Data API\n    |--------------------------------------------------------------------------\n    */\n    'youtube' => [\n        'client_id' => env('GOOGLE_YOUTUBE_CLIENT_ID'),\n        'client_secret' => env('GOOGLE_YOUTUBE_CLIENT_SECRET'),\n        'redirect_uri' => env('GOOGLE_YOUTUBE_REDIRECT_URI'),\n        'default_privacy_status' => env('YOUTUBE_DEFAULT_PRIVACY_STATUS', 'private'),\n        'category_id' => env('YOUTUBE_CATEGORY_ID', '22'),\n        'autopublish_enabled' => filter_var(env('YOUTUBE_AUTOPUBLISH_ENABLED', false), FILTER_VALIDATE_BOOL),\n    ],\n"
if "'autopublish_enabled' => filter_var(env('YOUTUBE_AUTOPUBLISH_ENABLED'" not in s:
    marker = '\n];'
    pos = s.rfind(marker)
    if pos < 0:
        raise SystemExit('ERROR: Could not safely patch config/services.php; missing final array terminator.')
    s = s[:pos] + youtube_block + s[pos:]
    services.write_text(s)
    print('Patched: YouTube services configuration')
else:
    print('Already applied: YouTube services configuration')

replace_once(
    'routes/api.php',
    'use App\\Http\\Controllers\\Social\\InstagramOAuthController;',
    'use App\\Http\\Controllers\\Social\\InstagramOAuthController;\nuse App\\Http\\Controllers\\Social\\YouTubeOAuthController;',
    'YouTube API controller import'
)
replace_once(
    'routes/api.php',
    "    Route::get('/social/instagram/connect',[InstagramOAuthController::class, 'redirect']);",
    "    Route::get('/social/instagram/connect',[InstagramOAuthController::class, 'redirect']);\n    Route::get('/social/youtube/connect', [YouTubeOAuthController::class, 'connect']);",
    'YouTube connect API route'
)

replace_once(
    'routes/web.php',
    'use App\\Http\\Controllers\\Social\\InstagramOAuthController;',
    'use App\\Http\\Controllers\\Social\\InstagramOAuthController;\nuse App\\Http\\Controllers\\Social\\YouTubeOAuthController;',
    'YouTube web controller import'
)
replace_once(
    'routes/web.php',
    '/*\n|--------------------------------------------------------------------------\n| Application Pages',
    "/*\n|--------------------------------------------------------------------------\n| YouTube OAuth Callback\n|--------------------------------------------------------------------------\n*/\n\nRoute::get(\n    '/social/youtube/callback',\n    [YouTubeOAuthController::class, 'callback']\n);\n\n/*\n|--------------------------------------------------------------------------\n| Application Pages",
    'YouTube OAuth callback route'
)

replace_once(
    'app/Console/Commands/ScheduleSmartAutocutRotation.php',
    "            foreach ($accounts as $account) {\n                $accountScheduled = 0;",
    "            foreach ($accounts as $account) {\n                // YouTube remains opt-in until a private integration test succeeds.\n                if (\n                    $account->provider === 'youtube'\n                    && ! config('services.youtube.autopublish_enabled', false)\n                ) {\n                    $this->warn(\n                        \"Account {$account->id} (youtube): automatic scheduling is disabled until YouTube private test upload is verified.\"\n                    );\n                    continue;\n                }\n\n                $accountScheduled = 0;",
    'YouTube scheduler safety gate'
)
PY

echo "===== Clearing Laravel caches ====="
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear

echo "===== PHP syntax checks ====="
for file in \
  app/Domain/Social/Enums/SocialProvider.php \
  app/Domain/Social/Services/YouTubeOAuthService.php \
  app/Http/Controllers/Social/YouTubeOAuthController.php \
  app/Infrastructure/Social/YouTube/YouTubeApiClient.php \
  app/Infrastructure/Social/YouTube/YouTubeProvider.php \
  app/Providers/AppServiceProvider.php \
  app/Console/Commands/ScheduleSmartAutocutRotation.php \
  app/Console/Commands/SmartAutocutYouTubeTestUpload.php \
  config/services.php routes/api.php routes/web.php; do
  "$PHP_BIN" -l "$file"
done

echo "===== Verifying routes and command ====="
"$PHP_BIN" artisan route:list --path=social/youtube
"$PHP_BIN" artisan list --raw | grep -F 'smart-autocut:youtube-test-upload' || true

echo "===== Patch completed ====="
echo "Backup: $BACKUP_DIR"
echo "Safety defaults: YOUTUBE_DEFAULT_PRIVACY_STATUS=private, YOUTUBE_AUTOPUBLISH_ENABLED=false"
echo "Important: do not enable public auto-publishing until the private upload test succeeds."
