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
        // Vehicle condition fields
        'pickup_mileage',
        'pickup_fuel_level',
        'pickup_condition_photos',
        'return_mileage',
        'return_fuel_level',
        'return_condition_photos',
        'excess_mileage_charge',
        'fuel_charge',
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
        // Vehicle condition casts
        'pickup_mileage' => 'integer',
        'return_mileage' => 'integer',
        'pickup_condition_photos' => 'array',
        'return_condition_photos' => 'array',
        'excess_mileage_charge' => 'decimal:2',
        'fuel_charge' => 'decimal:2',
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

    /**
     * Get the extensions for this contract.
     */
    public function extensions()
    {
        return $this->hasMany(ContractExtension::class)->orderBy('extension_number');
    }

    /**
     * Get the total number of extension days.
     */
    public function getTotalExtensionDays(): int
    {
        return $this->extensions()->approved()->sum('extension_days');
    }

    /**
     * Get the total extension amount.
     */
    public function getExtensionAmount(): float
    {
        return $this->extensions()->approved()->sum('total_amount');
    }

    /**
     * Get the current effective end date (including extensions).
     */
    public function getEffectiveEndDate(): \Carbon\Carbon
    {
        $latestExtension = $this->extensions()->approved()->latest('extension_number')->first();
        return $latestExtension ? $latestExtension->new_end_date : $this->end_date;
    }

    /**
     * Get the original end date (before any extensions).
     */
    public function getOriginalEndDate(): \Carbon\Carbon
    {
        return $this->end_date;
    }

    /**
     * Check if this contract has any extensions.
     */
    public function hasExtensions(): bool
    {
        return $this->extensions()->approved()->exists();
    }

    /**
     * Record vehicle return condition and calculate additional charges.
     */
    public function recordVehicleReturn(int $returnMileage, string $returnFuelLevel, array $returnPhotos = []): void
    {
        // Store return condition
        $this->update([
            'return_mileage' => $returnMileage,
            'return_fuel_level' => $returnFuelLevel,
            'return_condition_photos' => $returnPhotos,
            'excess_mileage_charge' => $this->calculateExcessMileageCharge($returnMileage),
            'fuel_charge' => $this->calculateFuelCharge($returnFuelLevel),
        ]);
    }

    /**
     * Calculate excess mileage charge.
     */
    public function calculateExcessMileageCharge(int $returnMileage): float
    {
        if (!$this->pickup_mileage || !$this->mileage_limit || !$this->excess_mileage_rate) {
            return 0;
        }

        $actualMileage = $returnMileage - $this->pickup_mileage;
        $excessMileage = max(0, $actualMileage - $this->mileage_limit);
        
        return $excessMileage * $this->excess_mileage_rate;
    }

    /**
     * Calculate fuel charge (if returned with less fuel).
     */
    public function calculateFuelCharge(string $returnFuelLevel): float
    {
        if (!$this->pickup_fuel_level) {
            return 0;
        }

        $fuelLevels = [
            'empty' => 0,
            'low' => 25,
            '1/4' => 25,
            '1/2' => 50,
            '3/4' => 75,
            'full' => 100,
        ];

        $pickupLevel = $fuelLevels[$this->pickup_fuel_level] ?? 0;
        $returnLevel = $fuelLevels[$returnFuelLevel] ?? 0;
        
        // If returned with less fuel, charge for the difference
        if ($returnLevel < $pickupLevel) {
            $fuelDifference = $pickupLevel - $returnLevel;
            // Assume a standard fuel charge rate (can be made configurable)
            $fuelChargeRate = 2.50; // AED per percentage point
            return ($fuelDifference * $fuelChargeRate) / 100;
        }

        return 0;
    }

    /**
     * Get the total additional charges (excess mileage + fuel).
     */
    public function getTotalAdditionalCharges(): float
    {
        return ($this->excess_mileage_charge ?? 0) + ($this->fuel_charge ?? 0);
    }

    /**
     * Get the actual mileage driven.
     */
    public function getActualMileageDriven(): ?int
    {
        if ($this->pickup_mileage && $this->return_mileage) {
            return $this->return_mileage - $this->pickup_mileage;
        }
        return null;
    }

    /**
     * Check if vehicle has been returned.
     */
    public function isVehicleReturned(): bool
    {
        return !is_null($this->return_mileage) && !is_null($this->return_fuel_level);
    }

    /**
     * Get fuel level as percentage for calculations.
     */
    public static function fuelLevelToPercentage(string $level): int
    {
        $levels = [
            'empty' => 0,
            'low' => 10,
            '1/4' => 25,
            '1/2' => 50,
            '3/4' => 75,
            'full' => 100,
        ];

        return $levels[$level] ?? 0;
    }

    /**
     * Extend the contract by a specified number of days.
     */
    public function extend(int $days, ?string $reason = null): ContractExtension
    {
        if ($this->status !== 'active') {
            throw new \Exception('Only active contracts can be extended.');
        }

        // Load the vehicle relation if not already loaded
        if (!$this->relationLoaded('vehicle')) {
            $this->load('vehicle');
        }

        $latestExtension = $this->extensions()->approved()->latest('extension_number')->first();
        $extensionNumber = $latestExtension ? $latestExtension->extension_number + 1 : 1;
        
        $originalEndDate = $latestExtension ? $latestExtension->new_end_date : $this->end_date;
        $newEndDate = Carbon::parse($originalEndDate)->addDays($days);
        
        // Use PricingService for consistent rate calculation
        $pricingService = new \App\Services\PricingService();
        $pricing = $pricingService->calculatePricingForDays($this->vehicle, $days);
        
        $extension = $this->extensions()->create([
            'extension_number' => $extensionNumber,
            'original_end_date' => $originalEndDate,
            'new_end_date' => $newEndDate,
            'extension_days' => $days,
            'daily_rate' => $pricing['effective_daily_rate'],
            'total_amount' => $pricing['total_amount'],
            'reason' => $reason,
            'approved_by' => auth()->user()?->name ?? 'System',
            'status' => 'approved',
        ]);
        
        // Update main contract
        // First update the end_date
        $this->update(['end_date' => $newEndDate]);
        
        // Then calculate total days based on the new end_date
        $newTotalDays = $this->calculateTotalDays();
        $originalAmount = $this->daily_rate * $newTotalDays;
        $extensionAmount = $this->getExtensionAmount();
        
        // Update total_days and total_amount
        $this->update([
            'total_days' => $newTotalDays,
            'total_amount' => $originalAmount + $extensionAmount,
        ]);
        
        return $extension;
    }
}
