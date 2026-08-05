<?php

namespace App\Providers;

use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Repositories\OrderRepository;
use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Module bindings are handled by module-specific providers.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
