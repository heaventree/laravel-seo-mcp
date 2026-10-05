<?php

namespace Heaventree\SeoMcp;

use Heaventree\SeoMcp\Auth\TokenStore;
use Illuminate\Support\ServiceProvider;

class LaravelSeoMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-seo-mcp.php', 'laravel-seo-mcp');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-seo-mcp');

        $this->publishes([
            __DIR__ . '/../config/laravel-seo-mcp.php' => config_path('laravel-seo-mcp.php'),
        ], 'laravel-seo-mcp-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GenerateApiKeyCommand::class,
                Console\CleanupExpiredTokensCommand::class,
            ]);
        }
    }
}
