<?php

namespace App\Domain\AI\Contracts;

interface AIProviderContract
{
    public function generate(string $prompt): string;
}