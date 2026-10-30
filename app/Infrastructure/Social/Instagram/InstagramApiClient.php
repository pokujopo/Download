<?php

namespace App\Infrastructure\Social\Instagram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class InstagramApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config(
                'services.instagram.base_url',
                'https://graph.instagram.com'
            ),
            '/'
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }

    public function createMediaContainer(
        string $instagramUserId,
        string $accessToken,
        string $imageUrl,
        ?string $caption = null,
    ): array {
        $response = $this->client()->post(
            "/{$instagramUserId}/media",
            array_filter([
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ], fn ($value) => $value !== null)
        );

        $response->throw();

        return $response->json();
    }

    public function createVideoContainer(
        string $instagramUserId,
        string $accessToken,
        string $videoUrl,
        ?string $caption = null,
    ): array {
        $response = $this->client()->post(
            "/{$instagramUserId}/media",
            array_filter([
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ], fn ($value) => $value !== null)
        );

        $response->throw();

        return $response->json();
    }

    public function getContainerStatus(
        string $containerId,
        string $accessToken,
    ): array {
        $response = $this->client()->get(
            "/{$containerId}",
            [
                'fields' => 'status_code,status',
                'access_token' => $accessToken,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function publishContainer(
        string $instagramUserId,
        string $containerId,
        string $accessToken,
    ): array {
        $response = $this->client()->post(
            "/{$instagramUserId}/media_publish",
            [
                'creation_id' => $containerId,
                'access_token' => $accessToken,
            ]
        );

        $response->throw();

        return $response->json();
    }
}