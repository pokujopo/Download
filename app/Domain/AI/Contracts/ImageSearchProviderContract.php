<?php

namespace App\Domain\AI\Contracts;

interface ImageSearchProviderContract
{
    /**
     * @return array<int, array{
     *     url: string,
     *     description: ?string,
     * }>
     */
    public function search(string $query): array;
}