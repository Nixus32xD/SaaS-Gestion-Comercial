<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Notifications\NotificationSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Users\BusinessUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'business', 'business.admin'])->group(function (): void {
    Route::get('/users', [BusinessUserController::class, 'index'])->name('users.index');
    Route::post('/users', [BusinessUserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/status', [BusinessUserController::class, 'updateStatus'])->name('users.status');
    Route::get('/notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.edit');
    Route::put('/notifications', [NotificationSettingsController::class, 'update'])->name('notifications.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
