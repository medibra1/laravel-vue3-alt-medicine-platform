<?php

use App\Domains\Billing\Http\Controllers\Admin\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access', 'module:billing'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('billing', [InvoiceController::class, 'index'])->name('billing.index');
        Route::get('patients/{patient}/invoices', [InvoiceController::class, 'forPatient'])->name('patients.invoices.index');
        Route::post('patients/{patient}/invoices', [InvoiceController::class, 'store'])->name('patients.invoices.store');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::delete('payments/{payment}', [InvoiceController::class, 'destroyPayment'])->name('payments.destroy');
    });
