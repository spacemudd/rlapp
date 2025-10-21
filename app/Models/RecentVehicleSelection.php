<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class RecentVehicleSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'selected_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
    ];

    /**
     * Get the user that owns the selection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle that was selected.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Record a vehicle selection for a user and maintain only the 3 most recent.
     */
    public static function recordSelection(int $userId, string $vehicleId): void
    {
        DB::transaction(function () use ($userId, $vehicleId) {
            // Check if this vehicle is already in recent selections
            $existing = self::where('user_id', $userId)
                ->where('vehicle_id', $vehicleId)
                ->first();

            if ($existing) {
                // Update the selected_at timestamp
                $existing->update(['selected_at' => now()]);
            } else {
                // Create new selection
                self::create([
                    'user_id' => $userId,
                    'vehicle_id' => $vehicleId,
                    'selected_at' => now(),
                ]);
            }

            // Keep only the 3 most recent selections
            $recentIds = self::where('user_id', $userId)
                ->orderBy('selected_at', 'desc')
                ->limit(3)
                ->pluck('id');

            // Delete older selections
            self::where('user_id', $userId)
                ->whereNotIn('id', $recentIds)
                ->delete();
        });
    }

    /**
     * Get the most recent vehicle selections for a user.
     */
    public static function getRecentForUser(int $userId, int $limit = 3): \Illuminate\Database\Eloquent\Collection
    {
        return self::with(['vehicle.vehicleMake', 'vehicle.vehicleModel'])
            ->where('user_id', $userId)
            ->orderBy('selected_at', 'desc')
            ->limit($limit)
            ->get();
    }
}

