<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\TikTokOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TikTokOAuthController extends Controller
{
    public function __construct(private TikTokOAuthService $oauth)
    {
    }

    public function connect(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'url' => $this->oauth->createAuthorizationUrl((int) $request->user()->id),
            ]);
        } catch (Throwable $e) {
            Log::warning('TikTok OAuth URL creation failed.', [
                'user_id' => $request->user()?->id,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect($this->frontendUrl() . '/settings/social-accounts?tiktok=cancelled');
        }

        try {
            $account = $this->oauth->handleCallback(
                state: (string) $request->query('state', ''),
                code: $request->query('code') ? (string) $request->query('code') : null,
            );

            $postingReady = in_array('video.publish', $account->scopes ?? [], true);
            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?tiktok=connected&account='
                . urlencode((string) $account->id)
                . '&posting_scope=' . ($postingReady ? 'granted' : 'missing')
            );
        } catch (Throwable $e) {
            Log::warning('TikTok OAuth callback failed.', [
                'exception' => class_basename($e),
            ]);

            return redirect($this->frontendUrl() . '/settings/social-accounts?tiktok=failed');
        }
    }

    public function creatorInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $data = $this->oauth->creatorInfo(
                userId: (int) $request->user()->id,
                socialAccountId: (int) $validated['account_id'],
            );

            return response()->json(['success' => true, 'creator' => $data]);
        } catch (Throwable $e) {
            Log::warning('TikTok creator info query failed.', [
                'user_id' => $request->user()?->id,
                'account_id' => (int) $validated['account_id'],
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
