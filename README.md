# نظام ملاحظات كاميرات المراقبة — وزارة الإعلام

> **نظام داخلي** لتسجيل ومتابعة ملاحظات مراقبة الكاميرات — واجهة ويب عربية RTL + واجهة برمجة تطبيقات REST لتطبيق Flutter + تطبيق PWA

> **قبل تعديل المشروع اقرأ مجلد `/docs` أولاً:** `SYSTEM.md` · `ARCHITECTURE.md` · `DATABASE.md` · `API.md` · `WORKFLOW.md` · `DEVELOPMENT.md`

---

## 📌 نظرة عامة

يسمح النظام للمراقبين بتسجيل ملاحظات ميدانية مرتبطة بـ **الطابق + الكاميرا + تاريخ/وقت الرصد + وصف + مرفقات (صور/فيديو/صوت)** ضمن دورة حياة محكمة:

```
مسودة (draft) → قيد المراجعة (pending) → مقبولة (accepted) | مرفوضة (rejected) → إعادة إرسال → pending
```

*   **الويب:** Blade + Tailwind + واجهة RTL قابلة للتثبيت كـ PWA
*   **API:** نفس المنطق عبر `NoteService` + `NotePolicy` مع مصادقة JWT مخصصة
*   **التخزين:** قرص خاص `private` غير مكشوف عبر `/storage`

---

## ✨ المميزات الرئيسية

| الفئة | المميزات |
|------|----------|
| **الملاحظات** | إنشاء مسودات، تعديل، حذف المسودات فقط، إرسال، قبول/رفض، إعادة إرسال المرفوضة، `observed_end_at` (نهاية الرصد)، عدادات وفلترة |
| **المرفقات** | رفع حتى **50 ملف/ملاحظة** — صور `jpg/png/webp` حتى 5MB، فيديو `mp4/webm/mov/avi/3gp/mkv/m4v` حتى 30MB، صوت `mp3/wav/ogg/m4a/aac/wma/flac/opus` حتى 10MB — أسماء UUID آمنة، فحص `finfo` + امتداد + رفض الامتدادات الخطرة ومزدوجة الامتداد |
| **التسجيل الصوتي** | زر `تسجيل صوتي` في `create/edit` عبر `MediaRecorder` — صوت في ذاكرة المتصفح فقط `Blob` + معاينة `audio` + `URL.createObjectURL/revoke` — لا localStorage/IndexedDB — يُرسل مع `FormData` كـ `attachments[]` |
| **الكاميرا المباشرة** | `getUserMedia` (HTTPS/localhost) + fallback `<input capture>` للـ HTTP — صورة/فيديو بدون حفظ محلي |
| **المشاركة** | `navigator.share({text, files})` مع fallback `wa.me` + نسخ — محترم لـ Secure Context |
| **المستخدمون** | ملف شخصي + avatar، رقم جوال سوري موحّد `SyrianPhone`، سجل إشعارات، ترتيب |
| **PWA** | `public/pwa/` — `manifest.json` + `sw.js` + أوفلاين محدود + تثبيت standalone RTL |
| **الأداء** | `config:cache` + `route:cache` + `view:cache`، تحميل Tailwind مؤجل، خط Cairo async، `preflight: false` |
| **الأمان** | حماية mass-assignment، تخزين خاص `serve:false`، مصادقة API بتوكن عبر Header أو `?token=` للصور |

---

## 🧱 الـ Stack التقني

*   **Backend:** `Laravel 13` / `PHP 8.3+`
*   **Frontend Web:** `Blade` + `Tailwind CDN (defer)` + `Cairo` + ألوان sage `#1f6f4a` + خلفية دافئة `#f5f3ef` + dark mode
*   **API:** REST JSON — `Bearer JWT (HMAC-SHA256)` — انتهاء `60 دقيقة`
*   **DB:** `MySQL 5.7+` / `SQLite` (اختبارات `:memory:`) — Eloquent
*   **Auth:** Web `session + CSRF` — API `JwtService + Cache blacklist`
*   **Build:** `Vite` (اختياري)، `Composer scripts: setup/dev/test`
*   **Tests:** `PHPUnit 12` — `63` اختبار

---

## 🏛 البنية (ملخص `ARCHITECTURE.md`)

```
Blade (Web) ──┐
              ├─→ Web Controllers ──┐
Flutter (API)─┘   API Controllers ──┤
                                   ├─→ NoteService (منطق موحّد) ──→ Models ──→ MySQL/SQLite
                                   └─→ NotePolicy  (تفويض موحّد)
```

*   **NoteService:** `createDraft / updateNote / deleteDraft / sendNote / acceptNote / rejectNote / resendRejectedNote / addAttachment / removeAttachment` — معاملات transactions عند الحاجة
*   **NotePolicy:** `create / viewAny / view / update / delete / send / accept / reject / resend / addAttachment / removeAttachment`
*   **JwtService:** توليد/تحقق `HMAC-SHA256` + فحص signature/expiry/blacklist (Cache)
*   **Config:** `config/jwt.php` + `config/attachments.php` + `config/filesystems.php` قرص `private` `serve:false` + `storage/app/private/notes/`

> الويب والـ API **لا يكرران** المنطق.

---

## 👥 الأدوار والصلاحيات (`SYSTEM.md`)

### الأدوار

| الدور | الوصف |
|------|-------|
| `monitor` | مُراقب — ينشئ ويدير ملاحظاته فقط |
| `report_writer` | كاتب تقارير — يعرض (عدا المسودات) + يقبل/يرفض |

### الملكية

`notes.user_id` = **المالك** وليس الرؤية — لا يمكن للعميل التلاعب به (محمي بـ `array_intersect_key`).

### مصفوفة الرؤية

| الحالة | مالك (monitor) | مراقب آخر | كاتب تقارير |
|--------|:---:|:---:|:---:|
| `draft` | ✅ | ❌ | ❌ |
| `pending` | ✅ | ✅ | ✅ |
| `rejected` | ✅ | ✅ | ✅ |
| `accepted` | ✅ | ✅ | ✅ |

### ملاحظاتي + إنشاء كاتب التقرير
*   صفحة `ملاحظاتي` (`/my-notes`): مرشحة تلقائياً لملاحظات المستخدم الحالي.
*   كاتب التقرير **يستطيع إنشاء ملاحظات** وتُقبل تلقائياً `status=accepted` مع `processed_by/processed_at`.

---

## 🔄 دورة الحياة (`WORKFLOW.md`)

```
draft ──(إرسال: مالك فقط)──→ pending ──(قبول: كاتب فقط)──→ accepted (نهائي)
                              pending ──(رفض+سبب: كاتب فقط)──→ rejected ──(إعادة إرسال: مالك فقط)──→ pending
```

**الانتقالات غير المسموحة:** `draft→accepted/rejected`، `pending→draft`، `accepted→*`، `rejected→accepted/draft`

**التعديل/الحذف:**

| الحالة | تعديل (مالك فقط) | حذف (مالك فقط) |
|--------|:---:|:---:|
| draft | ✅ | ✅ |
| pending | ✅ | ❌ |
| rejected | ✅ | ❌ |
| accepted | ❌ | ❌ |

*   الإرسال يضبط `sent_at` ويمسح حقول الرفض.
*   القبول/الرفض يضبط `processed_by/at` — الملاحظة المقبولة ثابتة.

---

## 🗄 قاعدة البيانات (`DATABASE.md`)

### `users`
| عمود | نوع | قيد |
|------|------|-----|
| `id` | bigint | PK |
| `name` | varchar | - |
| `username` | varchar | unique |
| `password` | varchar | hashed |
| `role` | enum(`monitor`,`report_writer`) | - |
| `avatar` | varchar | nullable |
| `personal_number` | varchar | nullable (رقم واتساب سوري) |

### `notes`
`id` | `user_id` FK→users | `floor_number` int indexed | `camera_number` int indexed | `observed_at` datetime indexed | `observed_end_at` datetime nullable | `description` text | `status` enum(`draft`,`pending`,`accepted`,`rejected`) indexed | `rejection_reason` text nullable | `processed_by` FK→users nullable | `sent_at` timestamp nullable | `processed_at` timestamp nullable

### `attachments`
`id` | `note_id` FK | `file_path` varchar | `original_name` varchar | `mime_type` varchar | `file_size` bigint

**العلاقات:** `User hasMany notes / processedNotes` · `Note belongsTo owner/processor` · `Note hasMany attachments` · `Attachment belongsTo note`

---

## 📎 المرفقات والتسجيل الصوتي

*   **المسار:** `storage/app/private/notes/` — قرص `private` — `serve:false` — لا يوجد `public/storage` للمجلد الخاص
*   **الأسماء:** `UUID + امتداد مستنتج من MIME الفعلي` (finfo) وليس امتداد العميل
*   **التحقق:** `allowedMimes` + `allowedMimeTypes` (finfo) + حجم + عدد (50) + رفض `php/phtml/phar/html/js/exe/sh/bat...` + رفض `test.php.jpg` / `image.jpg.php`
*   **التنزيل/العرض:**
    *   `GET /attachments/{id}/view` (stream inline) — أي مستخدم يملك حق `view`
    *   `GET /attachments/{id}/download` (attachment) — **كاتب التقارير فقط**
    *   `GET /api/attachments/{id}` — API بنفس القواعد
*   **التسجيل الصوتي:** `MediaRecorder` مع اختيار `audio/webm;codecs=opus → audio/webm → audio/ogg...` — `Blob` في RAM + `URL.createObjectURL` للمعاينة + تنظيف `revokeObjectURL` + `stream.getTracks().stop()` — لا يُحفظ على الجهاز

---

## 🔐 الأمان

*   مصادقة Web: `auth` + `CSRF` — API: `auth.api` (يقبل `Authorization: Bearer` **أو** `?token=`)
*   JWT: سر من `config('jwt.secret')` وليس `env()` مباشرة — انتهاء عبر `config/jwt.php`
*   mass assignment محمي (`array_intersect_key` في `NoteService`)
*   `AuthenticateApi` يعترض `?token=` لعرض الصور في PWA
*   تطبيع أرقام سوريا عبر `App\Support\SyrianPhone` — رسالة خطأ موحدة `رقم الجوال غير صحيح`
*   `X-Content-Type-Options: nosniff` عند عرض الملفات

---

## 🌐 المسارات (Routes)

### Web (`routes/web.php`) — `auth`

| Method | URI | الاسم | الوصف |
|--------|-----|-------|-------|
| GET | `/` | - | تحويل لـ login/dashboard |
| GET/POST | `/login` | `login` | تسجيل دخول ويب |
| POST | `/logout` | `logout` | خروج |
| GET | `/dashboard` | `dashboard` | → `notes.index` |
| RESOURCE | `/notes` | `notes.*` | CRUD |
| GET | `/my-notes` | `notes.my` | ملاحظاتي |
| POST | `/notes/{note}/send` | `notes.send` | إرسال |
| POST | `/notes/{note}/accept` | `notes.accept` | قبول |
| POST | `/notes/{note}/reject` | `notes.reject` | رفض |
| POST | `/notes/{note}/resend` | `notes.resend` | إعادة إرسال |
| DELETE | `/notes/{note}/attachments/{attachment}` | `notes.attachments.destroy` | حذف مرفق |
| GET | `/attachments/{a}/view` | `notes.attachments.view` | عرض |
| GET | `/attachments/{a}/download` | `notes.attachments.download` | تنزيل (كاتب فقط) |
| GET/PUT | `/profile`, `/profile/edit` | `profile.*` | الملف الشخصي + avatar `storage/app/public/avatars/` |
| GET | `/ranking` | `ranking` | الترتيب |
| GET/POST | `/notifications*` | `notifications.*` | الإشعارات |

### API (`routes/api.php`)

| Method | URI | Middleware | الوصف |
|--------|-----|------------|-------|
| POST | `/api/login` | `throttle:5,1` | دخول → `{token, user}` |
| POST | `/api/logout` | `auth.api` | خروج (blacklist) |
| GET | `/api/me` | `auth.api` | بياناتي |
| GET | `/api/notes` `?status&date&floor_number&camera_number` | `auth.api` | قائمة + فلترة + pagination |
| GET | `/api/my-notes` | `auth.api` | ملاحظاتي |
| POST | `/api/notes` | `auth.api` (monitor) | إنشاء مسودة |
| GET/PUT/DELETE | `/api/notes/{note}` | `auth.api` | عرض/تحديث/حذف |
| POST | `/api/notes/{note}/send|accept|reject|resend` | `auth.api` | تدفق العمل |
| POST | `/api/notes/{note}/attachments` | `auth.api` | رفع `file` multipart |
| DELETE | `/api/notes/{note}/attachments/{attachment}` | `auth.api` | حذف مرفق |
| GET | `/api/attachments/{attachment}` | `auth.api` | تنزيل ملف |

> صيغة الاستجابة الموحدة: `{success, message, data, errors}` — أكواد `200/201/204/401/403/404/422/429`

---

## 📱 PWA + الواجهة

*   **PWA:** `public/pwa/{manifest.json, sw.js, index.html, js/{app,api,auth,screens}.js, css/app.css}` — `start_url: /pwa/` — `display: standalone` — RTL — `theme_color #1f6f4a`
*   **الويب RTL:** `resources/views/layouts/app.blade.php` — Tailwind CDN `defer` + `preflight:false` — خط Cairo async — بطاقات بيضاء على خلفية `#f5f3ef` — `maximum-scale=1, user-scalable=no` — loader فوري — دعم الوضع الداكن
*   **المشاركة:** `navigator.share({text, files})` حصرياً — لا `wa.me` احتياطي — إخفاء الأزرار عند عدم الدعم — textarea قابلة للتحرير + اختيار مرفقات
*   **AJAX:** `send/accept/reject/resend/delete` بدون إعادة تحميل + تحديث badge + فلترة تبويبات عبر `History API` + استجابة `X-Requested-With`
*   **الوقت:** حقلا `date + time` منفصلان + presets + معاينة عربية حية

---

## 🚀 التثبيت المحلي

### المتطلبات
*   `PHP 8.3+` — `Composer` — `MySQL 5.7+` أو `SQLite` — `Node.js` (اختياري)

### خطوات سريعة

```bash
composer install
cp .env.example .env
php artisan key:generate
# SQLite للتطوير السريع
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
php artisan migrate --force
php artisan db:seed
php artisan test                 # 63 اختبار
php artisan serve                # http://127.0.0.1:8000
# أو عبر Laragon — تأكد أن composer في PATH أو استخدم المسار الكامل
```

### سكربتات Composer

```bash
composer setup   # install + .env + key + migrate + npm build
composer dev     # artisan dev (serve + queue + vite)
composer test    # config:clear + artisan test
```

### متغيرات البيئة (`DEVELOPMENT.md`)

```ini
APP_NAME="ملاحظات الكاميرات"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
# أو mysql:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=rasd
# DB_USERNAME=root
# DB_PASSWORD=

JWT_SECRET=ضع_مفتاح_256بت_عشوائي_قوي
JWT_EXPIRY_MINUTES=60

MAX_IMAGE_SIZE=5120        # KB
MAX_VIDEO_SIZE=30720       # KB
MAX_ATTACHMENTS_PER_NOTE=50
```

> تُقرأ عبر `config('jwt.secret')` و `config('attachments.*')` وليس `env()` مباشرة (آمن مع `config:cache`).

### البذور

```bash
php artisan db:seed
# monitor1 / password — طارق عبد الرحمن
# monitor2 / password — هادي السهلي
# writer1  / password — كاتب التقرير
```

---

## 🧪 الاختبارات

```bash
php artisan test
php artisan test --filter=WorkflowTest
composer test
```

*   تغطي: `Api/{Auth,Visibility,Authorization,Workflow,Rejection,Resend,Attachment}` + `Web/{Auth,Note}`
*   تستخدم `RefreshDatabase` + `:memory:` SQLite

---

## ☁️ النشر — InfinityFree / استضافة مشتركة

*   **PHP:** فعّل `8.3`
*   **Document Root:** `public`
*   **Env:** انسخ `.env.example` → `.env` + عيّن `APP_KEY` + `DB_*` + `JWT_SECRET`
*   **Migrations:** `php artisan migrate --force`
*   **Storage:** تأكد من صلاحيات `storage/` — لا تستخدم `storage:link` للمجلد الخاص — تأكد أن `storage/app/private` غير مكشوف
*   **Cache (للأداء):**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
*   **بدون Queue/Redis/WebSocket** — الملفات محلية فقط

---

## 📁 هيكل المجلدات

```
app/Http/Controllers/{Api/{Auth,Note}Controller, Web/{Auth,Note,Profile,Notification}Controller}
app/Http/Middleware/Api/AuthenticateApi.php
app/Services/{JwtService, NoteService}.php
app/Policies/NotePolicy.php
app/Models/{User, Note, Attachment}.php
app/Support/SyrianPhone.php
config/{jwt, attachments, filesystems}.php
database/{migrations,factories,seeders}
resources/views/{layouts/app, auth/login, notes/{index,my,create,edit}, profile/*}
routes/{web,api}.php
public/pwa/{manifest.json, sw.js, index.html, js/*, css/*, icons/*}
tests/Feature/{Api/*, Web/*}
docs/{SYSTEM,ARCHITECTURE,DATABASE,API,WORKFLOW,DEVELOPMENT}.md
```

---

## 🗺 خارطة الطريق — تطبيق Flutter

الـ API جاهز للاستهلاك عبر `Authorization: Bearer <token>` مع نفس قواعد `NoteService/NotePolicy` + فلترة `status/date/floor_number/camera_number` + تحميل مرفقات `?token=` للـ PWA/Flutter.

---

## 🤝 المساهمة

*   اقرأ `docs/*.md` قبل أي تعديل
*   حافظ على `NoteService/NotePolicy` كمصدر وحيد للمنطق
*   أي `env()` جديد يجب نقله إلى `config/*.php`
*   الملفات الخاصة دائماً عبر قرص `private` مع فحص `finfo` و `UUID`
*   شغّل `php artisan test` قبل الـ push

---

## 📄 الترخيص

`MIT` — مشروع داخلي لوزارة الإعلام.
