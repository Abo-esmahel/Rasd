# GALLERY.md — معرض المرفقات الموحد

## الفكرة

صفحة واحدة (`/gallery`) تعرض **كل المرفقات**: مرفقات الملاحظات + مرفقات الإرسالات العامة — كل مرفق تحته رابط مباشر لأصله (ملاحظة أو إرسالية).

## البنية

```
GalleryController@index
 ├─ Attachment (+whereHas note: مرئية + ليست من إرسالية)
 ├─ unionAll
 └─ GeneralSubmissionAttachment (+whereHas submission: مرئية)
 → ترتيب بتاريخ الإنشاء → paginate(24) → تطبيع عناصر → Blade
```

*   دمج عبر `unionAll` بأعمدة متوافقة + عمود `kind` (`note` | `submission`).
*   تحميل الآباء دفعة واحدة (`whereIn`) ثم تطبيع كل عنصر لمصفوفة موحدة:
    `kind, id, name, mime, size, created, viewUrl, downloadUrl, parentUrl, parentKind, parentRef, camera, floor, ownerName`.
*   `notes.general_submission_id` يبقي قوائم الملاحظات والإرسالات منفصلة، والمعرض وحده يوحّدهما عرضًا.

## الفلترة (قابلة للطي، مخفية افتراضيًا)

| باراميتر | القيم | يطبق على |
|----------|-------|-----------|
| `camera_number` | رقم | الكاميرا (المصدرين) |
| `floor_number` | رقم | الطابق (المصدرين) |
| `type` | `image`/`video`/`audio` | `mime_type LIKE` |
| `date` | `YYYY-MM-DD` | تاريخ إنشاء المرفق |
| `sort` | `latest`/`oldest` | الترتيب |

شارة عدّاد على زر الفلترة عند التفعيل. القوائم المنسدلة (كاميرات/طوابق) مدمجة من المصدرين.

## أنماط العرض (محفوظة في `localStorage`)

| النمط | الوصف |
|-------|-------|
| شبكة `grid` | بطاقات بمعاينة كبيرة + شارة مصدر (ملاحظة خضراء / إرسال كهرمانية) |
| مدمج `compact` | مصغرات مربعة كثيفة |
| قائمة `list` | صفوف مدمجة بمعاينة وتفاصيل |

## الصلاحيات

| الإجراء | مراقب | كاتب | زائر |
|---------|:---:|:---:|:---:|
| مشاهدة المعرض | ✅ | ✅ | ❌ (دخول) |
| فتح الأصل | ✅ | ✅ | ❌ |
| تنزيل | ❌ | ✅ | ❌ |

*   المسودات لا تظهر إلا لمالكها (نفس قواعد الرؤية).
*   زر التنزيل يستخدم مسارات التنزيل الأصلية المحمية أصلًا (`isReportWriter`).

## الملفات

*   `app/Http/Controllers/Web/GalleryController.php`
*   `resources/views/gallery/index.blade.php`
*   Route: `GET /gallery` → `gallery.index` (مجموعة `auth`)
*   روابط القائمة: سطح مكتب + جوال (`layouts/app.blade.php`)
