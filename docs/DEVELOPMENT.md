# DEVELOPMENT.md — دليل التشغيل المُحقق (Verified 2026-09-13)

## المتطلبات — محققة من `composer.json` و`package.json`

*   `PHP 8.4+` (مطلوب `^8.4` في `composer.json` — `laravel/framework ^13.17` يتطلب 8.4)
*   `Laravel 13.17+`
*   `MySQL 8+` أو `SQLite` (الافتراضي `DB_CONNECTION=sqlite` في `.env.example`)
*   `Composer 2.x`
*   `Node.js 20+` (لبناء الواجهة: `npm run build` → `vite 8 + tailwindcss 4 + @tailwindcss/vite`)

## التثبيت

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed
npm install && npm run build
```

## المتغيرات البيئية — القيم الفعلية من `.env.example` و`config/*.php`

```ini
APP_URL=http://192.168.x.x:8000   # للجوال على نفس الواي فاي
SHARE_URL=                         # فارغ = ديناميكي من عنوان المتصفح (يتحول IP→domain تلقائياً)
DB_CONNECTION=sqlite
JWT_SECRET=مفتاح_64_حرف_hex       # إلزامي ≥32 حرفاً وإلا يرفض الإقلاع (config/jwt.php)
JWT_EXPIRY_MINUTES=60
APP_TIMEZONE=Asia/Damascus         # يوم التقرير يُحسب عليها — ثبّتها ولا تغيّرها بعد التشغيل
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
CACHE_STORE=database
QUEUE_CONNECTION=database          # لا Redis افتراضياً — الـ Jobs تُنفذ بعد الاستجابة (afterResponse) أو عبر worker
```

### JWT `config/jwt.php`
```
JWT_SECRET=your-256-bit-secret-key  # يُقرأ عبر config('jwt.secret') وليس env() مباشرة (آمن مع config:cache)
JWT_EXPIRY_MINUTES=60
```
يُولد عبر `php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"`. التوكن `HMAC-SHA256` مع `blacklist via Cache` عند `POST /api/logout`.

### المرفقات — القيم المعتمدة `config/attachments.php` + `.env.example`
```
MAX_IMAGE_SIZE=20480              # كيلوبايت = 20M
MAX_VIDEO_SIZE=102400             # كيلوبايت = 100M
MAX_AUDIO_SIZE=104857600          # بايت = 100M
MAX_ATTACHMENTS_PER_NOTE=10
MAX_ATTACHMENTS_PER_SUBMISSION=5
IMAGE_MAX_DIMENSION=1920
IMAGE_JPEG_QUALITY=82
IMAGE_WEBP_QUALITY=80
```
*   المصدر الوحيد للحد في الكود هو `NoteService::uploadFileMaxKb()` = `min(PHP upload_max_filesize, max(app_image, app_video, app_audio), 512M)` — لا حساب يدوي منفصل في الكنترولرز.
*   الكنترولر يفحص `client_files_count vs filesReceived` قبل المعاملة لكشف فقدان النقل (transport loss) في متصفحات الجوال مع الفيديو الكبير.
*   `php.ini` المطلوب للرفع المحلي السريع:
```
upload_max_filesize=128M
post_max_size=128M
max_file_uploads=20
memory_limit=256M
```

### التقارير الذكية (Gemini) `config/ai.php`
```ini
AI_ENABLED=false                  # false = كل شيء يدوي — لا Gemini يُستدعى
AI_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.5-flash     # المستقر الحالي (بدائل صالحة: gemini-3.6-flash, gemini-3.7-flash, gemini-3-flash-preview) — 2.5/2.0 متقاعد 404 منذ 2026-09
GEMINI_TIMEOUT=60
GEMINI_MAX_OUTPUT_TOKENS=4096
AI_MAX_IMAGES_PER_GENERATION=3
```
تُقرأ عبر `config/ai.php` فقط (لا `env()` في الكود). `AI_ENABLED=false` يبقي كل شيء يدوياً. خطوات استخراج المفتاح في `REPORTS.md`. توليد الصور بالذكاء مُزال — الرسم محلي `GD` حصراً، Gemini للنصوص فقط.

### مشاركة واتساب `config/app.php`
```
SHARE_URL=   # فارغ = ديناميكي (يأخذ host الطلب الحالي)
```

## الشبكة المحلية (مشاركة واتساب للجوال)

```bash
php artisan serve --host=0.0.0.0 --port=8000
```
+ السماح للجدار الناري للمنفذ `8000` + فتح نفس الواي فاي على الجوال. `SHARE_URL` الفارغ يجعل روابط `/s/...` تتكيف تلقائياً مع `192.168.x.x:8000`.

## البذور — الواقع الحالي `database/seeders/RasdSmartSeed.php`

```bash
php artisan db:seed              # DatabaseSeeder → RasdSmartSeed
php artisan migrate:fresh --seed # إعادة كاملة
```

يُنشئ (Verified من `RasdSmartSeed.php` الحالي):

*   مستخدمون: `tariq, hadi, hamza, rami` (مراقبون) + `writer` (كاتب رئيسي) + كُتّاب إضافيون — كلهم `password`
*   **ملاحظات تغطي 7 أيام** تشمل ملاحظات اليوم الحيّة (7 ملاحظات: مسودات/قيد المراجعة/مقبولة) — كل ملاحظة مع مرفق `SVG` مولد محلياً (`AttachmentStorageService` → `notes/{id}/UUID.svg`)
*   **تقارير منشورة** لكل يوم (byDay من المقبولة) + تقرير اليوم المنشور حديثاً (داخل مهلة التعديل 12h) + مسودة تقرير اليوم (بلا ملاحظات — لاختبار دورة النشر الكاملة)
*   **`report_sheet_renders`** لكل تقرير منشور (1–7 ملاحظات) — `template screen-1…7` + `payload_hash` + `system_hash`
*   **إشعارات** للملاحظات المرسلة عبر `NoteSentNotification → FanoutNoteNotifications` وللتقارير المنشورة عبر `ReportPublishedNotification` — مع `read_at` للمكتملة و`null` للحديثة (cutoff: بداية أمس)

> الإرسالات العامة تُنشأ يدوياً عبر الواجهة/الـAPI — لا تُزرع تلقائياً.

## الاختبارات — الوضع الحالي (Verified 2026-09-13)

> **الواقع الحالي:** مجلد `tests/` فارغ — `php artisan test` يعيد `Test directory not found`. البنية جاهزة لإضافة اختبارات Feature/Unit.

```bash
php artisan test                              # الوضع الحالي: لا اختبارات — المجلد فارغ
```

*النطاق المقترح للتغطية:* `tests/Feature/Report` (القالب الثابت + الأوراق + يوم واحد + تفويض + Gemini وهمي) + دورات العمل + المرفقات + المعرض + الصلاحيات + الترجمة.

## تخزين الملفات — محقق `config/filesystems.php`

```
قرص `attachments` → storage/app/private/
  notes/{id}/UUID.ext          (ملاحظات)
  submissions/{id}/UUID.ext    (إرسالات)
  ai/report-context.txt         (سياق Gemini — خارج public/ بلا مسار ويب)
```

*   لا يوجد رابط عام للتخزين (`/storage` لا يخدم `private` — `serve=>false`).
*   تحميل الملفات عبر endpoints مخصصة مع التفويض:
    *   `GET /api/attachments/{id}` (API, يتبع visibility + `throttle:60,1`)
    *   `GET /attachments/{id}/view|download` (Web, يتبع visibility — التنزيل للكتّاب فقط)
    *   `GET /s/attachments/{id}` و`/s/submission-attachments/{id}` (روابط مشاركة دائمة — الدخول إجباري)
*   الامتداد يُستنتج من `finfo` الحقيقي (لا من اسم الملف) ويُخزن باسم `UUID` آمن. قائمة `mimes` تشمل `ac3,dts,alac`.
*   `NoteService` يتحقق بعد `Attachment::create` من وجود السطر والملف فعلياً وإلا يحذف ويرمي `verification` — لا ملف يتيم.

## بنية المجلدات — الفعلية

```
app/
├── Console/Commands/RebuildAiContext.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/ (AuthController, NoteController, GeneralSubmissionController, ReportController)
│   │   └── Web/ (AuthController, NoteController, GeneralSubmissionController, GalleryController,
│   │             ReportController, ProfileController, NotificationController, NotificationStreamController,
│   │             LocaleController, DynamicTranslationController, NoteTranslationController,
│   │             PushSubscriptionController, SmartRedirectController)
│   ├── Middleware/ (AuthenticateApi, SetLocale, SetApiLocale, SecurityHeaders)
│   └── Requests/Api/* (StoreNoteRequest, Report/* …)
├── Jobs/ (FanoutNoteNotifications, FanoutDispatchNotifications, FanoutReportPublished,
│          WarmTranslationProjection, RebuildAiContext, SendPushToUser)
├── Models/ (User, Note, Attachment, GeneralSubmission, GeneralSubmissionAttachment,
│            Report, ReportRevision, ReportSheetRender, ContentTranslation,
│            NotificationPreference, PushSubscription)
├── Observers/ (AiContextObserver, TranslationWarmObserver)
├── Policies/ (NotePolicy, GeneralSubmissionPolicy, ReportPolicy)
├── Services/
│   ├── Ai/ (ReportAiService, ReportContextService, GeminiAiTextGenerator, FakeAiTextGenerator,
│   │        ReportAiPromptBuilder, ReportAiDataService, AiAttachmentResolver, ReportSheetFillService)
│   ├── Localization/ (TranslationService, DynamicTranslationService, GeminiTranslationProvider,
│   │                 LocalizedPresenter, SourceLanguage, TranslationProviderInterface)
│   ├── Report/ReportEngine.php
│   ├── ReportPreview/ (ReportHtmlRenderingService, ReportDataBuilder, ReportTemplateSelector, ReportRenderPayload)
│   ├── NoteService.php (normalizeObservedRange + uploadFileMaxKb + flushNoteCaches)
│   ├── GeneralSubmissionService.php + AttachmentStorageService.php + ReportService.php
│   ├── JwtService.php + WebPushService.php + ReportSheetService.php
│   └── Support/localization.php
├── Providers/AppServiceProvider.php (Observers + Policies)
database/
├── migrations/ (32 ملف — آخرها 2026_09_14_000003_stringify_content_translation_id)
├── factories/ (ReportFactory …)
└── seeders/ (DatabaseSeeder, RasdSmartSeed, RasdDemoNotesSeeder …)
resources/views/
├── layouts/app.blade.php (يحقن rasd-i18n-v2.js + app-bootstrap.js)
├── auth/login.blade.php
├── notes/ (index, my, create, edit, show + partials/print_modal, translation_status, smart_hint)
├── general-submissions/ (index, create, show)
├── reports/ (index, create, edit, show, preview, print, export-*, engine/document, sheet_page)
├── gallery/index.blade.php
├── shared/ (attachment, redirect)
├── profile/ (show, edit, ranking)
└── errors/ (403, 404, 419, 500) — جديدة
public/
├── images/eagle-emblem.svg + التقارير/screen-1…7.png + report/assets/logo-report.png
├── report/css/report-engine.css + fonts/ (Amiri, ArefRuqaa, NotoNaskhArabic)
├── pwa/ (manifest.json locale-aware, sw.js, offline.html, icons/)
└── js/ (rasd-i18n-v2.js — الجديد؛ rasd-i18n.js/rasd-dynamic-i18n.js أُزيلا)
routes/
├── web.php (67 مسار — gallery, reports, translations, locale, push, smart redirect, fallback 404)
└── api.php (27 مسار — notes, submissions, reports, attachments)
docs/
├── SYSTEM.md / ARCHITECTURE.md / DATABASE.md / API.md / WORKFLOW.md / GALLERY.md / REPORTS.md / DEVELOPMENT.md
```

## ملاحظات تشغيلية

*   `MAX_IMAGE_SIZE=20480 (20M)` و`MAX_VIDEO_SIZE=102400 (100M)` — `config/attachments.php`.
*   `APP_TIMEZONE=Asia/Damascus` — الـ naive يُفسر كـ Damascus لتفادي مستقبل كاذب (+03).
*   الواجهة الأمامية تعتمد `public/js/rasd-i18n-v2.js` فقط.
*   `QUEUE_CONNECTION=database` — لا `Redis` ولا `WebSocket`؛ الإشعارات تُرسل `afterResponse` داخل نفس الطلب أو عبر `php artisan queue:work`.
*   `CACHE_STORE=database` — الكاش يعيد arrays فقط (لا objects) — `Cache::remember('observers_list', 3600, …)` يخزن arrays لهذا السبب.

## InfinityFree (استضافة مشتركة)

### PHP
*   يدعم `PHP 8.x` — فعّل 8.4.

### MySQL
*   انسخ بيانات اتصال MySQL الممنوحة إلى `.env`.

### Document Root
*   يجب أن يشير إلى مجلد `public`.

### .env
```
APP_KEY= (php artisan key:generate)
DB_* credentials
JWT_SECRET= (64 hex)
```

### التخزين
*   تأكد من صلاحيات `storage/app/private/` — لا تستخدم `php artisan storage:link`.
*   `storage/app/private/` غير متاح عبر الويب — لا روابط عامة للملفات الخاصة إطلاقاً.

### الترحيلات
```bash
php artisan migrate --force
```

### التحديات
*   لا `queue worker` افتراضي — إن أردت `afterResponse` فعّال، شغّل `php artisan queue:work --queue=default`.
*   لا `Redis` / لا `WebSocket` — `BROADCAST_CONNECTION=log` افتراضياً.
*   الملفات تُخزن محلياً فقط — لا `S3` ولا `Cloudinary` (أعمدة `cloudinary_*` غير مستخدمة — محفوظة للتوافق).
