<?php

use App\Domains\Patients\Http\Controllers\Admin\PatientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::post('patients/draft', [PatientController::class, 'storeDraft'])->name('patients.draft.store');
        Route::patch('patients/{patient}/draft', [PatientController::class, 'updateDraft'])->name('patients.draft.update');
        Route::post('patients/{patient}/confirm', [PatientController::class, 'confirm'])->name('patients.confirm');
        Route::resource('patients', PatientController::class)->only(['index', 'create', 'edit', 'destroy']);
    });
