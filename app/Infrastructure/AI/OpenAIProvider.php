<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\AIProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAIProvider implements AIProviderContract
{
    public function generate(string $prompt): string
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'input' => $prompt,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI generation request failed: '
                . $response->status()
                . ' '
                . $response->body()
            );
        }

        $text = $response->json('output.0.content.0.text');

        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException(
                'OpenAI returned an empty response.'
            );
        }

        return trim($text);
    }
}