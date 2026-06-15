<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Language switching route
Route::get('/language/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');
// Theme switching route
Route::post('/theme/switch', [\App\Http\Controllers\ThemeController::class, 'switchTheme'])->name('theme.switch');

// Protected routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        return redirect()->route('business.dashboard');
    })->name('dashboard');

    // Super Admin routes
    Route::prefix('superadmin')->name('superadmin.')->middleware('superadmin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\SuperAdminController::class, 'dashboard'])->name('dashboard');

        // Business Units
        Route::get('/business-units', [\App\Http\Controllers\SuperAdminController::class, 'indexBusinessUnits'])->name('business-units.index');
        Route::get('/business-units/create', [\App\Http\Controllers\SuperAdminController::class, 'createBusinessUnit'])->name('business-units.create');
        Route::post('/business-units', [\App\Http\Controllers\SuperAdminController::class, 'storeBusinessUnit'])->name('business-units.store');
        Route::get('/business-units/{id}/edit', [\App\Http\Controllers\SuperAdminController::class, 'editBusinessUnit'])->name('business-units.edit');
        Route::put('/business-units/{id}', [\App\Http\Controllers\SuperAdminController::class, 'updateBusinessUnit'])->name('business-units.update');
        Route::post('/business-units/{id}/register-kato', [\App\Http\Controllers\SuperAdminController::class, 'registerWithKato'])->name('business-units.register-kato');
        Route::post('/business-units/{id}/refresh-kato-token', [\App\Http\Controllers\SuperAdminController::class, 'refreshKatoToken'])->name('business-units.refresh-kato-token');
        Route::post('/business-units/{id}/restore-kato-username', [\App\Http\Controllers\SuperAdminController::class, 'restoreKatoUsername'])->name('business-units.restore-kato-username');
        Route::post('/switch-business/{businessUnit}', [\App\Http\Controllers\SuperAdminController::class, 'switchBusinessUnit'])->name('switch-business');
        Route::post('/switch-back', [\App\Http\Controllers\SuperAdminController::class, 'switchBackToSuperadmin'])->name('switch-back');

        // Business Unit Users
        Route::get('/business-units/{businessUnit}/users', [\App\Http\Controllers\SuperAdminController::class, 'showBusinessUsers'])->name('business-units.users');
        Route::post('/business-units/{businessUnit}/users', [\App\Http\Controllers\SuperAdminController::class, 'addUserToBusiness'])->name('business-units.add-user');

        // Roles
        Route::get('/business-units/{businessUnit}/roles/create', [\App\Http\Controllers\SuperAdminController::class, 'createRole'])->name('business-units.create-role');
        Route::post('/business-units/{businessUnit}/roles', [\App\Http\Controllers\SuperAdminController::class, 'storeRole'])->name('business-units.store-role');

        // Core Banking Systems
        Route::resource('core-banking-systems', \App\Http\Controllers\CoreBankingSystemController::class)->names([
            'index' => 'core-banking-systems.index',
            'create' => 'core-banking-systems.create',
            'store' => 'core-banking-systems.store',
            'edit' => 'core-banking-systems.edit',
            'update' => 'core-banking-systems.update',
            'destroy' => 'core-banking-systems.destroy',
        ]);

        // User Management
        Route::prefix('user-management')->name('user-management.')->group(function () {
            // Users
            Route::get('/users', [\App\Http\Controllers\UserManagementController::class, 'indexUsers'])->name('users.index');
            Route::get('/users/create', [\App\Http\Controllers\UserManagementController::class, 'createUser'])->name('users.create');
            Route::post('/users', [\App\Http\Controllers\UserManagementController::class, 'storeUser'])->name('users.store');
            Route::get('/users/{id}/edit', [\App\Http\Controllers\UserManagementController::class, 'editUser'])->name('users.edit');
            Route::put('/users/{id}', [\App\Http\Controllers\UserManagementController::class, 'updateUser'])->name('users.update');

            // Roles
            Route::get('/roles', [\App\Http\Controllers\UserManagementController::class, 'indexRoles'])->name('roles.index');
            Route::get('/roles/create', [\App\Http\Controllers\UserManagementController::class, 'createRole'])->name('roles.create');
            Route::post('/roles', [\App\Http\Controllers\UserManagementController::class, 'storeRole'])->name('roles.store');
            Route::get('/roles/{id}/edit', [\App\Http\Controllers\UserManagementController::class, 'editRole'])->name('roles.edit');
            Route::put('/roles/{id}', [\App\Http\Controllers\UserManagementController::class, 'updateRole'])->name('roles.update');

            // Permissions
            Route::get('/permissions', [\App\Http\Controllers\UserManagementController::class, 'indexPermissions'])->name('permissions.index');
            Route::get('/permissions/create', [\App\Http\Controllers\UserManagementController::class, 'createPermission'])->name('permissions.create');
            Route::post('/permissions', [\App\Http\Controllers\UserManagementController::class, 'storePermission'])->name('permissions.store');
            Route::get('/permissions/{id}/edit', [\App\Http\Controllers\UserManagementController::class, 'editPermission'])->name('permissions.edit');
            Route::put('/permissions/{id}', [\App\Http\Controllers\UserManagementController::class, 'updatePermission'])->name('permissions.update');
        });

        // SMS Management
        Route::prefix('sms')->name('sms.')->group(function () {
            // SMS Configuration
            Route::get('/config', [\App\Http\Controllers\SmsConfigurationController::class, 'index'])->name('config.index');
            Route::get('/config/create', [\App\Http\Controllers\SmsConfigurationController::class, 'create'])->name('config.create');
            Route::post('/config', [\App\Http\Controllers\SmsConfigurationController::class, 'store'])->name('config.store');
            Route::get('/config/edit', [\App\Http\Controllers\SmsConfigurationController::class, 'edit'])->name('config.edit');
            Route::put('/config', [\App\Http\Controllers\SmsConfigurationController::class, 'update'])->name('config.update');
            Route::post('/config/test', [\App\Http\Controllers\SmsConfigurationController::class, 'test'])->name('config.test');

            // SMS Credits Management
            Route::get('/load-credits', [\App\Http\Controllers\SmsController::class, 'loadCredits'])->name('load_credits');
            Route::post('/store-credits', [\App\Http\Controllers\SmsController::class, 'storeCredits'])->name('store_credits');
            Route::get('/all-balances', [\App\Http\Controllers\SmsController::class, 'allBusinessesBalance'])->name('all_balances');
        });

        // Transaction Types Management
        Route::resource('transaction-types', \App\Http\Controllers\SuperAdminTransactionTypeController::class)->names([
            'index' => 'transaction-types.index',
            'create' => 'transaction-types.create',
            'store' => 'transaction-types.store',
            'edit' => 'transaction-types.edit',
            'update' => 'transaction-types.update',
            'destroy' => 'transaction-types.destroy',
        ]);

        // Business Unit Transaction Type Assignment
        Route::get('/business-units/{businessUnit}/transaction-types', [\App\Http\Controllers\SuperAdminController::class, 'manageBusinessTransactionTypes'])->name('business-units.transaction-types');
        Route::put('/business-units/{businessUnit}/transaction-types', [\App\Http\Controllers\SuperAdminController::class, 'updateBusinessTransactionTypes'])->name('business-units.transaction-types.update');

        Route::get('/business-units/{businessUnit}/cbs-mappings', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'index'])->name('business-units.cbs-mappings.index');
        Route::get('/business-units/{businessUnit}/cbs-mappings/create', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'create'])->name('business-units.cbs-mappings.create');
        Route::post('/business-units/{businessUnit}/cbs-mappings', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'store'])->name('business-units.cbs-mappings.store');
        Route::get('/business-units/{businessUnit}/cbs-mappings/{mapping}/edit', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'edit'])->name('business-units.cbs-mappings.edit');
        Route::put('/business-units/{businessUnit}/cbs-mappings/{mapping}', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'update'])->name('business-units.cbs-mappings.update');
        Route::delete('/business-units/{businessUnit}/cbs-mappings/{mapping}', [\App\Http\Controllers\BusinessUnitCbsMappingController::class, 'destroy'])->name('business-units.cbs-mappings.destroy');
    });

    // Business routes
    Route::prefix('business')->name('business.')->middleware('business.access')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\BusinessDashboardController::class, 'dashboard'])->name('dashboard');

        // Business Users Management
        Route::get('/users', [\App\Http\Controllers\BusinessUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\BusinessUserController::class, 'create'])->name('users.create');
        Route::post('/users', [\App\Http\Controllers\BusinessUserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [\App\Http\Controllers\BusinessUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{id}', [\App\Http\Controllers\BusinessUserController::class, 'update'])->name('users.update');

        // Business Roles Management
        Route::get('/roles', [\App\Http\Controllers\BusinessRoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [\App\Http\Controllers\BusinessRoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [\App\Http\Controllers\BusinessRoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{id}/edit', [\App\Http\Controllers\BusinessRoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{id}', [\App\Http\Controllers\BusinessRoleController::class, 'update'])->name('roles.update');

        // Configuration routes
        Route::prefix('config')->name('config.')->group(function () {
            // Transaction Types
            Route::resource('transaction-types', \App\Http\Controllers\TransactionTypeController::class);

            // Transaction Costs
            Route::resource('transaction-costs', \App\Http\Controllers\TransactionCostConfigurationController::class);
            Route::post('/transaction-costs/{id}/tiers', [\App\Http\Controllers\TransactionCostConfigurationController::class, 'storeTier'])->name('transaction-costs.tiers.store');
            Route::put('/transaction-costs/tiers/{tierId}', [\App\Http\Controllers\TransactionCostConfigurationController::class, 'updateTier'])->name('transaction-costs.tiers.update');
            Route::delete('/transaction-costs/tiers/{tierId}', [\App\Http\Controllers\TransactionCostConfigurationController::class, 'destroyTier'])->name('transaction-costs.tiers.destroy');

            // Commission Setup
            Route::resource('commissions', \App\Http\Controllers\CommissionConfigurationController::class);
            Route::post('/commissions/{id}/tiers', [\App\Http\Controllers\CommissionConfigurationController::class, 'storeTier'])->name('commissions.tiers.store');
            Route::put('/commissions/tiers/{tierId}', [\App\Http\Controllers\CommissionConfigurationController::class, 'updateTier'])->name('commissions.tiers.update');
            Route::delete('/commissions/tiers/{tierId}', [\App\Http\Controllers\CommissionConfigurationController::class, 'destroyTier'])->name('commissions.tiers.destroy');
        });

        // Reports routes
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ReportsController::class, 'index'])->name('index');
            Route::get('/agents', [\App\Http\Controllers\ReportsController::class, 'agentReports'])->name('agents');
            Route::get('/clients', [\App\Http\Controllers\ReportsController::class, 'clientReports'])->name('clients');
            Route::get('/transactions', [\App\Http\Controllers\ReportsController::class, 'transactionReports'])->name('transactions');
            Route::get('/commissions', [\App\Http\Controllers\ReportsController::class, 'commissionReports'])->name('commissions');
            Route::get('/costs', [\App\Http\Controllers\ReportsController::class, 'costReports'])->name('costs');
            Route::post('/agents', [\App\Http\Controllers\ReportsController::class, 'requestAgentReport'])->name('agents.request');
            Route::post('/clients', [\App\Http\Controllers\ReportsController::class, 'requestClientReport'])->name('clients.request');
            Route::post('/transactions', [\App\Http\Controllers\ReportsController::class, 'requestTransactionReport'])->name('transactions.request');
            Route::post('/commissions', [\App\Http\Controllers\ReportsController::class, 'requestCommissionReport'])->name('commissions.request');
            Route::post('/costs', [\App\Http\Controllers\ReportsController::class, 'requestCostReport'])->name('costs.request');
            Route::get('/status/{id}', [\App\Http\Controllers\ReportsController::class, 'status'])->name('status');
            Route::get('/download/{id}', [\App\Http\Controllers\ReportsController::class, 'download'])->name('download');
            Route::delete('/{id}', [\App\Http\Controllers\ReportsController::class, 'destroy'])->name('delete');
            Route::post('/{id}/retry', [\App\Http\Controllers\ReportsController::class, 'retry'])->name('retry');
        });

        Route::prefix('cnss')->name('cnss.')->group(function () {
            Route::get('/members', [\App\Http\Controllers\CnssMemberController::class, 'index'])->name('members.index');
            Route::get('/members/export', [\App\Http\Controllers\CnssMemberController::class, 'export'])->name('members.export');
            Route::get('/members/{enrollment}/edit', [\App\Http\Controllers\CnssMemberController::class, 'edit'])
                ->whereNumber('enrollment')
                ->name('members.edit');
            Route::put('/members/{enrollment}', [\App\Http\Controllers\CnssMemberController::class, 'update'])
                ->whereNumber('enrollment')
                ->name('members.update');
            Route::delete('/members/{enrollment}', [\App\Http\Controllers\CnssMemberController::class, 'destroy'])
                ->whereNumber('enrollment')
                ->name('members.destroy');
            Route::get('/members/{enrollment}/documents/{type}', [\App\Http\Controllers\CnssMemberController::class, 'document'])
                ->whereNumber('enrollment')
                ->where('type', 'id_document|id_document_back|birth_certificate')
                ->name('members.document');
            Route::get('/members/{enrollment}', [\App\Http\Controllers\CnssMemberController::class, 'show'])
                ->whereNumber('enrollment')
                ->name('members.show');
            Route::get('/contributions/dashboard', [\App\Http\Controllers\CnssContributionWebController::class, 'dashboard'])
                ->name('contributions.dashboard');
            Route::get('/contributions/export', [\App\Http\Controllers\CnssContributionWebController::class, 'export'])
                ->name('contributions.export');
            Route::get('/contributions', [\App\Http\Controllers\CnssContributionWebController::class, 'index'])
                ->name('contributions.index');
        });

        // SMS Dashboard (accessible to all authenticated users with permission)
        Route::prefix('sms')->name('sms.')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\SmsController::class, 'index'])->name('index');
            Route::get('/transactions', [\App\Http\Controllers\SmsController::class, 'getSmsTransactions'])->name('transactions');
            Route::get('/balance', [\App\Http\Controllers\SmsController::class, 'getBalance'])->name('balance');

            // Send SMS routes
            Route::get('/send-single', [\App\Http\Controllers\SmsController::class, 'sendSingle'])->name('send-single');
            Route::post('/send-single', [\App\Http\Controllers\SmsController::class, 'storeSingle'])->name('store-single');
            Route::get('/send-bulk', [\App\Http\Controllers\SmsController::class, 'sendBulk'])->name('send-bulk');
            Route::post('/send-bulk', [\App\Http\Controllers\SmsController::class, 'storeBulk'])->name('store-bulk');

            // SMS History routes
            Route::get('/history', [\App\Http\Controllers\SmsController::class, 'history'])->name('history');
            Route::get('/history/data', [\App\Http\Controllers\SmsController::class, 'getHistory'])->name('history.data');
        });
    });

    // Export routes (must be defined before resource routes to avoid conflicts)
    Route::get('clients/export', [\App\Http\Controllers\ClientController::class, 'export'])->name('clients.export')->middleware('business.access');
    Route::get('agents/export', [\App\Http\Controllers\AgentController::class, 'export'])->name('agents.export')->middleware('business.access');
    Route::get('terminals/export', [\App\Http\Controllers\TerminalController::class, 'export'])->name('terminals.export')->middleware('business.access');
    Route::get('accounts/export', [\App\Http\Controllers\AccountController::class, 'export'])->name('accounts.export')->middleware('business.access');
    Route::get('transactions/export', [\App\Http\Controllers\TransactionController::class, 'export'])->name('transactions.export')->middleware('business.access');
    Route::get('cards/export', [\App\Http\Controllers\CardController::class, 'export'])->name('cards.export')->middleware('business.access');

    // Client Groups routes (must be defined before clients resource route to avoid conflicts)
    Route::get('clients/groups', [\App\Http\Controllers\ClientGroupController::class, 'index'])->name('clients.groups.index')->middleware('business.access');
    Route::get('clients/groups/create', [\App\Http\Controllers\ClientGroupController::class, 'create'])->name('clients.groups.create')->middleware('business.access');
    Route::post('clients/groups', [\App\Http\Controllers\ClientGroupController::class, 'store'])->name('clients.groups.store')->middleware('business.access');
    Route::get('clients/groups/{id}', [\App\Http\Controllers\ClientGroupController::class, 'show'])->name('clients.groups.show')->middleware('business.access');
    Route::get('clients/groups/{id}/edit', [\App\Http\Controllers\ClientGroupController::class, 'edit'])->name('clients.groups.edit')->middleware('business.access');
    Route::put('clients/groups/{id}', [\App\Http\Controllers\ClientGroupController::class, 'update'])->name('clients.groups.update')->middleware('business.access');
    Route::delete('clients/groups/{id}', [\App\Http\Controllers\ClientGroupController::class, 'destroy'])->name('clients.groups.destroy')->middleware('business.access');

    // Clients routes (accessible to business users) — KATO sync before resource to avoid {client} catching paths
    Route::post('/clients/sync-kato-bulk', [\App\Http\Controllers\ClientController::class, 'syncWithKatoBulk'])
        ->name('clients.sync-kato-bulk');
    Route::post('/clients/{id}/sync-kato', [\App\Http\Controllers\ClientController::class, 'syncWithKato'])
        ->whereNumber('id')
        ->name('clients.sync-kato');
    Route::resource('clients', \App\Http\Controllers\ClientController::class)->middleware('business.access');

    // Agents routes (accessible to business users)
    Route::resource('agents', \App\Http\Controllers\AgentController::class)->middleware('business.access');

    // Terminals routes (accessible to business users)
    Route::resource('terminals', \App\Http\Controllers\TerminalController::class)->middleware('business.access');

    // Agent Terminal Assignment routes
    Route::post('/agents/{id}/assign-terminal', [\App\Http\Controllers\AgentController::class, 'assignTerminal'])->name('agents.assign-terminal');
    Route::delete('/agents/{id}/unassign-terminal/{terminalId}', [\App\Http\Controllers\AgentController::class, 'unassignTerminal'])->name('agents.unassign-terminal');
    Route::post('/agents/{id}/reset-pin-otp', [\App\Http\Controllers\AgentController::class, 'sendPinResetOtp'])->name('agents.reset-pin-otp');

    // Accounts routes (accessible to business users)
    Route::resource('accounts', \App\Http\Controllers\AccountController::class)->middleware('business.access');
    Route::post('/clients/{id}/accounts', [\App\Http\Controllers\ClientController::class, 'storeAccount'])->name('clients.accounts.store');

    // Transactions routes (accessible to business users)
    Route::resource('transactions', \App\Http\Controllers\TransactionController::class)->middleware('business.access');

    // Card management routes
    // Card settings routes must be defined BEFORE resource route to avoid conflicts
    Route::get('cards/settings', [\App\Http\Controllers\CardSettingsController::class, 'index'])->name('cards.settings')->middleware('business.access');
    Route::post('cards/settings', [\App\Http\Controllers\CardSettingsController::class, 'update'])->name('cards.settings.update')->middleware('business.access');

    // Card resource routes
    Route::resource('cards', \App\Http\Controllers\CardController::class)->middleware('business.access');
    Route::post('cards/{card}/activate', [\App\Http\Controllers\CardController::class, 'activate'])->name('cards.activate');
    Route::post('cards/{card}/reset-pin', [\App\Http\Controllers\CardController::class, 'sendCardPinReset'])->name('cards.reset-pin');
    Route::post('cards/{card}/block', [\App\Http\Controllers\CardController::class, 'block'])->name('cards.block');
    Route::post('cards/{card}/replace', [\App\Http\Controllers\CardController::class, 'replace'])->name('cards.replace');
    Route::post('cards/import-uids', [\App\Http\Controllers\CardController::class, 'importUids'])->name('cards.import-uids');
    Route::get('cards/import-uids/sample', [\App\Http\Controllers\CardController::class, 'downloadSampleTemplate'])->name('cards.import-uids.sample');
    Route::post('cards/{card}/assign-account', [\App\Http\Controllers\CardController::class, 'assignToAccount'])->name('cards.assign-account');

    // Audit Trail routes (accessible to business users)
    Route::prefix('audit-trail')->name('audit-trail.')->middleware('business.access')->group(function () {
        Route::get('/', [\App\Http\Controllers\AuditTrailController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\AuditTrailController::class, 'show'])->name('show');
        Route::get('/model/{modelType}/{modelId}', [\App\Http\Controllers\AuditTrailController::class, 'byModel'])->name('model');
        Route::get('/user/{userId}', [\App\Http\Controllers\AuditTrailController::class, 'byUser'])->name('user');
        Route::get('/export', [\App\Http\Controllers\AuditTrailController::class, 'export'])->name('export');
    });
});
