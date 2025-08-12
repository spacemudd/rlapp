<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جدولة أمر تشغيل سكريبت البايثون كل يوم الساعة 12 ظهراً
Schedule::command('run:scrap-rta')->dailyAt('12:00');
Schedule::command('app:update-salik-trips')->dailyAt('012:00');

// جدولة تشغيل سكريبت المخالفات كل 10 دقائق
Schedule::command('fines:run-script')->everyTenMinutes();

// جدولة: إنهاء الحجوزات المعلقة التي مرّ عليها أكثر من 5 دقائق تلقائياً كل دقيقة
Artisan::command('reservations:expire-pending', function () {
    $expiredCount = 0;

    Reservation::where('status', Reservation::STATUS_PENDING)
        ->where('updated_at', '<=', now()->subMinutes(5))
        ->orderBy('id')
        ->chunkById(200, function ($reservations) use (&$expiredCount) {
            foreach ($reservations as $reservation) {
                $reservation->update(['status' => Reservation::STATUS_EXPIRED]);
                $expiredCount++;

                Log::info('Auto-expired reservation via scheduler', [
                    'reservation_id' => $reservation->id,
                    'uid' => $reservation->uid,
                    'expired_at' => now(),
                ]);
            }
        });

    $this->info("Expired {$expiredCount} pending reservations.");
})->purpose('Expire pending reservations older than 5 minutes');

// تشغيل الأمر كل دقيقة تلقائياً
Schedule::command('reservations:expire-pending')->everyMinute();
