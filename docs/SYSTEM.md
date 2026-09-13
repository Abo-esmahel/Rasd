# SYSTEM.md — نظام ملاحظات كاميرات المراقبة (Verified 2026-09-13)

> **الغرض:** نظام داخلي لوزارة الإعلام لمتابعة كاميرات المراقبة وتسجيل ملاحظات المراقبين واعتمادها — **مصدر الحقيقة:** `app/Policies/*` + `app/Services/*` + `routes/*.php`.

## النطاق — محقق من الكود الفعلي

*   تسجيل ملاحظات المراقبة: طابق/كاميرا/تاريخ+نهاية/وصف/مرفقات — مع `NoteService::normalizeObservedRange()` (عبور منتصف الليل → +1 day، حد 12 ساعة، تفسير naive كـ `Asia/Damascus`).
*   تدفق عمل: `مسودة → قيد المراجعة → مقبولة/مرفوضة` + إعادة إرسال — ذري عبر `transactions`.
*   إعادة إرسال الملاحظات المرفوضة (مالك فقط) مع `FanoutNoteNotifications::dispatch(... true)` لإشعار الكتّاب من جديد.
*   **الإرسالات العامة**: إرسال مبسّط (وصف + كتّاب عبر `general_submission_report_writer`) بقبول/رفض — منفصلة كليًا عن الملاحظات (القبول لا ينشئ `Note` أبدًا) — `GeneralSubmissionService`.
*   **معرض موحد** لكل المرفقات (ملاحظات + إرسالات) مع `unionAll + paginate(24)` و3 أنماط عرض — راجع `GALLERY.md`.
*   **ترجمة ذكية ثنائية** `ar ↔ en`: كشف حتمي `SourceLanguage::detect()` + `content_translations` (`string translatable_id + source_hash`) + `TranslationService` (دفعة `whereIn` + `xxh3` cache) + `WarmTranslationObserver` (توزيع كل حقل حسب لغته الفعلية) — Presentation فقط، Business Data لا يتغير.
*   مشاركة واتساب بروابط دائمة `/s/...` + صفحة عرض خاصة (الدخول إجباري، التنزيل للكتّاب فقط، `SmartRedirectController` يمنع open-redirect).
*   واجهة ويب عربية RTL + إنجليزية LTR + وضع داكن — `lang/ar+en/*.php` (957 مفتاح) + `SetLocale`/`SetApiLocale` + `LocalizedPresenter`.
*   واجهة برمجة تطبيقات REST — `routes/api.php` (27 مسار) — JWT Bearer + نفس Policies.
*   **التقارير اليومية**: تقرير واحد لكل يوم من الملاحظات المقبولة (`report_date` على `Asia/Damascus`)، مع توليد مسودة عبر Gemini + محرك وثائق موحد ثنائي اللغة + 7 أوراق رسمية — راجع `REPORTS.md`.

## المستخدمون والأدوار — من `app/Models/User.php` + `DatabaseSeeder`

| الدور | القيمة `role` | ينشئ ملاحظة | يرى مسودات الآخرين | يقبل/يرفض | ينزّل المرفقات | يدير التقارير |
|-------|---------------|-------------|-------------------|-----------|----------------|---------------|
| مُراقب | `monitor` | ✅ (مسودة→pending، أو `accepted` إن كان كاتباً) | ❌ | ❌ | مشاهدة فقط | يرى المنشور المرئي فقط بلا AI/سجل |
| كاتب تقارير | `report_writer` | ✅ (يُنشئ `accepted` مباشرة) | ❌ | ✅ | ✅ حصرياً | ينشئ الكل، يدير تقاريره فقط (12h مهلة بعد النشر) |

*البذور:* `tariq, hadi, hamza, rami` (مراقبون) + `writer` + كتّاب إضافيون — كلهم `password` — `RasdSmartSeed`.

### مُراقب (monitor) — `NotePolicy + GeneralSubmissionPolicy`
*   إنشاء مسودات ملاحظات (`create: isMonitor || isReportWriter`)
*   عرض/تعديل/حذف مسوداته (`update: owner && !rejected && (!accepted || processed_by==me)`, `delete: owner && isDraft`)
*   إرسال مسوداته (`send: owner && isDraft` → `pending` أو `accepted` إن كان كاتباً)
*   عرض جميع الملاحظات **عدا مسودات الآخرين** (`view: owner || status!=draft`)
*   تعديل ملاحظاته قيد المراجعة والمرفوضة → إعادة إرسال (`resend: owner && isRejected → pending`)
*   إنشاء إرسالات عامة موجهة لكتّاب محددين

### كاتب تقارير (report_writer) — `NotePolicy + ReportPolicy`
*   عرض جميع الملاحظات (عدا المسودات) + قبول/رفض `pending` فقط (`accept/reject: isReportWriter && isPending`) مع `rejection_reason` إجباري
*   **تنزيل المرفقات (حصريًا)** — `GET /attachments/{id}/download` يتحقق `isReportWriter` (المراقب 403 حتى بالرابط المباشر)
*   إنشاء التقارير اليومية وإدارة **تقاريره فقط** (`ReportPolicy: author_id==me && isDraft() && within 12h` للنشر/الحذف/إلغاء النشر/التوليد) — يرى كل التقارير لكن لا يدير تقارير غيره
*   **تصدير PDF/صورة** `export` — كتّاب فقط (403 للمراقب)

## الملكية

`user_id` = ملكية، وليس رؤية. لا يمكن للعملاء التحكم في `user_id` أو `status` أو `processed_by` (mass assignment محمي بـ `array_intersect_key` في `NoteService`).

## الرؤية — `getVisibleNotesQuery()` + `NotePolicy::view`

| الحالة | المُراقب (مالك) | مُراقب آخر | كاتب التقارير |
|----------|-----------------|------------|---------------|
| مسودة | ✅ | ❌ | ❌ |
| قيد المراجعة | ✅ | ✅ | ✅ |
| مرفوضة | ✅ | ✅ | ✅ |
| مقبولة | ✅ | ✅ | ✅ |

*التطبيق:* `whereNull(general_submission_id)->where(user_id=me OR status!=draft)` + مع `owner, processor, attachments_count` بلا N+1. `general_submission_id` يبقي الإرسالات خارج قوائم الملاحظات تماماً.

## رؤية التقارير — `ReportPolicy::view`

| التقرير | مُراقب | كاتب التقارير |
|---------|--------|---------------|
| مسودة | ❌ | ✅ (الكل) |
| منشور + مرئي للمراقبين `visible_to_monitors=true` | ✅ (بدون `ai_draft_content/ai_summary/ai_recommendations` ولا `report_revisions` ولا `report_sheet_renders` الخاصة) | ✅ (الكل) |
| منشور + مخفي `visible_to_monitors=false` | ❌ | ✅ (الكل) |

## رؤية الإرسالات — `GeneralSubmissionPolicy`

| المستخدم | يرى |
|----------|-----|
| المرسِل | ✅ كل إرسالاته |
| كاتب معني (في `general_submission_report_writer`) | ✅ الإرسالات الموجهة إليه |
| كاتب غير معني | ❌ |
| مراقب غير مرسِل وغير معني | ❌ |

## الترجمة — Presentation Concern

*   `SourceLanguage::detect()` حتمي بلا AI (عربي `[0600-06FF]` وإلا إنجليزي/محايد — `NEUTRAL` لا يُترجم).
*   `content_translations` خارج Business Data — `translatable_type/id(string)/field/locale/source_hash` unique — stale تُتجاهل تلقائياً.
*   `LocalizedPresenter` يعرض حسب `app()->getLocale()` — الأصل لا يتغير أبداً.

## الضوابط المعتمدة

*   رؤية التقارير: مهلة إدارة 12 ساعة بعد النشر (`ReportPolicy` — بعدها 403).
*   إنشاء الملاحظات: المراقب وكاتب التقارير — ملاحظة الكاتب تُقبل تلقائياً (`accepted` + `processed_by`).
*   الإرسالات: منفصلة تماماً عن الملاحظات — تُنشأ يدوياً ولا تُولد `Note`.
