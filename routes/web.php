<?php

use App\Http\Controllers\Social\FacebookOAuthController;
use App\Http\Controllers\Social\InstagramOAuthController;
use App\Http\Controllers\Social\YouTubeOAuthController;
use App\Http\Controllers\Social\TikTokOAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Facebook OAuth Callback
|--------------------------------------------------------------------------
*/

Route::get(
    '/social/facebook/callback',
    [FacebookOAuthController::class, 'callback']
);

/*
|--------------------------------------------------------------------------
| Instagram OAuth Callback
|--------------------------------------------------------------------------
*/

Route::get(
    '/social/instagram/callback',
    [InstagramOAuthController::class, 'callback']
);

/*
|--------------------------------------------------------------------------
| YouTube OAuth Callback
|--------------------------------------------------------------------------
*/

Route::get(
    '/social/youtube/callback',
    [YouTubeOAuthController::class, 'callback']
);

/* TikTok Login Kit callback. */
Route::get(
    '/social/tiktok/callback',
    [TikTokOAuthController::class, 'callback']
);

/*
|--------------------------------------------------------------------------
| Application Pages
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::view(
    '/social-accounts',
    'social-accounts'
);

Route::view(
    '/settings/social-accounts',
    'social-accounts'
);