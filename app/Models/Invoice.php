<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Invoice extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'currency',
        'total_days',
        'start_datetime',
        'end_datetime',
        'vehicle_id',
        'customer_id',
        'sub_total',
        'total_discount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'sub_total' => 'decimal:2',
        'total_discount' => 'decimal:2',
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

    public function items()
    {
        return $this->hasMany(\App\Models\InvoiceItem::class, 'invoice_id');
    }
}
