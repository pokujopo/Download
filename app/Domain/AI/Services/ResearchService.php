<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\ResearchProviderContract;
use App\Domain\AI\DTOs\ResearchResult;

final class ResearchService
{
    public function __construct(
        private ResearchProviderContract $provider,
    ) {
    }

    /**
     * @return ResearchResult[]
     */
    public function research(
        string $niche,
        array $topics = [],
    ): array {
        $topic = $topics[array_rand($topics)] ?? $niche;

        $results = $this->provider->search(
            "{$niche} {$topic} latest trends news"
        );

        return array_map(
            fn (array $result) => new ResearchResult(
                title: $result['title'],
                summary: $result['summary'],
                url: $result['url'],
            ),
            $results
        );
    }
}