<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
        'current_location',
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
    ];

    /**
     * Get the vehicle's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
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

    /**
     * Get the invoices for this vehicle.
     */
    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }

    /**
     * Get the contracts for this vehicle.
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
