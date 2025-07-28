# دليل اختبار API على السيرفر 🚀

## 📋 الخطوات السريعة لاختبار الـ API

### 1. تحميل Postman Collection
قم بتحميل ملف `RLAPP_API_Collection.postman_collection.json` إلى Postman.

### 2. إعداد المتغيرات
في Postman، اذهب إلى **Variables** وقم بتحديث:
- `token`: رمز المصادقة الخاص بك
- `reservation_id`: معرف الحجز المراد اختباره
- `customer_id`: معرف العميل
- `vehicle_id`: معرف المركبة

### 3. تسجيل الدخول أولاً
```bash
POST https://rlapp.rentluxuria.com/api/v1/login
Content-Type: application/json

{
  "email": "your-email@example.com",
  "password": "your-password"
}
```

### 4. اختبار الـ APIs الرئيسية

#### 🔍 جلب الحجوزات المعلقة
```bash
GET https://rlapp.rentluxuria.com/api/v1/reservations/pending
Authorization: Bearer your-token
```

#### 🔄 تغيير حالة الحجز (مفصل)
```bash
PATCH https://rlapp.rentluxuria.com/api/v1/reservations/{id}/change-status
Authorization: Bearer your-token
Content-Type: application/json

{
  "status": "confirmed"
}
```

#### 🔄 تغيير حالة الحجز (بسيط)
```bash
PATCH https://rlapp.rentluxuria.com/api/v1/reservations/{id}/status
Authorization: Bearer your-token
Content-Type: application/json

{
  "status": "completed"
}
```

## 🎯 أمثلة عملية

### مثال 1: جلب جميع الحجوزات المعلقة
```bash
curl -X GET "https://rlapp.rentluxuria.com/api/v1/reservations/pending" \
  -H "Authorization: Bearer your-token" \
  -H "Accept: application/json"
```

### مثال 2: تغيير حالة الحجز إلى مؤكد
```bash
curl -X PATCH "https://rlapp.rentluxuria.com/api/v1/reservations/YOUR-RESERVATION-ID/change-status" \
  -H "Authorization: Bearer your-token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "confirmed"
  }'
```

### مثال 3: إلغاء الحجز
```bash
curl -X PATCH "https://rlapp.rentluxuria.com/api/v1/reservations/YOUR-RESERVATION-ID/change-status" \
  -H "Authorization: Bearer your-token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "canceled"
  }'
```

## 📊 الحالات المتاحة

| الحالة | الوصف | اللون |
|--------|-------|-------|
| `pending` | معلق | أصفر |
| `confirmed` | مؤكد | أزرق |
| `completed` | مكتمل | أخضر |
| `canceled` | ملغي | أحمر |
| `expired` | منتهي الصلاحية | برتقالي |

## 🔐 Authentication

جميع الـ APIs تتطلب authentication. يجب تضمين token في header:
```
Authorization: Bearer your-token
```

## 📱 اختبار سريع مع JavaScript

```javascript
// دالة لتغيير حالة الحجز
const changeReservationStatus = async (reservationId, newStatus) => {
  try {
    const response = await fetch(`https://rlapp.rentluxuria.com/api/v1/reservations/${reservationId}/change-status`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        status: newStatus
      })
    });

    const data = await response.json();
    console.log('تم تغيير الحالة:', data);
    return data;
  } catch (error) {
    console.error('خطأ:', error);
  }
};

// استخدام الدالة
changeReservationStatus('reservation-id', 'confirmed');
```

## 🚨 الأخطاء الشائعة وحلولها

### خطأ 401 - Unauthorized
**السبب**: token غير صحيح أو منتهي الصلاحية
**الحل**: قم بتسجيل الدخول مرة أخرى للحصول على token جديد

### خطأ 404 - Not Found
**السبب**: معرف الحجز غير موجود
**الحل**: تأكد من صحة معرف الحجز

### خطأ 422 - Validation Error
**السبب**: الحالة المحددة غير صحيحة
**الحل**: استخدم إحدى الحالات المتاحة: `pending`, `confirmed`, `completed`, `canceled`, `expired`

## 📞 الدعم

إذا واجهت أي مشاكل، تأكد من:
1. صحة الـ URL: `https://rlapp.rentluxuria.com`
2. صحة token المصادقة
3. صحة معرفات الحجوزات والعملاء والمركبات
4. صحة الحالات المستخدمة

## 🎉 تم إنشاء الـ APIs بنجاح!

الآن يمكنك اختبار جميع الـ APIs على السيرفر باستخدام Postman أو أي أداة أخرى! 
