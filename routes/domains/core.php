<?php

use App\Domains\Core\Http\Controllers\Admin\CenterClosureController;
use App\Domains\Core\Http\Controllers\Admin\CenterController;
use App\Domains\Core\Http\Controllers\Admin\CountryController;
use App\Domains\Core\Http\Controllers\Admin\ZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('centers/next-code', [CenterController::class, 'nextCode'])->name('centers.next-code');
        Route::put('centers/{center}/operating-hours', [CenterController::class, 'syncOperatingHours'])
            ->name('centers.operating-hours.sync');
        Route::get('centers/{center}/closures', [CenterClosureController::class, 'index'])->name('centers.closures.index');
        Route::post('centers/{center}/closures', [CenterClosureController::class, 'store'])->name('centers.closures.store');
        Route::delete('closures/{closure}', [CenterClosureController::class, 'destroy'])->name('closures.destroy');
        Route::resource('centers', CenterController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('zones', ZoneController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('countries', CountryController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
