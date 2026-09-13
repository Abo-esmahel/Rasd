<div align="center">

# 🦅 نظام RASD

### منظومة المتابعة الميدانية — وزارة الإعلام
**سجّل • تابع • شارك • اعتمد — كل ملاحظات كاميرات المراقبة في مكان واحد، لحظة بلحظة**

[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php)](https://php.net)
[![PWA](https://img.shields.io/badge/PWA-Ready-1f6f4a?style=flat-square)](./public/pwa/manifest.json)
[![RTL](https://img.shields.io/badge/RTL-عربي_كامل-0e6a38?style=flat-square)]()
[![Bilingual](https://img.shields.io/badge/i18n-AR_EN-0c5d9e?style=flat-square)]()
[![License MIT](https://img.shields.io/badge/License-MIT-lightgrey?style=flat-square)]()

[لماذا RASD](#-لماذا-rasd) · [الملاحظات](#-الملاحظات-الميدانية) · [التقارير والذكاء](#-التقارير-اليومية-والذكاء-الاصطناعي) · [الترجمة](#-الترجمة-الذكية-ثنائية-اللغة) · [الصلاحيات](#-الصلاحيات-والأدوار) · [التصدير](#-التصدير-والطباعة) · [المعرض](#️-معرض-المرفقات-الموحد) · [سير العمل](#-سير-العمل) · [التثبيت](#-التشغيل-خلال-5-دقائق) · [التوثيق](#-التوثيق-الكامل)

</div>

---

## 💎 لماذا RASD؟

| التحدي اليومي | حل RASD |
|---------------|---------|
| ملاحظات مبعثرة (ورق/واتساب) تضيع ولا تُتابع | سجل موحد لكل ملاحظة: كاميرا + طابق + وقت + وصف + مرفقات |
| صور وفيديو متناثرة بلا مرجع | **🖼️ معرض موحد** يجمع كل المرفقات مع رابط أصلها |
| روابط مشاركة معقدة منتهية الصلاحية | روابط **دائمة ونظيفة** `/s/...` + صفحة عرض خاصة بمشغّل |
| تأخر الاعتماد وغموض الحالة | دورة حياة شفافة `مسودة ⇄ منشورة` + إشعارات لحظية |
| صياغة التقرير تستهلك ساعات | **توليد ذكي** + **اعتماد** بضغطة زر + وثيقة A4 رسمية |
| تقارير كصفحات ويب لا كوثائق | **محرك وثائق موحد**: المعاينة = الطباعة = PDF |
| أنظمة عربية ناقصة أو بلا ترجمة | **ثنائية كاملة AR/EN** + ترجمة ذكية للمحتوى نفسه |
| صلاحيات مبهمة وتسريب بيانات | **سياسات صارمة** على كل حقل وكل انتقال |
| لا يعمل على الجوال | **PWA قابل للتثبيت** + متجاوب + وضع داكن + LAN |

---

## ✨ خارطة الميزات الكاملة

### 📝 الملاحظات الميدانية

دورة كاملة: `مسودة → قيد المراجعة → مقبولة / مرفوضة → إعادة إرسال` مع تفاصيل دقيقة:

*   **حقول منظمة**: طابق + كاميرا + `observed_at` + `observed_end_at` + وصف — كلها مفهرسة (`floor_number`, `camera_number`, `observed_at`)
*   **تحديث حي صامت** عبر `Livewire\NotesList` دون إعادة تحميل، مع فلترة بالحالة والتاريخ والطابق والكاميرا والمراقب
*   **مرفقات آمنة**: صور/فيديو/صوت بأسماء UUID + فحص `finfo` الحقيقي + رفض الامتدادات الخطرة والمزدوجة + قرص `attachments` المحلي `storage/app/private/notes/{id}/` — بلا `storage:link` وبلا وصول مباشر
*   **كاميرا مباشرة** في صفحة الإنشاء: التقاط صورة / تسجيل فيديو / تسجيل صوتي مباشرة للمرفقات (تعمل على `https` و`localhost`، مع بديل fallback يعمل على `http` والشبكات المحلية)
*   **طباعة A4 لكل ملاحظة**: وثيقة رسمية بترويسة الوزارة + شعار النسر الـVector + الجدول الزمني + الوصف مع ترقيم صفحات `صفحة X من Y` وتاريخ طباعة — `resources/views/notes/partials/print_modal.blade.php`
*   **حفظ مرن**: تجاوز حد المرفقات أثناء الإنشاء يحفظ الملاحظة ويبلغ عن الفشل جزئياً (`attach_partial`) بدل ضياع كل شيء

### 📤 الإرسالات العامة — منفصلة كليًا

*   إنشاء مبسّط: وصف + اختيار كتّاب متعددين (`general_submission_report_writer` pivot) + مرفقات اختيارية
*   فلترة زمنية جاهزة: اليوم / أمس / 7 أيام / 30 يوم
*   **القبول يقلب الحالة فقط — الإرسالية لا تتحول لملاحظة أبدًا** (`notes.general_submission_id` أرشيفي للفصل العرضي فقط)
*   تنزيل محمي — الخدمة `GeneralSubmissionService` + `GeneralSubmissionPolicy`

### 🖼️ معرض المرفقات الموحد — [التفاصيل](./docs/GALLERY.md)

قلب المنظومة البصري: كل صور وفيديو وصوت **الملاحظات والإرسالات** في صفحة واحدة `/gallery`:

*   **بنية `unionAll`**: `Attachment` (مع `whereHas note: مرئية + ليست من إرسالية`) + `GeneralSubmissionAttachment` → تطبيع لمصفوفة موحدة `kind, mime, size, viewUrl, downloadUrl, parentUrl, camera, floor, ownerName` → `paginate(24)`
*   **فلترة قابلة للطي** (مخفية افتراضياً): `camera_number` · `floor_number` · `type=image/video/audio` · `date` · `sort=latest/oldest` — مع شارة عدّاد الفلاتر النشطة + قوائم منسدلة مدمجة من المصدرين
*   **3 أنماط عرض محفوظة في `localStorage`**: شبكة (`grid` بطاقات كبيرة) · مدمج (`compact` مصغرات كثيفة) · قائمة (`list` صفوف)
*   **شارة مصدر** على كل مرفق (ملاحظة خضراء / إرسال كهرماني) + رابط مباشر للأصل
*   **الصلاحيات**: المشاهدة للجميع المصادقين، **التنزيل لكتّاب التقارير فقط** (المراقب مشاهدة فقط — نفس حماية المسارات الأصلية)

### 🤖 التقارير اليومية والذكاء الاصطناعي — [التفاصيل](./docs/REPORTS.md)

تقرير واحد لكل يوم يُبنى من الملاحظات **المقبولة فقط**، عبر خط إنتاج صارم:

```
ملاحظات مقبولة (نفس اليوم حصراً على Asia/Damascus)
        ↓
التوليد الذكي — Gemini يصوغ FinalReportData فقط (JSON: ملاحظات + توصيات)
        ↓
مراجعة بشرية وتحرير في المحرر — لا شيء يُحفظ تلقائياً
        ↓
الاعتماد — تحقق + تطبيع + حفظ نسخة مرقّمة في report_revisions + توليد HTML
        ↓
وثيقة A4 رسمية واحدة: المعاينة = الطباعة = PDF = الصورة
```

*   **قاعدة اليوم الواحد محروسة من السيرفر**: أي ملاحظة من يوم آخر — ولو بفارق دقيقة — **مرفوضة** في `attach/detach/reorder/publish/generate` (لا حماية واجهة فقط — فحص `reportDay()` داخل `ReportService` بمعاملات ذرية)
*   **زر توليد ذكي**: يصوغ وقائع موجزة بصيغة الماضي، **ويقرر بنفسه هل تستدعي الحالة توصيات أصلاً** — الروتين الهادئ يُنتج توصيات فارغة بدل الحشو
*   **حراس جودة تلقائية** ترفض المخرجات المخترَقة: تسرب التوصيات للملاحظات، قوائم مرقمة داخل ملاحظة، صيغ أوامر، تكرار الوقائع، إخلاءات فارغة — مع رسالة عربية واضحة وإعادة توليد
*   **القالب الثابت**: `ReportService::composeContent()` يركّب `content` من `summary + recommendations` فقط — لا نص حر. التعبئة يدوية أو آلية تصب في حقلين منظمين، والنشر يعيد التركيب (فلا يُنشر جدول قديم)
*   **وثيقة المحرك الموحدة** (`ReportEngine` + `ReportHtmlRenderingService`): ترويسة رسمية + الرقم والتاريخ مرة واحدة + الملاحظات بترقيم عربي هادئ + التوصيات كقائمة حقيقية (تُخفى عند غيابها) + كتلة توقيع زاوية — **بلا أي أثر تقني**: لا أرقام نسخ، لا كثافة، لا طوابع
*   **كثافة ذكية**: تقدير أسطر الطباعة وشدّ التباعد والخط تلقائياً (1→7 ملاحظات ومزدحم) مع حد قراءة وفواصل صفحات سليمة
*   **النشر محروس**: مستحيل نشر تقرير بمعاينة قديمة — أي تغيير بعد الاعتماد يجعل النسخة `stale` ويمنع النشر حتى إعادة الاعتماد
*   **7 أوراق رسمية جاهزة**: `public/images/التقارير/screen-1.png … screen-7.png` — النظام يختار تلقائياً الورقة المطابقة لعدد الملاحظات (1–7) ويملأ حقولها بمعايرة بكسلات في `config/report_sheets.php` (رقم/تاريخ/مربعات الملاحظات/التوصيات/التوقيع). خارج النطاق تُستخدم الطباعة العامة بلا فقدان محتوى. المعاينة الحية على نفس الصورة ونفس المقاسات — ما تراه هو ما يُطبع
*   **أكواد أعطال دقيقة بالعربية**: `AI_DISABLED(403)` · `AI_AUTH(502)` · `AI_RATE_LIMITED(429+retry_after+عدّاد)` · `AI_UNAVAILABLE(503)` · `AI_BAD_RESPONSE(502)` · `AI_BUSY(409 قفل 120ث)` · `AI_VALIDATION(422)` — بطاقة حمراء + زر إعادة
*   **السياق المحلي فقط**: `storage/app/ai/report-context.txt` يُبنى من قاعدة البيانات فقط (قواعد + أسلوب + مقبولات + سجل تقارير + إحصاءات) بترتيب ثابت `observed_at → id` وكتابة ذرية؛ **لا يُرسل كاملاً لـ Gemini أبداً** — تُرسل شريحة يوم التقرير فقط (~6000 حرف). إعادة البناء تلقائياً عند أي تغيّر + أمر `reports:rebuild-ai-context` + حد صور `AI_MAX_IMAGES_PER_GENERATION=3` (صور فقط، فيديو/صوت وصف فقط، بلا تعرّف وجوه)

### 🌐 الترجمة الذكية ثنائية اللغة

النظام **ثنائي كامل** — ليس مجرد واجهة مترجمة، بل **محتوى ديناميكي مترجم** مع فصل صارم بين الأصل والعرض:

| الطبقة | المسؤول | المصدر |
|--------|---------|--------|
| واجهة ثابتة | `lang/ar/*.php` + `lang/en/*.php` | 800+ مفتاح `ui.php` + `report.php` + `notifications.php` + `validation.php` |
| لغة المصدر | `SourceLanguage::detect()` — كشف حتمي بلا AI: عربي عند وجود `[\x{0600}-\x{06FF}]` وإلا إنجليزي/محايد | النص نفسه |
| ترجمة الملاحظات الذكية | Gemini في الخلفية فقط | `ContentTranslation` المحفوظة |
| ترجمة الصفحة الديناميكية | دفعة منظمة + Cache | `DynamicTranslationController::translatePage` |

*   **تبديل اللغة فوري** `POST /locale` (`ar ↔ en`): يحفظ في `cookie rasd_locale + session`، يعرض **النسخة المحفوظة** بتلك اللغة — **الأصل محفوظ ولا يُعاد ترجمته هنا أبداً**
*   **Smart Note Translation** (عرض فقط، بلا مصطلحات تقنية):
    *   عند إنشاء/تعديل ملاحظة — تُحفظ بلغتك الحالية كما هي، وتُجهَّز النسخة الأخرى **تلقائياً في الخلفية** عبر `TranslationWarmObserver` → `WarmTranslationProjection` (Job) → `GeminiTranslationProvider` → جدول `content_translations` (`translatable_type/id/field/locale/source_hash/content/status`)
    *   بطاقة حالة غير تقنية: `جارٍ تجهيز الترجمة…` · `الترجمة جاهزة` · `قيد التحديث بعد التعديل…` · `تعذّر التجهيز — إعادة المحاولة`
    *   `GET /notes/{note}/translation-status` (قراءة مخزن فقط — ZERO Gemini متزامن) + `POST /notes/{note}/translation-retry` + `POST /translations/retry` (جدولة Job فقط، `throttle:10,1`)
    *   النص المحايد (أرقام/رموز) لا يُترجم أبداً
*   **Dynamic Translation للصفحة**: `POST /translations/page` يأخذ مصفوفة `{type, id, fields}` ويرجع `{translations, meta: {localized, source}}` من المخزن فقط — بلا استدعاء Gemini أثناء التصفح — مع ترجمة دُفعات `translateBatch()` وكاش
*   **PWA والطباعة تتبع اللغة الحالية**: `GET /pwa/manifest.json` يرجع `name/short_name/dir/lang` حسب `rasd_locale`، ووثائق A4 والمعاينات والأوراق الرسمية تُعرض باللغة المختارة عبر `LocalizedPresenter` و`reports.localized`
*   **الأمان**: Gemini مفتاحه على الخادم فقط، لا يظهر في API/Logs/DB

### 🔐 الصلاحيات والأدوار — [التفاصيل](./docs/SYSTEM.md)

| الدور | ينشئ ملاحظة | يرى مسودات الآخرين | يقبل/يرفض | ينزّل المرفقات | يدير التقارير |
|-------|:-----------:|:-----------------:|:---------:|:--------------:|:-------------:|
| **مُراقب ميداني** `monitor` | ✅ | ❌ | ❌ | مشاهدة فقط | يرى المنشور المرئي فقط (بلا مسودة AI) |
| **كاتب تقارير** `report_writer` | ✅* | ❌ | ✅ | ✅ حصرياً | ينشئ/يعدّل/ينشر/يسحب تقاريره فقط — يرى كل التقارير |

> *`NotePolicy::create` يسمح للكاتب بالإنشاء أيضاً — لكن الواجهة توجهه للتقارير.

**سياسات مركزية بلا منطق مكرر** (تُطبق في Web + API):

*   `NotePolicy`: `create/viewAny/view/update/delete/send/accept/reject/resend/addAttachment/removeAttachment` — المسودة لا يراها إلا مالكها؛ المقبولة لا يعدّلها إلا معالجها (`processed_by`)
*   `GeneralSubmissionPolicy`: رؤية للمرسِل + الكتّاب المعنيين فقط
*   `ReportPolicy`: `create/generate/publish/unpublish/update/delete` لكاتبه فقط؛ `view` للكاتب كلها، وللمراقب المنشور المرئي فقط (`published + visible_to_monitors`) بلا `ai_draft_content` وبلا `report_revisions`
*   **الرؤية كجدول**:

| حالة الملاحظة | مالك | مراقب آخر | كاتب |
|--------------|:----:|:---------:|:----:|
| مسودة | ✅ | ❌ | ❌ |
| قيد المراجعة/مرفوضة/مقبولة | ✅ | ✅ | ✅ |

| تقرير | مراقب | كاتب |
|-------|-------|------|
| مسودة | ❌ | ✅ (الكل) |
| منشور مرئي | ✅ (بلا AI/سجل) | ✅ |
| منشور مخفي | ❌ | ✅ |

*   **أمان إضافي**: JWT للـAPI (HMAC-SHA256 + blacklist بالكاش) + CSRF للويب + `finfo` للمرفقات + `throttle` على تسجيل الدخول (5/دقيقة) والتوليد (5/دقيقة) والترجمة (30/دقيقة) + `SecurityHeaders` middleware

### 📤 التصدير والطباعة

كل وثيقة رسمية تُصدَّر من **نفس المحرك** — لا تضارب بين المعاينة والطباعة والـPDF والصورة:

| المسار | الوصف | الصلاحية |
|--------|-------|----------|
| `GET /reports/{id}/preview` | معاينة **Inline** (View) داخل تبويب التقارير — بلا تنزيل | مراقب: المنشور المرئي فقط · كاتب: الكل |
| `GET /reports/{id}/print` | طباعة A4 منفصلة (قالب طباعة مستقل) | نفس رؤية التقرير |
| `GET /reports/{id}/export-pdf` | **تصدير PDF** — نفس HTML المحرك مع ترويسة/تذييل | **كتّاب فقط (403 للمراقب حتى بالرابط المباشر)** |
| `GET /reports/{id}/export-image` | **تصدير كصورة PNG** عبر GD محلي (بلا AI) — مع ترميز/تنزيل + `long-press` احتياطي | كتّاب فقط |
| `GET /reports/{id}/localized` | **الوثيقة باللغة الحالية** (JSON fragment) — نفس المحرك، عرض فقط بلا حفظ — للتبديل بدون Reload | نفس رؤية التقرير |
| `POST /reports/{id}/render` `throttle:3,10` | توليد HTML للمعاينة المرقمة `صفحة X من Y` مع ملاءمة/tكبير/تصغير | كاتب |
| `POST /reports/{id}/fill-sheet` | ملء الورقة الرسمية (1–7) بالبيانات + حفظ `report_sheet_renders` | كاتب |
| `GET /reports/{id}/filled-sheet` | صورة الورقة المملوءة | كاتب |
| `GET /reports/{id}/sheet-html` | HTML الورقة للمعاينة | كاتب |
| `GET /report-sheets/{n}` | صور القوالب الخام `screen-n.png` | مصادق |
| `GET /pwa/manifest.json` | Manifest متجاوب مع اللغة | عام |

*   **المعاينة المرقمة الذكية**: المحتوى يُوزع على صفحات A4 حقيقية (210×297مم) مع ترقيم ومنع قص سطر بين صفحتين — ما تراه هو ما يُطبع حرفياً (مع بديل ثابت بلا JS)
*   **كثافة طباعة**: المحرك يقدّر أسطر الطباعة ويشدّ التباعد والخط تلقائياً — من ملاحظة واحدة إلى التقارير المزدحمة متعددة الصفحات

### 💬 مشاركة واتساب حقيقية

*   مشاركة لمنتقي جهات الاتصال (لا لشخص مفروض) + نسخ يعمل حتى على `http`
*   روابط دائمة قصيرة `/s/attachments/{id}` و`/s/submission-attachments/{id}` تتكيف مع `SHARE_URL` أو عنوان سيرفرك تلقائياً (`config/app.php: share_url`)
*   صفحة عرض خاصة بمشغّل صورة/فيديو/صوت — **الدخول إجباري**، والتنزيل للكتّاب فقط (`/s/.../file`)

### 🔔 إشعارات لا تفوّت شيئًا + PWA

*   **مركز إشعارات** بدرج جانبي + عدّاد + إخفاء ذكي عند الفراغ — `GET /notifications` + `unread-count` + `mark-read` + `markOneRead`
*   **تحديث لحظي مع احتياطي تلقائي**: `GET /notifications/stream` (SSE) + `GET /notifications/feed` (polling fallback) + `BroadcastDatabaseNotification` Listener
*   **Fanout Jobs**: `FanoutNoteNotifications` / `FanoutDispatchNotifications` / `FanoutReportPublished` عند قبول/رفض/نشر
*   **Web Push** حقيقي: `minishlink/web-push` + `PushSubscription` + `WebPushService` — `POST /push/subscribe` · `DELETE /push/unsubscribe` · `GET /push/vapid-public-key` (VAPID) — يعمل حتى والمتصفح مغلق
*   **تفضيلات وصوت**: `GET/PUT /notifications/preferences` + `soundManager.js` + `config.js` — مستوى صوت + توست فوري + كتم يحفظ تلقائياً
*   **PWA قابل للتثبيت**: `public/manifest.json` + `public/sw.js` + `public/offline.html` + `public/pwa/icons/*` — `start_url="/"`, `display:"standalone"`, `shortcuts: [ملاحظة جديدة, الملاحظات]` — الـ manifest يتعرّف على لغتك (`ar` RTL / `en` LTR) + Service Worker يخزن الأصول للعمل على LAN بلا إنترنت

### 👤 الملف الشخصي والترتيب

*   **الملف**: `GET /profile` + `PUT /profile` — اسم + `phone` سوري موحّد (`SyrianPhone` helper) + `personal_number` + كلمة مرور — مع `avatar_path` دائري مقصوص كواتساب (سحب/تكبير داخل دائرة، `10MB` حد، معاينة قبل الحفظ)
*   **عرض ملفات الآخرين**: `GET /profile/{id}` عام للجميع + إحصاءات (إجمالي/مقبولة/مرفوضة/نسبة قبول) + `الترتيب`
*   **الترتيب**: `GET /ranking` — المراقبون مرتبون بالأكثر قبولاً أولاً (مقبولات فقط — `ranking_calc`)
*   **اللغة كبطاقة داخل الملف**: تغيير فوري مع تلميح `الأصل محفوظ ولا يُعاد ترجمته هنا`

### 🧭 إعادة التوجيه الذكي

*   بوابة `GET /r?to=...` — رسالة جميلة + عدّاد + انتقال تلقائي لوجهة **داخلية فقط** (حماية open-redirect) — `SmartRedirectController`
*   أي مسار غير موجود (`Route::fallback`) يذهب لصفحة 404 جميلة بنفس البوابة بدل صفحة فارغة
*   صفحات صحة: `GET /health` + `GET /health/nojs` + `GET /offline.html`

---

## 👥 مصمم لأدوار واضحة

| مراقب ميداني | كاتب تقارير |
|--------------|-------------|
| يسجّل ملاحظاته وإرسالاته من الجوال | يعتمد/يرفض مع متابعة شاملة |
| يشاهد كل المرفقات (تنزيل ممنوع) | **ينزّل** المرفقات حصريًا |
| يستقبل إشعارات القبول/الرفض والنشر | تصله الإرسالات الموجهة إليه فقط |
| يرى التقارير المنشورة المرئية له | ينشئ التقارير ويدير تقاريره (توليد، اعتماد، نشر، سحب) + يصدّر PDF/صورة |

---

## 🔄 سير العمل

```
ملاحظة:    مسودة ─→ قيد المراجعة ─→ مقبولة ✅ | مرفوضة ─→ إعادة إرسال → قيد المراجعة
إرسالية:   وصف + كتّاب ─→ قيد المراجعة ─→ مقبولة ✅ | مرفوضة (تبقى إرسالية — لا تتحول لملاحظة)
تقرير:     مسودة ⇄ منشورة (النشر يشترط: ملاحظة مقبولة واحدة من نفس اليوم + محتوى معتمد غير قديم)
لغة:       نص أصلي (Source) ─→ Job خلفي ─→ ترجمة محفوظة (Projection) ─→ عرض حسب Locale
```

| من | إلى | الإجراء | المسموح |
|----|-----|---------|---------|
| `draft` | `pending` | إرسال | مالك الملاحظة فقط |
| `pending` | `accepted` | قبول | كاتب تقارير فقط |
| `pending` | `rejected` | رفض (سبب إجباري) | كاتب تقارير فقط |
| `rejected` | `pending` | إعادة إرسال | مالك فقط |
| `accepted` | — | ثابتة | لا تعديل إلا للمعالج |
| `draft` | `published` | نشر تقرير | كاتب مالك + محتوى معتمد + ملاحظة |
| `published` | `draft` | سحب نشر | كاتب مالك |

التفاصيل: [`WORKFLOW.md`](./docs/WORKFLOW.md) · الصلاحيات [`SYSTEM.md`](./docs/SYSTEM.md) · التقارير [`REPORTS.md`](./docs/REPORTS.md)

---

## 🧱 الثقة التقنية والتشغيل

*   **Laravel 13 + PHP 8.4 + Livewire 4 + Vite 8 + Tailwind 4** — خدمات مفصولة (`NoteService`، `GeneralSubmissionService`، `ReportService`، `ReportEngine`، `ReportHtmlRenderingService`، `TranslationService`، `LocalizedPresenter`) وسياسات تفويض صارمة بلا منطق مكرر
*   **فصل صارم**: المحتوى الرسمي في الوثيقة، وبيانات المحرك (`generation_id`، الكثافة، القوالب، `content_translations`) في قاعدة البيانات والسجلات فقط
*   **أمان**: JWT للـAPI + CSRF للويب + فحص `finfo` للمرفقات + رفض الامتدادات الخطرة + مفاتيح Gemini/VAPID على الخادم فقط + `SecurityHeaders` + `throttle` دقيق
*   **أداء**: بناء Vite + تخزين محلي سريع + `unionAll` + `paginate(24)` + تخزين مؤقت للترجمات + كتابة سياق ذرية
*   **يعمل على شبكتك**: LAN + PWA — بلا سحابة وبلا اشتراكات (Gemini اختياري عبر الخطة المجانية)
*   **تشغيلياً**: كل توليد/ترجمة موثّق في سجل النظام (`[HTML-RENDER]`/`[REPORT]`/`[TRANSLATION]`)، ونسخ الاعتماد مرقّمة لكل تقرير كسجل تدقيق
*   **هوية بصرية**: شعار نسر ذهبي **Vector خالص (SVG)** يتوسع بلا حدود + صفحة دخول مركزية فاخرة + عربي RTL كامل + وضع داكن

---

## 🗄️ قاعدة البيانات — [التفاصيل](./docs/DATABASE.md)

```
users ──< notes ──< attachments
  │           │
  │           └──< report_note >── reports ──< report_revisions
  │                                   └──< report_sheet_renders
  │
  ├──< general_submissions ──< general_submission_attachments
  │         └──< general_submission_report_writer >── users(report_writer)
  │
  ├──< content_translations (translatable_type/id + field + locale)
  ├──< notifications (database)
  ├──< push_subscriptions
  └──< notification_preferences
```

*   `users`: `username(unique)` + `role: monitor|report_writer` + `phone` + `avatar_path` + `personal_number`
*   `notes`: `user_id FK` + `floor_number` + `camera_number` + `observed_at/observed_end_at` + `status: draft|pending|accepted|rejected` + `general_submission_id` (أرشيفي) + فهارس على `status/observed_at/floor/camera/processed_by`
*   `attachments` / `general_submission_attachments`: `file_path(UUID)` + `mime_type` + `file_size` + `original_name`
*   `reports`: `author_id` + `title` + `content(مركّب)` + `summary/recommendations` + `ai_draft_content/ai_summary/ai_recommendations` + `generation_mode: ai|manual|hybrid` + `status: draft|published` + `visible_to_monitors` + `report_date` + `published_at`
*   `content_translations`: `translatable_type` + `translatable_id` + `field` + `locale(ar/en)` + `source_hash` + `content` + `status` — فريد مركب
*   فهارس إضافية: `reports(report_date/status/author_id/visible_to_monitors)` + `report_note(report_id,note_id unique)` + `content_translations(translatable+field+locale unique)`

---

## 🚀 التشغيل خلال 5 دقائق

### المتطلبات

`PHP 8.4+` · `Composer` · `MySQL 8+` أو `SQLite` · `Node.js 20+` (لبناء الواجهة)

### الخطوات

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed
npm install && npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

الدخول التجريبي (كلمة المرور `password` للجميع): `tariq` · `hadi` · `hamza` · `rami` (مراقبون) · `writer` (كاتب تقارير)

> للجوال على نفس الواي فاي + مشاركة واتساب: راجع [`DEVELOPMENT.md`](./docs/DEVELOPMENT.md) (جدار ناري + `SHARE_URL`)
>
> لتفعيل التوليد الذكي للتقارير والترجمة: مفتاح مجاني من [Google AI Studio](https://aistudio.google.com) ثم ضعه في `.env`:
>
> ```env
> AI_ENABLED=true
> GEMINI_API_KEY=ضع_مفتاحك_هنا
> GEMINI_MODEL=gemini-3.5-flash
> ```
>
> التفاصيل والأعطال في [`REPORTS.md`](./docs/REPORTS.md)

### المتغيرات البيئية المهمة

```ini
APP_TIMEZONE=Asia/Damascus      # يوم التقرير يُحسب عليها — ثبّتها ولا تغيّرها بعد التشغيل
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
SHARE_URL=                       # فارغ = ديناميكي من عنوان المتصفح
JWT_SECRET=مفتاح_64_حرف_عشوائي   # يرفض الإقلاع إن كان فارغاً أو <32
JWT_EXPIRY_MINUTES=60
MAX_IMAGE_SIZE=20480             # كيلوبايت (20M)
MAX_VIDEO_SIZE=102400            # 100M
MAX_AUDIO_SIZE=104857600         # 100M
MAX_ATTACHMENTS_PER_NOTE=10
AI_ENABLED=false
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.5-flash
GEMINI_TIMEOUT=60
GEMINI_MAX_OUTPUT_TOKENS=4096
AI_MAX_IMAGES_PER_GENERATION=3
# php.ini: upload_max_filesize=128M / post_max_size=128M / memory_limit=256M
```

---

## 🔌 واجهة برمجة التطبيقات — [التفاصيل](./docs/API.md)

```
POST   /api/login                          {username, password}  → {token, user}  (throttle:5,1)
POST   /api/logout                         Bearer
GET    /api/me                             Bearer
GET    /api/notes?status&date&floor&camera Bearer (مسودات الآخرين محجوبة)
POST   /api/notes                          {floor, camera, observed_at, description}
GET    /api/notes/{id}  PUT  DELETE
POST   /api/notes/{id}/send|accept|reject|resend
POST   /api/notes/{id}/attachments         multipart file
DELETE /api/notes/{id}/attachments/{att}
GET    /api/attachments/{id}  /view        Bearer

GET    /api/general-submissions            Bearer
POST   /api/general-submissions            {description, writer_ids[], attachments?}
GET    /api/general-submissions/{id}
POST   /api/general-submissions/{id}/submit|accept|reject

GET    /api/reports                        Bearer (مراقب: منشور مرئي فقط بلا AI)
POST   /api/reports                        {title, report_date, visible_to_monitors?}
GET    /api/reports/{id}  PUT  DELETE
POST   /api/reports/{id}/attach            {note_ids[]}      (مقبولة + نفس اليوم)
DELETE /api/reports/{id}/notes/{noteId}
POST   /api/reports/{id}/reorder           {ordered_ids[]}
POST   /api/reports/{id}/publish|unpublish
POST   /api/reports/{id}/generate          {regenerate?, include_images?} (throttle:5,1)
```

*   صيغة موحدة: `{success, message, data}` للنجاح و`{success:false, message, errors}` للخطأ — أكواد `200/201/204/401/403/404/422/429`
*   أخطاء Gemini مرمزة في `error`: `AI_DISABLED/403` · `AI_AUTH/502` · `AI_RATE_LIMITED/429(+retry_after)` · `AI_UNAVAILABLE/503` · `AI_BAD_RESPONSE/502` · `AI_BUSY/409`

**الويب (Blade + Livewire)** يشارك نفس الخدمات والسياسات:

```
GET  /notes  /my-notes  /gallery  /reports  /general-submissions  /ranking  /profile  /notifications
POST /notes/{id}/send|accept|reject|resend
GET  /attachments/{id}/view|download   (مصادق + تفويض)
GET  /s/attachments/{id}  /s/submission-attachments/{id}  (روابط دائمة مصادقة)
POST /locale  POST /translations/page  GET /notes/{id}/translation-status  POST /translations/retry
GET  /reports/{id}/preview|print|export-pdf|export-image|localized|sheet-html|filled-sheet
POST /reports/{id}/generate|generate-data|render|fill-sheet  (throttle)
GET  /pwa/manifest.json  /sw.js  /offline.html  +  /health  +  /r (توجيه ذكي)
```

---

## 🧪 الاختبارات — الوضع الحالي (Verified 2026-09-13)

> **تنبيه مصداقية:** مجلد `tests/` فارغ حالياً — كل اختبارات `tests/Feature/*` و`tests/Unit/*` أُزيلت في الالتزامات الأخيرة (`git log` — حذف `AttachmentTest`, `WorkflowTest`, إلخ). الأوامر التالية لا تعمل حالياً وستعيد `Test directory not found`.

```bash
php artisan test                              # حالياً: لا اختبارات — المجلد فارغ
# كان سابقاً: 155 اختباراً (دورات العمل، المرفقات، المعرض، التقارير 45…)
# php artisan test --filter=ReportLiveGeminiTest  # تكامل حي (RUN_LIVE_GEMINI_TEST=true)
```

*إن أُعيدت الاختبارات مستقبلاً، يجب أن تغطي:* دورات العمل، المرفقات (`finfo` + `UUID` + `max_per_note`), المعرض (`unionAll + paginate`), الصلاحيات (`NotePolicy/ReportPolicy`), الترجمة (`detect + content_translations + Warm`), التقارير (يوم واحد + `stale` + `12h` قفل + Gemini وهمي 401/429/500).

---

## 📚 التوثيق الكامل

| الملف | المحتوى |
|-------|---------|
| [`REPORTS.md`](./docs/REPORTS.md) | 🤖 التقارير والذكاء الاصطناعي — الإعداد والأمان والأخطاء والأوراق |
| [`GALLERY.md`](./docs/GALLERY.md) | 🖼️ المعرض الموحد — البنية والفلترة والعروض والصلاحيات |
| [`SYSTEM.md`](./docs/SYSTEM.md) | النطاق والأدوار والرؤية |
| [`ARCHITECTURE.md`](./docs/ARCHITECTURE.md) | الخدمات والسياسات والتخزين والترجمة |
| [`DATABASE.md`](./docs/DATABASE.md) | الجداول والعلاقات والفهارس |
| [`API.md`](./docs/API.md) | نقاط REST للملاحظات والإرسالات والتقارير |
| [`WORKFLOW.md`](./docs/WORKFLOW.md) | دورات الحياة والانتقالات |
| [`DEVELOPMENT.md`](./docs/DEVELOPMENT.md) | التثبيت والبيئة وهيكل المشروع والشبكة المحلية |

---

<div align="center">

**RASD — من الميدان إلى الاعتماد، بلا ورق وبلا ضياع.**

*ملاحظات موثقة · تقارير ذكية · ترجمة تلقائية · صلاحيات محكمة · تصدير رسمي · معرض موحد — كل شيء في مكان واحد.*

وزارة الإعلام · `MIT`

</div>
