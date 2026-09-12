/* RASD smart UI i18n — v1.0.0
 * يترجم واجهة النظام فقط (قوائم/أزرار/عناوين) ويترك البيانات كما هي.
 * القاعدة الذهبية: لا يترجم أي نص إلا إذا طابق حرفياً مفتاحاً في القاموس.
 * أي نص حر (أسماء، وصف ملاحظة، عنوان تقرير، رقم جوال) لن يطابق القاموس فيبقى عربياً تلقائياً.
 * عناصر [data-no-translate] وأسلافها مستثناة دائماً. قيم inputs/textareas مستثناة (بيانات)، placeholders تترجم (واجهة).
 */
(function () {
  'use strict';
  // v1 disabled when v2 is present — prevents double handling and black-screen navigation
  try { if (document.querySelector('script[src*="rasd-i18n-v2"]')) return; } catch (e) {}
  if (window.RASD_I18N && window.RASD_I18N.get) return;

  var STORAGE_KEY = 'rasd_locale';
  var COOKIE_KEY = 'rasd_locale';

  // القاموس: عربي (كما يظهر في Blade) -> إنجليزي
  var AR2EN = {
    'نظام ملاحظات كاميرات المراقبة': 'Surveillance Camera Notes System',
    'نظام ملاحظة كاميرات المراقبة': 'Surveillance Camera Notes System',
    'وزارة الإعلام': 'Ministry of Information',
    'وزارة الإعلام — سورية': 'Ministry of Information — Syria',
    'الجمهورية العربية السورية': 'Syrian Arab Republic',
    'تخطي إلى المحتوى': 'Skip to content',
    'جارٍ التحميل': 'Loading',
    'جاري التنفيذ...': 'Processing...',
    'جارٍ التحقق...': 'Verifying...',
    'جارٍ التجهيز...': 'Preparing...',
    'جاري الرفع...': 'Uploading...',
    'إغلاق': 'Close',
    'إلغاء': 'Cancel',
    'رجوع': 'Back',
    'عودة': 'Back',
    '‹ العودة': '‹ Back',
    'حفظ': 'Save',
    'تعديل': 'Edit',
    'حذف': 'Delete',
    'تطبيق': 'Apply',
    'مشاركة': 'Share',
    'نسخ': 'Copy',
    'تم النسخ': 'Copied',
    'تعذر النسخ': 'Copy failed',
    'عرض': 'View',
    'تنزيل': 'Download',
    'تنزيل المرفق': 'Download attachment',
    'طباعة': 'Print',
    'فتح للطباعة': 'Open for printing',
    'طباعة / حفظ': 'Print / Save',
    'الكل': 'All',
    'الحالة': 'Status',
    'إجراءات': 'Actions',
    'نشط': 'Active',
    'المطورون': 'Developers',
    // تنقل
    'الملاحظات': 'Notes',
    'ملاحظاتي': 'My Notes',
    'المعرض': 'Gallery',
    'الإرسالات العامة': 'Public Submissions',
    'التقارير': 'Reports',
    'التقارير اليومية': 'Daily reports',
    'الترتيب': 'Ranking',
    'ترتيب المراقبين': 'Monitors ranking',
    'الأكثر قبولاً أولاً': 'Most accepted first',
    'يُحتسب الترتيب من الملاحظات المقبولة فقط': 'Ranking counts accepted notes only',
    'حسابي': 'My Account',
    'خروج': 'Logout',
    'القائمة': 'Menu',
    'تبديل الوضع': 'Toggle theme',
    'الوضع الداكن': 'Dark mode',
    // إشعارات
    'الإشعارات': 'Notifications',
    'مركز الإشعارات': 'Notification Center',
    'إعدادات الإشعارات': 'Notification settings',
    'تحديد الكل كمقروء': 'Mark all as read',
    'تم تحديد كل الإشعارات كمقروءة': 'All notifications marked as read',
    'أصوات الإشعارات': 'Notification sounds',
    'مستوى الصوت': 'Volume',
    'التوست الفوري': 'Instant toast',
    'يتم حفظ اختيارك تلقائياً. عند كتم الصوت لن تسمع نغمة حتى لو وصل إشعار جديد.': 'Your choice is saved automatically. When muted you will not hear a tone.',
    'فعّل الصوت والإشعارات ليصلك التنبيه فوراً': 'Enable sound & notifications to get instant alerts',
    'فعّل الصوت ليصلك التنبيه فوراً': 'Enable sound to get instant alerts',
    'تفعيل': 'Enable',
    'تفعيل 🔔': 'Enable 🔔',
    'لا توجد إشعارات': 'No notifications',
    'ستظهر الإشعارات الواردة هنا فور وصولها': 'Incoming notifications will appear here',
    'عرض كل الإشعارات': 'View all notifications',
    'سجل الإشعارات': 'Notification log',
    'متابعة فورية لحالة ملاحظاتك': 'Live tracking of your notes status',
    'تصفية الإشعارات': 'Filter notifications',
    'غير مقروءة': 'Unread',
    'مقروءة': 'Read',
    'تحديد كمقروء': 'Mark as read',
    'عرض التفاصيل': 'View details',
    'إشعار جديد': 'New notification',
    'غير متصل': 'Offline',
    'تنبيه — يرجى المراجعة': 'Notice — please review',
    'تم الحفظ، لكن بعض المرفقات لم تُرفع:': 'Saved, but some attachments failed to upload:',
    // الملف الشخصي
    'الملف الشخصي': 'Profile',
    'بيانات حسابك ودورك في النظام': 'Your account data and role',
    'عرض ملف مستخدم آخر — مسموح للجميع': 'Viewing another user — public',
    'تعديل الملف الشخصي': 'Edit Profile',
    'تعديل الملف': 'Edit profile',
    'حدّث صورتك واسمك والرقم الشخصي وكلمة المرور': 'Update your photo, name, personal number and password',
    'بيانات الحساب': 'Account data',
    'معلومات الحساب': 'Account info',
    'الصورة الشخصية': 'Profile photo',
    'تغيير الصورة': 'Change photo',
    'اختيار صورة جديدة': 'Choose a new photo',
    'بعد اختيار الصورة ستظهر معاينة لقصّها داخل إطار دائري قبل الحفظ': 'After choosing, preview and crop it inside a circle before saving',
    'حذف الصورة الحالية': 'Remove current photo',
    'قص الصورة الشخصية': 'Crop profile photo',
    'اسحب الصورة لتحريكها داخل الدائرة — الناتج دائري ثابت مثل واتساب': 'Drag the photo inside the circle — circular output like WhatsApp',
    'اعتماد': 'Approve',
    'الاسم الكامل': 'Full name',
    'اسم المستخدم': 'Username',
    'رقم الجوال (واتساب)': 'Mobile number (WhatsApp)',
    'الرقم الشخصي': 'Personal number',
    '— غير محدد': '— Not set',
    'الدور الحالي': 'Current role',
    'الدور': 'Role',
    'مُراقب ميداني': 'Field Monitor',
    'مراقب — إنشاء ومتابعة الملاحظات': 'Monitor — create & follow up notes',
    'كاتب تقارير': 'Report Writer',
    'كاتب التقارير': 'Report Writer',
    'كاتب تقارير — اعتماد ورفض': 'Report writer — approve & reject',
    'كتّاب التقارير': 'Report writers',
    'لا يوجد كتّاب تقارير حاليًا': 'No report writers right now',
    'الجهة': 'Organization',
    'تغيير كلمة المرور': 'Change password',
    '— اختياري': '— optional',
    'اتركها فارغة إذا لا تريد التغيير': 'Leave empty if you don’t want to change',
    'اتركها فارغة إذا لا تريد التغيير ': 'Leave empty if you don’t want to change',
    'كلمة المرور الحالية': 'Current password',
    'كلمة المرور الجديدة': 'New password',
    'تأكيد الجديدة': 'Confirm new one',
    'كلمة المرور': 'Password',
    'حفظ التعديلات': 'Save changes',
    'العودة للملاحظات': 'Back to notes',
    'عرض ملاحظاته': 'View notes',
    'إجمالي الملاحظات': 'Total notes',
    'مقبولة': 'Accepted',
    'مرفوضة': 'Rejected',
    'مسودة': 'Draft',
    'قيد المراجعة': 'Pending review',
    'قبول': 'Acceptance',
    'نسبة القبول': 'Acceptance rate',
    'عضو منذ': 'Member since',
    'لا توجد إحصائيات بعد.': 'No statistics yet.',
    'النشاط والأداء': 'Activity & performance',
    'إجمالي': 'Total',
    'عرض الصورة بحجم كامل': 'View full-size photo',
    // اللغة
    'اللغة': 'Language',
    'لغة الواجهة': 'Interface language',
    'تغيير اللغة يترجم الواجهة وبيانات الصفحة الحالية — الأصل العربي محفوظ ولا يتغير أبداً.': 'Switching language translates the interface and the current page data — the Arabic original is always preserved.',
    'العربية': 'Arabic',
    'الترجمة للعرض فقط — البيانات الأصلية تبقى بالعربية': 'Translation is display-only — original data stays in Arabic',
    'تم تغيير اللغة بنجاح': 'Language changed successfully',
    // حالات + وثيقة رسمية (labels فقط — القيم الداخلية لا تتغير)
    'منشور': 'Published',
    'منشور منذ': 'Published since',
    'التعديل متاح حتى': 'Editable until',
    'رقم التقرير': 'Report Number',
    'تاريخ التقرير': 'Report date',
    'التقرير اليومي': 'Daily Report',
    'التقرير اليومي — 2026-09-12': 'Daily Report — 2026-09-12',
    'معدّ التقرير': 'Prepared by',
    'التوقيع': 'Signature',
    'جارٍ تجهيز المحتوى…': 'Preparing content…',
    // صفحة التقرير — واجهة (التبديل الفوري يطابق العرض السيرفري)
    'مخفي عن المراقبين': 'Hidden from monitors',
    'أضف ملاحظة للنشر.': 'Add a note to publish.',
    'اعتمد التقرير للنشر.': 'Approve the report to publish.',
    'أضف ملاحظة واعتمد التقرير للنشر.': 'Add a note and approve the report to publish.',
    'النسخة المعتمدة قديمة — أعد الاعتماد أدناه.': 'The approved version is stale — re-approve below.',
    'تعديل البيانات': 'Edit data',
    'سحب النشر': 'Unpublish',
    'حذف التقرير': 'Delete report',
    'إضافة ملاحظات…': 'Add notes…',
    'إضافة المحدد': 'Add selected',
    'اختر ملاحظة.': 'Choose a note.',
    'البيانات النهائية': 'Final data',
    'استعادة النظام': 'Restore system data',
    'لا مسودة بعد.': 'No draft yet.',
    'اعتماد الملخص ↓': 'Adopt summary ↓',
    'اعتماد التوصيات ↓': 'Adopt recommendations ↓',
    'تحريك لأعلى': 'Move up',
    'تحريك لأسفل': 'Move down',
    'إزالة الملاحظة من التقرير؟': 'Remove note from report?',
    'إجراءات التقرير': 'Report actions',
    'إجراءات الملاحظة': 'Note actions',
    'خيارات إضافية': 'More options',
    'لا ملاحظات.': 'No notes.',
    'لا يوجد.': 'None yet.',
    'فتح التقرير': 'Open report',
    'معاينة': 'Preview',
    'للتصدير استخدم قائمة ⋮ في التقارير.': 'To export, use the ⋮ menu in Reports.',
    'تقرير واحد': '1 report',
    'تقريران': '2 reports',
    'لا توجد تقارير': 'No reports',
    'تصدير PDF': 'Export PDF',
    'مشاركة عبر WhatsApp': 'Share via WhatsApp',
    'طباعة / PDF': 'Print / PDF',
    'تحميل PNG': 'Download PNG',
    // تسجيل الدخول
    'تسجيل الدخول': 'Login',
    'وزارة الإعلام — تسجيل الدخول': 'Ministry of Information — Login',
    'أدخل بيانات الاعتماد للمتابعة إلى لوحة المتابعة': 'Enter your credentials to continue',
    'تعذر تسجيل الدخول': 'Login failed',
    'تذكرني': 'Remember me',
    'دخول': 'Login',
    // ملاحظات
    'ملاحظة جديدة': 'New note',
    'وثّق ملاحظة ميدانية': 'Document a field note',
    'تابع ملاحظاتك الميدانية وإدارتها': 'Track and manage your field notes',
    'رقم الطابق': 'Floor number',
    'رقم الكاميرا': 'Camera number',
    'التاريخ': 'Date',
    'بداية الملاحظة': 'Note start',
    'انتهاء الملاحظة': 'Note end',
    'الوصف': 'Description',
    'وصف الملاحظة': 'Note description',
    'صف ما تم رصده بدقة...': 'Describe what was observed accurately...',
    'المرفقات': 'Attachments',
    'اختيار ملفات': 'Choose files',
    'الكاميرا المباشرة': 'Live camera',
    'تسجيل صوتي': 'Audio recording',
    'تسجيل فيديو': 'Video recording',
    'التقاط صورة': 'Capture photo',
    'تصوير': 'Capture',
    'فيديو': 'Video',
    'حفظ الملاحظة': 'Save note',
    'حفظ كمسودة': 'Save as draft',
    'حفظ وإرسال للمراجعة': 'Save & send for review',
    'تعديل الملاحظة': 'Edit note',
    'تاريخ الإنشاء': 'Created at',
    'وقت الإنشاء': 'Creation time',
    'وقت الملاحظة': 'Note time',
    'المُلاحظ': 'Observer',
    'لا توجد ملاحظات': 'No notes',
    'لا توجد ملاحظات قيد المراجعة حالياً': 'No pending notes right now',
    'مسح الفلاتر': 'Clear filters',
    'مسح الفلترة': 'Clear filter',
    'مسح': 'Clear',
    'إرسال للمراجعة': 'Send for review',
    'تصحيح وإعادة إرسال': 'Fix & resend',
    'قبول واعتماد': 'Accept & approve',
    'قبول': 'Accept',
    'رفض': 'Reject',
    'رفض مع سبب': 'Reject with reason',
    'سبب الرفض': 'Rejection reason',
    'اكتب سبباً واضحاً لكي يتمكن المراقب من التصحيح': 'Write a clear reason so the monitor can fix it',
    'تأكيد الرفض': 'Confirm rejection',
    'طباعة الملاحظة كوثيقة رسمية': 'Print note as official document',
    'فتح واتساب بالنص': 'Open WhatsApp with text',
    'نسخ النص': 'Copy text',
    'نسخ الرابط': 'Copy link',
    'روابط المشاهدة — دائمة': 'Watch links — permanent',
    'لا تغلق الصفحة': 'Do not close the page',
    'جاري رفع الملفات الأصلية دون تعديل': 'Uploading original files unmodified',
    'يرجى تصحيح الحقول:': 'Please fix the fields:',
    'فشل رفع المرفقات. لم يتم حفظ الملاحظة': 'Attachment upload failed. Note was not saved',
    'هل أنت متأكد من الحذف؟': 'Are you sure you want to delete?',
    'مُعتمدة نهائياً': 'Finally approved',
    'لا يوجد وصف': 'No description',
    'لا يمكن معاينة هذا النوع': 'Cannot preview this type',
    // تقارير
    'تقرير جديد': 'New report',
    'تقرير يومي جديد': 'New daily report',
    'لا توجد تقارير مطابقة': 'No matching reports',
    'أنشئ تقريراً جديداً من الزر أعلاه': 'Create a new report from the button above',
    'عرض التقرير': 'View report',
    'تصدير كصورة': 'Export as image',
    'تحميل كصورة': 'Download as image',
    'التقرير مرتبط بيوم تقويمي واحد': 'A report is linked to a single calendar day',
    'عنوان التقرير': 'Report title',
    'مثال: التقرير اليومي': 'E.g.: Daily report',
    'مرئي للمراقبين عند النشر': 'Visible to monitors when published',
    'إنشاء المسودة': 'Create draft',
    'جارٍ إنشاء المسودة...': 'Creating draft...',
    'البيانات النهائية والاعتماد': 'Final data & approval',
    'المحتوى اليدوي': 'Manual content',
    'الملخص التنفيذي': 'Executive summary',
    'التوصيات': 'Recommendations',
    'توليد المسودة': 'Generate draft',
    'توليد ذكي': 'Smart generate',
    'نشر': 'Publish',
    'نشر التقرير؟': 'Publish report?',
    'سحب النشر؟': 'Unpublish?',
    'حذف نهائي؟': 'Delete permanently?',
    'إعادة الترتيب': 'Reorder',
    'إضافة من مقبولات اليوم': 'Add from today’s accepted',
    'لا ملاحظات مرفقة بعد': 'No notes attached yet',
    'المعاينة': 'Preview',
    'وثيقة المحرك الموحدة': 'Unified engine document',
    // معرض
    'معرض المرفقات': 'Attachments gallery',
    'مرفق · ملاحظات وإرسالات': 'Attachment · notes & submissions',
    'خيارات العرض': 'View options',
    'شبكة': 'Grid',
    'مدمج': 'Compact',
    'قائمة': 'List',
    'الكاميرا': 'Camera',
    'الطابق': 'Floor',
    'النوع': 'Type',
    'الترتيب': 'Sort',
    'صور': 'Images',
    'صوت': 'Audio',
    'الأحدث أولًا': 'Newest first',
    'الأقدم أولًا': 'Oldest first',
    'لا توجد مرفقات مطابقة': 'No matching attachments',
    'عرض الكل': 'View all',
    'مقطع فيديو': 'Video clip',
    'مقطع صوتي': 'Audio clip',
    // إرسالات
    'إرسال عام جديد': 'New public submission',
    '+ إرسال عام جديد': '+ New public submission',
    'الإرسالات الموجهة إليك أو التي أنشأتها': 'Submissions sent to you or created by you',
    'اليوم': 'Today',
    'أمس': 'Yesterday',
    'آخر 7 أيام': 'Last 7 days',
    'فلترة حسب الوقت': 'Filter by time',
    'لا توجد إرسالات': 'No submissions',
    'اختر كاتبًا واحدًا أو عدة كتّاب لإرسال الملاحظة': 'Select one or more writers to send the note to',
    'إرسال إلى المختارين': 'Send to selected',
    'مشاركة الإرسال': 'Share submission',
    'مشاركة عبر واتساب': 'Share via WhatsApp',
    'المرسل:': 'Sender:',
    'موجه إلى:': 'To:',
    // تحقق
    // صفحة عرض التقرير (عناوين إدارية قصيرة + قوائم ⋮)
    'البيانات النهائية': 'Final data',
    'المحتوى': 'Content',
    'منشور': 'Published',
    'مخفي عن المراقبين': 'Hidden from monitors',
    'إزالة': 'Remove',
    'تحريك لأعلى': 'Move up',
    'تحريك لأسفل': 'Move down',
    'إضافة ملاحظات…': 'Add notes…',
    'إضافة المحدد': 'Add selected',
    'سحب النشر': 'Unpublish',
    'حذف التقرير': 'Delete report',
    'تعديل البيانات': 'Edit data',
    'استعادة النظام': 'Restore system',
    'لا ملاحظات.': 'No notes.',
    'لا يوجد.': 'None.',
    'أضف ملاحظة للنشر.': 'Add a note to publish.',
    'اعتمد التقرير للنشر.': 'Approve the report to publish.',
    'أضف ملاحظة واعتمد التقرير للنشر.': 'Add a note and approve the report to publish.',
    'النسخة المعتمدة قديمة — أعد الاعتماد أدناه.': 'Approved version is outdated — re-approve below.',
    'تم تحديث الملف الشخصي بنجاح': 'Profile updated successfully'
  };

  var EN2AR = {};
  Object.keys(AR2EN).forEach(function (k) { EN2AR[AR2EN[k]] = k; });

  function norm(s) {
    return String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
  }

  function hasNoTranslate(el) {
    try {
      if (!el || el.nodeType !== 1) return false;
      if (el.hasAttribute('data-no-translate')) return true;
      if (el.closest) {
        if (el.closest('[data-no-translate]')) return true;
        // البيانات الحرة: لا نلمسها أبداً
        if (el.closest('script,style,textarea')) return true;
        if (el.closest('[data-keep-lang],.no-i18n,.user-content,.note-body,.report-content')) return true;
      }
      var tag = (el.tagName || '').toUpperCase();
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA') return true;
      // قيم الحقول بيانات — نترجم placeholder فقط
      if (tag === 'INPUT' || tag === 'SELECT' || tag === 'OPTION') {
        // نص الخيار قد يكون واجهة (مثل الكل/اليوم) — نسمح به إذا طابق القاموس
        // لكن قيمة input النصية لا نترجمها كنص
        return false;
      }
      return false;
    } catch (e) { return false; }
  }

  function currentLocale() {
    return window.RASD_LOCALE === 'en' ? 'en' : 'ar';
  }

  function translateString(s, to) {
    var n = norm(s);
    if (!n) return null;
    if (to === 'en') {
      if (AR2EN[n]) return AR2EN[n];
      // تقرير يومي مع تاريخ — ترجمة نمطية فورية لأي تاريخ (لا قاموس محدد لكل يوم)
      var m = n.match(/^التقرير اليومي — (\d{4}-\d{2}-\d{2})$/);
      if (m) return 'Daily Report — ' + m[1];
      // Fallback بفاصلة عادية لو تغير الرمز
      var m2 = n.match(/^التقرير اليومي - (\d{4}-\d{2}-\d{2})$/);
      if (m2) return 'Daily Report — ' + m2[1];
      return null;
    }
    if (EN2AR[n]) return EN2AR[n];
    var e = n.match(/^Daily Report — (\d{4}-\d{2}-\d{2})$/);
    if (e) return 'التقرير اليومي — ' + e[1];
    var e2 = n.match(/^Daily Report - (\d{4}-\d{2}-\d{2})$/);
    if (e2) return 'التقرير اليومي — ' + e2[1];
    return null;
  }

  function applyToElement(el, to) {
    if (!el || el.nodeType !== 1) return 0;
    if (hasNoTranslate(el)) return 0;
    var changed = 0;
    var tag = (el.tagName || '').toUpperCase();

    // لا تلمس inputs كنص — فقط attributes
    var skipText = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'SCRIPT' || tag === 'STYLE');

    if (!skipText) {
      // ترجم عقد النص المباشرة فقط (حتى لا نكسر HTML الداخلي)
      var nodes = el.childNodes;
      for (var i = 0; i < nodes.length; i++) {
        var nd = nodes[i];
        if (nd.nodeType === 3) {
          var orig = nd.nodeValue;
          var n = norm(orig);
          if (!n) continue;
          // تجاهل النصوص القصيرة جداً/أرقام/رموز (بيانات غالباً)
          if (n.length < 2) continue;
          if (/^[0-9\s\-:\/.,#@_()\[\]٪%]+$/.test(n)) continue;
          var rep = translateString(n, to);
          if (rep && rep !== n) {
            // حافظ على المسافات المحيطة
            var lead = (orig.match(/^\s+/) || [''])[0];
            var trail = (orig.match(/\s+$/) || [''])[0];
            nd.nodeValue = lead + rep + trail;
            changed++;
          }
        }
      }
    }

    // attributes واجهة فقط — وفقط عند التطابق الحرفي
    ['placeholder', 'title', 'aria-label'].forEach(function (attr) {
      try {
        if (el.hasAttribute && el.hasAttribute(attr)) {
          var v = el.getAttribute(attr);
          var n = norm(v);
          if (!n) return;
          // حماية: alt أسماء المستخدمين وصور البروفايل لا تترجم
          if (attr === 'aria-label' || attr === 'title') {
            if (el.hasAttribute('data-no-translate')) return;
          }
          var rep = translateString(n, to);
          if (rep && rep !== n) { el.setAttribute(attr, rep); changed++; }
        }
      } catch (e) {}
    });

    return changed;
  }

  var scheduled = false;
  function applyLocale(to, opts) {
    opts = opts || {};
    to = to === 'en' ? 'en' : 'ar';
    try {
      var root = document.documentElement;
      root.setAttribute('lang', to);
      root.setAttribute('dir', to === 'ar' ? 'rtl' : 'ltr');
      window.RASD_LOCALE = to;

      // زر الهيدر
      var lbl = document.getElementById('lang-toggle-label');
      if (lbl) lbl.textContent = to === 'ar' ? 'EN' : 'ع';

      // أزرار الملف الشخصي (إن وجدت)
      document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
        var l = b.getAttribute('data-lang-btn');
        var on = l === to;
        b.classList.toggle('ring-2', on);
        b.classList.toggle('ring-offset-2', on);
        b.classList.toggle('ring-sage-500', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      // امشِ على كل العناصر — القاموس whitelist يضمن بقاء البيانات
      var all = document.querySelectorAll('body *');
      for (var i = 0; i < all.length; i++) {
        applyToElement(all[i], to);
      }
      // title
      try {
        var t = norm(document.title);
        // title مركب "X — Y" — ترجم الأجزاء
        var parts = document.title.split('—');
        var out = parts.map(function (p) {
          var r = translateString(p, to);
          return r || p.trim();
        }).join(' — ');
        if (out && out !== document.title) document.title = out;
      } catch (e) {}

      if (!opts.silent) {
        try { localStorage.setItem(STORAGE_KEY, to); } catch (e) {}
        try {
          var exp = new Date(); exp.setFullYear(exp.getFullYear() + 1);
          document.cookie = COOKIE_KEY + '=' + to + '; path=/; expires=' + exp.toUTCString() + '; SameSite=Lax';
        } catch (e) {}
      }
    } catch (e) {}
  }

  function persistServer(to) {
    try {
      var token = document.querySelector('meta[name="csrf-token"]');
      var csrf = token ? token.getAttribute('content') : '';
      fetch('/locale', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf || '',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ locale: to })
      }).catch(function () {});
    } catch (e) {}
  }

  function setLocale(to, persist) {
    to = to === 'en' ? 'en' : 'ar';
    if (to === currentLocale()) { applyLocale(to, { silent: false }); }
    else { applyLocale(to, { silent: false }); }
    if (persist !== false) persistServer(to);
    // طبقة البيانات الديناميكية (Presentation فقط) تستمع لهذا الحدث.
    try { document.dispatchEvent(new CustomEvent('rasd:locale', { detail: { locale: to } })); } catch (e) {}
  }

  function scheduleApply() {
    if (scheduled) return;
    scheduled = true;
    setTimeout(function () {
      scheduled = false;
      try { applyLocale(currentLocale(), { silent: true }); } catch (e) {}
    }, 120);
  }

  function init() {
    var initial = 'ar';
    try {
      var srv = document.querySelector('meta[name="rasd-locale"]');
      var srvL = srv ? srv.getAttribute('content') : null;
      var ls = null; try { ls = localStorage.getItem(STORAGE_KEY); } catch (e) {}
      var ck = (document.cookie.match(/(?:^|;\s*)rasd_locale=(ar|en)/) || [])[1];
      initial = window.RASD_LOCALE || ls || ck || srvL || 'ar';
      if (initial !== 'ar' && initial !== 'en') initial = 'ar';
      window.RASD_LOCALE = initial;
    } catch (e) { window.RASD_LOCALE = 'ar'; initial = 'ar'; }

    var run = function () {
      applyLocale(initial, { silent: true });
      var btn = document.getElementById('lang-toggle');
      if (btn && !btn.dataset.i18nBound) {
        btn.dataset.i18nBound = '1';
        btn.addEventListener('click', function () {
          var next = currentLocale() === 'ar' ? 'en' : 'ar';
          setLocale(next, true);
        });
      }
      document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
        if (b.dataset.i18nBound) return;
        b.dataset.i18nBound = '1';
        b.addEventListener('click', function () {
          var l = b.getAttribute('data-lang-btn');
          // أزرار الملف: فوراً JS + حفظ سيرفر؛ إن كان form سيتكفل بالPOST الكامل
          if (b.tagName === 'BUTTON' && !b.closest('form')) {
            setLocale(l, true);
          }
        });
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else { run(); }

    try {
      var obs = new MutationObserver(function () { scheduleApply(); });
      obs.observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    } catch (e) {}
  }

  window.RASD_I18N = {
    setLocale: setLocale,
    apply: applyLocale,
    get: currentLocale,
    dictSize: function () { return Object.keys(AR2EN).length; }
  };

  init();
})();
