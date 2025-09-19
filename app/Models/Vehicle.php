<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IFRS\Models\Account as IFRSAccount;

class Vehicle extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plate_number',
        'make',
        'model',
        'year',
        'color',
        'seats',
        'doors',
        'category',
        'price_daily',
        'price_weekly',
        'price_monthly',
        'location_id',
        'branch_id',
        'status',
        'ownership_status',
        'borrowed_from_office',
        'borrowing_terms',
        'borrowing_start_date',
        'borrowing_end_date',
        'borrowing_notes',
        'expected_return_date',
        'upcoming_reservations',
        'latest_return_date',
        'odometer',
        'chassis_number',
        'license_expiry_date',
        'insurance_expiry_date',
        'recent_note',
        'ifrs_asset_account_id',
        'acquisition_cost',
        'acquisition_date',
        'depreciation_method',
        'useful_life_years',
        'salvage_value',
        'accumulated_depreciation',
        'last_depreciation_date',
        'estimated_recoverable_amount',
        'is_active',
        'disposal_date',
        'disposal_method',
        'sale_price',
        'disposal_gain_loss',
        'disposal_notes',
        'current_mileage',
        'total_expected_mileage',
        'asset_tag',
        'insurance_policy_number',
        'insurance_expiry',
        'last_maintenance_date',
        'next_maintenance_due',
        'annual_maintenance_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'expected_return_date' => 'datetime',
        'latest_return_date' => 'datetime',
        'upcoming_reservations' => 'integer',
        'odometer' => 'integer',
        'license_expiry_date' => 'date',
        'insurance_expiry_date' => 'date',
        'borrowing_start_date' => 'date',
        'borrowing_end_date' => 'date',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'useful_life_years' => 'integer',
        'last_depreciation_date' => 'date',
        'estimated_recoverable_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'disposal_date' => 'date',
        'sale_price' => 'decimal:2',
        'disposal_gain_loss' => 'decimal:2',
        'current_mileage' => 'decimal:2',
        'total_expected_mileage' => 'decimal:2',
        'insurance_expiry' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_due' => 'date',
        'annual_maintenance_cost' => 'decimal:2',
    ];

    /**
     * Depreciation methods constants.
     */
    const DEPRECIATION_STRAIGHT_LINE = 'straight_line';
    const DEPRECIATION_DECLINING_BALANCE = 'declining_balance';
    const DEPRECIATION_SUM_OF_YEARS = 'sum_of_years';

    public static function getDepreciationMethods()
    {
        return [
            self::DEPRECIATION_STRAIGHT_LINE => 'Straight Line',
            self::DEPRECIATION_DECLINING_BALANCE => 'Declining Balance',
            self::DEPRECIATION_SUM_OF_YEARS => 'Sum of Years Digits',
        ];
    }

    /**
     * Get the IFRS asset account associated with this vehicle.
     */
    public function ifrsAssetAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'ifrs_asset_account_id');
    }

    /**
     * Get the vehicle's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    /**
     * Get the current book value of the vehicle.
     */
    public function getBookValueAttribute()
    {
        if (!$this->acquisition_cost) {
            return 0;
        }
        
        return $this->acquisition_cost - $this->accumulated_depreciation;
    }

    /**
     * Get the depreciation method label.
     */
    public function getDepreciationMethodLabelAttribute()
    {
        return self::getDepreciationMethods()[$this->depreciation_method] ?? $this->depreciation_method;
    }

    /**
     * Calculate annual depreciation based on the selected method.
     */
    public function calculateAnnualDepreciation()
    {
        if (!$this->acquisition_cost || !$this->useful_life_years) {
            return 0;
        }

        $depreciableAmount = $this->acquisition_cost - $this->salvage_value;

        switch ($this->depreciation_method) {
            case self::DEPRECIATION_STRAIGHT_LINE:
                return $depreciableAmount / $this->useful_life_years;

            case self::DEPRECIATION_DECLINING_BALANCE:
                $rate = 2 / $this->useful_life_years; // Double declining balance
                $currentBookValue = $this->book_value;
                return min($currentBookValue * $rate, $depreciableAmount);

            case self::DEPRECIATION_SUM_OF_YEARS:
                $totalYears = $this->useful_life_years;
                $sumOfYears = ($totalYears * ($totalYears + 1)) / 2;
                $currentYear = $this->getCurrentDepreciationYear();
                $fraction = ($totalYears - $currentYear + 1) / $sumOfYears;
                return $depreciableAmount * $fraction;

            default:
                return $depreciableAmount / $this->useful_life_years;
        }
    }

    /**
     * Get the current year of depreciation.
     */
    private function getCurrentDepreciationYear()
    {
        if (!$this->acquisition_date) {
            return 1;
        }
        
        $yearsSinceAcquisition = now()->diffInYears($this->acquisition_date) + 1;
        return min($yearsSinceAcquisition, $this->useful_life_years);
    }

    /**
     * Check if the vehicle is fully depreciated.
     */
    public function isFullyDepreciated()
    {
        return $this->book_value <= $this->salvage_value;
    }

    /**
     * Get the remaining years of useful life.
     */
    public function getRemainingUsefulLifeAttribute()
    {
        if (!$this->acquisition_date) {
            return $this->useful_life_years;
        }
        
        $yearsUsed = now()->diffInYears($this->acquisition_date);
        return max(0, $this->useful_life_years - $yearsUsed);
    }

    /**
     * Scope a query to only include available vehicles.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope a query to only include rented vehicles.
     */
    public function scopeRented($query)
    {
        return $query->where('status', 'rented');
    }

    /**
     * Scope a query to only include vehicles in maintenance.
     */
    public function scopeInMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    /**
     * Scope a query to only include out of service vehicles.
     */
    public function scopeOutOfService($query)
    {
        return $query->where('status', 'out_of_service');
    }

    /**
     * Scope a query to only include owned vehicles.
     */
    public function scopeOwned($query)
    {
        return $query->where('ownership_status', 'owned');
    }

    /**
     * Scope a query to only include borrowed vehicles.
     */
    public function scopeBorrowed($query)
    {
        return $query->where('ownership_status', 'borrowed');
    }

    /**
     * Check if the vehicle is borrowed.
     */
    public function isBorrowed(): bool
    {
        return $this->ownership_status === 'borrowed';
    }

    /**
     * Check if the vehicle is owned.
     */
    public function isOwned(): bool
    {
        return $this->ownership_status === 'owned';
    }

    /**
     * Check if the borrowing period is expired (only applicable for borrowed vehicles).
     */
    public function isBorrowingExpired(): bool
    {
        if (!$this->isBorrowed() || !$this->borrowing_end_date) {
            return false;
        }
        
        return $this->borrowing_end_date->isPast();
    }

    /**
     * Check if the vehicle's license is expired.
     */
    public function isLicenseExpired(): bool
    {
        return $this->license_expiry_date->isPast();
    }

    /**
     * Check if the vehicle's insurance is expired.
     */
    public function isInsuranceExpired(): bool
    {
        return $this->insurance_expiry_date->isPast();
    }

    // Additional Asset Management Methods

    /**
     * Check if the vehicle needs impairment testing.
     */
    public function needsImpairmentTesting(): bool
    {
        if (!$this->estimated_recoverable_amount || !$this->acquisition_cost) {
            return false;
        }
        
        return $this->book_value > $this->estimated_recoverable_amount;
    }

    /**
     * Get the age of the vehicle in years.
     */
    public function getAgeInYears(): float
    {
        if (!$this->acquisition_date) {
            return 0;
        }
        
        return $this->acquisition_date->diffInYears(now());
    }

    /**
     * Check if maintenance is due soon (within 30 days).
     */
    public function isMaintenanceDueSoon(): bool
    {
        if (!$this->next_maintenance_due) {
            return false;
        }
        
        return $this->next_maintenance_due->isAfter(now()) && 
               $this->next_maintenance_due->diffInDays(now()) <= 30;
    }

    /**
     * Check if maintenance is overdue.
     */
    public function isMaintenanceOverdue(): bool
    {
        if (!$this->next_maintenance_due) {
            return false;
        }
        
        return $this->next_maintenance_due->isPast();
    }

    /**
     * Get usage percentage (current mileage / total expected mileage).
     */
    public function getUsagePercentage(): float
    {
        if (!$this->total_expected_mileage || !$this->current_mileage) {
            return 0;
        }
        
        return min(100, ($this->current_mileage / $this->total_expected_mileage) * 100);
    }

    /**
     * Get depreciation rate percentage per year.
     */
    public function getDepreciationRate(): float
    {
        if (!$this->useful_life_years) {
            return 0;
        }
        
        return 100 / $this->useful_life_years;
    }

    /**
     * Check if the vehicle has been disposed.
     */
    public function isDisposed(): bool
    {
        return !$this->is_active;
    }

    /**
     * Get the disposal status label.
     */
    public function getDisposalStatusLabel(): string
    {
        if ($this->is_active) {
            return 'Active';
        }
        
        return match($this->disposal_method) {
            'sale' => 'Sold',
            'trade_in' => 'Traded In',
            'scrapped' => 'Scrapped',
            'donated' => 'Donated',
            'lost' => 'Lost/Stolen',
            default => 'Disposed',
        };
    }

    /**
     * Scope: Only active assets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only disposed assets.
     */
    public function scopeDisposed($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Assets that need maintenance.
     */
    public function scopeMaintenanceDue($query)
    {
        return $query->whereNotNull('next_maintenance_due')
                    ->where('next_maintenance_due', '<=', now()->addDays(30));
    }

    /**
     * Get the invoices for this vehicle.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the contracts for this vehicle.
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the location where this vehicle is stationed.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the branch where this vehicle is assigned.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
