<?php

namespace App\Infrastructure\Social\Webhooks;

use App\Domain\Social\Webhooks\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MetaWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    public function verify(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            throw new HttpException(
                401,
                'Missing webhook signature.'
            );
        }

        $appSecret = config('services.meta.app_secret');

        if (!$appSecret) {
            throw new HttpException(
                500,
                'Meta app secret is not configured.'
            );
        }

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret,
        );

        if (!hash_equals($expected, $signature)) {
            throw new HttpException(
                401,
                'Invalid webhook signature.'
            );
        }
    }
}