<?php

namespace App\Infrastructure\Social\YouTube;

use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\Contracts\SocialProviderContract;
use App\Domain\Social\DTOs\PublishContext;
use App\Domain\Social\Enums\SocialProvider;
use App\Models\SocialAccount;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\RequestException as LaravelRequestException;
use RuntimeException;
use Throwable;

final class YouTubeProvider implements SocialProviderContract
{
    public function __construct(private YouTubeApiClient $apiClient)
    {
    }

    public function validate(PublishContext $context): void
    {
        $account = $this->account($context);

        if ($account->provider !== SocialProvider::YOUTUBE) {
            throw new RuntimeException('Social account is not a YouTube account.');
        }
        if (!$account->access_token) {
            throw new RuntimeException('YouTube access token is missing. Reconnect the channel.');
        }
        if (!$account->provider_account_id) {
            throw new RuntimeException('YouTube channel ID is missing.');
        }
        if (!$this->videoMedia($context)) {
            throw new RuntimeException('YouTube publishing requires a video with a public URL.');
        }
    }

    public function publish(PublishContext $context): PublishResult
    {
        $account = $this->account($context);
        $media = $this->videoMedia($context);

        try {
            $response = $this->apiClient->uploadVideo(
                account: $account,
                publicUrl: (string) $media['public_url'],
                caption: $context->caption,
                metadata: is_array($media['metadata'] ?? null) ? $media['metadata'] : [],
            );

            return new PublishResult(
                success: true,
                externalMediaId: $response['video_id'] ?? null,
                providerResponse: $response,
            );
        } catch (Throwable $e) {
            $status = (int) $e->getCode();
            if ($e instanceof LaravelRequestException) {
                $status = $e->response->status();
            }
            $message = $e->getMessage();
            $lowerMessage = strtolower($message);

            if (
                $status === 401
                || str_contains($lowerMessage, 'reauthorization is required')
                || str_contains($lowerMessage, 'invalid_grant')
            ) {
                $errorCode = 'INVALID_ACCESS_TOKEN';
            } elseif (
                $status === 403
                && (
                    str_contains($lowerMessage, 'insufficient authentication scopes')
                    || str_contains($lowerMessage, 'insufficientpermissions')
                    || str_contains($lowerMessage, 'insufficient permission')
                )
            ) {
                $errorCode = 'MISSING_PERMISSION';
            } else {
                $errorCode = 'YOUTUBE_PUBLISH_FAILED';
            }

            $retryable = $e instanceof ConnectException
                || $e instanceof LaravelConnectionException
                || in_array($status, [408, 429, 500, 502, 503, 504], true);

            if ($e instanceof GoogleServiceException && $status === 0) {
                $retryable = false;
            }

            return new PublishResult(
                success: false,
                errorCode: $errorCode,
                errorMessage: mb_substr($message, 0, 1000),
                providerResponse: [
                    'http_status' => $status ?: null,
                    'exception' => class_basename($e),
                ],
                retryable: $retryable,
            );
        }
    }

    public function getStatus(PublishContext $context): PublishResult
    {
        throw new RuntimeException('YouTube post status checking is not implemented yet.');
    }

    public function disconnect(string $providerAccountId): void
    {
        // OAuth revocation/disconnect can be added separately.
    }

    private function account(PublishContext $context): SocialAccount
    {
        $account = SocialAccount::query()->find($context->socialAccountId);
        if (!$account) {
            throw new RuntimeException('Social account not found.');
        }

        return $account;
    }

    private function videoMedia(PublishContext $context): ?array
    {
        foreach ($context->media as $media) {
            if (($media['type'] ?? null) === 'video' && !empty($media['public_url'])) {
                return $media;
            }
        }

        return null;
    }
}
