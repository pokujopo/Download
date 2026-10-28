<?php

namespace Database\Seeders;

use App\Domain\Social\Enums\SocialProvider;
use App\Models\Content;
use App\Models\ContentDestination;
use App\Models\ContentMedia;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class PublishingTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create();

        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => SocialProvider::INSTAGRAM,
            'provider_account_id' => '28780557518298053',
            'username' => 'jboycreater',
            'access_token' => 'TEST_TOKEN',
            'status' => 'active',
        ]);

        $content = Content::create([
            'user_id' => $user->id,
            'caption' => 'Test post from Once Post Anywhere',
            'status' => 'draft',
        ]);

        $media = ContentMedia::create([
            'content_id' => $content->id,
            'type' => 'image',
            'public_url' => 'https://example.com/test.jpg',
        ]);

        ContentDestination::create([
            'content_id' => $content->id,
            'social_account_id' => $account->id,
            'provider' => SocialProvider::INSTAGRAM,
            'status' => 'pending',
        ]);
    }
}