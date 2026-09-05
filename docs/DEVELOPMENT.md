# DEVELOPMENT.md

## المتطلبات
- PHP 8.3+
- Laravel 13+
- MySQL 5.7+ أو SQLite
- Composer
- Node.js (اختياري للتطوير المحلي)

## التثبيت

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link (اختياري)
```

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
# أو
composer test
```

## تخزين الملفات

الملفات محفوظة في `storage/app/private/notes/` عبر قرص `private` (`config/filesystems.php` مع `serve => false`).
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
- استخدم MySQL提供的 credentials في .env

### Document Root
- يجب أن يشير إلى مجلد `public`

### .env
- انسخ `.env.example` إلى `.env`
- عيّن `APP_KEY` يدوياً أو عبر `php artisan key:generate`
- عيّن `DB_*` credentials
- عيّن `JWT_SECRET` لقᒪة عشوائية قوية

### التخزين
- تأكد من صلاحيات مجلد `storage`
- لا تستخدم `php artisan storage:link` على الاستضافة المشتركة

### الملفات الخاصة
- تأكد من أن `storage/app/private/` غير متاح عبر الويب
- لا توجد أ庸mistakes عامة للملفات

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
│   │   │   └── NoteController.php
│   │   └── Web/
│   │       ├── AuthController.php
│   │       └── NoteController.php
│   └── Middleware/
│       └── Api/
│           └── AuthenticateApi.php
├── Models/
│   ├── User.php
│   ├── Note.php
│   └── Attachment.php
├── Policies/
│   └── NotePolicy.php
├── Services/
│   ├── JwtService.php
│   └── NoteService.php
└── Providers/
    └── AppServiceProvider.php

database/
├── migrations/
├── factories/
└── seeders/

resources/views/
├── layouts/app.blade.php
├── auth/login.blade.php
└── notes/
    ├── index.blade.php
    ├── create.blade.php
    └── edit.blade.php

routes/
├── web.php
└── api.php

tests/
├── Feature/
│   ├── Api/
│   │   ├── AuthTest.php
│   │   ├── VisibilityTest.php
│   │   ├── AuthorizationTest.php
│   │   ├── WorkflowTest.php
│   │   ├── RejectionTest.php
│   │   ├── ResendTest.php
│   │   └── AttachmentTest.php
│   └── Web/
│       ├── AuthTest.php
│       └── NoteTest.php
└── Unit/

docs/
├── SYSTEM.md
├── ARCHITECTURE.md
├── DATABASE.md
├── API.md
├── WORKFLOW.md
└── DEVELOPMENT.md
```
