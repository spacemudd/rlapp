<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VATService
{
    // UAE VAT Rates
    const VAT_STANDARD_RATE = 5.0;  // 5% standard rate
    const VAT_ZERO_RATE = 0.0;      // 0% for exports, certain goods
    const VAT_EXEMPT_RATE = null;   // Exempt supplies (financial services, etc.)
    
    // VAT Treatment Types
    const VAT_STANDARD = 'standard';
    const VAT_ZERO_RATED = 'zero_rated';
    const VAT_EXEMPT = 'exempt';
    const VAT_OUT_OF_SCOPE = 'out_of_scope';
    
    protected $accountingService;
    
    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Calculate VAT amount based on treatment type and amount.
     */
    public function calculateVAT($amount, $vatTreatment = self::VAT_STANDARD, $includeVAT = false)
    {
        if ($amount <= 0) {
            return [
                'amount_excluding_vat' => 0,
                'vat_amount' => 0,
                'amount_including_vat' => 0,
                'vat_rate' => 0,
                'vat_treatment' => $vatTreatment,
            ];
        }

        $vatRate = $this->getVATRate($vatTreatment);
        
        if ($vatRate === null) {
            // Exempt supplies
            return [
                'amount_excluding_vat' => $amount,
                'vat_amount' => 0,
                'amount_including_vat' => $amount,
                'vat_rate' => null,
                'vat_treatment' => $vatTreatment,
            ];
        }

        if ($includeVAT) {
            // Amount includes VAT - calculate backwards
            $amountExcludingVAT = $amount / (1 + ($vatRate / 100));
            $vatAmount = $amount - $amountExcludingVAT;
        } else {
            // Amount excludes VAT - calculate forwards
            $amountExcludingVAT = $amount;
            $vatAmount = $amount * ($vatRate / 100);
        }

        return [
            'amount_excluding_vat' => round($amountExcludingVAT, 2),
            'vat_amount' => round($vatAmount, 2),
            'amount_including_vat' => round($amountExcludingVAT + $vatAmount, 2),
            'vat_rate' => $vatRate,
            'vat_treatment' => $vatTreatment,
        ];
    }

    /**
     * Get VAT rate for treatment type.
     */
    public function getVATRate($vatTreatment)
    {
        switch ($vatTreatment) {
            case self::VAT_STANDARD:
                return self::VAT_STANDARD_RATE;
            case self::VAT_ZERO_RATED:
                return self::VAT_ZERO_RATE;
            case self::VAT_EXEMPT:
            case self::VAT_OUT_OF_SCOPE:
                return null;
            default:
                return self::VAT_STANDARD_RATE;
        }
    }

    /**
     * Calculate VAT for an invoice.
     */
    public function calculateInvoiceVAT(Invoice $invoice)
    {
        $items = $invoice->items ?? [];
        $vatSummary = [
            'total_excluding_vat' => 0,
            'total_vat_amount' => 0,
            'total_including_vat' => 0,
            'vat_breakdown' => [],
        ];

        foreach ($items as $item) {
            $itemTotal = ($item->quantity ?? 1) * ($item->unit_price ?? 0);
            $vatTreatment = $item->vat_treatment ?? self::VAT_STANDARD;
            
            $vatCalc = $this->calculateVAT($itemTotal, $vatTreatment, false);
            
            // Add to totals
            $vatSummary['total_excluding_vat'] += $vatCalc['amount_excluding_vat'];
            $vatSummary['total_vat_amount'] += $vatCalc['vat_amount'];
            $vatSummary['total_including_vat'] += $vatCalc['amount_including_vat'];
            
            // Group by VAT rate for breakdown
            $vatRateKey = $vatCalc['vat_rate'] ?? 'exempt';
            if (!isset($vatSummary['vat_breakdown'][$vatRateKey])) {
                $vatSummary['vat_breakdown'][$vatRateKey] = [
                    'rate' => $vatCalc['vat_rate'],
                    'treatment' => $vatCalc['vat_treatment'],
                    'taxable_amount' => 0,
                    'vat_amount' => 0,
                ];
            }
            
            $vatSummary['vat_breakdown'][$vatRateKey]['taxable_amount'] += $vatCalc['amount_excluding_vat'];
            $vatSummary['vat_breakdown'][$vatRateKey]['vat_amount'] += $vatCalc['vat_amount'];
        }

        return $vatSummary;
    }

    /**
     * Generate VAT return data for a specific period.
     */
    public function generateVATReturn($startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        
        // Get all invoices in the period
        $invoices = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->with(['customer', 'items'])
            ->get();

        $vatReturn = [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'period_name' => $startDate->format('M Y'),
            ],
            'output_vat' => [
                'standard_rated_supplies' => ['taxable_amount' => 0, 'vat_amount' => 0],
                'zero_rated_supplies' => ['taxable_amount' => 0, 'vat_amount' => 0],
                'exempt_supplies' => ['taxable_amount' => 0, 'vat_amount' => 0],
                'total_output_vat' => 0,
            ],
            'input_vat' => [
                'standard_rated_expenses' => ['taxable_amount' => 0, 'vat_amount' => 0],
                'zero_rated_expenses' => ['taxable_amount' => 0, 'vat_amount' => 0],
                'total_input_vat' => 0,
            ],
            'net_vat' => [
                'payable_to_government' => 0,
                'refundable_from_government' => 0,
            ],
            'summary' => [
                'total_supplies' => 0,
                'total_purchases' => 0,
                'total_output_vat' => 0,
                'total_input_vat' => 0,
                'net_vat_due' => 0,
            ],
            'invoices_count' => 0,
            'generated_at' => now()->toISOString(),
        ];

        // Process output VAT (sales/invoices)
        foreach ($invoices as $invoice) {
            $vatCalc = $this->calculateInvoiceVAT($invoice);
            
            foreach ($vatCalc['vat_breakdown'] as $rateKey => $breakdown) {
                $treatment = $breakdown['treatment'];
                
                switch ($treatment) {
                    case self::VAT_STANDARD:
                        $vatReturn['output_vat']['standard_rated_supplies']['taxable_amount'] += $breakdown['taxable_amount'];
                        $vatReturn['output_vat']['standard_rated_supplies']['vat_amount'] += $breakdown['vat_amount'];
                        break;
                    case self::VAT_ZERO_RATED:
                        $vatReturn['output_vat']['zero_rated_supplies']['taxable_amount'] += $breakdown['taxable_amount'];
                        break;
                    case self::VAT_EXEMPT:
                        $vatReturn['output_vat']['exempt_supplies']['taxable_amount'] += $breakdown['taxable_amount'];
                        break;
                }
            }
            
            $vatReturn['output_vat']['total_output_vat'] += $vatCalc['total_vat_amount'];
            $vatReturn['summary']['total_supplies'] += $vatCalc['total_excluding_vat'];
            $vatReturn['invoices_count']++;
        }

        // TODO: Process input VAT (purchases/expenses) when expense tracking is implemented
        
        // Calculate net VAT
        $netVAT = $vatReturn['output_vat']['total_output_vat'] - $vatReturn['input_vat']['total_input_vat'];
        
        if ($netVAT > 0) {
            $vatReturn['net_vat']['payable_to_government'] = $netVAT;
        } else {
            $vatReturn['net_vat']['refundable_from_government'] = abs($netVAT);
        }

        $vatReturn['summary']['total_output_vat'] = $vatReturn['output_vat']['total_output_vat'];
        $vatReturn['summary']['total_input_vat'] = $vatReturn['input_vat']['total_input_vat'];
        $vatReturn['summary']['net_vat_due'] = $netVAT;

        return $vatReturn;
    }

    /**
     * Get VAT summary for dashboard.
     */
    public function getVATSummary($period = 'current_quarter')
    {
        $dates = $this->getPeriodDates($period);
        $vatReturn = $this->generateVATReturn($dates['start'], $dates['end']);

        return [
            'current_period' => $vatReturn,
            'compliance_status' => $this->getComplianceStatus(),
            'upcoming_deadlines' => $this->getUpcomingDeadlines(),
            'recent_returns' => $this->getRecentReturns(),
        ];
    }

    /**
     * Get VAT compliance status.
     */
    public function getComplianceStatus()
    {
        $currentQuarter = $this->getCurrentQuarter();
        
        // Check if current quarter return is due
        $returnDue = now()->day >= 28; // VAT returns due by 28th of following month
        
        return [
            'current_quarter' => $currentQuarter,
            'return_due' => $returnDue,
            'registration_status' => 'registered', // TODO: Make this configurable
            'next_return_deadline' => $this->getNextReturnDeadline(),
            'compliance_warnings' => $this->getComplianceWarnings(),
        ];
    }

    /**
     * Get upcoming VAT deadlines.
     */
    public function getUpcomingDeadlines()
    {
        $deadlines = [];
        
        // Next VAT return deadline
        $nextDeadline = $this->getNextReturnDeadline();
        if ($nextDeadline) {
            $deadlines[] = [
                'type' => 'vat_return',
                'description' => 'VAT Return Submission',
                'due_date' => $nextDeadline->format('Y-m-d'),
                'days_remaining' => $nextDeadline->diffInDays(now()),
                'priority' => $nextDeadline->diffInDays(now()) <= 7 ? 'high' : 'normal',
            ];
        }

        // VAT payment deadline (same as return deadline)
        if ($nextDeadline) {
            $deadlines[] = [
                'type' => 'vat_payment',
                'description' => 'VAT Payment Due',
                'due_date' => $nextDeadline->format('Y-m-d'),
                'days_remaining' => $nextDeadline->diffInDays(now()),
                'priority' => $nextDeadline->diffInDays(now()) <= 7 ? 'high' : 'normal',
            ];
        }

        return $deadlines;
    }

    /**
     * Validate VAT number format (UAE TRN).
     */
    public function validateVATNumber($vatNumber)
    {
        // UAE TRN format: 15 digits
        $pattern = '/^\d{15}$/';
        
        return [
            'valid' => preg_match($pattern, $vatNumber),
            'format' => '15 digits (UAE TRN format)',
            'example' => '100000000000003',
        ];
    }

    /**
     * Get VAT treatment options.
     */
    public static function getVATTreatments()
    {
        return [
            self::VAT_STANDARD => 'Standard Rated (5%)',
            self::VAT_ZERO_RATED => 'Zero Rated (0%)',
            self::VAT_EXEMPT => 'Exempt',
            self::VAT_OUT_OF_SCOPE => 'Out of Scope',
        ];
    }

    /**
     * Determine VAT treatment based on service type.
     */
    public function determineVATTreatment($serviceType, $customerType = 'local')
    {
        // Car rental services are generally standard rated
        if ($serviceType === 'car_rental') {
            // Export of services (to non-UAE customers) might be zero-rated
            if ($customerType === 'export') {
                return self::VAT_ZERO_RATED;
            }
            return self::VAT_STANDARD;
        }

        // Default to standard rated
        return self::VAT_STANDARD;
    }

    /**
     * Record VAT liability in IFRS system.
     */
    public function recordVATLiability($vatAmount, $transactionDate = null)
    {
        if ($vatAmount <= 0) {
            return null;
        }

        $transactionDate = $transactionDate ?: now();
        
        try {
            // This would integrate with the AccountingService to record VAT liability
            // For now, return a placeholder structure
            return [
                'vat_amount' => $vatAmount,
                'transaction_date' => $transactionDate,
                'recorded' => true,
                'reference' => 'VAT-' . now()->format('YmdHis'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to record VAT liability', [
                'vat_amount' => $vatAmount,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Generate VAT audit trail for a period.
     */
    public function generateVATAuditTrail($startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        
        $invoices = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->with(['customer', 'items'])
            ->orderBy('invoice_date')
            ->get();

        $auditTrail = [];
        
        foreach ($invoices as $invoice) {
            $vatCalc = $this->calculateInvoiceVAT($invoice);
            
            $auditTrail[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'customer_name' => $invoice->customer->full_name ?? 'Unknown',
                'customer_vat_number' => $invoice->customer->vat_number ?? '',
                'amount_excluding_vat' => $vatCalc['total_excluding_vat'],
                'vat_amount' => $vatCalc['total_vat_amount'],
                'amount_including_vat' => $vatCalc['total_including_vat'],
                'vat_breakdown' => $vatCalc['vat_breakdown'],
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'transactions' => $auditTrail,
            'summary' => [
                'transaction_count' => count($auditTrail),
                'total_excluding_vat' => array_sum(array_column($auditTrail, 'amount_excluding_vat')),
                'total_vat' => array_sum(array_column($auditTrail, 'vat_amount')),
                'total_including_vat' => array_sum(array_column($auditTrail, 'amount_including_vat')),
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    // Private helper methods

    private function getPeriodDates($period)
    {
        switch ($period) {
            case 'current_quarter':
                $quarter = ceil(now()->month / 3);
                $year = now()->year;
                $startMonth = ($quarter - 1) * 3 + 1;
                return [
                    'start' => Carbon::create($year, $startMonth, 1)->startOfDay(),
                    'end' => Carbon::create($year, $startMonth + 2, 1)->endOfMonth()->endOfDay(),
                ];
                
            case 'previous_quarter':
                $quarter = ceil(now()->month / 3) - 1;
                if ($quarter < 1) {
                    $quarter = 4;
                    $year = now()->year - 1;
                } else {
                    $year = now()->year;
                }
                $startMonth = ($quarter - 1) * 3 + 1;
                return [
                    'start' => Carbon::create($year, $startMonth, 1)->startOfDay(),
                    'end' => Carbon::create($year, $startMonth + 2, 1)->endOfMonth()->endOfDay(),
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

    private function getCurrentQuarter()
    {
        return [
            'quarter' => ceil(now()->month / 3),
            'year' => now()->year,
            'name' => 'Q' . ceil(now()->month / 3) . ' ' . now()->year,
        ];
    }

    private function getNextReturnDeadline()
    {
        // VAT returns are due by 28th of the month following the quarter end
        $currentQuarter = ceil(now()->month / 3);
        $quarterEndMonth = $currentQuarter * 3;
        
        if (now()->month <= $quarterEndMonth && now()->day <= 28) {
            // Current quarter return is still due
            $deadlineMonth = $quarterEndMonth + 1;
            $deadlineYear = now()->year;
        } else {
            // Next quarter return
            $nextQuarter = $currentQuarter + 1;
            if ($nextQuarter > 4) {
                $nextQuarter = 1;
                $deadlineYear = now()->year + 1;
            } else {
                $deadlineYear = now()->year;
            }
            $deadlineMonth = ($nextQuarter * 3) + 1;
            if ($deadlineMonth > 12) {
                $deadlineMonth -= 12;
                $deadlineYear++;
            }
        }

        return Carbon::create($deadlineYear, $deadlineMonth, 28);
    }

    private function getComplianceWarnings()
    {
        $warnings = [];
        
        // Check if return is due soon
        $nextDeadline = $this->getNextReturnDeadline();
        $daysRemaining = $nextDeadline->diffInDays(now());
        
        if ($daysRemaining <= 7) {
            $warnings[] = [
                'type' => 'deadline_approaching',
                'message' => "VAT return due in {$daysRemaining} days",
                'severity' => 'warning',
            ];
        }

        if ($daysRemaining <= 3) {
            $warnings[] = [
                'type' => 'deadline_critical',
                'message' => "VAT return due in {$daysRemaining} days - submit immediately",
                'severity' => 'error',
            ];
        }

        return $warnings;
    }

    private function getRecentReturns()
    {
        // This would typically come from a vat_returns table
        // For now, return empty array as placeholder
        return [];
    }
} 