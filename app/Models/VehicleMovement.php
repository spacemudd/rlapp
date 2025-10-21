<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMovement extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vehicle_movements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'event_type',
        'mileage',
        'fuel_level',
        'location_id',
        'contract_id',
        'photos',
        'notes',
        'performed_by_user_id',
        'performed_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mileage' => 'integer',
            'performed_at' => 'datetime',
            'photos' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the vehicle that this movement belongs to.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the location where this movement occurred.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the contract associated with this movement.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who performed this movement.
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /**
     * Scope a query to only include movements for a specific vehicle.
     */
    public function scopeForVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope a query to only include movements of a specific event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to get recent movements.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('performed_at')->limit($limit);
    }

    /**
     * Get the latest mileage recorded for a vehicle.
     *
     * @param string $vehicleId
     * @return int|null
     */
    public static function getLatestMileageForVehicle(string $vehicleId): ?int
    {
        $latestMovement = static::forVehicle($vehicleId)
            ->orderByDesc('performed_at')
            ->first();

        return $latestMovement?->mileage;
    }

    /**
     * Get the event type label for display.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match($this->event_type) {
            'contract_pickup' => __('words.contract_pickup'),
            'contract_return' => __('words.contract_return'),
            'maintenance' => __('words.maintenance'),
            'inspection' => __('words.inspection'),
            'relocation' => __('words.relocation'),
            'manual_adjustment' => __('words.manual_adjustment'),
            'other' => __('words.other'),
            default => $this->event_type,
        };
    }
}

