

export const PRIORITY = {
  NORMAL: 'normal',
  HIGH: 'high',
  CRITICAL: 'critical',
};

export const CATEGORY = {
  NOTE: 'note',
  DISPATCH: 'dispatch',
  GENERIC: 'generic',
};

// Locale-aware labels: Arabic default, English when <html lang="en"> or RASD_LOCALE=en.
// Server sends localized title/message first — these are fallbacks only (ZERO hardcoded visible Arabic in EN).
export function rasdNotifLocale() {
  try {
    if (typeof window !== 'undefined' && window.RASD_LOCALE === 'en') return 'en';
    if (typeof document !== 'undefined' && document.documentElement && document.documentElement.lang === 'en') return 'en';
  } catch (e) {}
  return 'ar';
}
export function cfgLabel(cfg, short = false) {
  if (!cfg) return rasdNotifLocale() === 'en' ? 'Notification' : 'إشعار';
  if (rasdNotifLocale() === 'en') {
    if (short && cfg.labelShortEn) return cfg.labelShortEn;
    if (!short && cfg.labelEn) return cfg.labelEn;
  }
  if (short && cfg.labelShort) return cfg.labelShort;
  return cfg.label || (rasdNotifLocale() === 'en' ? 'Notification' : 'إشعار');
}
export const NOTIF_FB = {
  get newNotif() { return rasdNotifLocale() === 'en' ? 'New notification' : 'إشعار جديد'; },
  get youHave() { return rasdNotifLocale() === 'en' ? 'You have a new notification' : 'لديك إشعار جديد'; },
  get notif() { return rasdNotifLocale() === 'en' ? 'Notification' : 'إشعار'; },
  get now() { return rasdNotifLocale() === 'en' ? 'Now' : 'الآن'; },
  get emptyTitle() { return rasdNotifLocale() === 'en' ? 'No notifications' : 'لا توجد إشعارات'; },
  get emptyHint() { return rasdNotifLocale() === 'en' ? 'Incoming notifications will appear here' : 'ستظهر الإشعارات الواردة هنا فور وصولها'; },
  get camera() { return rasdNotifLocale() === 'en' ? 'Camera' : 'كاميرا'; },
  get floor() { return rasdNotifLocale() === 'en' ? 'Floor' : 'طابق'; },
  get unread() { return rasdNotifLocale() === 'en' ? 'unread notifications' : 'إشعارات غير مقروءة'; },
  get close() { return rasdNotifLocale() === 'en' ? 'Close notification' : 'إغلاق الإشعار'; },
  get viewDetails() { return rasdNotifLocale() === 'en' ? 'View details' : 'عرض التفاصيل'; },
  get soundBlocked() { return rasdNotifLocale() === 'en' ? 'Browser blocked sound — tap enable' : 'المتصفح حجب الصوت — اضغط تفعيل'; },
  get enableSoundNow() { return rasdNotifLocale() === 'en' ? 'Enable sound to get instant alerts' : 'فعّل الصوت ليصلك التنبيه فوراً'; },
};
export const TYPE_CONFIG = {
  note_sent: {
    label: 'ملاحظة واردة',
    labelEn: 'Incoming note',
    labelShort: 'واردة',
    labelShortEn: 'Incoming',
    icon: 'doc',
    color: 'amber',
    bgClass: 'bg-surface-elevated border border-border text-amber-600 dark:text-amber-400',
    dotClass: 'bg-amber-500',
    priority: PRIORITY.NORMAL,
    sound: 'normal',
    duration: 5000,
    toast: true,
    vibrate: [200, 100, 200],
  },
  note_accepted: {
    label: 'تم القبول',
    labelEn: 'Accepted',
    labelShort: 'مقبول',
    labelShortEn: 'Accepted',
    icon: 'check',
    color: 'green',
    bgClass: 'bg-surface-elevated border border-border text-primary dark:text-green-400',
    dotClass: 'bg-primary',
    priority: PRIORITY.NORMAL,
    sound: 'success',
    duration: 5000,
    toast: true,
    vibrate: [150, 80, 150],
  },
  note_rejected: {
    label: 'مرفوض',
    labelEn: 'Rejected',
    labelShort: 'مرفوض',
    labelShortEn: 'Rejected',
    icon: 'x-circle',
    color: 'red',
    bgClass: 'bg-surface-elevated border border-red-200 dark:border-red-900/30 text-red-600 dark:text-red-400',
    dotClass: 'bg-red-500',
    priority: PRIORITY.HIGH,
    sound: 'error',
    duration: 7000,
    toast: true,
    vibrate: [250, 100, 250, 100, 250],
    requireInteraction: true,
  },
  dispatch_sent: {
    label: 'إرسالية واردة',
    labelEn: 'Incoming submission',
    labelShort: 'إرسالية',
    labelShortEn: 'Submission',
    icon: 'doc',
    color: 'amber',
    bgClass: 'bg-surface-elevated border border-border text-amber-600 dark:text-amber-400',
    dotClass: 'bg-amber-500',
    priority: PRIORITY.NORMAL,
    sound: 'normal',
    duration: 5500,
    toast: true,
    vibrate: [200, 100, 200],
  },
  dispatch_accepted: {
    label: 'تم القبول',
    labelEn: 'Accepted',
    labelShort: 'مقبول',
    labelShortEn: 'Accepted',
    icon: 'check',
    color: 'green',
    bgClass: 'bg-surface-elevated border border-border text-primary dark:text-green-400',
    dotClass: 'bg-primary',
    priority: PRIORITY.NORMAL,
    sound: 'success',
    duration: 5000,
    toast: true,
    vibrate: [150, 80, 150],
  },
  dispatch_rejected: {
    label: 'مرفوض',
    labelEn: 'Rejected',
    labelShort: 'مرفوض',
    labelShortEn: 'Rejected',
    icon: 'x-circle',
    color: 'red',
    bgClass: 'bg-surface-elevated border border-red-200 dark:border-red-900/30 text-red-600 dark:text-red-400',
    dotClass: 'bg-red-500',
    priority: PRIORITY.HIGH,
    sound: 'error',
    duration: 7000,
    toast: true,
    vibrate: [250, 100, 250, 100, 250],
    requireInteraction: true,
  },
  generic: {
    label: 'إشعار',
    labelEn: 'Notification',
    labelShort: 'جديد',
    labelShortEn: 'New',
    icon: 'bell',
    color: 'slate',
    bgClass: 'bg-surface-elevated border border-border text-text-secondary dark:text-text-secondary',
    dotClass: 'bg-text-muted dark:bg-text-muted',
    priority: PRIORITY.NORMAL,
    sound: 'normal',
    duration: 5000,
    toast: true,
    vibrate: [200, 100, 200],
  },
};

export function getTypeConfig(rawType) {
  if (!rawType) return TYPE_CONFIG.generic;
  const key = String(rawType).toLowerCase();
  
  if (TYPE_CONFIG[key]) return TYPE_CONFIG[key];
  
  for (const k of Object.keys(TYPE_CONFIG)) {
    if (k !== 'generic' && key.includes(k)) return TYPE_CONFIG[k];
  }
  
  return TYPE_CONFIG.generic;
}

export const PRIORITY_CONFIG = {
  normal: {
    toastDuration: 5000,
    sound: 'normal',
    animation: 'slide-in',
  },
  high: {
    toastDuration: 7000,
    sound: 'error',
    animation: 'shake',
    requireInteraction: false,
  },
  critical: {
    toastDuration: 9000,
    sound: 'critical',
    animation: 'pulse',
    requireInteraction: true,
  },
};

export const ICONS = {
  bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  check: 'M5 13l4 4L19 7',
  'x-circle': 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  inbox: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
};

export function priorityForType(type, dataPriority) {
  if (dataPriority && PRIORITY_CONFIG[dataPriority]) return dataPriority;
  const cfg = getTypeConfig(type);
  return cfg.priority || PRIORITY.NORMAL;
}
