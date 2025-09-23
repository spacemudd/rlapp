<?php

namespace App\Jobs;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reservationId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $reservationId)
    {
        $this->reservationId = $reservationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find the reservation
        $reservation = Reservation::find($this->reservationId);

        if (!$reservation) {
            Log::warning('Reservation not found for expiration job', [
                'reservation_id' => $this->reservationId,
            ]);
            return;
        }

        // Only act on pending web reservations
        if ($reservation->status !== Reservation::STATUS_PENDING) {
            Log::info('Reservation was not expired because status changed', [
                'reservation_id' => $reservation->id,
                'uid' => $reservation->uid,
                'current_status' => $reservation->status,
                'checked_at' => now(),
            ]);
            return;
        }

        // Only expire web reservations, not agent reservations
        if ($reservation->reservation_source !== Reservation::SOURCE_WEB) {
            Log::info('Reservation was not expired because it is not a web reservation', [
                'reservation_id' => $reservation->id,
                'uid' => $reservation->uid,
                'reservation_source' => $reservation->reservation_source,
                'checked_at' => now(),
            ]);
            return;
        }

        // If the reservation was updated within the last 5 minutes, reschedule the job
        $expireAt = $reservation->updated_at->copy()->addMinutes(5);
        if (now()->lt($expireAt)) {
            $delayInSeconds = now()->diffInSeconds($expireAt);
            self::dispatch($reservation->id)->delay(now()->addSeconds($delayInSeconds));

            Log::info('Rescheduled expiration due to recent activity', [
                'reservation_id' => $reservation->id,
                'uid' => $reservation->uid,
                'updated_at' => $reservation->updated_at,
                'will_expire_at' => $expireAt,
                'delay_seconds' => $delayInSeconds,
            ]);
            return;
        }

        // No activity for 5+ minutes while still pending → expire
        $reservation->update(['status' => Reservation::STATUS_EXPIRED]);

        Log::info('Web reservation has been expired after 5 minutes of inactivity', [
            'reservation_id' => $reservation->id,
            'uid' => $reservation->uid,
            'customer_id' => $reservation->customer_id,
            'vehicle_id' => $reservation->vehicle_id,
            'reservation_source' => $reservation->reservation_source,
            'original_status' => Reservation::STATUS_PENDING,
            'new_status' => Reservation::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExpireReservationJob failed for reservation {$this->reservationId}", [
            'reservation_id' => $this->reservationId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
