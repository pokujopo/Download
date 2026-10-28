<?php

namespace App\Domain\Social\Webhooks;

final readonly class WebhookEvent
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $event,
        public ?string $accountId = null,
        public ?string $externalMediaId = null,
        public array $data = [],
    ) {
    }
}