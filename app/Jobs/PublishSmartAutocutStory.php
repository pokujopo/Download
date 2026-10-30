<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PublishSmartAutocutStory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Avoid blind queue retries after Meta might have accepted a publish request.
    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(public readonly int $storyId)
    {
    }

    public function handle(): void
    {
        $now = now('UTC')->format('Y-m-d H:i:s');
        $claimed = DB::table('content_story_destinations')
            ->where('id', $this->storyId)
            ->where('status', 'queued')
            ->update([
                'status' => 'publishing',
                'processing_started_at' => $now,
                'updated_at' => $now,
            ]);

        if (! $claimed) {
            return;
        }

        // Read Story metadata with the query builder, but load social credentials
        // through Eloquent so SocialAccount's encrypted casts decrypt the token.
        $story = DB::table('content_story_destinations as s')
            ->where('s.id', $this->storyId)
            ->first([
                's.id', 's.provider', 's.media_url', 's.provider_reference',
                's.social_account_id',
            ]);

        if (! $story) {
            $this->failStory('STORY_NOT_FOUND', 'The Story destination no longer exists.');
            return;
        }

        $socialAccount = SocialAccount::query()->find($story->social_account_id);
        if (! $socialAccount) {
            $this->failStory('SOCIAL_ACCOUNT_NOT_FOUND', 'The connected social account for this Story no longer exists.');
            return;
        }

        // access_token is cast as "encrypted" on SocialAccount. DB::table()
        // reads ciphertext, which Meta rejects with OAuth error 190.
        $story->provider_account_id = $socialAccount->provider_account_id;
        $story->access_token = $socialAccount->access_token;
        $story->account_status = $socialAccount->getRawOriginal('status');

        if ($story->account_status !== 'active') {
            $this->failStory('SOCIAL_ACCOUNT_INACTIVE', 'Reconnect or reactivate the social account before publishing Stories.');
            return;
        }
        if (! $story->provider_account_id || ! $story->access_token) {
            $this->failStory('SOCIAL_CREDENTIALS_MISSING', 'The connected social account is missing its provider ID or access token.');
            return;
        }
        if (! in_array($story->provider, ['facebook', 'instagram'], true)) {
            $this->failStory('UNSUPPORTED_PROVIDER', 'Only Facebook Page video Stories and Instagram video Stories are supported by this release.');
            return;
        }

        try {
            $version = (string) config('services.meta.graph_api_version', 'v26.0');
            if (! preg_match('/^v\d+\.\d+$/', $version)) {
                throw new \RuntimeException('services.meta.graph_api_version must look like v26.0.');
            }

            if ($story->provider === 'instagram') {
                // Story publishing uses Facebook Login credentials from the Page linked
                // to the target professional Instagram account. This is Story-only;
                // the existing Instagram feed-publishing account/token stays untouched.
                $this->resolveInstagramStoryCredentials($story, $socialAccount, $version);
                $this->publishInstagramStory($story, $version);
            } else {
                $this->publishFacebookStory($story, $version);
            }
        } catch (Throwable $e) {
            Log::warning('Smart Autocut Story publishing failed.', [
                'story_id' => $this->storyId,
                'provider' => $story->provider,
                'error' => $e->getMessage(),
            ]);
            $this->failStory('META_STORY_PUBLISH_FAILED', mb_substr($e->getMessage(), 0, 2000));
        }
    }


    /**
     * Resolve credentials specifically for Instagram Stories via Facebook Login.
     * This deliberately does not rewrite the SocialAccount row used by feed/Reel jobs.
     */
    private function resolveInstagramStoryCredentials(object $story, SocialAccount $instagramAccount, string $version): void
    {
        $targetInstagramId = trim((string) config('services.meta.instagram_story_account_id', ''));
        $pageAccountId = (int) config('services.meta.instagram_story_page_social_account_id', 0);

        if ($targetInstagramId === '' || $pageAccountId <= 0) {
            throw new \RuntimeException('Instagram Story target/Page account configuration is missing.');
        }
        if ((string) $instagramAccount->getRawOriginal('provider') !== 'instagram') {
            throw new \RuntimeException('The source social account for this Instagram Story is not an Instagram account.');
        }
        if ((string) $instagramAccount->getRawOriginal('status') !== 'active') {
            throw new \RuntimeException('The source Instagram social account is not active.');
        }

        $pageAccount = SocialAccount::query()->find($pageAccountId);
        if (! $pageAccount
            || (string) $pageAccount->getRawOriginal('provider') !== 'facebook'
            || (string) $pageAccount->getRawOriginal('status') !== 'active'
            || (int) $pageAccount->user_id !== (int) $instagramAccount->user_id) {
            throw new \RuntimeException('Configured Facebook Page account is missing, inactive, or belongs to a different user.');
        }

        $pageId = trim((string) $pageAccount->provider_account_id);
        $pageToken = (string) $pageAccount->access_token; // Eloquent decrypts encrypted cast.
        if ($pageId === '' || $pageToken === '') {
            throw new \RuntimeException('Configured Facebook Page ID/token is missing.');
        }

        $base = "https://graph.facebook.com/{$version}";
        $pageResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get("{$base}/" . rawurlencode($pageId), [
                'fields' => 'id,instagram_business_account',
                'access_token' => $pageToken,
            ]);
        $pageBody = $pageResponse->json();
        if (! $pageResponse->successful() || ! is_array($pageBody) || isset($pageBody['error'])) {
            $error = is_array($pageBody) ? ($pageBody['error'] ?? []) : [];
            $code = is_array($error) ? ($error['code'] ?? 'n/a') : 'n/a';
            $message = is_array($error) ? ($error['message'] ?? '') : '';
            throw new \RuntimeException('Could not verify the configured Facebook Page to Instagram link (HTTP '
                . $pageResponse->status() . ', Meta code ' . $code . '): ' . mb_substr((string) $message, 0, 500));
        }

        $returnedPageId = (string) ($pageBody['id'] ?? '');
        $linkedInstagramId = (string) ($pageBody['instagram_business_account']['id'] ?? '');
        if ($returnedPageId !== $pageId || $linkedInstagramId !== $targetInstagramId) {
            throw new \RuntimeException('Configured Facebook Page is not linked to the exact Instagram Story account ID. No Story was published.');
        }

        // Confirm this Page token can access the exact target Instagram professional account.
        $igResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get("{$base}/" . rawurlencode($targetInstagramId), [
                'fields' => 'id,username',
                'access_token' => $pageToken,
            ]);
        $igBody = $igResponse->json();
        if (! $igResponse->successful() || ! is_array($igBody) || isset($igBody['error'])
            || (string) ($igBody['id'] ?? '') !== $targetInstagramId) {
            $error = is_array($igBody) ? ($igBody['error'] ?? []) : [];
            $code = is_array($error) ? ($error['code'] ?? 'n/a') : 'n/a';
            $message = is_array($error) ? ($error['message'] ?? '') : '';
            throw new \RuntimeException('Facebook Page token cannot access the target Instagram account (HTTP '
                . $igResponse->status() . ', Meta code ' . $code . '): ' . mb_substr((string) $message, 0, 500));
        }

        // Override only this Story job's credentials/target; do not mutate social_accounts.
        $story->provider_account_id = $targetInstagramId;
        $story->access_token = $pageToken;
    }

    private function publishInstagramStory(object $story, string $version): void
    {
        $base = "https://graph.facebook.com/{$version}";
        $token = (string) $story->access_token;
        $accountId = rawurlencode((string) $story->provider_account_id);
        $containerId = $story->provider_reference;

        if (! $containerId) {
            $created = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
                ->post("{$base}/{$accountId}/media", [
                    'media_type' => 'STORIES',
                    'video_url' => $story->media_url,
                    'access_token' => $token,
                ]);
            $body = $this->jsonOrThrow($created, 'Instagram Story container creation');
            $containerId = $body['id'] ?? null;
            if (! is_string($containerId) || $containerId === '') {
                throw new \RuntimeException('Meta did not return an Instagram Story container ID.');
            }
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'provider_reference' => $containerId,
                'provider_response' => json_encode(['container_created' => true, 'container_id' => $containerId]),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        }

        // Give Meta a short processing window per job. If still processing, resume next minute
        // using the saved container ID rather than creating another container or another Story.
        $ready = false;
        for ($i = 0; $i < 8; $i++) {
            $statusResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
                ->get("{$base}/" . rawurlencode((string) $containerId), [
                    'fields' => 'status_code,status',
                    'access_token' => $token,
                ]);
            $status = $this->jsonOrThrow($statusResponse, 'Instagram Story container status check');
            $code = strtoupper((string) ($status['status_code'] ?? ''));

            if ($code === 'FINISHED') {
                $ready = true;
                break;
            }
            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new \RuntimeException('Instagram Story container processing ended with status ' . ($code ?: 'unknown') . '. ' . ($status['status'] ?? ''));
            }

            if ($i < 7) {
                sleep(5);
            }
        }

        if (! $ready) {
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'status' => 'pending',
                'error_code' => 'INSTAGRAM_CONTAINER_PROCESSING',
                'error_message' => 'Meta is still processing the same Story container; the scheduler will resume it without creating another container.',
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        $published = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
            ->post("{$base}/{$accountId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $token,
            ]);
        $body = $this->jsonOrThrow($published, 'Instagram Story publishing');
        $mediaId = $body['id'] ?? null;
        if (! is_string($mediaId) || $mediaId === '') {
            throw new \RuntimeException('Instagram accepted the publish request but returned no media ID; inspect Meta before manually retrying.');
        }

        $this->markPublished($mediaId, $body);
    }

    private function publishFacebookStory(object $story, string $version): void
    {
        $base = "https://graph.facebook.com/{$version}";
        $token = (string) $story->access_token;
        $pageId = rawurlencode((string) $story->provider_account_id);
        $videoId = $story->provider_reference;

        if (! $videoId) {
            $started = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(35)
                ->post("{$base}/{$pageId}/video_stories", [
                    'upload_phase' => 'start',
                    'access_token' => $token,
                ]);
            $body = $this->jsonOrThrow($started, 'Facebook Story upload initialization');
            $videoId = $body['video_id'] ?? null;
            $uploadUrl = $body['upload_url'] ?? null;
            if (! is_string($videoId) || $videoId === '' || ! is_string($uploadUrl) || $uploadUrl === '') {
                throw new \RuntimeException('Meta did not return the Facebook Story video ID and upload URL.');
            }
            DB::table('content_story_destinations')->where('id', $this->storyId)->update([
                'provider_reference' => $videoId,
                'provider_response' => json_encode(['upload_started' => true, 'video_id' => $videoId]),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        } else {
            // Never guess or reconstruct the upload URL. If the first job failed after the start phase,
            // stop safely rather than creating a second remote upload session.
            throw new \RuntimeException('Facebook Story upload already has a saved video ID but did not finish. Automatic restart was stopped to prevent duplicate or inconsistent Story uploads. Inspect Meta and retry deliberately.');
        }

        $parts = parse_url($uploadUrl);
        if (! $parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), ['rupload.facebook.com'], true)) {
            throw new \RuntimeException('Meta returned an unexpected Facebook Story upload URL; refusing to send the media there.');
        }

        $uploaded = Http::withHeaders([
                'Authorization' => 'OAuth ' . $token,
                'file_url' => (string) $story->media_url,
            ])
            ->connectTimeout(15)->timeout(60)
            ->post($uploadUrl);
        $uploadBody = $this->jsonOrThrow($uploaded, 'Facebook Story hosted video upload');
        if (($uploadBody['success'] ?? false) !== true && ($uploadBody['success'] ?? null) !== 'true') {
            throw new \RuntimeException('Facebook video Story upload did not return success=true.');
        }

        $finished = Http::asForm()->acceptJson()->connectTimeout(15)->timeout(45)
            ->post("{$base}/{$pageId}/video_stories", [
                'upload_phase' => 'finish',
                'video_id' => $videoId,
                'access_token' => $token,
            ]);
        $finishBody = $this->jsonOrThrow($finished, 'Facebook Story final publishing phase');
        if (($finishBody['success'] ?? false) !== true && ($finishBody['success'] ?? null) !== 'true') {
            throw new \RuntimeException('Facebook did not confirm the Story publish phase.');
        }

        $this->markPublished((string) $videoId, $finishBody);
    }

    private function jsonOrThrow($response, string $operation): array
    {
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || isset($body['error'])) {
            $error = is_array($body) ? ($body['error'] ?? $body) : [];
            $message = is_array($error) ? (string) ($error['message'] ?? '') : '';
            $code = is_array($error) ? ($error['code'] ?? null) : null;
            throw new \RuntimeException($operation . ' failed (HTTP ' . $response->status() . ', Meta code ' . ($code ?? 'n/a') . '): ' . ($message !== '' ? $message : mb_substr((string) $response->body(), 0, 1000)));
        }
        return $body;
    }

    private function markPublished(string $mediaId, array $response): void
    {
        DB::table('content_story_destinations')->where('id', $this->storyId)->update([
            'status' => 'published',
            'external_media_id' => $mediaId,
            'error_code' => null,
            'error_message' => null,
            'provider_response' => json_encode($response),
            'published_at' => now('UTC')->format('Y-m-d H:i:s'),
            'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
        Log::info('Smart Autocut Story published.', ['story_id' => $this->storyId, 'external_media_id' => $mediaId]);
    }

    private function failStory(string $code, string $message): void
    {
        DB::table('content_story_destinations')->where('id', $this->storyId)->update([
            'status' => 'failed',
            'error_code' => $code,
            'error_message' => mb_substr($message, 0, 2000),
            'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        // This callback runs for queue-level failures outside handle(). Preserve state if an API call
        // may already have reached Meta; do not blindly republish a Story.
        DB::table('content_story_destinations')->where('id', $this->storyId)
            ->whereIn('status', ['queued', 'publishing'])
            ->update([
                'status' => 'failed',
                'error_code' => 'QUEUE_JOB_FAILED',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
    }
}
