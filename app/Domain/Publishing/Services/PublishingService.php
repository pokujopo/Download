<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Content\Services\ContentStatusService;
use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\Contracts\SocialProviderResolver;
use App\Domain\Social\DTOs\PublishContext;
use App\Domain\Social\Enums\SocialAccountStatus;
use App\Models\ContentDestination;
use RuntimeException;

final class PublishingService
{
    public function __construct(
        private SocialProviderResolver $providerResolver,
        private ContentStatusService $contentStatusService,
    ) {
    }

    public function publish(
        ContentDestination $destination,
    ): PublishResult {
        $destination->loadMissing([
            'content.media',
            'socialAccount',
        ]);

        if ($destination->status->value === 'published') {
            throw new RuntimeException(
                'Destination has already been published.'
            );
        }

        if (!$destination->socialAccount) {
            throw new RuntimeException(
                'Social account is no longer available.'
            );
        }

        $context = new PublishContext(
            contentId: $destination->content_id,
            destinationId: $destination->id,
            socialAccountId: $destination->social_account_id,
            caption: $destination->content->caption ?? '',
            media: $destination->content->media
                ->map(static fn ($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'public_url' => $media->public_url,
                    'metadata' => $media->metadata,
                ])
                ->values()
                ->all(),
            providerReference: $destination->provider_reference,
        );

        $provider = $this->providerResolver->resolve(
            $destination->provider
        );

        $provider->validate($context);

        $destination->update([
            'status' => 'publishing',
            'attempts' => $destination->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        $this->contentStatusService->sync(
            $destination->content
        );

        $result = $provider->publish($context);

        if ($result->success) {
            $destination->update([
                'status' => 'published',
                'external_media_id' => $result->externalMediaId,
                'provider_reference' => null,
                'error_code' => null,
                'error_message' => null,
                'provider_response' => $result->providerResponse,
                'published_at' => now(),
            ]);
        } else {
            $this->handleProviderFailure(
                $destination,
                $result,
            );
        }

        $this->contentStatusService->sync(
            $destination->content
        );

        return $result;
    }

    private function handleProviderFailure(
        ContentDestination $destination,
        PublishResult $result,
    ): void {
        if ($this->requiresReauthorization($result)) {
            $destination->socialAccount->update([
                'status' => SocialAccountStatus::REAUTHORIZATION_REQUIRED,
            ]);
        }

        $destination->update([
            'status' => $result->retryable
                ? 'queued'
                : 'failed',

            'provider_reference' => $result->providerReference
                ?? $destination->provider_reference,

            'error_code' => $result->errorCode,

            'error_message' => $result->errorMessage,

            'provider_response' => $result->providerResponse,
        ]);
    }

    private function requiresReauthorization(
        PublishResult $result,
    ): bool {
        return in_array(
            $result->errorCode,
            [
                'AUTHENTICATION_FAILED',
                'INVALID_ACCESS_TOKEN',
                'MISSING_PERMISSION',
            ],
            true,
        );
    }
}