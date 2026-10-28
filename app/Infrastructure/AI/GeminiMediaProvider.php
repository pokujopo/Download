<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\MediaGenerationProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiMediaProvider implements MediaGenerationProviderContract
{
    public function generateImage(string $prompt): array
    {
        $model = config('services.gemini.image_model');

        $response = Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ])
            ->post(
                config('services.gemini.url')
                . "/models/{$model}:generateContent",
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['IMAGE'],
                    ],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini image generation failed: '
                . $response->status()
                . ' '
                . $response->body()
            );
        }

        $parts = $response->json(
            'candidates.0.content.parts',
            []
        );

        foreach ($parts as $part) {
            $inlineData = $part['inlineData'] ?? null;

            if (
                is_array($inlineData)
                && !empty($inlineData['data'])
            ) {
                return [
                    'data' => base64_decode(
                        $inlineData['data'],
                        true
                    ),
                    'mime_type' => $inlineData['mimeType']
                        ?? 'image/png',
                ];
            }
        }

        throw new RuntimeException(
            'Gemini did not return image data.'
        );
    }
}