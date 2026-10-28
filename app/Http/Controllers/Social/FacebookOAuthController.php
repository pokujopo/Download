<?php

namespace App\Http\Controllers\Social;

use App\Domain\Social\Services\FacebookOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FacebookOAuthController extends Controller
{
    public function __construct(
        private FacebookOAuthService $oauth,
    ) {
    }

    public function connect(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'url' => $this->oauth->createAuthorizationUrl(
                $request->user()->id
            ),
        ]);
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?oauth=cancelled'
            );
        }

        try {
            $result = $this->oauth->handleCallback(
                state: (string) $request->input('state'),
                code: $request->input('code'),
            );

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts/facebook/select?connection='
                . urlencode($result['connection_id'])
            );
        } catch (Throwable $e) {
            Log::warning('Facebook OAuth callback failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect(
                $this->frontendUrl()
                . '/settings/social-accounts?oauth=failed'
            );
        }
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection' => ['required', 'string'],
        ]);

        try {
            $pages = $this->oauth->getPageOptions(
                userId: $request->user()->id,
                connectionId: $validated['connection'],
            );

            return response()->json([
                'success' => true,
                'pages' => $pages,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection' => ['required', 'string'],
            'page_id' => ['required', 'string'],
        ]);

        try {
            $account = $this->oauth->connectPage(
                userId: $request->user()->id,
                connectionId: $validated['connection'],
                pageId: $validated['page_id'],
            );

            return response()->json([
                'success' => true,
                'social_account' => $account->only([
                    'id',
                    'provider',
                    'provider_account_id',
                    'username',
                    'display_name',
                    'status',
                ]),
            ]);
        } catch (Throwable $e) {
            Log::warning('Facebook Page selection failed.', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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