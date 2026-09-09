# نظام ملاحظات كاميرات المراقبة — جهة حكومية

> **إعلان — وثائق A4 بمعايير النشر الاحترافي**
> لم يعد إخراج التقارير مجرد طباعة صور. النظام الآن يبني **صفحة A4 تحريرية متكاملة** — صور ووصف وهيكلة بصرية — كما يصممها مصمم نشر محترف، لا كشبكة مربعات آلية.

> **نظام داخلي** لتسجيل ومتابعة ملاحظات مراقبة الكاميرات — واجهة ويب عربية RTL + واجهة برمجة تطبيقات REST لتطبيق Flutter + تطبيق PWA

> **قبل تعديل المشروع اقرأ مجلد `/docs` أولاً:** `SYSTEM.md` · `ARCHITECTURE.md` · `DATABASE.md` · `API.md` · `WORKFLOW.md` · `DEVELOPMENT.md`

---

## 📌 نظرة عامة

يسمح النظام للمراقبين بتسجيل ملاحظات ميدانية مرتبطة بـ **الطابق + الكاميرا + تاريخ/وقت الملاحظة + وصف + مرفقات (صور/فيديو/صوت)** ضمن دورة حياة محكمة:

```
مسودة (draft) → قيد المراجعة (pending) → مقبولة (accepted) | مرفوضة (rejected) → إعادة إرسال → pending
```

*   **الويب:** Blade + Tailwind + واجهة RTL قابلة للتثبيت كـ PWA
*   **API:** نفس المنطق عبر `NoteService` + `NotePolicy` مع مصادقة JWT مخصصة
*   **التخزين:** سحابي `Cloudinary` عبر `Storage::disk('cloudinary')` — القرص الافتراضي `FILESYSTEM_DISK=cloudinary` — روابط `https` مباشرة

---

## 🖨️ إعلان — نظام الطباعة الاحترافي الجديد

### لماذا الطباعة مختلفة الآن؟

الطباعة التقليدية تضع الصور في شبكة ثابتة `50/50` أو `2×2` ثم تلصق الوصف أسفلها. النتيجة: فراغات ضخمة، صور مقصوصة، وصف منفصل بصريًا، ووثيقة تبدو مولدة آليًا.

**نظام RASD الجديد هو محرك تكوين بصري تحريري (Editorial Visual Composition Engine v4.2)** — يفكر كـ **مصمم مطبوعات**، لا كـ خوارزمية تعبئة مستطيلات.

#### المحرك — مرحلتان

```
الصور + الوصف + A4
        ↓
[1] توليد تكوينات تحريرية ذات معنى (Hero, Stack, Mosaic)
        ↓
[2] تحسين هندسي مستمر (نسب 0.02mm + Gap 3mm + وزن بصري)
        ↓
وثيقة A4 واحدة متكاملة
```

**الوصف ليس صندوقًا يُضاف بعد الصور.** يُحسب أولاً `estimateDescriptionMetrics()` (خط 8.2pt / ارتفاع سطر 1.45 / حشوة 3.5mm / عدد الأسطر الحقيقي) ثم `imageAreaH = innerH - descH - gap` — الصور والوصف وحدة واحدة.

#### ماذا ترى في كل حالة؟

| العدد | التكوين التحريري | كيف يوزع المساحة | الميزة |
|------|-----------------|------------------|--------|
| **N=1** | `HERO-FILL` | صورة واحدة تملأ `innerW × imageAreaH` كاملة بأكبر تغطية `coverage >0.70` | صورة تقرير مهيمنة، لا فراغ مهدور |
| **N=2** | `FULL-WIDTH VERTICAL STACK` | صورتان فوق بعضهما بعرض كامل `198mm`، ارتفاع ديناميكي `35/65 → 65/35` حسب `Aspect Ratio + الدقة + الوزن البصري + مساحة الوصف` (مثال `56/44` للوزن المختلف، `50/50` للمتشابه) — ممنوع `side-by-side` أو `Grid` | صفحة تقرير رسمية متماسكة، لا مربعان متساويان قسرًا |
| **N=3** | `HERO + STACKED PAIR` | `Hero 62-68% عرض (افتراضي 0.64)` يسارًا + عمود ثانوي `32-38%` يضم صورتين مكدستين عموديًا `gap 3mm` — بطل واضح + ثانويتان داعمتان | هرمية بصرية واضحة، لا `3 أعمدة` ولا `3 cards` |
| **N=4** | `HERO + THREE SUPPORT` | `Hero 58-68% عرض (افتراضي 0.64)` يسارًا + 3 صور مكدسة يمينًا `secH=(area.h-2*gap)/3` مع حد أدنى `32mm` لكل صورة — بديل وحيد `Hero Top + 2 +1` عند الضرورة فقط | 4 صور لا تصبح 4 وحدات متساوية `2×2` — القارئ يرى `معلومة أساسية + 3 معلومات داعمة` فورًا |
| **N=5** | `HERO + 4 GRID` | Hero علوي `30-40%` ارتفاع + 4 صور `2×2` أسفله | تغطية `0.91` مع الحفاظ على القراءة |

#### ضمانات بصرية

* **100% بدون Crop** — `object-fit: fill` مع كلفة `aspectCost = log(tr/sr)²` — تشويه صغير رخيص، كبير مكلف جدًا
* **استغلال كامل العرض** — `x=margin, width=innerW` — ممنوع `198→180mm` من optimizer
* **لا تدوير إنقاذي** — `rotation=0` لـ N=2/3/4 — لا تقلب الصورة 90° لمجرد تحسين رقم
* **Gap موحد 3mm** — يشعر أن الصور قطعة واحدة، لا عناصر منفصلة
* **حواف متراصفة** — `Hero left edge = Secondary edge = Description edge = Page edge`
* **RTL كامل** — `html dir="rtl" lang="ar"` + `body direction:rtl` + `description direction:rtl; text-align:right; unicode-bidi:plaintext` — يحافظ على `Camera 12` و `10:35 PM` و `192.168.1.10` دون انقلاب، ويظهر صحيحًا في **Windows Print Preview** (المستند المعزول `window.open` كان `LTR` سابقًا)
* **هندسة واحدة مصدر الحقيقة** — `Engine → layout {x,y,width,height} mm → DOM absolute mm` — لا يعيد CSS تحديد المكان
* **نافذة طباعة معزولة** — `#printable-a4-doc` مصدر وحيد، `A4 210×297` ثابت `overflow:visible`، لا `height:auto` ولا `display:flex/grid` ولا `aspect-ratio:4/3` — نفس الهندسة في `Website Preview = Browser Preview = Windows Preview = PDF`

#### مثال حي — N=2

```
قبل:  [ IMG 1 | IMG 2 ] + صندوق وصف منفصل
بعد:  ┌─────────────────────┐
      │      IMAGE 1        │  ← ارتفاع 56% حسب الوزن
      ├─────────────────────┤
      │      IMAGE 2        │  ← ارتفاع 44%
      ├─────────────────────┤
      │ DESCRIPTION         │  ← محسوب من البداية، مندمج
      └─────────────────────┘
```

وثيقة N=2, N=3, N=4 الآن تبدو **صفحة تقرير صممها إنسان**، لا شبكة.

> **الإصدار الحالي:** `EDITORIAL-v4.2-N3-N4-2026-09-06` — يُعرض في Console و `window.__PRINT_LAYOUT_ENGINE_VERSION__`

---

## ✨ المميزات الرئيسية

| الفئة | المميزات |
|------|----------|
| **الملاحظات** | إنشاء مسودات، تعديل، حذف المسودات فقط، إرسال، قبول/رفض، إعادة إرسال المرفوضة، `observed_end_at` (نهاية الملاحظة)، عدادات وفلترة |
| **المرفقات** | رفع حتى **50 ملف/ملاحظة** — صور `jpg/png/webp` حتى 5MB، فيديو `mp4/webm/mov/avi/3gp/mkv/m4v` حتى 30MB، صوت `mp3/wav/ogg/m4a/aac/wma/flac/opus` حتى 10MB — أسماء UUID آمنة، فحص `finfo` + امتداد + رفض الامتدادات الخطرة ومزدوجة الامتداد |
| **التسجيل الصوتي** | زر `تسجيل صوتي` في `create/edit` عبر `MediaRecorder` — صوت في ذاكرة المتصفح فقط `Blob` + معاينة `audio` + `URL.createObjectURL/revoke` — لا localStorage/IndexedDB — يُرسل مع `FormData` كـ `attachments[]` |
| **الكاميرا المباشرة** | `getUserMedia` (HTTPS/localhost) + fallback `<input capture>` للـ HTTP — صورة/فيديو بدون حفظ محلي |
| **المشاركة** | `navigator.share({text, files})` مع fallback `wa.me` + نسخ — محترم لـ Secure Context |
| **المستخدمون** | ملف شخصي + avatar، رقم جوال سوري موحّد `SyrianPhone`، سجل إشعارات، ترتيب |
| **PWA** | `public/pwa/` — `manifest.json` + `sw.js` + أوفلاين محدود + تثبيت standalone RTL |
| **الأداء** | `config:cache` + `route:cache` + `view:cache`، تحميل Tailwind مؤجل، خط Cairo async، `preflight: false` |
| **الأمان** | حماية mass-assignment، تخزين سحابي `Cloudinary` عبر `Storage::disk('cloudinary')`، مصادقة API بتوكن عبر Header أو `?token=` للصور |

---

## 🧱 الـ Stack التقني

*   **Backend:** `Laravel 13` / `PHP 8.3+`
*   **Frontend Web:** `Blade` + `Tailwind CDN (defer)` + `Cairo` + ألوان sage `#1f6f4a` + خلفية دافئة `#f5f3ef` + dark mode
*   **API:** REST JSON — `Bearer JWT (HMAC-SHA256)` — انتهاء `60 دقيقة`
*   **DB:** `MySQL 5.7+` / `SQLite` (اختبارات `:memory:`) — Eloquent
*   **التخزين:** `Cloudinary` عبر `codebar-ag/laravel-flysystem-cloudinary` — القرص الافتراضي `cloudinary` — مجلد `CLOUDINARY_FOLDER=rasd` — توصيل `https` (`CLOUDINARY_SECURE_URL=true`)
*   **Auth:** Web `session + CSRF` — API `JwtService + Cache blacklist`
*   **Build:** `Vite` (اختياري)، `Composer scripts: setup/dev/test`
*   **Tests:** `PHPUnit 12` — `63` اختبار
*   **Print Engine:** `public/pwa/js/print-layout-engine.js` — Editorial v4.2 — `210×297mm` — `mm` مطلق

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
*   **Config:** `config/jwt.php` + `config/attachments.php` + `config/filesystems.php` قرص `cloudinary` (الافتراضي) + `config/flysystem-cloudinary.php` (`folder` / `secure_url`)

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
`id` | `note_id` FK | `file_path` varchar (مسار Cloudinary `public_id`: `notes/{id}/{UUID}` بدون امتداد) | `original_name` varchar | `mime_type` varchar | `file_size` bigint

**العلاقات:** `User hasMany notes / processedNotes` · `Note belongsTo owner/processor` · `Note hasMany attachments` · `Attachment belongsTo note`

---

## 📎 المرفقات والتسجيل الصوتي (Cloudinary)

*   **التخزين:** `Cloudinary` حصرًا عبر `Storage::disk('cloudinary')` — لا يوجد تخزين محلي للمرفقات
*   **المسار (`file_path` = `public_id`):** الملاحظات `notes/{note_id}/{UUID}` **بدون امتداد** (يحدد Cloudinary الصيغة تلقائيًا `resource_type=auto` لتجنب `cat.jpg.jpg`) — الصور الشخصية `avatars/{UUID}.{ext}`
*   **البيانات المحفوظة:** `attachments(file_path, original_name, mime_type, file_size)` — الأصلي للعرض فقط، والامتداد الآمن يُستنتج من `finfo` وليس من امتداد العميل
*   **الأسماء:** `UUID` آمن — فحص `finfo` + امتداد + رفض الامتدادات الخطرة ومزدوجة الامتداد (`php/phtml/phar/html/js/exe/sh/bat...` + `test.php.jpg`)
*   **التنزيل/العرض (stream من Cloudinary):**
    *   `GET /attachments/{id}/view` (stream inline) — أي مستخدم يملك حق `view`
    *   `GET /attachments/{id}/download` (attachment) — **كاتب التقارير فقط**
    *   `GET /api/attachments/{id}` — API بنفس القواعد
*   **التسجيل الصوتي:** `MediaRecorder` مع اختيار `audio/webm;codecs=opus → audio/webm → audio/ogg...` — `Blob` في RAM + `URL.createObjectURL` للمعاينة + تنظيف `revokeObjectURL` + `stream.getTracks().stop()` — لا يُحفظ على الجهاز

---

## 🖨️ تفاصيل الطباعة للمطورين

*   **المحرك:** `public/pwa/js/print-layout-engine.js` — `PrintLayoutEngine.layout(images, description, paper, opts)` → `{paper, images:[{x,y,width,height,rotation}], description, metrics}`
*   **الوحدات:** `mm` مطلق، `EPS 1e-7` للحدود المشتركة، `210×297` مع هوامش `6mm`
*   **الوصف:** `estimateDescriptionMetrics()` يحسب الارتفاع الحقيقي ثم `descH = min(height, innerH*0.35-0.40)` قبل توزيع الصور — لا `append` منفصل
*   **الطباعة:** `resources/views/notes/partials/print_modal.blade.php` — معاينة `a4-preview-sheet scale(0.92)` + طباعة معزولة `window.open` بـ `html dir="rtl"` — `#printable-a4-doc` مصدر وحيد، `position:absolute left/top/width/height mm`، `overflow:visible`, `display:block`
*   **RTL:** `unicode-bidi: plaintext` على وصف الطباعة يحافظ على `Camera 12` و `192.168.1.10` دون انقلاب
*   **الاختبار:** `node public/pwa/js/print-layout-engine.js` → `17/17 passed` — يختبر `N=1..5` + `extreme` + `RTL`

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
| GET/PUT | `/profile`, `/profile/edit` | `profile.*` | الملف الشخصي + avatar على `Cloudinary` مجلد `avatars/` (الرابط عبر `avatar_url`) |
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

FILESYSTEM_DISK=cloudinary
CLOUDINARY_CLOUD_NAME=your-cloud-name
CLOUDINARY_API_KEY=your-api-key
CLOUDINARY_API_SECRET=your-api-secret
CLOUDINARY_FOLDER=rasd
CLOUDINARY_SECURE_URL=true
# اختياري: CLOUDINARY_UPLOAD_PRESET=

MAX_IMAGE_SIZE=5120        # KB
MAX_VIDEO_SIZE=30720       # KB
MAX_ATTACHMENTS_PER_NOTE=50
```

> تُقرأ عبر `config('jwt.secret')` و `config('attachments.*')` و `config/filesystems.php` (قرص `cloudinary`) وليس `env()` مباشرة (آمن مع `config:cache`).
> الحزمة المستخدمة: `codebar-ag/laravel-flysystem-cloudinary` — الإعداد الإضافي في `config/flysystem-cloudinary.php` (`folder` / `secure_url`).

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

*   **PHP:** فعّل `8.3` + تأكد من `ext-curl` و `ext-fileinfo` (مطلوبة لرفع Cloudinary وفحص `finfo`)
*   **Document Root:** `public`
*   **Env:** انسخ `.env.example` → `.env` + عيّن `APP_KEY` + `DB_*` + `JWT_SECRET` + `FILESYSTEM_DISK=cloudinary` + `CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET/FOLDER/SECURE_URL`
*   **Migrations:** `php artisan migrate --force`
*   **Storage (Cloudinary):** المرفقات تُرفع لـ `notes/{id}/{UUID}` والصور الشخصية لـ `avatars/` وتُعرض عبر `Storage::disk('cloudinary')->response/download/url` بروابط `https` — لا حاجة لـ `storage:link` للمرفقات — فقط تأكد من صلاحيات `storage/` للكاش والجلسات واللوغ
*   **Cache (للأداء):**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
*   **بدون Queue/Redis/WebSocket** — الملفات على `Cloudinary` مباشرة (رفع/حذف متزامن عبر `Storage::disk('cloudinary')`)

---

## 📁 هيكل المجلدات

```
app/Http/Controllers/{Api/{Auth,Note}Controller, Web/{Auth,Note,Profile,Notification}Controller}
app/Http/Middleware/Api/AuthenticateApi.php
app/Services/{JwtService, NoteService}.php
app/Policies/NotePolicy.php
app/Models/{User, Note, Attachment}.php
app/Support/SyrianPhone.php
config/{jwt, attachments, filesystems, flysystem-cloudinary}.php
database/{migrations,factories,seeders}
resources/views/{layouts/app, auth/login, notes/{index,my,create,edit}, profile/*, notes/partials/print_modal.blade.php}
routes/{web,api}.php
public/pwa/{manifest.json, sw.js, index.html, js/print-layout-engine.js, js/*, css/*, icons/*}
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
*   المرفقات والصور دائماً عبر قرص `cloudinary` (`notes/{id}/{UUID}` بدون امتداد + `avatars/`) مع فحص `finfo` و `UUID` — و `file_path` هو `public_id` في Cloudinary
*   شغّل `php artisan test` قبل الـ push

---

## 📄 الترخيص

`MIT` — مشروع داخلي لجهة حكومية.
