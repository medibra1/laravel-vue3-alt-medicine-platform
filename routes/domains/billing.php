<?php

use App\Domains\Billing\Http\Controllers\Admin\AccountingExportController;
use App\Domains\Billing\Http\Controllers\Admin\ExpenseController;
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
        Route::put('invoices/{invoice}/installments', [InvoiceController::class, 'updateInstallments'])->name('invoices.installments.update');
        Route::put('patients/{patient}/billing-discount', [InvoiceController::class, 'updatePatientDiscount'])->name('patients.billing-discount.update');
        Route::post('invoices/{invoice}/credit-notes', [InvoiceController::class, 'storeCreditNote'])->name('invoices.credit-notes.store');
        Route::post('invoices/{invoice}/refunds', [InvoiceController::class, 'storeRefund'])->name('invoices.refunds.store');
        Route::get('billing/payments', [InvoiceController::class, 'payments'])->name('billing.payments');
        Route::get('billing/export', AccountingExportController::class)->name('billing.export');
        Route::get('billing/expenses', [ExpenseController::class, 'index'])->name('billing.expenses.index');
        Route::post('billing/expenses', [ExpenseController::class, 'store'])->name('billing.expenses.store');
        Route::put('billing/expenses/{expense}', [ExpenseController::class, 'update'])->name('billing.expenses.update');
        Route::delete('billing/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('billing.expenses.destroy');
        Route::delete('payments/{payment}', [InvoiceController::class, 'destroyPayment'])->name('payments.destroy');
    });
