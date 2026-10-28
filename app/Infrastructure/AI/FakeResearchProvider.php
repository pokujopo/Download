<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\ResearchProviderContract;

final class FakeResearchProvider implements ResearchProviderContract
{
    public function search(string $query): array
    {
        return [
            [
                'title' => 'Example research result',
                'summary' => 'Example research information for testing.',
                'url' => 'https://example.com',
            ],
        ];
    }
}