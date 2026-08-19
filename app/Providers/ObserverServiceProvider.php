<?php

namespace App\Providers;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Observers\CenterObserver;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Observers\PractitionerObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Center::observe(CenterObserver::class);
        Practitioner::observe(PractitionerObserver::class);
    }
}
