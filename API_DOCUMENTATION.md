# API Documentation

## Base URL
```
http://127.0.0.1:8001/api/v1
```

## Authentication
جميع الـ APIs (ما عدا login) تتطلب API key في الـ headers.

### Headers المطلوبة:
```
X-RLAPP-KEY: 28izx09iasdasd
X-TEAM-ID: 01978391-2b82-7226-bc6a-e8e49a90c7f8
Accept: application/json
Content-Type: application/json
```

**ملاحظة مهمة:** 
- `X-TEAM-ID` يجب أن يكون UUID صحيح لأحد الـ teams الموجودة في النظام
- يمكنك الحصول على team_id من جدول teams أو من بيانات المستخدم المسجل

---

## 🔐 Authentication API

### Login
**POST** `/login`

تسجيل الدخول والحصول على token.

#### Request Body:
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

#### Response:
```json
{
  "token": "your-token-here",
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com"
  }
}
```

---

## 📋 Reservations API

### 1. Get All Reservations
**GET** `/reservations`

جلب جميع الحجوزات مع إمكانيات الفلترة والـ pagination.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | فلترة حسب الحالة: `pending`, `confirmed`, `completed`, `canceled`, `expired` |
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
GET /reservations?status=confirmed&pickup_date_from=2025-01-01&per_page=20&page=1
```

### 2. Get Reservations by Status
**GET** `/reservations/status/{status}`

جلب الحجوزات حسب الحالة.

#### Valid Statuses:
- `pending` - معلق
- `confirmed` - مؤكد
- `completed` - مكتمل
- `canceled` - ملغي
- `expired` - منتهي الصلاحية

#### Example Request:
```bash
GET /reservations/status/pending
```

### 3. Get Specific Reservation
**GET** `/reservations/{id}`

جلب حجز محدد.

#### Example Request:
```bash
GET /reservations/019821eb-7801-73a4-87ae-204220a64cf7
```

### 4. Create New Reservation
**POST** `/reservations`

إنشاء حجز جديد.

#### Request Body:
```json
{
  "customer_id": "019821eb-7801-73a4-87ae-204220a64cf7",
  "vehicle_id": "01982195-f322-7149-ab35-1b392e7160bc",
  "pickup_date": "2025-07-27T10:00:00Z",
  "pickup_location": "مطار دبي الدولي",
  "return_date": "2025-07-30T18:00:00Z",
  "rate": 150.00,
  "status": "pending",
  "notes": "العميل يحتاج كرسي أطفال"
}
```

### 5. Update Reservation
**PUT** `/reservations/{id}`

تحديث حجز موجود.

#### Request Body:
```json
{
  "pickup_date": "2025-07-28T10:00:00Z",
  "pickup_location": "مطار أبوظبي الدولي",
  "return_date": "2025-08-01T18:00:00Z",
  "rate": 200.00,
  "status": "confirmed",
  "notes": "تم تأكيد الحجز"
}
```

### 6. Delete Reservation
**DELETE** `/reservations/{id}`

حذف حجز.

### 7. Update Reservation Status
**PATCH** `/reservations/{id}/status`

تحديث حالة الحجز فقط.

#### Request Body:
```json
{
  "status": "confirmed"
}
```

### 8. Get Statistics
**GET** `/reservations/statistics`

جلب إحصائيات الحجوزات.

### 9. Get Today's Reservations
**GET** `/reservations/today`

جلب حجوزات اليوم.

### 10. Get Tomorrow's Reservations
**GET** `/reservations/tomorrow`

جلب حجوزات الغد.

### 11. Search Reservations
**GET** `/reservations/search?query={search_term}`

البحث في الحجوزات.

### 12. Get Available Vehicles
**GET** `/reservations/available-vehicles`

جلب المركبات المتاحة لفترة محددة.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `pickup_date` | date | Yes | تاريخ الاستلام |
| `return_date` | date | Yes | تاريخ الإرجاع |

---

## 🚗 Vehicles API

### 1. Get All Vehicles
**GET** `/vehicles`

جلب جميع المركبات.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | فلترة حسب الحالة: `available`, `rented`, `maintenance`, `out_of_service` |
| `search` | string | No | البحث في الماركة، الموديل، رقم اللوحة |
| `per_page` | integer | No | عدد العناصر في الصفحة |
| `page` | integer | No | رقم الصفحة |

### 2. Get Specific Vehicle
**GET** `/vehicles/{id}`

جلب مركبة محددة.

### 3. Create New Vehicle
**POST** `/vehicles`

إنشاء مركبة جديدة.

#### Request Body:
```json
{
  "plate_number": "ABC-123",
  "make": "Toyota",
  "model": "Camry",
  "year": 2023,
  "color": "أبيض",
  "seats": 5,
  "doors": 4,
  "category": "سيدان",
  "price_daily": 150.00,
  "price_weekly": 900.00,
  "price_monthly": 3000.00,
  "location_id": "location-uuid",
  "status": "available",
  "ownership_status": "owned",
  "odometer": 50000,
  "chassis_number": "CH123456789",
  "license_expiry_date": "2026-12-31"
}
```

### 4. Update Vehicle
**PUT** `/vehicles/{id}`

تحديث مركبة موجودة.

### 5. Delete Vehicle
**DELETE** `/vehicles/{id}`

حذف مركبة.

### 6. Search Vehicles
**GET** `/vehicles/search?query={search_term}`

البحث في المركبات.

### 7. Get Available Vehicles
**GET** `/vehicles/available`

جلب المركبات المتاحة.

---

## 👥 Customers API

### 1. Get All Customers
**GET** `/customers`

جلب جميع العملاء.

#### Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | البحث في الاسم، البريد الإلكتروني، رقم الهاتف |
| `status` | string | No | فلترة حسب الحالة: `active`, `inactive` |
| `per_page` | integer | No | عدد العناصر في الصفحة |
| `page` | integer | No | رقم الصفحة |

### 2. Get Specific Customer
**GET** `/customers/{id}`

جلب عميل محدد.

### 3. Create New Customer
**POST** `/customers`

إنشاء عميل جديد.

#### Request Body:
```json
{
  "first_name": "أحمد",
  "last_name": "محمد",
  "email": "ahmed@example.com",
  "phone": "+971501234567",
  "date_of_birth": "1990-01-01",
  "drivers_license_number": "DL12345678",
  "drivers_license_expiry": "2026-12-31",
  "country": "United Arab Emirates",
  "nationality": "Emirati",
  "emergency_contact_name": "فاطمة محمد",
  "emergency_contact_phone": "+971501234568",
  "status": "active"
}
```

### 4. Update Customer
**PUT** `/customers/{id}`

تحديث عميل موجود.

### 5. Delete Customer
**DELETE** `/customers/{id}`

حذف عميل.

### 6. Search Customers
**GET** `/customers/search?query={search_term}`

البحث في العملاء.

### 7. Get Customer Reservations
**GET** `/customers/{id}/reservations`

جلب حجوزات عميل محدد.

---

## 📊 Response Format

### Success Response:
```json
{
  "success": true,
  "data": [
    // Array of items
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 45,
    "from": 1,
    "to": 15
  }
}
```

### Error Response:
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

---

## 🔧 Example Usage with cURL

### Get Pending Reservations:
```bash
curl -X GET "http://127.0.0.1:8001/api/v1/reservations/status/pending" \
  -H "X-RLAPP-KEY: 28izx09iasdasd" \
  -H "X-TEAM-ID: 1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

### Create New Reservation:
```bash
curl -X POST "http://127.0.0.1:8001/api/v1/reservations" \
  -H "X-RLAPP-KEY: 28izx09iasdasd" \
  -H "X-TEAM-ID: 1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "019821eb-7801-73a4-87ae-204220a64cf7",
    "vehicle_id": "01982195-f322-7149-ab35-1b392e7160bc",
    "pickup_date": "2025-07-27T10:00:00Z",
    "pickup_location": "مطار دبي الدولي",
    "return_date": "2025-07-30T18:00:00Z",
    "rate": 150.00,
    "status": "pending",
    "notes": "العميل يحتاج كرسي أطفال"
  }'
```

### Get All Vehicles:
```bash
curl -X GET "http://127.0.0.1:8001/api/v1/vehicles" \
  -H "X-RLAPP-KEY: 28izx09iasdasd" \
  -H "X-TEAM-ID: 1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

### Get All Customers:
```bash
curl -X GET "http://127.0.0.1:8001/api/v1/customers" \
  -H "X-RLAPP-KEY: 28izx09iasdasd" \
  -H "X-TEAM-ID: 1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

---

## 📝 Notes

1. جميع التواريخ يجب أن تكون بصيغة ISO 8601 (YYYY-MM-DDTHH:MM:SSZ)
2. جميع المبالغ المالية بصيغة decimal مع 2 منزلة عشرية
3. UID الحجز يتم توليده تلقائياً
4. يتم حساب المدة والمبلغ الإجمالي تلقائياً عند الإنشاء أو التحديث
5. جميع العمليات محصورة في نطاق الفريق المحدد في `X-TEAM-ID`
6. يتم التحقق من توفر المركبة تلقائياً عند الإنشاء أو التحديث 
