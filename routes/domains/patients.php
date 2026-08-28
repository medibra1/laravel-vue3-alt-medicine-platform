<?php

use App\Domains\Patients\Http\Controllers\Admin\CareCategoryController;
use App\Domains\Patients\Http\Controllers\Admin\CareItemController;
use App\Domains\Patients\Http\Controllers\Admin\ConsentTemplateController;
use App\Domains\Patients\Http\Controllers\Admin\DiseaseCategoryController;
use App\Domains\Patients\Http\Controllers\Admin\DiseaseController;
use App\Domains\Patients\Http\Controllers\Admin\PatientConsentController;
use App\Domains\Patients\Http\Controllers\Admin\PatientController;
use App\Domains\Patients\Http\Controllers\Admin\PatientDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::post('patients/draft', [PatientController::class, 'storeDraft'])->name('patients.draft.store');
        Route::patch('patients/{patient}/draft', [PatientController::class, 'updateDraft'])->name('patients.draft.update');
        Route::post('patients/{patient}/confirm', [PatientController::class, 'confirm'])->name('patients.confirm');
        Route::resource('patients', PatientController::class)->only(['index', 'create', 'edit', 'destroy']);

        Route::post('patients/{patient}/documents', [PatientDocumentController::class, 'store'])->name('patients.documents.store');
        Route::delete('patients/{patient}/documents/{media}', [PatientDocumentController::class, 'destroy'])->name('patients.documents.destroy');
        Route::get('patients/{patient}/documents/{media}', [PatientDocumentController::class, 'show'])->name('patients.documents.show');
        Route::get('patients/{patient}/documents/{media}/thumb', [PatientDocumentController::class, 'thumb'])->name('patients.documents.thumb');

        Route::post('patients/{patient}/consents', [PatientConsentController::class, 'store'])->name('patients.consents.store');
        Route::get('patients/{patient}/consents/{consent}', [PatientConsentController::class, 'show'])->name('patients.consents.show');

        Route::resource('consent-templates', ConsentTemplateController::class)
            ->only(['index', 'store', 'update']);

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
