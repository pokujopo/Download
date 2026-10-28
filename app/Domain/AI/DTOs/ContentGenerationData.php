<?php

namespace App\Domain\AI\DTOs;

final readonly class ContentGenerationData
{
    public function __construct(
        public string $caption,
        public ?string $topic = null,
    ) {
    }
}