# API.md — REST المحقق (Verified 2026-09-13)

> **المصدر:** `routes/api.php:1` (27 مسار) + `routes/web.php:1` (67 مسار) + `app/Http/Controllers/Api/*` — كل مسار هنا له handler فعلي.

## المصادقة — `POST /api/login` `throttle:5,1`

### تسجيل الدخول
```
POST /api/login
Content-Type: application/json

{
    "username": "tariq",          // من RasdSmartSeed: tariq/hadi/hamza/rami/writer (password)
    "password": "password"
}

Response 200:
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح",
    "data": {
        "token": "eyJhbGciOi...",  // HMAC-SHA256 — config/jwt.php: secret + expiry 60m
        "user": { "id": 1, "name": "طارق", "username": "tariq", "role": "monitor" }
    }
}

Response 401: { "success": false, "message": "بيانات الدخول غير صحيحة" }
Response 429: { "success": false, "message": "حاول مرة أخرى بعد دقيقة" }
```

*   `JWT_SECRET` إلزامي ≥32 حرفاً وإلا يرفض الإقلاع.
*   التوكن يُحفظ `blacklist via Cache` عند `POST /api/logout`.

### تسجيل الخروج
```
POST /api/logout
Authorization: Bearer <token>
→ 200 { "success": true, "message": "تم تسجيل الخروج بنجاح" }
```

### بيانات المستخدم
```
GET /api/me
Authorization: Bearer <token>
→ 200 { "success": true, "data": { "id": 1, "name": "...", "username": "...", "role": "monitor|report_writer", "phone": "...", "avatar_path": "..." } }
```

## الملاحظات — `auth.api` group

### عرض الملاحظات (مرئية فقط)
```
GET /api/notes?status=draft|pending|accepted|rejected&date=YYYY-MM-DD&floor_number=&camera_number=
Authorization: Bearer <token>

Response 200: { "success": true, "data": { "current_page": 1, "data": [...], "total": 50 } }
```
*   المسودات لا تُرجع إلا لمالكها (`getVisibleNotesQuery`: `whereNull(general_submission_id)->where(user_id=me OR status!=draft)`).
*   كل `data[]` يتضمن `owner, processor, attachments, attachments_count` + `description` الأصلي (الترجمة للويب فقط عبر `LocalizedPresenter`).

### إنشاء ملاحظة — `NoteService::normalizeObservedRange` + `createNoteWithAttachments`
```
POST /api/notes
Authorization: Bearer <token>
Content-Type: application/json | multipart/form-data (files[])

{
    "floor_number": 2,
    "camera_number": 7,
    "observed_at": "2026-09-13 08:00:00",   // naive → يُفسر ك Asia/Damascus (+03) إن كان APP_TIMEZONE=UTC
    "observed_end_at": "2026-09-13 08:15:00", // اختياري — end<start → +1 day (منتصف الليل)
    "description": "وصف لا يقل عن 10 أحرف...",
    "files": [binary, ...]                 // مرفقات اختيارية — حد 10 (config attachments.max_per_note)
}

Response 201: { "success": true, "message": "تم إنشاء المسودة بنجاح", "data": { ... } }
Response 403 (غير مصرح): { "success": false, "message": "غير مصرح لك بإنشاء الملاحظات" } — لا يحدث: الكاتب أيضاً ينشئ (يُقبل تلقائياً)
Response 422: { "success": false, "message": "الوصف مطلوب / الوقت غير صالح / المدة تتجاوز 12 ساعة / تاريخ مستقبلي …" }
```
*   `user_id/status/processed_by/sent_at` تُحدد تلقائياً من الخادم — `array_intersect_key` يرفض أي محاولة `mass assignment`.
*   المرفقات: `UUID + finfo + mimes(ac3,dts,alac…) + أحجام 20M/100M` — تفشل ذرياً (`DB::rollBack + cleanup`) مع `attachmentErrors`.

### عرض/تحديث/حذف
```
GET    /api/notes/{note}          → 200 { data: {...} } | 403 (مسودة غيرك) | 404
PUT    /api/notes/{note}          → {floor_number?, camera_number?, observed_at?, description?} — مالك فقط + !isRejected + (!isAccepted || processed_by==me) + normalizeObservedRange
DELETE /api/notes/{note}          → 204 (مالك + isDraft فقط) | 403 | 404
```

### تدفق العمل — نفس NoteService للويب والـ API

```
POST /api/notes/{note}/send    → 200 { data: {...} } — مالك + isDraft → pending (أو accepted إن كان كاتباً) + FanoutNoteNotifications afterResponse
POST /api/notes/{note}/accept  → 200 — report_writer + isPending → accepted + NoteAcceptedNotification للمالك (hasNotification deduplication)
POST /api/notes/{note}/reject  → 200 { rejection_reason: "المعلومات غير كافية" } — report_writer + isPending → rejected + NoteRejectedNotification
POST /api/notes/{note}/resend  → 200 — مالك + isRejected → pending + FanoutNoteNotifications(true)
```

### المرفقات — `AttachmentStorageService` + `finfo`

```
POST   /api/notes/{note}/attachments              file: binary (mimes: jpg…ac3,dts,alac, max: uploadFileMaxKb) → 201 { data: attachment } | 422 (نوع/حجم/خطر)
DELETE /api/notes/{note}/attachments/{attachment}  → 204 (مالك + !isRejected + (!isAccepted || processed_by==me))
GET    /api/attachments/{attachment}               → file download (auth.api — يتبع visibility + isReportWriter للتنزيل المباشر)
GET    /api/attachments/{attachment}/view          → inline view (auth.api)
GET    /api/submission-attachments/{attachment}/view|download → للإرسالات (نفس التفويض)
```

## التقارير اليومية — `routes/api.php:39` (8 مسارات)

```
GET    /api/reports                              → مرسِل: يرى الكل؛ مراقب: منشور مرئي فقط بلا ai_draft_content — paginate
POST   /api/reports         {title, report_date: YYYY-MM-DD, content?, visible_to_monitors? bool} → 201 (report_writer فقط) — report_date على Asia/Damascus
GET    /api/reports/{id}                         → 200 | 403 (مراقب لمسودة/مخفي)
PUT    /api/reports/{id}    {title?, summary?, recommendations?, visible_to_monitors?} → مالك + داخل 12h بعد النشر وإلا 403
DELETE /api/reports/{id}                         → 204 — مالك + داخل 12h وإلا 403
POST   /api/reports/{id}/attach   {note_ids: int[]} → 200 — ملاحظات مقبولة + نفس report_date فقط (مرفوضة/يوم آخر → 422)
DELETE /api/reports/{id}/notes/{noteId}          → 200 — مالك المسودة فقط
POST   /api/reports/{id}/reorder  {ordered_ids: int[]} → 200 — ترتيب pivot order_index
POST   /api/reports/{id}/publish                 → 200 — مالك + content غير فارغ + ملاحظة واحدة على الأقل + داخل 12h
POST   /api/reports/{id}/unpublish               → 200 — مالك + داخل 12h
POST   /api/reports/{id}/generate {regenerate?, confirm_overwrite_manual?, include_images?} → 200 + {ai_draft_content} — throttle:5,1
```

*   أخطاء التوليد مرمزة بدقة في `error`: `AI_DISABLED/403` · `AI_NOT_CONFIGURED|AI_AUTH/502-503` · `AI_RATE_LIMITED/429 (+retry_after)` · `AI_UNAVAILABLE/503` · `AI_BAD_RESPONSE/502` · `AI_BUSY/409 (120s)` · `AI_VALIDATION/422` — التفاصيل في `REPORTS.md`.
*   `report_sheet_renders` و`report_revisions` لا تُرجع للمراقب (كتّاب فقط).

## الإرسالات العامة — منفصلة `routes/api.php:28`

```
GET    /api/general-submissions                  → المرسِل + الكتّاب المعنيون فقط (pivot)
POST   /api/general-submissions   {floor_number, camera_number, observed_at?, description (≥10), writer_ids: int[], files[]? } → 201 (أي مصادق)
GET    /api/general-submissions/{id}             → 200 | 403 (غير مرسِل وغير معني)
POST   /api/general-submissions/{id}/submit      → draft → pending (مالك فقط)
POST   /api/general-submissions/{id}/accept      → pending → accepted (writer في pivot فقط)
POST   /api/general-submissions/{id}/reject      {rejection_reason} → pending → rejected (writer في pivot)
GET    /api/submission-attachments/{attachment}/view|download → حسب رؤية الإرسالية
```

> القبول يقلب الحالة فقط ولا ينشئ `Note` — `notes.general_submission_id` أرشيفي للفصل العرضي فقط.

## الويب — `routes/web.php` (67 مسار — نفس الخدمات)

```
GET  /gallery, /reports, /notes, /my-notes, /ranking, /profile, /notifications
POST /notes/{id}/send|accept|reject|resend (auth)
GET  /attachments/{id}/view|download — التفويض نفسه + تنزيل للكتّاب فقط
GET  /s/attachments/{id} + /s/submission-attachments/{id} (روابط دائمة — auth + SmartRedirect يمنع open-redirect)
POST /locale (throttle:30,1) — تبديل ar↔en
POST /translations/page (throttle:30,1) — stored-only بلا Gemini
GET  /notes/{id}/translation-status (60,1) + POST /notes/{id}/translation-retry (10,1) + POST /translations/retry (10,1) — ZERO Gemini متزامن
GET  /reports/{id}/preview|print|localized|sheet-html|filled-sheet + POST /reports/{id}/generate|generate-data|render|fill-sheet (5,1 / 3,10)
GET  /reports/{id}/export-pdf|export-image — export policy: كتّاب فقط (403 للمراقب حتى بالرابط المباشر)
GET  /pwa/manifest.json (locale-aware) + /sw.js + /offline.html + /health + /r (smart redirect) + fallback 404
```

## صيغة الاستجابة — موحدة

### نجاح
```json
{ "success": true, "message": "تم تنفيذ العملية بنجاح", "data": {} }
```

### خطأ — رسائل عربية دقيقة `lang/ar+en/api.php`
```json
{ "success": false, "message": "الوصف مطلوب / الوقت غير صالح / المدة تتجاوز 12 ساعة ...", "errors": { "description": ["..."] } }
```

## حالات HTTP — فعلية
*   `200` نجاح، `201` إنشاء، `204` حذف بلا محتوى
*   `401` غير مصرح (توكن مفقود/منتهي/مُسحوب blacklist)
*   `403` ممنوع (Policy — مسودة غيرك، تنزيل لغير كاتب، تصدير لمراقب، تعديل تقرير مقفل 12h)
*   `404` غير موجود
*   `422` تحقق (وصف/وقت/يوم تقرير/ملاحظات غير مقبولة)
*   `429` تجاوز حد الطلبات (throttle) — يعيد `Retry-After` header

## الحدود التشغيلية

*   لا `PATCH` جزئي للملاحظات — `PUT` كامل مع `normalizeObservedRange`.
*   لا عمليات جماعية (bulk) — كل إرفاق/فك/إعادة ترتيب طلب مستقل.
*   لا ترجمة عبر API — الترجمة طبقة عرض للويب فقط (Presentation).
*   `max_per_note=10` — `config/attachments.php`.
