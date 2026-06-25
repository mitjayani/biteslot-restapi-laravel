<?php

namespace Biteslot\Connector;

use Biteslot\Connector\Console\ImportProductsCommand;
use Biteslot\Connector\Console\SyncCatalogCommand;
use Biteslot\Connector\Services\CatalogSync;
use Biteslot\Connector\Services\OrderForwarder;
use Biteslot\Connector\Services\ProductImporter;
use Biteslot\Connector\Services\ProductMapper;
use Biteslot\Connector\Services\SourceCatalog;
use Biteslot\RestApi\Client;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the biteslot Laravel connector: config, migrations, the mapping/forwarding
 * services, the webhook route, and the catalog-sync command.
 *
 * The API client itself is provided by biteslot/restapi-sdk's auto-discovered
 * provider (config/biteslot-restapi.php holds base_url + api_key). This package
 * only resolves that Client; it does not re-declare credentials.
 */
class ConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/biteslot-connector.php', 'biteslot-connector');

        $this->app->singleton(ProductMapper::class);

        $this->app->singleton(SourceCatalog::class, fn ($app) => new SourceCatalog(
            $app['db'],
            $app['config']
        ));

        $this->app->singleton(ProductImporter::class, fn ($app) => new ProductImporter(
            $app->make(SourceCatalog::class)
        ));

        $this->app->singleton(CatalogSync::class, fn ($app) => new CatalogSync(
            $app->make(Client::class),
            $app['config']
        ));

        $this->app->singleton(OrderForwarder::class, fn ($app) => new OrderForwarder(
            $app->make(Client::class),
            $app->make(ProductMapper::class),
            $app['config'],
            $app['events']
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'biteslot-connector');

        if (($this->app['config']['biteslot-connector.webhook.enabled'] ?? true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }

        if (($this->app['config']['biteslot-connector.wizard.enabled'] ?? true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/wizard.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/biteslot-connector.php' => $this->app->configPath('biteslot-connector.php'),
            ], 'biteslot-connector-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'biteslot-connector-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/biteslot-connector'),
            ], 'biteslot-connector-views');

            $this->commands([SyncCatalogCommand::class, ImportProductsCommand::class]);
        }
    }
}
