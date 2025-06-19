<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'status',
        'currency',
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

    public function getPaidAmountAttribute()
    {
        return $this->payments()->where('transaction_type', 'payment')->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getDepositBalanceAttribute()
    {
        $deposit = $this->payments()->where('transaction_type', 'deposit')->sum('amount');
        $refund = $this->payments()->where('transaction_type', 'refund')->sum('amount');
        return $deposit - $refund;
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
