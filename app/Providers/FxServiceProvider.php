<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class FxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Fx\FxRateService::class);
    }
}