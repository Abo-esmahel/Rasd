

import { NotificationSoundManager } from './notifications/soundManager.js';
import { NotificationManager } from './notifications/notificationManager.js';

try {
  
  if (import.meta.env.VITE_REVERB_APP_KEY) {
    await import('./echo.js');
  }
} catch (e) {
  console.warn('[ECHO] not loaded', e);
}

function initNotifications() {
  const userIdMeta = document.querySelector('meta[name="user-id"]')?.content || window.NOTIF_USER_ID || null;
  const userId = userIdMeta ? parseInt(userIdMeta, 10) : null;
  if (!userId) {
    
    return;
  }

  const debug = (() => {
    try {
      if (localStorage.getItem('notif_debug') === '1') return true;
      if (document.querySelector('meta[name="app-debug"]')?.content === '1') return true;
    } catch {}
    return false;
  })();

  const soundManager = new NotificationSoundManager({
    enabled: true,
    volume: 0.7,
    debug,
  });
  soundManager.loadPersisted();
  soundManager.bindUnlockOnInteraction();

  const manager = new NotificationManager({
    soundManager,
    userId,
    debug,
  });

  
  window.NotificationSoundManager = soundManager;
  window.NotificationManagerInstance = manager;
  window.RASDNotifications = manager;

  manager.init().catch(err => console.error('[NOTIFICATIONS] init failed', err));

  if (debug) {
    console.log('[NOTIFICATIONS] debug enabled');
    console.log('[NOTIFICATIONS] sound state', soundManager.getState());
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNotifications);
} else {
  initNotifications();
}

window.testNotificationSound = async (type = 'normal') => {
  const sm = window.NotificationSoundManager;
  if (sm) return sm.test(type);
};
