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

        // Check if reservation exists and is still pending
        if ($reservation && $reservation->status === Reservation::STATUS_PENDING) {
            // Update status to expired
            $reservation->update(['status' => Reservation::STATUS_EXPIRED]);

            Log::info("Reservation {$reservation->uid} has been expired after 5 minutes", [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'vehicle_id' => $reservation->vehicle_id,
                'original_status' => Reservation::STATUS_PENDING,
                'new_status' => Reservation::STATUS_EXPIRED,
                'expired_at' => now()
            ]);
        } else {
            // Log if reservation was not found or already changed status
            if (!$reservation) {
                Log::warning("Reservation not found for expiration job", [
                    'reservation_id' => $this->reservationId
                ]);
            } else {
                Log::info("Reservation {$reservation->uid} was not expired because status changed", [
                    'reservation_id' => $reservation->id,
                    'current_status' => $reservation->status,
                    'checked_at' => now()
                ]);
            }
        }
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
