<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IFRS\Models\Transaction as IFRSTransaction;
use IFRS\Models\Account as IFRSAccount;

class Invoice extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'issue_date',
        'due_date',
        'total_days',
        'start_datetime',
        'end_datetime',
        'vehicle_id',
        'customer_id',
        'contract_id',
        'team_id',
        'sub_total',
        'subtotal',
        'total_discount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'notes',
        'ifrs_transaction_id',
        'ifrs_receivable_account_id',
        'vat_amount',
        'vat_rate',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'issue_date' => 'datetime',
        'due_date' => 'datetime',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'sub_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the IFRS transaction associated with this invoice.
     */
    public function ifrsTransaction(): BelongsTo
    {
        return $this->belongsTo(IFRSTransaction::class, 'ifrs_transaction_id');
    }

    /**
     * Get the IFRS receivable account associated with this invoice.
     */
    public function ifrsReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'ifrs_receivable_account_id');
    }

    /**
     * Calculate the total paid amount from payments.
     */
    public function calculatePaidAmount()
    {
        return $this->payments()->where('transaction_type', 'payment')->sum('amount');
    }

    /**
     * Calculate the remaining amount.
     */
    public function calculateRemainingAmount()
    {
        return $this->total_amount - $this->calculatePaidAmount();
    }

    /**
     * Sync payment tracking fields with calculated values.
     */
    public function syncPaymentFields()
    {
        $paidAmount = $this->calculatePaidAmount();
        $remainingAmount = $this->total_amount - $paidAmount;
        
        $this->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
        ]);
    }

    /**
     * Boot method to sync payment fields when payments are added and prevent duplicate invoices per contract.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($invoice) {
            if ($invoice->contract_id) {
                $existing = static::where('contract_id', $invoice->contract_id)->exists();
                if ($existing) {
                    throw new \RuntimeException('A contract can only have one invoice.');
                }
            }
        });
        
        static::saved(function ($invoice) {
            // Only sync if the total_amount changed to avoid infinite loops
            if ($invoice->wasChanged('total_amount')) {
                $invoice->syncPaymentFields();
            }
        });
    }

    public function getDepositBalanceAttribute()
    {
        $deposit = $this->payments()->where('transaction_type', 'deposit')->sum('amount');
        $refund = $this->payments()->where('transaction_type', 'refund')->sum('amount');
        return $deposit - $refund;
    }

    /**
     * Calculate VAT amount based on vat_rate and subtotal.
     */
    public function calculateVatAmount()
    {
        if (!$this->vat_rate || !$this->sub_total) {
            return 0;
        }
        
        return ($this->sub_total * $this->vat_rate) / 100;
    }

    /**
     * Get the net amount (subtotal after discount, before VAT).
     */
    public function getNetAmountAttribute()
    {
        return $this->sub_total - $this->total_discount;
    }

    /**
     * Get the payment status based on payment amounts.
     */
    public function getPaymentStatusAttribute()
    {
        if ($this->remaining_amount <= 0) {
            return 'paid';
        } elseif ($this->paid_amount > 0 && $this->remaining_amount > 0) {
            return 'partial_paid';
        } else {
            return 'unpaid';
        }
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue()
    {
        return $this->due_date < now() && in_array($this->payment_status, ['unpaid', 'partial_paid']);
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date);
    }

    /**
     * Get the aging category for this invoice.
     */
    public function getAgingCategoryAttribute()
    {
        if (!$this->isOverdue()) {
            return 'current';
        }
        
        $daysOverdue = $this->days_overdue;
        
        if ($daysOverdue <= 30) {
            return '1-30 days';
        } elseif ($daysOverdue <= 60) {
            return '31-60 days';
        } elseif ($daysOverdue <= 90) {
            return '61-90 days';
        } elseif ($daysOverdue <= 180) {
            return '91-180 days';
        } else {
            return '180+ days';
        }
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('remaining_amount', '>', 0);
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('remaining_amount', '>', 0);
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('remaining_amount', '<=', 0);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $lastInvoice = static::latest()->first();
        $number = $lastInvoice ? (int)substr($lastInvoice->invoice_number, 4) + 1 : 1001;
        return 'INV-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
