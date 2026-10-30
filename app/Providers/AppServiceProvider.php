<?php

namespace App\Providers;

use App\Domain\Media\Contracts\MediaStorageContract;
use App\Domain\Social\Contracts\SocialProviderResolver;
use App\Domain\Social\Webhooks\WebhookSignatureVerifier;
use App\Domain\Social\Webhooks\WebhookSignatureVerifierResolver;
use App\Infrastructure\Social\DefaultSocialProviderResolver;
use App\Infrastructure\Social\Facebook\FacebookProvider;
use App\Infrastructure\Social\Instagram\InstagramProvider;
use App\Infrastructure\Social\YouTube\YouTubeProvider;
use App\Infrastructure\Social\Webhooks\DefaultWebhookSignatureVerifierResolver;
use App\Infrastructure\Social\Webhooks\MetaWebhookSignatureVerifier;
use App\Infrastructure\Storage\LaravelMediaStorage;
use Illuminate\Support\ServiceProvider;
use App\Domain\AI\Contracts\AIProviderContract;
use App\Infrastructure\AI\FakeAIProvider;
use App\Domain\AI\Contracts\ResearchProviderContract;
use App\Infrastructure\AI\FakeResearchProvider;
use App\Infrastructure\AI\TavilyResearchProvider;
use App\Infrastructure\AI\OpenAIProvider;
use App\Infrastructure\AI\GeminiProvider;
use App\Domain\AI\Contracts\MediaGenerationProviderContract;
use App\Infrastructure\AI\GeminiMediaProvider;
use App\Domain\AI\Contracts\ImageSearchProviderContract;
use App\Infrastructure\AI\TavilyImageSearchProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SocialProviderResolver::class,
            fn ($app) => new DefaultSocialProviderResolver([
                'facebook' => $app->make(
                    FacebookProvider::class
                ),

                'instagram' => $app->make(
                    InstagramProvider::class
                ),

                'youtube' => $app->make(
                    YouTubeProvider::class
                ),
            ])
        );

        $this->app->bind(
            MediaStorageContract::class,
            LaravelMediaStorage::class,
        );
        $this->app->bind(
            AIProviderContract::class,
            FakeAIProvider::class,
        );

        $this->app->singleton(
            WebhookSignatureVerifier::class,
            MetaWebhookSignatureVerifier::class,
        );

        $this->app->bind(
            ResearchProviderContract::class,
            FakeResearchProvider::class,
        );

        $this->app->bind(
            AIProviderContract::class,
            OpenAIProvider::class,
        );
        $this->app->bind(
            AIProviderContract::class,
            GeminiProvider::class,
        );
            
        $this->app->bind(
            ImageSearchProviderContract::class,
            TavilyImageSearchProvider::class,
        );

        $this->app->bind(
            MediaGenerationProviderContract::class,
            GeminiMediaProvider::class,
        );

        $this->app->bind(
            ResearchProviderContract::class,
            TavilyResearchProvider::class,
        );

        $this->app->singleton(
            WebhookSignatureVerifierResolver::class,
            fn ($app) => new DefaultWebhookSignatureVerifierResolver([
                'facebook' => $app->make(
                    MetaWebhookSignatureVerifier::class
                ),

                'instagram' => $app->make(
                    MetaWebhookSignatureVerifier::class
                ),
            ])
        );
    }

    public function boot(): void
    {
        //
    }
}