<?php

namespace App\Infrastructure\AI;

use App\Domain\AI\Contracts\AIProviderContract;

final class FakeAIProvider implements AIProviderContract
{
    public function generate(string $prompt): string
    {
        return 'Generated test content.';
    }
}