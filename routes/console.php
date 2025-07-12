<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جدولة أمر تشغيل سكريبت البايثون كل يوم الساعة 12 ظهراً
Schedule::command('run:scrap-rta')->dailyAt('12:00');
Schedule::command('app:update-salik-trips')->dailyAt('012:00');
