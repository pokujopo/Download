<?php

namespace App\Domain\Social\Webhooks\Services;

use App\Domain\Social\Webhooks\WebhookEvent;
use App\Models\WebhookEvent as WebhookEventModel;
use Illuminate\Database\QueryException;

final class WebhookEventService
{
    public function storeIfNew(WebhookEvent $event): bool
    {
        try {
            WebhookEventModel::create([
                'provider' => $event->provider,
                'event_id' => $event->eventId,
                'event' => $event->event,
                'account_id' => $event->accountId,
                'external_media_id' => $event->externalMediaId,
                'payload' => $event->data,
            ]);

            return true;
        } catch (QueryException $exception) {
            if ($this->isDuplicate($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    private function isDuplicate(QueryException $exception): bool
    {
        return str_contains(
            strtolower($exception->getMessage()),
            'unique'
        );
    }
}