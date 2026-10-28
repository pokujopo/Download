<?php

namespace App\Infrastructure\Social\Facebook;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class FacebookApiClient
{
    private function client(string $accessToken): PendingRequest
        {
            return Http::baseUrl(config('services.facebook.base_url'))
                ->acceptJson()
                ->withToken($accessToken)
                ->timeout(30)
                ->retry(2, 500);
        }

    public function publishPost(
        string $pageId,
        string $accessToken,
        string $message,
    ): array {
        return $this->client($accessToken)
            ->post("/{$pageId}/feed", [
                'message' => $message,
            ])
            ->throw()
            ->json();
    }

    public function publishPhoto(
    string $pageId,
    string $accessToken,
    string $photoUrl,
    string $message,
): array {
    return $this->client($accessToken)
        ->post("/{$pageId}/photos", [
            'url' => $photoUrl,
            'message' => $message,
        ])
        ->throw()
        ->json();
}

    public function publishVideo(
        string $pageId,
        string $accessToken,
        string $videoUrl,
        string $description,
    ): array {
        // Video publishing is a side-effecting request.
        // Avoid automatic retries that could create duplicate posts.
        return Http::baseUrl(config('services.facebook.base_url'))
            ->acceptJson()
            ->withToken($accessToken)
            ->timeout(120)
            ->post("/{$pageId}/videos", [
                'file_url' => $videoUrl,
                'description' => $description,
            ])
            ->throw()
            ->json();
    }

    public function getPost(
        string $postId,
        string $accessToken,
    ): array {
        return $this->client($accessToken)
            ->get("/{$postId}", [
                'fields' => 'id,message',
            ])
            ->throw()
            ->json();
    }
}