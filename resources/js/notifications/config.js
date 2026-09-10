/**
 * Notification Presentation Configuration
 * Single source of truth for icon, color, sound, duration, priority behavior.
 * No hardcoded colors scattered across Blade/JS.
 */

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

// Map raw type -> presentation meta
export const TYPE_CONFIG = {
  note_sent: {
    label: 'ملاحظة جديدة',
    labelShort: 'جديد',
    icon: 'inbox', // maps to SVG path key
    color: 'amber', // tailwind-like semantic: amber | green | red | slate
    bgClass: 'bg-amber-50 border-amber-200 text-amber-600',
    dotClass: 'bg-amber-400',
    priority: PRIORITY.NORMAL,
    sound: 'normal',
    duration: 5000, // toast ms
    toast: true,
    vibrate: [200, 100, 200],
  },
  note_accepted: {
    label: 'تم القبول',
    labelShort: 'مقبول',
    icon: 'check',
    color: 'green',
    bgClass: 'bg-[#eef4f0] border-[#cde7d6] text-[#0e6a38]',
    dotClass: 'bg-[#0e6a38]',
    priority: PRIORITY.NORMAL,
    sound: 'success',
    duration: 5000,
    toast: true,
    vibrate: [150, 80, 150],
  },
  note_rejected: {
    label: 'تم الرفض',
    labelShort: 'مرفوض',
    icon: 'x-circle',
    color: 'red',
    bgClass: 'bg-red-50 border-red-200 text-red-600',
    dotClass: 'bg-red-500',
    priority: PRIORITY.HIGH,
    sound: 'error',
    duration: 7000,
    toast: true,
    vibrate: [250, 100, 250, 100, 250],
    requireInteraction: true,
  },
  dispatch_sent: {
    label: 'إرسالية جديدة',
    labelShort: 'إرسالية',
    icon: 'inbox',
    color: 'amber',
    bgClass: 'bg-amber-50 border-amber-200 text-amber-600',
    dotClass: 'bg-amber-400',
    priority: PRIORITY.NORMAL,
    sound: 'normal',
    duration: 5500,
    toast: true,
    vibrate: [200, 100, 200],
  },
  dispatch_accepted: {
    label: 'تم قبول الإرسالية',
    labelShort: 'مقبولة',
    icon: 'check',
    color: 'green',
    bgClass: 'bg-[#eef4f0] border-[#cde7d6] text-[#0e6a38]',
    dotClass: 'bg-[#0e6a38]',
    priority: PRIORITY.NORMAL,
    sound: 'success',
    duration: 5000,
    toast: true,
    vibrate: [150, 80, 150],
  },
  dispatch_rejected: {
    label: 'تم رفض الإرسالية',
    labelShort: 'مرفوضة',
    icon: 'x-circle',
    color: 'red',
    bgClass: 'bg-red-50 border-red-200 text-red-600',
    dotClass: 'bg-red-500',
    priority: PRIORITY.HIGH,
    sound: 'error',
    duration: 7000,
    toast: true,
    vibrate: [250, 100, 250, 100, 250],
    requireInteraction: true,
  },
  generic: {
    label: 'إشعار جديد',
    labelShort: 'جديد',
    icon: 'bell',
    color: 'slate',
    bgClass: 'bg-surface-50 border-surface-300 text-ink-600',
    dotClass: 'bg-ink-400',
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
  // try exact
  if (TYPE_CONFIG[key]) return TYPE_CONFIG[key];
  // try contains
  for (const k of Object.keys(TYPE_CONFIG)) {
    if (k !== 'generic' && key.includes(k)) return TYPE_CONFIG[k];
  }
  // data.type field may be 'note_rejected' etc.
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

// Browser notification icons
export const ICONS = {
  bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  check: 'M5 13l4 4L19 7',
  'x-circle': 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  inbox: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
};

export function priorityForType(type, dataPriority) {
  if (dataPriority && PRIORITY_CONFIG[dataPriority]) return dataPriority;
  const cfg = getTypeConfig(type);
  return cfg.priority || PRIORITY.NORMAL;
}
