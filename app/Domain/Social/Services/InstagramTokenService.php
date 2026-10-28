<?php

namespace App\Domain\Social\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class InstagramTokenService
{
    private const REFRESH_BEFORE_SECONDS = 86400;

    public function getValidToken(
        SocialAccount $account,
    ): string {
        if (
            $account->provider->value !== 'instagram'
        ) {
            throw new RuntimeException(
                'Social account provider is not Instagram.'
            );
        }

        if (
            !$account->access_token ||
            !$account->token_expires_at
        ) {
            $this->requireReauthorization($account);
        }

        if (
            $account->token_expires_at->gt(
                now()->addSeconds(
                    self::REFRESH_BEFORE_SECONDS
                )
            )
        ) {
            return $account->access_token;
        }

        return $this->refresh($account);
    }

    private function refresh(
        SocialAccount $account,
    ): string {
        try {
            $response = Http::baseUrl(
                config(
                    'services.instagram.base_url',
                    'https://graph.instagram.com'
                )
            )
                ->acceptJson()
                ->timeout(30)
                ->get(
                    '/refresh_access_token',
                    [
                        'grant_type' => 'ig_refresh_token',
                        'access_token' => $account->access_token,
                    ]
                )
                ->throw()
                ->json();

            $accessToken =
                $response['access_token'] ?? null;

            $expiresIn =
                $response['expires_in'] ?? null;

            if (
                !$accessToken ||
                !is_numeric($expiresIn)
            ) {
                throw new RuntimeException(
                    'Instagram refresh response is invalid.'
                );
            }

            $account->forceFill([
                'access_token' => $accessToken,
                'token_expires_at' => now()->addSeconds(
                    (int) $expiresIn
                ),
                'status' => 'active',
            ])->save();

            return $accessToken;
        } catch (\Throwable $e) {
            $this->requireReauthorization($account);

            throw new RuntimeException(
                'Instagram access token could not be refreshed.',
                previous: $e,
            );
        }
    }

    private function requireReauthorization(
        SocialAccount $account,
    ): never {
        $account->forceFill([
            'status' => 'reauthorization_required',
        ])->save();

        throw new RuntimeException(
            'Instagram authorization is required.'
        );
    }
}