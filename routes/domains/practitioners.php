<?php

use App\Domains\Practitioners\Http\Controllers\Admin\PractitionerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('practitioners/next-matricule', [PractitionerController::class, 'nextMatricule'])->name('practitioners.next-matricule');
        Route::resource('practitioners', PractitionerController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
