<?php

namespace App\Infrastructure\Social\Webhooks;

use App\Domain\Social\Webhooks\WebhookHandlerContract;
use App\Domain\Social\Webhooks\WebhookHandlerResolver;
use RuntimeException;

final class DefaultWebhookHandlerResolver
{
    public function __construct(
        private array $handlers,
    ) {
    }

    public function resolve(string $provider): WebhookHandlerContract
    {
        $handler = $this->handlers[$provider] ?? null;

        if (!$handler instanceof WebhookHandlerContract) {
            throw new RuntimeException(
                "Webhook handler not configured for provider: {$provider}"
            );
        }

        return $handler;
    }
}