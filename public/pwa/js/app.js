
const PwaInstall = {
  
  state: 'NOT_READY',
  deferredPrompt: null,
  _dismissedAt: 0,
  _bannerEl: null,
  _btnEl: null,
  _subEl: null,

  STATES: {
    UNSUPPORTED: 'UNSUPPORTED',
    NOT_READY: 'NOT_READY',
    READY_TO_INSTALL: 'READY_TO_INSTALL',
    INSTALL_PROMPTED: 'INSTALL_PROMPTED',
    INSTALLED: 'INSTALLED'
  },

  isStandalone() {
    try {
      if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return true;
      if (window.navigator.standalone === true) return true; 
      
      if (document.referrer.includes('android-app:
    } catch(e){}
    return false;
  },

  isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent || '');
  },

  isSecureContextCheck() {
    
    return window.isSecureContext;
  },

  init() {
    this._bannerEl = document.getElementById('pwa-install-banner');
    this._btnEl = document.getElementById('pwa-install-btn');
    this._subEl = document.getElementById('pwa-banner-sub');
    this._diagEl = document.getElementById('pwa-diag');

    
    if (this.isStandalone()) {
      this.setState(this.STATES.INSTALLED);
      console.log('[PWA] isStandalone = true -> INSTALLED');
      this.render();
      return;
    }

    
    if ('serviceWorker' in navigator) {
      console.log('[PWA] registering service worker at /sw.js scope /');
      console.log('[PWA] isSecureContext =', this.isSecureContextCheck(), ' protocol=', location.protocol, ' host=', location.hostname);
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function(reg){
        console.log('[PWA] SW registered scope=', reg.scope);
        return navigator.serviceWorker.ready;
      }).then(function(){
        console.log('[PWA] SW ready controller=', navigator.serviceWorker.controller ? 'present' : 'none (reload to activate)');
        if (!PwaInstall.isStandalone()) PwaInstall.evaluateReadiness();
      }).catch(function(err){
        console.error('[PWA ERROR] SW registration failed — likely SecureContext block on HTTP LAN:', err);
        console.warn('[PWA] HTTP LAN (192.168.x.x) is NOT a secure context. Chrome requires HTTPS or localhost for SW/PWA. See chrome:
        
        PwaInstall.evaluateReadiness();
        PwaInstall.maybeShowDiag('SW registration failed: ' + (err && err.message ? err.message : err) + ' — isSecureContext=' + window.isSecureContext);
      });
      navigator.serviceWorker.addEventListener('controllerchange', function(){ console.log('[PWA] controllerchange — new SW activated'); });
      
      navigator.serviceWorker.addEventListener('message', function(e){ console.log('[PWA] SW message', e.data); });
    } else {
      console.warn('[PWA] serviceWorker not supported -> UNSUPPORTED');
      this.setState(this.STATES.UNSUPPORTED);
      this.render();
    }

    
    window.addEventListener('beforeinstallprompt', (e) => {
      console.log('[PWA] beforeinstallprompt fired — installable!');
      e.preventDefault(); 
      this.deferredPrompt = e;
      window.deferredPWAInstallPrompt = e; 
      this.setState(this.STATES.READY_TO_INSTALL);
      this.render();
      this.maybeShowDiag('beforeinstallprompt captured — READY_TO_INSTALL');
    });

    window.addEventListener('appinstalled', () => {
      console.log('[PWA] appinstalled event fired');
      this.deferredPrompt = null;
      window.deferredPWAInstallPrompt = null;
      this.setState(this.STATES.INSTALLED);
      this.render();
      try { localStorage.setItem('pwa_installed', '1'); } catch(e){}
    });

    
    try {
      window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
        if (e.matches) { this.setState(this.STATES.INSTALLED); this.render(); }
        else { this.evaluateReadiness(); this.render(); }
      });
    } catch(e){}

    
    if (this.isIos() && !this.isStandalone()) {
      
      setTimeout(() => {
        if (this.state !== this.STATES.INSTALLED) {
          console.log('[PWA] iOS detected without beforeinstallprompt — showing install hint state');
          
          this.setState(this.STATES.NOT_READY);
          this.render();
        }
      }, 2000);
    }

    
    setTimeout(() => { this.evaluateReadiness(); this.render(); }, 4000);

    
    try {
      const d = parseInt(localStorage.getItem('pwa_banner_dismissed')||'0',10);
      this._dismissedAt = isNaN(d)?0:d;
    } catch(e){}

    
    if (location.search.includes('diag=1') || (function(){ try{return localStorage.getItem('pwa_diag')==='1';}catch(e){return false} })()) {
      this.showDiag();
    }

    this.render();
    console.log('[PWA] initial state =', this.state, ' isSecureContext=', this.isSecureContextCheck(), ' isStandalone=', this.isStandalone(), ' isIos=', this.isIos());
  },

  setState(s) {
    if (this.state !== s) {
      console.log('[PWA] state ' + this.state + ' -> ' + s);
      this.state = s;
    }
  },

  evaluateReadiness() {
    if (this.isStandalone()) { this.setState(this.STATES.INSTALLED); return; }
    if (this.deferredPrompt) { this.setState(this.STATES.READY_TO_INSTALL); return; }
    
    if (!('serviceWorker' in navigator)) { this.setState(this.STATES.UNSUPPORTED); return; }
    
    if (this.isIos()) { this.setState(this.STATES.NOT_READY); return; }
    
    this.setState(this.STATES.NOT_READY);
  },

  shouldShowBanner() {
    if (this.state === this.STATES.INSTALLED) return false;
    if (this.state === this.STATES.UNSUPPORTED) return false;
    if (this.state !== this.STATES.READY_TO_INSTALL) return false;
    
    if (this._dismissedAt && (Date.now() - this._dismissedAt) < 24*60*60*1000) return false;
    if (this.isStandalone()) return false;
    return true;
  },

  render() {
    if (!this._bannerEl) return;
    const show = this.shouldShowBanner();
    this._bannerEl.classList.toggle('show', show);
    if (this._btnEl) {
      if (this.state === this.STATES.READY_TO_INSTALL) {
        this._btnEl.textContent = 'تثبيت';
        this._btnEl.disabled = false;
        if (this._subEl) this._subEl.textContent = 'ثبّت ملاحظة على هاتفك للوصول السريع';
      } else if (this.state === this.STATES.INSTALL_PROMPTED) {
        this._btnEl.textContent = '...';
        this._btnEl.disabled = true;
      }
    }
    
    document.querySelectorAll('[data-pwa-state]').forEach(el => {
      el.textContent = this.getStatusLabel();
      el.className = 'pwa-state-badge ' + this.getStatusClass();
    });
    
    const rowBtn = document.getElementById('profile-pwa-btn');
    if (rowBtn) this.updateProfileRow(rowBtn);
  },

  getStatusLabel() {
    switch(this.state) {
      case this.STATES.INSTALLED: return 'مثبت ✓';
      case this.STATES.READY_TO_INSTALL: return 'جاهز للتثبيت';
      case this.STATES.INSTALL_PROMPTED: return 'بانتظار التأكيد';
      case this.STATES.UNSUPPORTED: return 'غير مدعوم';
      default: return 'غير جاهز';
    }
  },
  getStatusClass() {
    switch(this.state) {
      case this.STATES.INSTALLED: return 'pwa-state-installed';
      case this.STATES.READY_TO_INSTALL: return 'pwa-state-ready';
      case this.STATES.UNSUPPORTED: return 'pwa-state-unsupported';
      default: return 'pwa-state-unsupported';
    }
  },

  async prompt() {
    if (this.isStandalone()) { toast('التطبيق مثبت بالفعل'); return; }
    
    if (this.isIos() && !this.deferredPrompt) {
      this.openIosSheet();
      return;
    }
    if (!this.deferredPrompt) {
      
      console.warn('[PWA] prompt() called but deferredPrompt is null, state=', this.state);
      if (!window.isSecureContext) {
        toast('التثبيت يتطلب HTTPS أو تفعيل chrome:
        this.showDiag();
        return;
      }
      
      toast('التطبيق غير جاهز للتثبيت حالياً', 'error');
      this.showDiag();
      return;
    }
    this.setState(this.STATES.INSTALL_PROMPTED);
    this.render();
    try {
      this.deferredPrompt.prompt();
      const choice = await this.deferredPrompt.userChoice;
      console.log('[PWA] userChoice =', choice.outcome);
      if (choice.outcome === 'accepted') {
        this.setState(this.STATES.INSTALLED);
        toast('تم التثبيت بنجاح');
      } else {
        this.setState(this.STATES.READY_TO_INSTALL);
        toast('تم إلغاء التثبيت');
      }
    } catch(e) {
      console.error('[PWA] prompt() error', e);
      this.setState(this.STATES.READY_TO_INSTALL);
    } finally {
      this.deferredPrompt = null;
      window.deferredPWAInstallPrompt = null;
      this.render();
    }
  },

  dismiss() {
    this._bannerEl?.classList.remove('show');
    try { localStorage.setItem('pwa_banner_dismissed', String(Date.now())); this._dismissedAt = Date.now(); } catch(e){}
    console.log('[PWA] banner dismissed');
  },

  openIosSheet() { document.getElementById('pwa-ios-sheet')?.classList.add('show'); },
  closeIosSheet() { document.getElementById('pwa-ios-sheet')?.classList.remove('show'); },

  maybeShowDiag(msg) {
    if (location.search.includes('diag=1')) this.showDiag(msg);
  },

  showDiag(extra) {
    if (!this._diagEl) return;
    this._diagEl.style.display = 'block';
    const info = {
      state: this.state,
      isSecureContext: window.isSecureContext,
      protocol: location.protocol,
      host: location.hostname + ':' + location.port,
      origin: location.origin,
      isStandalone: this.isStandalone(),
      isIos: this.isIos(),
      userAgent: navigator.userAgent.slice(0,120),
      hasSW: 'serviceWorker' in navigator,
      hasDeferredPrompt: !!this.deferredPrompt,
      swController: navigator.serviceWorker?.controller ? 'present' : 'none',
      swScope: navigator.serviceWorker?.controller?.scriptURL || 'n/a',
      displayMode: window.matchMedia('(display-mode: standalone)').matches ? 'standalone' : 'browser',
      installedFlag: (function(){try{return localStorage.getItem('pwa_installed');}catch(e){return 'err'}})()
    };
    let html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b>PWA DIAG</b><button onclick="document.getElementById(\'pwa-diag\').style.display=\'none\'" style="background:#1f6f4a;color:#fff;border:none;padding:4px 8px;border-radius:6px;font-size:10px">إغلاق</button></div>';
    html += '<div style="display:grid;grid-template-columns:110px 1fr;gap:2px 8px">';
    for (const [k,v] of Object.entries(info)) html += `<div style="color:#94a3b8">${k}</div><div>${String(v)}</div>`;
    html += '</div>';
    if (extra) html += `<div style="margin-top:8px;color:#fbbf24">${extra}</div>`;
    if (!window.isSecureContext) {
      html += `<div style="margin-top:8px;background:#7f1d1d;padding:6px;border-radius:6px;color:#fecaca">⚠ SecureContext = false — Chrome يمنع SW/PWA على HTTP LAN (192.168.x.x). الحل: افتح <b>chrome:
    }
    html += `<div style="margin-top:8px;display:flex;gap:6px"><button onclick="PwaInstall.checkInstallability()" style="background:#1f6f4a;color:#fff;border:none;padding:6px 10px;border-radius:8px;font-size:11px">فحص مرة أخرى</button><button onclick="localStorage.setItem('pwa_diag','1');location.reload()" style="background:#334155;color:#fff;border:none;padding:6px 10px;border-radius:8px;font-size:11px">تثبيت التشخيص</button></div>`;
    this._diagEl.innerHTML = html;
  },

  async checkInstallability() {
    
    this.evaluateReadiness();
    this.render();
    this.showDiag('فحص يدوي…');
    try {
      const m = await fetch('/pwa/manifest.json', {cache:'no-store'}).then(r=>r.json());
      console.log('[PWA] manifest fetch ok', m);
      this.maybeShowDiag('manifest ok: ' + m.name);
    } catch(e){ console.warn('[PWA] manifest fetch failed', e); this.maybeShowDiag('manifest fetch failed: '+e); }
    if ('serviceWorker' in navigator) {
      try { const regs = await navigator.serviceWorker.getRegistrations(); console.log('[PWA] registrations', regs.map(r=>r.scope)); this.maybeShowDiag('SW registrations: '+ regs.map(r=>r.scope).join(', ')); } catch(e){}
    }
  },

  updateProfileRow(btn) {
    if (this.isStandalone() || this.state === this.STATES.INSTALLED) {
      btn.textContent = 'مثبت ✓';
      btn.disabled = true;
      btn.style.opacity = '.6';
    } else if (this.state === this.STATES.READY_TO_INSTALL) {
      btn.textContent = '📱 تثبيت الآن';
      btn.disabled = false;
      btn.style.opacity = '1';
    } else if (this.isIos()) {
      btn.textContent = 'طريقة التثبيت';
      btn.disabled = false;
    } else {
      btn.textContent = 'غير جاهز';
      btn.disabled = true;
      btn.style.opacity = '.5';
    }
  }
};

const App = {
  container: null,
  history: [],
  currentScreen: '',
  transitioning: false,

  async init() {
    this.container = document.getElementById('screen-container');
    window.addEventListener('hashchange', () => this.route());
    window.addEventListener('popstate', (e) => this.handleBack(e));
    
    try { PwaInstall.init(); } catch(e){ console.warn('[PWA] PwaInstall init failed', e); }
    this.initTheme();
    await this.route();
    setTimeout(() => document.getElementById('splash')?.classList.add('hide'), 600);
    setTimeout(() => document.getElementById('splash')?.remove(), 1100);
  },

  navigate(screen, param, skipHistory = false) {
    this.haptic('light');
    const hash = param !== undefined ? screen + '/' + param : screen;
    if (!skipHistory) this.history.push(window.location.hash.slice(1));
    window.location.hash = hash;
  },

  goBack() {
    this.haptic('light');
    if (this.history.length > 0) {
      const prev = this.history.pop();
      window.location.hash = prev || 'notes';
    } else {
      window.location.hash = 'notes';
    }
  },

  handleBack(e) {
    if (this.transitioning) return;
    const hash = window.location.hash.slice(1) || 'login';
    const [screen] = hash.split('/');
    if (screen !== this.currentScreen) this.transitionTo(screen);
  },

  async route() {
    const hash = window.location.hash.slice(1) || 'login';
    const [screen, param] = hash.split('/');
    const id = param ? parseInt(param) : null;

    if (screen !== 'login' && !Auth.isLoggedIn()) {
      const ok = await Auth.check();
      if (!ok) { this.navigate('login', undefined, true); return; }
    }
    if (screen === 'login' && Auth.isLoggedIn()) {
      this.navigate('notes', undefined, true);
      return;
    }

    this.renderScreen(screen, id);
  },

  renderScreen(screen, id) {
    this.transitioning = true;
    this.container.classList.add('screen-exit');
    setTimeout(() => {
      switch (screen) {
        case 'login': renderLogin(this.container); break;
        case 'notes': renderNotes(this.container); break;
        case 'create': renderCreate(this.container); break;
        case 'edit': if (id) renderEdit(this.container, id); break;
        case 'detail': if (id) renderDetail(this.container, id); break;
        case 'profile': renderProfile(this.container); break;
        default: renderNotes(this.container);
      }
      this.currentScreen = screen;
      this.container.classList.remove('screen-exit');
      this.container.classList.add('screen-enter');
      this.container.scrollTop = 0;
      window.scrollTo(0, 0);
      setTimeout(() => {
        this.container.classList.remove('screen-enter');
        this.transitioning = false;
      }, 300);
    }, 200);
  },

  async apiAction(fn) {
    try { return await fn(); }
    catch (e) {
      if (e?.status === 401) { Auth.clear(); this.navigate('login', undefined, true); }
      throw e;
    }
  },

  haptic(style = 'light') {
    if ('vibrate' in navigator) {
      switch (style) {
        case 'light': navigator.vibrate(10); break;
        case 'medium': navigator.vibrate(20); break;
        case 'heavy': navigator.vibrate([15, 30, 15]); break;
        case 'error': navigator.vibrate([50, 50, 50]); break;
      }
    }
  },

  ripple(e, el) {
    const btn = el || e.currentTarget;
    const r = document.createElement('span');
    r.className = 'ripple';
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    r.style.width = r.style.height = size + 'px';
    r.style.left = (e.clientX - rect.left - size / 2) + 'px';
    r.style.top = (e.clientY - rect.top - size / 2) + 'px';
    btn.appendChild(r);
    setTimeout(() => r.remove(), 600);
  },

  initTheme() {
    this.applyStoredTheme();
    window.addEventListener('pageshow', () => this.applyStoredTheme());
    window.addEventListener('storage', (e) => { if(e.key==='rasd_theme'||e.key==='theme') this.applyStoredTheme(); });
  },

  applyStoredTheme(){
    try{
      var t=localStorage.getItem('rasd_theme')||localStorage.getItem('theme');
      if(t==='light') document.documentElement.classList.remove('dark'); else document.documentElement.classList.add('dark');
    }catch(e){ document.documentElement.classList.add('dark'); }
    this.syncThemeIcons(document.documentElement.classList.contains('dark'));
  },

  toggleTheme() {
    this.haptic('light');
    const html = document.documentElement;
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark');
    try { localStorage.setItem('rasd_theme', isDark ? 'dark' : 'light'); localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (e) {}
    this.syncThemeIcons(isDark);
  },

  syncThemeIcons(isDark) {
    document.querySelectorAll('.sun-icon').forEach(e => e.style.display = isDark ? 'none' : 'block');
    document.querySelectorAll('.moon-icon').forEach(e => e.style.display = isDark ? 'block' : 'none');
  }
};


const ImageViewer = {
  images: [],
  current: 0,
  scale: 1,
  el: null,
  imgEl: null,
  startX: 0, startY: 0, startScale: 1,

  open(urls, index = 0) {
    this.images = urls;
    this.current = index;
    this.scale = 1;
    this.el = document.getElementById('image-viewer');
    this.el.innerHTML = `
      <button class="viewer-close" onclick="ImageViewer.close()">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      <img id="viewer-img" src="${urls[index]}" alt="مرفق" draggable="false">
      <div class="viewer-counter">${index + 1} / ${urls.length}</div>
      ${urls.length > 1 ? `
        <button class="viewer-nav prev" onclick="ImageViewer.prev()">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
        <button class="viewer-nav next" onclick="ImageViewer.next()">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>` : ''}`;
    this.el.classList.remove('hide');
    this.imgEl = document.getElementById('viewer-img');
    document.body.style.overflow = 'hidden';
    this.initTouch();
  },

  close() {
    this.el.classList.add('hide');
    document.body.style.overflow = '';
    App.haptic('light');
  },

  prev() { if (this.current > 0) { this.current--; this.update(); App.haptic('light'); } },
  next() { if (this.current < this.images.length - 1) { this.current++; this.update(); App.haptic('light'); } },

  update() {
    this.scale = 1;
    this.imgEl.src = this.images[this.current];
    this.imgEl.style.transform = 'scale(1)';
    this.el.querySelector('.viewer-counter').textContent = `${this.current + 1} / ${this.images.length}`;
  },

  initTouch() {
    let lastDist = 0, lastMid = null;
    const img = this.imgEl;

    img.addEventListener('touchstart', (e) => {
      if (e.touches.length === 2) {
        lastDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
      } else if (e.touches.length === 1) {
        this.startX = e.touches[0].clientX;
      }
    }, { passive: true });

    img.addEventListener('touchmove', (e) => {
      if (e.touches.length === 2) {
        const dist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        this.scale = Math.min(Math.max(this.scale * (dist / lastDist), 0.5), 4);
        lastDist = dist;
        img.style.transform = `scale(${this.scale})`;
      }
    }, { passive: true });

    img.addEventListener('touchend', () => {
      if (this.scale < 1) { this.scale = 1; img.style.transform = 'scale(1)'; }
    });

    img.addEventListener('dblclick', () => {
      this.scale = this.scale > 1 ? 1 : 2;
      img.style.transition = 'transform .2s';
      img.style.transform = `scale(${this.scale})`;
      setTimeout(() => img.style.transition = '', 200);
      App.haptic('light');
    });
  }
};


function skNotes() {
  return Array(4).fill('').map(() => `
    <div class="skeleton-card">
      <div class="skeleton-row">
        <div class="skeleton skeleton-text" style="width:80px"></div>
        <div class="skeleton skeleton-badge" style="flex-shrink:0"></div>
      </div>
      <div class="skeleton skeleton-text" style="width:90%;margin-bottom:8px"></div>
      <div class="skeleton skeleton-text-sm" style="width:40%"></div>
    </div>`).join('');
}

function skDetail() {
  return `
    <div class="skeleton-card">
      <div class="skeleton-row"><div class="skeleton skeleton-text" style="width:60px;height:50px;border-radius:12px"></div>
      <div class="skeleton skeleton-text" style="width:60px;height:50px;border-radius:12px"></div>
      <div class="skeleton skeleton-text" style="width:60px;height:50px;border-radius:12px"></div></div>
    </div>
    <div class="skeleton-card"><div class="skeleton skeleton-title" style="margin-bottom:8px"></div>
    <div class="skeleton skeleton-text" style="width:100%"></div>
    <div class="skeleton skeleton-text" style="width:80%"></div></div>`;
}

function skProfile() {
  return `
    <div style="text-align:center;padding:32px 16px">
      <div class="skeleton skeleton-avatar" style="width:64px;height:64px;margin:0 auto 12px"></div>
      <div class="skeleton skeleton-title" style="width:120px;margin:0 auto 8px"></div>
      <div class="skeleton skeleton-text-sm" style="width:80px;margin:0 auto"></div>
    </div>
    <div style="padding:0 16px">${Array(3).fill('').map(() => `
      <div class="skeleton-card" style="margin:0 0 8px"><div class="skeleton skeleton-text" style="width:60%"></div></div>
    `).join('')}</div>`;
}

function toast(msg, type = 'success') {
  App.haptic(type === 'error' ? 'error' : 'light');
  const el = document.createElement('div');
  el.className = 'toast toast-' + type;
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function timeRange(start, end) {
  return formatTime(start) + (end ? ' — ' + formatTime(end) : '');
}

function daysAgo(d) {
  const diff = (Date.now() - new Date(d)) / 86400000;
  if (diff < 1) return 'اليوم';
  if (diff < 2) return 'أمس';
  if (diff < 7) return Math.floor(diff) + ' أيام';
  return Math.floor(diff / 7) + ' أسابيع';
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function badge(status) {
  const m = { draft: ['مسودة', 'badge-draft'], pending: ['قيد المراجعة', 'badge-pending'],
    accepted: ['مقبولة', 'badge-accepted'], rejected: ['مرفوضة', 'badge-rejected'] };
  const [l, c] = m[status] || m.draft;
  return `<span class="badge ${c}"><span class="badge-dot"></span>${l}</span>`;
}

const StatusMap = { draft: 'مسودة', pending: 'قيد المراجعة', accepted: 'مقبولة', rejected: 'مرفوضة' };

const PwaShare = {
  _note: null,

  open(note) {
    if (!navigator.share || !navigator.canShare) {
      alert('المشاركة غير مدعومة في هذا المتصفح');
      return;
    }
    this._note = note;
    const isDraft = note.status === 'draft';
    const isPending = note.status === 'pending';
    const statusLabel = isDraft ? 'مسودة' : isPending ? 'قيد المراجعة' : 'مقبولة';
    const dt = new Date(note.observed_at);
    const dateStr = dt.toLocaleDateString('ar-EG');
    const timeStr = dt.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', hour12: true });
    let endStr = '';
    if (note.observed_end_at) {
      endStr = ' — ' + new Date(note.observed_end_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    const text = `الطابق: ${note.floor_number} — الكاميرا: ${note.camera_number}\nالملاحظة: ${dateStr} ${timeStr}${endStr}\n${note.description}`;

    document.getElementById('share-text').value = text;

    const section = document.getElementById('share-attachments-section');
    const list = document.getElementById('share-attachments');
    const atts = note.attachments || [];
    if (atts.length === 0) {
      section.style.display = 'none';
      list.innerHTML = '';
    } else {
      section.style.display = '';
      list.innerHTML = atts.map(a => {
        const isVideo = (a.mime_type || '').includes('video');
        const icon = isVideo
          ? '<svg width="18" height="18" fill="none" stroke="#f87171" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>'
          : '<svg width="18" height="18" fill="none" stroke="var(--sage-600)" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
        const safeMime = a.mime_type || 'application/octet-stream';
return `<label class="share-attach-item">
          <input type="checkbox" class="share-file-cb" data-id="${a.id}" data-name="${a.original_name || a.file_name}" data-mime="${safeMime}" checked onchange="PwaShare._updateCount()">
          <span class="share-attach-icon">${icon}</span>
          <span class="share-attach-name">${a.original_name || a.file_name}</span>
        </label>`;
      }).join('');
    }

    this._updateCount();
    document.getElementById('share-modal').classList.remove('hide');
    document.body.style.overflow = 'hidden';
  },

  close() {
    document.getElementById('share-modal').classList.add('hide');
    document.body.style.overflow = '';
    this._note = null;
  },

  _updateCount() {
    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const all = document.querySelectorAll('.share-file-cb');
    const countEl = document.getElementById('share-selected-count');
    if (countEl) countEl.textContent = cbs.length + ' من ' + all.length;
  },

  async send() {
    if (!navigator.share || !navigator.canShare) {
      alert('المشاركة غير مدعومة في هذا المتصفح');
      return;
    }
    const btn = document.getElementById('share-send-btn');
    const btnText = document.getElementById('share-send-text');
    const text = document.getElementById('share-text').value.trim();

    btn.disabled = true;
    btnText.textContent = 'جاري التجهيز...';

    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const files = [];
    const baseUrl = window.location.origin;

    
    for (const cb of cbs) {
      try {
        const url = baseUrl + '/api/attachments/' + cb.dataset.id + '/download';
        const res = await fetch(url);
        if (!res.ok) continue;
        const blob = await res.blob();
        const name = cb.dataset.name || 'attachment';
        const mime = cb.dataset.mime || 'application/octet-stream';
        
        const file = new File([blob], name, { type: mime });
        files.push(file);
      } catch(e) {
        console.warn('Failed to load attachment:', e);
      }
    }

    const shareData = {};
    if (text) shareData.text = text;
    if (files.length > 0) {
      
      if (navigator.canShare({ files })) {
        shareData.files = files;
        shareData.text = text || '';
      } else {
        
        btn.disabled = false;
        btnText.textContent = 'مشاركة';
        alert('تم تجهيز النص، ولكن المتصفح لا يدعم إرفاق الملفات عبر مشاركة الويب. سيُنسخ النص فقط.');
        shareData.text = text || '';
        shareData.files = [];
      }
    }
    if (!shareData.text && !shareData.files) {
      btn.disabled = false;
      btnText.textContent = 'مشاركة';
      alert('اختر رسالة أو وسائط للمشاركة');
      return;
    }
    try {
      await navigator.share(shareData);
      this.close();
    } catch(e) {
      if (e.name !== 'AbortError') alert('تعذرت المشاركة');
    } finally {
      btn.disabled = false;
      btnText.textContent = 'مشاركة';
    }
  },

  copyText() {
    const text = document.getElementById('share-text');
    navigator.clipboard.writeText(text.value).then(() => {
      const btn = document.getElementById('share-copy-btn');
      btn.textContent = 'تم النسخ';
      setTimeout(() => btn.textContent = 'نسخ', 1500);
    });
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
