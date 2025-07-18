<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContractExtension extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_id',
        'extension_number',
        'original_end_date',
        'new_end_date',
        'extension_days',
        'daily_rate',
        'total_amount',
        'reason',
        'approved_by',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'original_end_date' => 'datetime',
        'new_end_date' => 'datetime',
        'extension_days' => 'integer',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the contract that owns this extension.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Scope a query to only include approved extensions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include pending extensions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get the extension's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "Extension #{$this->extension_number} (+{$this->extension_days} days)";
    }

    /**
     * Check if this extension is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if this extension is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this extension is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
