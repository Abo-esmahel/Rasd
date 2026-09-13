# ARCHITECTURE.md — البنية المُحقّقة (Verified)

> **تاريخ التحقق:** 2026-09-13 — Laravel 13 + PHP 8.4 + Vite 8 + Tailwind 4
> كل ما يلي مُستنتج من الكود الفعلي (`app/Services/*`, `routes/*.php`, `config/*.php`) وليس من افتراضات.

## البنية العامة

### الويب
```
Blade + Livewire 4
  → Web Controllers (NoteController, ReportController, GalleryController…)
  → Services (NoteService, ReportService, GeneralSubmissionService, TranslationService, LocalizedPresenter)
  → Models (Note, GeneralSubmission, Report, ContentTranslation…)
  → MySQL/SQLite (private disk `attachments`)
```

### API
```
Flutter / أي عميل
  → Api Controllers (NoteController, ReportController, GeneralSubmissionController)
  → نفس Services و Policies تماماً — لا تكرار منطق
  → MySQL/SQLite
```

### مبدأ أساسي
الويب والـ API يشتركان نفس منطق العمل وقواعد التفويض. لا يوجد `env()` مباشر في الكود — كل شيء عبر `config()` (آمن مع `config:cache`).

## المكونات المحققة

### NoteService `app/Services/NoteService.php:70`

*   **نطاق زمني `normalizeObservedRange()`**:
     *   المتصفح يرسل `d+'T'+t` بلا offset (naive) — النظام يفسره حسب `config('app.timezone')`، ومع `UTC` يطبق `Asia/Damascus` كافتراضي لتفادي مستقبل كاذب (+03) (`$parseTz`).
     *   عبور منتصف الليل: `end < start` → `+1 day`.
     *   الحد الأقصى للمدة 12 ساعة (`diffInMinutes > 12*60` → رفض).
     *   لا تواريخ مستقبلية (هامش ساعة لانحراف الساعات): `start/end > now(parseTz)+1h` → رفض.
     *   جميع المقارنات تتم بنفس `$parseTz` لضمان اتساق `reportDay()`.
*   **مصدر واحد للحد `uploadFileMaxKb()`** `app/Services/NoteService.php:29`: يحسب `min(PHP upload_max_filesize, max(app_image, app_video, app_audio)/1024, 512M)` — الكنترولرز والـ API يستخدمونه وحده (لا حساب يدوي منفصل).
*   **إنشاء ذري `createNoteWithAttachments()`**:
    *   فحص نقل مبكر `client_files_count vs filesReceived` → `transport_loss` قبل فتح المعاملة.
    *   سقف مبكر `filesReceived > max_per_note (10)` قبل `DB::beginTransaction` حتى لا تُرفع ملفات عبثاً.
    *   داخل `DB::transaction`: `createDraft` → حلقة `storeSingleAttachment` → تحقق `filesReceived == attachmentsSaved` → تحقق وجود السطر والملف فعلياً في القرص — وإلا `AttachmentUploadException` مع `stage=verification`.
    *   `cleanupStoredFiles()` + `DB::rollBack()` عند أي فشل.
*   **تعديل `updateNoteWithAttachments()`**: يفحص `attachmentsBefore + filesReceived > max_per_note` مع رفض تجاوز الحد قبل أي كتابة.
*   **إبطال كاش مستهدف `flushNoteCaches()`**: بعد كل تغيير حالة يبطل فوراً `notes-counts:{user}:my/all` و`profile-stats:{user}` و`ranking-global-stats` لضمان دقة العدادات اللحظية.
*   **قوائم مرئية `getVisibleNotesQuery()`**: `with(['owner:id,name,avatar_path','processor:id,name'])->withCount('attachments')->whereNull(general_submission_id)->where(user_id=me OR status!=draft)` — بلا N+1.

### GeneralSubmissionService
*   `createDraft / submit / accept / reject` للإرسالات — **القبول يقلب الحالة فقط، لا ينشئ `Note` أبداً**.
*   `getVisibleSubmissions` للمرسِل + الكتّاب المعنيين فقط (pivot `general_submission_report_writer`).

### GalleryController `app/Http/Controllers/Web/GalleryController.php:1`
*   `unionAll` بين `attachments` (`whereHas note: مرئية + general_submission_id IS NULL`) و `general_submission_attachments` (`whereHas submission: مرئية`) بأعمدة متوافقة + `kind`.
*   تطبيع لمصفوفة موحدة ثم `paginate(24)`. تحميل آباء دفعة واحدة `whereIn` — راجع `GALLERY.md`.

### ReportService `app/Services/ReportService.php:1`
*   الدومين الثالث بنفس النمط: `createDraft / attachNotes / detachNote / reorderNotes / update / publish / unpublish / delete`.
*   قاعدة اليوم الواحد تُفحص في السيرفر عبر `reportDay()` على `Asia/Damascus` — لا يعتمد على الواجهة. كل العمليات الحرجة داخل `transactions` + قفل يمنع التوليد المزدوج (120 ثانية).

### ReportPolicy `app/Policies/ReportPolicy.php:1`
*   `create/generate/publish/unpublish/update/delete/export`: **كاتب التقارير لِتقاريره فقط** + مهلة تعديل 12 ساعة بعد النشر (بعدها 403 حتى للمالك — مطابقة للواقع).
*   `view`: الكاتب يرى الكل، المراقب يرى المنشور المرئي فقط (`published + visible_to_monitors`) بلا `ai_draft_content` وبلا `report_revisions`.

### ReportEngine `app/Services/Report/ReportEngine.php:460`
*   `observationDetails(report, count, locale)`: مدة ثنائية اللغة — `min/h min` للإنجليزية و`د/س` للعربية (`$isEn` switch).
*   `ordinal(n, locale)`: `1st/2nd/3rd/th` للإنجليزية و`الأولى…العاشرة` للعربية.
*   باقي المحرك: `distinctObservers`, `responsibles`, تطبيع الملاحظات/التوصيات — راجع `REPORTS.md`.

### ReportHtmlRenderingService `app/Services/ReportPreview/ReportHtmlRenderingService.php:1`
*   يبني `HTML` الرسمي من `Report + ReportSheetRender` مع `systemHash` للكشف عن `stale` — نفس الـ HTML للمعاينة والطباعة والـ PDF والصورة.

### منظومة الترجمة — Presentation Layer فقط `app/Services/Localization/*`
*   `SourceLanguage::detect()` — كشف حتمي بلا AI: عربي إن وجد `[0600-06FF]` وإلا إنجليزي/محايد.
*   `TranslationService` — طبقة عرض عالية الأداء:
     *   **ذاكرة طلب**: `shouldTranslateCache` و`detectCache` بمفتاح `xxh3` يمنع تكرار 8 regex لكل نص (`shouldTranslateCached`, `detectCached`, `flushRequestCache`).
     *   **دفعة DB واحدة** `resolveStoredMany` و`localizeMany`: تجميع `byType → whereIn(ids, fields, hashes)` باستعلام واحد — مع فهرسة `O(1) isset($pending[$rk])`.
     *   **Dedup حسب الحقل**: `dedupKey = type\0fieldBase\0hash(text)` — نفس النص بنفس الحقل يُرسل مرة واحدة للـ Provider.
     *   **WarmTranslationObserver** `app/Observers/TranslationWarmObserver.php:1`: عند `saved` يوزع الحقول حسب لغتها الفعلية — عربي→`en` وإنجليزي→`ar` ومحايد→عكس `uiLocale` — ويجدول `WarmTranslationProjection` لكل هدف على حدة، مع `resolveStoredMany` مسبق لإسقاط المحفوظ بنفس البصمة.
*   `LocalizedPresenter` يغلف `TranslationService::localizeMany` ويقدم `reportPayload` / `noteDescription` حسب `app()->getLocale()` — بلا Gemini متزامن في عرض الصفحة.
*   `DynamicTranslationController::translatePage` `throttle:30,1` — يقرأ المخزن فقط (stored-only) بلا استدعاء Gemini أثناء التصفح.

### منظومة Gemini — التقارير فقط
*   `ReportAiService` ينسق: تحقق → شريحة سياق → بناء طلب → `GeminiAiTextGenerator` → حفظ `ai_draft_content` (لا يمس `content`).
*   `ReportContextService` يبني `storage/app/ai/report-context.txt` من DB فقط + أمر `reports:rebuild-ai-context` — خاص خارج `public/`.
*   `GeminiAiTextGenerator` عبر `AiTextGeneratorInterface` — المفتاح والموديل من `config/ai.php` فقط (`gemini-3.5-flash` المستقر الحالي، بدائل `3.6/3.7/preview` — `2.5/2.0` متقاعد 404).

### NotePolicy `app/Policies/NotePolicy.php:1`
*   `create`: `monitor` أو `report_writer`.
*   `view`: مالك يرى كل شيء، غير المالك لا يرى المسودة.
*   `update/addAttachment/removeAttachment`: مالك فقط، والمرفوضة مقفلة دائماً، والمقبولة لا يعدلها إلا `processed_by`.
*   `delete/send/resend/accept/reject`: كما في `WORKFLOW.md` — فحص مزدوج `Policy + NoteService`.

### JWT Service
*   `HMAC-SHA256` + `blacklist via Cache` + `config/jwt.php: secret/expiry` — السر من `config('jwt.secret')` وليس `env()`.

### Config — القيم الفعلية
*   `config/attachments.php:1`: `max_image_size=20480 (20M), max_video_size=102400 (100M), max_per_note=10, max_per_submission=5, max_audio_size=100M`.
*   `config/ai.php:1`: `enabled, provider, max_images_per_generation=3, gemini[api_key, model=gemini-3.5-flash, timeout=60, max_output_tokens=4096], context_path, context_version=1.0`.
*   `config/app.php:1`: `share_url` — هوست روابط المشاركة (فارغ = ديناميكي من عنوان المتصفح).
*   `config/filesystems.php:1`: قرص `attachments` المحلي `storage/app/private/{notes/{id}/, submissions/{id}/}` مع `serve=>false`.
*   `.env.example:1`: `APP_TIMEZONE=Asia/Damascus` (لا `UTC` — يوم التقرير يُحسب عليها، ثبّتها ولا تغيّرها بعد التشغيل).

## التوثيق والأمان

*   **Web**: جلسات Laravel + `CSRF` + `SecurityHeaders` (`app/Http/Middleware/SecurityHeaders.php:1` — HSTS/frame/no-sniff).
*   **API**: `JWT Bearer` + `AuthenticateApi` + `SetApiLocale` + `throttle:5,1` للدخول والتوليد.
*   كلاهما يستخدم نفس `NoteService` و`NotePolicy` — لا منطق مكرر.

## التخزين — لا وصول مباشر

*   الملفات: قرص محلي `attachments` — ملاحظات `notes/{id}/UUID.ext` + إرسالات `submissions/{id}/UUID.ext`.
*   لا `storage:link` — عرض/تنزيل عبر `GET /attachments/{id}/view|download` و`GET /s/attachments/{id}` بتفويض (`isReportWriter` للتنزيل).
*   روابط مشاركة دائمة نظيفة `/s/...` (صفحة عرض خاصة، الدخول إجباري — `SmartRedirectController` يمنع open-redirect).
*   أسماء ملفات `UUID` + فحص `finfo` حقيقي + رفض الامتدادات الخطرة والمزدوجة (`php, html, js, exe…` و`*.php.*`) + قائمة `mimes` تشمل `ac3,dts,alac` حديثاً.

## المواصفات المعتمدة حالياً

*   `MAX_IMAGE_SIZE=20M / MAX_VIDEO_SIZE=100M` — `config/attachments.php`.
*   `APP_TIMEZONE=Asia/Damascus` — الـ naive يُفسر كـ Damascus لتفادي مستقبل كاذب.
*   `candidates = whereNotIn(select note_id from report_note)` — أي ملاحظة مستخدمة في أي تقرير تُستبعد، للتقارير المسودة فقط (`isDraft()`).
*   `TranslationWarmObserver` يوزع كل حقل حسب لغته الفعلية (عربي→en، إنجليزي→ar، محايد→عكس uiLocale).
