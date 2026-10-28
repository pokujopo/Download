<?php

namespace App\Domain\AI\Contracts;

interface ResearchProviderContract
{
    public function search(string $query): array;
}