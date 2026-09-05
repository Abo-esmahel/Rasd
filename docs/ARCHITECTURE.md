# ARCHITECTURE.md

## البنية العامة

### الويب
```
Blade
 → Web Controllers
 → NoteService (منطق مشترك)
 → Models
 → MySQL/SQLite
```

### API
```
Flutter
 → API Controllers
 → NoteService (منطق مشترك)
 → Models
 → MySQL/SQLite
```

### مبدأ أساسي
الويب وواجهة برمجة التطبيقات يشاركان نفس المنطق עסקי وقواعد التفويض.
لا يوجد تكرار في منطق العمل بين Controllers.

## المكونات

### NoteService
خدمة مركزية تحتوي على جميع عمليات العمل:
- createDraft
- updateNote
- deleteDraft
- sendNote
- acceptNote
- rejectNote
- resendRejectedNote
- addAttachment
- removeAttachment

### NotePolicy
سياسة مركزية للتفويض:
- create (monitor فقط)
- viewAny / view
- update / delete
- send / accept / reject / resend
- addAttachment / removeAttachment

### JWT Service
خدمة مخصصة للتوكن مع:
- توليد توكن HMAC-SHA256
- التحقق من التوكن (توقيع + انتهاء + blacklist)
- سحب التوكن (blacklist via Cache)
- انتهاء الصلاحية (config/jwt.php)
- السر من `config('jwt.secret')` وليس `env()` مباشرة

### Config
- `config/jwt.php`: إعدادات JWT (secret, expiry)
- `config/attachments.php`: حدود المرفقات (max_image_size, max_video_size, max_per_note)
- `config/filesystems.php`: قرص `private` منفصل بـ `serve => false` لمنع الوصول العام عبر `/storage`

## التوثيق
- Web: جلسات Laravel + CSRF
- API: JWT (Bearer token)
- كلاهما يستخدم نفس NoteService و NotePolicy

## التخزين
- الملفات: storage/app/private/notes/ (قرص `private`)
- لا يوجد رابط عام للتخزين (`serve => false`)
- أسماء ملفات مولدة UUID + امتداد آمن من MIME الفعلي (وليس امتداد العميل فقط)
- التحقق: امتداد + MIME فعلي عبر finfo + حجم + حد العدد + رفض الامتدادات الخطرة (php, phtml, html, js, exe, sh, bat, ...) + رفض الأسماء المزدوجة مثل `image.jpg.php` و `test.php.jpg` إذا احتوت php
- خدمة مخصصة لتحميل الملفات مع التفويض (يتبع قواعد visibility للملاحظة)
