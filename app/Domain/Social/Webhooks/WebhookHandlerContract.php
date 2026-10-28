<?php

namespace App\Domain\Social\Webhooks;

interface WebhookHandlerContract
{
    public function handle(WebhookEvent $event): void;
}