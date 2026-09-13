# GALLERY.md — معرض المرفقات الموحد (Verified 2026-09-13)

> **المصدر:** `app/Http/Controllers/Web/GalleryController.php` + `resources/views/gallery/index.blade.php:1` — `unionAll` فعلي، `paginate(24)` فعلي.

## الفكرة

صفحة واحدة (`GET /gallery` → `gallery.index` `auth`) تعرض **كل المرفقات**: مرفقات الملاحظات + مرفقات الإرسالات العامة — كل مرفق تحته رابط مباشر لأصله (ملاحظة أو إرسالية) مع شارة مصدر.

## البنية — محققة

```
GalleryController@index (Request → validate → query)
  ├─ Attachment query: whereHas('note', fn($q)=> $q->whereNull('general_submission_id')->where(visible))
  │     whereNull(general_submission_id) يضمن عدم تسريب إرسالات كملاحظات
  ├─ GeneralSubmissionAttachment query: whereHas('submission', fn($q)=> visible)
  ├─ unionAll بأعمدة متوافقة (id, file_path, original_name, mime_type, file_size, created_at) + عمود kind ('note'|'submission')
  ├─ ترتيب `created_at DESC` / `ASC` حسب `sort` → paginate(24)
  ├─ تحميل آباء دفعة واحدة: attachment.note (owner, floor, camera) + submission (owner, writers)
  └─ تطبيع كل عنصر لمصفوفة موحدة:
       kind, id, file_path, original_name, mime, size, created_at,
       viewUrl (/attachments/{id}/view أو /submission-attachments/{id}/view),
       downloadUrl (محمي isReportWriter),
       parentUrl (/notes/{id} أو /general-submissions/{id}),
       parentKind, parentRef, camera, floor, ownerName, ownerAvatar
  → Blade: resources/views/gallery/index.blade.php
```

*   دمج عبر `unionAll` (لا `union` — أسرع بلا dedup) بأعمدة متوافقة + عمود `kind`.
*   `notes.general_submission_id` يبقي قوائم الملاحظات والإرسالات منفصلة، والمعرض وحده يوحّدهما عرضًا.
*   `withCount` بلا N+1 — التحميل `whereIn` للآباء.

## الفلترة — قابلة للطي، مخفية افتراضيًا `gallery/index.blade.php:1`

| باراميتر | القيم | يطبق على | التفاصيل |
|----------|-------|-----------|----------|
| `camera_number` | رقم/نص | الكاميرا (المصدرين) | `where('camera_number', $v)` مرن (varchar) |
| `floor_number` | رقم/نص | الطابق (المصدرين) | نفس المرونة |
| `type` | `image`/`video`/`audio` | `mime_type LIKE` | `image/%`, `video/%`, `audio/%` |
| `date` | `YYYY-MM-DD` | تاريخ إنشاء المرفق `created_at` | `whereDate` |
| `sort` | `latest`/`oldest` | الترتيب | `latest` = `created_at DESC` (افتراضي) |

*   شارة عدّاد على زر الفلترة عند التفعيل (`filterCount > 0`).
*   القوائم المنسدلة (كاميرات/طوابق) مدمجة من المصدرين عبر `pluck + unique + sort` — لا مصدر واحد فقط.
*   كل الفلاتر تُطبق قبل `unionAll` (على كل فرع) لتكون النتائج دقيقة.

## أنماط العرض — محفوظة في `localStorage` (`gallery:index.blade.php: js`)

| النمط | المفتاح `localStorage` | الوصف |
|-------|------------------------|-------|
| شبكة `grid` | `gallery_view=grid` | بطاقات بمعاينة كبيرة + شارة مصدر (ملاحظة خضراء / إرسال كهرمانية) — افتراضي |
| مدمج `compact` | `gallery_view=compact` | مصغرات مربعة كثيفة (أكثر كثافة، أقل نص) |
| قائمة `list` | `gallery_view=list` | صفوف مدمجة بمعاينة يساراً وتفاصيل يميناً |

*   التبديل فوري بلا إعادة تحميل — `classList` + حفظ المفتاح.
*   الصور `image/*` تُعرض كـ `img`، الفيديو كـ `video` مع `poster`، الصوت كـ `audio` — البقية أيقونة `file`.

## الصلاحيات — محققة `GalleryController + AttachmentController`

| الإجراء | مراقب | كاتب | زائر |
|---------|:---:|:---:|:---:|
| مشاهدة المعرض `GET /gallery` | ✅ | ✅ | ❌ (302 → login) |
| فتح الأصل `parentUrl` | ✅ (إن كان مرئياً له) | ✅ | ❌ |
| معاينة `viewUrl` | ✅ (حسب رؤية الأصل) | ✅ | ❌ |
| تنزيل `downloadUrl` | ❌ (403 — `isReportWriter` check) | ✅ | ❌ |

*   المسودات لا تظهر إلا لمالكها (نفس قواعد `getVisibleNotesQuery` + `GeneralSubmissionPolicy`).
*   زر التنزيل يستخدم مسارات التنزيل الأصلية المحمية أصلًا (`NoteController::downloadAttachment` + `GeneralSubmissionController::downloadAttachment` — تتحقق `isReportWriter` حتى بالرابط المباشر).

## الملفات — الفعلية

*   `app/Http/Controllers/Web/GalleryController.php` (88 سطر — `index` فقط)
*   `resources/views/gallery/index.blade.php` (128 سطر بعد التعديل — فلترة + 3 أنماط + `localStorage` + `unionAll` render)
*   `routes/web.php:120` → `GET /gallery` → `gallery.index` (مجموعة `auth`)
*   روابط القائمة: سطح مكتب + جوال (`resources/views/layouts/app.blade.php` — `nav_gallery`)

## حدود واقعية

*   لا بحث نصي داخل المعرض (الفلترة بالباراميترات فقط).
*   لا حذف جماعي من المعرض — الحذف من صفحة الأصل فقط.
*   `paginate(24)` ثابت — لا تحميل لا نهائي.
*   لا ترجمة لمحتوى المعرض نفسه — الترجمة للملاحظات/الإرسالات الأصلية عبر `LocalizedPresenter` عند فتح الأصل.
