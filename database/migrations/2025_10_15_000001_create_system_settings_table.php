<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed default fee types
        DB::table('system_settings')->insert([
            'key' => 'fee_types',
            'value' => json_encode([
                [
                    'key' => 'abu_dhabi_toll_gates',
                    'en' => 'Abu Dhabi Toll Gates',
                    'ar' => 'بوابات ابوظبي',
                ],
                [
                    'key' => 'car_wash',
                    'en' => 'Car Wash',
                    'ar' => 'غسيل سيارات',
                ],
                [
                    'key' => 'collection',
                    'en' => 'Collection',
                    'ar' => 'تحصيل',
                ],
                [
                    'key' => 'damages',
                    'en' => 'Damages',
                    'ar' => 'اضرار',
                ],
                [
                    'key' => 'delivery',
                    'en' => 'Delivery',
                    'ar' => 'توصيل',
                ],
                [
                    'key' => 'insurance_deductible',
                    'en' => 'Insurance Deductible',
                    'ar' => 'بدل تأمين',
                ],
                [
                    'key' => 'kilometers',
                    'en' => 'Kilometers',
                    'ar' => 'كيلومترات',
                ],
                [
                    'key' => 'extra_hours',
                    'en' => 'Extra Hours',
                    'ar' => 'ساعات اضافية',
                ],
                [
                    'key' => 'fuel',
                    'en' => 'Fuel',
                    'ar' => 'بترول',
                ],
                [
                    'key' => 'rental_fees',
                    'en' => 'Rental Fees',
                    'ar' => 'رسوم ايجار',
                ],
                [
                    'key' => 'salik_fees',
                    'en' => 'Salik Fees',
                    'ar' => 'رسوم سالك',
                ],
                [
                    'key' => 'salik_parking',
                    'en' => 'Salik Parking',
                    'ar' => 'مواقف سالك',
                ],
                [
                    'key' => 'violations',
                    'en' => 'Violations',
                    'ar' => 'مخالفات',
                ],
                [
                    'key' => 'black_points',
                    'en' => 'Black Points',
                    'ar' => 'نقاط سوداء',
                ],
                [
                    'key' => 'vehicle_reservation',
                    'en' => 'Vehicle Reservation',
                    'ar' => 'حجز مركبة',
                ],
            ]),
            'description' => 'System-wide fee types for additional contract charges',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

