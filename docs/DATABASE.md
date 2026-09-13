# DATABASE.md — المخطط المحقق (Verified 2026-09-13)

> **المصدر:** `database/migrations/*.php` — كل جدول مُدرج هنا له ملف ترحيل فعلي.

## الجداول الأساسية

### users `0001_01_01_000000 + 2026_09_03* + 2026_09_04* + 2026_09_11*`
| العمود | النوع | nullable | مفتاح |
|-----------|----------|----------|----------|
| id | bigint | لا | PK |
| name | varchar | لا | - |
| username | varchar | لا | unique |
| password | varchar | لا | - |
| role | enum(`monitor`,`report_writer`) | لا | index |
| phone | varchar | نعم | - (سوري موحّد `SyrianPhone`) |
| avatar_path | varchar | نعم | - |
| personal_number | varchar | نعم | - |
| locale | varchar(5) | نعم | - (`ar`/`en` — تفضيل المستخدم) |
| remember_token | varchar | نعم | - |
| cloudinary_* | varchar | نعم | غير مستخدمة — التخزين المحلي هو المعتمد |
| created_at/updated_at | timestamp | لا | - |

### notes `2026_09_02_000001 + 2026_09_03_000001 + 2026_09_10_120000`
| العمود | النوع | nullable | مفتاح |
|----------------|----------|----------|----------|
| id | bigint | لا | PK |
| user_id | bigint | لا | FK→users + index |
| floor_number | varchar/integer | لا | index |
| camera_number | varchar/integer | لا | index |
| observed_at | datetime | لا | index |
| observed_end_at | datetime | نعم | - (عبور منتصف الليل → +1 day في `NoteService`) |
| description | text | لا | - |
| status | enum(`draft`,`pending`,`accepted`,`rejected`) | لا | index |
| rejection_reason | text | نعم | - |
| processed_by | bigint | نعم | FK→users + index |
| sent_at | timestamp | نعم | - |
| processed_at | timestamp | نعم | - |
| general_submission_id | bigint | نعم | index (ربط أرشيفي بطلب الإرسال — للفصل العرضي فقط) |
| created_at/updated_at | timestamp | لا | - |

*فهارس إضافية:* `2026_09_09_192321` يضيف `composite (status, observed_at)` و`(user_id, status)`.

### attachments `2026_09_02_000002`
| العمود | النوع | nullable | مفتاح |
|--------------|----------|----------|----------|
| id | bigint | لا | PK |
| note_id | bigint | لا | FK→notes cascade + index |
| file_path | varchar | لا | - (`notes/{id}/UUID.ext`) |
| original_name | varchar | لا | - |
| mime_type | varchar | لا | - (`finfo` حقيقي) |
| file_size | bigint | لا | - |
| created_at/updated_at | timestamp | لا | - |

### general_submissions `2026_09_06_161207`
`id` | `user_id` FK→users | `floor_number` varchar | `camera_number` varchar | `observed_at` datetime | `observed_end_at` nullable | `description` text | `status` enum(draft/pending/accepted/rejected) | `rejection_reason` nullable | `processed_by` FK nullable | `sent_at`/`processed_at` nullable | `created_at/updated_at`

*فهارس `2026_09_13_000001`: `user_id, status, observed_at, processed_by` + مركبة `(status,observed_at)` و`(user_id,status)`.*

### general_submission_attachments `2026_09_09_000001`
`id` | `general_submission_id` FK/cascade | `file_path` (`submissions/{id}/UUID.ext`) | `original_name` | `mime_type` | `file_size` | `created_at/updated_at`

### general_submission_report_writer `2026_09_06_161233`
`general_submission_id` FK/cascade + `user_id` FK/cascade (unique معًا) + index `user_id` (`2026_09_13_000001`)

## الإشعارات والدفع

### notifications `2026_09_04_000002` (Laravel DatabaseNotifications)
`id` UUID | `type` | `notifiable_type/id` | `data` JSON | `read_at` nullable | `created_at/updated_at` + فهارس مركبة (`2026_09_09_193637`)

### notification_preferences `2026_09_09_100000`
`id` | `user_id` FK unique | `email_notifications` bool | `push_notifications` bool | `sound_enabled` bool | `created_at/updated_at`

### push_subscriptions `2026_09_10_000001`
`id` | `user_id` FK→users | `endpoint` text (unique) | `public_key` | `auth_token` | `content_encoding` | `created_at/updated_at` + index `user_id`

## التقارير اليومية — الدومين الثالث

### reports `2026_09_12_000001 + 000004 + 000007`
```
id | author_id FK→users
title varchar(255)
content longText nullable          — النهائي المُركّب من القالب (مصدر النشر)
summary longText nullable          — الملخص المنظم (تعبئة)
recommendations longText nullable  — التوصيات المنظمة (تعبئة)
ai_draft_content longText nullable — مسودة Gemini للعرض (لا تُنشر)
ai_summary / ai_recommendations nullable — مخرجات الآلة للاعتماد
generation_mode enum(ai/manual/hybrid) default manual
status enum(draft/published) default draft
visible_to_monitors bool default true
report_date date                   — يوم تقويمي واحد (Asia/Damascus)
published_at nullable
ai_sheet_image_path varchar(255) nullable
ai_sheet_generated_at datetime nullable
ai_sheet_data_hash char(64) nullable
created_at/updated_at
indexes: report_date, status, author_id, visible_to_monitors
```

### report_note (pivot) `2026_09_12_000002`
`report_id` FK/cascade + `note_id` FK/cascade (فريد معًا) + `order_index` integer — ترتيب الملاحظات داخل التقرير (pivot).

### report_revisions `2026_09_12_000003`
`id` | `report_id` FK/cascade | `editor_id` FK nullable | `content_snapshot` longText (لقطة عند النشر وبعده) | `created_at/updated_at` + index `editor_id` (`2026_09_13_000001`)

### report_sheet_renders `2026_09_12_000008 + 000009 + 000010`
```
id | report_id FK/cascade
generation_no uint               — رقم الجيل (Race-safe)
data_version uint default 1       — إصدار البيانات
template varchar(20)             — screen-1 … screen-7
payload_hash char(64)            — hash الـ payload المرسل
system_hash char(64) nullable    — hash النظام الحالي (للكشف عن stale)
payload JSON nullable            — Payload الكامل (أُضيفت لاحقاً)
image_path varchar(255) nullable — مسار الصورة (nullable بعد 000010)
created_at/updated_at
unique: (report_id, payload_hash)
index: (report_id, generation_no)
```

## الترجمة — Presentation Cache خارج Business Data

### content_translations `2026_09_14_000001 + 000002 + 000003`
```
id bigint PK
translatable_type varchar(32)   — report | note | submission
translatable_id varchar(64)     — string (يدعم UUID و bigint)
field varchar(64)               — title | description | observation | recommendations | summary | rejection_reason …
source_hash char(64)            — sha256(source_text) — للتمييز عند تعديل المصدر
source_text mediumText
locale varchar(5)               — ar | en
translated_text mediumText
created_at/updated_at
unique: (translatable_type, translatable_id, field, locale, source_hash) — ct_entity_field_locale_hash_unique (بعد 000002)
index: (translatable_type, translatable_id, locale) — ct_entity_locale_idx
cache: database (keys: translation:{type}:{id}:{field}:{locale}:{hash}:v1)
```

*المواصفات الحالية:* `translatable_id` من نوع `string(64)` لدعم UUID، و`unique` يتضمن `source_hash` لضمان تجاهل الترجمة stale تلقائياً عند تعديل المصدر.

## العلاقات

*   `User hasMany notes (user_id)` + `hasMany processedNotes (processed_by)` + `hasMany reports (author_id)` + `hasMany pushSubscriptions` + `hasOne notificationPreference`
*   `Note belongsTo owner (user_id)` + `belongsTo processor (processed_by)` + `hasMany attachments` + `belongsToMany reports (report_note order_index)`
*   `GeneralSubmission belongsTo owner` + `belongsToMany reportWriters (general_submission_report_writer)` + `hasMany attachments` + `belongsTo processor`
*   `Report belongsTo author` + `belongsToMany notes (order_index)` + `hasMany revisions` + `hasMany sheetRenders`
*   `ContentTranslation polymorphic` (`translatable_type/id` + `field` + `locale`)
*   `ReportSheetRender belongsTo report`

## الفهارس — الأداء

*   `users.username unique` + `users.role`
*   `notes`: `user_id`, `status`, `observed_at`, `floor_number`, `camera_number`, `processed_by`, `general_submission_id` + مركبة `(status,observed_at)` و`(user_id,status)` و`(status,observed_at)` للإرسالات
*   `attachments.note_id`, `general_submission_attachments.general_submission_id`
*   `reports`: `report_date`, `status`, `author_id`, `visible_to_monitors`
*   `report_note`: `(report_id, note_id) unique`
*   `content_translations`: `(translatable_type, translatable_id, field, locale, source_hash) unique` — يمنع تكرار الترجمة لنفس البصمة

## ضمانات المخطط الحالية

*   `translatable_id` من نوع `varchar(64)` لدعم UUID.
*   `unique` على `(type, id, field, locale, source_hash)` — أي تعديل مصدر يبطل الترجمة غير المطابقة للبصمة تلقائياً (stale تُتجاهل).
*   جداول `report_sheet_renders` و`content_translations` و`push_subscriptions` جزء أساسي من المخطط الحالي.
*   `report_note.order_index` هو مصدر الترتيب الوحيد — لا عمود ترتيب في `notes`.
