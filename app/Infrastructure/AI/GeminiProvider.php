<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\AIProviderContract;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiProvider implements AIProviderContract
{
    public function generate(string $prompt): string
    {
        $model = config('services.gemini.model');

        $response = $this->client()
            ->retry(
                3,
                2000,
                function ($exception, PendingRequest $request) {
                    if (!$exception instanceof \Illuminate\Http\Client\RequestException) {
                        return true;
                    }

                    $status = $exception->response?->status();

                    return in_array($status, [429, 500, 502, 503, 504], true);
                },
                throw: false
            )
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
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini generation request failed: '
                . $response->status()
                . ' '
                . $response->body()
            );
        }

        $text = $response->json(
            'candidates.0.content.parts.0.text'
        );

        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException(
                'Gemini returned an empty response.'
            );
        }

        return trim($text);
    }

    private function client(): PendingRequest
    {
        return Http::timeout(60)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ]);
    }
}