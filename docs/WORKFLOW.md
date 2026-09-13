# WORKFLOW.md — دورات الحياة المحققة (Verified 2026-09-13)

> **المصدر:** `app/Services/NoteService.php:70` + `app/Policies/*` + `app/Services/ReportService.php` — الحالات والانتقالات كما هي في الكود.

## آلة الحالة — الملاحظات

```
مسودة (draft)
  ↓ send (مالك فقط) — fan-out بعد الاستجابة
قيد المراجعة (pending)
  ↓         ↓
مقبولة    مرفوضة
(accepted) (rejected) — rejection_reason إجباري
               ↓ resend (مالك فقط) → pending من جديد
```

*   `draft` لا يراه إلا مالكه (`NotePolicy::view`).
*   `accepted` لا يعدلها إلا `processed_by` (المعالج)، والمرفوضة مقفلة دائماً (`NotePolicy::update` يرفض `isRejected`).

## الانتقالات المسموحة فقط

| من | إلى | الإجراء | المستخدم | التفاصيل الفعلية |
|-------------|-------------|-----------|------------|------------------|
| `draft` | `pending` | إرسال `POST /notes/{id}/send` | مالك فقط | `NoteService::sendNote`: إن كان المرسل `report_writer` يُقبل تلقائياً (`accepted` + `processed_by=me`) وإلا `pending` + `FanoutNoteNotifications::dispatchAfterResponse` |
| `pending` | `accepted` | قبول `POST /notes/{id}/accept` | كاتب تقارير فقط | `processed_by=writer, processed_at=now()` + `NoteAcceptedNotification` للمالك (بفحص `hasNotification` لمنع التكرار) + `flushNoteCaches` للمالك والمعالج |
| `pending` | `rejected` | رفض `POST /notes/{id}/reject` | كاتب تقارير فقط | `rejection_reason` إجباري + `NoteRejectedNotification` |
| `rejected` | `pending` | إعادة إرسال `POST /notes/{id}/resend` | مالك فقط | نفس رقم الملاحظة، `sent_at` جديد، مسح حقول المعالجة + `FanoutNoteNotifications(..., true)` |

## الانتقالات غير المسموحة — يرمي `InvalidArgumentException` (422/403)

*   `draft → accepted` ❌ (إلا إن كان المرسل كاتباً — يمر عبر `send` ويُقبل تلقائياً، ليس `accept` مباشر)
*   `draft → rejected` ❌
*   `pending → draft` ❌
*   `accepted → أي شيء` ❌ (لا `resend` ولا `send` — `isAccepted` يمنع)
*   `rejected → accepted` ❌ (يجب `resend → pending → accept`)
*   `rejected → draft` ❌
*   `rejected → pending` لغير المالك ❌

## تعديل — `NotePolicy::update`

| الحالة | المالك | آخرون | كاتب التقارير (غير مالك) |
|----------|--------|-------|---------------|
| `draft` | ✅ | ❌ | ❌ |
| `pending` | ✅ (مع `normalizeObservedRange` — عبور منتصف الليل مسموح) | ❌ | ❌ |
| `rejected` | ❌ (مقفلة — يجب `resend` أولاً) | ❌ | ❌ |
| `accepted` | ❌ إلا إن كان `processed_by==me` | ❌ | ❌ (إلا إن كان هو المعالج) |

## حذف — `NotePolicy::delete`

| الحالة | المالك | آخرون |
|----------|--------|-------|
| `draft` | ✅ (`DB::transaction` + حذف ملفات محلية) | ❌ |
| `pending`/`rejected`/`accepted` | ❌ | ❌ |

## مرفقات — `NotePolicy::addAttachment/removeAttachment`

*   `addAttachment`: مالك فقط + `!isRejected` + `(!isAccepted || processed_by==me)` + `count < max_per_note(10)` + `finfo` حقيقي + تحقق `existsDb && existsFile` بعد `Attachment::create` وإلا حذف و`verification` خطأ.
*   `removeAttachment`: نفس الشروط — المرفوضة والمقبولة لغير المعالج مقفلتان.

## تفاصيل `NoteService::normalizeObservedRange`

1.  `observed_at` يُفسر حسب `parseTz = (APP_TIMEZONE==UTC ? Asia/Damascus : APP_TIMEZONE)` لتفادي مستقبل كاذب (+03).
2.  `observed_end_at < observed_at` → `+1 day` (عبور منتصف الليل).
3.  `diffInMinutes > 12*60` → رفض (`time_range_exceeded`).
4.  `start/end > now(parseTz)+1h` → رفض (`time_future`).
5.  الحفظ بـ `setTimezone(parseTz)->toDateTimeString()` لضمان اتساق `reportDay()`.

## التقارير اليومية — دورة مستقلة `ReportService + ReportPolicy`

```
مسودة (draft) ⇄ منشورة (published) — مهلة تعديل 12 ساعة بعد النشر
```

| من | إلى | الشرط المحقق |
|----|-----|-------|
| `draft` | `published` | `POST /reports/{id}/publish`: محتوى `content` غير فارغ + ملاحظة مقبولة واحدة على الأقل من نفس `report_date` (فحص `reportDay` على `Asia/Damascus` — أي ملاحظة من يوم آخر مرفوضة حتى بفارق دقيقة) |
| `published` | `draft` | `POST /reports/{id}/unpublish`: مالك فقط + داخل 12 ساعة من `published_at` وإلا 403 |

*   التقرير مرتبط بيوم تقويمي واحد عبر `notes.observed_at` — أي ملاحظة من يوم آخر مرفوضة من السيرفر في `attach/detach/reorder/publish/generate` (لا حماية واجهة فقط).
*   `ai_draft_content/ai_summary/ai_recommendations` مسودة صياغة فقط ولا تُنشر أبدًا — النشر من `content` (المركب من `summary+recommendations`) حصرًا.
*   **المرشحون لإرفاق يدوي** `ReportController::show`: فقط للمسودة (`isDraft()`) + `whereNotIn(select note_id from report_note)` — أي ملاحظة مستخدمة في أي تقرير تُستبعد (ليس فقط هذا التقرير) — `limit 50`.
*   `published` يُنشئ `report_revisions` + `report_sheet_renders` (1–7) — `ReportHtmlRenderingService::systemHash` يكشف `stale` (`system_hash != payload_hash`).
*   التوليد `POST /reports/{id}/generate` محمي بقفل 120 ثانية (`AI_BUSY 409`) و`throttle:5,1` — راجع `REPORTS.md`.
*   بعد 12 ساعة من `published_at`: `ReportPolicy` يمنع `update/delete/unpublish/generate` حتى للمالك (403) — أي تقرير منشور يتجاوز المهلة يصبح مقفلاً، والتقرير المنشور ضمن المهلة يبقى قابلاً للإدارة.

## الإرسالات العامة — منفصلة كليًا `GeneralSubmissionService + GeneralSubmissionPolicy`

```
مراقب: إنشاء (وصف + كتّاب فقط) → POST /general-submissions/{id}/submit → pending
كاتب معني: POST /.../accept → accepted | POST /.../reject + سبب → rejected
```

*   القبول **يقلب الحالة فقط** — لا ينشئ `Note` أبدًا (الإرسالات ليست ملاحظات).
*   `notes.general_submission_id` عمود أرشيفي للفصل العرضي (قوائم الملاحظات `whereNull(general_submission_id)`).
*   الانتقالات: `draft→pending (owner)`, `pending→accepted/rejected (writer in pivot)`, لا `resend` للإرسالات — المرفوضة تبقى مرفوضة.
*   البذور الحالية لا تنشئ إرسالات — تُنشأ يدوياً.

## الترجمة — دورة Presentation فقط

```
نص أصلي (Source) — لغة تُكتشف SourceLanguage::detect()
  ↓ saved (Observer)
محايد → عكس uiLocale | عربي→en | إنجليزي→ar — موزعة per-field
  ↓ WarmTranslationProjection (Job afterResponse) — dedup per target
content_translations (translatable_type/id string/field/locale/source_hash unique)
  ↓ read (resolveStoredMany — دفعة whereIn + xxh3 cache, O(1))
عرض حسب app()->getLocale() عبر LocalizedPresenter — الأصل لا يتغير
```

*   لا انتقالات حالة — `source → stale → refreshed` عبر `source_hash` فقط.

## تنفيذ — طبقتان

1.  `Policy` (التفويض الأساسي — 403 إن فشل)
2.  `NoteService/ReportService/GeneralSubmissionService` (قواعد العمل — 422 برسالة عربية دقيقة `api.*`)
3.  كل الانتقالات الحرجة داخل `DB::transaction` + `afterResponse` للـ fan-out حتى لا يُبطئ الريكويست.
