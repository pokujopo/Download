<?php

namespace App\Infrastructure\Social\Instagram;

use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\Contracts\SocialProviderContract;
use App\Domain\Social\DTOs\PublishContext;
use App\Domain\Social\Services\InstagramTokenService;
use App\Models\SocialAccount;
use RuntimeException;

final class InstagramProvider implements SocialProviderContract
{
    public function __construct(
        private InstagramApiClient $client,
        private InstagramTokenService $tokenService,
    ) {
    }

    public function validate(PublishContext $context): void
    {
        $account = $this->account($context);

        if ($account->provider->value !== 'instagram') {
            throw new RuntimeException(
                'Social account provider is not Instagram.'
            );
        }

        if ($account->status->value !== 'active') {
            throw new RuntimeException(
                'Instagram account is not active.'
            );
        }

        if (empty($account->access_token)) {
            throw new RuntimeException(
                'Instagram access token is missing.'
            );
        }

        if (empty($account->provider_account_id)) {
            throw new RuntimeException(
                'Instagram account ID is missing.'
            );
        }

        if (empty($context->media)) {
            throw new RuntimeException(
                'Instagram publication requires media.'
            );
        }
    }

    public function publish(
        PublishContext $context
    ): PublishResult {
        $account = $this->account($context);

        $this->validate($context);

        $accessToken = $this->tokenService->getValidToken(
            $account
        );

        $media = $context->media[0] ?? null;

        if (
            !$media ||
            empty($media['public_url'])
        ) {
            throw new RuntimeException(
                'Instagram requires a publicly accessible media URL.'
            );
        }

        /*
         * Reuse existing Instagram container when retrying.
         */
        $containerId = $context->providerReference;

        $container = null;

        if (!$containerId) {
            $container = match ($media['type']) {
                'image' => $this->client->createMediaContainer(
                    instagramUserId: $account->provider_account_id,
                    accessToken: $accessToken,
                    imageUrl: $media['public_url'],
                    caption: $context->caption,
                ),

                'video' => $this->client->createVideoContainer(
                    instagramUserId: $account->provider_account_id,
                    accessToken: $accessToken,
                    videoUrl: $media['public_url'],
                    caption: $context->caption,
                ),

                default => throw new RuntimeException(
                    "Unsupported Instagram media type: {$media['type']}"
                ),
            };

            $containerId = $container['id'] ?? null;

            if (!$containerId) {
                return new PublishResult(
                    success: false,
                    errorCode: 'CONTAINER_CREATION_FAILED',
                    errorMessage: 'Instagram did not return a container ID.',
                    providerResponse: $container,
                    retryable: false,
                );
            }
        }

        $status = [];
        $statusCode = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $status = $this->client->getContainerStatus(
                containerId: $containerId,
                accessToken: $accessToken,
            );

            $statusCode = $status['status_code'] ?? null;

            if ($statusCode === 'FINISHED') {
                break;
            }

            if ($statusCode === 'ERROR') {
                return new PublishResult(
                    success: false,
                    errorCode: 'MEDIA_PROCESSING_FAILED',
                    errorMessage: 'Instagram failed to process the media.',
                    providerResponse: [
                        'container_id' => $containerId,
                        'status' => $status,
                    ],
                    retryable: false,
                );
            }

            if ($statusCode !== 'IN_PROGRESS') {
                return new PublishResult(
                    success: false,
                    errorCode: 'UNKNOWN_MEDIA_STATUS',
                    errorMessage: "Instagram media status: {$statusCode}",
                    providerResponse: [
                        'container_id' => $containerId,
                        'status' => $status,
                    ],
                    retryable: false,
                );
            }

            sleep(5);
        }

        if ($statusCode !== 'FINISHED') {
            return new PublishResult(
                success: false,
                providerReference: $containerId,
                errorCode: 'MEDIA_PROCESSING_TIMEOUT',
                errorMessage: 'Instagram media was not ready within the processing window.',
                providerResponse: [
                    'container_id' => $containerId,
                    'status' => $status,
                ],
                retryable: true,
            );
        }

        $published = $this->client->publishContainer(
            instagramUserId: $account->provider_account_id,
            containerId: $containerId,
            accessToken: $accessToken,
        );

        $externalMediaId = $published['id'] ?? null;

        if (!$externalMediaId) {
            return new PublishResult(
                success: false,
                errorCode: 'PUBLISH_FAILED',
                errorMessage: 'Instagram did not return a published media ID.',
                providerResponse: $published,
                retryable: false,
            );
        }

        return new PublishResult(
            success: true,
            externalMediaId: $externalMediaId,
            providerReference: $containerId,
            providerResponse: [
                'container_id' => $containerId,
                'status' => $status,
                'published' => $published,
            ],
        );
    }

    public function getStatus(
        PublishContext $context
    ): PublishResult {
        throw new RuntimeException(
            'Instagram publication status lookup is not implemented yet.'
        );
    }

    public function disconnect(
        string $providerAccountId
    ): void {
        // OAuth/disconnection flow will be implemented separately.
    }

    private function account(
        PublishContext $context
    ): SocialAccount {
        return SocialAccount::query()
            ->findOrFail(
                $context->socialAccountId
            );
    }
}