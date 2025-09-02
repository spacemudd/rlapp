<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Jobs\ExpireReservationJob;

class Reservation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uid',
        'customer_id',
        'vehicle_id',
        'rate',
        'pickup_date',
        'pickup_location',
        'return_date',
        'status',
        'reservation_date',
        'notes',
        'total_amount',
        'duration_days',
        'team_id',
    ];

    protected $casts = [
        'pickup_date' => 'datetime',
        'return_date' => 'datetime',
        'reservation_date' => 'datetime',
        'rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'duration_days' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXPIRED = 'expired';

    /**
     * Get all available statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELED => 'Canceled',
        ];
    }

    /**
     * Get the customer that owns the reservation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the vehicle that is reserved.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the team that owns the reservation.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Generate unique UID for reservation
     */
    public static function generateUID()
    {
        do {
            $uid = 'RES-' . strtoupper(substr(uniqid(), -8));
        } while (self::where('uid', $uid)->exists());

        return $uid;
    }

    /**
     * Calculate duration in days
     */
    public function calculateDuration()
    {
        if ($this->pickup_date && $this->return_date) {
            return Carbon::parse($this->pickup_date)->diffInDays(Carbon::parse($this->return_date)); // Exclude end date (day 1 to day 11 = 10 days)
        }
        return 0;
    }

    /**
     * Calculate total amount
     */
    public function calculateTotalAmount()
    {
        $duration = $this->calculateDuration();
        return $duration * $this->rate;
    }

    /**
     * Scope for today's reservations
     */
    public function scopeToday($query)
    {
        return $query->whereDate('pickup_date', today());
    }

    /**
     * Scope for tomorrow's reservations
     */
    public function scopeTomorrow($query)
    {
        return $query->whereDate('pickup_date', today()->addDay());
    }

    /**
     * Scope for pending reservations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for confirmed reservations
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope for completed reservations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for canceled reservations
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    /**
     * Scope to get expired reservations
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Check if reservation is for today
     */
    public function isToday()
    {
        return Carbon::parse($this->pickup_date)->isToday();
    }

    /**
     * Check if reservation is for tomorrow
     */
    public function isTomorrow()
    {
        return Carbon::parse($this->pickup_date)->isTomorrow();
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELED => 'red',
            self::STATUS_EXPIRED => 'orange',
            default => 'gray',
        };
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (!$reservation->uid) {
                $reservation->uid = self::generateUID();
            }

            if (!$reservation->reservation_date) {
                $reservation->reservation_date = now();
            }

            if (!$reservation->team_id && Auth::check()) {
                $reservation->team_id = Auth::user()->team_id;
            }

            // Calculate duration and total amount
            if ($reservation->pickup_date && $reservation->return_date && $reservation->rate) {
                $reservation->duration_days = $reservation->calculateDuration();
                $reservation->total_amount = $reservation->calculateTotalAmount();
            }
        });

        static::created(function ($reservation) {
            // Schedule expiration job if reservation is pending
            if ($reservation->status === self::STATUS_PENDING) {
                ExpireReservationJob::dispatch($reservation->id)->delay(now()->addMinutes(5));
            }
        });

        static::updating(function ($reservation) {
            // Recalculate duration and total amount if dates or rate changed
            if ($reservation->isDirty(['pickup_date', 'return_date', 'rate'])) {
                $reservation->duration_days = $reservation->calculateDuration();
                $reservation->total_amount = $reservation->calculateTotalAmount();
            }
        });
    }
}
