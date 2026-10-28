<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->issueTokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (
            !$user ||
            !Hash::check($data['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return $this->issueTokenResponse($user);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is missing.',
            ], 401);
        }

        try {
            $newToken = JWTAuth::setToken($refreshToken)->refresh();

            $user = JWTAuth::setToken($newToken)->toUser();

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is invalid or expired.',
            ], 401)->withoutCookie('refresh_token');
        }
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();

            if ($token) {
                JWTAuth::invalidate($token);
            }
        } catch (\Throwable) {
            // Token may already be expired or invalid.
        }

        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            try {
                JWTAuth::setToken($refreshToken)->invalidate();
            } catch (\Throwable) {
                // Refresh token may already be expired or invalid.
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ])->withoutCookie('refresh_token');
    }

    private function issueTokenResponse(
        User $user,
        int $status = 200
    ): JsonResponse {
        $accessToken = JWTAuth::fromUser($user);

        /*
         * The same JWT can be refreshed during JWT_REFRESH_TTL.
         * The cookie persists longer than the access-token TTL.
         */
        return response()
            ->json([
                'success' => true,
                'user' => $user,
                'token' => $accessToken,
            ], $status)
            ->cookie(
                'refresh_token',
                $accessToken,
                20160,
                '/api/auth',
                null,
                app()->environment('production'),
                true,
                false,
                'lax'
            );
    }
}