<?php

use App\Domains\Core\Http\Controllers\Admin\CenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('centers/next-code', [CenterController::class, 'nextCode'])->name('centers.next-code');
        Route::resource('centers', CenterController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
