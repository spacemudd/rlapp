<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\ReportsController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\AssetController;
use App\Http\Controllers\Accounting\VATController;
use App\Http\Controllers\Accounting\AnalyticsController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CashAccountController;

/*
|--------------------------------------------------------------------------
| Accounting Routes
|--------------------------------------------------------------------------
|
| Here is where you can register accounting-related routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware(['auth', 'verified'])->prefix('accounting')->name('accounting.')->group(function () {
    // Main Accounting Dashboard
    Route::get('/', [AccountingDashboardController::class, 'index'])->name('dashboard');
    
    // Bank Management Routes
    Route::prefix('banks')->name('banks.')->group(function () {
        Route::get('/', [BankController::class, 'index'])->name('index');
        Route::get('/create', [BankController::class, 'create'])->name('create');
        Route::post('/', [BankController::class, 'store'])->name('store');
        Route::get('/{bank}', [BankController::class, 'show'])->name('show');
        Route::get('/{bank}/edit', [BankController::class, 'edit'])->name('edit');
        Route::put('/{bank}', [BankController::class, 'update'])->name('update');
        Route::delete('/{bank}', [BankController::class, 'destroy'])->name('destroy');
        Route::patch('/{bank}/toggle-status', [BankController::class, 'toggleStatus'])->name('toggle-status');
        Route::patch('/{bank}/update-balance', [BankController::class, 'updateBalance'])->name('update-balance');
    });
    
    // Cash Account Management Routes
    Route::prefix('cash-accounts')->name('cash-accounts.')->group(function () {
        Route::get('/', [CashAccountController::class, 'index'])->name('index');
        Route::get('/create', [CashAccountController::class, 'create'])->name('create');
        Route::post('/', [CashAccountController::class, 'store'])->name('store');
        Route::get('/{cashAccount}', [CashAccountController::class, 'show'])->name('show');
        Route::get('/{cashAccount}/edit', [CashAccountController::class, 'edit'])->name('edit');
        Route::put('/{cashAccount}', [CashAccountController::class, 'update'])->name('update');
        Route::delete('/{cashAccount}', [CashAccountController::class, 'destroy'])->name('destroy');
        Route::patch('/{cashAccount}/toggle-status', [CashAccountController::class, 'toggleStatus'])->name('toggle-status');
        Route::patch('/{cashAccount}/update-balance', [CashAccountController::class, 'updateBalance'])->name('update-balance');
    });
    
    // Asset Management Routes
    Route::prefix('assets')->name('assets.')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::get('/{asset}', [AssetController::class, 'show'])->name('show');
        
        // Depreciation Management
        Route::patch('/{asset}/depreciation', [AssetController::class, 'updateDepreciation'])->name('update-depreciation');
        Route::post('/{asset}/record-depreciation', [AssetController::class, 'recordDepreciation'])->name('record-depreciation');
        Route::post('/record-monthly-depreciation', [AssetController::class, 'recordMonthlyDepreciation'])->name('record-monthly-depreciation');
        
        // Asset Disposal
        Route::get('/{asset}/disposal', [AssetController::class, 'showDisposal'])->name('disposal');
        Route::post('/{asset}/disposal', [AssetController::class, 'recordDisposal'])->name('record-disposal');
        
        // Reporting & Analytics
        Route::get('/reports/depreciation', [AssetController::class, 'depreciationReport'])->name('depreciation-report');
        Route::get('/analytics', [AssetController::class, 'analytics'])->name('analytics');
        
        // Export
        Route::get('/{asset}/schedule/export', [AssetController::class, 'exportSchedule'])->name('export-schedule');
    });
    
    // Financial Reports Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/income-statement', [ReportsController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/balance-sheet', [ReportsController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/trial-balance', [ReportsController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/cash-flow', [ReportsController::class, 'cashFlowStatement'])->name('cash-flow');
        Route::get('/account-statement', [ReportsController::class, 'accountStatement'])->name('account-statement');
        Route::get('/analytics', [ReportsController::class, 'analytics'])->name('analytics');
        Route::get('/comparative', [ReportsController::class, 'comparative'])->name('comparative');
        
        // Export Routes
        Route::post('/export/pdf', [ReportsController::class, 'exportToPdf'])->name('export-pdf');
        Route::post('/export/excel', [ReportsController::class, 'exportToExcel'])->name('export-excel');
        
        // Cache Management
        Route::delete('/cache', [ReportsController::class, 'clearCache'])->name('clear-cache');
    });
    
    // Chart of Accounts Routes
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [ChartOfAccountsController::class, 'index'])->name('index');
        Route::get('/create', [ChartOfAccountsController::class, 'create'])->name('create');
        Route::post('/', [ChartOfAccountsController::class, 'store'])->name('store');
        Route::get('/{account}', [ChartOfAccountsController::class, 'show'])->name('show');
        Route::get('/{account}/edit', [ChartOfAccountsController::class, 'edit'])->name('edit');
        Route::put('/{account}', [ChartOfAccountsController::class, 'update'])->name('update');
        Route::delete('/{account}', [ChartOfAccountsController::class, 'destroy'])->name('destroy');
    });
    
    // VAT Management Routes
    Route::prefix('vat')->name('vat.')->group(function () {
        Route::get('/', [VATController::class, 'index'])->name('index');
        Route::get('/returns', [VATController::class, 'returns'])->name('returns');
        Route::get('/analytics', [VATController::class, 'analytics'])->name('analytics');
        Route::get('/audit-trail', [VATController::class, 'auditTrail'])->name('audit-trail');
        
        // VAT Operations
        Route::post('/calculate', [VATController::class, 'calculateVAT'])->name('calculate');
        Route::post('/validate-number', [VATController::class, 'validateVATNumber'])->name('validate-number');
        Route::post('/generate-return', [VATController::class, 'generateReturn'])->name('generate-return');
        Route::post('/record-payment', [VATController::class, 'recordPayment'])->name('record-payment');
        
        // Invoice VAT Management
        Route::patch('/invoices/{invoice}/vat', [VATController::class, 'updateInvoiceVAT'])->name('invoices.update-vat');
        
        // Compliance & Status
        Route::get('/compliance-status', [VATController::class, 'complianceStatus'])->name('compliance-status');
        
        // Export Routes
        Route::post('/export', [VATController::class, 'export'])->name('export');
    });
    
    // Advanced Analytics & Forecasting Routes
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/financial-overview', [AnalyticsController::class, 'financialOverview'])->name('financial_overview');
        Route::get('/revenue-analytics', [AnalyticsController::class, 'revenueAnalytics'])->name('revenue_analytics');
        Route::get('/customer-analytics', [AnalyticsController::class, 'customerAnalytics'])->name('customer_analytics');
        Route::get('/fleet-analytics', [AnalyticsController::class, 'fleetAnalytics'])->name('fleet_analytics');
        Route::get('/performance-kpis', [AnalyticsController::class, 'performanceKPIs'])->name('performance_kpis');
        Route::get('/trend-analysis', [AnalyticsController::class, 'trendAnalysis'])->name('trend_analysis');
        Route::get('/business-alerts', [AnalyticsController::class, 'businessAlerts'])->name('business_alerts');
        Route::get('/custom', [AnalyticsController::class, 'customAnalytics'])->name('custom');
        Route::get('/benchmarks', [AnalyticsController::class, 'benchmarks'])->name('benchmarks');
        Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
    });

    Route::prefix('forecasting')->name('forecasting.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'forecasting'])->name('index');
        Route::get('/revenue', [AnalyticsController::class, 'revenueForecast'])->name('revenue');
        Route::get('/cash-flow', [AnalyticsController::class, 'cashFlowForecast'])->name('cash_flow');
        Route::get('/demand', [AnalyticsController::class, 'demandForecast'])->name('demand');
        Route::get('/utilization', [AnalyticsController::class, 'utilizationForecast'])->name('utilization');
        Route::get('/profitability', [AnalyticsController::class, 'profitabilityForecast'])->name('profitability');
    });

    // Accounts Receivable Routes (to be implemented)
    Route::prefix('receivables')->name('receivables.')->group(function () {
        Route::get('/', function () {
            return inertia('Accounting/Receivables/Index');
        })->name('index');
        Route::get('/aging', function () {
            return inertia('Accounting/Receivables/Aging');
        })->name('aging');
        Route::get('/statements', function () {
            return inertia('Accounting/Receivables/Statements');
        })->name('statements');
    });
});

// Quick alias routes for common accounting actions
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/accounting/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting');
    
    // Direct routes for easier access
    Route::resource('banks', BankController::class);
    Route::resource('cash-accounts', CashAccountController::class);
}); 