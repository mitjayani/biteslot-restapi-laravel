<?php

namespace Biteslote\Connector;

use Biteslote\Connector\Console\SyncCatalogCommand;
use Biteslote\Connector\Services\CatalogSync;
use Biteslote\Connector\Services\OrderForwarder;
use Biteslote\Connector\Services\ProductMapper;
use Biteslote\RestApi\Client;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the biteslote Laravel connector: config, migrations, the mapping/forwarding
 * services, the webhook route, and the catalog-sync command.
 *
 * The API client itself is provided by biteslote/restapi-sdk's auto-discovered
 * provider (config/biteslote-restapi.php holds base_url + api_key). This package
 * only resolves that Client; it does not re-declare credentials.
 */
class ConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/biteslote-connector.php', 'biteslote-connector');

        $this->app->singleton(ProductMapper::class);

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

        if (($this->app['config']['biteslote-connector.webhook.enabled'] ?? true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/biteslote-connector.php' => $this->app->configPath('biteslote-connector.php'),
            ], 'biteslote-connector-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'biteslote-connector-migrations');

            $this->commands([SyncCatalogCommand::class]);
        }
    }
}
