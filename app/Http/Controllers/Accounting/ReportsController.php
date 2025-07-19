<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportsController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Display the reports dashboard.
     */
    public function index()
    {
        return Inertia::render('Accounting/Reports/Index', [
            'availableReports' => $this->getAvailableReports(),
            'quickStats' => $this->getQuickStats(),
        ]);
    }

    /**
     * Generate and display Income Statement.
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $report = $this->reportingService->getIncomeStatement(
                $request->start_date,
                $request->end_date
            );

            if ($request->wantsJson()) {
                return response()->json($report);
            }

            return Inertia::render('Accounting/Reports/IncomeStatement', [
                'report' => $report,
                'filters' => [
                    'start_date' => $request->start_date ?? now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $request->end_date ?? now()->endOfMonth()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Income Statement', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Income Statement report.']);
        }
    }

    /**
     * Generate and display Balance Sheet.
     */
    public function balanceSheet(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        try {
            $report = $this->reportingService->getBalanceSheet($request->as_of_date);

            if ($request->wantsJson()) {
                return response()->json($report);
            }

            return Inertia::render('Accounting/Reports/BalanceSheet', [
                'report' => $report,
                'filters' => [
                    'as_of_date' => $request->as_of_date ?? now()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Balance Sheet', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Balance Sheet report.']);
        }
    }

    /**
     * Generate and display Trial Balance.
     */
    public function trialBalance(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        try {
            $report = $this->reportingService->getTrialBalance($request->as_of_date);

            if ($request->wantsJson()) {
                return response()->json($report);
            }

            return Inertia::render('Accounting/Reports/TrialBalance', [
                'report' => $report,
                'filters' => [
                    'as_of_date' => $request->as_of_date ?? now()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Trial Balance', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Trial Balance report.']);
        }
    }

    /**
     * Generate and display Cash Flow Statement.
     */
    public function cashFlowStatement(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $report = $this->reportingService->getCashFlowStatement(
                $request->start_date,
                $request->end_date
            );

            if ($request->wantsJson()) {
                return response()->json($report);
            }

            return Inertia::render('Accounting/Reports/CashFlowStatement', [
                'report' => $report,
                'filters' => [
                    'start_date' => $request->start_date ?? now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $request->end_date ?? now()->endOfMonth()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Cash Flow Statement', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Cash Flow Statement report.']);
        }
    }

    /**
     * Generate account statement for a specific account.
     */
    public function accountStatement(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:ifrs_accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $report = $this->reportingService->getAccountStatement(
                $request->account_id,
                $request->start_date,
                $request->end_date
            );

            if ($request->wantsJson()) {
                return response()->json($report);
            }

            return Inertia::render('Accounting/Reports/AccountStatement', [
                'report' => $report,
                'filters' => [
                    'account_id' => $request->account_id,
                    'start_date' => $request->start_date ?? now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $request->end_date ?? now()->endOfMonth()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Account Statement', [
                'account_id' => $request->account_id,
                'error' => $e->getMessage()
            ]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Account Statement report.']);
        }
    }

    /**
     * Get financial analytics and KPIs.
     */
    public function analytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $analytics = $this->reportingService->getFinancialAnalytics(
                $request->start_date,
                $request->end_date
            );

            if ($request->wantsJson()) {
                return response()->json($analytics);
            }

            return Inertia::render('Accounting/Reports/Analytics', [
                'analytics' => $analytics,
                'filters' => [
                    'start_date' => $request->start_date ?? now()->startOfYear()->format('Y-m-d'),
                    'end_date' => $request->end_date ?? now()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Financial Analytics', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate analytics'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Financial Analytics.']);
        }
    }

    /**
     * Export report to PDF.
     */
    public function exportToPdf(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:income_statement,balance_sheet,trial_balance,cash_flow_statement',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'as_of_date' => 'nullable|date',
        ]);

        try {
            $report = $this->generateReportByType($request->report_type, $request->all());
            
            // Generate PDF using DomPDF or similar
            $pdf = $this->generatePdf($report, $request->report_type);
            
            return $pdf->download($this->getReportFilename($request->report_type, $report));
        } catch (\Exception $e) {
            Log::error('Failed to export report to PDF', [
                'report_type' => $request->report_type,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to export report to PDF.']);
        }
    }

    /**
     * Export report to Excel.
     */
    public function exportToExcel(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:income_statement,balance_sheet,trial_balance,cash_flow_statement',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'as_of_date' => 'nullable|date',
        ]);

        try {
            $report = $this->generateReportByType($request->report_type, $request->all());
            
            // Generate Excel using Laravel Excel or similar
            $excel = $this->generateExcel($report, $request->report_type);
            
            return $excel->download($this->getReportFilename($request->report_type, $report, 'xlsx'));
        } catch (\Exception $e) {
            Log::error('Failed to export report to Excel', [
                'report_type' => $request->report_type,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to export report to Excel.']);
        }
    }

    /**
     * Clear report cache.
     */
    public function clearCache()
    {
        try {
            $this->reportingService->clearReportCache();
            
            return response()->json(['message' => 'Report cache cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to clear report cache', ['error' => $e->getMessage()]);
            
            return response()->json(['error' => 'Failed to clear report cache'], 500);
        }
    }

    /**
     * Get comparative reports (year-over-year, quarter-over-quarter).
     */
    public function comparative(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:income_statement,balance_sheet',
            'comparison_type' => 'required|in:year_over_year,quarter_over_quarter,month_over_month',
            'base_date' => 'required|date',
        ]);

        try {
            $baseDate = Carbon::parse($request->base_date);
            $comparisonDate = $this->getComparisonDate($baseDate, $request->comparison_type);
            
            $currentReport = $this->generateReportByType($request->report_type, [
                'as_of_date' => $baseDate->format('Y-m-d'),
                'start_date' => $baseDate->startOfMonth()->format('Y-m-d'),
                'end_date' => $baseDate->endOfMonth()->format('Y-m-d'),
            ]);

            $previousReport = $this->generateReportByType($request->report_type, [
                'as_of_date' => $comparisonDate->format('Y-m-d'),
                'start_date' => $comparisonDate->startOfMonth()->format('Y-m-d'),
                'end_date' => $comparisonDate->endOfMonth()->format('Y-m-d'),
            ]);

            $comparative = [
                'title' => "Comparative {$currentReport['title']}",
                'comparison_type' => $request->comparison_type,
                'current_period' => $currentReport,
                'previous_period' => $previousReport,
                'variances' => $this->calculateVariances($currentReport, $previousReport),
                'generated_at' => now()->toISOString(),
            ];

            if ($request->wantsJson()) {
                return response()->json($comparative);
            }

            return Inertia::render('Accounting/Reports/Comparative', [
                'report' => $comparative,
                'filters' => $request->only(['report_type', 'comparison_type', 'base_date']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Comparative Report', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate comparative report'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate Comparative Report.']);
        }
    }

    // Private helper methods

    private function getAvailableReports()
    {
        return [
            [
                'name' => 'Income Statement',
                'description' => 'Profit and Loss statement showing revenue and expenses',
                'route' => 'accounting.reports.income-statement',
                'icon' => 'chart-line',
                'type' => 'period',
                'frequency' => 'monthly',
            ],
            [
                'name' => 'Balance Sheet',
                'description' => 'Financial position showing assets, liabilities, and equity',
                'route' => 'accounting.reports.balance-sheet',
                'icon' => 'scale',
                'type' => 'point_in_time',
                'frequency' => 'monthly',
            ],
            [
                'name' => 'Trial Balance',
                'description' => 'List of all accounts with their debit and credit balances',
                'route' => 'accounting.reports.trial-balance',
                'icon' => 'list-check',
                'type' => 'point_in_time',
                'frequency' => 'monthly',
            ],
            [
                'name' => 'Cash Flow Statement',
                'description' => 'Cash receipts and payments from operating, investing, and financing activities',
                'route' => 'accounting.reports.cash-flow',
                'icon' => 'arrows-up-down',
                'type' => 'period',
                'frequency' => 'monthly',
            ],
        ];
    }

    private function getQuickStats()
    {
        $currentMonth = now();
        $previousMonth = now()->subMonth();

        try {
            $currentIncomeStatement = $this->reportingService->getIncomeStatement(
                $currentMonth->startOfMonth(),
                $currentMonth->endOfMonth(),
                true
            );

            $previousIncomeStatement = $this->reportingService->getIncomeStatement(
                $previousMonth->startOfMonth(),
                $previousMonth->endOfMonth(),
                true
            );

            $currentBalanceSheet = $this->reportingService->getBalanceSheet($currentMonth, true);

            return [
                'current_month_revenue' => $currentIncomeStatement['totals']['total_revenue'] ?? 0,
                'previous_month_revenue' => $previousIncomeStatement['totals']['total_revenue'] ?? 0,
                'current_month_net_income' => $currentIncomeStatement['totals']['net_income'] ?? 0,
                'total_assets' => $currentBalanceSheet['totals']['total_assets'] ?? 0,
                'total_liabilities' => $currentBalanceSheet['totals']['total_liabilities'] ?? 0,
                'total_equity' => $currentBalanceSheet['totals']['total_equity'] ?? 0,
                'is_balance_sheet_balanced' => $currentBalanceSheet['is_balanced'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate quick stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function generateReportByType($reportType, $params)
    {
        switch ($reportType) {
            case 'income_statement':
                return $this->reportingService->getIncomeStatement(
                    $params['start_date'] ?? null,
                    $params['end_date'] ?? null,
                    false
                );
            case 'balance_sheet':
                return $this->reportingService->getBalanceSheet(
                    $params['as_of_date'] ?? null,
                    false
                );
            case 'trial_balance':
                return $this->reportingService->getTrialBalance(
                    $params['as_of_date'] ?? null,
                    false
                );
            case 'cash_flow_statement':
                return $this->reportingService->getCashFlowStatement(
                    $params['start_date'] ?? null,
                    $params['end_date'] ?? null,
                    false
                );
            default:
                throw new \Exception('Unknown report type: ' . $reportType);
        }
    }

    private function getComparisonDate($baseDate, $comparisonType)
    {
        $date = $baseDate->copy();
        
        switch ($comparisonType) {
            case 'year_over_year':
                return $date->subYear();
            case 'quarter_over_quarter':
                return $date->subQuarter();
            case 'month_over_month':
                return $date->subMonth();
            default:
                return $date->subYear();
        }
    }

    private function calculateVariances($current, $previous)
    {
        // Implement variance calculation logic
        return [
            'absolute_variance' => 0,
            'percentage_variance' => 0,
        ];
    }

    private function generatePdf($report, $reportType)
    {
        // Implement PDF generation using DomPDF
        // This is a placeholder - you'll need to implement the actual PDF generation
        throw new \Exception('PDF generation not implemented yet');
    }

    private function generateExcel($report, $reportType)
    {
        // Implement Excel generation using Laravel Excel
        // This is a placeholder - you'll need to implement the actual Excel generation
        throw new \Exception('Excel generation not implemented yet');
    }

    private function getReportFilename($reportType, $report, $extension = 'pdf')
    {
        $type = str_replace('_', '-', $reportType);
        $date = now()->format('Y-m-d');
        
        if (isset($report['period']['period_name'])) {
            $period = str_replace([' ', '-'], '_', $report['period']['period_name']);
            return "{$type}_{$period}.{$extension}";
        }
        
        return "{$type}_{$date}.{$extension}";
    }
}
