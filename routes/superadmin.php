<?php

use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\BusinessSalesSettingsController;
use App\Http\Controllers\Admin\CommercialGuideController;
use App\Http\Controllers\Admin\GlobalProductCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::put('/businesses/{business}/sales-settings', [BusinessSalesSettingsController::class, 'update'])->name('businesses.sales-settings.update');
        Route::get('/global-products', [GlobalProductCatalogController::class, 'index'])->name('global-products.index');
        Route::post('/global-products/sync', [GlobalProductCatalogController::class, 'sync'])->name('global-products.sync');
        Route::get('/commercial-guide', [CommercialGuideController::class, 'index'])->name('commercial-guide.index');
    });
