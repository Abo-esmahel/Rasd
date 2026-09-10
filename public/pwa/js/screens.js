/* ===== CAMERA HELPER =====
   Works everywhere:
   - HTTPS / localhost → getUserMedia (full camera UI)
   - HTTP LAN / any HTTP → <input type="file"> (shows camera option on Android/iOS)
   - Desktop → file picker (webcam if available) */
function openCamera(fileInput, onFiles) {
  const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  const hasMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

  if (isSecure && hasMedia) {
    tryCameraModal(fileInput, onFiles);
  } else {
    fileInput.click();
  }
}

function tryCameraModal(fileInput, onFiles) {
  const existing = document.getElementById('camera-modal');
  if (existing) existing.remove();
  const modal = document.createElement('div');
  modal.id = 'camera-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:10000;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center';
  modal.innerHTML = `
    <div style="position:absolute;top:12px;right:12px;z-index:2">
      <button id="cam-close" style="background:rgba(255,255,255,0.2);border:none;color:white;width:40px;height:40px;border-radius:50%;font-size:20px;cursor:pointer">✕</button>
    </div>
    <video id="cam-video" autoplay playsinline style="max-width:100%;max-height:70vh;border-radius:12px"></video>
    <div style="position:absolute;bottom:30px;display:flex;gap:20px;align-items:center">
      <button id="cam-flip" style="background:rgba(255,255,255,0.2);border:none;color:white;width:44px;height:44px;border-radius:50%;font-size:18px;cursor:pointer">⟲</button>
      <button id="cam-capture" style="background:white;border:none;width:64px;height:64px;border-radius:50%;cursor:pointer;box-shadow:0 0 20px rgba(0,0,0,0.3)"></button>
      <button id="cam-gallery" style="background:rgba(255,255,255,0.2);border:none;color:white;width:44px;height:44px;border-radius:50%;font-size:18px;cursor:pointer">🖼</button>
    </div>
    <div id="cam-error" style="color:white;text-align:center;padding:20px;display:none"></div>`;
  document.body.appendChild(modal);
  const video = document.getElementById('cam-video');
  const errEl = document.getElementById('cam-error');
  let stream = null;
  let facing = 'environment';
  async function startCamera() {
    try {
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } } });
      video.srcObject = stream;
      video.style.display = '';
      errEl.style.display = 'none';
    } catch (e) {
      video.style.display = 'none';
      errEl.style.display = 'block';
      errEl.innerHTML = '<div style="font-size:48px;margin-bottom:12px">📷</div><div style="font-size:16px;font-weight:bold;margin-bottom:8px">الكاميرا غير متوفرة</div><div style="font-size:13px;opacity:0.7">اختر صورة من المعرض</div><button id="cam-gallery-fallback" style="margin-top:16px;background:#1f6f4a;color:white;border:none;padding:10px 24px;border-radius:12px;font-weight:bold;cursor:pointer">اختيار من المعرض</button>';
      document.getElementById('cam-gallery-fallback')?.addEventListener('click', () => { modal.remove(); fileInput.click(); });
    }
  }
  startCamera();
  document.getElementById('cam-close').addEventListener('click', () => { if (stream) stream.getTracks().forEach(t => t.stop()); modal.remove(); });
  document.getElementById('cam-flip').addEventListener('click', () => { facing = facing === 'environment' ? 'user' : 'environment'; startCamera(); });
  document.getElementById('cam-gallery').addEventListener('click', () => { if (stream) stream.getTracks().forEach(t => t.stop()); modal.remove(); fileInput.click(); });
  document.getElementById('cam-capture').addEventListener('click', () => {
    App.haptic('heavy');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
      if (blob) {
        const file = new File([blob], `camera_${Date.now()}.jpg`, { type: 'image/jpeg' });
        onFiles([file]);
      }
      if (stream) stream.getTracks().forEach(t => t.stop());
      modal.remove();
      toast('تم التقاط الصورة');
    }, 'image/jpeg', 1.0);
  });
}

/* ===== HELPER: Bottom Nav ===== */
function bottomNav(active) {
  const items = [
    { key: 'notes', label: 'الملاحظات', icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' },
    { key: 'create', label: 'جديدة', icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>' },
    { key: 'profile', label: 'حسابي', icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>' },
  ];
  return `<nav class="bottom-nav">${items.map(i => `
    <div class="nav-item ${active === i.key ? 'active' : ''}" onclick="App.navigate('${i.key}')">
      ${i.icon}<span>${i.label}</span>
    </div>`).join('')}</nav>`;
}

/* ===== LOGIN SCREEN ===== */
function renderLogin(container) {
  container.innerHTML = `
    <div class="login-page">
      <div class="login-card">
        <div class="login-logo">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
        </div>
        <h1 class="login-title">نظام ملاحظة</h1>
        <p class="login-subtitle">سجّل دخولك للمتابعة</p>
        <form class="login-form" id="login-form">
          <div class="input-group">
            <label>اسم المستخدم <span class="required">*</span></label>
            <input class="input" type="text" id="login-user" placeholder="أدخل اسم المستخدم" required autocomplete="username">
          </div>
          <div class="input-group">
            <label>كلمة المرور <span class="required">*</span></label>
            <input class="input" type="password" id="login-pass" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
          </div>
          <div id="login-error" class="error-msg" style="display:none"></div>
          <button class="btn btn-primary btn-full" type="submit" id="login-btn">تسجيل الدخول</button>
        </form>
        <div class="login-footer">وزارة الإعلام — نظام المراقبة</div>
      </div>
    </div>`;
  document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    const errEl = document.getElementById('login-error');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    errEl.style.display = 'none';
    App.haptic('medium');
    try {
      await Auth.login(
        document.getElementById('login-user').value.trim(),
        document.getElementById('login-pass').value
      );
      App.navigate('notes');
    } catch (err) {
      App.haptic('error');
      errEl.textContent = err?.errors?.username?.[0] || err?.message || 'بيانات الدخول غير صحيحة';
      errEl.style.display = 'block';
    } finally {
      btn.disabled = false;
      btn.textContent = 'تسجيل الدخول';
    }
  });
}

/* ===== NOTES LIST SCREEN ===== */
let notesPage = 1, notesFilter = {}, notesData = null, notesLoading = false, notesSearch = '', notesMineMode = false;

async function loadNotes(page = 1, append = false) {
  if (notesLoading) return;
  notesLoading = true;
  const listEl = document.getElementById('notes-list');
  const params = { page, ...notesFilter };
  if (notesSearch) params.search = notesSearch;
  try {
    const res = notesMineMode ? await API.myNotes(params) : await API.notes(params);
    if (res.success) {
      if (append && notesData) {
        notesData.data = [...notesData.data, ...res.data.data];
        notesData.current_page = res.data.current_page;
        notesData.last_page = res.data.last_page;
        notesData.total = res.data.total;
      } else {
        notesData = res.data;
      }
      notesPage = res.data.current_page;
      renderNotesList();
      updateTabCounts();
    }
  } catch (e) {
    if (!append && listEl) {
      listEl.innerHTML = `<div class="empty-state">
        <div class="empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg></div>
        <div class="empty-title">${!API.isOnline() ? 'لا يوجد اتصال' : 'خطأ في تحميل البيانات'}</div>
        <div class="empty-desc">${!API.isOnline() ? 'تحقق من اتصالك بالإنترنت' : 'يرجى المحاولة مرة أخرى'}</div>
        <button class="btn btn-primary btn-sm" style="margin-top:12px" onclick="loadNotes(1)">إعادة المحاولة</button>
      </div>`;
    }
  } finally { notesLoading = false; }
}

function renderNotesList() {
  const listEl = document.getElementById('notes-list');
  const loadMoreEl = document.getElementById('load-more');
  if (!notesData) return;
  const notes = notesData.data;
  if (!notes.length) {
    listEl.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></div>
        <div class="empty-title">لا توجد ملاحظات</div>
        <div class="empty-desc">لم يتم العثور على نتائج مطابقة</div>
      </div>`;
    if (loadMoreEl) loadMoreEl.style.display = 'none';
    return;
  }
  listEl.innerHTML = notes.map(n => {
    const canShare = n.status !== 'rejected' && n.owner?.personal_number;
    const statusLabel = n.status === 'draft' ? 'مسودة' : n.status === 'pending' ? 'قيد المراجعة' : 'مقبولة';
    return `
    <div class="note-item" onclick="App.navigate('detail', ${n.id})">
      <div class="note-top">
        <div class="note-meta">
          <span class="note-camera">كاميرا ${n.camera_number}</span>
          <span class="note-floor">الطابق ${n.floor_number}</span>
          <span class="note-time">${timeRange(n.observed_at, n.observed_end_at)}</span>
        </div>
        ${badge(n.status)}
      </div>
      <div class="note-desc">${escHtml(n.description)}</div>
      <div class="note-bottom">
        <div class="note-owner">
          <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          ${escHtml(n.owner?.name || '—')}
        </div>
        ${n.attachments?.length ? `<div class="note-attach">
          <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
          ${n.attachments.length}
        </div>` : ''}
        ${canShare ? `<button onclick="event.stopPropagation();PwaShare.open(notes.find(x=>x.id===${n.id}))" class="note-share" title="مشاركة">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8m-4-6l-4-4m0 0L8 6m4-4v13"/></svg>
        </button>` : ''}
        <div class="note-time">${daysAgo(n.created_at)}</div>
      </div>
    </div>`}).join('');

  if (loadMoreEl) {
    if (notesData.current_page < notesData.last_page) {
      loadMoreEl.style.display = 'flex';
      loadMoreEl.innerHTML = '<div class="spinner"></div>';
    } else {
      loadMoreEl.style.display = 'none';
    }
  }
}

function updateTabCounts() {
  if (!notesData) return;
  const total = notesData.total;
  if (!notesMineMode) {
    document.getElementById('cnt-all').textContent = total;
  } else {
    document.getElementById('cnt-mine').textContent = total;
  }
}

function renderNotes(container) {
  const user = Auth.user;
  notesFilter = {};
  notesData = null;
  notesSearch = '';
  notesMineMode = false;
  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div>
          <div class="header-title">الملاحظات</div>
          <div class="header-subtitle">${escHtml(user?.name || '')} · ${user?.role === 'monitor' ? 'مراقب' : 'كاتب تقارير'}</div>
        </div>
        <div class="header-actions">
          <button class="header-btn" onclick="App.toggleTheme()" title="الوضع الداكن">
            <svg class="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <svg class="moon-icon" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
          </button>
          <button class="header-btn" onclick="App.navigate('profile')" title="الملف الشخصي">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </button>
        </div>
      </div>
    </div>
    <div class="search-bar">
      <input type="text" placeholder="بحث في الملاحظات..." id="notes-search" value="">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    </div>
    <div class="tab-bar" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
      <button class="tab active" data-status="" data-mine="0">الكل <span class="count" id="cnt-all">0</span></button>
      <button class="tab" data-status="" data-mine="1">ملاحظاتي <span class="count" id="cnt-mine">0</span></button>
      <button class="tab" data-status="draft">مسودات</button>
      <button class="tab" data-status="pending">قيد المراجعة</button>
      <button class="tab" data-status="accepted">مقبولة</button>
      <button class="tab" data-status="rejected">مرفوضة</button>
    </div>
    <div class="filter-section">
      <div class="filter-row">
        <select class="input" id="filter-floor" style="max-width:120px;padding:8px 32px 8px 12px;font-size:12px">
          <option value="">الطابق</option>
          ${[0,1,2,3,4,5,6,7,8,9,10].map(f => `<option value="${f}">${f}</option>`).join('')}
        </select>
        <select class="input" id="filter-camera" style="max-width:120px;padding:8px 32px 8px 12px;font-size:12px">
          <option value="">الكاميرا</option>
          ${[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20].map(c => `<option value="${c}">${c}</option>`).join('')}
        </select>
        <input type="date" class="input" id="filter-date" style="max-width:150px;padding:8px 12px;font-size:12px">
      </div>
    </div>
    <div id="notes-list">${skNotes()}</div>
    <div id="load-more" style="display:none;align-items:center;justify-content:center;padding:16px"><div class="spinner"></div></div>
    ${bottomNav('notes')}`;

  let searchTimeout;
  document.getElementById('notes-search').addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      notesSearch = e.target.value.trim();
      loadNotes(1);
    }, 400);
  });

  container.querySelectorAll('.tab').forEach(t => {
    t.addEventListener('click', () => {
      App.haptic('light');
      container.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      notesMineMode = t.dataset.mine === '1';
      notesFilter.status = t.dataset.status || '';
      loadNotes(1);
    });
  });

  ['filter-floor', 'filter-camera', 'filter-date'].forEach(id => {
    document.getElementById(id).addEventListener('change', (e) => {
      const key = id === 'filter-floor' ? 'floor_number' : id === 'filter-camera' ? 'camera_number' : 'date';
      notesFilter[key] = e.target.value || '';
      loadNotes(1);
    });
  });

  // Infinite Scroll
  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && notesData && notesData.current_page < notesData.last_page && !notesLoading) {
      loadNotes(notesData.current_page + 1, true);
    }
  }, { threshold: 0.1 });
  const sentinel = document.getElementById('load-more');
  if (sentinel) observer.observe(sentinel);

  loadNotes(1);
  // Load "my notes" count in background
  API.myNotes({ per_page: 1 }).then(res => {
    if (res.success) {
      const el = document.getElementById('cnt-mine');
      if (el) el.textContent = res.data.total;
    }
  }).catch(() => {});
}

/* ===== CREATE SCREEN ===== */
function renderCreate(container) {
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  const today = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
  const curTime = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:10px">
          <button class="header-btn" onclick="App.goBack()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
          <div class="header-title">ملاحظة جديدة</div>
        </div>
      </div>
    </div>
    <div class="form-page" style="padding-bottom:80px">
      <div class="form-card">
        <form id="create-form">
          <div class="form-row" style="margin-bottom:14px">
            <div class="input-group">
              <label>رقم الطابق <span class="required">*</span></label>
              <select class="input" name="floor_number" required>
                <option value="">اختر الطابق</option>
                ${[0,1,2,3,4,5,6,7,8,9,10].map(f => `<option value="${f}">${f}</option>`).join('')}
              </select>
            </div>
            <div class="input-group">
              <label>رقم الكاميرا <span class="required">*</span></label>
              <select class="input" name="camera_number" required>
                <option value="">اختر الكاميرا</option>
                ${[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20].map(c => `<option value="${c}">${c}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              الملاحظة <span class="required">*</span>
            </div>
            <div class="dt-box">
              <div class="input-group" style="margin-bottom:8px">
                <label>التاريخ</label>
                <input type="date" class="input" id="cr-date" value="${today}" required style="padding:10px 12px">
              </div>
              <div class="dt-row">
                <div class="input-group">
                  <label>بداية الملاحظة</label>
                  <input type="time" class="input" id="cr-time" value="${curTime}" required step="60" style="padding:10px 12px">
                </div>
                <div class="input-group">
                  <label>انتهاء الملاحظة</label>
                  <input type="time" class="input" id="cr-end" step="60" style="padding:10px 12px">
                </div>
              </div>
              <div class="dt-presets">
                <button type="button" class="dt-preset active" data-p="now">الآن</button>
                <button type="button" class="dt-preset" data-p="hour">قبل ساعة</button>
                <button type="button" class="dt-preset" data-p="morning">08:00</button>
              </div>
              <div class="dt-preview">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span id="cr-preview">— اختر الوقت —</span>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              الوصف <span class="required">*</span>
            </div>
            <textarea class="input" name="description" rows="4" required maxlength="5000" placeholder="صف ما تم رصده بدقة..." id="cr-desc"></textarea>
            <div style="text-align:left;font-size:11px;color:var(--ink-300);font-weight:600;margin-top:4px"><span id="cr-desc-cnt">0</span> / 5000</div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              المرفقات <span style="color:var(--ink-300);font-weight:500;font-size:12px">— اختياري</span>
            </div>
            <div class="file-zone" id="cr-file-zone">
              <div class="file-zone-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg></div>
              <div class="file-zone-text">اسحب وأفلت هنا أو</div>
              <div style="display:flex;gap:8px;justify-content:center;margin-top:8px;flex-wrap:wrap">
                <label class="file-zone-btn" for="cr-files">اختيار ملفات</label>
                <button type="button" class="file-zone-btn" id="cr-camera-btn" style="background:var(--ink-600)">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  الكاميرا
                </button>
              </div>
              <input type="file" id="cr-files" multiple accept="image/*,video/*,audio/*" style="display:none">
              <input type="file" id="cr-camera" accept="image/*" style="display:none">
              <div style="font-size:11px;color:var(--ink-300);margin-top:6px">صور/فيديو/صوت — حتى 10 ملفات (20MB صورة، 100MB فيديو/صوت)</div>
              <div class="file-list" id="cr-file-list"></div>
            </div>
          </div>

          <div style="display:flex;gap:10px;padding-top:16px;border-top:1px solid var(--border)">
            <button type="button" class="btn btn-secondary" onclick="App.goBack()" style="flex:0">إلغاء</button>
            ${Auth.user?.role === 'report_writer'
              ? '<button type="submit" name="action" value="save" class="btn btn-primary" style="flex:1">حفظ الملاحظة</button>'
              : '<button type="submit" name="action" value="save" class="btn btn-secondary" style="flex:1">حفظ كمسودة</button><button type="submit" name="action" value="send" class="btn btn-primary" style="flex:1">إرسال للمراجعة</button>'}
          </div>
        </form>
      </div>
    </div>
    ${bottomNav('create')}`;

  // DateTime
  const dateEl = document.getElementById('cr-date');
  const timeEl = document.getElementById('cr-time');
  const endEl = document.getElementById('cr-end');
  const previewEl = document.getElementById('cr-preview');
  function updatePreview() {
    const d = dateEl.value, t = timeEl.value, e = endEl.value;
    if (d && t) {
      const dt = new Date(d + 'T' + t);
      let txt = dt.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
      if (e) txt += ' — ' + e;
      previewEl.textContent = txt;
    } else { previewEl.textContent = '— اختر الوقت —'; }
  }
  dateEl.addEventListener('change', updatePreview);
  timeEl.addEventListener('change', updatePreview);
  endEl.addEventListener('change', updatePreview);
  document.querySelectorAll('.dt-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      App.haptic('light');
      const now = new Date();
      if (btn.dataset.p === 'hour') now.setHours(now.getHours() - 1);
      else if (btn.dataset.p === 'morning') now.setHours(8, 0, 0, 0);
      dateEl.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
      timeEl.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
      document.querySelectorAll('.dt-preset').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      updatePreview();
    });
  });

  const descEl = document.getElementById('cr-desc');
  const cntEl = document.getElementById('cr-desc-cnt');
  descEl.addEventListener('input', () => cntEl.textContent = descEl.value.length);

  // Files
  let crFiles = [];
  const crFileInput = document.getElementById('cr-files');
  const crCameraInput = document.getElementById('cr-camera');
  const crFileList = document.getElementById('cr-file-list');
  const crFileZone = document.getElementById('cr-file-zone');

  document.getElementById('cr-camera-btn')?.addEventListener('click', () => openCamera(crCameraInput, files => { crFiles = [...crFiles, ...files].slice(0, 5); renderCrFiles(); }));

  function renderCrFiles() {
    if (!crFiles.length) { crFileList.innerHTML = ''; return; }
    crFileList.innerHTML = crFiles.map((f, i) => `
      <div class="file-item">
        <span style="font-size:14px">${f.type.startsWith('video/') ? '🎬' : '🖼️'}</span>
        <div class="file-item-info"><div class="file-item-name">${escHtml(f.name)}</div><div class="file-item-size">${(f.size/1024/1024).toFixed(2)} MB</div></div>
        <div class="file-item-remove" onclick="crRemoveFile(${i})"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></div>
      </div>`).join('');
  }
  window.crRemoveFile = i => { crFiles.splice(i, 1); renderCrFiles(); App.haptic('light'); };

  function addFiles(files) { crFiles = [...crFiles, ...Array.from(files)].slice(0, 5); renderCrFiles(); }
  crFileInput.addEventListener('change', e => addFiles(e.target.files));
  crCameraInput.addEventListener('change', e => addFiles(e.target.files));
  ['dragenter', 'dragover'].forEach(ev => crFileZone.addEventListener(ev, e => { e.preventDefault(); crFileZone.classList.add('dragover'); }));
  ['dragleave', 'drop'].forEach(ev => crFileZone.addEventListener(ev, e => { e.preventDefault(); crFileZone.classList.remove('dragover'); }));
  crFileZone.addEventListener('drop', e => addFiles(e.dataTransfer.files));

  document.getElementById('create-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.submitter;
    const action = btn?.value || 'save';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    App.haptic('medium');
    try {
      const data = {
        floor_number: parseInt(e.target.floor_number.value),
        camera_number: parseInt(e.target.camera_number.value),
        observed_at: dateEl.value + 'T' + timeEl.value,
        description: descEl.value.trim(),
      };
      if (endEl.value) data.observed_end_at = dateEl.value + 'T' + endEl.value;
      const res = await App.apiAction(() => API.createNote(data));
      if (res.success) {
        for (const f of crFiles) { await App.apiAction(() => API.uploadAttachment(res.data.id, f)); }
        if (action === 'send') await App.apiAction(() => API.sendNote(res.data.id));
        toast(action === 'send' ? 'تم الإرسال للمراجعة' : 'تم الحفظ كمسودة');
        App.navigate('notes');
      }
    } catch (err) { toast(err?.message || 'حدث خطأ', 'error'); App.haptic('error'); }
    finally { btn.disabled = false; btn.textContent = action === 'send' ? 'إرسال للمراجعة' : 'حفظ كمسودة'; }
  });
  updatePreview();
}

/* ===== EDIT SCREEN ===== */
async function renderEdit(container, noteId) {
  container.innerHTML = skDetail();
  try {
    const res = await API.note(noteId);
    if (!res.success) throw new Error();
    const note = res.data;
    const dt = new Date(note.observed_at);
    const pad = n => String(n).padStart(2, '0');
    const dateVal = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}`;
    const timeVal = `${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
    let endVal = '';
    if (note.observed_end_at) { const et = new Date(note.observed_end_at); endVal = `${pad(et.getHours())}:${pad(et.getMinutes())}`; }

    container.innerHTML = `
      <div class="app-header">
        <div class="header-row">
          <div style="display:flex;align-items:center;gap:10px">
            <button class="header-btn" onclick="App.goBack()">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <div class="header-title">تعديل الملاحظة</div>
          </div>
          ${badge(note.status)}
        </div>
      </div>
      <div class="form-page">
        ${note.status === 'rejected' && note.rejection_reason ? `
          <div class="reject-banner">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="reject-banner-content">
              <div class="reject-banner-title">سبب الرفض</div>
              <div class="reject-banner-text">${escHtml(note.rejection_reason)}</div>
            </div>
          </div>` : ''}
        <div class="form-card">
          <form id="edit-form">
            <div class="form-row" style="margin-bottom:14px">
              <div class="input-group">
                <label>رقم الطابق <span class="required">*</span></label>
                <select class="input" name="floor_number" required>
                  ${[0,1,2,3,4,5,6,7,8,9,10].map(f => `<option value="${f}" ${note.floor_number == f ? 'selected' : ''}>${f}</option>`).join('')}
                </select>
              </div>
              <div class="input-group">
                <label>رقم الكاميرا <span class="required">*</span></label>
                <select class="input" name="camera_number" required>
                  ${[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20].map(c => `<option value="${c}" ${note.camera_number == c ? 'selected' : ''}>${c}</option>`).join('')}
                </select>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                الملاحظة <span class="required">*</span>
              </div>
              <div class="dt-box">
                <div class="input-group" style="margin-bottom:8px">
                  <label>التاريخ</label>
                  <input type="date" class="input" id="ed-date" value="${dateVal}" required style="padding:10px 12px">
                </div>
                <div class="dt-row">
                  <div class="input-group">
                    <label>بداية الملاحظة</label>
                    <input type="time" class="input" id="ed-time" value="${timeVal}" required step="60" style="padding:10px 12px">
                  </div>
                  <div class="input-group">
                    <label>انتهاء الملاحظة</label>
                    <input type="time" class="input" id="ed-end" value="${endVal}" step="60" style="padding:10px 12px">
                  </div>
                </div>
                <div class="dt-presets">
                  <button type="button" class="dt-preset" data-p="now">الآن</button>
                  <button type="button" class="dt-preset" data-p="hour">قبل ساعة</button>
                  <button type="button" class="dt-preset" data-p="morning">08:00</button>
                </div>
                <div class="dt-preview">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span id="ed-preview">—</span>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                الوصف <span class="required">*</span>
              </div>
              <textarea class="input" name="description" rows="4" required maxlength="5000" id="ed-desc">${escHtml(note.description)}</textarea>
            </div>

            ${note.attachments?.length ? `
              <div class="form-section">
                <div class="form-section-title">المرفقات الحالية</div>
                <div class="file-list">
                  ${note.attachments.map(a => `
                    <div class="file-item" id="att-${a.id}">
                      <span style="font-size:14px">${a.mime_type?.includes('video') ? '🎬' : '🖼️'}</span>
                      <div class="file-item-info"><div class="file-item-name">${escHtml(a.original_name)}</div><div class="file-item-size">${a.mime_type} — ${(a.file_size/1024).toFixed(1)} KB</div></div>
                      <div class="file-item-remove" onclick="deleteAtt(${note.id},${a.id})"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></div>
                    </div>`).join('')}
                </div>
              </div>` : ''}

            <div class="form-section">
              <div class="form-section-title">إضافة مرفقات</div>
              <div class="file-zone" id="ed-file-zone">
                <div class="file-zone-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:4px;flex-wrap:wrap">
                  <label class="file-zone-btn" for="ed-files">اختيار ملفات</label>
                  <button type="button" class="file-zone-btn" id="ed-camera-btn" style="background:var(--ink-600)">الكاميرا</button>
                </div>
              <input type="file" id="ed-files" multiple accept="image/*,video/*,audio/*" style="display:none">
              <input type="file" id="ed-camera" accept="image/*" style="display:none">
                <div class="file-list" id="ed-file-list"></div>
              </div>
            </div>

            <div style="display:flex;gap:10px;padding-top:16px;border-top:1px solid var(--border)">
              <button type="button" class="btn btn-secondary" onclick="App.goBack()">إلغاء</button>
              <button type="submit" class="btn btn-primary" style="flex:1">حفظ التعديلات</button>
            </div>
          </form>
        </div>
      </div>`;

    // DateTime
    const dateEl = document.getElementById('ed-date');
    const timeEl = document.getElementById('ed-time');
    const endEl = document.getElementById('ed-end');
    const previewEl = document.getElementById('ed-preview');
    function updatePreview() {
      const d = dateEl.value, t = timeEl.value, e = endEl.value;
      if (d && t) {
        const dt2 = new Date(d + 'T' + t);
        let txt = dt2.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
        if (e) txt += ' — ' + e;
        previewEl.textContent = txt;
      }
    }
    dateEl.addEventListener('change', updatePreview);
    timeEl.addEventListener('change', updatePreview);
    endEl.addEventListener('change', updatePreview);
    document.querySelectorAll('.dt-preset').forEach(btn => {
      btn.addEventListener('click', () => {
        App.haptic('light');
        const now = new Date();
        if (btn.dataset.p === 'hour') now.setHours(now.getHours() - 1);
        else if (btn.dataset.p === 'morning') now.setHours(8, 0, 0, 0);
        dateEl.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
        timeEl.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
        updatePreview();
      });
    });
    updatePreview();

    // Files
    let edFiles = [];
    const edFileInput = document.getElementById('ed-files');
    const edCameraInput = document.getElementById('ed-camera');
    const edFileList = document.getElementById('ed-file-list');
    document.getElementById('ed-camera-btn')?.addEventListener('click', () => openCamera(edCameraInput, files => { edFiles = [...edFiles, ...files].slice(0, 5); renderEdFiles(); }));
    function renderEdFiles() {
      edFileList.innerHTML = edFiles.map((f, i) => `
        <div class="file-item">
          <span style="font-size:14px">${f.type.startsWith('video/') ? '🎬' : '🖼️'}</span>
          <div class="file-item-info"><div class="file-item-name">${escHtml(f.name)}</div></div>
          <div class="file-item-remove" onclick="edRemoveFile(${i})"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></div>
        </div>`).join('');
    }
    window.edRemoveFile = i => { edFiles.splice(i, 1); renderEdFiles(); App.haptic('light'); };
    function addEdFiles(files) { edFiles = [...edFiles, ...Array.from(files)].slice(0, 5); renderEdFiles(); }
    edFileInput.addEventListener('change', e => addEdFiles(e.target.files));
    edCameraInput.addEventListener('change', e => addEdFiles(e.target.files));

    window.deleteAtt = async (nid, aid) => {
      if (!confirm('هل أنت متأكد من حذف المرفق؟')) return;
      try { await API.deleteAttachment(nid, aid); document.getElementById('att-' + aid)?.remove(); toast('تم الحذف'); App.haptic('medium'); }
      catch (e) { toast('خطأ في الحذف', 'error'); }
    };

    document.getElementById('edit-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = e.submitter;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>';
      App.haptic('medium');
      try {
        const data = {
          floor_number: parseInt(e.target.floor_number.value),
          camera_number: parseInt(e.target.camera_number.value),
          observed_at: dateEl.value + 'T' + timeEl.value,
          description: document.getElementById('ed-desc').value.trim(),
        };
        if (endEl.value) data.observed_end_at = dateEl.value + 'T' + endEl.value;
        await App.apiAction(() => API.updateNote(note.id, data));
        for (const f of edFiles) { await App.apiAction(() => API.uploadAttachment(note.id, f)); }
        toast('تم حفظ التعديلات');
        App.navigate('detail', note.id);
      } catch (err) { toast(err?.message || 'حدث خطأ', 'error'); App.haptic('error'); }
      finally { btn.disabled = false; btn.textContent = 'حفظ التعديلات'; }
    });
  } catch (e) {
    container.innerHTML = `<div class="empty-state">
      <div class="empty-title">خطأ في تحميل الملاحظة</div>
      <button class="btn btn-primary btn-sm" style="margin-top:12px" onclick="App.goBack()">العودة</button>
    </div>`;
  }
}

/* ===== DETAIL SCREEN ===== */
async function renderDetail(container, noteId) {
  container.innerHTML = skDetail();
  try {
    const res = await API.note(noteId);
    if (!res.success) throw new Error();
    const n = res.data;
    window._detailNote = n;
    const isOwner = Auth.user?.id === n.user_id;
    const isDraft = n.status === 'draft';
    const isPending = n.status === 'pending';
    const isRejected = n.status === 'rejected';
    const canEdit = isOwner && (isDraft || isRejected) && !n.processed_at;
    const canSend = isOwner && isDraft;
    const canResend = isOwner && isRejected;
    const canAcceptReject = Auth.isWriter() && isPending;
    const canDelete = isOwner && isDraft;

    let actionsHtml = '';
    const canShare = !isRejected && n.owner?.personal_number;
    if (canShare) {
      actionsHtml += `<button class="btn" style="flex:1;background:#25D366;color:#fff;font-weight:700;border-radius:12px;padding:12px;display:flex;align-items:center;justify-content:center;gap:8px" onclick="PwaShare.open(window._detailNote)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8m-4-6l-4-4m0 0L8 6m4-4v13"/></svg>
        مشاركة
      </button>`;
    }
    if (canAcceptReject) {
      actionsHtml = `<div class="detail-actions">
        <div class="detail-actions-row">
          <button class="btn btn-primary" style="flex:1" onclick="acceptNote(${n.id})">قبول الملاحظة</button>
          <button class="btn btn-danger" style="flex:1" onclick="showRejectModal(${n.id})">رفض الملاحظة</button>
        </div>
      </div>`;
    } else if (canEdit || canSend || canResend) {
      actionsHtml = `<div class="detail-actions">
        <div class="detail-actions-row">
          ${canEdit ? `<button class="btn btn-secondary" style="flex:1" onclick="App.navigate('edit',${n.id})">تعديل</button>` : ''}
          ${canSend ? `<button class="btn btn-primary" style="flex:1" onclick="sendNote(${n.id})">إرسال للمراجعة</button>` : ''}
          ${canResend ? `<button class="btn btn-primary" style="flex:1" onclick="resendNote(${n.id})">إعادة الإرسال</button>` : ''}
        </div>
        ${canDelete ? `<div class="detail-actions-row" style="margin-top:8px"><button class="btn btn-danger btn-full" onclick="deleteNote(${n.id})">حذف الملاحظة</button></div>` : ''}
      </div>`;
    }

    const attachUrls = n.attachments?.filter(a => !a.mime_type?.includes('video')).map(a => API.downloadUrl(a.id)) || [];

    container.innerHTML = `
      <div class="detail-header">
        <button class="detail-back" onclick="App.goBack()">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
        <div style="flex:1">
          <div class="detail-title">ملاحظة #${n.id}</div>
          <div style="margin-top:4px">${badge(n.status)}</div>
        </div>
      </div>
      <div class="detail-content">
        <div class="detail-grid">
          <div class="detail-stat"><div class="detail-stat-label">الكاميرا</div><div class="detail-stat-value">${n.camera_number}</div></div>
          <div class="detail-stat"><div class="detail-stat-label">الطابق</div><div class="detail-stat-value">${n.floor_number}</div></div>
          <div class="detail-stat">
            <div class="detail-stat-label">وقت الملاحظة</div>
            <div class="detail-stat-value">${formatTime(n.observed_at)}</div>
            <div class="detail-stat-sub">${n.observed_end_at ? 'حتى ' + formatTime(n.observed_end_at) : formatDate(n.observed_at)}</div>
          </div>
        </div>

        ${n.status === 'rejected' && n.rejection_reason ? `
          <div class="reject-banner">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="reject-banner-content">
              <div class="reject-banner-title">سبب الرفض — يرجى التصحيح</div>
              <div class="reject-banner-text">${escHtml(n.rejection_reason)}</div>
              ${n.processor ? `<div class="reject-banner-meta">بواسطة ${escHtml(n.processor.name)} — ${formatDate(n.processed_at)}</div>` : ''}
            </div>
          </div>` : ''}

        <div class="detail-desc">
          <div class="detail-desc-title">الوصف</div>
          <div class="detail-desc-text">${escHtml(n.description)}</div>
        </div>

        <div style="margin-bottom:12px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:13px;font-weight:800;color:var(--ink-700)">المرفقات</div>
            ${n.attachments?.length ? `<div style="font-size:12px;color:var(--ink-300);font-weight:600">${n.attachments.length} / 5</div>` : ''}
          </div>
          ${n.attachments?.length ? `
            <div class="attach-grid">
              ${n.attachments.map((a, i) => `
                <div class="attach-item" onclick="openAttachment(${i})">
                  ${a.mime_type?.includes('video')
                    ? `<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:32px;background:var(--surface-100)">🎬</div>`
                    : `<img src="${API.downloadUrl(a.id)}" alt="${escHtml(a.original_name)}" loading="lazy">`
                  }
                  <div class="attach-item-badge">${a.mime_type?.includes('video') ? 'فيديو' : 'صورة'}</div>
                </div>`).join('')}
            </div>` : `
            <div style="text-align:center;padding:20px;color:var(--ink-300);font-size:13px;font-weight:600;background:var(--surface-50);border-radius:var(--radius-sm);border:1px solid var(--border)">
              لا توجد مرفقات
            </div>`}
        </div>

        <div style="font-size:11px;color:var(--ink-300);font-weight:600;padding:8px 0">
          <div>بواسطة: ${escHtml(n.owner?.name || '—')} · ${formatDate(n.created_at)}</div>
          ${n.sent_at ? `<div>أُرسل: ${formatDate(n.sent_at)}</div>` : ''}
          ${n.processed_at ? `<div>تمت المراجعة: ${formatDate(n.processed_at)}</div>` : ''}
        </div>
      </div>
      ${actionsHtml}
      <div id="reject-modal-container"></div>`;

    window.openAttachment = (idx) => { App.haptic('light'); ImageViewer.open(attachUrls, idx); };

    window.acceptNote = async (id) => {
      if (!confirm('هل تريد قبول هذه الملاحظة؟')) return;
      App.haptic('medium');
      try { await App.apiAction(() => API.acceptNote(id)); toast('تم قبول الملاحظة'); renderDetail(container, id); }
      catch (e) { toast(e?.message || 'خطأ', 'error'); }
    };
    window.sendNote = async (id) => {
      if (!confirm('هل تريد إرسال هذه الملاحظة للمراجعة؟')) return;
      App.haptic('medium');
      try { await App.apiAction(() => API.sendNote(id)); toast('تم الإرسال للمراجعة'); renderDetail(container, id); }
      catch (e) { toast(e?.message || 'خطأ', 'error'); }
    };
    window.resendNote = async (id) => {
      if (!confirm('هل تريد إعادة إرسال هذه الملاحظة؟')) return;
      App.haptic('medium');
      try { await App.apiAction(() => API.resendNote(id)); toast('تم إعادة الإرسال'); renderDetail(container, id); }
      catch (e) { toast(e?.message || 'خطأ', 'error'); }
    };
    window.deleteNote = async (id) => {
      if (!confirm('هل تريد حذف هذه الملاحظة نهائياً؟')) return;
      App.haptic('heavy');
      try { await App.apiAction(() => API.deleteNote(id)); toast('تم الحذف'); App.navigate('notes'); }
      catch (e) { toast(e?.message || 'خطأ', 'error'); }
    };
    window.showRejectModal = (id) => {
      App.haptic('medium');
      document.getElementById('reject-modal-container').innerHTML = `
        <div class="modal-overlay" onclick="closeRejectModal(event)">
          <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-handle"></div>
            <div class="modal-title">رفض الملاحظة</div>
            <div class="input-group" style="margin-bottom:16px">
              <label>سبب الرفض <span class="required">*</span></label>
              <textarea class="input" id="reject-reason" rows="4" placeholder="اكتب سبب الرفض..." required></textarea>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn btn-secondary" onclick="closeRejectModal()" style="flex:1">إلغاء</button>
              <button class="btn btn-danger" onclick="submitReject(${id})" style="flex:1">رفض</button>
            </div>
          </div>
        </div>`;
    };
    window.closeRejectModal = (e) => { if (e && e.target !== e.currentTarget) return; document.getElementById('reject-modal-container').innerHTML = ''; };
    window.submitReject = async (id) => {
      const reason = document.getElementById('reject-reason')?.value?.trim();
      if (!reason) { toast('يرجى كتابة سبب الرفض', 'error'); return; }
      App.haptic('heavy');
      try {
        await App.apiAction(() => API.rejectNote(id, reason));
        toast('تم رفض الملاحظة');
        document.getElementById('reject-modal-container').innerHTML = '';
        renderDetail(container, id);
      } catch (e) { toast(e?.message || 'خطأ', 'error'); }
    };
  } catch (e) {
    container.innerHTML = `<div class="empty-state">
      <div class="empty-title">خطأ في تحميل الملاحظة</div>
      <button class="btn btn-primary btn-sm" style="margin-top:12px" onclick="App.goBack()">العودة</button>
    </div>`;
  }
}

/* ===== PROFILE SCREEN ===== */
function renderProfile(container) {
  const u = Auth.user;
  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:10px">
          <button class="header-btn" onclick="App.goBack()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
          <div class="header-title">الملف الشخصي</div>
        </div>
        <button class="header-btn" onclick="App.toggleTheme()">
          <svg class="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <svg class="moon-icon" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
      </div>
    </div>
    <div class="profile-header">
      <div class="profile-avatar">${(u?.name || 'م')[0]}</div>
      <div class="profile-name">${escHtml(u?.name || '—')}</div>
      <div class="profile-role">${u?.role === 'monitor' ? 'مراقب' : 'كاتب تقارير'}</div>
    </div>
    <div class="profile-list" style="padding-bottom:80px">
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
          <div class="profile-item-text">${escHtml(u?.name || '—')}</div>
        </div>
      </div>
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg></div>
          <div class="profile-item-text">${escHtml(u?.username || '—')}</div>
        </div>
      </div>
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div>
          <div class="profile-item-text">${u?.role === 'monitor' ? 'مراقب' : 'كاتب تقارير'}</div>
        </div>
      </div>
      <!-- PWA Install — visible state machine -->
      <div class="profile-item" style="flex-direction:column;align-items:stretch;gap:8px;cursor:default" onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="profile-item-icon" style="background:var(--sage-50);border-color:var(--sage-200)"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-8m0 8l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg></div>
            <div>
              <div class="profile-item-text" style="font-size:13px">تثبيت التطبيق</div>
              <div style="font-size:11px;color:var(--ink-400)" data-pwa-state class="pwa-state-badge">—</div>
            </div>
          </div>
          <button id="profile-pwa-btn" class="btn btn-sm" style="background:var(--sage-600);color:#fff;border:none;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:800" onclick="PwaInstall.prompt()">تثبيت</button>
        </div>
        <div style="font-size:11px;color:var(--ink-400);line-height:1.6">
          ثبّت التطبيق على شاشتك للوصول السريع دون متصفح. <a href="#" onclick="event.preventDefault();PwaInstall.showDiag()" style="color:var(--sage-600);font-weight:700">التشخيص</a>
        </div>
        <div style="font-size:10px;color:var(--ink-300);font-family:monospace;word-break:break-all" id="profile-pwa-origin"></div>
      </div>

      <div class="profile-item" onclick="doLogout()" style="margin-top:16px;border-color:var(--red-200)">
        <div class="profile-item-left">
          <div class="profile-item-icon" style="background:var(--red-50);border-color:var(--red-200)"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color:var(--red-500)"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></div>
          <div class="profile-item-text" style="color:var(--red-600)">تسجيل الخروج</div>
        </div>
      </div>
    </div>
    ${bottomNav('profile')}`;

  window.doLogout = async () => {
    if (!confirm('هل تريد تسجيل الخروج؟')) return;
    App.haptic('heavy');
    await Auth.logout();
    App.navigate('login');
  };

  // Update PWA profile row after render
  setTimeout(() => {
    try {
      const el = document.getElementById('profile-pwa-origin');
      if (el) el.textContent = location.origin + ' — ' + (window.isSecureContext ? 'SecureContext ✓' : 'Non-Secure ✗');
      const btn = document.getElementById('profile-pwa-btn');
      if (btn && window.PwaInstall) PwaInstall.updateProfileRow(btn);
      document.querySelectorAll('[data-pwa-state]').forEach(e=>{
        e.textContent = PwaInstall.getStatusLabel();
        e.className = 'pwa-state-badge ' + PwaInstall.getStatusClass();
      });
    } catch(e){}
  }, 100);

  App.initTheme();
}
