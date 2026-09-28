<?php

use App\Domains\Scheduling\Http\Controllers\Admin\AppointmentController;
use App\Domains\Scheduling\Http\Controllers\Admin\PractitionerAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('agenda', [AppointmentController::class, 'agenda'])->name('agenda');
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.available-slots');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow'])->name('appointments.no-show');

        Route::put('practitioners/availabilities/bulk-sync', [PractitionerAvailabilityController::class, 'bulkSync'])->name('practitioners.availabilities.bulk-sync');
        Route::put('practitioners/{practitioner}/availabilities', [PractitionerAvailabilityController::class, 'sync'])->name('practitioners.availabilities.sync');
        Route::resource('availabilities', PractitionerAvailabilityController::class)->except(['show', 'create', 'edit']);
    });
