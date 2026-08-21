<?php

use App\Domains\Patients\Http\Controllers\Admin\TreatmentController;
use App\Domains\Patients\Http\Controllers\Admin\TreatmentSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::post('treatments/draft', [TreatmentController::class, 'storeDraft'])->name('treatments.draft.store');
        Route::patch('treatments/{treatment}/draft', [TreatmentController::class, 'updateDraft'])->name('treatments.draft.update');
        Route::post('treatments/{treatment}/confirm', [TreatmentController::class, 'confirm'])->name('treatments.confirm');
        Route::post('treatments/{treatment}/close', [TreatmentController::class, 'close'])->name('treatments.close');
        Route::post('treatments/{treatment}/reopen', [TreatmentController::class, 'reopen'])->name('treatments.reopen');
        Route::resource('treatments', TreatmentController::class)->only(['index', 'create', 'edit', 'destroy']);

        Route::post('treatments/{treatment}/sessions', [TreatmentSessionController::class, 'store'])->name('treatments.sessions.store');
        Route::patch('treatments/{treatment}/sessions/{session}', [TreatmentSessionController::class, 'update'])->name('treatments.sessions.update');
        Route::delete('treatments/{treatment}/sessions/{session}', [TreatmentSessionController::class, 'destroy'])->name('treatments.sessions.destroy');
    });
