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
