<?php

namespace App\Infrastructure\Social\Webhooks;

use App\Domain\Social\Webhooks\WebhookSignatureVerifier;
use App\Domain\Social\Webhooks\WebhookSignatureVerifierResolver;
use RuntimeException;

final class DefaultWebhookSignatureVerifierResolver
    implements WebhookSignatureVerifierResolver
{
    public function __construct(
        private array $verifiers,
    ) {
    }

    public function resolve(string $provider): WebhookSignatureVerifier
    {
        $verifier = $this->verifiers[$provider] ?? null;

        if (!$verifier instanceof WebhookSignatureVerifier) {
            throw new RuntimeException(
                "Webhook signature verifier not configured for provider: {$provider}"
            );
        }

        return $verifier;
    }
}