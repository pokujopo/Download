<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\YouTubeOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class YouTubeOAuthController extends Controller
{
    public function __construct(private YouTubeOAuthService $oauth)
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
            Log::warning('YouTube OAuth URL creation failed.', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'YouTube connection is not configured correctly.',
            ], 422);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect($this->frontendUrl() . '/settings/social-accounts?youtube=cancelled');
        }

        try {
            $account = $this->oauth->handleCallback(
                state: (string) $request->query('state', ''),
                code: $request->query('code') ? (string) $request->query('code') : null,
            );

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?youtube=connected&account='
                . urlencode((string) $account->id)
            );
        } catch (Throwable $e) {
            Log::warning('YouTube OAuth callback failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect($this->frontendUrl() . '/settings/social-accounts?youtube=failed');
        }
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
