<?php

namespace App\Domain\Social\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class FacebookOAuthService
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
            'client_id' => config('services.facebook.client_id'),
            'redirect_uri' => config('services.facebook.redirect_uri'),
            'state' => $state,
            'config_id' => config('services.facebook.login_config_id'),
        ]);

        return 'https://www.facebook.com/v26.0/dialog/oauth?' . $query;
    }

    public function handleCallback(
        string $state,
        ?string $code,
    ): array {
        if ($state === '') {
            throw new RuntimeException(
                'Facebook OAuth state is missing.'
            );
        }

        $oauth = Cache::pull($this->stateKey($state));

        if (!$oauth || empty($oauth['user_id'])) {
            throw new RuntimeException(
                'Facebook OAuth session expired or is invalid.'
            );
        }

        if (!$code) {
            throw new RuntimeException(
                'Facebook authorization code is missing.'
            );
        }

        $response = Http::asForm()
            ->post(
                'https://graph.facebook.com/v26.0/oauth/access_token',
                [
                    'client_id' => config('services.facebook.client_id'),
                    'client_secret' => config('services.facebook.client_secret'),
                    'redirect_uri' => config('services.facebook.redirect_uri'),
                    'code' => $code,
                ]
            )
            ->throw()
            ->json();

        $accessToken = $response['access_token'] ?? null;

        if (!$accessToken) {
            throw new RuntimeException(
                'Facebook access token was not returned.'
            );
        }

        $connectionId = Str::random(64);

        $pages = $this->discoverPages($accessToken);

        Cache::put(
            $this->connectionKey($connectionId),
            [
                'user_id' => $oauth['user_id'],
            ],
            now()->addSeconds(self::STATE_TTL)
        );

        Cache::put(
            $this->pagesKey($connectionId),
            $this->protectPageTokens($pages),
            now()->addSeconds(self::STATE_TTL)
        );

        return [
            'connection_id' => $connectionId,
            'pages' => $this->publicPages($pages),
        ];
    }

    public function getPageOptions(
        int $userId,
        string $connectionId,
    ): array {
        $connection = Cache::get(
            $this->connectionKey($connectionId)
        );

        $this->assertConnectionOwner(
            $connection,
            $userId
        );

        $pages = Cache::get(
            $this->pagesKey($connectionId),
            []
        );

        return $this->publicPages(
            $this->restorePageTokens($pages)
        );
    }

    public function connectPage(
        int $userId,
        string $connectionId,
        string $pageId,
    ): SocialAccount {
        $connection = Cache::get(
            $this->connectionKey($connectionId)
        );

        $this->assertConnectionOwner(
            $connection,
            $userId
        );

        $pages = Cache::get(
            $this->pagesKey($connectionId),
            []
        );

        $pages = $this->restorePageTokens($pages);

        $page = collect($pages)
            ->firstWhere('id', $pageId);

        if (!$page) {
            throw new RuntimeException(
                'Selected Facebook Page is not available.'
            );
        }

        $pageAccessToken = $page['access_token'] ?? null;

        if (!$pageAccessToken) {
            throw new RuntimeException(
                'Facebook Page access token is missing.'
            );
        }

        $account = SocialAccount::updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => 'facebook',
                'provider_account_id' => (string) $page['id'],
            ],
            [
                'username' => $page['name'] ?? null,
                'display_name' => $page['name'] ?? null,
                'access_token' => $pageAccessToken,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => 'active',
                'scopes' => $page['granted_scopes'] ?? [],
                'metadata' => [
                    'page_id' => $page['id'],
                ],
            ]
        );

        $this->forgetConnection($connectionId);

        return $account;
    }

    private function discoverPages(string $accessToken): array
    {
        $response = Http::baseUrl(
            config('services.facebook.base_url')
        )
            ->acceptJson()
            ->timeout(30)
            ->get('/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account',
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();

        return collect($response['data'] ?? [])
            ->map(fn (array $page) => [
                'id' => $page['id'] ?? null,
                'name' => $page['name'] ?? null,
                'access_token' => $page['access_token'] ?? null,
                'instagram_business_account' =>
                    $page['instagram_business_account']['id'] ?? null,
                'granted_scopes' => [],
            ])
            ->filter(
                fn (array $page) =>
                    !empty($page['id']) &&
                    !empty($page['access_token'])
            )
            ->values()
            ->all();
    }

    private function publicPages(array $pages): array
    {
        return collect($pages)
            ->map(fn (array $page) => [
                'id' => $page['id'],
                'name' => $page['name'],
                'instagram_business_account' =>
                    $page['instagram_business_account'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function protectPageTokens(array $pages): array
    {
        return collect($pages)
            ->map(function (array $page) {
                $page['access_token'] = Crypt::encryptString(
                    $page['access_token']
                );

                return $page;
            })
            ->all();
    }

    private function restorePageTokens(array $pages): array
    {
        return collect($pages)
            ->map(function (array $page) {
                if (!empty($page['access_token'])) {
                    $page['access_token'] = Crypt::decryptString(
                        $page['access_token']
                    );
                }

                return $page;
            })
            ->all();
    }

    private function assertConnectionOwner(
        ?array $connection,
        int $userId,
    ): void {
        if (
            !$connection ||
            (int) ($connection['user_id'] ?? 0) !== $userId
        ) {
            throw new RuntimeException(
                'Facebook OAuth connection expired or is invalid.'
            );
        }
    }

    private function forgetConnection(string $connectionId): void
    {
        Cache::forget(
            $this->connectionKey($connectionId)
        );

        Cache::forget(
            $this->pagesKey($connectionId)
        );
    }

    private function stateKey(string $state): string
    {
        return "facebook_oauth:state:{$state}";
    }

    private function connectionKey(string $connectionId): string
    {
        return "facebook_oauth:connection:{$connectionId}";
    }

    private function pagesKey(string $connectionId): string
    {
        return "facebook_oauth:pages:{$connectionId}";
    }
}