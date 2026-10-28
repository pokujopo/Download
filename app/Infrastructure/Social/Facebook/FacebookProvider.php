<?php

namespace App\Infrastructure\Social\Facebook;

use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\Contracts\SocialProviderContract;
use App\Domain\Social\DTOs\PublishContext;
use App\Domain\Social\Enums\SocialProvider;
use App\Models\SocialAccount;
use RuntimeException;

final class FacebookProvider implements SocialProviderContract
{
    public function __construct(
        private FacebookApiClient $apiClient,
    ) {}

    public function validate(PublishContext $context): void
    {
        $account = $this->account($context);

        if ($account->provider !== SocialProvider::FACEBOOK) {
            throw new RuntimeException(
                'Social account is not a Facebook account.'
            );
        }

        if (!$account->access_token) {
            throw new RuntimeException(
                'Facebook access token is missing.'
            );
        }

        if (!$account->provider_account_id) {
            throw new RuntimeException(
                'Facebook Page ID is missing.'
            );
        }

        if (empty($context->caption)) {
            throw new RuntimeException(
                'Facebook post message cannot be empty.'
            );
        }
    }

    public function publish(PublishContext $context): PublishResult
{
    $account = $this->account($context);

    try {
        $media = $context->media[0] ?? null;

        if ($media && $media['type'] === 'video') {
            $response = $this->apiClient->publishVideo(
                pageId: $account->provider_account_id,
                accessToken: $account->access_token,
                videoUrl: $media['public_url'],
                description: $context->caption,
            );
        } elseif ($media && $media['type'] === 'image') {
            $response = $this->apiClient->publishPhoto(
                pageId: $account->provider_account_id,
                accessToken: $account->access_token,
                photoUrl: $media['public_url'],
                message: $context->caption,
            );
        } else {
            $response = $this->apiClient->publishPost(
                pageId: $account->provider_account_id,
                accessToken: $account->access_token,
                message: $context->caption,
            );
        }

        return new PublishResult(
            success: true,
            externalMediaId: $response['post_id']
                ?? $response['id']
                ?? null,
            providerResponse: $response,
        );
    } catch (\Throwable $e) {
        return new PublishResult(
            success: false,
            errorCode: 'FACEBOOK_PUBLISH_FAILED',
            errorMessage: $e->getMessage(),
            retryable: false,
        );
    }
}

    public function getStatus(PublishContext $context): PublishResult
    {
        throw new RuntimeException(
            'Facebook post status is not implemented yet.'
        );
    }

    public function disconnect(string $providerAccountId): void
    {
        // Facebook disconnect will be implemented with OAuth.
    }

    private function account(PublishContext $context): SocialAccount
    {
        $account = SocialAccount::query()
            ->find($context->socialAccountId);

        if (!$account) {
            throw new RuntimeException(
                'Social account not found.'
            );
        }

        return $account;
    }
}