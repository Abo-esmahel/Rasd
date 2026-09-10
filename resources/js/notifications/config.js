

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

export const TYPE_CONFIG = {
  note_sent: {
    label: 'ملاحظة واردة',
    labelShort: 'واردة',
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
    labelShort: 'مقبول',
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
    labelShort: 'مرفوض',
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
    labelShort: 'إرسالية',
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
    labelShort: 'مقبول',
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
    labelShort: 'مرفوض',
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
    labelShort: 'جديد',
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
