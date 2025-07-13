<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fine;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ImportFinesFromExcel extends Command
{
    protected $signature = 'import:fines {file?}';
    protected $description = 'Import fines from Clean.xlsx into the fines table';

    public function handle()
    {
        $file = $this->argument('file') ?? base_path('scripts/Clean.xlsx');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        // إضافة معلومات تشخيصية
        $this->info("Starting import process...");
        $this->info("File path: $file");
        $this->info("Database connection: " . config('database.default'));
        $this->info("Database host: " . config('database.connections.mysql.host'));
        $this->info("Database name: " . config('database.connections.mysql.database'));

        try {
            // اختبار الاتصال بقاعدة البيانات
            DB::connection()->getPdo();
            $this->info("Database connection successful!");
        } catch (\Exception $e) {
            $this->error("Database connection failed: " . $e->getMessage());
            return 1;
        }

        $rows = Excel::toArray([], $file)[0];
        $header = array_map('strtolower', $rows[0]);
        unset($rows[0]);

        $this->info("Found " . count($rows) . " rows to process");

                $newCount = 0;
        $errorCount = 0;

        foreach ($rows as $index => $row) {
            try {
                $data = array_combine($header, $row);

                                // تحقق إذا كانت المخالفة موجودة بالفعل حسب fine_number
                $uniqueKey = $data['fine number'] ?? '';
                if (!$uniqueKey) {
                    $this->warn("Row " . ($index + 1) . ": Skipping - no fine number");
                    continue; // تجاهل السطر إذا لم يوجد رقم المخالفة
                }

                                                // في الوضع الافتراضي، نضيف جميع البيانات بدون تحقق من الوجود
                // فقط إذا كان هناك fine_number
                if ($uniqueKey) {
                    // نحذف أي مخالفة موجودة بنفس الرقم ونضيف الجديدة
                    Fine::where('fine_number', $uniqueKey)->delete();
                    $this->info("Row " . ($index + 1) . ": Replaced existing fine $uniqueKey");
                }

                // تحويل التاريخ إلى تنسيق MySQL
                $mysqlDateTime = $this->parseDateTime($data['date and time'] ?? '');

                // تنظيف قيمة amount من أي نصوص غير رقمية (مثل 'AED 600' تصبح 600)
                $amountValue = $this->parseAmount($data['amount'] ?? '');

                // تحويل الأعمدة حسب جدولك
                Fine::create([
                    'car_name'      => $data['car name'] ?? '',
                    'plate_code'    => $data['plate code'] ?? '',
                    'plate_number'  => $data['plate number'] ?? '',
                    'dateandtime'   => $mysqlDateTime,
                    'location'      => $data['location'] ?? '',
                    'source'        => $data['source'] ?? '',
                    'amount'        => $amountValue,
                    'fine_number'   => $data['fine number'] ?? '',
                    'details'       => $data['details'] ?? '',
                    'dispute'       => !empty($data['dispute']) && strtolower($data['dispute']) == 'yes',
                    'created_at'    => Carbon::now(),
                    'updated_at'    => Carbon::now(),
                ]);
                $newCount++;
                $this->info("Row " . ($index + 1) . ": Successfully added fine number $uniqueKey");

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Row " . ($index + 1) . ": Error processing row - " . $e->getMessage());
                continue;
            }
        }

                        $this->info("Import completed. Added: $newCount, Errors: $errorCount");

        if ($newCount > 0) {
            $this->info("تم إضافة $newCount مخالفة إلى قاعدة البيانات.");
        } else {
            $this->info("لا توجد مخالفات تمت إضافتها.");
        }
        return $newCount;
    }

    /**
     * Parse date and time string to MySQL format
     */
    private function parseDateTime($dateTimeString)
    {
        if (empty($dateTimeString)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d M Y, g:i a', $dateTimeString)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->warn("Date parsing failed for '$dateTimeString': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse amount string to numeric value
     */
    private function parseAmount($amountString)
    {
        if (empty($amountString)) {
            return 0;
        }

        if (preg_match('/([0-9,.]+)/', $amountString, $matches)) {
            return str_replace(',', '', $matches[1]);
        }

        return 0;
    }

    /**
     * Update existing fine data
     */
    private function updateFineData($fine, $data, $mysqlDateTime, $amountValue)
    {
        $fine->update([
            'car_name'      => $data['car name'] ?? $fine->car_name,
            'plate_code'    => $data['plate code'] ?? $fine->plate_code,
            'plate_number'  => $data['plate number'] ?? $fine->plate_number,
            'dateandtime'   => $mysqlDateTime ?? $fine->dateandtime,
            'location'      => $data['location'] ?? $fine->location,
            'source'        => $data['source'] ?? $fine->source,
            'amount'        => $amountValue ?: $fine->amount,
            'details'       => $data['details'] ?? $fine->details,
            'dispute'       => !empty($data['dispute']) && strtolower($data['dispute']) == 'yes',
            'updated_at'    => Carbon::now(),
        ]);
    }
}
