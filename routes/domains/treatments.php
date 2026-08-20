<?php

use App\Domains\Patients\Http\Controllers\Admin\TreatmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::post('treatments/draft', [TreatmentController::class, 'storeDraft'])->name('treatments.draft.store');
        Route::patch('treatments/{treatment}/draft', [TreatmentController::class, 'updateDraft'])->name('treatments.draft.update');
        Route::post('treatments/{treatment}/confirm', [TreatmentController::class, 'confirm'])->name('treatments.confirm');
        Route::resource('treatments', TreatmentController::class)->only(['index', 'create', 'edit', 'destroy']);
    });
