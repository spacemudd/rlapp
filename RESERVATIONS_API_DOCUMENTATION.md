# Reservations API Documentation

## Base URL
```
http://your-domain.com/api/reservations
```

## Authentication
جميع endpoints تتطلب authentication. يجب تضمين authentication token في request headers.

## Endpoints

### 1. Get All Reservations
**GET** `/api/reservations`

جلب جميع الحجوزات مع إمكانيات الفلترة والـ pagination.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | فلترة حسب الحالة: `pending`, `confirmed`, `completed`, `canceled` |
| `customer_id` | string | No | فلترة حسب العميل |
| `vehicle_id` | string | No | فلترة حسب المركبة |
| `pickup_date_from` | date | No | تاريخ البداية للاستلام (YYYY-MM-DD) |
| `pickup_date_to` | date | No | تاريخ النهاية للاستلام (YYYY-MM-DD) |
| `return_date_from` | date | No | تاريخ البداية للإرجاع (YYYY-MM-DD) |
| `return_date_to` | date | No | تاريخ النهاية للإرجاع (YYYY-MM-DD) |
| `search` | string | No | البحث في UID، موقع الاستلام، ملاحظات، اسم العميل، أو بيانات المركبة |
| `sort_by` | string | No | ترتيب حسب: `pickup_date`, `return_date`, `reservation_date`, `status`, `rate`, `total_amount`, `uid` |
| `sort_order` | string | No | اتجاه الترتيب: `asc`, `desc` (افتراضي: `desc`) |
| `per_page` | integer | No | عدد العناصر في الصفحة (1-100، افتراضي: 15) |
| `page` | integer | No | رقم الصفحة |

#### Example Request:
```bash
GET /api/reservations?status=confirmed&pickup_date_from=2025-01-01&per_page=20&page=1
```

#### Example Response:
```json
{
  "success": true,
  "data": [
    {
      "id": "9d234567-e89b-12d3-a456-426614174000",
      "uid": "RSV-2025-001",
      "customer_id": "9d234567-e89b-12d3-a456-426614174001",
      "vehicle_id": "9d234567-e89b-12d3-a456-426614174002",
      "rate": "150.00",
      "pickup_date": "2025-01-15T10:00:00.000000Z",
      "pickup_location": "Dubai International Airport",
      "return_date": "2025-01-20T10:00:00.000000Z",
      "status": "confirmed",
      "reservation_date": "2025-01-10T14:30:00.000000Z",
      "notes": "Customer needs baby seat",
      "total_amount": "750.00",
      "duration_days": 5,
      "team_id": "9d234567-e89b-12d3-a456-426614174003",
      "created_at": "2025-01-10T14:30:00.000000Z",
      "updated_at": "2025-01-10T14:30:00.000000Z",
      "customer": {
        "id": "9d234567-e89b-12d3-a456-426614174001",
        "name": "Ahmed Mohamed",
        "email": "ahmed@example.com",
        "phone": "+971501234567"
      },
      "vehicle": {
        "id": "9d234567-e89b-12d3-a456-426614174002",
        "name": "Toyota Camry 2024",
        "plate_number": "A-12345",
        "model": "Camry",
        "daily_rate": "150.00"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100,
    "from": 1,
    "to": 20
  },
  "filters": {
    "status": "confirmed",
    "pickup_date_from": "2025-01-01",
    "sort_by": "pickup_date",
    "sort_order": "desc"
  }
}
```

---

### 2. Get Single Reservation
**GET** `/api/reservations/{id}`

جلب حجز واحد بالتفاصيل الكاملة.

#### Example Request:
```bash
GET /api/reservations/9d234567-e89b-12d3-a456-426614174000
```

#### Example Response:
```json
{
  "success": true,
  "data": {
    "id": "9d234567-e89b-12d3-a456-426614174000",
    "uid": "RSV-2025-001",
    "customer": { /* بيانات العميل كاملة */ },
    "vehicle": { /* بيانات المركبة كاملة */ },
    "team": { /* بيانات الفريق */ },
    /* باقي بيانات الحجز */
  }
}
```

---

### 3. Create New Reservation
**POST** `/api/reservations`

إنشاء حجز جديد.

#### Request Body:
```json
{
  "customer_id": "9d234567-e89b-12d3-a456-426614174001",
  "vehicle_id": "9d234567-e89b-12d3-a456-426614174002",
  "pickup_date": "2025-01-15T10:00:00Z",
  "pickup_location": "Dubai International Airport",
  "return_date": "2025-01-20T10:00:00Z",
  "rate": 150.00,
  "status": "pending",
  "notes": "Customer needs baby seat"
}
```

#### Validation Rules:
- `customer_id`: مطلوب، يجب أن يكون موجود في جدول العملاء
- `vehicle_id`: مطلوب، يجب أن يكون موجود في جدول المركبات
- `pickup_date`: مطلوب، يجب أن يكون تاريخ مستقبلي
- `pickup_location`: مطلوب، نص أقصى 255 حرف
- `return_date`: مطلوب، يجب أن يكون بعد تاريخ الاستلام
- `rate`: مطلوب، رقم أكبر من أو يساوي 0
- `status`: مطلوب، واحد من: `pending`, `confirmed`, `completed`, `canceled`, `expired`
- `notes`: اختياري، نص

#### Example Response:
```json
{
  "success": true,
  "message": "Reservation created successfully",
  "data": {
    /* بيانات الحجز الجديد */
  }
}
```

#### Error Response (Vehicle Not Available):
```json
{
  "success": false,
  "message": "Vehicle is not available for the selected period"
}
```

---

### 4. Update Reservation
**PUT** `/api/reservations/{id}`

تحديث حجز موجود.

#### Request Body:
```json
{
  "pickup_date": "2025-01-16T10:00:00Z",
  "return_date": "2025-01-21T10:00:00Z",
  "rate": 160.00,
  "status": "confirmed",
  "notes": "Updated notes"
}
```

#### Example Response:
```json
{
  "success": true,
  "message": "Reservation updated successfully",
  "data": {
    /* بيانات الحجز المحدثة */
  }
}
```

---

### 5. Delete Reservation
**DELETE** `/api/reservations/{id}`

حذف حجز.

#### Example Response:
```json
{
  "success": true,
  "message": "Reservation deleted successfully"
}
```

---

### 6. Update Reservation Status
**PATCH** `/api/reservations/{id}/status`

تحديث حالة الحجز فقط.

#### Request Body:
```json
{
  "status": "confirmed"
}
```

#### Example Response:
```json
{
  "success": true,
  "message": "Reservation status updated successfully",
  "data": {
    /* بيانات الحجز المحدثة */
  }
}
```

---

### 7. Get Reservations by Status
**GET** `/api/reservations/status/{status}`

جلب الحجوزات حسب الحالة.

#### Valid Statuses:
- `pending`
- `confirmed`
- `completed`
- `canceled`
- `expired`

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | البحث في بيانات الحجز |
| `per_page` | integer | No | عدد العناصر في الصفحة |

#### Example Request:
```bash
GET /api/reservations/status/confirmed?search=ahmed&per_page=10
```

#### Example Response:
```json
{
  "success": true,
  "data": [
    /* مصفوفة الحجوزات */
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  },
  "status": "confirmed"
}
```

---

### 8. Get Today's Reservations
**GET** `/api/reservations/today`

جلب حجوزات اليوم.

#### Example Response:
```json
{
  "success": true,
  "data": [
    /* حجوزات اليوم */
  ],
  "count": 5
}
```

---

### 9. Get Tomorrow's Reservations
**GET** `/api/reservations/tomorrow`

جلب حجوزات الغد.

#### Example Response:
```json
{
  "success": true,
  "data": [
    /* حجوزات الغد */
  ],
  "count": 3
}
```

---

### 10. Get Reservations Statistics
**GET** `/api/reservations/statistics`

جلب إحصائيات الحجوزات والإيرادات.

#### Example Response:
```json
{
  "success": true,
  "data": {
    "reservations": {
      "total": 150,
      "today": 5,
      "tomorrow": 3,
      "pending": 12,
      "confirmed": 25,
      "completed": 98,
      "canceled": 15
    },
    "revenue": {
      "total_revenue": "45000.00",
      "monthly_revenue": "12000.00",
      "weekly_revenue": "3500.00"
    }
  }
}
```

---

### 11. Get Available Vehicles
**GET** `/api/reservations/available-vehicles`

جلب المركبات المتاحة لفترة محددة.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `pickup_date` | datetime | Yes | تاريخ ووقت الاستلام |
| `return_date` | datetime | Yes | تاريخ ووقت الإرجاع |

#### Example Request:
```bash
GET /api/reservations/available-vehicles?pickup_date=2025-01-15T10:00:00Z&return_date=2025-01-20T10:00:00Z
```

#### Example Response:
```json
{
  "success": true,
  "data": [
    {
      "id": "9d234567-e89b-12d3-a456-426614174002",
      "name": "Toyota Camry 2024",
      "plate_number": "A-12345",
      "model": "Camry",
      "daily_rate": "150.00",
      "status": "available",
      "location": {
        "id": "9d234567-e89b-12d3-a456-426614174004",
        "name": "Main Branch"
      }
    }
  ],
  "period": {
    "pickup_date": "2025-01-15T10:00:00Z",
    "return_date": "2025-01-20T10:00:00Z"
  }
}
```

---

### 12. Search Reservations
**GET** `/api/reservations/search`

البحث في الحجوزات.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | Yes | النص المراد البحث عنه (أقل حد 2 حرف) |

#### Example Request:
```bash
GET /api/reservations/search?query=ahmed
```

#### Example Response:
```json
{
  "success": true,
  "data": [
    /* نتائج البحث */
  ],
  "query": "ahmed",
  "count": 8
}
```

---

## Status Codes

| Code | Description |
|------|-------------|
| 200 | نجح الطلب |
| 201 | تم إنشاء المورد بنجاح |
| 400 | خطأ في البيانات المرسلة |
| 401 | غير مصرح بالدخول |
| 404 | المورد غير موجود |
| 409 | تعارض (مثل عدم توفر المركبة) |
| 422 | خطأ في التحقق من البيانات |
| 500 | خطأ داخلي في الخادم |

## Reservation Statuses

| Status | Description |
|--------|-------------|
| `pending` | معلق - الحجز في انتظار التأكيد (ينتهي تلقائياً بعد 5 دقائق) |
| `confirmed` | مؤكد - تم تأكيد الحجز |
| `completed` | مكتمل - تم إنهاء الحجز |
| `canceled` | ملغي - تم إلغاء الحجز |
| `expired` | منتهي الصلاحية - انتهت صلاحية الحجز تلقائياً بعد 5 دقائق |

## Error Response Format

```json
{
  "success": false,
  "message": "وصف الخطأ",
  "errors": {
    "field_name": [
      "رسالة الخطأ"
    ]
  }
}
```

## Example Usage with cURL

### Get All Reservations
```bash
curl -X GET "http://your-domain.com/api/reservations" \
  -H "Authorization: Bearer your-token" \
  -H "Accept: application/json"
```

### Create New Reservation
```bash
curl -X POST "http://your-domain.com/api/reservations" \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "customer_id": "9d234567-e89b-12d3-a456-426614174001",
    "vehicle_id": "9d234567-e89b-12d3-a456-426614174002",
    "pickup_date": "2025-01-15T10:00:00Z",
    "pickup_location": "Dubai International Airport",
    "return_date": "2025-01-20T10:00:00Z",
    "rate": 150.00,
    "status": "pending"
  }'
```

### Update Reservation Status
```bash
curl -X PATCH "http://your-domain.com/api/reservations/{id}/status" \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "confirmed"
  }'
```

## Notes

1. جميع التواريخ يجب أن تكون بصيغة ISO 8601 (YYYY-MM-DDTHH:MM:SSZ)
2. جميع المبالغ المالية بصيغة decimal مع 2 منزلة عشرية
3. UID الحجز يتم توليده تلقائياً بصيغة RSV-YYYY-XXX
4. يتم حساب المدة والمبلغ الإجمالي تلقائياً عند الإنشاء أو التحديث
5. جميع العمليات محصورة في نطاق الفريق الخاص بالمستخدم المصادق عليه
6. يتم التحقق من توفر المركبة تلقائياً عند الإنشاء أو التحديث 
 