/**
 * NotificationManager — Central orchestrator for notification lifecycle
 * 
 * Responsibilities:
 *  - receive()        : single entry point for incoming payloads (SSE, Echo, polling)
 *  - deduplicate()    : prevents rerender on reconnect / duplicate broadcast
 *  - renderToast()    : animated, accessible, priority-aware
 *  - updateBadge()    : backend truth (unread_count)
 *  - updateCenter()   : dropdown / panel list
 *  - playSound()      : via SoundManager respecting user prefs & autoplay policy
 *  - markRead() etc.
 *  - handleClick()
 *  - offline/reconnect state
 *  - multi-tab coordination via BroadcastChannel + focus awareness
 *  - debug logging (toggleable, disabled in prod)
 * 
 * No duplication of this logic inside Blade.
 */

import { getTypeConfig, priorityForType, PRIORITY } from './config.js';

export class NotificationManager {
  constructor(options = {}) {
    this.soundManager = options.soundManager;
    this.config = options.config || {};
    this.debug = options.debug ?? this.isDebugEnabled();
    this.userId = options.userId ?? null;

    // DOM refs (set in init)
    this.badgeEl = null;
    this.dropdownEl = null;
    this.listEl = null;
    this.bellEl = null;
    this.markAllBtn = null;
    this.permissionBanner = null;
    this.toastContainer = null;
    this.liveRegion = null;

    // State
    this.unreadCount = 0;
    this.notifications = []; // frontmost 20
    this.seenIds = new Set(); // dedup
    this.renderedToastIds = new Set();
    this.lastEventId = null;
    this.lastServerTime = null;
    this.connectionState = 'DISCONNECTED'; // CONNECTED | DISCONNECTED | RECONNECTING
    this.sse = null;
    this.pollTimer = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 8;

    // Multi-tab
    this.tabId = Math.random().toString(36).slice(2, 9);
    this.channel = null;
    try {
      this.channel = new BroadcastChannel('rasd_notifications');
      this.channel.onmessage = (e) => this.handleBroadcastMessage(e);
    } catch {}

    // Preference cache
    this.prefs = {
      sound_enabled: true,
      toast_enabled: true,
      volume: 70,
    };

    this.boundFetch = this.fetchNotifications.bind(this);

    this.log('[MANAGER] init', { userId: this.userId, debug: this.debug });
  }

  isDebugEnabled() {
    try {
      if (localStorage.getItem('notif_debug') === '1') return true;
      // also enable when APP_DEBUG true? We expose via meta?
      const metaDebug = document.querySelector('meta[name="app-debug"]')?.content;
      if (metaDebug === '1') return true;
      // fallback: check URL ?debug=notif
      if (new URLSearchParams(location.search).has('notif_debug')) return true;
    } catch {}
    return false;
  }

  log(...args) {
    if (this.debug) console.log(...args);
  }

  async init() {
    // Resolve DOM
    this.badgeEl = document.getElementById('notification-badge');
    this.dropdownEl = document.getElementById('notification-dropdown');
    this.listEl = document.getElementById('notification-list');
    this.bellEl = document.getElementById('notification-bell');
    this.markAllBtn = document.getElementById('mark-all-read');
    this.permissionBanner = document.getElementById('notification-permission-banner');
    this.toastContainer = this.ensureToastContainer();
    this.liveRegion = this.ensureLiveRegion();

    // Load prefs
    await this.loadPreferences();
    // apply to soundManager
    if (this.soundManager) {
      this.soundManager.loadPersisted();
      await this.soundManager.loadFromServer();
      this.prefs.sound_enabled = this.soundManager.enabled;
      this.prefs.volume = Math.round(this.soundManager.volume * 100);
      this.bindSoundState();
    }

    // Seed seenIds from server (fetch initial)
    await this.fetchNotifications({ silent: true });

    // Bind UI events
    this.bindUI();

    // Connect real-time
    this.connect();

    // Listen to visibility/focus for adaptive behavior
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        this.log('[VISIBILITY] visible -> sync');
        this.soundManager?.unlock();
        this.sync();
      }
    });
    window.addEventListener('focus', () => {
      this.soundManager?.unlock();
      this.sync();
    });
    window.addEventListener('online', () => {
      this.log('[NETWORK] online -> reconnect');
      this.setConnectionState('RECONNECTING');
      this.reconnect();
    });
    window.addEventListener('offline', () => {
      this.log('[NETWORK] offline');
      this.setConnectionState('DISCONNECTED');
    });

    // Periodic drift sync every 60s (fallback safety) — but not every 1s
    setInterval(() => this.syncIfNeeded(), 60000);

    this.log('[MANAGER] ready');
  }

  bindSoundState() {
    window.addEventListener('notification:sound-state', (e) => {
      this.updatePermissionBanner();
    });
  }

  bindUI() {
    this.bellEl?.addEventListener('click', async (e) => {
      e.stopPropagation();
      await this.soundManager?.unlock();
      this.dropdownEl?.classList.toggle('hidden');
      if (!this.dropdownEl?.classList.contains('hidden')) {
        await this.fetchNotifications({ silent: false });
        this.updatePermissionBanner();
      }
    });

    document.addEventListener('click', (e) => {
      if (!this.bellEl?.contains(e.target) && !this.dropdownEl?.contains(e.target)) {
        this.dropdownEl?.classList.add('hidden');
      }
    });

    this.markAllBtn?.addEventListener('click', async () => {
      await this.markAllAsRead();
    });

    // Close dropdown on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.dropdownEl?.classList.add('hidden');
        // also close any toast focused
        this.toastContainer?.querySelectorAll('[data-toast]').forEach(el => {
          if (document.activeElement && el.contains(document.activeElement)) el.remove();
        });
      }
    });

    // Delegated click for notification items (mark read on click)
    this.listEl?.addEventListener('click', async (e) => {
      const link = e.target.closest('a[data-id]');
      if (!link) return;
      const id = link.getAttribute('data-id');
      // optimistic mark read after navigation? we mark before navigation
      // Don't prevent default; let navigation happen but mark in background
      if (id) {
        try { await this.markOneAsRead(id, { silent: true }); } catch {}
      }
    });

    // Permission banner actions
    const enableBtn = document.getElementById('enable-notif-btn');
    const testSoundBtn = document.getElementById('test-notif-sound');
    const globalEnableBtn = document.getElementById('global-sound-enable');
    const globalDismissBtn = document.getElementById('global-sound-dismiss');
    const handleEnable = async () => {
      await this.soundManager?.unlock();
      if (window.Notification && Notification.permission === 'default') {
        try { await Notification.requestPermission(); } catch {}
      }
      const ok = await this.soundManager?.play('normal');
      this.updatePermissionBanner();
      this.sync();
      // إخفاء البانر العام بعد النجاح
      if (ok) {
        document.getElementById('global-sound-banner')?.classList.add('hidden');
        try { sessionStorage.setItem('global_sound_dismissed', '1'); } catch {}
      }
      // toast تأكيد
      this.showToast({
        id: 'enable-' + Date.now(),
        type: 'Test',
        data: {
          message: ok ? 'تم تفعيل الصوت بنجاح ✅ — ستصلك النغمة عند الإشعار القادم' : 'تعذر تفعيل الصوت — تأكد من عدم كتم التبويب',
          type: ok ? 'note_accepted' : 'note_rejected',
          url: null,
        },
        created_at_human: 'الآن',
      }, { force: true, sound: false });
    };
    enableBtn?.addEventListener('click', handleEnable);
    globalEnableBtn?.addEventListener('click', handleEnable);
    globalDismissBtn?.addEventListener('click', () => {
      document.getElementById('global-sound-banner')?.classList.add('hidden');
      try { sessionStorage.setItem('global_sound_dismissed', '1'); } catch {}
    });
    testSoundBtn?.addEventListener('click', async () => {
      await this.soundManager?.unlock();
      const ok = await this.soundManager?.play('normal');
      this.showToast({
        id: 'test-' + Date.now(),
        type: 'Test',
        data: {
          message: ok ? 'تم تشغيل نغمة الإشعار بنجاح ✅ — إذا لم تسمعها تأكد من مستوى الصوت' : 'تعذر تشغيل الصوت — تحقق من السماح للمتصفح',
          type: 'note_sent',
          url: null,
        },
        created_at_human: 'الآن',
      }, { force: true, sound: false });
    });

    // Settings UI (if present)
    const soundToggle = document.getElementById('notif-sound-toggle');
    const volumeSlider = document.getElementById('notif-volume-slider');
    const toastToggle = document.getElementById('notif-toast-toggle');

    if (soundToggle) {
      soundToggle.checked = this.prefs.sound_enabled;
      soundToggle.addEventListener('change', async () => {
        this.prefs.sound_enabled = soundToggle.checked;
        this.soundManager?.setEnabled(soundToggle.checked);
        await this.persistPreferences();
      });
    }
    if (volumeSlider) {
      volumeSlider.value = this.prefs.volume;
      const volLabel = document.getElementById('notif-volume-label');
      if (volLabel) volLabel.textContent = this.prefs.volume + '%';
      volumeSlider.addEventListener('input', () => {
        const v = parseInt(volumeSlider.value);
        if (volLabel) volLabel.textContent = v + '%';
        this.soundManager?.setVolume(v);
        this.prefs.volume = v;
      });
      volumeSlider.addEventListener('change', async () => {
        await this.persistPreferences();
        // preview sound on change end
        if (this.prefs.sound_enabled) this.soundManager?.play('normal');
      });
    }
    if (toastToggle) {
      toastToggle.checked = this.prefs.toast_enabled;
      toastToggle.addEventListener('change', async () => {
        this.prefs.toast_enabled = toastToggle.checked;
        try { localStorage.setItem('notif_toast_enabled', toastToggle.checked ? '1' : '0'); } catch {}
        await this.persistPreferences();
      });
      try {
        const t = localStorage.getItem('notif_toast_enabled');
        if (t !== null) {
          toastToggle.checked = t === '1';
          this.prefs.toast_enabled = t === '1';
        }
      } catch {}
    }

    this.updatePermissionBanner();
    setTimeout(() => this.updatePermissionBanner(), 900);
  }

  async loadPreferences() {
    try {
      const res = await fetch('/notifications/preferences', { headers: { Accept: 'application/json' } });
      if (res.ok) {
        const data = await res.json();
        this.prefs = {
          sound_enabled: data.sound_enabled ?? true,
          toast_enabled: data.toast_enabled ?? true,
          volume: data.volume ?? 70,
          desktop_enabled: data.desktop_enabled ?? true,
          sound_theme: data.sound_theme ?? 'default',
        };
        this.log('[PREFS] loaded', this.prefs);
      }
    } catch {}
    // localStorage overrides for toast
    try {
      const t = localStorage.getItem('notif_toast_enabled');
      if (t !== null) this.prefs.toast_enabled = t === '1';
    } catch {}
  }

  async persistPreferences() {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      await fetch('/notifications/preferences', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          sound_enabled: this.prefs.sound_enabled,
          toast_enabled: this.prefs.toast_enabled,
          volume: this.prefs.volume,
        }),
      });
    } catch {}
    try {
      localStorage.setItem('notif_toast_enabled', this.prefs.toast_enabled ? '1' : '0');
    } catch {}
  }

  // ──────────────────────────────────────────────
  // Connection
  // ──────────────────────────────────────────────

  connect() {
    // FAST HTTP: php -S أحادي الخيط على Windows لا يتحمل SSE طويل — على HTTP (غير آمن) استخدم polling دائماً
    // هذا يشمل http://127.0.0.1 و http://10.150.2.29 و http://192.168.x.x — فقط https://rasd.home.arpa خلف Caddy يبقى SSE
    const isLocalSingleThread = (() => {
      try {
        if (new URLSearchParams(location.search).has('nosse')) return true;
        // أي HTTP غير آمن أو localhost يجب أن يستخدم polling ليبقى single-worker حراً
        if (location.protocol === 'http:') return true;
        if (!window.isSecureContext) return true;
        if (location.hostname === '127.0.0.1' || location.hostname === 'localhost' || location.hostname === '10.150.2.29' || location.hostname.endsWith('.lan') || location.hostname.endsWith('.home.arpa') === false && location.hostname.match(/^\d+\.\d+\.\d+\.\d+$/)) {
          // LAN IP مباشر
          return true;
        }
      } catch {}
      return false;
    })();
    if (isLocalSingleThread) {
      this.log('[CONNECT] local cli-server detected -> polling mode (skip SSE to keep single worker fast)');
      this.connectPolling();
      this.tryConnectEcho();
      return;
    }

    // Try SSE primary (production LAN behind Caddy — concurrent, SSE is fine)
    if (typeof EventSource !== 'undefined') {
      this.connectSSE();
    } else {
      this.log('[CONNECT] EventSource not supported -> fallback polling');
      this.connectPolling();
    }

    // Also attempt Echo/Reverb if available (optional, non-blocking)
    this.tryConnectEcho();
  }

  connectSSE() {
    if (this.sse) {
      try { this.sse.close(); } catch {}
      this.sse = null;
    }

    const params = new URLSearchParams();
    if (this.lastEventId) params.set('last_id', this.lastEventId);
    if (this.lastServerTime) params.set('after', this.lastServerTime);
    const url = '/notifications/stream' + (params.toString() ? '?' + params.toString() : '');

    this.log('[SSE] connecting', url);
    this.setConnectionState('RECONNECTING');

    try {
      const es = new EventSource(url, { withCredentials: true });
      this.sse = es;

      es.addEventListener('open', () => {
        this.log('[SSE] connected');
        this.setConnectionState('CONNECTED');
        this.reconnectAttempts = 0;
      });

      es.addEventListener('init', (e) => {
        try {
          const data = JSON.parse(e.data);
          this.log('[SSE] init', data);
          if (typeof data.unread_count === 'number') this.updateBadge(data.unread_count);
          if (data.server_time) this.lastServerTime = data.server_time;
        } catch {}
      });

      es.addEventListener('notification', (e) => {
        try {
          const data = JSON.parse(e.data);
          this.log('[SSE] notification', data);
          if (e.lastEventId) this.lastEventId = e.lastEventId;
          if (data.notification) {
            this.receive(data.notification, data.unread_count, { source: 'sse' });
          }
          if (data.server_time) this.lastServerTime = data.server_time;
        } catch (err) {
          this.log('[SSE] parse error', err);
        }
      });

      es.addEventListener('sync', (e) => {
        try {
          const data = JSON.parse(e.data);
          if (typeof data.unread_count === 'number') {
            this.log('[SSE] sync unread', data.unread_count);
            this.updateBadge(data.unread_count);
            // Broadcast to other tabs
            this.broadcast({ type: 'badge_sync', unread_count: data.unread_count });
          }
        } catch {}
      });

      es.addEventListener('ping', () => {
        this.log('[SSE] ping');
      });

      es.addEventListener('error', (e) => {
        this.log('[SSE] error', e);
        // If server returned 204 SSE disabled (local cli), fallback to polling immediately
        // EventSource error on 204 triggers CLOSED — detect and switch
        if (es.readyState === EventSource.CLOSED) {
          // Check if we should fallback to polling due to local disable
          // The server sends 204; EventSource will treat as error and close — we detect via polling fallback
          this.setConnectionState('DISCONNECTED');
          // على HTTP أو 204 من الخادم الأحادي، حوّل لـ polling فوراً بدل إعادة محاولة SSE
          const isHttp = location.protocol === 'http:' || !window.isSecureContext;
          const isLocal = location.hostname === '127.0.0.1' || location.hostname === 'localhost';
          if (isLocal || isHttp) {
            this.log('[SSE] http/local 204 -> switching to polling');
            this.connectPolling();
            return;
          }
          this.scheduleReconnect();
        } else {
          this.setConnectionState('RECONNECTING');
        }
      });

      // Also catch generic message (fallback)
      es.onmessage = (e) => {
        this.log('[SSE] message', e.data);
      };

    } catch (err) {
      this.log('[SSE] connect failed', err);
      this.setConnectionState('DISCONNECTED');
      this.connectPolling();
    }
  }

  connectPolling() {
    const isHttp = location.protocol === 'http:';
    const isLocal = location.hostname === '127.0.0.1' || location.hostname === 'localhost';
    const interval = (isHttp || isLocal) ? 5000 : 15000;
    this.log(`[POLL] starting fallback polling (every ${interval/1000}s)${isHttp?' — http fast':''}${isLocal?' — local':''}`);
    this.setConnectionState('CONNECTED'); // polling considered connected (degraded)
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = setInterval(() => this.fetchNotifications({ silent: true }), interval);
    // immediate fetch
    this.fetchNotifications({ silent: true });
  }

  scheduleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      this.log('[RECONNECT] max attempts reached -> fallback polling');
      this.connectPolling();
      return;
    }
    const delay = Math.min(30000, 1000 * Math.pow(1.8, this.reconnectAttempts));
    this.reconnectAttempts++;
    this.log(`[RECONNECT] attempt ${this.reconnectAttempts} in ${Math.round(delay)}ms`);
    setTimeout(() => {
      if (this.sse) {
        try { this.sse.close(); } catch {}
      }
      this.connectSSE();
    }, delay);
  }

  reconnect() {
    if (this.sse) {
      try { this.sse.close(); } catch {}
      this.sse = null;
    }
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
    this.reconnectAttempts = 0;
    this.connect();
  }

  sync() {
    this.fetchNotifications({ silent: true });
  }

  syncIfNeeded() {
    // على HTTP نزامن حتى لو مخفي (الـ polling هو المصدر الوحيد للإشعارات)
    // المتصفح قد يخنق الـ interval في الخلفية لكن نحاول دائماً
    this.sync();
  }

  setConnectionState(state) {
    this.connectionState = state;
    this.log('[CONNECTION]', state);
    // Optional UI indicator (if element exists)
    const el = document.getElementById('notification-connection-status');
    if (el) {
      el.textContent = state;
      el.dataset.state = state.toLowerCase();
    }
    window.dispatchEvent(new CustomEvent('notification:connection', { detail: { state } }));
  }

  // Try to connect via Laravel Echo (Reverb) if library present
  tryConnectEcho() {
    // Echo is optional: if window.Echo exists (injected via Vite), subscribe
    if (!window.Echo) {
      this.log('[ECHO] not available (skipping)');
      return;
    }
    if (!this.userId) {
      this.log('[ECHO] no userId');
      return;
    }
    try {
      const channelName = `notifications.${this.userId}`;
      this.log('[ECHO] subscribing', channelName);
      window.Echo.private(channelName)
        .listen('.notification.created', (e) => {
          this.log('[ECHO] notification.created', e);
          if (e.notification) this.receive(e.notification, e.unread_count, { source: 'echo' });
        });
      // Also listen for system channel if needed
      this.log('[ECHO] subscribed');
    } catch (err) {
      this.log('[ECHO] subscribe failed', err);
    }
  }

  // ──────────────────────────────────────────────
  // Core receive pipeline
  // ──────────────────────────────────────────────

  /**
   * Central entry for any incoming notification
   * @param {object} notification normalized payload {id, type, data, read_at, created_at}
   * @param {number|null} unreadCount backend truth (if null, we keep current)
   * @param {object} meta {source: 'sse'|'echo'|'poll'}
   */
  async receive(notification, unreadCount = null, meta = {}) {
    if (!notification || !notification.id) {
      this.log('[RECEIVE] invalid payload', notification);
      return;
    }

    const id = String(notification.id);

    // Deduplicate
    if (this.seenIds.has(id)) {
      this.log('[NOTIFICATION] duplicate ignored', id);
      return;
    }
    // Also persistent dedup via sessionStorage (survives soft reload)
    try {
      const key = 'notif_seen_' + id;
      if (sessionStorage.getItem(key)) {
        this.log('[NOTIFICATION] duplicate ignored (session)', id);
        this.seenIds.add(id);
        return;
      }
      sessionStorage.setItem(key, '1');
      // keep only last 200 to avoid bloating
      // prune oldest if needed (simple: if > 300 keys, clear)
      // omitted for brevity
    } catch {}

    this.seenIds.add(id);
    this.lastEventId = id;
    this.lastServerTime = notification.created_at || new Date().toISOString();

    // Update internal list (prepend)
    this.notifications.unshift(notification);
    if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);

    // Badge: authoritative from backend if provided, otherwise increment
    if (typeof unreadCount === 'number') {
      this.updateBadge(unreadCount);
    } else {
      // optimistic increment if unread
      if (!notification.read_at) this.updateBadge(this.unreadCount + 1);
    }

    // Broadcast to other tabs (badge sync)
    this.broadcast({ type: 'notification_received', notification, unread_count: this.unreadCount });

    // Decide if this tab should present (toast/sound)
    const shouldPresent = this.shouldPresentInThisTab(notification);

    this.log('[NOTIFICATION] received', { id, type: notification.type, priority: notification.data?.priority, shouldPresent, source: meta.source });

    if (!shouldPresent) {
      // Still update center list silently
      this.updateCenter();
      return;
    }

    // Check user prefs for muted types
    if (this.isMuted(notification)) {
      this.log('[NOTIFICATION] muted type, skipping toast/sound', notification.type);
      this.updateCenter();
      return;
    }

    // Update Center
    this.updateCenter();

    // Toast (if enabled)
    if (this.prefs.toast_enabled) {
      this.renderToast(notification);
    }

    // Sound (if enabled and not muted) — try unlock aggressively if blocked
    if (this.prefs.sound_enabled) {
      const cfg = getTypeConfig(notification.data?.type || notification.type);
      const soundType = this.mapSoundType(cfg.sound, notification.data?.priority);
      let played = await this.soundManager?.play(soundType);
      if (!played && this.soundManager?.blocked) {
        this.log('[SOUND] blocked -> trying unlock + retry');
        try{ await this.soundManager?.unlock(); played = await this.soundManager?.play(soundType); }catch{}
        if(!played) this.updatePermissionBanner();
      }
    }

    // Live region for accessibility
    this.announceToScreenReader(notification);

    // Browser Notification (if permission granted and tab hidden)
    this.maybeShowBrowserNotification(notification);
  }

  shouldPresentInThisTab(notification) {
    // على HTTP وحتى لو التبويب مخفي/بالخلفية نريد الصوت — نستخدم BroadcastChannel لتجنب التكرار
    // سابقاً كنا نمنع hidden/not focused تماماً مما يمنع الصوت على الهاتف عندما يكون التطبيق في الخلفية
    const isHttp = location.protocol === 'http:';
    if (document.hidden) {
      // على HTTP نسمح لتبويب واحد مخفي بتشغيل الصوت (عبر الـ claim أدناه)، على HTTPS نعتمد على Push
      // إذا كان هناك أكثر من تبويب مخفي، الـ claim يضمن تبويب واحد فقط يصدر الصوت
      this.log('[MULTI-TAB] hidden -> still try claim for background sound (HTTP)');
      // لا نرجع false مباشرة — نكمل للـ claim
    } else if (!document.hasFocus()) {
      // إذا كان هناك تبويب غير مركز لكن ليس مخفي (نافذتان جنباً إلى جنب) نستخدم الـ claim أيضاً
      this.log('[MULTI-TAB] not focused -> try claim');
    }

    // Additional claim via localStorage to handle race where two windows both focused (unlikely but possible)
    // We do sync check with 60ms delay verification
    const claimKey = 'notif_claim_' + notification.id;
    try {
      const existing = localStorage.getItem(claimKey);
      if (existing && existing !== this.tabId) {
        this.log('[MULTI-TAB] already claimed by', existing);
        return false;
      }
      if (!existing) {
        localStorage.setItem(claimKey, this.tabId);
        // verify after short delay: if another tab overwrote, we lose
        // But we already decided to present; we could check synchronously again after 0ms
        // For simplicity, we register cleanup and assume first claim wins if we set before check.
        // To ensure deterministic, we check again instantly: if we set and another tab set within same tick, last write wins
        // So we re-read: if localStorage.getItem(claimKey) !== tabId, we lost
        const verify = localStorage.getItem(claimKey);
        if (verify !== this.tabId) {
          this.log('[MULTI-TAB] claim lost race');
          return false;
        }
        // Schedule cleanup after 10s
        setTimeout(() => {
          if (localStorage.getItem(claimKey) === this.tabId) localStorage.removeItem(claimKey);
        }, 10000);
      }
    } catch {}

    // Broadcast claim to other tabs so they immediately know (even before storage event)
    this.broadcast({ type: 'claim', id: notification.id, tabId: this.tabId });

    return true;
  }

  isMuted(notification) {
    try {
      const t = (notification.data?.type || notification.type || '').toLowerCase();
      if (Array.isArray(this.prefs.muted_types) && this.prefs.muted_types.includes(t)) return true;
    } catch {}
    return false;
  }

  mapSoundType(cfgSound, priority) {
    if (priority === 'critical' || priority === 'high') return 'error';
    if (cfgSound === 'success') return 'success';
    if (cfgSound === 'error') return 'error';
    if (cfgSound === 'critical') return 'critical';
    return 'normal';
  }

  // ──────────────────────────────────────────────
  // Badge
  // ──────────────────────────────────────────────

  updateBadge(count) {
    const n = Math.max(0, parseInt(count) || 0);
    this.unreadCount = n;
    this.log('[BADGE] updated', n);
    window.dispatchEvent(new CustomEvent('notification:badge', { detail: { count: n } }));

    if (!this.badgeEl) return;

    if (n > 0) {
      this.badgeEl.textContent = n > 99 ? '99+' : String(n);
      this.badgeEl.classList.remove('hidden');
      this.badgeEl.classList.add('flex');
      // animation + ARIA
      this.badgeEl.setAttribute('aria-label', `${n} إشعارات غير مقروءة`);
      // subtle pulse if increment
      this.badgeEl.animate?.([{ transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 300, easing: 'ease-out' });
      // Title
      try {
        const base = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = `(${n}) ${base}`;
      } catch {}
    } else {
      this.badgeEl.classList.add('hidden');
      this.badgeEl.classList.remove('flex');
      this.badgeEl.removeAttribute('aria-label');
      try { document.title = document.title.replace(/^\(\d+\)\s*/, ''); } catch {}
    }

    // Also sync to BroadcastChannel so other tabs update instantly without waiting for their own SSE
    // (already broadcasted in receive, but also for mark-read flows)
  }

  // ──────────────────────────────────────────────
  // Toast
  // ──────────────────────────────────────────────

  ensureToastContainer() {
    let c = document.getElementById('notification-toast-container');
    if (c) return c;
    c = document.createElement('div');
    c.id = 'notification-toast-container';
    c.className = 'fixed bottom-4 left-4 right-4 sm:right-auto sm:left-4 z-[70] flex flex-col gap-2 pointer-events-none max-w-[420px] sm:w-[380px]';
    c.setAttribute('aria-live', 'polite');
    c.setAttribute('aria-atomic', 'false');
    document.body.appendChild(c);
    return c;
  }

  ensureLiveRegion() {
    let el = document.getElementById('notification-live-region');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'notification-live-region';
    el.className = 'sr-only';
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-atomic', 'true');
    document.body.appendChild(el);
    return el;
  }

  announceToScreenReader(notification) {
    if (!this.liveRegion) return;
    const data = notification.data || {};
    const msg = data.message || 'إشعار جديد';
    this.liveRegion.textContent = msg;
    // Clear after a bit so next announcement works
    setTimeout(() => { if (this.liveRegion.textContent === msg) this.liveRegion.textContent = ''; }, 4000);
  }

  escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  renderToast(notification, opts = {}) {
    const id = String(notification.id);
    if (!opts.force && this.renderedToastIds.has(id)) {
      this.log('[TOAST] duplicate toast ignored', id);
      return;
    }
    this.renderedToastIds.add(id);
    // prune after 100
    if (this.renderedToastIds.size > 100) {
      const first = this.renderedToastIds.values().next().value;
      this.renderedToastIds.delete(first);
    }

    const data = notification.data || {};
    const rawType = data.type || notification.type || 'generic';
    const cfg = getTypeConfig(rawType);
    const priority = priorityForType(rawType, data.priority);
    const isHigh = priority === 'high' || priority === 'critical';

    // Build toast
    const toast = document.createElement('div');
    toast.dataset.toast = id;
    toast.setAttribute('role', isHigh ? 'alert' : 'status');
    toast.setAttribute('aria-live', isHigh ? 'assertive' : 'polite');
    toast.tabIndex = 0;
    // Base + priority styling
    const baseClasses = 'pointer-events-auto w-full bg-white rounded-2xl shadow-2xl border overflow-hidden flex flex-col transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0e6a38] focus-visible:ring-offset-2';
    const borderClass = isHigh ? 'border-red-200' : 'border-[#e6e9e1]';
    toast.className = `${baseClasses} ${borderClass}`;
    toast.style.transform = 'translateY(12px)';
    toast.style.opacity = '0';

    const title = this.escapeHtml(this.titleForType(rawType, data));
    const body = this.escapeHtml((data.message || data.reason || 'لديك إشعار جديد').substring(0, 180));
    const time = this.escapeHtml(notification.created_at_human || data.created_at_human || 'الآن');
    // Priority accent bar
    const accent = isHigh ? 'bg-red-500' : (cfg.color === 'green' ? 'bg-[#0e6a38]' : (cfg.color === 'amber' ? 'bg-amber-500' : 'bg-ink-300'));
    const iconBg = cfg.bgClass || 'bg-surface-50';
    const url = this.resolveUrl(notification);

    toast.innerHTML = `
      <div class="h-1 w-full ${accent}"></div>
      <div class="p-4 flex gap-3">
        <div class="w-10 h-10 rounded-xl ${iconBg} border flex items-center justify-center shrink-0" aria-hidden="true">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="${this.iconPathFor(cfg.icon)}"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-ink-800 leading-5">${title}</div>
          <div class="text-xs text-ink-500 leading-5 mt-1 line-clamp-2">${body}</div>
          <div class="text-[11px] text-ink-400 mt-1.5">${time}</div>
        </div>
        <button type="button" aria-label="إغلاق الإشعار" class="shrink-0 w-8 h-8 rounded-lg hover:bg-surface-50 flex items-center justify-center text-ink-300 hover:text-ink-600 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      ${url ? `<div class="px-4 pb-3 -mt-1"><span class="inline-flex items-center gap-1 text-xs font-bold text-[#0e6a38]">عرض التفاصيل <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span></div>` : ''}
    `;

    // Interactions
    const closeBtn = toast.querySelector('button');
    closeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      this.dismissToast(toast);
    });
    toast.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.dismissToast(toast);
      if (e.key === 'Enter' || e.key === ' ') {
        if (url) window.location.href = url;
      }
    });
    if (url) {
      toast.style.cursor = 'pointer';
      toast.addEventListener('click', () => {
        this.handleClick(notification);
      });
    }

    this.toastContainer.appendChild(toast);

    // Animate in (respect reduced motion)
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced) {
      toast.style.transform = 'translateY(0)';
      toast.style.opacity = '1';
    } else {
      requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
      });
      // high priority shake
      if (isHigh && toast.animate) {
        toast.animate(
          [{ transform: 'translateX(0)' }, { transform: 'translateX(-2px)' }, { transform: 'translateX(2px)' }, { transform: 'translateX(0)' }],
          { duration: 400, easing: 'ease-out', delay: 200 }
        );
      }
    }

    this.log('[TOAST] rendered', id, rawType);

    // Auto dismiss
    const duration = opts.duration ?? cfg.duration ?? 5000;
    const timer = setTimeout(() => this.dismissToast(toast), duration);
    toast.addEventListener('mouseenter', () => clearTimeout(timer));
    toast.addEventListener('focusin', () => clearTimeout(timer));
    toast.addEventListener('mouseleave', () => {
      setTimeout(() => this.dismissToast(toast), 2000);
    });

    window.dispatchEvent(new CustomEvent('notification:toast', { detail: { notification } }));
  }

  dismissToast(toast) {
    if (!toast || !toast.parentNode) return;
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced) {
      toast.remove();
      return;
    }
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
    setTimeout(() => toast.remove(), 300);
  }

  titleForType(rawType, data) {
    const cfg = getTypeConfig(rawType);
    // Use data.message first? but title should be concise
    if (cfg.label) return cfg.label;
    return 'إشعار جديد';
  }

  iconPathFor(iconKey) {
    const map = {
      bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
      check: 'M5 13l4 4L19 7',
      'x-circle': 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      inbox: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    };
    return map[iconKey] || map.bell;
  }

  resolveUrl(notification) {
    const data = notification.data || {};
    if (data.url) return data.url;
    if (data.note_id) return `/notes/${data.note_id}`;
    if (data.submission_id) return `/general-submissions/${data.submission_id}`;
    if (data.general_submission_id) return `/general-submissions/${data.general_submission_id}`;
    return '/notifications';
  }

  handleClick(notification) {
    const url = this.resolveUrl(notification);
    this.log('[CLICK] notification', notification.id, url);
    // Mark as read optimistically before navigation
    this.markOneAsRead(notification.id, { silent: true }).catch(() => {});
    window.location.href = url;
  }

  // ──────────────────────────────────────────────
  // Center (dropdown)
  // ──────────────────────────────────────────────

  updateCenter() {
    if (!this.listEl) return;

    if (this.notifications.length === 0) {
      this.listEl.innerHTML = '<div class="p-10 text-center"><div class="w-12 h-12 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center mx-auto"><svg class="w-6 h-6 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.313 6.022c1.543.94 3.31-.826 2.37-2.37a1.724 1.724 0 001.065-2.572"/></svg></div><p class="mt-3 text-sm font-bold text-ink-600">لا توجد إشعارات</p><p class="mt-1 text-xs text-ink-400">ستظهر الإشعارات الواردة هنا فور وصولها</p></div>';
      return;
    }

    this.listEl.innerHTML = this.notifications.slice(0, 20).map(n => {
      const data = n.data || {};
      const rawType = data.type || n.type || 'generic';
      const cfg = getTypeConfig(rawType);
      const isUnread = !n.read_at;
      const url = this.escapeHtml(this.resolveUrl(n));
      const title = this.escapeHtml(data.title || cfg.label || 'إشعار');
      const body = this.escapeHtml(data.message || '');
      const time = this.escapeHtml(n.created_at_human || 'الآن');
      const sender = this.escapeHtml(data.sender_name || data.processor_name || '');
      const meta = [];
      if (data.camera_number) meta.push(`كاميرا ${this.escapeHtml(String(data.camera_number))}`);
      if (data.floor_number) meta.push(`طابق ${this.escapeHtml(String(data.floor_number))}`);
      const metaStr = meta.join(' · ');
      const reason = data.reason ? `<div class="mt-2 text-xs leading-5 text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-2 line-clamp-2">${this.escapeHtml(data.reason)}</div>` : '';
      const accent = isUnread ? (cfg.priority === 'high' ? 'border-r-red-500' : cfg.color === 'green' ? 'border-r-[#0e6a38]' : cfg.color === 'amber' ? 'border-r-amber-500' : 'border-r-ink-300') : 'border-r-transparent';
      const bg = isUnread ? 'bg-white' : 'bg-[#fdfcfa]/70';
      const iconBg = cfg.bgClass;

      return `
        <a href="${url}" data-id="${this.escapeHtml(n.id)}" class="group flex items-stretch gap-0 hover:bg-[#f5f7f5] transition ${bg} border-b border-[#e6e9e1] last:border-0 border-r-[3px] ${accent} focus:outline-none focus-visible:bg-[#f5f7f5]" tabindex="0">
          <div class="flex items-start gap-3 p-3 flex-1 min-w-0">
            <div class="w-8 h-8 rounded-lg ${iconBg} flex items-center justify-center shrink-0 border mt-0.5" aria-hidden="true">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="${this.iconPathFor(cfg.icon)}"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-[13px] font-bold text-ink-800 leading-none truncate">${title}</span>
                ${isUnread ? `<span class="w-1.5 h-1.5 rounded-full ${cfg.dotClass} shrink-0"></span>` : ''}
                <span class="text-[11px] font-medium text-ink-400 mr-auto shrink-0">${time}</span>
              </div>
              ${body ? `<p class="text-xs font-medium text-ink-600 mt-1.5 leading-4 line-clamp-1">${body}</p>` : ''}
              ${(metaStr || sender) ? `<p class="text-[11px] text-ink-400 mt-1 truncate">${sender ? sender + (metaStr ? ' · ' + metaStr : '') : metaStr}</p>` : ''}
              ${reason}
            </div>
          </div>
        </a>
      `;
    }).join('');

    this.log('[CENTER] updated', this.notifications.length);
  }

  // ──────────────────────────────────────────────
  // Server sync (fetch)
  // ──────────────────────────────────────────────

  async fetchNotifications({ silent = false } = {}) {
    try {
      const res = await fetch('/notifications?per_page=20', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('fetch failed ' + res.status);
      const data = await res.json();
      const incoming = data.notifications || [];
      const unread = typeof data.unread_count === 'number' ? data.unread_count : this.unreadCount;

      // If silent initial load, just populate without toast/sound
      if (silent && this.notifications.length === 0) {
        // Normalize incoming (coming from index endpoint already normalized)
        const normalized = incoming.map(n => ({
          id: n.id,
          type: n.type,
          data: n.data,
          read_at: n.read_at,
          created_at: n.created_at,
          created_at_human: n.created_at_human || n.created_at,
          created_at_full: n.created_at_full,
        }));
        normalized.forEach(n => this.seenIds.add(String(n.id)));
        // Store sorted by created_at desc? incoming already latest first
        this.notifications = normalized.slice(0, 20);
        this.updateBadge(unread);
        this.updateCenter();
        if (normalized.length) {
          this.lastEventId = String(normalized[0].id);
          this.lastServerTime = normalized[0].created_at;
        }
        this.log('[FETCH] initial silent', normalized.length);
        return;
      }

      // For subsequent polls: detect new unread not yet seen
      const newOnes = incoming.filter(n => !n.read_at).filter(n => !this.seenIds.has(String(n.id)));
      if (newOnes.length) {
        this.log('[FETCH] newOnes', newOnes.length);
        // Sort ascending so receive in chronological order (oldest first)
        newOnes.reverse().forEach(n => {
          const payload = {
            id: n.id,
            type: n.type,
            data: n.data,
            read_at: n.read_at,
            created_at: n.created_at,
            created_at_human: n.created_at_human,
            created_at_full: n.created_at_full,
          };
          this.receive(payload, null, { source: 'poll' });
        });
        // After processing, sync badge to authoritative count
        this.updateBadge(unread);
      } else {
        // No new, but badge might have changed (mark read from other tab/server)
        if (unread !== this.unreadCount) {
          this.log('[FETCH] badge drift', this.unreadCount, '->', unread);
          this.updateBadge(unread);
        }
        // Also refresh center list if empty but server has data
        if (this.notifications.length === 0 && incoming.length) {
          this.notifications = incoming.slice(0, 20).map(n => ({
            id: n.id, type: n.type, data: n.data, read_at: n.read_at, created_at: n.created_at, created_at_human: n.created_at_human
          }));
          incoming.forEach(n => this.seenIds.add(String(n.id)));
          this.updateCenter();
        }
      }
    } catch (e) {
      this.log('[FETCH] error', e);
    }
  }

  // ──────────────────────────────────────────────
  // Mark read
  // ──────────────────────────────────────────────

  async markOneAsRead(id, { silent = false } = {}) {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      const res = await fetch(`/notifications/${encodeURIComponent(id)}/read`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('markOne failed');
      const data = await res.json();
      // Update local state
      const idx = this.notifications.findIndex(n => String(n.id) === String(id));
      if (idx !== -1) {
        this.notifications[idx].read_at = new Date().toISOString();
      }
      if (typeof data.unread_count === 'number') this.updateBadge(data.unread_count);
      else this.updateBadge(Math.max(0, this.unreadCount - 1));
      this.updateCenter();
      this.broadcast({ type: 'mark_one_read', id, unread_count: this.unreadCount });
      if (!silent) this.log('[MARK] one read', id);
    } catch (e) {
      this.log('[MARK] one read failed', e);
      throw e;
    }
  }

  async markAllAsRead() {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      const res = await fetch('/notifications/mark-read', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({}),
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('markAll failed');
      const data = await res.json();
      this.notifications.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
      if (typeof data.unread_count === 'number') this.updateBadge(data.unread_count);
      else this.updateBadge(0);
      this.updateCenter();
      this.broadcast({ type: 'mark_all_read', unread_count: this.unreadCount });
      this.log('[MARK] all read');
    } catch (e) {
      this.log('[MARK] all read failed', e);
    }
  }

  // ──────────────────────────────────────────────
  // Browser Notification (system)
  // ──────────────────────────────────────────────

  maybeShowBrowserNotification(notification) {
    if (!window.Notification) return;
    if (Notification.permission !== 'granted') return;
    if (!document.hidden) return; // only when background
    if (!this.prefs.desktop_enabled) return;

    try {
      const data = notification.data || {};
      const rawType = data.type || notification.type || '';
      const cfg = getTypeConfig(rawType);
      const title = cfg.label || 'إشعار جديد';
      const body = (data.message || data.reason || 'لديك إشعار جديد').substring(0, 130);
      const url = this.resolveUrl(notification);

      const n = new Notification(title, {
        body,
        icon: '/pwa/icons/icon-192.png',
        badge: '/pwa/icons/icon-192.png',
        tag: String(notification.id),
        requireInteraction: cfg.priority === 'high',
        silent: false,
      });
      n.onclick = () => {
        window.focus();
        if (url) window.location.href = url;
        n.close();
      };
      // auto close after 7s if not requireInteraction
      if (!cfg.requireInteraction) setTimeout(() => n.close(), 7000);
    } catch (e) {
      this.log('[BROWSER NOTIF] failed', e);
    }
  }

  // ──────────────────────────────────────────────
  // Multi-tab BroadcastChannel
  // ──────────────────────────────────────────────

  broadcast(msg) {
    if (!this.channel) return;
    try {
      this.channel.postMessage({ ...msg, tabId: this.tabId, ts: Date.now() });
    } catch {}
  }

  handleBroadcastMessage(e) {
    const msg = e.data;
    if (!msg || msg.tabId === this.tabId) return;

    this.log('[BROADCAST] recv', msg);

    switch (msg.type) {
      case 'badge_sync':
        if (typeof msg.unread_count === 'number') this.updateBadge(msg.unread_count);
        break;
      case 'mark_one_read':
        {
          const idx = this.notifications.findIndex(n => String(n.id) === String(msg.id));
          if (idx !== -1) this.notifications[idx].read_at = new Date().toISOString();
          if (typeof msg.unread_count === 'number') this.updateBadge(msg.unread_count);
          this.updateCenter();
        }
        break;
      case 'mark_all_read':
        this.notifications.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
        if (typeof msg.unread_count === 'number') this.updateBadge(msg.unread_count);
        this.updateCenter();
        break;
      case 'notification_received':
        // Another tab already received and incremented badge; we just ensure badge sync but do not toast/sound
        if (msg.notification && !this.seenIds.has(String(msg.notification.id))) {
          this.seenIds.add(String(msg.notification.id));
          this.notifications.unshift(msg.notification);
          if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);
          if (typeof msg.unread_count === 'number') this.updateBadge(msg.unread_count);
          this.updateCenter();
          // do not toast/sound (other tab already did)
        }
        break;
      case 'claim':
        // No action needed now; claim just informs
        break;
    }
  }

  // ──────────────────────────────────────────────
  // Permission banner (sound + desktop)
  // ──────────────────────────────────────────────

  updatePermissionBanner() {
    const soundBlocked = this.soundManager?.blocked;
    const soundDisabled = this.soundManager && !this.soundManager.enabled;
    let desktopState = 'unknown';
    if (window.Notification) desktopState = Notification.permission;
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || window.innerWidth < 768;
    const notUnlocked = this.soundManager && !this.soundManager.unlocked;
    const shouldShowMobilePrompt = isMobile && notUnlocked && this.soundManager?.enabled;
    const shouldShowDropdown = soundBlocked || shouldShowMobilePrompt || (window.Notification && desktopState === 'default' && !document.hidden);

    // Dropdown banner
    if (this.permissionBanner) {
      if (shouldShowDropdown) {
        this.permissionBanner.classList.remove('hidden');
        const textEl = document.getElementById('notif-banner-text');
        const btn = document.getElementById('enable-notif-btn');
        if (shouldShowMobilePrompt && !soundBlocked) {
          if (textEl) textEl.textContent = 'اضغط تفعيل الصوت مرة واحدة ليصلك التنبيه على الهاتف 🔊 — ضروري لنظام iOS/Android';
          if (btn) btn.textContent = 'تفعيل الصوت الآن 🔊';
        } else if (soundBlocked) {
          if (textEl) textEl.textContent = 'المتصفح منع تشغيل الصوت تلقائياً — اضغط تفعيل ليصلك التنبيه بالصوت';
          if (btn) btn.textContent = 'تفعيل الصوت 🔊';
        } else if (desktopState === 'default') {
          if (textEl) textEl.textContent = 'فعّل الإشعارات ليصلك التنبيه حتى عند تصفح تبويب آخر';
          if (btn) btn.textContent = 'تفعيل الإشعارات 🔔';
        } else if (desktopState === 'denied') {
          if (textEl) textEl.textContent = 'الإشعارات محظورة في المتصفح — فعّلها من إعدادات الموقع';
          if (btn) {
            btn.textContent = 'تعليمات';
            btn.onclick = () => alert('افتح أيقونة القفل بجانب العنوان > إعدادات الموقع > الإشعارات > سماح لـ ' + location.host);
          }
        }
      } else {
        if (soundBlocked === false && desktopState === 'granted') this.permissionBanner.classList.add('hidden');
        else if (!soundBlocked && desktopState !== 'default') this.permissionBanner.classList.add('hidden');
        else if (desktopState === 'granted' && !soundBlocked) this.permissionBanner.classList.add('hidden');
      }
    }

    // Global sound prompt — يظهر مرة واحدة بعد مهلة قصيرة حتى لا يومض عند التحميل
    const globalBanner = document.getElementById('global-sound-banner');
    if (globalBanner && this.soundManager) {
      let dismissed = false;
      let wasUnlockedBefore = false;
      try {
        dismissed = sessionStorage.getItem('global_sound_dismissed') === '1';
        wasUnlockedBefore = sessionStorage.getItem('notif_unlocked') === '1';
      } catch {}
      // إذا كان المستخدم قد فك القفل سابقاً، لا تُظهر البانر فوراً — انتظر محاولة الفك التلقائي (400ms)
      if (wasUnlockedBefore && notUnlocked && !soundBlocked) {
        globalBanner.classList.add('hidden');
      } else {
        const needGlobal = (notUnlocked && this.soundManager.enabled && !dismissed) || soundBlocked;
        const showGlobal = needGlobal && this.soundManager.enabled && this.prefs.sound_enabled;
        // تأخير بسيط لأول ظهور حتى لا يومض
        const isFirstShow = !globalBanner.dataset.shown;
        if (showGlobal) {
          if (isFirstShow && !soundBlocked) {
            // أجل الظهور 900ms لإعطاء AudioContext فرصة للفتح التلقائي
            if (!globalBanner.dataset.pending) {
              globalBanner.dataset.pending = '1';
              setTimeout(() => {
                delete globalBanner.dataset.pending;
                // أعد التقييم بعد المهلة
                if (this.soundManager && !this.soundManager.unlocked && this.soundManager.enabled && this.prefs.sound_enabled) {
                  let d2 = false;
                  try { d2 = sessionStorage.getItem('global_sound_dismissed') === '1'; } catch {}
                  if (!d2) {
                    globalBanner.classList.remove('hidden');
                    globalBanner.dataset.shown = '1';
                  }
                }
              }, 900);
            }
          } else {
            globalBanner.classList.remove('hidden');
            globalBanner.dataset.shown = '1';
            const gText = document.getElementById('global-sound-text');
            if (gText) {
              if (soundBlocked) gText.textContent = 'المتصفح حجب الصوت — اضغط تفعيل';
              else gText.textContent = 'فعّل الصوت ليصلك التنبيه فوراً';
            }
          }
        } else {
          globalBanner.classList.add('hidden');
        }
      }
    }

    const stateDetail = this.soundManager?.getState();
    window.dispatchEvent(new CustomEvent('notification:permission-banner', { detail: { soundBlocked, desktopState, stateDetail } }));
  }
}
