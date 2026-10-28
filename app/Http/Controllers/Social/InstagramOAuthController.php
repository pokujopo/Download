<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\InstagramOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class InstagramOAuthController extends Controller
{
    public function __construct(
        private InstagramOAuthService $oauth,
    ) {
    }

    public function redirect(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'url' => $this->oauth->createAuthorizationUrl(
                $request->user()->id
            ),
        ]);
    }

    public function callback(
        Request $request
    ): RedirectResponse {
        if ($request->filled('error')) {
            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?oauth=cancelled'
            );
        }

        try {
            $account = $this->oauth->handleCallback(
                state: (string) $request->input('state'),
                code: $request->input('code'),
            );

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?oauth=success'
            );
        } catch (Throwable $e) {
            Log::warning(
                'Instagram OAuth callback failed.',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?oauth=failed'
            );
        }
    }

    private function frontendUrl(): string
    {
        return rtrim(
            (string) config('app.frontend_url'),
            '/'
        );
    }
}