<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractAdditionalFee extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_id',
        'fee_type',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
        'vat_account_id',
        'vat_amount',
        'is_vat_exempt',
        'total',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'is_vat_exempt' => 'boolean',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($fee) {
            // Calculate subtotal: (quantity × unit_price) - discount
            $fee->subtotal = round(($fee->quantity * $fee->unit_price) - $fee->discount, 2);

            // Calculate VAT amount if not exempt
            if (!$fee->is_vat_exempt && $fee->vat_account_id) {
                $fee->vat_amount = round($fee->subtotal * 0.05, 2);
            } else {
                $fee->vat_amount = 0;
            }

            // Calculate total: subtotal + vat_amount
            $fee->total = round($fee->subtotal + $fee->vat_amount, 2);
        });
    }

    /**
     * Get the contract that owns the fee.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the VAT account.
     */
    public function vatAccount()
    {
        return $this->belongsTo(\IFRS\Models\Account::class, 'vat_account_id');
    }

    /**
     * Get the user who created the fee.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

