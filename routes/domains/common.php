<?php

use App\Domains\Common\Http\Controllers\Admin\EnumOptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::resource('enum-options', EnumOptionController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
