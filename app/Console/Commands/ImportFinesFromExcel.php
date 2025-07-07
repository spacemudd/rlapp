<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fine;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

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

        $rows = Excel::toArray([], $file)[0];
        $header = array_map('strtolower', $rows[0]);
        unset($rows[0]);

        $newCount = 0;
        foreach ($rows as $row) {
            $data = array_combine($header, $row);

            // تحقق إذا كانت المخالفة موجودة بالفعل حسب fine_number
            $uniqueKey = $data['fine number'] ?? '';
            if (!$uniqueKey) {
                continue; // تجاهل السطر إذا لم يوجد رقم المخالفة
            }
            $exists = Fine::where('fine_number', $uniqueKey)->exists();
            if ($exists) {
                continue; // لا تضفها مرة أخرى
            }

            // تحويل التاريخ إلى تنسيق MySQL
            $mysqlDateTime = null;
            if (!empty($data['date and time'])) {
                try {
                    $mysqlDateTime = Carbon::createFromFormat('d M Y, g:i a', $data['date and time'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $mysqlDateTime = null; // أو يمكنك تسجيل الخطأ
                }
            }

            // تنظيف قيمة amount من أي نصوص غير رقمية (مثل 'AED 600' تصبح 600)
            $amountValue = 0;
            if (!empty($data['amount'])) {
                if (preg_match('/([0-9,.]+)/', $data['amount'], $matches)) {
                    $amountValue = str_replace(',', '', $matches[1]);
                }
            }

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
        }

        if ($newCount > 0) {
            $this->info("تم إضافة $newCount مخالفة جديدة.");
        } else {
            $this->info("لا توجد مخالفات جديدة.");
        }
        return $newCount;
    }
}
