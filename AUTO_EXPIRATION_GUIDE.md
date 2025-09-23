# نظام انتهاء صلاحية الحجوزات التلقائي

## 📋 الوصف
تم إنشاء نظام تلقائي لانتهاء صلاحية الحجوزات التي تكون في حالة `pending` لأكثر من 5 دقائق.

**ملاحظة مهمة:** النظام يطبق فقط على الحجوزات من مصدر الويب (`web`). الحجوزات من الوكيل (`agent`) لا تنتهي صلاحيتها تلقائياً.

**ملاحظة:** الحجوزات من المتصفح (واجهة الويب) تُعتبر `agent` لأن الوكيل يستخدم المتصفح، بينما الحجوزات من API تُعتبر `web` لأنها آلية.

## ⚙️ كيف يعمل النظام؟

### 1. عند إنشاء حجز جديد:
- إذا كانت حالة الحجز `pending` **ومصدر الحجز `web` (API)**
- يتم تشغيل Job باسم `ExpireReservationJob` 
- الـ Job يتم جدولته للتشغيل بعد 5 دقائق بالضبط
- **الحجوزات من الوكيل (`agent` - المتصفح) لا يتم جدولة انتهاء صلاحيتها**

### 2. عند تشغيل الـ Job:
- يبحث عن الحجز بالـ ID المحدد
- يتحقق من أن الحجز لا يزال موجوداً
- يتحقق من أن حالة الحجز لا تزال `pending`
- **يتحقق من أن مصدر الحجز هو `web` (API)**
- إذا كان الحجز لا يزال `pending` ومصدره `web` → يغير الحالة إلى `expired`
- إذا تغيرت الحالة أو كان المصدر `agent` (المتصفح) → لا يفعل شيء ويسجل في الـ log

## 🔄 الحالات الممكنة

| الحالة | الوصف | اللون |
|--------|-------|-------|
| `pending` | معلق - في انتظار التأكيد (5 دقائق للAPI فقط) | أصفر |
| `confirmed` | مؤكد | أزرق |
| `completed` | مكتمل | أخضر |
| `canceled` | ملغي | أحمر |
| `expired` | منتهي الصلاحية تلقائياً (API فقط) | برتقالي |

## 📱 مصادر الحجوزات

| المصدر | الوصف | انتهاء الصلاحية |
|--------|-------|------------------|
| `web` | حجز من API (آلي) | 5 دقائق تلقائياً |
| `agent` | حجز من المتصفح (وكيل) | لا ينتهي تلقائياً |

## 🛠️ ملفات النظام

### 1. **ExpireReservationJob** (`app/Jobs/ExpireReservationJob.php`)
```php
// Job يعمل بعد 5 دقائق لتغيير الحالة
ExpireReservationJob::dispatch($reservation->id)->delay(now()->addMinutes(5));
```

### 2. **Reservation Model** (`app/Models/Reservation.php`)
```php
// عند إنشاء حجز جديد
static::created(function ($reservation) {
    if ($reservation->status === self::STATUS_PENDING) {
        ExpireReservationJob::dispatch($reservation->id)->delay(now()->addMinutes(5));
    }
});
```

### 3. **API Controller** (`app/Http/Controllers/Api/ReservationApiController.php`)
```php
// إضافة 'expired' للحالات المسموحة
'status' => 'required|in:pending,confirmed,completed,canceled,expired'
```

## 🧪 اختبار النظام

### إنشاء حجز تجريبي:
```bash
php artisan tinker
```

```php
$reservation = Reservation::create([
    'customer_id' => 'customer-uuid',
    'vehicle_id' => 'vehicle-uuid', 
    'team_id' => 'team-uuid',
    'pickup_date' => '2025-01-26 14:00:00',
    'pickup_location' => 'Airport',
    'return_date' => '2025-01-28 14:00:00',
    'rate' => 180.00,
    'status' => 'pending'
]);

// Job سيعمل تلقائياً بعد 5 دقائق
```

### تشغيل Queue Worker:
```bash
php artisan queue:work
```

### مراقبة Logs:
```bash
tail -f storage/logs/laravel.log
```

## 📊 مثال على سيناريوهات مختلفة

### السيناريو 1: الحجز يبقى pending
```
00:00 - إنشاء حجز (pending)
00:05 - Job يعمل ويجد الحجز pending
00:05 - تغيير الحالة إلى expired ✅
```

### السيناريو 2: العميل يؤكد الحجز
```
00:00 - إنشاء حجز (pending) 
00:02 - العميل يؤكد (confirmed)
00:05 - Job يعمل ويجد الحجز confirmed
00:05 - لا يفعل شيء ✅
```

### السيناريو 3: العميل يلغي الحجز
```
00:00 - إنشاء حجز (pending)
00:03 - العميل يلغي (canceled)
00:05 - Job يعمل ويجد الحجز canceled
00:05 - لا يفعل شيء ✅
```

## 🔍 مراقبة النظام

### Logs المسجلة:
- عند انتهاء صلاحية الحجز
- عند عدم العثور على الحجز
- عند تغيير حالة الحجز قبل انتهاء المهلة
- عند فشل الـ Job

### مثال على Log:
```
[2025-07-22 12:50:22] Reservation RES-7E2A36BA has been expired after 5 minutes
```

## 🎯 فوائد النظام

1. **تنظيف تلقائي**: إزالة الحجوزات المعلقة تلقائياً
2. **توفير المركبات**: تحرير المركبات للحجوزات الأخرى
3. **تحسين الأداء**: تقليل عدد الحجوزات المعلقة
4. **مراقبة**: تسجيل كامل لجميع العمليات
5. **مرونة**: النظام لا يؤثر على الحجوزات المؤكدة

## ⚡ متطلبات التشغيل

1. **Queue System**: يجب تشغيل `php artisan queue:work`
2. **Database**: جدول الحجوزات يجب أن يدعم حالة `expired`
3. **Logs**: مساحة كافية لحفظ الـ logs

## 🔧 التخصيص

### تغيير مدة الانتظار:
```php
// في Reservation.php
ExpireReservationJob::dispatch($reservation->id)->delay(now()->addMinutes(10)); // 10 دقائق بدلاً من 5
```

### إضافة إشعارات:
```php
// في ExpireReservationJob.php
// إرسال إشعار للعميل عند انتهاء الصلاحية
```

### تخصيص الشروط:
```php
// إضافة شروط أخرى قبل انتهاء الصلاحية
if ($reservation->status === self::STATUS_PENDING && $reservation->some_condition) {
    // انتهاء الصلاحية
}
``` 
