<?php

declare(strict_types=1);

namespace App\Modules\Order\Providers;

use App\Modules\Order\Adapters\MongoAdapter;
use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use App\Modules\Order\Repositories\OrderRepository;
use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Support\ServiceProvider;

class OrderModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MongoAdapter::class, function () {
            $adapter = new MongoAdapter(config('database.mongodb.uri'));

            if ($adapter->isConnected()) {
                $adapter->ensureIndexes(config('database.mongodb.database'));
            }

            return $adapter;
        });

        $this->app->singleton(OrderRepositoryInterface::class, function ($app) {
            return new OrderRepository($app->make(MongoAdapter::class));
        });

        $this->app->singleton(LegacyOrderParser::class);
        $this->app->singleton(OrderAggregationHandler::class);

        $this->app->singleton(OrderImporterInterface::class, function ($app) {
            return new LegacyOrderImporter(
                $app->make(OrderRepositoryInterface::class),
                $app->make(LegacyOrderParser::class),
                $app->make(OrderAggregationHandler::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
