<?php

namespace App\Infrastructure\Social\Webhooks;

use App\Domain\Social\Webhooks\WebhookEvent;
use App\Domain\Social\Webhooks\WebhookHandlerContract;

final class InstagramWebhookHandler implements WebhookHandlerContract
{
    public function handle(WebhookEvent $event): void
    {
        // Instagram-specific webhook processing
        // will be implemented when webhook events are mapped.
    }
}