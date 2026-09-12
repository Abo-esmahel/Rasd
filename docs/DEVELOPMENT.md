# DEVELOPMENT.md

## المتطلبات
- PHP 8.4+
- Laravel 13+
- MySQL 8+ أو SQLite
- Composer
- Node.js 20+ (لبناء الأصول: `npm run build`)

## التثبيت

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed
npm install && npm run build
```

## المتغيرات البيئية (المهمة)

```ini
APP_URL=http://192.168.x.x:8000
SHARE_URL=                       # فارغ = ديناميكي من عنوان المتصفح (IP يتحول لنطاق تلقائيًا)
DB_CONNECTION=sqlite
JWT_SECRET=مفتاح_قوي
JWT_EXPIRY_MINUTES=60
APP_TIMEZONE=UTC                 # يوم التقرير يُحسب عليها — ثبّتها ولا تغيّرها بعد التشغيل حتى لا تنكسر مطابقة منتصف الليل
```

## الشبكة المحلية (مشاركة واتساب للجوال)

```bash
php artisan serve --host=0.0.0.0 --port=8000
```
+ السماح بالجدار الناري للمنفذ `8000` + فتح نفس الواي فاي على الجوال.

## المتغيرات البيئية

### JWT
```
JWT_SECRET=your-256-bit-secret-key  # يُقرأ عبر config/jwt.php
JWT_EXPIRY_MINUTES=60
```
تُستخدم عبر `config('jwt.secret')` وليس `env()` مباشرة (آمن مع config:cache).

### المرفقات
```
MAX_IMAGE_SIZE=5120        # كيلوبايت (5 ميجا) -> config/attachments.php
MAX_VIDEO_SIZE=30720       # كيلوبايت (30 ميجا)
MAX_ATTACHMENTS_PER_NOTE=5 # عدد الملفات
```
تُستخدم عبر `config('attachments.*')` في NoteService و StoreAttachmentRequest.

### التقارير الذكية (Gemini)
```
AI_ENABLED=false
AI_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
GEMINI_TIMEOUT=60
GEMINI_MAX_OUTPUT_TOKENS=4096
AI_MAX_IMAGES_PER_GENERATION=3
```
تُقرأ عبر `config/ai.php` فقط (لا `env()` في الكود). `AI_ENABLED=false` يبقي كل شيء يدوياً. خطوات استخراج المفتاح في `REPORTS.md`.

## البذور

```bash
php artisan db:seed
```

يُنشئ:
- مُراقب 1: monitor1 / password
- مُراقب 2: monitor2 / password
- كاتب تقارير: writer1 / password

## الاختبارات

```bash
php artisan test
```

`155` اختبارًا: دورات العمل، المرفقات، المشاركة، المعرض، الإشعارات، الصلاحيات، API، والتقارير الذكية (`tests/Feature/Report`).

```bash
php artisan test tests/Feature/Report   # التقارير فقط
php artisan test --filter=ReportLiveGeminiTest  # تكامل حي حقيقي (يتطلب RUN_LIVE_GEMINI_TEST=true ومفتاحاً فعلياً)
```

## تخزين الملفات

الملفات محفوظة في `storage/app/private/` عبر قرص `attachments` (`notes/{id}/` للملاحظات و`submissions/{id}/` للإرسالات — `config/filesystems.php` مع `serve => false`).
لا يوجد رابط عام للتخزين (`/storage` لا يخدم ملفات `private`).
تحميل الملفات عبر endpoints مخصصة مع التفويض:
- `GET /api/attachments/{id}` (API, يتبع visibility)
- `GET /attachments/{id}/download` (Web, يتبع visibility)
- الامتداد يُستنتج من MIME الفعلي (finfo) ويُخزن باسم UUID آمن.

## InfinityFree

### PHP
- يدعم PHP 8.x
- تأكد من تفعيل الإصدار المناسب

### MySQL
- انسخ بيانات اتصال MySQL الممنوحة لك إلى `.env`

### Document Root
- يجب أن يشير إلى مجلد `public`

### .env
- انسخ `.env.example` إلى `.env`
- عيّن `APP_KEY` يدوياً أو عبر `php artisan key:generate`
- عيّن `DB_*` credentials
- عيّن `JWT_SECRET` قيمة عشوائية قوية

### التخزين
- تأكد من صلاحيات مجلد `storage`
- لا تستخدم `php artisan storage:link` على الاستضافة المشتركة

### الملفات الخاصة
- تأكد من أن `storage/app/private/` غير متاح عبر الويب
- لا توجد روابط عامة للملفات الخاصة إطلاقاً

### الترحيلات
```bash
php artisan migrate --force
```

### التحديات
- لا يوجد queue worker افتراضي
- لا يوجد Redis
- لا يوجد WebSocket
- الملفات تُخزن محلياً فقط

## بنية المجلدات

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── NoteController.php
│   │   │   └── GeneralSubmissionController.php
│   │   └── Web/
│   │       ├── AuthController.php
│   │       ├── NoteController.php
│   │       ├── GeneralSubmissionController.php
│   │       ├── GalleryController.php
│   │       ├── ProfileController.php
│   │       └── Notification*.php
│   └── Middleware/
│       └── Api/
│           └── AuthenticateApi.php
├── Models/
│   ├── User.php
│   ├── Note.php
│   ├── Attachment.php
│   ├── GeneralSubmission.php
│   ├── GeneralSubmissionAttachment.php
│   ├── Report.php
│   └── ReportRevision.php
├── Policies/
│   ├── NotePolicy.php
│   ├── GeneralSubmissionPolicy.php
│   └── ReportPolicy.php
├── Services/
│   ├── JwtService.php
│   ├── NoteService.php
│   ├── GeneralSubmissionService.php
│   ├── AttachmentStorageService.php
│   ├── WebPushService.php
│   ├── ReportService.php
│   └── Ai/ (سياق Gemini، بناء الطلب، المولّد، محلل المرفقات)
└── Providers/
    └── AppServiceProvider.php

database/
├── migrations/
├── factories/
└── seeders/

resources/views/
├── layouts/app.blade.php
├── auth/login.blade.php
├── notes/
├── general-submissions/
├── reports/ (العرض + التوليد + طباعة A4 منفصلة)
├── gallery/index.blade.php
├── shared/attachment.blade.php
└── profile/*

public/images/eagle-emblem.svg (شعار Vector)

routes/
├── web.php
└── api.php

tests/
├── Feature/
└── Unit/

docs/
├── SYSTEM.md
├── ARCHITECTURE.md
├── DATABASE.md
├── API.md
├── WORKFLOW.md
├── GALLERY.md
├── REPORTS.md
└── DEVELOPMENT.md
```
