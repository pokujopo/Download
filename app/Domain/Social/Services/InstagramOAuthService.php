<?php

namespace App\Domain\Social\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class InstagramOAuthService
{
    private const STATE_TTL = 600;

    public function createAuthorizationUrl(int $userId): string
    {
        $state = Str::random(64);

        Cache::put(
            $this->stateKey($state),
            [
                'user_id' => $userId,
            ],
            now()->addSeconds(self::STATE_TTL)
        );

        $query = http_build_query([
            'client_id' => config('services.instagram.client_id'),
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'scope' => implode(',', [
                'instagram_business_basic',
                'instagram_business_content_publish',
            ]),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return 'https://www.instagram.com/oauth/authorize?' . $query;
    }

    public function handleCallback(
        string $state,
        ?string $code,
    ): SocialAccount {
        if ($state === '') {
            throw new RuntimeException(
                'Instagram OAuth state is missing.'
            );
        }

        $oauth = Cache::pull(
            $this->stateKey($state)
        );

        if (
            !$oauth ||
            empty($oauth['user_id'])
        ) {
            throw new RuntimeException(
                'Instagram OAuth session expired or is invalid.'
            );
        }

        if (!$code) {
            throw new RuntimeException(
                'Instagram authorization code is missing.'
            );
        }

        /*
         * Exchange authorization code for initial access token.
         */
        $tokenResponse = Http::asForm()
            ->post(
                'https://api.instagram.com/oauth/access_token',
                [
                    'client_id' => config('services.instagram.client_id'),
                    'client_secret' => config('services.instagram.client_secret'),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.instagram.redirect_uri'),
                    'code' => $code,
                ]
            )
            ->throw()
            ->json();

        $shortLivedToken =
            $tokenResponse['access_token'] ?? null;

        $instagramUserId =
            $tokenResponse['user_id'] ?? null;

        if (
            !$shortLivedToken ||
            !$instagramUserId
        ) {
            throw new RuntimeException(
                'Instagram token response is invalid.'
            );
        }

        /*
         * Exchange the initial token for a long-lived
         * Instagram User Access Token.
         */
        $longLivedResponse = Http::baseUrl(
            config(
                'services.instagram.base_url',
                'https://graph.instagram.com'
            )
        )
            ->acceptJson()
            ->timeout(30)
            ->get(
                '/access_token',
                [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => config(
                        'services.instagram.client_secret'
                    ),
                    'access_token' => $shortLivedToken,
                ]
            )
            ->throw()
            ->json();

        $accessToken =
            $longLivedResponse['access_token'] ?? null;

        $expiresIn =
            $longLivedResponse['expires_in'] ?? null;

        if (
            !$accessToken ||
            !is_numeric($expiresIn)
        ) {
            throw new RuntimeException(
                'Instagram long-lived token response is invalid.'
            );
        }

        $tokenExpiresAt = now()->addSeconds(
            (int) $expiresIn
        );

        /*
         * Verify the Instagram account using the
         * long-lived token.
         */
        $accountResponse = Http::baseUrl(
            config(
                'services.instagram.base_url',
                'https://graph.instagram.com'
            )
        )
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get(
                "/{$instagramUserId}",
                [
                    'fields' => 'user_id,username,name',
                ]
            )
            ->throw()
            ->json();

        return SocialAccount::updateOrCreate(
            [
                'user_id' => $oauth['user_id'],
                'provider' => 'instagram',
                'provider_account_id' => (string) $instagramUserId,
            ],
            [
                'username' =>
                    $accountResponse['username'] ?? null,

                'display_name' =>
                    $accountResponse['name'] ?? null,

                'access_token' =>
                    $accessToken,

                'refresh_token' => null,

                'token_expires_at' =>
                    $tokenExpiresAt,

                'status' => 'active',

                'scopes' => [
                    'instagram_business_basic',
                    'instagram_business_content_publish',
                ],

                'metadata' => [],
            ]
        );
    }

    private function stateKey(string $state): string
    {
        return "instagram_oauth:state:{$state}";
    }
}