<?php

namespace NepalPay\Gateway;

use Illuminate\Support\ServiceProvider;
use NepalPay\Gateway\Console\Commands\InstallCommand;

class NepalPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config with app's published config (so defaults work even before publishing)
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nepal-payment.php',
            'nepal-payment'
        );

        // Bind the manager into the container as 'nepal-payment' (matches the Facade accessor)
        $this->app->singleton('nepal-payment', function ($app) {
            return new GatewayManager();
        });
    }

    public function boot(): void
    {
        // Load package routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load package views under the 'nepal-payment::' namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'nepal-payment');

        \Illuminate\Support\Facades\Blade::component('nepal-payment::buttons', \NepalPay\Gateway\View\Components\Buttons::class);

        // Load package migrations directly (so it works even without publishing)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            // Allow publishing config to host app's config/nepal-payment.php
            $this->publishes([
                __DIR__ . '/../config/nepal-payment.php' => config_path('nepal-payment.php'),
            ], 'nepal-payment-config');

            // Allow publishing migrations to host app's database/migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'nepal-payment-migrations');

            // Register the install command
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}