<?php

use App\Domains\Patients\Http\Controllers\Admin\CareCategoryController;
use App\Domains\Patients\Http\Controllers\Admin\CareItemController;
use App\Domains\Patients\Http\Controllers\Admin\DiseaseCategoryController;
use App\Domains\Patients\Http\Controllers\Admin\DiseaseController;
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

        Route::resource('disease-categories', DiseaseCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('diseases/next-code', [DiseaseController::class, 'nextCode'])->name('diseases.next-code');
        Route::resource('diseases', DiseaseController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('care-categories', CareCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('care-items/next-code', [CareItemController::class, 'nextCode'])->name('care-items.next-code');
        Route::resource('care-items', CareItemController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
