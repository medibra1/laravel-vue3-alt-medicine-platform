<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            foreach (glob(base_path('routes/domains/*.php')) as $domainRoutes) {
                $this->loadRoutesFrom($domainRoutes);
            }
        });
    }
}
