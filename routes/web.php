<?php

use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\BusinessBillingController;
use App\Http\Controllers\Admin\BusinessSalesSettingsController;
use App\Http\Controllers\Admin\CommercialGuideController;
use App\Http\Controllers\Admin\GlobalProductCatalogController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Customers\CustomerAccountController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Notifications\NotificationSettingsController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\Users\BusinessUserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class);

Route::middleware(['auth', 'superadmin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::put('/businesses/{business}/billing', [BusinessBillingController::class, 'update'])->name('businesses.billing.update');
        Route::post('/businesses/{business}/payments', [BusinessBillingController::class, 'storePayment'])->name('businesses.payments.store');
        Route::put('/businesses/{business}/sales-settings', [BusinessSalesSettingsController::class, 'update'])->name('businesses.sales-settings.update');
        Route::get('/global-products', [GlobalProductCatalogController::class, 'index'])->name('global-products.index');
        Route::post('/global-products/sync', [GlobalProductCatalogController::class, 'sync'])->name('global-products.sync');
        Route::get('/commercial-guide', [CommercialGuideController::class, 'index'])->name('commercial-guide.index');
    });

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('/products/catalog/lookup', [ProductController::class, 'lookupCatalog'])->name('products.catalog.lookup');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::get('/products/{product}/batch-corrections', [ProductController::class, 'batchCorrections'])->name('products.batch-corrections.index');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::put('/products/{product}/batches/{batch}', [ProductController::class, 'updateBatch'])->name('products.batches.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/debtors', [CustomerController::class, 'debtors'])->name('customers.debtors');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/payments', [CustomerAccountController::class, 'storePayment'])->name('customers.payments.store');
    Route::get('/customers/{customer}/reminders/whatsapp', [CustomerAccountController::class, 'launchWhatsappReminder'])->name('customers.reminders.whatsapp');
    Route::post('/customers/{customer}/reminders/email', [CustomerAccountController::class, 'sendEmailReminder'])->name('customers.reminders.email');
    Route::get('/customer-accounts', [CustomerAccountController::class, 'index'])->name('customer-accounts.index');
    Route::get('/customer-accounts/{customer}', [CustomerAccountController::class, 'show'])->name('customer-accounts.show');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::get('/sales/products/search', [SaleController::class, 'searchProducts'])->name('sales.products.search');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/print', [SaleController::class, 'printIndex'])->name('sales.print.index');
    Route::get('/sales/{sale}/print', [SaleController::class, 'printShow'])->name('sales.print.show');
    Route::post('/sales/{sale}/receipt', [SaleController::class, 'storeReceipt'])->name('sales.receipt.store');
    Route::get('/sales/{sale}/receipt', [SaleController::class, 'downloadReceipt'])->name('sales.receipt.download');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
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

require __DIR__.'/auth.php';
