<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\ImageSearchProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TavilyImageSearchProvider implements ImageSearchProviderContract
{
    public function search(string $query): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post(config('services.tavily.url'), [
                'api_key' => config('services.tavily.key'),
                'query' => $query,
                'search_depth' => 'basic',
                'max_results' => 5,
                'include_answer' => false,
                'include_raw_content' => false,
                'include_images' => true,
                'include_image_descriptions' => true,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Tavily image search failed: '
                . $response->status()
                . ' '
                . $response->body()
            );
        }

        return collect($response->json('images', []))
            ->map(function ($image) {
                if (is_string($image)) {
                    return [
                        'url' => $image,
                        'description' => null,
                    ];
                }

                return [
                    'url' => $image['url'] ?? '',
                    'description' => $image['description'] ?? null,
                ];
            })
            ->filter(
                fn (array $image) =>
                    $image['url'] !== ''
            )
            ->values()
            ->all();
    }
}