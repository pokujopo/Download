<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\ResearchProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TavilyResearchProvider implements ResearchProviderContract
{
    public function search(string $query): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post(config('services.tavily.url'), [
                'api_key' => config('services.tavily.key'),
                'query' => $query,
                'search_depth' => 'advanced',
                'max_results' => 5,
                'include_answer' => false,
                'include_raw_content' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Tavily research request failed: '
                . $response->status()
            );
        }

        return collect($response->json('results', []))
            ->map(fn (array $result) => [
                'title' => $result['title'] ?? '',
                'summary' => $result['content'] ?? '',
                'url' => $result['url'] ?? '',
            ])
            ->filter(
                fn (array $result) =>
                    $result['title'] !== ''
                    && $result['summary'] !== ''
                    && $result['url'] !== ''
            )
            ->values()
            ->all();
    }
}