<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $accessToken = $user->createToken('auth_token')->plainTextToken;
        $refreshToken = Str::random(100);

        $user->update([
            'refresh_token' => $refreshToken,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $user = $request->user();

        $user->tokens()->delete();

        $accessToken = $user->createToken('auth_token')->plainTextToken;
        $refreshToken = Str::random(100);

        $user->update([
            'refresh_token' => $refreshToken,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $user = User::where('refresh_token', $request->refresh_token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token.',
            ], 401);
        }

        $user->tokens()->delete();

        $newAccessToken = $user->createToken('auth_token')->plainTextToken;
        $newRefreshToken = Str::random(100);

        $user->update([
            'refresh_token' => $newRefreshToken,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => auth()->user(),
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        if ($user) {
            $user->currentAccessToken()?->delete();

            $user->update([
                'refresh_token' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function redirectToFacebook()
{
    return Socialite::driver('facebook')
        ->scopes([
            'pages_manage_posts', 
            'pages_read_engagement', 
            'pages_show_list',
            'business_management' // Added this
        ])
        ->redirect();
}


//with(['auth_type' => 'rerequest'])

  
      // Handle Facebook Callback
      public function handleFacebookCallback()
      {
          $user = Socialite::driver('facebook')->user();
  
          $existingUser = User::where('email', $user->getEmail())->first();
  
          if ($existingUser) {
              Auth::login($existingUser, true);
              return redirect()->route('home'); // Redirect to the homepage after login
          } else {
              // Register a new user
              $newUser = User::create([
                  'name' => $user->getName(),
                  'email' => $user->getEmail(),
                  'password' => bcrypt(str_random(16)), // Create a random password for new users
              ]);
  
              Auth::login($newUser, true);
              return redirect()->route('home');
          }
      }
}



