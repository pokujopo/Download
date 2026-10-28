<?php

namespace App\Http\Controllers\Webhook;

use App\Domain\Social\Webhooks\Services\WebhookEventService;
use App\Domain\Social\Webhooks\WebhookEvent;
use App\Domain\Social\Webhooks\WebhookHandlerResolver;
use App\Domain\Social\Webhooks\WebhookSignatureVerifierResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SocialWebhookController extends Controller
{
    public function __construct(
        private WebhookHandlerResolver $resolver,
        private WebhookSignatureVerifierResolver $signatureVerifierResolver,
        private WebhookEventService $eventService,
    ) {
    }

    public function handle(
        Request $request,
        string $provider,
    ): JsonResponse {
        $signatureVerifier = $this->signatureVerifierResolver
            ->resolve($provider);

        $signatureVerifier->verify($request);

        $eventId = $request->input('event_id')
            ?? hash('sha256', $request->getContent());

        $event = new WebhookEvent(
            provider: $provider,
            eventId: $eventId,
            event: $request->input('event', 'unknown'),
            accountId: $request->input('account_id'),
            externalMediaId: $request->input('external_media_id'),
            data: $request->all(),
        );

        if (!$this->eventService->storeIfNew($event)) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
            ]);
        }

        $handler = $this->resolver->resolve($provider);

        $handler->handle($event);

        return response()->json([
            'success' => true,
        ]);
    }

    public function verify(
        Request $request,
    ): Response {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if (
            $mode !== 'subscribe' ||
            !hash_equals(
                (string) config('services.meta.webhook_verify_token'),
                (string) $token,
            )
        ) {
            abort(403);
        }

        return response($challenge, 200)
            ->header('Content-Type', 'text/plain');
    }
}