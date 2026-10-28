<?php

use App\Http\Controllers\Social\FacebookOAuthController;
use App\Http\Controllers\Social\InstagramOAuthController;
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