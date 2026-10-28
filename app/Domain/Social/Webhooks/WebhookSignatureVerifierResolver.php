<?php

namespace App\Domain\Social\Webhooks;

interface WebhookSignatureVerifierResolver
{
    public function resolve(string $provider): WebhookSignatureVerifier;
}