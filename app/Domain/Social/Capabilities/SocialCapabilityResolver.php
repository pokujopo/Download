<?php

namespace App\Domain\Social\Capabilities;

use App\Domain\Social\Enums\SocialProvider;

final class SocialCapabilityResolver
{
    public function resolve(
        SocialProvider $provider,
    ): SocialCapabilities {
        return match ($provider) {
            SocialProvider::FACEBOOK => new SocialCapabilities(
                text: true,
                image: true,
                video: true,
                caption: true,
                mediaRequired: true,
                mediaTypes: ['image', 'video'],
            ),

            SocialProvider::INSTAGRAM => new SocialCapabilities(
                text: false,
                image: true,
                video: true,
                caption: true,
                mediaRequired: true,
                mediaTypes: ['image', 'video'],
            ),

            default => new SocialCapabilities(
                text: true,
                image: false,
                video: false,
                caption: true,
                mediaRequired: false,
            ),
        };
    }
}
