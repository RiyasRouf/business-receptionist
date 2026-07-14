<?php

namespace App\Providers;

use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use App\Modules\CorePlatform\Services\JwtService;
use App\Modules\CorePlatform\Services\PiiEncryptionService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
