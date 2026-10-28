<?php

namespace App\Domain\AI\Contracts;

interface MediaGenerationProviderContract
{
    /**
     * @return array{
     *     data: string,
     *     mime_type: string,
     * }
     */
    public function generateImage(string $prompt): array;
}