<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class Contract extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_number',
        'status',
        'customer_id',
        'vehicle_id',
        'team_id',
        'start_date',
        'end_date',
        'signed_at',
        'activated_at',
        'completed_at',
        'voided_at',
        'total_amount',
        'deposit_amount',
        'daily_rate',
        'total_days',
        'currency',
        'mileage_limit',
        'excess_mileage_rate',
        'terms_and_conditions',
        'notes',
        'created_by',
        'approved_by',
        'void_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'signed_at' => 'datetime',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'excess_mileage_rate' => 'decimal:2',
        'total_days' => 'integer',
        'mileage_limit' => 'integer',
    ];

    /**
     * Get the customer that owns the contract.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the vehicle that belongs to the contract.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the team that owns the contract.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the invoices associated with the contract.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope a query to only include active contracts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include draft contracts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include completed contracts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include voided contracts.
     */
    public function scopeVoid($query)
    {
        return $query->where('status', 'void');
    }

    /**
     * Get the contract's full title.
     */
    public function getFullTitleAttribute(): string
    {
        return "{$this->contract_number} - {$this->customer->first_name} {$this->customer->last_name}";
    }

    /**
     * Check if the contract is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'active' && $this->end_date->isPast();
    }

    /**
     * Check if the contract is expiring soon (within 7 days).
     */
    public function isExpiringSoon(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        $sevenDaysFromNow = Carbon::now()->addDays(7);
        return $this->end_date->lte($sevenDaysFromNow);
    }

    /**
     * Activate the contract.
     */
    public function activate()
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);
        
        // Update vehicle status to rented
        $this->vehicle->update(['status' => 'rented']);
    }

    /**
     * Complete the contract.
     */
    public function complete()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        // Update vehicle status to available
        $this->vehicle->update(['status' => 'available']);
    }

    /**
     * Void the contract.
     */
    public function void($reason = null)
    {
        $this->update([
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
        
        // Update vehicle status to available if it was rented for this contract
        if ($this->vehicle->status === 'rented') {
            $this->vehicle->update(['status' => 'available']);
        }
    }

    /**
     * Generate a unique contract number.
     */
    public static function generateContractNumber(): string
    {
        $count = static::count();
        $number = 1001 + $count;
        
        do {
            $contractNumber = 'CON-' . str_pad($number, 6, '0', STR_PAD_LEFT);
            $number++;
        } while (static::where('contract_number', $contractNumber)->exists());
        
        return $contractNumber;
    }

    /**
     * Calculate total days between start and end date.
     */
    public function calculateTotalDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1; // +1 to include both start and end days
    }

    /**
     * Calculate total amount based on daily rate and total days.
     */
    public function calculateTotalAmount(): float
    {
        return $this->daily_rate * $this->total_days;
    }
}
