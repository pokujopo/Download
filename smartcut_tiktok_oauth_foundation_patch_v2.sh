#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="/www/wwwroot/smartcut-backend"
PHP_BIN="/www/server/php/84/bin/php"

if [[ "$(pwd)" != "$ROOT" || ! -f artisan || ! -f composer.json ]]; then
  echo "ERROR: Run from $ROOT. No changes made." >&2
  exit 1
fi
if [[ ! -x "$PHP_BIN" ]]; then
  echo "ERROR: PHP 8.4 executable was not found. No changes made." >&2
  exit 1
fi

# Fail before making changes if the source structure is not the one reviewed.
python3 <<'PREFLIGHT'
from pathlib import Path
import sys

required = {
    'routes/api.php': ["use App\\Http\\Controllers\\Social\\YouTubeOAuthController;", "Route::get('/social/youtube/connect', [YouTubeOAuthController::class, 'connect']);"],
    'routes/web.php': ["use App\\Http\\Controllers\\Social\\YouTubeOAuthController;", "[YouTubeOAuthController::class, 'callback']"],
    'config/services.php': ["'youtube' => ["],
    'app/Console/Commands/ScheduleSmartAutocutRotation.php': [
        "$accounts = DB::table('social_accounts')",
        "// Respect the explicit YouTube auto-publishing safety gate.",
        "if (!(bool) config('services.youtube.autopublish_enabled', false))",
    ],
    'app/Models/SocialAccount.php': ["'access_token' => 'encrypted'", "'refresh_token' => 'encrypted'"],
}
for name, markers in required.items():
    p = Path(name)
    if not p.is_file():
        sys.exit(f'ERROR: Required file missing: {name}; no changes made.')
    text = p.read_text()
    for marker in markers:
        if marker not in text:
            sys.exit(f'ERROR: Expected marker missing in {name}: {marker}; no changes made.')
if Path('app/Domain/Social/Services/TikTokOAuthService.php').exists() or Path('app/Http/Controllers/Social/TikTokOAuthController.php').exists():
    sys.exit('ERROR: TikTok OAuth files already exist. This patch will not overwrite them.')
api = Path('routes/api.php').read_text()
if api.count("Route::get('/social/youtube/connect', [YouTubeOAuthController::class, 'connect']);") != 1:
    sys.exit('ERROR: YouTube connect route did not match exactly once; no changes made.')
sched = Path('app/Console/Commands/ScheduleSmartAutocutRotation.php').read_text()
if sched.count("// Respect the explicit YouTube auto-publishing safety gate.") != 1:
    sys.exit('ERROR: Expected YouTube safety-gate anchor did not match exactly once; no changes made.')
if "provider === 'tiktok'" in sched:
    sys.exit('ERROR: A TikTok scheduler gate already exists; inspect manually before continuing.')
print('Preflight passed; all expected markers found. No tokens were read.')
PREFLIGHT

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="storage/app/tiktok-oauth-foundation-${STAMP}"
mkdir -p "$BACKUP"
for file in \
  config/services.php routes/api.php routes/web.php \
  app/Console/Commands/ScheduleSmartAutocutRotation.php .env; do
  cp -a "$file" "$BACKUP/$(basename "$file")"
done
echo "Backup created: $BACKUP"

mkdir -p app/Domain/Social/Services app/Http/Controllers/Social

cat > app/Domain/Social/Services/TikTokOAuthService.php <<'PHP'
<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class TikTokOAuthService
{
    private const STATE_TTL = 600;
    private const BASE_URL = 'https://open.tiktokapis.com';
    private const REQUESTED_SCOPES = ['user.info.basic', 'video.publish'];

    public function createAuthorizationUrl(int $userId): string
    {
        $this->assertConfigured();

        $state = Str::random(64);
        Cache::put(
            $this->stateKey($state),
            ['user_id' => $userId],
            now()->addSeconds(self::STATE_TTL)
        );

        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'response_type' => 'code',
            'scope' => implode(',', self::REQUESTED_SCOPES),
            'redirect_uri' => config('services.tiktok.redirect_uri'),
            'state' => $state,
        ]);
    }

    public function handleCallback(string $state, ?string $code): SocialAccount
    {
        $this->assertConfigured();

        if ($state === '') {
            throw new RuntimeException('TikTok OAuth state is missing. Restart the connection flow.');
        }

        $oauth = Cache::pull($this->stateKey($state));
        if (!$oauth || empty($oauth['user_id'])) {
            throw new RuntimeException('TikTok OAuth state expired or is invalid. Restart the connection flow.');
        }
        if (!$code) {
            throw new RuntimeException('TikTok authorization code is missing.');
        }

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post(self::BASE_URL . '/v2/oauth/token/', [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.tiktok.redirect_uri'),
            ]);

        $tokenBody = $tokenResponse->json() ?? [];
        if (!$tokenResponse->successful() || !is_array($tokenBody)
            || !empty($tokenBody['error'])
            || empty($tokenBody['access_token'])
            || empty($tokenBody['refresh_token'])
            || !is_numeric($tokenBody['expires_in'] ?? null)) {
            $errorCode = is_array($tokenBody) ? (string) ($tokenBody['error'] ?? 'invalid_token_response') : 'invalid_token_response';
            throw new RuntimeException('TikTok token exchange failed (HTTP ' . $tokenResponse->status() . ', code ' . mb_substr($errorCode, 0, 80) . '). Check app credentials, redirect URI, and granted scopes.');
        }

        $profileResponse = Http::withToken((string) $tokenBody['access_token'])
            ->acceptJson()
            ->timeout(30)
            ->get(self::BASE_URL . '/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ]);
        $profileBody = $profileResponse->json() ?? [];
        $profile = is_array($profileBody) ? ($profileBody['data']['user'] ?? null) : null;
        $apiErrorCode = is_array($profileBody) ? ($profileBody['error']['code'] ?? 'ok') : 'invalid_response';
        $openId = $tokenBody['open_id'] ?? (is_array($profile) ? ($profile['open_id'] ?? null) : null);

        if (!$profileResponse->successful() || $apiErrorCode !== 'ok'
            || !is_array($profile) || empty($profile['open_id']) || empty($openId)
            || (string) $profile['open_id'] !== (string) $openId) {
            throw new RuntimeException('TikTok user profile lookup failed (HTTP ' . $profileResponse->status() . ', code ' . mb_substr((string) $apiErrorCode, 0, 80) . '). Verify that user.info.basic is enabled and granted.');
        }

        $grantedScopes = array_values(array_filter(array_map(
            static fn ($scope) => trim((string) $scope),
            explode(',', (string) ($tokenBody['scope'] ?? ''))
        )));
        $existing = SocialAccount::query()
            ->where('user_id', (int) $oauth['user_id'])
            ->where('provider', 'tiktok')
            ->where('provider_account_id', (string) $openId)
            ->first();

        $metadata = array_merge($existing?->metadata ?? [], [
            'open_id' => (string) $openId,
            'union_id' => $profile['union_id'] ?? null,
            'avatar_url' => $profile['avatar_url'] ?? null,
            'refresh_token_expires_at' => isset($tokenBody['refresh_expires_in']) && is_numeric($tokenBody['refresh_expires_in'])
                ? now()->addSeconds((int) $tokenBody['refresh_expires_in'])->toIso8601String()
                : null,
            'connected_at' => now()->toIso8601String(),
            'posting_ready' => in_array('video.publish', $grantedScopes, true),
        ]);

        return SocialAccount::updateOrCreate(
            [
                'user_id' => (int) $oauth['user_id'],
                'provider' => 'tiktok',
                'provider_account_id' => (string) $openId,
            ],
            [
                'username' => isset($profile['username']) ? (string) $profile['username'] : null,
                'display_name' => (string) ($profile['display_name'] ?? 'TikTok account'),
                'access_token' => (string) $tokenBody['access_token'],
                'refresh_token' => (string) $tokenBody['refresh_token'],
                'token_expires_at' => now()->addSeconds((int) $tokenBody['expires_in']),
                'status' => 'active',
                'scopes' => $grantedScopes,
                'metadata' => $metadata,
            ]
        );
    }

    public function creatorInfo(int $userId, int $socialAccountId): array
    {
        $account = SocialAccount::query()
            ->where('id', $socialAccountId)
            ->where('user_id', $userId)
            ->where('provider', 'tiktok')
            ->first();

        if (!$account) {
            throw new RuntimeException('TikTok social account not found for this user. Connect TikTok first.');
        }
        if (!$account->access_token) {
            throw new RuntimeException('TikTok access token is missing. Reconnect the account.');
        }

        $token = $this->ensureFreshAccessToken($account);
        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(self::BASE_URL . '/v2/post/publish/creator_info/query/', (object) []);

        $body = $response->json() ?? [];
        $errorCode = is_array($body) ? (string) ($body['error']['code'] ?? 'ok') : 'invalid_response';
        if (!$response->successful() || !is_array($body) || $errorCode !== 'ok' || !is_array($body['data'] ?? null)) {
            throw new RuntimeException('TikTok creator information query failed (HTTP ' . $response->status() . ', code ' . mb_substr($errorCode, 0, 80) . '). Confirm Content Posting API and video.publish permission are enabled for this TikTok app.');
        }

        $data = $body['data'];
        // Return only fields needed by the posting UI; never return credentials.
        return [
            'creator_username' => $data['creator_username'] ?? null,
            'creator_nickname' => $data['creator_nickname'] ?? null,
            'privacy_level_options' => array_values(is_array($data['privacy_level_options'] ?? null) ? $data['privacy_level_options'] : []),
            'comment_disabled' => (bool) ($data['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($data['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($data['stitch_disabled'] ?? false),
            'max_video_post_duration_sec' => isset($data['max_video_post_duration_sec']) ? (int) $data['max_video_post_duration_sec'] : null,
        ];
    }

    private function ensureFreshAccessToken(SocialAccount $account): string
    {
        if ($account->token_expires_at && $account->token_expires_at->isAfter(now()->addMinutes(5))) {
            return (string) $account->access_token;
        }

        if (!$account->refresh_token) {
            $account->update(['status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED]);
            throw new RuntimeException('TikTok reauthorization is required because the refresh token is missing. Reconnect the account.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post(self::BASE_URL . '/v2/oauth/token/', [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);
        $body = $response->json() ?? [];
        if (!$response->successful() || !is_array($body) || !empty($body['error'])
            || empty($body['access_token']) || !is_numeric($body['expires_in'] ?? null)) {
            $errorCode = is_array($body) ? (string) ($body['error'] ?? 'token_refresh_failed') : 'token_refresh_failed';
            if (in_array($errorCode, ['invalid_grant', 'invalid_token', 'refresh_token_expired'], true)) {
                $account->update(['status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED]);
            }
            throw new RuntimeException('TikTok token refresh failed (HTTP ' . $response->status() . ', code ' . mb_substr($errorCode, 0, 80) . '). Reconnect if the refresh token is no longer valid.');
        }

        $changes = [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => (string) ($body['refresh_token'] ?? $account->refresh_token),
            'token_expires_at' => now()->addSeconds((int) $body['expires_in']),
        ];
        if (!empty($body['scope'])) {
            $changes['scopes'] = array_values(array_filter(array_map('trim', explode(',', (string) $body['scope']))));
        }
        $account->update($changes);
        $account->refresh();

        return (string) $account->access_token;
    }

    private function assertConfigured(): void
    {
        foreach (['client_key', 'client_secret', 'redirect_uri'] as $key) {
            if (!config("services.tiktok.{$key}")) {
                throw new RuntimeException('TikTok OAuth is not configured. Set TIKTOK_CLIENT_KEY, TIKTOK_CLIENT_SECRET, and TIKTOK_REDIRECT_URI in the server environment.');
            }
        }

        $uri = (string) config('services.tiktok.redirect_uri');
        $parts = parse_url($uri);
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('TIKTOK_REDIRECT_URI must be a fixed HTTPS URL without query parameters or fragments.');
        }
    }

    private function stateKey(string $state): string
    {
        return 'tiktok_oauth:state:' . $state;
    }
}
PHP

cat > app/Http/Controllers/Social/TikTokOAuthController.php <<'PHP'
<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\TikTokOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TikTokOAuthController extends Controller
{
    public function __construct(private TikTokOAuthService $oauth)
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
            Log::warning('TikTok OAuth URL creation failed.', [
                'user_id' => $request->user()?->id,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect($this->frontendUrl() . '/settings/social-accounts?tiktok=cancelled');
        }

        try {
            $account = $this->oauth->handleCallback(
                state: (string) $request->query('state', ''),
                code: $request->query('code') ? (string) $request->query('code') : null,
            );

            $postingReady = in_array('video.publish', $account->scopes ?? [], true);
            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?tiktok=connected&account='
                . urlencode((string) $account->id)
                . '&posting_scope=' . ($postingReady ? 'granted' : 'missing')
            );
        } catch (Throwable $e) {
            Log::warning('TikTok OAuth callback failed.', [
                'exception' => class_basename($e),
            ]);

            return redirect($this->frontendUrl() . '/settings/social-accounts?tiktok=failed');
        }
    }

    public function creatorInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $data = $this->oauth->creatorInfo(
                userId: (int) $request->user()->id,
                socialAccountId: (int) $validated['account_id'],
            );

            return response()->json(['success' => true, 'creator' => $data]);
        } catch (Throwable $e) {
            Log::warning('TikTok creator info query failed.', [
                'user_id' => $request->user()?->id,
                'account_id' => (int) $validated['account_id'],
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
PHP

python3 <<'PATCH'
from pathlib import Path
import sys

# Add TikTok settings to Laravel services config.
p = Path('config/services.php')
s = p.read_text()
if "'tiktok' => [" not in s:
    idx = s.rfind('\n];')
    if idx < 0:
        sys.exit('ERROR: Could not locate final services config array terminator.')
    block = "\n\n    'tiktok' => [\n        'client_key' => env('TIKTOK_CLIENT_KEY'),\n        'client_secret' => env('TIKTOK_CLIENT_SECRET'),\n        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),\n    ],"
    s = s[:idx] + block + s[idx:]
    p.write_text(s)
    print('Added TikTok service configuration.')
else:
    print('TikTok service configuration already exists; left it unchanged.')

# Add authenticated API endpoints next to YouTube connect.
p = Path('routes/api.php')
s = p.read_text()
if 'use App\\Http\\Controllers\\Social\\TikTokOAuthController;' not in s:
    marker = 'use App\\Http\\Controllers\\Social\\YouTubeOAuthController;'
    if s.count(marker) != 1:
        sys.exit('ERROR: YouTube controller import anchor was not unique.')
    s = s.replace(marker, marker + '\nuse App\\Http\\Controllers\\Social\\TikTokOAuthController;', 1)
connect = "Route::get('/social/youtube/connect', [YouTubeOAuthController::class, 'connect']);"
if "Route::get('/social/tiktok/connect', [TikTokOAuthController::class, 'connect']);" not in s:
    if s.count(connect) != 1:
        sys.exit('ERROR: YouTube connect route anchor was not unique.')
    s = s.replace(connect, connect + "\n    Route::get('/social/tiktok/connect', [TikTokOAuthController::class, 'connect']);\n    Route::get('/social/tiktok/creator-info', [TikTokOAuthController::class, 'creatorInfo']);", 1)
p.write_text(s)
print('Registered authenticated TikTok connect/creator-info API routes.')

# Add public OAuth callback route to web.php.
p = Path('routes/web.php')
s = p.read_text()
if 'use App\\Http\\Controllers\\Social\\TikTokOAuthController;' not in s:
    marker = 'use App\\Http\\Controllers\\Social\\YouTubeOAuthController;'
    if s.count(marker) != 1:
        sys.exit('ERROR: YouTube controller import anchor in web routes was not unique.')
    s = s.replace(marker, marker + '\nuse App\\Http\\Controllers\\Social\\TikTokOAuthController;', 1)
callback = '''Route::get(
    '/social/youtube/callback',
    [YouTubeOAuthController::class, 'callback']
);'''
tiktok_callback = '''Route::get(
    '/social/tiktok/callback',
    [TikTokOAuthController::class, 'callback']
);'''
if tiktok_callback not in s:
    if s.count(callback) != 1:
        sys.exit('ERROR: YouTube callback route anchor was not unique.')
    s = s.replace(callback, callback + '\n\n/* TikTok Login Kit callback. */\n' + tiktok_callback, 1)
p.write_text(s)
print('Registered TikTok OAuth callback route.')

# Keep TikTok out of automatic rotation until its publish consent UI and publisher are complete.
# Find the unique comment by trimmed line, so harmless indentation differences cannot break the patch.
p = Path('app/Console/Commands/ScheduleSmartAutocutRotation.php')
s = p.read_text()
if "->reject(fn ($account) => $account->provider === 'tiktok')" not in s:
    lines = s.splitlines()
    matches = [i for i, line in enumerate(lines) if line.strip() == '// Respect the explicit YouTube auto-publishing safety gate.']
    if len(matches) != 1:
        sys.exit('ERROR: Current scheduler YouTube safety-gate anchor is not unique; refusing to change it.')
    i = matches[0]
    indent = lines[i][:len(lines[i]) - len(lines[i].lstrip())]
    guard = [
        indent + '// TikTok OAuth foundation only: its publisher and per-post consent UI are not enabled yet.',
        indent + '// Exclude TikTok from automatic rotation so no unsupported destination is created.',
        indent + '$accounts = $accounts',
        indent + "    ->reject(fn ($account) => $account->provider === 'tiktok')",
        indent + '    ->values();',
        '',
    ]
    lines[i:i] = guard
    p.write_text('\n'.join(lines) + '\n')
    print('Added TikTok scheduler exclusion before the existing YouTube gate.')
else:
    print('TikTok scheduler exclusion already exists; left it unchanged.')

PATCH

echo "===== PHP syntax checks ====="
for file in \
  app/Domain/Social/Services/TikTokOAuthService.php \
  app/Http/Controllers/Social/TikTokOAuthController.php \
  config/services.php routes/api.php routes/web.php \
  app/Console/Commands/ScheduleSmartAutocutRotation.php; do
  "$PHP_BIN" -l "$file"
done

echo "===== Refresh Laravel caches ====="
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:clear

echo "===== Verify TikTok routes ====="
"$PHP_BIN" artisan route:list --path=social/tiktok

echo
echo "TIKTOK_OAUTH_FOUNDATION_PATCH_V2=SUCCESS"
echo "Backup: $BACKUP"
echo "Safety: scheduler skips all TikTok accounts; this patch adds OAuth/profile preflight only, not publishing."
echo "Next setup: register this exact redirect URI in TikTok Developer Portal: https://smartcut.labill.online/social/tiktok/callback"
echo "Then set TIKTOK_CLIENT_KEY, TIKTOK_CLIENT_SECRET and TIKTOK_REDIRECT_URI in .env (do not share the secret)."
