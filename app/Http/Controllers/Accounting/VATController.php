<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\VATService;
use App\Services\AccountingService;
use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class VATController extends Controller
{
    protected $vatService;
    protected $accountingService;

    public function __construct(VATService $vatService, AccountingService $accountingService)
    {
        $this->vatService = $vatService;
        $this->accountingService = $accountingService;
    }

    /**
     * Display the VAT management dashboard.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'current_quarter');
        $vatSummary = $this->vatService->getVATSummary($period);

        return Inertia::render('Accounting/VAT/Index', [
            'vatSummary' => $vatSummary,
            'vatTreatments' => VATService::getVATTreatments(),
            'selectedPeriod' => $period,
            'periodOptions' => $this->getPeriodOptions(),
        ]);
    }

    /**
     * Display VAT return preparation and submission.
     */
    public function returns(Request $request)
    {
        $request->validate([
            'period' => 'nullable|string|in:current_quarter,previous_quarter,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $request->get('period', 'current_quarter');
        
        if ($period === 'custom') {
            $startDate = $request->start_date ?? now()->startOfQuarter();
            $endDate = $request->end_date ?? now()->endOfQuarter();
        } else {
            $dates = $this->getPeriodDates($period);
            $startDate = $dates['start'];
            $endDate = $dates['end'];
        }

        try {
            $vatReturn = $this->vatService->generateVATReturn($startDate, $endDate);
            $auditTrail = $this->vatService->generateVATAuditTrail($startDate, $endDate);

            return Inertia::render('Accounting/VAT/Returns', [
                'vatReturn' => $vatReturn,
                'auditTrail' => $auditTrail,
                'filters' => [
                    'period' => $period,
                    'start_date' => Carbon::parse($startDate)->format('Y-m-d'),
                    'end_date' => Carbon::parse($endDate)->format('Y-m-d'),
                ],
                'periodOptions' => $this->getPeriodOptions(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate VAT return', ['error' => $e->getMessage()]);
            
            return back()->withErrors(['error' => 'Failed to generate VAT return. Please try again.']);
        }
    }

    /**
     * Generate VAT return report.
     */
    public function generateReturn(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|string|in:json,pdf,excel',
        ]);

        try {
            $vatReturn = $this->vatService->generateVATReturn(
                $request->start_date,
                $request->end_date
            );

            $format = $request->get('format', 'json');
            
            if ($format === 'json') {
                return response()->json($vatReturn);
            } elseif ($format === 'pdf') {
                return $this->exportReturnToPdf($vatReturn);
            } elseif ($format === 'excel') {
                return $this->exportReturnToExcel($vatReturn);
            }

            return response()->json($vatReturn);

        } catch (\Exception $e) {
            Log::error('Failed to generate VAT return', [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Failed to generate VAT return'], 500);
        }
    }

    /**
     * Calculate VAT for a given amount.
     */
    public function calculateVAT(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'vat_treatment' => 'nullable|string|in:standard,zero_rated,exempt,out_of_scope',
            'include_vat' => 'nullable|boolean',
        ]);

        $vatCalculation = $this->vatService->calculateVAT(
            $request->amount,
            $request->get('vat_treatment', VATService::VAT_STANDARD),
            $request->boolean('include_vat', false)
        );

        return response()->json($vatCalculation);
    }

    /**
     * Validate VAT number (UAE TRN).
     */
    public function validateVATNumber(Request $request)
    {
        $request->validate([
            'vat_number' => 'required|string',
        ]);

        $validation = $this->vatService->validateVATNumber($request->vat_number);

        return response()->json($validation);
    }

    /**
     * Get VAT compliance status.
     */
    public function complianceStatus()
    {
        $complianceStatus = $this->vatService->getComplianceStatus();
        $upcomingDeadlines = $this->vatService->getUpcomingDeadlines();

        return response()->json([
            'compliance_status' => $complianceStatus,
            'upcoming_deadlines' => $upcomingDeadlines,
        ]);
    }

    /**
     * Display VAT audit trail.
     */
    public function auditTrail(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $auditTrail = $this->vatService->generateVATAuditTrail(
                $request->start_date,
                $request->end_date
            );

            if ($request->wantsJson()) {
                return response()->json($auditTrail);
            }

            return Inertia::render('Accounting/VAT/AuditTrail', [
                'auditTrail' => $auditTrail,
                'filters' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate VAT audit trail', ['error' => $e->getMessage()]);
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to generate audit trail'], 500);
            }

            return back()->withErrors(['error' => 'Failed to generate VAT audit trail.']);
        }
    }

    /**
     * Display VAT analytics and reports.
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', 'current_year');
        
        try {
            // Generate analytics for different periods
            $currentQuarter = $this->vatService->getVATSummary('current_quarter');
            $previousQuarter = $this->vatService->getVATSummary('previous_quarter');
            
            // Calculate quarter-over-quarter growth
            $growthMetrics = $this->calculateVATGrowthMetrics($currentQuarter, $previousQuarter);
            
            // Get monthly VAT trend for the year
            $monthlyTrends = $this->getMonthlyVATTrends();
            
            $analytics = [
                'current_quarter' => $currentQuarter,
                'previous_quarter' => $previousQuarter,
                'growth_metrics' => $growthMetrics,
                'monthly_trends' => $monthlyTrends,
                'compliance_summary' => $this->vatService->getComplianceStatus(),
            ];

            return Inertia::render('Accounting/VAT/Analytics', [
                'analytics' => $analytics,
                'selectedPeriod' => $period,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate VAT analytics', ['error' => $e->getMessage()]);
            
            return back()->withErrors(['error' => 'Failed to generate VAT analytics.']);
        }
    }

    /**
     * Record VAT payment.
     */
    public function recordPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Record VAT payment in IFRS system
            $result = $this->vatService->recordVATLiability(
                $request->amount,
                $request->payment_date
            );

            if ($result) {
                return back()->with('success', 'VAT payment recorded successfully.')
                              ->with('payment_result', $result);
            } else {
                return back()->withErrors(['error' => 'Failed to record VAT payment.']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to record VAT payment', [
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to record VAT payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Update invoice VAT treatment.
     */
    public function updateInvoiceVAT(Request $request, Invoice $invoice)
    {
        $request->validate([
            'vat_treatment' => 'required|string|in:standard,zero_rated,exempt,out_of_scope',
            'recalculate' => 'nullable|boolean',
        ]);

        try {
            // Update invoice items VAT treatment
            foreach ($invoice->items as $item) {
                $item->update(['vat_treatment' => $request->vat_treatment]);
            }

            // Recalculate invoice totals if requested
            if ($request->boolean('recalculate', false)) {
                $vatCalc = $this->vatService->calculateInvoiceVAT($invoice);
                $invoice->update([
                    'subtotal' => $vatCalc['total_excluding_vat'],
                    'vat_amount' => $vatCalc['total_vat_amount'],
                    'total' => $vatCalc['total_including_vat'],
                ]);
            }

            return back()->with('success', 'Invoice VAT treatment updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update invoice VAT treatment', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to update invoice VAT treatment.']);
        }
    }

    /**
     * Export VAT return to different formats.
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|string|in:pdf,excel,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'report_type' => 'required|string|in:return,audit_trail,analytics',
        ]);

        try {
            switch ($request->report_type) {
                case 'return':
                    $data = $this->vatService->generateVATReturn($request->start_date, $request->end_date);
                    break;
                case 'audit_trail':
                    $data = $this->vatService->generateVATAuditTrail($request->start_date, $request->end_date);
                    break;
                case 'analytics':
                    // Generate analytics data
                    $data = $this->vatService->getVATSummary();
                    break;
                default:
                    throw new \Exception('Unknown report type');
            }

            switch ($request->format) {
                case 'pdf':
                    return $this->exportToPdf($data, $request->report_type);
                case 'excel':
                    return $this->exportToExcel($data, $request->report_type);
                case 'csv':
                    return $this->exportToCsv($data, $request->report_type);
                default:
                    throw new \Exception('Unknown export format');
            }

        } catch (\Exception $e) {
            Log::error('Failed to export VAT report', [
                'format' => $request->format,
                'report_type' => $request->report_type,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to export VAT report: ' . $e->getMessage()]);
        }
    }

    // Private helper methods

    private function getPeriodOptions()
    {
        return [
            'current_quarter' => 'Current Quarter',
            'previous_quarter' => 'Previous Quarter',
            'current_month' => 'Current Month',
            'custom' => 'Custom Period',
        ];
    }

    private function getPeriodDates($period)
    {
        switch ($period) {
            case 'current_quarter':
                return [
                    'start' => now()->startOfQuarter(),
                    'end' => now()->endOfQuarter(),
                ];
            case 'previous_quarter':
                return [
                    'start' => now()->subQuarter()->startOfQuarter(),
                    'end' => now()->subQuarter()->endOfQuarter(),
                ];
            case 'current_month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            default:
                return [
                    'start' => now()->startOfQuarter(),
                    'end' => now()->endOfQuarter(),
                ];
        }
    }

    private function calculateVATGrowthMetrics($current, $previous)
    {
        $currentVAT = $current['current_period']['summary']['total_output_vat'] ?? 0;
        $previousVAT = $previous['current_period']['summary']['total_output_vat'] ?? 0;
        
        $growth = 0;
        if ($previousVAT > 0) {
            $growth = (($currentVAT - $previousVAT) / $previousVAT) * 100;
        }

        return [
            'vat_growth_percentage' => round($growth, 2),
            'vat_growth_amount' => $currentVAT - $previousVAT,
            'current_vat' => $currentVAT,
            'previous_vat' => $previousVAT,
        ];
    }

    private function getMonthlyVATTrends()
    {
        $trends = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startDate = $date->copy()->startOfMonth();
            $endDate = $date->copy()->endOfMonth();
            
            $vatReturn = $this->vatService->generateVATReturn($startDate, $endDate);
            
            $trends[] = [
                'month' => $date->format('M Y'),
                'month_short' => $date->format('M'),
                'year' => $date->year,
                'output_vat' => $vatReturn['summary']['total_output_vat'],
                'input_vat' => $vatReturn['summary']['total_input_vat'],
                'net_vat' => $vatReturn['summary']['net_vat_due'],
                'total_supplies' => $vatReturn['summary']['total_supplies'],
            ];
        }

        return $trends;
    }

    private function exportToPdf($data, $reportType)
    {
        // Implement PDF export
        throw new \Exception('PDF export not yet implemented');
    }

    private function exportToExcel($data, $reportType)
    {
        // Implement Excel export
        throw new \Exception('Excel export not yet implemented');
    }

    private function exportToCsv($data, $reportType)
    {
        // Implement CSV export
        throw new \Exception('CSV export not yet implemented');
    }

    private function exportReturnToPdf($vatReturn)
    {
        // Implement VAT return PDF export
        throw new \Exception('VAT return PDF export not yet implemented');
    }

    private function exportReturnToExcel($vatReturn)
    {
        // Implement VAT return Excel export
        throw new \Exception('VAT return Excel export not yet implemented');
    }
}
