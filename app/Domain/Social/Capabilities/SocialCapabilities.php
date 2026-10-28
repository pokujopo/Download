<?php

namespace App\Domain\Social\Capabilities;

final readonly class SocialCapabilities
{
    public function __construct(
        public bool $text,
        public bool $image,
        public bool $video,
        public bool $caption,
        public bool $mediaRequired,
        public array $mediaTypes = [],
    ) {
    }
}
