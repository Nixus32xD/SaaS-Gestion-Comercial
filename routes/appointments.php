<?php

use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\AppointmentCustomerController;
use App\Http\Controllers\Appointments\AppointmentSettingController;
use App\Http\Controllers\Appointments\BlockedSlotController;
use App\Http\Controllers\Appointments\DashboardController;
use App\Http\Controllers\Appointments\ServiceController;
use App\Http\Controllers\Appointments\StaffMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business', 'feature:appointments'])
    ->prefix('appointments')
    ->as('appointments.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/staff-members', [StaffMemberController::class, 'index'])->name('staff-members.index');
        Route::post('/staff-members', [StaffMemberController::class, 'store'])->name('staff-members.store');
        Route::put('/staff-members/{staffMember}', [StaffMemberController::class, 'update'])->name('staff-members.update');
        Route::delete('/staff-members/{staffMember}', [StaffMemberController::class, 'destroy'])->name('staff-members.destroy');

        Route::get('/customers', [AppointmentCustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [AppointmentCustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [AppointmentCustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [AppointmentCustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('/blocked-slots', [BlockedSlotController::class, 'index'])->name('blocked-slots.index');
        Route::post('/blocked-slots', [BlockedSlotController::class, 'store'])->name('blocked-slots.store');
        Route::delete('/blocked-slots/{blockedSlot}', [BlockedSlotController::class, 'destroy'])->name('blocked-slots.destroy');

        Route::get('/calendar', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/calendar', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::put('/calendar/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/calendar/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

        Route::get('/settings', [AppointmentSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AppointmentSettingController::class, 'update'])->name('settings.update');
    });
