<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IFRS\Models\Account as IFRSAccount;
use IFRS\Models\LineItem as IFRSLineItem;

class PaymentReceiptAllocation extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'payment_receipt_id',
        'gl_account_id',
        'row_id',
        'description',
        'amount',
        'memo',
        'ifrs_line_item_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'gl_account_id');
    }

    public function ifrsLineItem(): BelongsTo
    {
        return $this->belongsTo(IFRSLineItem::class, 'ifrs_line_item_id');
    }

    /**
     * Scope for specific row types
     */
    public function scopeForRowId($query, string $rowId)
    {
        return $query->where('row_id', $rowId);
    }

    /**
     * Scope for liability allocations
     */
    public function scopeLiability($query)
    {
        return $query->whereIn('row_id', ['violation_guarantee', 'prepayment']);
    }

    /**
     * Scope for income allocations
     */
    public function scopeIncome($query)
    {
        return $query->whereIn('row_id', ['rental_income', 'additional_fees']);
    }
}
