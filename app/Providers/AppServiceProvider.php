<?php

namespace App\Providers;

use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use App\Modules\CorePlatform\Services\JwtService;
use App\Modules\CorePlatform\Services\PiiEncryptionService;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;
use App\Modules\KnowledgeBase\Contracts\RerankerInterface;
use App\Modules\KnowledgeBase\Services\MockEmbeddingProvider;
use App\Modules\KnowledgeBase\Services\MockReranker;
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

        // Real AI provider wiring lands in Module 5 (Sprint 2) once OQ-003
        // (primary AI model) is resolved. Mock is the only implementation
        // for now — matches IP-003 (MockAIProvider used in all CI tests).
        $this->app->singleton(EmbeddingProviderInterface::class, MockEmbeddingProvider::class);
        $this->app->singleton(RerankerInterface::class, MockReranker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
