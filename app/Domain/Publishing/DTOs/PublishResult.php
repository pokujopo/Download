<?php

namespace App\Domain\Publishing\DTOs;

final readonly class PublishResult
{
    public function __construct(
        public bool $success,
        public ?string $externalMediaId = null,
        public ?string $providerReference = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $providerResponse = [],
        public bool $retryable = false,
    ) {
    }
}