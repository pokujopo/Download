<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\SmartAutocutImportController;
use App\Http\Controllers\Social\FacebookOAuthController;
use App\Http\Controllers\Social\InstagramOAuthController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\Webhook\SocialWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiAutomationController;

Route::get('/webhooks/{provider}',[SocialWebhookController::class, 'verify']);
Route::post('/webhooks/{provider}',[SocialWebhookController::class, 'handle']);

Route::prefix('auth')->group(function () {
    Route::post('/register',[AuthController::class, 'register']);
    Route::post('/login',[AuthController::class, 'login']);
    Route::post('/refresh',[AuthController::class, 'refresh']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me',[AuthController::class, 'me']);
    Route::post('/auth/logout',[AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {return $request->user();});

    Route::get('/social-accounts',[SocialAccountController::class, 'index']);
    Route::get('/social/facebook/connect',[FacebookOAuthController::class, 'connect']);
    Route::get('/social/facebook/options',[FacebookOAuthController::class, 'options']);
    Route::post('/social/facebook/select',[FacebookOAuthController::class, 'select']);
    Route::get('/social/instagram/connect',[InstagramOAuthController::class, 'redirect']);

    Route::post('/smart-autocut/import-clip', [SmartAutocutImportController::class, 'importClip']);

    Route::get('/contents',[ContentController::class, 'index']);
    Route::post('/contents',[ContentController::class, 'store']);
    Route::get('/contents/{content}',[ContentController::class, 'show']);

    Route::get('/ai-automation',[AiAutomationController::class, 'show']);
    Route::post('/ai-automation',[AiAutomationController::class, 'store']);
    Route::put('/ai-automation',[AiAutomationController::class, 'update']);
    Route::delete('/ai-automation',[AiAutomationController::class, 'destroy']);
});
