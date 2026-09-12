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
الويب وواجهة برمجة التطبيقات يشاركان نفس منطق العمل وقواعد التفويض.
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
- getVisibleNotesQuery (تستبعد ملاحظات الإرسالات `general_submission_id IS NULL`)

### GeneralSubmissionService
- createDraft / submit / accept / reject للإرسالات العامة
- **القبول يقلب الحالة فقط — لا ينشئ `Note` أبدًا** (الإرسالات ليست ملاحظات)
- getVisibleSubmissions (المرسِل + الكتّاب المعنيون)

### GalleryController
- معرض موحد: `unionAll` بين مرفقات الملاحظات والإرسالات + تطبيع + `paginate(24)` — راجع `GALLERY.md`

### ReportService
- الدومين الثالث بنفس النمط: createDraft / attachNotes / detachNote / reorderNotes / update / publish / unpublish / delete
- قاعدة اليوم الواحد تُفحص في السيرفر عبر `reportDay()` — لا يعتمد على الواجهة
- كل العمليات الحرجة داخل معاملات (transactions)

### ReportPolicy
- create/generateAi/publish/unpublish/update/delete: كاتب التقارير لتقاريره فقط
- view: الكاتب يرى الكل، والمراقب يرى المنشور المرئي فقط (بدون مسودة AI)

### منظومة Gemini (التقارير فقط)
- `ReportAiService` ينسق: تحقق ← شريحة سياق ← بناء طلب ← Gemini ← حفظ `ai_draft_content` (لا يمس `content`)
- `ReportContextService` يبني `storage/app/ai/report-context.txt` من قاعدة البيانات فقط + أمر `reports:rebuild-ai-context`
- `GeminiAiTextGenerator` عبر `AiTextGeneratorInterface` — المفتاح والموديل من `config/ai.php` فقط

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
- `config/attachments.php`: حدود المرفقات
- `config/ai.php`: إعدادات التقارير الذكية (تفعيل، مفتاح Gemini، الموديل، حد الصور) — راجع `REPORTS.md`
- `config/filesystems.php`: قرص محلي `attachments` (`notes/{id}/...` + `submissions/{id}/...`)
- `config/app.php`: `share_url` — هوست روابط المشاركة (فارغ = تلقائي ديناميكي)

## التوثيق
- Web: جلسات Laravel + CSRF
- API: JWT (Bearer token)
- كلاهما يستخدم نفس NoteService و NotePolicy

## التخزين
- الملفات: قرص محلي `attachments` — ملاحظات `notes/{id}/` + إرسالات `submissions/{id}/`
- لا وصول مباشر — عرض/تنزيل عبر endpoints بتفويض
- روابط مشاركة دائمة نظيفة `/s/...` (صفحة عرض خاصة، الدخول إجباري)
- أسماء ملفات UUID + فحص `finfo` + رفض الامتدادات الخطرة والمزدوجة
