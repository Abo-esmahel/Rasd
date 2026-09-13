
import { NotificationSoundManager } from './soundManager.js';
import { NotificationManager } from './notificationManager.js';

function init() {
  
  try {
    const qs = new URLSearchParams(location.search);
    if (qs.has('nosse') || localStorage.getItem('nosse') === '1') {
      console.warn('[NOTIFICATIONS] disabled via nosse');
      return;
    }
  } catch {}
  const userIdMeta = document.querySelector('meta[name="user-id"]')?.content || window.NOTIF_USER_ID || null;
  const userId = userIdMeta ? parseInt(userIdMeta, 10) : null;
  if (!userId) return;

  const debug = (() => {
    try {
      if (localStorage.getItem('notif_debug') === '1') return true;
      if (document.querySelector('meta[name="app-debug"]')?.content === '1') return true;
      if (new URLSearchParams(location.search).has('notif_debug')) return true;
    } catch {}
    return false;
  })();

  const soundManager = new NotificationSoundManager({ enabled: true, volume: 0.7, debug });
  soundManager.loadPersisted();
  soundManager.bindUnlockOnInteraction();

  const manager = new NotificationManager({ soundManager, userId, debug });
  window.NotificationSoundManager = soundManager;
  window.NotificationManagerInstance = manager;
  window.RASDNotifications = manager;

  manager.init().catch(e => console.error('[NOTIFICATIONS] init failed', e));

  if (debug) console.log('[NOTIFICATIONS] debug enabled (public fallback)', soundManager.getState());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

window.testNotificationSound = async (type = 'normal') => {
  const sm = window.NotificationSoundManager;
  if (sm) return sm.test(type);
};
