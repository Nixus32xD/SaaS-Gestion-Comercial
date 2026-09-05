<?php

use App\Http\Controllers\Admin\BusinessBillingController;
use App\Http\Controllers\Admin\BusinessBranchCommercialSettingController;
use App\Http\Controllers\Admin\BusinessBranchController;
use App\Http\Controllers\Admin\BusinessBranchFiscalSettingController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\BusinessSalesSettingsController;
use App\Http\Controllers\Admin\CommercialGuideController;
use App\Http\Controllers\Admin\GlobalProductCatalogController;
use App\Http\Controllers\Branches\CurrentBranchController;
use App\Http\Controllers\CashRegister\CashRegisterController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Customers\CustomerAccountController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Fiscal\ElectronicBillingController;
use App\Http\Controllers\Fiscal\FiscalCredentialProxyController;
use App\Http\Controllers\Fiscal\FiscalMonthlyReportController;
use App\Http\Controllers\Inventory\InventoryTransferController;
use App\Http\Controllers\Notifications\NotificationSettingsController;
use App\Http\Controllers\Payments\MercadoPagoPointController;
use App\Http\Controllers\Payments\MercadoPagoSettingsController;
use App\Http\Controllers\Payments\MercadoPagoWebhookController;
use App\Http\Controllers\Products\InventoryAdjustmentController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Sales\QuickSaleOptionController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleFiscalDocumentController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\Users\BusinessUserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/', WelcomeController::class);

Route::post('/webhooks/mercadopago/orders', [MercadoPagoWebhookController::class, 'orders'])
    ->middleware('throttle:mercadopago-webhook')
    ->name('webhooks.mercadopago.orders');

Route::middleware(['auth', 'superadmin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::delete('/businesses/{business}', [BusinessController::class, 'archive'])->name('businesses.archive');
        Route::post('/businesses/{business}/branches', [BusinessBranchController::class, 'store'])->name('businesses.branches.store');
        Route::put('/businesses/{business}/branches/{branch}', [BusinessBranchController::class, 'update'])->name('businesses.branches.update');
        Route::put('/businesses/{business}/branches/{branch}/fiscal-settings', [BusinessBranchFiscalSettingController::class, 'update'])->name('businesses.branches.fiscal-settings.update');
        Route::post('/businesses/{business}/fiscal-identities/{identity}/sync', [BusinessBranchFiscalSettingController::class, 'retryIdentitySync'])->name('businesses.fiscal-identities.sync');
        Route::put('/businesses/{business}/branches/{branch}/commercial-settings', [BusinessBranchCommercialSettingController::class, 'update'])->name('businesses.branches.commercial-settings.update');
        Route::put('/businesses/{business}/billing', [BusinessBillingController::class, 'update'])->name('businesses.billing.update');
        Route::post('/businesses/{business}/payments', [BusinessBillingController::class, 'storePayment'])->name('businesses.payments.store');
        Route::put('/businesses/{business}/sales-settings', [BusinessSalesSettingsController::class, 'update'])->name('businesses.sales-settings.update');
        Route::get('/global-products', [GlobalProductCatalogController::class, 'index'])->name('global-products.index');
        Route::post('/global-products/sync', [GlobalProductCatalogController::class, 'sync'])->name('global-products.sync');
        Route::get('/commercial-guide', [CommercialGuideController::class, 'index'])->name('commercial-guide.index');
    });

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::put('/branches/current', [CurrentBranchController::class, 'update'])->name('branches.current.update');

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
    Route::get('/products/{product}/inventory-adjustments/create', [InventoryAdjustmentController::class, 'create'])->name('products.inventory-adjustments.create');
    Route::post('/products/{product}/inventory-adjustments', [InventoryAdjustmentController::class, 'store'])->name('products.inventory-adjustments.store');
    Route::get('/products/{product}/batch-corrections', [ProductController::class, 'batchCorrections'])->name('products.batch-corrections.index');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::put('/products/{product}/batches/{batch}', [ProductController::class, 'updateBatch'])->name('products.batches.update');

    Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])->name('inventory.transfers.index');
    Route::post('/inventory/transfers', [InventoryTransferController::class, 'store'])->name('inventory.transfers.store');

    Route::get('/cash-register', [CashRegisterController::class, 'index'])->name('cash-register.index');
    Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->name('cash-register.open');
    Route::post('/cash-register/movements', [CashRegisterController::class, 'storeMovement'])->name('cash-register.movements.store');
    Route::post('/cash-register/close', [CashRegisterController::class, 'close'])->name('cash-register.close');
    Route::get('/cash-register/sessions/{cashSession}', [CashRegisterController::class, 'show'])->name('cash-register.sessions.show');

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
    Route::post('/sales/{sale}/payments/mercadopago-point', [MercadoPagoPointController::class, 'store'])->name('sales.payments.mercadopago-point.store');
    Route::get('/sales/{sale}/payments/{payment}/mercadopago-point', [MercadoPagoPointController::class, 'show'])->name('sales.payments.mercadopago-point.show');
    Route::post('/sales/{sale}/payments/{payment}/mercadopago-point/cancel', [MercadoPagoPointController::class, 'cancel'])->name('sales.payments.mercadopago-point.cancel');
    Route::post('/sales/{sale}/fiscal-documents', [SaleFiscalDocumentController::class, 'store'])->name('sales.fiscal-documents.store');
    Route::get('/sales/{sale}/fiscal-documents/{saleFiscalDocument}/pdf', [SaleFiscalDocumentController::class, 'downloadPdf'])->name('sales.fiscal-documents.pdf');
    Route::post('/sales/{sale}/fiscal-documents/{saleFiscalDocument}/reconcile', [SaleFiscalDocumentController::class, 'reconcile'])->name('sales.fiscal-documents.reconcile');

    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');

    Route::get('/electronic-billing', [ElectronicBillingController::class, 'index'])->name('electronic-billing.index');
    Route::get('/fiscal/iva', [FiscalMonthlyReportController::class, 'index'])->name('fiscal.vat-dashboard');
});

Route::middleware(['auth', 'business', 'business.admin'])->group(function (): void {
    Route::post('/electronic-billing/credentials/csr', [FiscalCredentialProxyController::class, 'generateCsr'])->name('electronic-billing.credentials.csr');
    Route::post('/electronic-billing/credentials/certificate', [FiscalCredentialProxyController::class, 'storeCertificate'])->name('electronic-billing.credentials.certificate.store');
    Route::post('/sales/quick-options', [QuickSaleOptionController::class, 'store'])->name('sales.quick-options.store');
    Route::delete('/sales/quick-options/{quickSaleOption}', [QuickSaleOptionController::class, 'destroy'])->name('sales.quick-options.destroy');
    Route::get('/integrations/mercadopago', [MercadoPagoSettingsController::class, 'edit'])->name('mercadopago-settings.edit');
    Route::put('/integrations/mercadopago', [MercadoPagoSettingsController::class, 'update'])->name('mercadopago-settings.update');
    Route::put('/integrations/mercadopago/point-branch', [MercadoPagoSettingsController::class, 'updateBranchPoint'])->name('mercadopago-settings.branch-point.update');

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
