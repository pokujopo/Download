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
