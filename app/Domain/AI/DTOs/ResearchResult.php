<?php

namespace App\Domain\AI\DTOs;

final readonly class ResearchResult
{
    public function __construct(
        public string $title,
        public string $summary,
        public string $url,
    ) {
    }
}