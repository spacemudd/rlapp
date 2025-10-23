<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentReceipt;
use Carbon\Carbon;

class ContractClosureService
{
    private function splitVATAmount(float $amount, bool $isVatInclusive, float $vatRate = 0.05): array
    {
        if ($isVatInclusive) {
            $netAmount = round($amount / (1 + $vatRate), 2);
            $vatAmount = round($amount - $netAmount, 2);
        } else {
            $netAmount = $amount;
            $vatAmount = round($amount * $vatRate, 2);
        }
        
        return [
            'net' => $netAmount,
            'vat' => $vatAmount,
            'total' => $netAmount + $vatAmount,
        ];
    }

    public function calculateFinancialSummary(Contract $contract): array
    {
        return [
            'base_rental' => $this->calculateBaseRental($contract),
            'extensions' => $this->calculateExtensions($contract),
            'payments_received' => $this->getPaymentsReceived($contract),
            'additional_charges' => $this->getAdditionalCharges($contract),
            'additional_fees' => $this->getAdditionalFees($contract),
            'security_deposit' => $this->getSecurityDeposit($contract),
            'outstanding_balance' => $this->calculateOutstanding($contract),
            'refund_due' => $this->calculateRefundDue($contract),
        ];
    }

    private function calculateBaseRental(Contract $contract): array
    {
        // Use the contract's total_days field instead of calculating from dates
        // This ensures consistency with the contract's actual duration
        $days = (int) $contract->total_days;
        $total = $days * $contract->daily_rate;
        $vatSplit = $this->splitVATAmount($total, $contract->is_vat_inclusive ?? true);
        
        return [
            'days' => $days,
            'daily_rate' => $contract->daily_rate,
            'total' => $total,
            'net_amount' => $vatSplit['net'],
            'vat_amount' => $vatSplit['vat'],
            'unit_price_net' => round($vatSplit['net'] / max(1, $days), 2),
            'is_vat_inclusive' => $contract->is_vat_inclusive ?? true,
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
        $totalNet = 0;
        $totalVat = 0;
        $extensionDetails = [];

        foreach ($extensions as $extension) {
            if ($extension->status === 'approved') {
                $extensionTotal = $extension->extension_days * $extension->daily_rate;
                $vatSplit = $this->splitVATAmount($extensionTotal, $contract->is_vat_inclusive ?? true);
                $total += $extensionTotal;
                $totalNet += $vatSplit['net'];
                $totalVat += $vatSplit['vat'];
                
                $extensionDetails[] = [
                    'extension_number' => $extension->extension_number,
                    'days' => $extension->extension_days,
                    'daily_rate' => $extension->daily_rate,
                    'total' => $extensionTotal,
                    'net_amount' => $vatSplit['net'],
                    'vat_amount' => $vatSplit['vat'],
                    'unit_price_net' => round($vatSplit['net'] / max(1, $extension->extension_days), 2),
                    'reason' => $extension->reason ?? null,
                ];
            }
        }

        return [
            'extensions' => $extensionDetails,
            'total' => $total,
            'net_amount' => $totalNet,
            'vat_amount' => $totalVat,
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
        $totalNet = 0;
        $totalVat = 0;

        // Excess mileage charge
        if ($contract->excess_mileage_charge && $contract->excess_mileage_charge > 0) {
            $vatSplit = $this->splitVATAmount($contract->excess_mileage_charge, $contract->is_vat_inclusive ?? true);
            $charges[] = [
                'type' => 'excess_mileage',
                'description' => __('words.excess_mileage_charge'),
                'amount' => $contract->excess_mileage_charge,
                'net_amount' => $vatSplit['net'],
                'vat_amount' => $vatSplit['vat'],
            ];
            $total += $contract->excess_mileage_charge;
            $totalNet += $vatSplit['net'];
            $totalVat += $vatSplit['vat'];
        }

        // Fuel charge
        if ($contract->fuel_charge && $contract->fuel_charge > 0) {
            $vatSplit = $this->splitVATAmount($contract->fuel_charge, $contract->is_vat_inclusive ?? true);
            $charges[] = [
                'type' => 'fuel_charge',
                'description' => __('words.fuel_charge'),
                'amount' => $contract->fuel_charge,
                'net_amount' => $vatSplit['net'],
                'vat_amount' => $vatSplit['vat'],
            ];
            $total += $contract->fuel_charge;
            $totalNet += $vatSplit['net'];
            $totalVat += $vatSplit['vat'];
        }

        // Late return charge (if contract is completed and there's a late return)
        if ($contract->status === 'completed' && $contract->completed_at) {
            $endDate = Carbon::parse($contract->end_date);
            $completedAt = Carbon::parse($contract->completed_at);
            
            if ($completedAt->gt($endDate)) {
                $lateDays = $endDate->diffInDays($completedAt);
                $lateCharge = $lateDays * $contract->daily_rate;
                $vatSplit = $this->splitVATAmount($lateCharge, $contract->is_vat_inclusive ?? true);
                
                $charges[] = [
                    'type' => 'late_return',
                    'description' => __('words.late_return_charge'),
                    'amount' => $lateCharge,
                    'net_amount' => $vatSplit['net'],
                    'vat_amount' => $vatSplit['vat'],
                    'details' => "{$lateDays} " . __('words.days') . " × " . number_format($contract->daily_rate, 2) . " AED",
                ];
                $total += $lateCharge;
                $totalNet += $vatSplit['net'];
                $totalVat += $vatSplit['vat'];
            }
        }

        return [
            'charges' => $charges,
            'total' => $total,
            'net_amount' => $totalNet,
            'vat_amount' => $totalVat,
            'currency' => $contract->currency ?? 'AED',
        ];
    }

    private function getAdditionalFees(Contract $contract): array
    {
        // Load additional fees if not already loaded
        if (!$contract->relationLoaded('additionalFees')) {
            $contract->load('additionalFees');
        }
        
        $fees = [];
        $subtotalAmount = 0;
        $vatAmount = 0;
        $totalAmount = 0;
        
        // Get fee types for localized names
        $feeTypes = \App\Models\SystemSetting::getFeeTypes();
        $feeTypeMap = collect($feeTypes)->keyBy('key');
        $locale = app()->getLocale();

        foreach ($contract->additionalFees as $fee) {
            $feeTypeInfo = $feeTypeMap->get($fee->fee_type);
            $feeTypeName = $feeTypeInfo[$locale] ?? $feeTypeInfo['en'] ?? $fee->fee_type;
            
            $fees[] = [
                'id' => $fee->id,
                'fee_type' => $fee->fee_type,
                'fee_type_name' => $feeTypeName,
                'description' => $fee->description,
                'quantity' => $fee->quantity,
                'unit_price' => $fee->unit_price,
                'discount' => $fee->discount,
                'subtotal' => $fee->subtotal,
                'vat_amount' => $fee->vat_amount,
                'is_vat_exempt' => $fee->is_vat_exempt,
                'total' => $fee->total,
            ];
            
            $subtotalAmount += $fee->subtotal;
            $vatAmount += $fee->vat_amount;
            $totalAmount += $fee->total;
        }

        return [
            'fees' => $fees,
            'subtotal' => $subtotalAmount,
            'vat' => $vatAmount,
            'total' => $totalAmount,
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
        $additionalFees = $this->getAdditionalFees($contract);
        $securityDeposit = $this->getSecurityDeposit($contract);
        $payments = $this->getPaymentsReceived($contract);
        
        $totalDue = $baseRental['total'] + $extensions['total'] + $additionalCharges['total'] + $additionalFees['total'];
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
        $additionalFees = $this->getAdditionalFees($contract);
        $securityDeposit = $this->getSecurityDeposit($contract);
        $payments = $this->getPaymentsReceived($contract);
        
        $totalDue = $baseRental['total'] + $extensions['total'] + $additionalCharges['total'] + $additionalFees['total'];
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
            'additional_fees' => $summary['additional_fees'],
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

    public function buildInvoiceItems(Contract $contract): array
    {
        $items = [];
        $summary = $this->calculateFinancialSummary($contract);
        
        // Base rental
        if ($summary['base_rental']['total'] > 0) {
            $items[] = [
                'description' => 'Base Rental',
                'quantity' => $summary['base_rental']['days'],
                'unit_price' => $summary['base_rental']['unit_price_net'],
                'amount' => $summary['base_rental']['net_amount'],
            ];
        }
        
        // Extensions
        foreach ($summary['extensions']['extensions'] as $ext) {
            $items[] = [
                'description' => "Extension #{$ext['extension_number']}",
                'quantity' => $ext['days'],
                'unit_price' => $ext['unit_price_net'],
                'amount' => $ext['net_amount'],
            ];
        }
        
        // Additional charges
        foreach ($summary['additional_charges']['charges'] as $charge) {
            $items[] = [
                'description' => $charge['description'],
                'quantity' => 1,
                'unit_price' => $charge['net_amount'],
                'amount' => $charge['net_amount'],
            ];
        }
        
        // Additional fees (already have VAT included in total)
        foreach ($summary['additional_fees']['fees'] as $fee) {
            $items[] = [
                'description' => $fee['fee_type_name'] . 
                    ($fee['description'] ? " - {$fee['description']}" : '') .
                    ($fee['discount'] > 0 ? " (Discount: " . number_format($fee['discount'], 2) . ")" : ''),
                'quantity' => $fee['quantity'],
                'unit_price' => $fee['unit_price'],
                'amount' => $fee['total'],
            ];
        }
        
        // Insurance fee (from Quick Pay)
        $insuranceAmount = $this->getInsuranceFeeAmount($contract);
        if ($insuranceAmount > 0) {
            $items[] = [
                'description' => 'Insurance Fee',
                'quantity' => 1,
                'unit_price' => $insuranceAmount,
                'amount' => $insuranceAmount,
            ];
        }
        
        // Security deposit (if included as line)
        $securityDepositAmount = $summary['security_deposit']['amount'];
        if ($securityDepositAmount > 0) {
            $items[] = [
                'description' => 'Security Deposit',
                'quantity' => 1,
                'unit_price' => $securityDepositAmount,
                'amount' => $securityDepositAmount,
            ];
        }
        
        return $items;
    }

    private function getInsuranceFeeAmount(Contract $contract): float
    {
        $payments = $contract->paymentReceipts()
            ->with('allocations')
            ->get();

        $total = 0;
        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                if (str_contains($allocation->description, 'تأمين') || 
                    str_contains(strtolower($allocation->description), 'insurance')) {
                    $total += $allocation->amount;
                }
            }
        }
        
        return $total;
    }
}
