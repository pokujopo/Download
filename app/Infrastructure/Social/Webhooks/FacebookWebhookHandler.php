<?php

namespace App\Infrastructure\Social\Webhooks;

use App\Domain\Social\Webhooks\WebhookEvent;
use App\Domain\Social\Webhooks\WebhookHandlerContract;

final class FacebookWebhookHandler implements WebhookHandlerContract
{
    public function handle(WebhookEvent $event): void
    {
        // Facebook-specific webhook processing
        // will be implemented when webhook events are mapped.
    }
}