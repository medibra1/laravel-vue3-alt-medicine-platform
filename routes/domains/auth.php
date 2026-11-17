<?php

use App\Domains\Auth\Http\Controllers\ActiveCenterController;
use App\Domains\Auth\Http\Controllers\Admin\UserController;
use App\Domains\Auth\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'center.access'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::put('users/{user}/modules', [UserController::class, 'updateModules'])->name('users.modules.update');
        Route::put('users/{user}/center-modules', [UserController::class, 'updateCenterModules'])->name('users.center-modules.update');
        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

        Route::post('active-center', [ActiveCenterController::class, 'update'])->name('active-center.update');
    });
