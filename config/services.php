
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env(
            'AWS_DEFAULT_REGION',
            'us-east-1'
        ),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' =>
                env('SLACK_BOT_USER_OAUTH_TOKEN'),

            'channel' =>
                env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta
    |--------------------------------------------------------------------------
    */

    'meta' => [
        'app_secret' =>
            env('META_APP_SECRET'),

        'webhook_verify_token' =>
            env('META_WEBHOOK_VERIFY_TOKEN'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v26.0'),
        'stories_enabled' => filter_var(env('SMARTCUT_STORIES_ENABLED', false), FILTER_VALIDATE_BOOL),
        'stories_enabled_since' => env('SMARTCUT_STORIES_ENABLED_SINCE'),
        'instagram_story_account_id' => env('SMARTCUT_INSTAGRAM_STORY_ACCOUNT_ID', ''),
        'instagram_story_page_social_account_id' => (int) env('SMARTCUT_INSTAGRAM_STORY_PAGE_SOCIAL_ACCOUNT_ID', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram
    |--------------------------------------------------------------------------
    */

    'instagram' => [
        'base_url' => env(
            'INSTAGRAM_GRAPH_URL',
            'https://graph.instagram.com'
        ),

        'client_id' =>
            env('INSTAGRAM_CLIENT_ID'),

        'client_secret' =>
            env('INSTAGRAM_CLIENT_SECRET'),

        'redirect_uri' =>
            env('INSTAGRAM_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook
    |--------------------------------------------------------------------------
    */
    'ai_research' => [
            'url' => env('AI_RESEARCH_URL'),
            'key' => env('AI_RESEARCH_KEY'),
        ],
    
    'tavily' => [
            'key' => env('TAVILY_API_KEY'),
            'url' => 'https://api.tavily.com/search',
        ],
    'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),
        ],
    'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'image_model' => env(
                'GEMINI_IMAGE_MODEL',
                'gemini-3.1-flash-image'
            ),
            'url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],

    'huggingface' => [
            'key' => env('HF_TOKEN'),
            'image_model' => env(
                'HF_IMAGE_MODEL',
                'black-forest-labs/FLUX.1-dev'
            ),
        ],

    'facebook' => [
        'base_url' => env(
            'FACEBOOK_GRAPH_URL',
            'https://graph.facebook.com/v26.0'
        ),

        'client_id' =>
            env('FACEBOOK_CLIENT_ID'),

        'client_secret' =>
            env('FACEBOOK_CLIENT_SECRET'),

        'redirect_uri' =>
            env('FACEBOOK_REDIRECT_URI'),

        'login_config_id' =>
            env('FACEBOOK_LOGIN_CONFIG_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube Data API
    |--------------------------------------------------------------------------
    */
    'youtube' => [
        'client_id' => env('GOOGLE_YOUTUBE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_YOUTUBE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_YOUTUBE_REDIRECT_URI'),
        'default_privacy_status' => env('YOUTUBE_DEFAULT_PRIVACY_STATUS', 'private'),
        'category_id' => env('YOUTUBE_CATEGORY_ID', '22'),
        'autopublish_enabled' => filter_var(env('YOUTUBE_AUTOPUBLISH_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],


    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
    ],
];