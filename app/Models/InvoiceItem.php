<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $table = 'invoice_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_id',
        'description',
        'amount',
        'discount',
        'vat_treatment',
        'vat_rate',
        'amount_excluding_vat',
        'vat_amount',
        'amount_including_vat',
        'item_category',
        'vat_exempt_reason',
        'vat_notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'amount_excluding_vat' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount_including_vat' => 'decimal:2',
        'vat_exempt_reason' => 'boolean'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
