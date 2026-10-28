<?php

namespace App\Domain\Social\Webhooks;

use Illuminate\Http\Request;

interface WebhookSignatureVerifier
{
    public function verify(Request $request): void;
}