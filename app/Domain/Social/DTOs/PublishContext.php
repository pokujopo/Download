<?php

namespace App\Domain\Social\DTOs;

final readonly class PublishContext
{
    public function __construct(
        public int $contentId,
        public int $destinationId,
        public int $socialAccountId,
        public string $caption,
        public array $media,
        public ?string $providerReference = null,
        public array $metadata = [],
    ) {
    }
}