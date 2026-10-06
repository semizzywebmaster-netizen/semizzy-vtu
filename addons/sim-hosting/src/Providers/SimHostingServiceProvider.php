<?php

namespace Semizzy\Addons\SimHosting\Providers;

use Illuminate\Support\ServiceProvider;
use Semizzy\Addons\SimHosting\Console\SimHostingExpire;

class SimHostingServiceProvider extends ServiceProvider
{
    public function register(): void { $this->commands([SimHostingExpire::class]); }
}