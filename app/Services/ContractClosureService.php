<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentReceipt;
use Carbon\Carbon;

class ContractClosureService
{
    public function calculateFinancialSummary(Contract $contract): array
    {
        return [
            'base_rental' => $this->calculateBaseRental($contract),
            'extensions' => $this->calculateExtensions($contract),
            'payments_received' => $this->getPaymentsReceived($contract),
            'additional_charges' => $this->getAdditionalCharges($contract),
            'security_deposit' => $this->getSecurityDeposit($contract),
            'outstanding_balance' => $this->calculateOutstanding($contract),
            'refund_due' => $this->calculateRefundDue($contract),
        ];
    }

    private function calculateBaseRental(Contract $contract): array
    {
        $startDate = Carbon::parse($contract->start_date);
        $endDate = Carbon::parse($contract->end_date);
        $days = $startDate->diffInDays($endDate) + 1; // Include both start and end dates
        
        return [
            'days' => $days,
            'daily_rate' => $contract->daily_rate,
            'total' => $days * $contract->daily_rate,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function calculateExtensions(Contract $contract): array
    {
        // Load extensions if not already loaded
        if (!$contract->relationLoaded('extensions')) {
            $contract->load('extensions');
        }
        
        $extensions = $contract->extensions ?? collect();
        $total = 0;
        $extensionDetails = [];

        foreach ($extensions as $extension) {
            if ($extension->status === 'approved') {
                $extensionTotal = $extension->extension_days * $extension->daily_rate;
                $total += $extensionTotal;
                
                $extensionDetails[] = [
                    'extension_number' => $extension->extension_number,
                    'days' => $extension->extension_days,
                    'daily_rate' => $extension->daily_rate,
                    'total' => $extensionTotal,
                    'reason' => $extension->reason ?? null,
                ];
            }
        }

        return [
            'extensions' => $extensionDetails,
            'total' => $total,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function getPaymentsReceived(Contract $contract): array
    {
        $payments = $contract->paymentReceipts()
            ->with('allocations')
            ->get();

        $groupedPayments = [];
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $receiptTotal = 0;
            $allocations = [];
            
            foreach ($payment->allocations as $allocation) {
                $allocations[] = [
                    'description' => $allocation->description ?? $allocation->row_id,
                    'amount' => $allocation->amount,
                    'type' => $allocation->allocation_type ?? 'advance_payment',
                ];
                
                $receiptTotal += $allocation->amount;
                $totalReceived += $allocation->amount;
            }
            
            $groupedPayments[$payment->id] = [
                'receipt_number' => $payment->receipt_number,
                'payment_date' => $payment->payment_date,
                'payment_method' => $payment->payment_method,
                'total' => $receiptTotal,
                'allocations' => $allocations,
            ];
        }

        return [
            'grouped' => $groupedPayments,
            'total' => $totalReceived,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function getAdditionalCharges(Contract $contract): array
    {
        $charges = [];
        $total = 0;

        // Excess mileage charge
        if ($contract->excess_mileage_charge && $contract->excess_mileage_charge > 0) {
            $charges[] = [
                'type' => 'excess_mileage',
                'description' => __('words.excess_mileage_charge'),
                'amount' => $contract->excess_mileage_charge,
            ];
            $total += $contract->excess_mileage_charge;
        }

        // Fuel charge
        if ($contract->fuel_charge && $contract->fuel_charge > 0) {
            $charges[] = [
                'type' => 'fuel_charge',
                'description' => __('words.fuel_charge'),
                'amount' => $contract->fuel_charge,
            ];
            $total += $contract->fuel_charge;
        }

        // Late return charge (if contract is completed and there's a late return)
        if ($contract->status === 'completed' && $contract->completed_at) {
            $endDate = Carbon::parse($contract->end_date);
            $completedAt = Carbon::parse($contract->completed_at);
            
            if ($completedAt->gt($endDate)) {
                $lateDays = $endDate->diffInDays($completedAt);
                $lateCharge = $lateDays * $contract->daily_rate;
                
                $charges[] = [
                    'type' => 'late_return',
                    'description' => __('words.late_return_charge'),
                    'amount' => $lateCharge,
                    'details' => "{$lateDays} " . __('words.days') . " × " . number_format($contract->daily_rate, 2) . " AED",
                ];
                $total += $lateCharge;
            }
        }

        return [
            'charges' => $charges,
            'total' => $total,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function getSecurityDeposit(Contract $contract): array
    {
        $payments = $contract->paymentReceipts()
            ->with('allocations')
            ->get();

        $depositAmount = 0;
        $depositReceipts = [];

        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                if ($allocation->row_id === 'violation_guarantee') {
                    $depositAmount += $allocation->amount;
                    $depositReceipts[] = [
                        'receipt_number' => $payment->receipt_number,
                        'amount' => $allocation->amount,
                        'date' => $payment->payment_date,
                    ];
                }
            }
        }

        return [
            'amount' => $depositAmount,
            'receipts' => $depositReceipts,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function calculateOutstanding(Contract $contract): array
    {
        // Calculate directly without calling calculateFinancialSummary to avoid circular reference
        $baseRental = $this->calculateBaseRental($contract);
        $extensions = $this->calculateExtensions($contract);
        $additionalCharges = $this->getAdditionalCharges($contract);
        $securityDeposit = $this->getSecurityDeposit($contract);
        $payments = $this->getPaymentsReceived($contract);
        
        $totalDue = $baseRental['total'] + $extensions['total'] + $additionalCharges['total'];
        $totalPaid = $payments['total'];
        
        // Subtract security deposit from paid amount for outstanding calculation
        $netPaid = $totalPaid - $securityDeposit['amount'];
        $outstanding = max(0, $totalDue - $netPaid);

        return [
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'security_deposit' => $securityDeposit['amount'],
            'net_paid' => $netPaid,
            'outstanding' => $outstanding,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function calculateRefundDue(Contract $contract): array
    {
        // Calculate directly without calling calculateFinancialSummary to avoid circular reference
        $baseRental = $this->calculateBaseRental($contract);
        $extensions = $this->calculateExtensions($contract);
        $additionalCharges = $this->getAdditionalCharges($contract);
        $securityDeposit = $this->getSecurityDeposit($contract);
        $payments = $this->getPaymentsReceived($contract);
        
        $totalDue = $baseRental['total'] + $extensions['total'] + $additionalCharges['total'];
        $totalPaid = $payments['total'];
        $netPaid = $totalPaid - $securityDeposit['amount'];
        $outstandingAmount = max(0, $totalDue - $netPaid);
        
        // If outstanding is negative (overpaid), that's the refund amount
        // If outstanding is positive, no refund unless security deposit is returned
        $refundAmount = 0;
        
        if ($outstandingAmount < 0) {
            // Customer overpaid
            $refundAmount = abs($outstandingAmount);
        }
        
        // Always return security deposit if no damages/charges
        // For now, we'll assume security deposit is always refundable
        // In a real system, this would check for damages, fines, etc.
        $refundAmount += $securityDeposit['amount'];

        return [
            'amount' => $refundAmount,
            'security_deposit' => $securityDeposit['amount'],
            'overpayment' => $outstandingAmount < 0 ? abs($outstandingAmount) : 0,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    public function getContractSummary(Contract $contract): array
    {
        $summary = $this->calculateFinancialSummary($contract);
        $outstanding = $this->calculateOutstanding($contract);
        $refund = $this->calculateRefundDue($contract);

        return [
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
                'customer' => $contract->customer,
                'vehicle' => $contract->vehicle,
            ],
            'rental' => [
                'base' => $summary['base_rental'],
                'extensions' => $summary['extensions'],
                'total_rental' => $summary['base_rental']['total'] + $summary['extensions']['total'],
            ],
            'payments' => $summary['payments_received'],
            'additional_charges' => $summary['additional_charges'],
            'security_deposit' => $summary['security_deposit'],
            'financial_summary' => [
                'total_due' => $outstanding['total_due'],
                'total_paid' => $outstanding['total_paid'],
                'outstanding' => $outstanding['outstanding'],
                'refund_due' => $refund['amount'],
                'currency' => $contract->currency ?? 'AED',
            ],
        ];
    }
}
