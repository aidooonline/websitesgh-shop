<?php

namespace App\Providers;

use App\Services\Woo\OrderSync;
use App\Services\Woo\SignedClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SignedClient::class, fn () => SignedClient::fromConfig());
        $this->app->bind(OrderSync::class, fn ($app) => new OrderSync($app->make(SignedClient::class)));
    }

    public function boot(): void
    {
        //
    }
}
