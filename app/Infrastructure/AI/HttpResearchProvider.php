<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\ResearchProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpResearchProvider implements ResearchProviderContract
{
    public function search(string $query): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken(config('services.ai_research.key'))
            ->post(config('services.ai_research.url'), [
                'query' => $query,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'AI research provider request failed.'
            );
        }

        return $response->json('results', []);
    }
}