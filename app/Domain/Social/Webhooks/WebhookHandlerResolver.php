<?php

namespace App\Domain\Social\Webhooks;

interface WebhookHandlerResolver
{
    public function resolve(string $provider): WebhookHandlerContract;
}