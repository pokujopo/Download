<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()
                ->socialAccounts()
                ->get([
                    'id',
                    'provider',
                    'provider_account_id',
                    'username',
                    'display_name',
                    'status',
                ])
        );
    }
}