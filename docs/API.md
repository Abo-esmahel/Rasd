# API.md

## المصادقة

### تسجيل الدخول
```
POST /api/login
Content-Type: application/json

{
    "username": "monitor1",
    "password": "password"
}

Response 200:
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح",
    "data": {
        "token": "eyJhbGciOi...",
        "user": {
            "id": 1,
            "name": "مُراقب ١",
            "username": "monitor1",
            "role": "monitor"
        }
    }
}
```

Rate limit: 5 محاولات لكل دقيقة.

### تسجيل الخروج
```
POST /api/logout
Authorization: Bearer <token>

Response 200:
{
    "success": true,
    "message": "تم تسجيل الخروج بنجاح"
}
```

### بيانات المستخدم
```
GET /api/me
Authorization: Bearer <token>

Response 200:
{
    "success": true,
    "data": {
        "id": 1,
        "name": "مُراقب ١",
        "username": "monitor1",
        "role": "monitor"
    }
}
```

## الملاحظات

### عرض الملاحظات
```
GET /api/notes
Authorization: Bearer <token>

Filters (اختياري):
- status: draft|pending|accepted|rejected
- date: YYYY-MM-DD
- floor_number: integer
- camera_number: integer

Response 200:
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [...],
        "total": 50
    }
}
```

### إنشاء ملاحظة (monitor فقط)
```
POST /api/notes
Authorization: Bearer <token> (monitor فقط)
Content-Type: application/json

{
    "floor_number": 1,
    "camera_number": 10,
    "observed_at": "2026-09-02 14:30:00",
    "description": "ملاحظة التجريبية"
}

Response 201:
{
    "success": true,
    "message": "تم إنشاء المسودة بنجاح",
    "data": { ... }
}

Response 403 (report_writer):
{
    "success": false,
    "message": "غير مصرح لك بإنشاء الملاحظات"
}
```
ملاحظة: `user_id`, `status`, `processed_by`, `sent_at` وغيرها تُحدد تلقائياً من الخادم ويُرفض أي محاولة لتغييرها عبر الطلب (mass assignment محمي).

### عرض ملاحظة
```
GET /api/notes/{note}
Authorization: Bearer <token>

Response 200:
{
    "success": true,
    "data": { ... }
}
```

### تحديث ملاحظة
```
PUT /api/notes/{note}
Authorization: Bearer <token>
Content-Type: application/json

{
    "floor_number": 2,
    "description": "وصف محدث"
}

Response 200:
{
    "success": true,
    "message": "تم تحديث الملاحظة بنجاح",
    "data": { ... }
}
```

### حذف ملاحظة
```
DELETE /api/notes/{note}
Authorization: Bearer <token>

Response 204
```

## تدفق العمل

### إرسال
```
POST /api/notes/{note}/send
Authorization: Bearer <token>

Response 200:
{
    "success": true,
    "message": "تم إرسال الملاحظة بنجاح",
    "data": { ... }
}
```

### قبول
```
POST /api/notes/{note}/accept
Authorization: Bearer <token> (report_writer فقط)

Response 200:
{
    "success": true,
    "message": "تم قبول الملاحظة بنجاح",
    "data": { ... }
}
```

### رفض
```
POST /api/notes/{note}/reject
Authorization: Bearer <token> (report_writer فقط)
Content-Type: application/json

{
    "rejection_reason": "المعلومات غير كافية"
}

Response 200:
{
    "success": true,
    "message": "تم رفض الملاحظة بنجاح",
    "data": { ... }
}
```

### إعادة الإرسال
```
POST /api/notes/{note}/resend
Authorization: Bearer <token> (مالك الملاحظة فقط)

Response 200:
{
    "success": true,
    "message": "تم إعادة إرسال الملاحظة بنجاح",
    "data": { ... }
}
```

## المرفقات

### إضافة مرفق
```
POST /api/notes/{note}/attachments
Authorization: Bearer <token>
Content-Type: multipart/form-data

file: [binary]

Response 201:
{
    "success": true,
    "message": "تم إضافة المرفق بنجاح",
    "data": { ... }
}
```

### حذف مرفق
```
DELETE /api/notes/{note}/attachments/{attachment}
Authorization: Bearer <token>

Response 204
```

### تحميل مرفق
```
GET /api/attachments/{attachment}
Authorization: Bearer <token>

Response: [file download]
```

## صيغة الاستجابة

### نجاح
```json
{
    "success": true,
    "message": "تم تنفيذ العملية بنجاح",
    "data": {}
}
```

### خطأ
```json
{
    "success": false,
    "message": "حدث خطأ",
    "errors": {}
}
```

## حالات HTTP
- 200: نجاح
- 201: إنشاء
- 204: حذف (بدون محتوى)
- 400: خطأ في الطلب
- 401: غير مصرح (صادقة مفقودة)
- 403: ممنوع (تفويض)
- 404: غير موجود
- 422: خطأ في التحقق
- 429: تجاوز حد الطلبات
