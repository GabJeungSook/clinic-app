<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Everyone with an account sees the dashboard.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Receipts are viewable by anyone who can reach the record that made them.
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');

    // Checkout — the single point-of-sale / billing screen (services, retail,
    // manual charges, promotions, split payments).
    Route::middleware('can:pos.use')->group(function () {
        Route::get('checkout', [CheckoutController::class, 'create'])->name('checkout.create');
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::post('checkout/patient', [CheckoutController::class, 'storePatient'])->name('checkout.patient');
    });

    // Legacy /pos deep links redirect into the checkout, preserving prefill params.
    Route::get('pos', fn (Request $request) => redirect()->route('checkout.create', $request->only('patient', 'service', 'course')))
        ->middleware('can:pos.use')->name('pos.create');

    // Patients
    Route::middleware('can:patients.view')->group(function () {
        Route::resource('patients', PatientController::class);
        Route::post('patients/{patient}/histories', [PatientController::class, 'storeHistory'])
            ->name('patients.histories.store');
        Route::match(['put', 'patch'], 'patients/{patient}/chart', [PatientController::class, 'updateChart'])
            ->name('patients.chart.update');
    });

    // Appointments / booking
    Route::middleware('can:appointments.view')->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::post('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
    });

    // Reports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('reports/appointments', [ReportController::class, 'appointments'])->name('reports.appointments');
        Route::get('reports/patients', [ReportController::class, 'patients'])->name('reports.patients');
        Route::get('reports/treatments', [ReportController::class, 'treatments'])->name('reports.treatments');
        Route::get('reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('reports/purchasing', [ReportController::class, 'purchasing'])->name('reports.purchasing');
    });

    // Expenses — register cash-outs. View is broad; create/delete needs manage.
    Route::middleware('can:expenses.view')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Services + Bill of Materials
    Route::middleware('can:services.manage')->group(function () {
        Route::get('services/categories', [ServiceCategoryController::class, 'index'])->name('service-categories.index');
        Route::post('services/categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::put('services/categories/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('service-categories.update');
        Route::delete('services/categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
        Route::resource('services', ServiceController::class)->except('show');
        Route::post('services/{service}/consumables', [ServiceController::class, 'storeConsumable'])->name('services.consumables.store');
        Route::delete('services/{service}/consumables/{consumable}', [ServiceController::class, 'destroyConsumable'])->name('services.consumables.destroy');
    });

    // Inventory items, categories, units, and stock actions
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('inventory/categories', [InventoryCategoryController::class, 'index'])->name('inventory-categories.index');
        Route::post('inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory-categories.store');
        Route::put('inventory/categories/{category}', [InventoryCategoryController::class, 'update'])->name('inventory-categories.update');
        Route::delete('inventory/categories/{category}', [InventoryCategoryController::class, 'destroy'])->name('inventory-categories.destroy');

        Route::get('inventory/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('inventory/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('inventory/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('inventory/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::get('inventory/stocktake', [StocktakeController::class, 'edit'])->name('inventory.stocktake');
        Route::post('inventory/stocktake', [StocktakeController::class, 'store'])->name('inventory.stocktake.store');

        Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'inventory']);
        Route::post('inventory/{inventory}/receive', [InventoryController::class, 'receiveStock'])->name('inventory.receive');
        Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
        Route::post('inventory/{inventory}/batches/{batch}/write-off', [InventoryController::class, 'writeOff'])->name('inventory.writeoff');
    });

    // Purchasing
    Route::middleware('can:purchasing.view')->group(function () {
        Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('purchases/reorder', [PurchaseController::class, 'reorder'])->name('purchases.reorder');
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
    });

    // Treatments (read-only progress overview; sessions are performed in Record Visit)
    Route::middleware('can:treatments.view')->group(function () {
        Route::get('treatments', [TreatmentController::class, 'index'])->name('treatments.index');
        Route::get('treatments/{treatment}', [TreatmentController::class, 'show'])->name('treatments.show');
    });

    // Billing
    Route::middleware('can:billing.view')->group(function () {
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/receipt', [InvoiceController::class, 'generateReceipt'])->name('invoices.receipt');
    });
    // Void / refund are corrective actions — managers only.
    Route::middleware('can:billing.manage')->group(function () {
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('invoices/{invoice}/refund', [InvoiceController::class, 'refund'])->name('invoices.refund');
    });
    Route::middleware('can:promotions.manage')->group(function () {
        Route::resource('promotions', PromotionController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Staff / user accounts
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Clinic settings
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('clinic-settings', [ClinicSettingsController::class, 'edit'])->name('clinic-settings.edit');
        Route::put('clinic-settings', [ClinicSettingsController::class, 'update'])->name('clinic-settings.update');
        Route::post('clinic-settings/backup', [ClinicSettingsController::class, 'backupNow'])->name('clinic-settings.backup');
        Route::get('clinic-settings/backup/{name}/download', [ClinicSettingsController::class, 'downloadBackup'])->name('clinic-settings.backup.download')->where('name', '[A-Za-z0-9\-_.]+');
        Route::post('clinic-settings/restore', [ClinicSettingsController::class, 'restore'])->name('clinic-settings.restore');

        // Online updates (GitHub releases) — desktop-only; degrade gracefully elsewhere.
        Route::post('clinic-settings/update/check', [ClinicSettingsController::class, 'checkForUpdates'])->name('clinic-settings.update.check');
        Route::post('clinic-settings/update/download', [ClinicSettingsController::class, 'downloadUpdate'])->name('clinic-settings.update.download');
        Route::post('clinic-settings/update/install', [ClinicSettingsController::class, 'installUpdate'])->name('clinic-settings.update.install');
        Route::get('clinic-settings/update/status', [ClinicSettingsController::class, 'updateStatus'])->name('clinic-settings.update.status');
    });
});

require __DIR__.'/settings.php';
