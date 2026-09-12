# DATABASE.md

## الجداول

### users
| العمود     | النوع    | nullable | مفتاح    |
|-----------|----------|----------|----------|
| id        | bigint   | لا       | PK       |
| name      | varchar  | لا       | -        |
| username  | varchar  | لا       | unique   |
| password  | varchar  | لا       | -        |
| role      | enum     | لا       | -        |
| created_at| timestamp| لا       | -        |
| updated_at| timestamp| لا       | -        |

`role` فقط: `monitor` | `report_writer`

### notes
| العمود          | النوع    | nullable | مفتاح    |
|----------------|----------|----------|----------|
| id             | bigint   | لا       | PK       |
| user_id        | bigint   | لا       | FK→users |
| floor_number   | integer  | لا       | index    |
| camera_number  | integer  | لا       | index    |
| observed_at    | datetime | لا       | index    |
| description    | text     | لا       | -        |
| status         | enum     | لا       | index    |
| rejection_reason| text    | نعم      | -        |
| processed_by   | bigint   | نعم      | FK→users |
| sent_at        | timestamp| نعم      | -        |
| processed_at   | timestamp| نعم      | -        |
| general_submission_id | bigint | نعم | index (ربط أرشيفي بطلب الإرسال — للفصل العرضي فقط) |
| created_at     | timestamp| لا       | -        |
| updated_at     | timestamp| لا       | -        |

`status`: `draft` | `pending` | `accepted` | `rejected`

### attachments
| العمود        | النوع    | nullable | مفتاح    |
|--------------|----------|----------|----------|
| id           | bigint   | لا       | PK       |
| note_id      | bigint   | لا       | FK→notes |
| file_path    | varchar  | لا       | -        |
| original_name| varchar  | لا       | -        |
| mime_type    | varchar  | لا       | -        |
| file_size    | bigint   | لا       | -        |
| created_at   | timestamp| لا       | -        |
| updated_at   | timestamp| لا       | -        |

## العلاقات
- User hasMany notes
- User hasMany processedNotes
- Note belongsTo owner (user_id)
- Note belongsTo processor (processed_by)
- Note hasMany attachments
- Attachment belongsTo note
- GeneralSubmission belongsTo owner + belongsToMany reportWriters + hasMany attachments
- GeneralSubmissionAttachment belongsTo submission

## الإرسالات العامة

### general_submissions
`id` | `user_id` FK→users | `floor_number` | `camera_number` | `observed_at` datetime | `observed_end_at` nullable | `description` text | `status` enum(draft/pending/accepted/rejected) | `rejection_reason` nullable | `processed_by` FK nullable | `sent_at`/`processed_at` nullable

### general_submission_attachments
`id` | `general_submission_id` FK/cascade | `file_path` | `original_name` | `mime_type` | `file_size`

### general_submission_report_writer
`general_submission_id` FK/cascade + `user_id` FK/cascade (unique معًا)

### users (إضافات)
`phone` nullable (سوري موحّد) · `avatar_path` nullable · `personal_number` nullable · `remember_token` · أعمدة `cloudinary_*` قديمة غير مستخدمة (التخزين الحالي محلي)

## التقارير اليومية

### reports
`id` | `author_id` FK→users | `title` | `content` (النهائي المُركّب من القالب — مصدر النشر) | `summary` nullable (الملخص — تعبئة منظمة) | `recommendations` nullable (التوصيات — تعبئة منظمة) | `ai_draft_content` nullable (مسودة Gemini للعرض — لا تُنشر) | `ai_summary` / `ai_recommendations` nullable (مخرجات الآلة للاعتماد) | `generation_mode` enum(ai/manual/hybrid) | `status` enum(draft/published) | `visible_to_monitors` bool | `report_date` date | `published_at` nullable

### report_note
`report_id` FK/cascade + `note_id` FK/cascade (فريد معًا) + `order_index` (ترتيب الملاحظات داخل التقرير)

### report_revisions
`report_id` FK/cascade + `editor_id` FK nullable + `content_snapshot` (لقطة عند النشر وبعده)

### علاقات التقارير
- User hasMany reports (author_id)
- Report belongsTo author + belongsToMany notes (مرتبة بـ order_index) + hasMany revisions

## الفهارس
- users.username (unique)
- notes.user_id
- notes.status
- notes.observed_at
- notes.floor_number
- notes.camera_number
- notes.processed_by
- attachments.note_id
- reports.report_date / reports.status / reports.author_id / reports.visible_to_monitors
- report_note [report_id, note_id] (فريد)
