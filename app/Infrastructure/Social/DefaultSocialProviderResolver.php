<?php

namespace App\Infrastructure\Social;

use App\Domain\Social\Contracts\SocialProviderContract;
use App\Domain\Social\Contracts\SocialProviderResolver;
use App\Domain\Social\Enums\SocialProvider;
use RuntimeException;

final class DefaultSocialProviderResolver implements SocialProviderResolver
{
    public function __construct(
        private array $providers,
    ) {
    }

    public function resolve(
        SocialProvider $provider,
    ): SocialProviderContract {
        $resolved = $this->providers[$provider->value] ?? null;

        if (!$resolved instanceof SocialProviderContract) {
            throw new RuntimeException(
                "Social provider not configured: {$provider->value}"
            );
        }

        return $resolved;
    }
}