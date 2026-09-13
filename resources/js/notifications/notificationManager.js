

import { getTypeConfig, priorityForType, PRIORITY, cfgLabel, NOTIF_FB } from './config.js';

export class NotificationManager {
  constructor(options = {}) {
    this.soundManager = options.soundManager;
    this.config = options.config || {};
    this.debug = options.debug ?? this.isDebugEnabled();
    this.userId = options.userId ?? null;

    
    this.badgeEl = null;
    this.dropdownEl = null;
    this.overlayEl = null;
    this.closeBtn = null;
    this.drawerCountEl = null;
    this.listEl = null;
    this.bellEl = null;
    this.markAllBtn = null;
    this.permissionBanner = null;
    this.toastContainer = null;
    this.liveRegion = null;
    this._drawerOpen = false;
    this._drawerHideT = null;
    this._prevBodyOverflow = '';

    
    this.unreadCount = 0;
    this.notifications = []; 
    this.seenIds = new Set(); 
    this.renderedToastIds = new Set();
    this.lastEventId = null;
    this.lastServerTime = null;
    this.connectionState = 'DISCONNECTED'; 
    this.sse = null;
    this.pollTimer = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 8;

    
    this.tabId = Math.random().toString(36).slice(2, 9);
    this.channel = null;
    try {
      this.channel = new BroadcastChannel('rasd_notifications');
      this.channel.onmessage = (e) => this.handleBroadcastMessage(e);
    } catch {}

    
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
      
      const metaDebug = document.querySelector('meta[name="app-debug"]')?.content;
      if (metaDebug === '1') return true;
      
      if (new URLSearchParams(location.search).has('notif_debug')) return true;
    } catch {}
    return false;
  }

  log(...args) {
    if (this.debug) console.log(...args);
  }

  async init() {
    
    this.badgeEl = document.getElementById('notification-badge');
    this.dropdownEl = document.getElementById('notification-drawer');
    this.overlayEl = document.getElementById('notification-overlay');
    this.closeBtn = document.getElementById('notification-close');
    this.drawerCountEl = document.getElementById('notification-drawer-count');
    this.listEl = document.getElementById('notification-list');
    this.bellEl = document.getElementById('notification-bell');
    this.markAllBtn = document.getElementById('mark-all-read');
    this.permissionBanner = document.getElementById('notification-permission-banner');
    this.toastContainer = this.ensureToastContainer();
    this.liveRegion = this.ensureLiveRegion();

    
    const [prefsData] = await Promise.all([
      this.loadPreferences(),
      this.fetchNotifications({ silent: true }),
    ]);

    if (this.soundManager) {
      this.soundManager.loadPersisted();
      await this.soundManager.loadFromServer(prefsData ?? undefined);
      this.prefs.sound_enabled = this.soundManager.enabled;
      this.prefs.volume = Math.round(this.soundManager.volume * 100);
      this.bindSoundState();
    }

    this.bindUI();

    
    this.connect();

    
    if (window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window) {
      this.subscribePush().catch(e=>this.log('[PUSH] subscribe failed',e));
    } else if (location.protocol === 'http:') {
      this.log('[PUSH] skipped — HTTP لا يدعم Push عند الإغلاق (يلزم HTTPS)');
    }

    
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


    this.log('[MANAGER] ready');
  }

  bindSoundState() {
    window.addEventListener('notification:sound-state', (e) => {
      this.updatePermissionBanner();
    });
  }


  isDrawerOpen() {
    return !!this._drawerOpen && !!this.dropdownEl && !this.dropdownEl.classList.contains('hidden');
  }

  openDrawer() {
    if (!this.dropdownEl || !this.overlayEl) return;
    if (this.isDrawerOpen()) return;
    this._drawerOpen = true;
    clearTimeout(this._drawerHideT);
    this._prevBodyOverflow = document.body.style.overflow;
    this.overlayEl.classList.remove('hidden');
    this.dropdownEl.classList.remove('hidden');
    this.dropdownEl.setAttribute('aria-hidden', 'false');
    this.bellEl?.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => requestAnimationFrame(() => {
      if (!this._drawerOpen) return;
      this.overlayEl?.classList.add('is-visible');
      this.dropdownEl?.classList.add('is-open');
    }));
    document.body.style.overflow = 'hidden';
    try { this.closeBtn?.focus({ preventScroll: true }); } catch { try { this.closeBtn?.focus(); } catch {} }
  }

  closeDrawer(returnFocus = true) {
    if (!this.dropdownEl) return;
    if (!this._drawerOpen && this.dropdownEl.classList.contains('hidden')) return;
    this._drawerOpen = false;
    clearTimeout(this._drawerHideT);
    this.overlayEl?.classList.remove('is-visible');
    this.dropdownEl?.classList.remove('is-open');
    this.dropdownEl.setAttribute('aria-hidden', 'true');
    this.bellEl?.setAttribute('aria-expanded', 'false');
    const stealFocus = returnFocus && !!this.dropdownEl.contains(document.activeElement);
    this._drawerHideT = setTimeout(() => {
      if (this._drawerOpen) return;
      this.overlayEl?.classList.add('hidden');
      this.dropdownEl?.classList.add('hidden');
      if (!document.querySelector('[data-modal]:not(.hidden)') && !document.querySelector('#mobile-menu.is-open')) {
        document.body.style.overflow = this._prevBodyOverflow || '';
      }
      if (stealFocus) { try { this.bellEl?.focus({ preventScroll: true }); } catch {} }
    }, 340);
  }

  bindUI() {
    this.bellEl?.addEventListener('click', async (e) => {
      e.stopPropagation();
      await this.soundManager?.unlock();
      if (this.isDrawerOpen()) {
        this.closeDrawer(false);
        return;
      }
      this.openDrawer();
      await this.fetchNotifications({ silent: false });
      this.updatePermissionBanner();
    });

    document.addEventListener('click', (e) => {
      if (!this.isDrawerOpen()) return;
      if (!this.bellEl?.contains(e.target) && !this.dropdownEl?.contains(e.target)) {
        this.closeDrawer(false);
      }
    });

    this.overlayEl?.addEventListener('click', () => this.closeDrawer(false));
    this.closeBtn?.addEventListener('click', () => this.closeDrawer());

    this.markAllBtn?.addEventListener('click', async () => {
      await this.markAllAsRead();
    });

    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeDrawer(false);
        
        this.toastContainer?.querySelectorAll('[data-toast]').forEach(el => {
          if (document.activeElement && el.contains(document.activeElement)) el.remove();
        });
      }
    });

    
    this.listEl?.addEventListener('click', async (e) => {
      const link = e.target.closest('a[data-id]');
      if (!link) return;
      const id = link.getAttribute('data-id');
      
      
      if (id) {
        try { await this.markOneAsRead(id, { silent: true }); } catch {}
      }
    });

    
    const enableBtn = document.getElementById('enable-notif-btn');
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
      
      if (ok) {
        document.getElementById('global-sound-banner')?.classList.add('hidden');
        try { sessionStorage.setItem('global_sound_dismissed', '1'); } catch {}
      }
      
      const isEnSnd = (typeof document !== 'undefined' && document.documentElement.lang === 'en') || (typeof window !== 'undefined' && window.RASD_LOCALE === 'en');
      this.showToast({
        id: 'enable-' + Date.now(),
        type: 'Test',
        data: {
          message: ok
            ? (isEnSnd ? 'Sound enabled ✅ — you will hear it on the next notification' : 'تم تفعيل الصوت بنجاح ✅ — ستصلك النغمة عند الإشعار القادم')
            : (isEnSnd ? 'Could not enable sound — check the tab is not muted' : 'تعذر تفعيل الصوت — تأكد من عدم كتم التبويب'),
          type: ok ? 'note_accepted' : 'note_rejected',
          url: null,
        },
        created_at_human: NOTIF_FB.now,
      }, { force: true, sound: false });
    };
    enableBtn?.addEventListener('click', handleEnable);
    globalEnableBtn?.addEventListener('click', handleEnable);
    globalDismissBtn?.addEventListener('click', () => {
      document.getElementById('global-sound-banner')?.classList.add('hidden');
      try { sessionStorage.setItem('global_sound_dismissed', '1'); } catch {}
    });

    
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
        return data;
      }
    } catch {}
    return null;
    
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

  
  connect() {
    
    
    const isLocalSingleThread = (() => {
      try {
        if (new URLSearchParams(location.search).has('nosse')) return true;
        
        if (location.protocol === 'http:') return true;
        if (!window.isSecureContext) return true;
        if (location.hostname === '127.0.0.1' || location.hostname === 'localhost' || location.hostname === '10.150.2.29' || location.hostname.endsWith('.lan') || location.hostname.endsWith('.home.arpa') === false && location.hostname.match(/^\d+\.\d+\.\d+\.\d+$/)) {
          
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

    
    if (typeof EventSource !== 'undefined') {
      this.connectSSE();
    } else {
      this.log('[CONNECT] EventSource not supported -> fallback polling');
      this.connectPolling();
    }

    
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
            
            this.broadcast({ type: 'badge_sync', unread_count: data.unread_count });
          }
        } catch {}
      });

      es.addEventListener('ping', () => {
        this.log('[SSE] ping');
      });

      es.addEventListener('error', (e) => {
        this.log('[SSE] error', e);
        
        
        if (es.readyState === EventSource.CLOSED) {
          
          
          this.setConnectionState('DISCONNECTED');
          
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
    const interval = 30000;
    this.log(`[POLL] starting fallback polling (every ${interval/1000}s)`);
    this.setConnectionState('CONNECTED'); 
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (this.pollWorker) { try{ this.pollWorker.postMessage({type:'stop'}); this.pollWorker.terminate(); }catch{} this.pollWorker=null; }
    
    try{
      if(window.Worker){
        this.pollWorker = new Worker('/js/poll-worker.js');
        this.pollWorker.onmessage = (e)=>{ if(e.data?.type==='poll') this.fetchNotifications({ silent: true }); };
        this.pollWorker.postMessage({type:'start', interval: interval});
        this.log('[POLL] worker started');
      } else throw new Error('no Worker');
    }catch{
      this.pollTimer = setInterval(() => this.fetchNotifications({ silent: true }), interval);
    }
    
    if (Date.now() - (this._lastFetchAt || 0) > 10000) {
      this.fetchNotifications({ silent: true });
    }
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
    if (this.pollWorker) { try{ this.pollWorker.postMessage({type:'stop'}); this.pollWorker.terminate(); }catch{} this.pollWorker=null; }
    this.reconnectAttempts = 0;
    this.connect();
  }

  sync() {
    this.fetchNotifications({ silent: true });
  }

  syncIfNeeded() {
    
    
    this.sync();
  }

  setConnectionState(state) {
    this.connectionState = state;
    this.log('[CONNECTION]', state);
    
    const el = document.getElementById('notification-connection-status');
    if (el) {
      el.textContent = state;
      el.dataset.state = state.toLowerCase();
    }
    window.dispatchEvent(new CustomEvent('notification:connection', { detail: { state } }));
  }

  
  tryConnectEcho() {
    
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
      
      this.log('[ECHO] subscribed');
    } catch (err) {
      this.log('[ECHO] subscribe failed', err);
    }
  }

  
  async receive(notification, unreadCount = null, meta = {}) {
    if (!notification || !notification.id) {
      this.log('[RECEIVE] invalid payload', notification);
      return;
    }

    const id = String(notification.id);

    
    if (this.seenIds.has(id)) {
      this.log('[NOTIFICATION] duplicate ignored', id);
      return;
    }
    
    try {
      const key = 'notif_seen_' + id;
      if (sessionStorage.getItem(key)) {
        this.log('[NOTIFICATION] duplicate ignored (session)', id);
        this.seenIds.add(id);
        return;
      }
      sessionStorage.setItem(key, '1');
      
      
    } catch {}

    this.seenIds.add(id);
    this.lastEventId = id;
    this.lastServerTime = notification.created_at || new Date().toISOString();

    
    this.notifications.unshift(notification);
    if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);

    
    if (typeof unreadCount === 'number') {
      this.updateBadge(unreadCount);
    } else {
      
      if (!notification.read_at) this.updateBadge(this.unreadCount + 1);
    }

    
    this.broadcast({ type: 'notification_received', notification, unread_count: this.unreadCount });

    
    const shouldPresent = this.shouldPresentInThisTab(notification);

    this.log('[NOTIFICATION] received', { id, type: notification.type, priority: notification.data?.priority, shouldPresent, source: meta.source });

    if (!shouldPresent) {
      
      this.updateCenter();
      return;
    }

    
    if (this.isMuted(notification)) {
      this.log('[NOTIFICATION] muted type, skipping toast/sound', notification.type);
      this.updateCenter();
      return;
    }

    
    this.updateCenter();

    
    if (this.prefs.toast_enabled) {
      this.renderToast(notification);
    }

    
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

    
    this.announceToScreenReader(notification);

    
    this.maybeShowBrowserNotification(notification);
  }

  shouldPresentInThisTab(notification) {
    
    
    const isHttp = location.protocol === 'http:';
    if (document.hidden) {
      
      
      this.log('[MULTI-TAB] hidden -> still try claim for background sound (HTTP)');
      
    } else if (!document.hasFocus()) {
      
      this.log('[MULTI-TAB] not focused -> try claim');
    }

    
    const claimKey = 'notif_claim_' + notification.id;
    try {
      const existing = localStorage.getItem(claimKey);
      if (existing && existing !== this.tabId) {
        this.log('[MULTI-TAB] already claimed by', existing);
        return false;
      }
      if (!existing) {
        localStorage.setItem(claimKey, this.tabId);
        
        
        const verify = localStorage.getItem(claimKey);
        if (verify !== this.tabId) {
          this.log('[MULTI-TAB] claim lost race');
          return false;
        }
        
        setTimeout(() => {
          if (localStorage.getItem(claimKey) === this.tabId) localStorage.removeItem(claimKey);
        }, 10000);
      }
    } catch {}

    
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

  
  updateBadge(count) {
    const n = Math.max(0, parseInt(count) || 0);
    this.unreadCount = n;
    this.log('[BADGE] updated', n);
    window.dispatchEvent(new CustomEvent('notification:badge', { detail: { count: n } }));

    if (this.drawerCountEl) {
      if (n > 0) {
        this.drawerCountEl.textContent = n > 99 ? '99+' : String(n);
        this.drawerCountEl.classList.remove('hidden');
      } else {
        this.drawerCountEl.classList.add('hidden');
      }
    }

    if (!this.badgeEl) return;

    if (n > 0) {
      this.badgeEl.textContent = n > 99 ? '99+' : String(n);
      this.badgeEl.classList.remove('hidden');
      this.badgeEl.classList.add('flex');
      
      this.badgeEl.setAttribute('aria-label', `${n} ${NOTIF_FB.unread}`);
      
      this.badgeEl.animate?.([{ transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 300, easing: 'ease-out' });
      
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

    
  }

  
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
    const msg = data.message || NOTIF_FB.newNotif;
    this.liveRegion.textContent = msg;
    
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
    
    if (this.renderedToastIds.size > 100) {
      const first = this.renderedToastIds.values().next().value;
      this.renderedToastIds.delete(first);
    }

    const data = notification.data || {};
    const rawType = data.type || notification.type || 'generic';
    const cfg = getTypeConfig(rawType);
    const priority = priorityForType(rawType, data.priority);
    const isHigh = priority === 'high' || priority === 'critical';

    
    const toast = document.createElement('div');
    toast.dataset.toast = id;
    toast.setAttribute('role', isHigh ? 'alert' : 'status');
    toast.setAttribute('aria-live', isHigh ? 'assertive' : 'polite');
    toast.tabIndex = 0;
    
    const baseClasses = 'pointer-events-auto w-full bg-surface-elevated rounded-2xl shadow-2xl border border-border overflow-hidden flex flex-col transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';
    toast.className = baseClasses;
    toast.style.transform = 'translateY(12px)';
    toast.style.opacity = '0';

    const title = this.escapeHtml(data.title || this.titleForType(rawType, data));
    const body = this.escapeHtml((data.message || data.reason || NOTIF_FB.youHave).substring(0, 180));
    const time = this.escapeHtml(notification.created_at_human || data.created_at_human || NOTIF_FB.now);
    
    const accent = isHigh ? 'bg-red-500' : (cfg.color === 'green' ? 'bg-primary' : (cfg.color === 'amber' ? 'bg-amber-500' : 'bg-text-muted'));
    const iconBg = cfg.bgClass || 'bg-surface-muted border border-border';
    const url = this.resolveUrl(notification);

    toast.innerHTML = `
      <div class="h-1 w-full ${accent}"></div>
      <div class="p-4 flex gap-3">
        <div class="w-10 h-10 rounded-xl ${iconBg} flex items-center justify-center shrink-0" aria-hidden="true">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="${this.iconPathFor(cfg.icon)}"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-text-primary dark:text-text-primary leading-5">${title}</div>
          <div class="text-xs text-text-secondary dark:text-text-secondary leading-5 mt-1 line-clamp-2">${body}</div>
          <div class="text-[11px] text-text-muted dark:text-text-muted mt-1.5">${time}</div>
        </div>
          <button type="button" aria-label="${this.escapeHtml(NOTIF_FB.close)}" class="shrink-0 w-8 h-8 rounded-lg hover:bg-surface-muted flex items-center justify-center text-text-muted dark:text-text-muted hover:text-text-secondary dark:hover:text-text-secondary transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      ${url ? `<div class="px-4 pb-3 -mt-1"><span class="inline-flex items-center gap-1 text-xs font-bold text-primary">${this.escapeHtml(NOTIF_FB.viewDetails)} <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span></div>` : ''}
    `;

    
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

    
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced) {
      toast.style.transform = 'translateY(0)';
      toast.style.opacity = '1';
    } else {
      requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
      });
      
      if (isHigh && toast.animate) {
        toast.animate(
          [{ transform: 'translateX(0)' }, { transform: 'translateX(-2px)' }, { transform: 'translateX(2px)' }, { transform: 'translateX(0)' }],
          { duration: 400, easing: 'ease-out', delay: 200 }
        );
      }
    }

    this.log('[TOAST] rendered', id, rawType);

    
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
    if (data && data.title) return data.title;

    return cfgLabel(cfg) || NOTIF_FB.newNotif;
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
    if (data.report_id) return `/reports/${data.report_id}`;
    return null;
  }

  handleClick(notification) {
    const url = this.resolveUrl(notification);
    this.log('[CLICK] notification', notification.id, url);
    this.markOneAsRead(notification.id, { silent: true }).catch(() => {});
    if (!url) return;
    window.location.href = url;
  }

  
  updateCenter() {
    if (!this.listEl) return;


    const empty = this.notifications.length === 0;
    document.getElementById('mark-all-read')?.classList.toggle('hidden', empty);
    document.querySelector('#notification-drawer .notif-subbar')?.classList.toggle('hidden', empty);
    document.querySelector('#notification-drawer .notif-footlink')?.classList.toggle('hidden', empty);

    if (empty) {
      this.listEl.innerHTML = '<div class="notif-empty" role="status"><div class="notif-empty-ic" aria-hidden="true"><svg class="notif-empty-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div><p class="notif-empty-title">' + this.escapeHtml(NOTIF_FB.emptyTitle) + '</p><p class="notif-empty-hint">' + this.escapeHtml(NOTIF_FB.emptyHint) + '</p></div>';
      return;
    }

    this.listEl.innerHTML = this.notifications.slice(0, 20).map(n => {
      const data = n.data || {};
      const rawType = data.type || n.type || 'generic';
      const cfg = getTypeConfig(rawType);
      const isUnread = !n.read_at;
      const rawUrl = this.resolveUrl(n);
      const url = this.escapeHtml(rawUrl || '');
      const title = this.escapeHtml(data.title || cfgLabel(cfg) || NOTIF_FB.notif);
      const body = this.escapeHtml(data.message || '');
      const time = this.escapeHtml(n.created_at_human || NOTIF_FB.now);
      const sender = this.escapeHtml(data.sender_name || data.processor_name || '');
      const meta = [];
      if (data.camera_number) meta.push(`${NOTIF_FB.camera} ${this.escapeHtml(String(data.camera_number))}`);
      if (data.floor_number) meta.push(`${NOTIF_FB.floor} ${this.escapeHtml(String(data.floor_number))}`);
      const metaStr = meta.join(' · ');
      const reason = data.reason ? `<div class="notif-reason">${this.escapeHtml(data.reason)}</div>` : '';
      const tone = cfg.color === 'green' ? 'green' : cfg.color === 'amber' ? 'amber' : cfg.color === 'red' ? 'red' : 'slate';

      const openTag = rawUrl
        ? `<a href="${url}" data-id="${this.escapeHtml(n.id)}" class="notif-card${isUnread ? ' is-unread' : ''}" tabindex="0">`
        : `<div data-id="${this.escapeHtml(n.id)}" class="notif-card${isUnread ? ' is-unread' : ''}" tabindex="0">`;
      const closeTag = rawUrl ? `</a>` : `</div>`;
      return `
        ${openTag}
          <div class="notif-ic notif-ic--${tone}" aria-hidden="true">
            <svg class="notif-ic-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="${this.iconPathFor(cfg.icon)}"/></svg>
          </div>
          <div class="notif-main">
            <div class="notif-head">
              <span class="notif-title">${title}</span>
              ${isUnread ? `<span class="notif-dot" aria-label="${this.escapeHtml(NOTIF_FB.unread)}"></span>` : ''}
              <span class="notif-time">${time}</span>
            </div>
            ${body ? `<span class="notif-msg">${body}</span>` : ''}
            ${(metaStr || sender) ? `<span class="notif-meta">${sender ? sender + (metaStr ? ' · ' + metaStr : '') : metaStr}</span>` : ''}
            ${reason}
          </div>
        ${closeTag}
      `;
    }).join('');

    this.log('[CENTER] updated', this.notifications.length);
  }

  
  async fetchNotifications({ silent = false } = {}) {
    try {
      if (typeof document !== 'undefined' && document.hidden && silent) {
        return;
      }
      this._lastFetchAt = Date.now();
      const res = await fetch('/notifications?per_page=20', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('fetch failed ' + res.status);
      const data = await res.json();
      const incoming = data.notifications || [];
      const unread = typeof data.unread_count === 'number' ? data.unread_count : this.unreadCount;

      
      if (silent && this.notifications.length === 0) {
        
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

      
      const newOnes = incoming.filter(n => !n.read_at).filter(n => !this.seenIds.has(String(n.id)));
      if (newOnes.length) {
        this.log('[FETCH] newOnes', newOnes.length);
        
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
        
        this.updateBadge(unread);
      } else {
        
        if (unread !== this.unreadCount) {
          this.log('[FETCH] badge drift', this.unreadCount, '->', unread);
          this.updateBadge(unread);
        }
        
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

  
  maybeShowBrowserNotification(notification) {
    if (!window.Notification) return;
    if (Notification.permission !== 'granted') return;
    if (!document.hidden) return; 
    if (!this.prefs.desktop_enabled) return;

    try {
      const data = notification.data || {};
      const rawType = data.type || notification.type || '';
      const cfg = getTypeConfig(rawType);
      const title = data.title || cfgLabel(cfg) || NOTIF_FB.newNotif;
      const body = (data.message || data.reason || NOTIF_FB.youHave).substring(0, 130);
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
      
      if (!cfg.requireInteraction) setTimeout(() => n.close(), 7000);
    } catch (e) {
      this.log('[BROWSER NOTIF] failed', e);
    }
  }

  
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
        
        if (msg.notification && !this.seenIds.has(String(msg.notification.id))) {
          this.seenIds.add(String(msg.notification.id));
          this.notifications.unshift(msg.notification);
          if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);
          if (typeof msg.unread_count === 'number') this.updateBadge(msg.unread_count);
          this.updateCenter();
          
        }
        break;
      case 'claim':
        
        break;
    }
  }

  
  updatePermissionBanner() {
    const soundBlocked = this.soundManager?.blocked;
    const soundDisabled = this.soundManager && !this.soundManager.enabled;
    let desktopState = 'unknown';
    if (window.Notification) desktopState = Notification.permission;
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || window.innerWidth < 768;
    const notUnlocked = this.soundManager && !this.soundManager.unlocked;
    const shouldShowMobilePrompt = isMobile && notUnlocked && this.soundManager?.enabled;
    const shouldShowDropdown = soundBlocked || shouldShowMobilePrompt || (window.Notification && desktopState === 'default' && !document.hidden);

    
    if (this.permissionBanner) {
      if (shouldShowDropdown) {
        this.permissionBanner.classList.remove('hidden');
        const textEl = document.getElementById('notif-banner-text');
        const btn = document.getElementById('enable-notif-btn');
        const isEnPerm = (typeof document !== 'undefined' && document.documentElement.lang === 'en') || (typeof window !== 'undefined' && window.RASD_LOCALE === 'en');
        if (shouldShowMobilePrompt && !soundBlocked) {
          if (textEl) textEl.textContent = isEnPerm ? 'Tap enable sound once to get alerts on your phone 🔊 — required on iOS/Android' : 'اضغط تفعيل الصوت مرة واحدة ليصلك التنبيه على الهاتف 🔊 — ضروري لنظام iOS/Android';
          if (btn) btn.textContent = isEnPerm ? 'Enable sound now 🔊' : 'تفعيل الصوت الآن 🔊';
        } else if (soundBlocked) {
          if (textEl) textEl.textContent = isEnPerm ? 'The browser blocked autoplay sound — tap enable to get sound alerts' : 'المتصفح منع تشغيل الصوت تلقائياً — اضغط تفعيل ليصلك التنبيه بالصوت';
          if (btn) btn.textContent = isEnPerm ? 'Enable sound 🔊' : 'تفعيل الصوت 🔊';
        } else if (desktopState === 'default') {
          if (textEl) textEl.textContent = isEnPerm ? 'Enable notifications to get alerts even on other tabs' : 'فعّل الإشعارات ليصلك التنبيه حتى عند تصفح تبويب آخر';
          if (btn) btn.textContent = isEnPerm ? 'Enable notifications 🔔' : 'تفعيل الإشعارات 🔔';
        } else if (desktopState === 'denied') {
          if (textEl) textEl.textContent = isEnPerm ? 'Notifications are blocked in the browser — enable them in site settings' : 'الإشعارات محظورة في المتصفح — فعّلها من إعدادات الموقع';
          if (btn) {
            btn.textContent = isEnPerm ? 'Instructions' : 'تعليمات';
            btn.onclick = () => alert(isEnPerm ? ('Open the lock icon next to the address > Site settings > Notifications > Allow for ' + location.host) : ('افتح أيقونة القفل بجانب العنوان > إعدادات الموقع > الإشعارات > سماح لـ ' + location.host));
          }
        }
      } else {
        if (soundBlocked === false && desktopState === 'granted') this.permissionBanner.classList.add('hidden');
        else if (!soundBlocked && desktopState !== 'default') this.permissionBanner.classList.add('hidden');
        else if (desktopState === 'granted' && !soundBlocked) this.permissionBanner.classList.add('hidden');
      }
    }

    
    const globalBanner = document.getElementById('global-sound-banner');
    if (globalBanner && this.soundManager) {
      let dismissed = false;
      let wasUnlockedBefore = false;
      try {
        dismissed = sessionStorage.getItem('global_sound_dismissed') === '1';
        wasUnlockedBefore = sessionStorage.getItem('notif_unlocked') === '1';
      } catch {}
      
      if (wasUnlockedBefore && notUnlocked && !soundBlocked) {
        globalBanner.classList.add('hidden');
      } else {
        const needGlobal = (notUnlocked && this.soundManager.enabled && !dismissed) || soundBlocked;
        const showGlobal = needGlobal && this.soundManager.enabled && this.prefs.sound_enabled;
        
        const isFirstShow = !globalBanner.dataset.shown;
        if (showGlobal) {
          if (isFirstShow && !soundBlocked) {
            
            if (!globalBanner.dataset.pending) {
              globalBanner.dataset.pending = '1';
              setTimeout(() => {
                delete globalBanner.dataset.pending;
                
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
              if (soundBlocked) gText.textContent = NOTIF_FB.soundBlocked;
              else gText.textContent = NOTIF_FB.enableSoundNow;
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

  async subscribePush(){
    try{
      const reg = await navigator.serviceWorker.ready;
      const existing = await reg.pushManager.getSubscription();
      if(existing) { this.log('[PUSH] already subscribed'); return existing; }
      const vapidRes = await fetch('/push/vapid-public-key', {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
      if(!vapidRes.ok) throw new Error('no vapid key '+vapidRes.status);
      const {key} = await vapidRes.json();
      if(!key) throw new Error('empty vapid');
      if(Notification.permission !== 'granted'){
        const perm = await Notification.requestPermission();
        if(perm !== 'granted') throw new Error('permission '+perm);
      }
      const sub = await reg.pushManager.subscribe({userVisibleOnly:true, applicationServerKey: this.urlBase64ToUint8Array(key)});
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      await fetch('/push/subscribe', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token, Accept:'application/json'}, credentials:'same-origin', body: JSON.stringify(sub.toJSON())});
      this.log('[PUSH] subscribed', sub.endpoint.substring(0,60));
      return sub;
    }catch(e){ this.log('[PUSH] subscribe failed', e); throw e; }
  }
  urlBase64ToUint8Array(base64String){
    const padding='='.repeat((4-base64String.length%4)%4);
    const base64=(base64String+padding).replace(/-/g,'+').replace(/_/g,'/');
    const raw=atob(base64);
    const out=new Uint8Array(raw.length);
    for(let i=0;i<raw.length;i++) out[i]=raw.charCodeAt(i);
    return out;
  }
}
