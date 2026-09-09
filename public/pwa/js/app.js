const App = {
  container: null,
  history: [],
  currentScreen: '',
  transitioning: false,

  async init() {
    this.container = document.getElementById('screen-container');
    window.addEventListener('hashchange', () => this.route());
    window.addEventListener('popstate', (e) => this.handleBack(e));
    // PWA install disabled - service worker not registered
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(rs=>rs.forEach(r=>r.unregister()));
    }
    this.initTheme();
    await this.route();
    setTimeout(() => document.getElementById('splash')?.classList.add('hide'), 600);
    setTimeout(() => document.getElementById('splash')?.remove(), 1100);
  },
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

/* ===== IMAGE VIEWER ===== */
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

/* ===== SKELETON HELPERS ===== */
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

    // جمع الملفات من الـ checkboxes
    for (const cb of cbs) {
      try {
        const url = baseUrl + '/api/attachments/' + cb.dataset.id + '/download';
        const res = await fetch(url);
        if (!res.ok) continue;
        const blob = await res.blob();
        const name = cb.dataset.name || 'attachment';
        const mime = cb.dataset.mime || 'application/octet-stream';
        // Create File object with proper MIME type
        const file = new File([blob], name, { type: mime });
        files.push(file);
      } catch(e) {
        console.warn('Failed to load attachment:', e);
      }
    }

    const shareData = {};
    if (text) shareData.text = text;
    if (files.length > 0) {
      // Try sharing with files first (best for Android WhatsApp)
      if (navigator.canShare({ files })) {
        shareData.files = files;
        shareData.text = text || '';
      } else {
        // Fallback: can share text but not files - show warning
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
