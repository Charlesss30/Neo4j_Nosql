<?php

namespace App\Providers;

use App\Services\Neo4jService;
use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn (): ClientInterface => ClientBuilder::create()
            ->withDriver('family', config('neo4j.uri'), Authenticate::basic(
                config('neo4j.user'),
                config('neo4j.password'),
            ))
            ->withDefaultDriver('family')
            ->withDefaultSessionConfiguration(SessionConfiguration::default()
                ->withDatabase(config('neo4j.database')))
            ->withDefaultDriverConfiguration(DriverConfiguration::default()
                ->withSocketTimeoutSeconds(config('neo4j.socket_timeout')))
            ->withDefaultTransactionConfiguration(TransactionConfiguration::default()
                ->withTimeout(config('neo4j.query_timeout')))
            ->build());

        $this->app->singleton(Neo4jService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
