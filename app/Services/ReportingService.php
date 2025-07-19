<?php

namespace App\Services;

use IFRS\Models\Entity;
use IFRS\Models\ReportingPeriod;
use IFRS\Models\Account;
use IFRS\Models\Transaction;
use IFRS\Models\LineItem;
use IFRS\Reports\IncomeStatement;
use IFRS\Reports\BalanceSheet;
use IFRS\Reports\TrialBalance;
use IFRS\Reports\CashFlowStatement;
use IFRS\Reports\AccountStatement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReportingService
{
    protected $entity;
    protected $cacheMinutes = 30; // Cache reports for 30 minutes

    public function __construct()
    {
        $this->entity = Entity::first();
    }

    /**
     * Generate Income Statement (Profit & Loss).
     */
    public function getIncomeStatement($startDate = null, $endDate = null, $useCache = true)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();
        
        $cacheKey = "income_statement_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $reportingPeriod = $this->getOrCreateReportingPeriod($startDate->year);
            
            $incomeStatement = new IncomeStatement(
                $reportingPeriod->id,
                $startDate,
                $endDate
            );

            $report = [
                'title' => 'Income Statement',
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'period_name' => $startDate->format('M Y') . ' - ' . $endDate->format('M Y'),
                ],
                'sections' => $this->formatIncomeStatementSections($incomeStatement),
                'totals' => [
                    'total_revenue' => $incomeStatement->getTotals()[Account::OPERATING_REVENUE] ?? 0,
                    'total_expenses' => $incomeStatement->getTotals()[Account::OPERATING_EXPENSE] ?? 0,
                    'gross_profit' => ($incomeStatement->getTotals()[Account::OPERATING_REVENUE] ?? 0) - 
                                   ($incomeStatement->getTotals()[Account::DIRECT_EXPENSE] ?? 0),
                    'net_income' => $incomeStatement->getNetIncome(),
                ],
                'generated_at' => now()->toISOString(),
            ];

            if ($useCache) {
                Cache::put($cacheKey, $report, now()->addMinutes($this->cacheMinutes));
            }

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate Income Statement', ['error' => $e->getMessage()]);
            return $this->getEmptyReport('Income Statement', $startDate, $endDate);
        }
    }

    /**
     * Generate Balance Sheet.
     */
    public function getBalanceSheet($asOfDate = null, $useCache = true)
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        
        $cacheKey = "balance_sheet_{$asOfDate->format('Y-m-d')}";
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $reportingPeriod = $this->getOrCreateReportingPeriod($asOfDate->year);
            
            $balanceSheet = new BalanceSheet(
                $reportingPeriod->id,
                $asOfDate
            );

            $report = [
                'title' => 'Balance Sheet',
                'period' => [
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                    'period_name' => 'As of ' . $asOfDate->format('F d, Y'),
                ],
                'sections' => $this->formatBalanceSheetSections($balanceSheet),
                'totals' => [
                    'total_assets' => $balanceSheet->getTotals()[Account::CURRENT_ASSET] + 
                                    $balanceSheet->getTotals()[Account::NON_CURRENT_ASSET],
                    'total_liabilities' => $balanceSheet->getTotals()[Account::CURRENT_LIABILITY] + 
                                         $balanceSheet->getTotals()[Account::NON_CURRENT_LIABILITY],
                    'total_equity' => $balanceSheet->getTotals()[Account::EQUITY],
                ],
                'generated_at' => now()->toISOString(),
            ];

            // Verify accounting equation
            $report['is_balanced'] = abs(
                $report['totals']['total_assets'] - 
                ($report['totals']['total_liabilities'] + $report['totals']['total_equity'])
            ) < 0.01;

            if ($useCache) {
                Cache::put($cacheKey, $report, now()->addMinutes($this->cacheMinutes));
            }

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate Balance Sheet', ['error' => $e->getMessage()]);
            return $this->getEmptyReport('Balance Sheet', null, $asOfDate);
        }
    }

    /**
     * Generate Trial Balance.
     */
    public function getTrialBalance($asOfDate = null, $useCache = true)
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        
        $cacheKey = "trial_balance_{$asOfDate->format('Y-m-d')}";
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $reportingPeriod = $this->getOrCreateReportingPeriod($asOfDate->year);
            
            $trialBalance = new TrialBalance(
                $reportingPeriod->id,
                $asOfDate
            );

            $accounts = [];
            foreach ($trialBalance->getAccounts() as $accountType => $accountList) {
                foreach ($accountList as $account) {
                    $accounts[] = [
                        'account_code' => $account->code,
                        'account_name' => $account->name,
                        'account_type' => $account->account_type,
                        'debit_balance' => $account->debit_balance ?? 0,
                        'credit_balance' => $account->credit_balance ?? 0,
                        'category' => $this->getAccountCategory($account->account_type),
                    ];
                }
            }

            $report = [
                'title' => 'Trial Balance',
                'period' => [
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                    'period_name' => 'As of ' . $asOfDate->format('F d, Y'),
                ],
                'accounts' => $accounts,
                'totals' => [
                    'total_debits' => array_sum(array_column($accounts, 'debit_balance')),
                    'total_credits' => array_sum(array_column($accounts, 'credit_balance')),
                ],
                'generated_at' => now()->toISOString(),
            ];

            // Verify trial balance is balanced
            $report['is_balanced'] = abs(
                $report['totals']['total_debits'] - $report['totals']['total_credits']
            ) < 0.01;

            if ($useCache) {
                Cache::put($cacheKey, $report, now()->addMinutes($this->cacheMinutes));
            }

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate Trial Balance', ['error' => $e->getMessage()]);
            return $this->getEmptyReport('Trial Balance', null, $asOfDate);
        }
    }

    /**
     * Generate Cash Flow Statement.
     */
    public function getCashFlowStatement($startDate = null, $endDate = null, $useCache = true)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();
        
        $cacheKey = "cash_flow_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $reportingPeriod = $this->getOrCreateReportingPeriod($startDate->year);
            
            $cashFlowStatement = new CashFlowStatement(
                $reportingPeriod->id,
                $startDate,
                $endDate
            );

            $report = [
                'title' => 'Cash Flow Statement',
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'period_name' => $startDate->format('M Y') . ' - ' . $endDate->format('M Y'),
                ],
                'sections' => [
                    'operating_activities' => $this->getCashFlowOperatingActivities($cashFlowStatement),
                    'investing_activities' => $this->getCashFlowInvestingActivities($cashFlowStatement),
                    'financing_activities' => $this->getCashFlowFinancingActivities($cashFlowStatement),
                ],
                'totals' => [
                    'net_operating_cash' => $cashFlowStatement->getOperatingCashFlow(),
                    'net_investing_cash' => $cashFlowStatement->getInvestingCashFlow(),
                    'net_financing_cash' => $cashFlowStatement->getFinancingCashFlow(),
                    'net_cash_change' => $cashFlowStatement->getNetCashFlow(),
                ],
                'generated_at' => now()->toISOString(),
            ];

            if ($useCache) {
                Cache::put($cacheKey, $report, now()->addMinutes($this->cacheMinutes));
            }

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate Cash Flow Statement', ['error' => $e->getMessage()]);
            return $this->getEmptyReport('Cash Flow Statement', $startDate, $endDate);
        }
    }

    /**
     * Generate Account Statement for a specific account.
     */
    public function getAccountStatement($accountId, $startDate = null, $endDate = null)
    {
        $account = Account::find($accountId);
        if (!$account) {
            throw new \Exception('Account not found');
        }

        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        try {
            $accountStatement = new AccountStatement($accountId, $startDate, $endDate);
            
            return [
                'title' => 'Account Statement',
                'account' => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->account_type,
                ],
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
                'transactions' => $this->getAccountTransactions($account, $startDate, $endDate),
                'balances' => [
                    'opening_balance' => $accountStatement->getOpeningBalance(),
                    'closing_balance' => $accountStatement->getClosingBalance(),
                    'total_debits' => $accountStatement->getTotalDebits(),
                    'total_credits' => $accountStatement->getTotalCredits(),
                ],
                'generated_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate Account Statement', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get financial analytics and KPIs.
     */
    public function getFinancialAnalytics($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfYear();
        $endDate = $endDate ? Carbon::parse($endDate) : now();

        $incomeStatement = $this->getIncomeStatement($startDate, $endDate, false);
        $balanceSheet = $this->getBalanceSheet($endDate, false);

        $analytics = [
            'profitability_ratios' => $this->calculateProfitabilityRatios($incomeStatement, $balanceSheet),
            'liquidity_ratios' => $this->calculateLiquidityRatios($balanceSheet),
            'efficiency_ratios' => $this->calculateEfficiencyRatios($incomeStatement, $balanceSheet),
            'growth_metrics' => $this->calculateGrowthMetrics($startDate, $endDate),
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'generated_at' => now()->toISOString(),
        ];

        return $analytics;
    }

    /**
     * Clear all cached reports.
     */
    public function clearReportCache()
    {
        $patterns = [
            'income_statement_*',
            'balance_sheet_*',
            'trial_balance_*',
            'cash_flow_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    // Private helper methods

    private function getOrCreateReportingPeriod($year)
    {
        return ReportingPeriod::where('calendar_year', $year)
            ->where('entity_id', $this->entity->id)
            ->first() ?? ReportingPeriod::create([
                'calendar_year' => $year,
                'entity_id' => $this->entity->id,
            ]);
    }

    private function formatIncomeStatementSections($incomeStatement)
    {
        return [
            'revenue' => $this->getAccountsByType($incomeStatement, [Account::OPERATING_REVENUE, Account::NON_OPERATING_REVENUE]),
            'cost_of_sales' => $this->getAccountsByType($incomeStatement, [Account::DIRECT_EXPENSE]),
            'operating_expenses' => $this->getAccountsByType($incomeStatement, [Account::OPERATING_EXPENSE]),
            'other_expenses' => $this->getAccountsByType($incomeStatement, [Account::NON_OPERATING_EXPENSE]),
        ];
    }

    private function formatBalanceSheetSections($balanceSheet)
    {
        return [
            'current_assets' => $this->getAccountsByType($balanceSheet, [Account::CURRENT_ASSET]),
            'non_current_assets' => $this->getAccountsByType($balanceSheet, [Account::NON_CURRENT_ASSET]),
            'current_liabilities' => $this->getAccountsByType($balanceSheet, [Account::CURRENT_LIABILITY]),
            'non_current_liabilities' => $this->getAccountsByType($balanceSheet, [Account::NON_CURRENT_LIABILITY]),
            'equity' => $this->getAccountsByType($balanceSheet, [Account::EQUITY]),
        ];
    }

    private function getAccountsByType($report, $types)
    {
        $accounts = [];
        foreach ($types as $type) {
            if (isset($report->getAccounts()[$type])) {
                foreach ($report->getAccounts()[$type] as $account) {
                    $accounts[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'amount' => $account->balance ?? 0,
                    ];
                }
            }
        }
        return $accounts;
    }

    private function getCashFlowOperatingActivities($cashFlowStatement)
    {
        // Implementation would depend on your specific IFRS package version
        return [];
    }

    private function getCashFlowInvestingActivities($cashFlowStatement)
    {
        return [];
    }

    private function getCashFlowFinancingActivities($cashFlowStatement)
    {
        return [];
    }

    private function getAccountTransactions($account, $startDate, $endDate)
    {
        return LineItem::where('account_id', $account->id)
            ->whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('transaction_date', [$startDate, $endDate]);
            })
            ->with(['transaction'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($lineItem) {
                return [
                    'date' => $lineItem->transaction->transaction_date,
                    'description' => $lineItem->transaction->narration,
                    'debit' => $lineItem->amount > 0 ? $lineItem->amount : 0,
                    'credit' => $lineItem->amount < 0 ? abs($lineItem->amount) : 0,
                    'balance' => $lineItem->amount,
                ];
            });
    }

    private function getAccountCategory($accountType)
    {
        $categories = [
            Account::CURRENT_ASSET => 'Assets',
            Account::NON_CURRENT_ASSET => 'Assets',
            Account::CURRENT_LIABILITY => 'Liabilities',
            Account::NON_CURRENT_LIABILITY => 'Liabilities',
            Account::EQUITY => 'Equity',
            Account::OPERATING_REVENUE => 'Revenue',
            Account::NON_OPERATING_REVENUE => 'Revenue',
            Account::OPERATING_EXPENSE => 'Expenses',
            Account::NON_OPERATING_EXPENSE => 'Expenses',
            Account::DIRECT_EXPENSE => 'Cost of Sales',
        ];

        return $categories[$accountType] ?? 'Other';
    }

    private function calculateProfitabilityRatios($incomeStatement, $balanceSheet)
    {
        $revenue = $incomeStatement['totals']['total_revenue'] ?? 0;
        $netIncome = $incomeStatement['totals']['net_income'] ?? 0;
        $totalAssets = $balanceSheet['totals']['total_assets'] ?? 0;
        $totalEquity = $balanceSheet['totals']['total_equity'] ?? 0;

        return [
            'net_profit_margin' => $revenue > 0 ? ($netIncome / $revenue) * 100 : 0,
            'return_on_assets' => $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0,
            'return_on_equity' => $totalEquity > 0 ? ($netIncome / $totalEquity) * 100 : 0,
        ];
    }

    private function calculateLiquidityRatios($balanceSheet)
    {
        // Placeholder - implement based on your balance sheet structure
        return [
            'current_ratio' => 0,
            'quick_ratio' => 0,
            'cash_ratio' => 0,
        ];
    }

    private function calculateEfficiencyRatios($incomeStatement, $balanceSheet)
    {
        // Placeholder - implement based on your business needs
        return [
            'asset_turnover' => 0,
            'inventory_turnover' => 0,
            'receivables_turnover' => 0,
        ];
    }

    private function calculateGrowthMetrics($startDate, $endDate)
    {
        // Compare with previous period
        return [
            'revenue_growth' => 0,
            'profit_growth' => 0,
            'asset_growth' => 0,
        ];
    }

    private function getEmptyReport($title, $startDate = null, $endDate = null)
    {
        return [
            'title' => $title,
            'period' => [
                'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
            ],
            'sections' => [],
            'totals' => [],
            'error' => 'Unable to generate report. Please ensure the accounting system is properly set up.',
            'generated_at' => now()->toISOString(),
        ];
    }
} 