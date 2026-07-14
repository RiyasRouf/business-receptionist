<?php

namespace App\Providers;

use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\AIAdapter\Services\GeminiAdapter;
use App\Modules\AIAdapter\Services\MockAIProvider;
use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use App\Modules\CorePlatform\Services\JwtService;
use App\Modules\CorePlatform\Services\PiiEncryptionService;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;
use App\Modules\KnowledgeBase\Contracts\RerankerInterface;
use App\Modules\KnowledgeBase\Services\MockEmbeddingProvider;
use App\Modules\KnowledgeBase\Services\MockReranker;
use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use App\Modules\WhatsAppAdapter\Services\WhatsAppAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtServiceInterface::class, JwtService::class);
        $this->app->singleton(PiiEncryptionServiceInterface::class, PiiEncryptionService::class);

        // Platform-level default only (config('ai.provider')) — per-tenant
        // selection is via tenant_config (ADR-053), not this binding.
        // 'mock' keeps CI free of real AI calls (IP-003, module-build-order
        // Key Rules). GeminiAdapter also implements EmbeddingProviderInterface,
        // so KnowledgeBase automatically gets real embeddings under the same
        // binding — no Module 4 code changes needed to switch providers.
        $this->app->singleton(AIProviderInterface::class, function ($app) {
            return config('ai.provider') === 'gemini'
                ? $app->make(GeminiAdapter::class)
                : $app->make(MockAIProvider::class);
        });

        $this->app->singleton(EmbeddingProviderInterface::class, function ($app) {
            return config('ai.provider') === 'gemini'
                ? $app->make(GeminiAdapter::class)
                : $app->make(MockEmbeddingProvider::class);
        });

        $this->app->singleton(RerankerInterface::class, MockReranker::class);

        $this->app->singleton(MessagingAdapterInterface::class, WhatsAppAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
