<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Services\AccountingService;
use IFRS\Models\Account;
use IFRS\Models\Transaction;
use IFRS\Models\LineItem;
use IFRS\Models\Entity;
use IFRS\Reports\IncomeStatement;
use IFRS\Reports\BalanceSheet;
use IFRS\Reports\TrialBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AccountingDashboardController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display the accounting dashboard.
     */
    public function index()
    {
        return Inertia::render('Accounting/Dashboard', [
            'financialOverview' => $this->getFinancialOverview(),
            'cashFlowSummary' => $this->getCashFlowSummary(),
            'accountsReceivable' => $this->getAccountsReceivableSummary(),
            'recentTransactions' => $this->getRecentTransactions(),
            'monthlyStats' => $this->getMonthlyStats(),
            'quickActions' => $this->getQuickActions(),
            'assetsSummary' => $this->getAssetsSummary(),
            'kpiMetrics' => $this->getKPIMetrics(),
            'dailyActivity' => $this->getDailyActivity(),
        ]);
    }

    /**
     * Get financial overview summary.
     */
    private function getFinancialOverview()
    {
        $entity = Entity::first();
        $currentMonth = now()->format('Y-m');
        
        // Get total revenue for current month
        $monthlyRevenue = Invoice::whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->sum('total_amount');

        // Get total payments received this month
        $monthlyPayments = Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->where('status', 'completed')
            ->where('transaction_type', 'payment')
            ->sum('amount');

        // Get outstanding receivables
        $outstandingReceivables = Invoice::where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        // Get overdue amount
        $overdueAmount = Invoice::overdue()->sum('remaining_amount');

        return [
            'monthly_revenue' => $monthlyRevenue,
            'monthly_payments' => $monthlyPayments,
            'outstanding_receivables' => $outstandingReceivables,
            'overdue_amount' => $overdueAmount,
            'collection_rate' => $monthlyRevenue > 0 ? ($monthlyPayments / $monthlyRevenue) * 100 : 0,
        ];
    }

    /**
     * Get cash flow summary.
     */
    private function getCashFlowSummary()
    {
        $bankAccounts = Bank::active()->get(['id', 'name', 'current_balance', 'currency']);
        $cashAccounts = CashAccount::active()->get(['id', 'name', 'current_balance', 'currency', 'type']);

        $totalBankBalance = $bankAccounts->sum('current_balance');
        $totalCashBalance = $cashAccounts->sum('current_balance');
        $totalLiquidAssets = $totalBankBalance + $totalCashBalance;

        return [
            'bank_accounts' => $bankAccounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $account->current_balance,
                    'currency' => $account->currency,
                    'type' => 'bank',
                ];
            }),
            'cash_accounts' => $cashAccounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $account->current_balance,
                    'currency' => $account->currency,
                    'type' => 'cash',
                    'account_type' => $account->type,
                ];
            }),
            'total_bank_balance' => $totalBankBalance,
            'total_cash_balance' => $totalCashBalance,
            'total_liquid_assets' => $totalLiquidAssets,
        ];
    }

    /**
     * Get accounts receivable summary.
     */
    private function getAccountsReceivableSummary()
    {
        $agingBrackets = [
            'current' => ['days' => 0, 'amount' => 0, 'count' => 0],
            '1_30_days' => ['days' => 30, 'amount' => 0, 'count' => 0],
            '31_60_days' => ['days' => 60, 'amount' => 0, 'count' => 0],
            '61_90_days' => ['days' => 90, 'amount' => 0, 'count' => 0],
            '91_180_days' => ['days' => 180, 'amount' => 0, 'count' => 0],
            '180_plus_days' => ['days' => 999, 'amount' => 0, 'count' => 0],
        ];

        $unpaidInvoices = Invoice::where('remaining_amount', '>', 0)
            ->with('customer')
            ->get();

        foreach ($unpaidInvoices as $invoice) {
            $category = $invoice->aging_category;
            
            $bracketKey = match($category) {
                'current' => 'current',
                '1-30 days' => '1_30_days',
                '31-60 days' => '31_60_days',
                '61-90 days' => '61_90_days',
                '91-180 days' => '91_180_days',
                default => '180_plus_days',
            };

            $agingBrackets[$bracketKey]['amount'] += $invoice->remaining_amount;
            $agingBrackets[$bracketKey]['count']++;
        }

        $topDebtors = Customer::withOutstandingBalance()
            ->get(['id', 'first_name', 'last_name'])
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'outstanding_balance' => $customer->outstanding_balance,
                    'overdue_amount' => $customer->getOverdueInvoices()->sum('remaining_amount'),
                ];
            })
            ->sortByDesc('outstanding_balance')
            ->take(5);

        return [
            'aging_analysis' => $agingBrackets,
            'total_outstanding' => $unpaidInvoices->sum('remaining_amount'),
            'total_overdue' => $unpaidInvoices->where('due_date', '<', now())->sum('remaining_amount'),
            'top_debtors' => $topDebtors,
        ];
    }

    /**
     * Get recent transactions.
     */
    private function getRecentTransactions()
    {
        $recentPayments = Payment::with(['customer', 'invoice', 'bank', 'cashAccount'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'type' => 'payment',
                    'date' => $payment->payment_date->format('Y-m-d'),
                    'amount' => $payment->amount,
                    'description' => "Payment from {$payment->customer->full_name}",
                    'reference' => $payment->reference_number,
                    'status' => $payment->status,
                    'account' => $payment->bank ? $payment->bank->name : ($payment->cashAccount ? $payment->cashAccount->name : 'N/A'),
                ];
            });

        $recentInvoices = Invoice::with(['customer', 'vehicle'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'type' => 'invoice',
                    'date' => $invoice->invoice_date->format('Y-m-d'),
                    'amount' => $invoice->total_amount,
                    'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->full_name}",
                    'reference' => $invoice->invoice_number,
                    'status' => $invoice->payment_status,
                    'account' => 'Accounts Receivable',
                ];
            });

        // Convert both collections to arrays and merge
        $paymentsArray = $recentPayments->toArray();
        $invoicesArray = $recentInvoices->toArray();
        $allTransactions = array_merge($paymentsArray, $invoicesArray);
        
        // Sort by date descending (newest first)
        usort($allTransactions, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        // Take first 15 and return as collection
        return collect(array_slice($allTransactions, 0, 15));
    }

    /**
     * Get monthly statistics.
     */
    private function getMonthlyStats()
    {
        $months = collect();
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            
            $revenue = Invoice::whereMonth('invoice_date', $date->month)
                ->whereYear('invoice_date', $date->year)
                ->sum('total_amount');

            $payments = Payment::whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->where('status', 'completed')
                ->where('transaction_type', 'payment')
                ->sum('amount');

            $invoicesCount = Invoice::whereMonth('invoice_date', $date->month)
                ->whereYear('invoice_date', $date->year)
                ->count();

            $months->push([
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
                'payments' => $payments,
                'invoices_count' => $invoicesCount,
                'collection_rate' => $revenue > 0 ? ($payments / $revenue) * 100 : 0,
            ]);
        }

        return $months;
    }

    /**
     * Get quick actions for the dashboard.
     */
    private function getQuickActions()
    {
        return [
            [
                'title' => 'Create Invoice',
                'description' => 'Generate a new customer invoice',
                'icon' => 'receipt',
                'route' => 'invoices.create',
                'color' => 'blue',
            ],
            [
                'title' => 'Record Payment',
                'description' => 'Record a customer payment',
                'icon' => 'credit-card',
                'route' => 'invoices.index', // Navigate to invoices to select one for payment
                'color' => 'green',
            ],
            [
                'title' => 'View Reports',
                'description' => 'Generate financial reports',
                'icon' => 'chart-bar',
                'route' => 'accounting.reports.index',
                'color' => 'purple',
            ],
            [
                'title' => 'Manage Banks',
                'description' => 'Manage bank accounts',
                'icon' => 'building-library',
                'route' => 'accounting.banks.index',
                'color' => 'indigo',
            ],
            [
                'title' => 'Customer Aging',
                'description' => 'View customer aging report',
                'icon' => 'users',
                'route' => 'accounting.receivables.aging',
                'color' => 'yellow',
            ],
            [
                'title' => 'Chart of Accounts',
                'description' => 'Manage chart of accounts',
                'icon' => 'list-bullet',
                'route' => 'accounting.accounts.index',
                'color' => 'gray',
            ],
        ];
    }

    /**
     * Get assets summary.
     */
    private function getAssetsSummary()
    {
        $vehicles = Vehicle::where('ownership_status', 'owned')
            ->get(['id', 'make', 'model', 'year', 'acquisition_cost', 'accumulated_depreciation']);

        $totalAcquisitionCost = $vehicles->sum('acquisition_cost');
        $totalDepreciation = $vehicles->sum('accumulated_depreciation');
        $totalBookValue = $totalAcquisitionCost - $totalDepreciation;

        $fullyDepreciated = $vehicles->filter(function ($vehicle) {
            return $vehicle->isFullyDepreciated();
        })->count();

        return [
            'total_vehicles' => $vehicles->count(),
            'total_acquisition_cost' => $totalAcquisitionCost,
            'total_depreciation' => $totalDepreciation,
            'total_book_value' => $totalBookValue,
            'fully_depreciated' => $fullyDepreciated,
            'average_age' => $vehicles->avg(function ($vehicle) {
                return now()->year - $vehicle->year;
            }),
        ];
    }

    /**
     * Get Key Performance Indicators.
     */
    private function getKPIMetrics()
    {
        $currentMonth = now();
        $previousMonth = now()->subMonth();

        // Current month metrics
        $currentRevenue = Invoice::whereMonth('invoice_date', $currentMonth->month)
            ->whereYear('invoice_date', $currentMonth->year)
            ->sum('total_amount');

        $previousRevenue = Invoice::whereMonth('invoice_date', $previousMonth->month)
            ->whereYear('invoice_date', $previousMonth->year)
            ->sum('total_amount');

        $revenueGrowth = $previousRevenue > 0 ? 
            (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        // Days Sales Outstanding (DSO)
        $totalReceivables = Invoice::where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
        
        $dailyAverageRevenue = $currentRevenue / $currentMonth->daysInMonth;
        $dso = $dailyAverageRevenue > 0 ? $totalReceivables / $dailyAverageRevenue : 0;

        // Average invoice value
        $avgInvoiceValue = Invoice::whereMonth('invoice_date', $currentMonth->month)
            ->whereYear('invoice_date', $currentMonth->year)
            ->avg('total_amount');

        return [
            'revenue_growth' => $revenueGrowth,
            'days_sales_outstanding' => $dso,
            'average_invoice_value' => $avgInvoiceValue,
            'current_month_revenue' => $currentRevenue,
            'previous_month_revenue' => $previousRevenue,
        ];
    }

    /**
     * Get daily activity summary for today.
     */
    private function getDailyActivity()
    {
        $today = now()->toDateString();

        // Get today's payments (exclude refunds)
        $todayPayments = Payment::whereDate('payment_date', $today)
            ->where('status', 'completed')
            ->where('transaction_type', 'payment')
            ->with(['customer', 'invoice', 'bank', 'cashAccount'])
            ->get();

        // Get today's income (invoices issued today)
        $todayInvoices = Invoice::whereDate('invoice_date', $today)
            ->with(['customer', 'vehicle'])
            ->get();

        // Get today's refunds
        $todayRefunds = Payment::whereDate('payment_date', $today)
            ->where('status', 'completed')
            ->where('transaction_type', 'refund')
            ->with(['customer', 'invoice', 'bank', 'cashAccount'])
            ->get();

        // Get today's deposits
        $todayDeposits = Payment::whereDate('payment_date', $today)
            ->where('status', 'completed')
            ->where('transaction_type', 'deposit')
            ->with(['customer', 'invoice', 'bank', 'cashAccount'])
            ->get();

        // Calculate totals
        $totalPayments = $todayPayments->sum('amount');
        $totalIncome = $todayInvoices->sum('total_amount');
        $totalRefunds = $todayRefunds->sum('amount');
        $totalDeposits = $todayDeposits->sum('amount');
        $netCashFlow = $totalPayments + $totalIncome - $totalRefunds - $totalDeposits;

        // Format transactions for display
        $transactions = collect();

        // Add payments
        foreach ($todayPayments as $payment) {
            $transactions->push([
                'id' => $payment->id,
                'type' => 'payment',
                'date' => $payment->payment_date->format('Y-m-d'),
                'time' => $payment->created_at->format('H:i'),
                'amount' => $payment->amount,
                'description' => "Payment from {$payment->customer->full_name}",
                'reference' => $payment->reference_number ?? $payment->invoice->invoice_number ?? 'N/A',
                'status' => $payment->status,
                'method' => $payment->payment_method,
                'account' => $payment->bank ? $payment->bank->name : ($payment->cashAccount ? $payment->cashAccount->name : 'N/A'),
            ]);
        }

        // Add income (invoices)
        foreach ($todayInvoices as $invoice) {
            $transactions->push([
                'id' => $invoice->id,
                'type' => 'income',
                'date' => $invoice->invoice_date->format('Y-m-d'),
                'time' => $invoice->created_at->format('H:i'),
                'amount' => $invoice->total_amount,
                'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->full_name}",
                'reference' => $invoice->invoice_number,
                'status' => $invoice->payment_status,
                'method' => 'invoice',
                'account' => 'Accounts Receivable',
            ]);
        }

        // Add refunds
        foreach ($todayRefunds as $refund) {
            $transactions->push([
                'id' => $refund->id,
                'type' => 'refund',
                'date' => $refund->payment_date->format('Y-m-d'),
                'time' => $refund->created_at->format('H:i'),
                'amount' => $refund->amount,
                'description' => "Refund to {$refund->customer->full_name}",
                'reference' => $refund->reference_number ?? $refund->invoice->invoice_number ?? 'N/A',
                'status' => $refund->status,
                'method' => $refund->payment_method,
                'account' => $refund->bank ? $refund->bank->name : ($refund->cashAccount ? $refund->cashAccount->name : 'N/A'),
            ]);
        }

        // Add deposits
        foreach ($todayDeposits as $deposit) {
            $transactions->push([
                'id' => $deposit->id,
                'type' => 'deposit',
                'date' => $deposit->payment_date->format('Y-m-d'),
                'time' => $deposit->created_at->format('H:i'),
                'amount' => $deposit->amount,
                'description' => "Deposit from {$deposit->customer->full_name}",
                'reference' => $deposit->reference_number ?? $deposit->invoice->invoice_number ?? 'N/A',
                'status' => $deposit->status,
                'method' => $deposit->payment_method,
                'account' => $deposit->bank ? $deposit->bank->name : ($deposit->cashAccount ? $deposit->cashAccount->name : 'N/A'),
            ]);
        }

        // Sort by time descending (newest first)
        $transactions = $transactions->sortByDesc(function ($transaction) {
            return $transaction['date'] . ' ' . $transaction['time'];
        })->values();

        return [
            'date' => $today,
            'total_payments' => $totalPayments,
            'total_income' => $totalIncome,
            'total_refunds' => $totalRefunds,
            'total_deposits' => $totalDeposits,
            'net_cash_flow' => $netCashFlow,
            'payments_count' => $todayPayments->count(),
            'invoices_count' => $todayInvoices->count(),
            'refunds_count' => $todayRefunds->count(),
            'deposits_count' => $todayDeposits->count(),
            'transactions' => $transactions->take(20), // Limit to 20 most recent
        ];
    }
}
