
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


function bottomNav(active) {
  const isGM = Auth.isGeneralManager && Auth.isGeneralManager();
  const items = isGM ? [
    { key: 'reports', label: 'التقارير', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,72V184a8,8,0,0,1-8,8H176V104L136,64H80V40a8,8,0,0,1,8-8h80Z" opacity="0.2"/><path d="M213.66,66.34l-40-40A8,8,0,0,0,168,24H88A16,16,0,0,0,72,40V56H56A16,16,0,0,0,40,72V216a16,16,0,0,0,16,16H168a16,16,0,0,0,16-16V200h16a16,16,0,0,0,16-16V72A8,8,0,0,0,213.66,66.34ZM168,216H56V72h76.69L168,107.31v84.53c0,.06,0,.11,0,.16s0,.1,0,.16V216Zm32-32H184V104a8,8,0,0,0-2.34-5.66l-40-40A8,8,0,0,0,136,56H88V40h76.69L200,75.31Zm-56-32a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h48A8,8,0,0,1,144,152Zm0,32a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h48A8,8,0,0,1,144,184Z"/></svg>' },
    { key: 'gallery', label: 'المعرض', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,56V178.06l-39.72-39.72a8,8,0,0,0-11.31,0L147.31,164,97.66,114.34a8,8,0,0,0-11.32,0L32,168.69V56a8,8,0,0,1,8-8H216A8,8,0,0,1,224,56Z" opacity="0.2"/><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,16V158.75l-26.07-26.06a16,16,0,0,0-22.63,0l-20,20-44-44a16,16,0,0,0-22.62,0L40,149.37V56ZM40,172l52-52,80,80H40Zm176,28H194.63l-36-36,20-20L216,181.38V200ZM144,100a12,12,0,1,1,12,12A12,12,0,0,1,144,100Z"/></svg>' },
    { key: 'profile', label: 'حسابي', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>' },
  ] : [
    { key: 'notes', label: 'الملاحظات', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z"/></svg>' },
    { key: 'create', label: 'جديدة', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"/></svg>' },
    { key: 'profile', label: 'حسابي', icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>' },
  ];
  return `<nav class="bottom-nav">${items.map(i => `
    <div class="nav-item ${active === i.key ? 'active' : ''}" onclick="App.navigate('${i.key}')">
      ${i.icon}<span>${i.label}</span>
    </div>`).join('')}</nav>`;
}


function renderLogin(container) {
  container.innerHTML = `
    <div class="login-page">
      <div class="login-card">
        <div class="login-logo">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M200,72V184a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V72a8,8,0,0,1,8-8H192A8,8,0,0,1,200,72Z" opacity="0.2"/><path d="M251.77,73a8,8,0,0,0-8.21.39L208,97.05V72a16,16,0,0,0-16-16H32A16,16,0,0,0,16,72V184a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V159l35.56,23.71A8,8,0,0,0,248,184a8,8,0,0,0,8-8V80A8,8,0,0,0,251.77,73ZM192,184H32V72H192V184Zm48-22.95-32-21.33V116.28L240,95Z"/></svg>
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
        <div class="empty-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M215.46,216H40.54C27.92,216,20,202.79,26.13,192.09L113.59,40.22c6.3-11,22.52-11,28.82,0l87.46,151.87C236,202.79,228.08,216,215.46,216Z" opacity="0.2"/><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM222.93,203.8a8.5,8.5,0,0,1-7.48,4.2H40.55a8.5,8.5,0,0,1-7.48-4.2,7.59,7.59,0,0,1,0-7.72L120.52,44.21a8.75,8.75,0,0,1,15,0l87.45,151.87A7.59,7.59,0,0,1,222.93,203.8ZM120,144V104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,180Z"/></svg></div>
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
        <div class="empty-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M200,72V184a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V72a8,8,0,0,1,8-8H192A8,8,0,0,1,200,72Z" opacity="0.2"/><path d="M251.77,73a8,8,0,0,0-8.21.39L208,97.05V72a16,16,0,0,0-16-16H32A16,16,0,0,0,16,72V184a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V159l35.56,23.71A8,8,0,0,0,248,184a8,8,0,0,0,8-8V80A8,8,0,0,0,251.77,73ZM192,184H32V72H192V184Zm48-22.95-32-21.33V116.28L240,95Z"/></svg></div>
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
          <svg width="12" height="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>
          ${escHtml(n.owner?.name || '—')}
        </div>
        ${n.attachments?.length ? `<div class="note-attach">
          <svg width="12" height="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M180.75,104.75,204,128l-82.06,81.94a48,48,0,0,1-67.88-67.88L153.37,41.37a32,32,0,0,1,45.26,45.26Z" opacity="0.2"/><path d="M209.66,122.34a8,8,0,0,1,0,11.32l-82.05,82a56,56,0,0,1-79.2-79.21L147.67,35.73a40,40,0,1,1,56.61,56.55L105,193A24,24,0,1,1,71,159L154.3,74.38A8,8,0,1,1,165.7,85.6L82.39,170.31a8,8,0,1,0,11.27,11.36L192.93,81A24,24,0,1,0,159,47L59.76,147.68a40,40,0,1,0,56.53,56.62l82.06-82A8,8,0,0,1,209.66,122.34Z"/></svg>
          ${n.attachments.length}
        </div>` : ''}
        ${canShare ? `<button onclick="event.stopPropagation();PwaShare.open(notes.find(x=>x.id===${n.id}))" class="note-share" title="مشاركة">
          <svg width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,200a32,32,0,1,1-32-32A32,32,0,0,1,208,200ZM176,88a32,32,0,1,0-32-32A32,32,0,0,0,176,88Z" opacity="0.2"/><path d="M176,160a39.89,39.89,0,0,0-28.62,12.09l-46.1-29.63a39.8,39.8,0,0,0,0-28.92l46.1-29.63a40,40,0,1,0-8.66-13.45l-46.1,29.63a40,40,0,1,0,0,55.82l46.1,29.63A40,40,0,1,0,176,160Zm0-128a24,24,0,1,1-24,24A24,24,0,0,1,176,32ZM64,152a24,24,0,1,1,24-24A24,24,0,0,1,64,152Zm112,72a24,24,0,1,1,24-24A24,24,0,0,1,176,224Z"/></svg>
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
  if (Auth.isGeneralManager && Auth.isGeneralManager()) {
    container.innerHTML = `
      <div class="app-header"><div class="header-title">التقارير والمعرض</div><div class="header-subtitle">المدير العام — وصول للتقارير والمعرض فقط</div></div>
      <div style="padding:24px;text-align:center">
        <div style="background:var(--surface-50);border:1px solid var(--border);border-radius:16px;padding:24px">
          <div style="font-size:18px;font-weight:800;margin-bottom:8px">مرحباً ${escHtml(user?.name||'')}</div>
          <div style="font-size:13px;color:var(--ink-500);line-height:1.7">حساب المدير العام مخصص لعرض <strong>التقارير</strong> و <strong>المعرض</strong> فقط.<br>الملاحظات غير متاحة لهذا الدور.</div>
          <div style="display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap">
            <a href="/reports" class="btn btn-primary" style="text-decoration:none">فتح التقارير</a>
            <a href="/gallery" class="btn btn-secondary" style="text-decoration:none">فتح المعرض</a>
          </div>
        </div>
      </div>
      ${bottomNav('reports')}`;
    return;
  }
  notesFilter = {};
  notesData = null;
  notesSearch = '';
  notesMineMode = false;
  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div>
          <div class="header-title">الملاحظات</div>
          <div class="header-subtitle">${escHtml(user?.name || '')} · ${user?.role === 'monitor' ? 'مراقب' : (user?.role === 'general_manager' ? 'المدير العام' : 'كاتب تقارير')}</div>
        </div>
        <div class="header-actions">
          <button class="header-btn" onclick="App.toggleTheme()" title="الوضع الداكن">
            <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M184,128a56,56,0,1,1-56-56A56,56,0,0,1,184,128Z" opacity="0.2"/><path d="M120,40V16a8,8,0,0,1,16,0V40a8,8,0,0,1-16,0Zm72,88a64,64,0,1,1-64-64A64.07,64.07,0,0,1,192,128Zm-16,0a48,48,0,1,0-48,48A48.05,48.05,0,0,0,176,128ZM58.34,69.66A8,8,0,0,0,69.66,58.34l-16-16A8,8,0,0,0,42.34,53.66Zm0,116.68-16,16a8,8,0,0,0,11.32,11.32l16-16a8,8,0,0,0-11.32-11.32ZM192,72a8,8,0,0,0,5.66-2.34l16-16a8,8,0,0,0-11.32-11.32l-16,16A8,8,0,0,0,192,72Zm5.66,114.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32-11.32ZM48,128a8,8,0,0,0-8-8H16a8,8,0,0,0,0,16H40A8,8,0,0,0,48,128Zm80,80a8,8,0,0,0-8,8v24a8,8,0,0,0,16,0V216A8,8,0,0,0,128,208Zm112-88H216a8,8,0,0,0,0,16h24a8,8,0,0,0,0-16Z"/></svg>
            <svg class="moon-icon" style="display:none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M210.69,158.18A88,88,0,1,1,97.82,45.31,96.08,96.08,0,0,0,192,160,96.78,96.78,0,0,0,210.69,158.18Z" opacity="0.2"/><path d="M240,96a8,8,0,0,1-8,8H216v16a8,8,0,0,1-16,0V104H184a8,8,0,0,1,0-16h16V72a8,8,0,0,1,16,0V88h16A8,8,0,0,1,240,96ZM144,56h8v8a8,8,0,0,0,16,0V56h8a8,8,0,0,0,0-16h-8V32a8,8,0,0,0-16,0v8h-8a8,8,0,0,0,0,16Zm72.77,97a8,8,0,0,1,1.43,8A96,96,0,1,1,95.07,37.8a8,8,0,0,1,10.6,9.06A88.07,88.07,0,0,0,209.14,150.33,8,8,0,0,1,216.77,153Zm-19.39,14.88c-1.79.09-3.59.14-5.38.14A104.11,104.11,0,0,1,88,64c0-1.79,0-3.59.14-5.38A80,80,0,1,0,197.38,167.86Z"/></svg>
          </button>
          <button class="header-btn" onclick="App.navigate('profile')" title="الملف الشخصي">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>
          </button>
        </div>
      </div>
    </div>
    <div class="search-bar">
      <input type="text" placeholder="بحث في الملاحظات..." id="notes-search" value="">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,112a80,80,0,1,1-80-80A80,80,0,0,1,192,112Z" opacity="0.2"/><path d="M229.66,218.34,179.6,168.28a88.21,88.21,0,1,0-11.32,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"/></svg>
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

  
  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && notesData && notesData.current_page < notesData.last_page && !notesLoading) {
      loadNotes(notesData.current_page + 1, true);
    }
  }, { threshold: 0.1 });
  const sentinel = document.getElementById('load-more');
  if (sentinel) observer.observe(sentinel);

  loadNotes(1);
  
  API.myNotes({ per_page: 1 }).then(res => {
    if (res.success) {
      const el = document.getElementById('cnt-mine');
      if (el) el.textContent = res.data.total;
    }
  }).catch(() => {});
}


function renderCreate(container) {
  if (Auth.isGeneralManager && Auth.isGeneralManager()) {
    container.innerHTML = `<div class="app-header"><div class="header-title">غير مصرح</div></div><div style="padding:24px;text-align:center;color:var(--ink-500)">لا يمكن للمدير العام إنشاء ملاحظات.</div>${bottomNav('reports')}`;
    return;
  }
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  const today = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
  const curTime = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:10px">
          <button class="header-btn" onclick="App.goBack()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M176,128,96,208V48Z" opacity="0.2"/><path d="M181.66,122.34l-80-80A8,8,0,0,0,88,48V208a8,8,0,0,0,13.66,5.66l80-80A8,8,0,0,0,181.66,122.34ZM104,188.69V67.31L164.69,128Z"/></svg>
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
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z"/></svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z"/></svg>
                <span id="cr-preview">— اختر الوقت —</span>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M221.66,90.34,192,120,136,64l29.66-29.66a8,8,0,0,1,11.31,0L221.66,79A8,8,0,0,1,221.66,90.34Z" opacity="0.2"/><path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z"/></svg>
              الوصف <span class="required">*</span>
            </div>
            <textarea class="input" name="description" rows="4" required maxlength="5000" placeholder="صف ما تم رصده بدقة..." id="cr-desc"></textarea>
            <div style="text-align:left;font-size:11px;color:var(--ink-300);font-weight:600;margin-top:4px"><span id="cr-desc-cnt">0</span> / 5000</div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M180.75,104.75,204,128l-82.06,81.94a48,48,0,0,1-67.88-67.88L153.37,41.37a32,32,0,0,1,45.26,45.26Z" opacity="0.2"/><path d="M209.66,122.34a8,8,0,0,1,0,11.32l-82.05,82a56,56,0,0,1-79.2-79.21L147.67,35.73a40,40,0,1,1,56.61,56.55L105,193A24,24,0,1,1,71,159L154.3,74.38A8,8,0,1,1,165.7,85.6L82.39,170.31a8,8,0,1,0,11.27,11.36L192.93,81A24,24,0,1,0,159,47L59.76,147.68a40,40,0,1,0,56.53,56.62l82.06-82A8,8,0,0,1,209.66,122.34Z"/></svg>
              المرفقات <span style="color:var(--ink-300);font-weight:500;font-size:12px">— اختياري</span>
            </div>
            <div class="file-zone" id="cr-file-zone">
              <div class="file-zone-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M240,128a80,80,0,0,1-80,80H72A56,56,0,1,1,85.92,97.74l0,.1A80,80,0,0,1,240,128Z" opacity="0.2"/><path d="M178.34,165.66,160,147.31V208a8,8,0,0,1-16,0V147.31l-18.34,18.35a8,8,0,0,1-11.32-11.32l32-32a8,8,0,0,1,11.32,0l32,32a8,8,0,0,1-11.32,11.32ZM160,40A88.08,88.08,0,0,0,81.29,88.68,64,64,0,1,0,72,216h40a8,8,0,0,0,0-16H72a48,48,0,0,1,0-96c1.1,0,2.2,0,3.29.12A88,88,0,0,0,72,128a8,8,0,0,0,16,0,72,72,0,1,1,100.8,66,8,8,0,0,0,3.2,15.34,7.9,7.9,0,0,0,3.2-.68A88,88,0,0,0,160,40Z"/></svg></div>
              <div class="file-zone-text">اسحب وأفلت هنا أو</div>
              <div style="display:flex;gap:8px;justify-content:center;margin-top:8px;flex-wrap:wrap">
                <label class="file-zone-btn" for="cr-files">اختيار ملفات</label>
                <button type="button" class="file-zone-btn" id="cr-camera-btn" style="background:var(--ink-600)">
                  <svg width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,64H176L160,40H96L80,64H48A16,16,0,0,0,32,80V192a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V80A16,16,0,0,0,208,64ZM128,168a36,36,0,1,1,36-36A36,36,0,0,1,128,168Z" opacity="0.2"/><path d="M208,56H180.28L166.65,35.56A8,8,0,0,0,160,32H96a8,8,0,0,0-6.65,3.56L75.71,56H48A24,24,0,0,0,24,80V192a24,24,0,0,0,24,24H208a24,24,0,0,0,24-24V80A24,24,0,0,0,208,56Zm8,136a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V80a8,8,0,0,1,8-8H80a8,8,0,0,0,6.66-3.56L100.28,48h55.43l13.63,20.44A8,8,0,0,0,176,72h32a8,8,0,0,1,8,8ZM128,88a44,44,0,1,0,44,44A44.05,44.05,0,0,0,128,88Zm0,72a28,28,0,1,1,28-28A28,28,0,0,1,128,160Z"/></svg>
                  الكاميرا
                </button>
              </div>
              <input type="file" id="cr-files" multiple accept="image
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
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M176,128,96,208V48Z" opacity="0.2"/><path d="M181.66,122.34l-80-80A8,8,0,0,0,88,48V208a8,8,0,0,0,13.66,5.66l80-80A8,8,0,0,0,181.66,122.34ZM104,188.69V67.31L164.69,128Z"/></svg>
            </button>
            <div class="header-title">تعديل الملاحظة</div>
          </div>
          ${badge(note.status)}
        </div>
      </div>
      <div class="form-page">
        ${note.status === 'rejected' && note.rejection_reason ? `
          <div class="reject-banner">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M144,176a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176Zm88-48A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128ZM124,96a12,12,0,1,0-12-12A12,12,0,0,0,124,96Z"/></svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z"/></svg>
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
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z"/></svg>
                  <span id="ed-preview">—</span>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M221.66,90.34,192,120,136,64l29.66-29.66a8,8,0,0,1,11.31,0L221.66,79A8,8,0,0,1,221.66,90.34Z" opacity="0.2"/><path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z"/></svg>
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
                      <div class="file-item-remove" onclick="deleteAtt(${note.id},${a.id})"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></div>
                    </div>`).join('')}
                </div>
              </div>` : ''}

            <div class="form-section">
              <div class="form-section-title">إضافة مرفقات</div>
              <div class="file-zone" id="ed-file-zone">
                <div class="file-zone-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"/></svg></div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:4px;flex-wrap:wrap">
                  <label class="file-zone-btn" for="ed-files">اختيار ملفات</label>
                  <button type="button" class="file-zone-btn" id="ed-camera-btn" style="background:var(--ink-600)">الكاميرا</button>
                </div>
              <input type="file" id="ed-files" multiple accept="image
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
        <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,200a32,32,0,1,1-32-32A32,32,0,0,1,208,200ZM176,88a32,32,0,1,0-32-32A32,32,0,0,0,176,88Z" opacity="0.2"/><path d="M176,160a39.89,39.89,0,0,0-28.62,12.09l-46.1-29.63a39.8,39.8,0,0,0,0-28.92l46.1-29.63a40,40,0,1,0-8.66-13.45l-46.1,29.63a40,40,0,1,0,0,55.82l46.1,29.63A40,40,0,1,0,176,160Zm0-128a24,24,0,1,1-24,24A24,24,0,0,1,176,32ZM64,152a24,24,0,1,1,24-24A24,24,0,0,1,64,152Zm112,72a24,24,0,1,1,24-24A24,24,0,0,1,176,224Z"/></svg>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M176,128,96,208V48Z" opacity="0.2"/><path d="M181.66,122.34l-80-80A8,8,0,0,0,88,48V208a8,8,0,0,0,13.66,5.66l80-80A8,8,0,0,0,181.66,122.34ZM104,188.69V67.31L164.69,128Z"/></svg>
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2"/><path d="M144,176a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176Zm88-48A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128ZM124,96a12,12,0,1,0-12-12A12,12,0,0,0,124,96Z"/></svg>
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


function renderProfile(container) {
  const u = Auth.user;
  container.innerHTML = `
    <div class="app-header">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:10px">
          <button class="header-btn" onclick="App.goBack()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M176,128,96,208V48Z" opacity="0.2"/><path d="M181.66,122.34l-80-80A8,8,0,0,0,88,48V208a8,8,0,0,0,13.66,5.66l80-80A8,8,0,0,0,181.66,122.34ZM104,188.69V67.31L164.69,128Z"/></svg>
          </button>
          <div class="header-title">الملف الشخصي</div>
        </div>
        <button class="header-btn" onclick="App.toggleTheme()">
          <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M184,128a56,56,0,1,1-56-56A56,56,0,0,1,184,128Z" opacity="0.2"/><path d="M120,40V16a8,8,0,0,1,16,0V40a8,8,0,0,1-16,0Zm72,88a64,64,0,1,1-64-64A64.07,64.07,0,0,1,192,128Zm-16,0a48,48,0,1,0-48,48A48.05,48.05,0,0,0,176,128ZM58.34,69.66A8,8,0,0,0,69.66,58.34l-16-16A8,8,0,0,0,42.34,53.66Zm0,116.68-16,16a8,8,0,0,0,11.32,11.32l16-16a8,8,0,0,0-11.32-11.32ZM192,72a8,8,0,0,0,5.66-2.34l16-16a8,8,0,0,0-11.32-11.32l-16,16A8,8,0,0,0,192,72Zm5.66,114.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32-11.32ZM48,128a8,8,0,0,0-8-8H16a8,8,0,0,0,0,16H40A8,8,0,0,0,48,128Zm80,80a8,8,0,0,0-8,8v24a8,8,0,0,0,16,0V216A8,8,0,0,0,128,208Zm112-88H216a8,8,0,0,0,0,16h24a8,8,0,0,0,0-16Z"/></svg>
          <svg class="moon-icon" style="display:none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M210.69,158.18A88,88,0,1,1,97.82,45.31,96.08,96.08,0,0,0,192,160,96.78,96.78,0,0,0,210.69,158.18Z" opacity="0.2"/><path d="M240,96a8,8,0,0,1-8,8H216v16a8,8,0,0,1-16,0V104H184a8,8,0,0,1,0-16h16V72a8,8,0,0,1,16,0V88h16A8,8,0,0,1,240,96ZM144,56h8v8a8,8,0,0,0,16,0V56h8a8,8,0,0,0,0-16h-8V32a8,8,0,0,0-16,0v8h-8a8,8,0,0,0,0,16Zm72.77,97a8,8,0,0,1,1.43,8A96,96,0,1,1,95.07,37.8a8,8,0,0,1,10.6,9.06A88.07,88.07,0,0,0,209.14,150.33,8,8,0,0,1,216.77,153Zm-19.39,14.88c-1.79.09-3.59.14-5.38.14A104.11,104.11,0,0,1,88,64c0-1.79,0-3.59.14-5.38A80,80,0,1,0,197.38,167.86Z"/></svg>
        </button>
      </div>
    </div>
    <div class="profile-header">
      <div class="profile-avatar">${(u?.name || 'م')[0]}</div>
      <div class="profile-name">${escHtml(u?.name || '—')}</div>
      <div class="profile-role">${u?.role === 'monitor' ? 'مراقب' : (u?.role === 'general_manager' ? 'المدير العام' : 'كاتب تقارير')}</div>
    </div>
    <div class="profile-list" style="padding-bottom:80px">
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg></div>
          <div class="profile-item-text">${escHtml(u?.name || '—')}</div>
        </div>
      </div>
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M168,64v64a40,40,0,0,1-40,40h0a40,40,0,0,1-40-40V64a40,40,0,0,1,40-40h0A40,40,0,0,1,168,64Z" opacity="0.2"/><path d="M128,176a48.05,48.05,0,0,0,48-48V64a48,48,0,0,0-96,0v64A48.05,48.05,0,0,0,128,176ZM96,64a32,32,0,0,1,64,0v64a32,32,0,0,1-64,0Zm40,143.6V240a8,8,0,0,1-16,0V207.6A80.11,80.11,0,0,1,48,128a8,8,0,0,1,16,0,64,64,0,0,0,128,0,8,8,0,0,1,16,0A80.11,80.11,0,0,1,136,207.6Z"/></svg></div>
          <div class="profile-item-text">${escHtml(u?.username || '—')}</div>
        </div>
      </div>
      <div class="profile-item">
        <div class="profile-item-left">
          <div class="profile-item-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M232,128c0,12.51-17.82,21.95-22.68,33.69-4.68,11.32,1.42,30.65-7.78,39.85s-28.53,3.1-39.85,7.78C150,214.18,140.5,232,128,232s-22-17.82-33.69-22.68c-11.32-4.68-30.65,1.42-39.85-7.78s-3.1-28.53-7.78-39.85C41.82,150,24,140.5,24,128s17.82-22,22.68-33.69C51.36,83,45.26,63.66,54.46,54.46S83,51.36,94.31,46.68C106.05,41.82,115.5,24,128,24S150,41.82,161.69,46.68c11.32,4.68,30.65-1.42,39.85,7.78s3.1,28.53,7.78,39.85C214.18,106.05,232,115.5,232,128Z" opacity="0.2"/><path d="M225.86,102.82c-3.77-3.94-7.67-8-9.14-11.57-1.36-3.27-1.44-8.69-1.52-13.94-.15-9.76-.31-20.82-8-28.51s-18.75-7.85-28.51-8c-5.25-.08-10.67-.16-13.94-1.52-3.56-1.47-7.63-5.37-11.57-9.14C146.28,23.51,138.44,16,128,16s-18.27,7.51-25.18,14.14c-3.94,3.77-8,7.67-11.57,9.14C88,40.64,82.56,40.72,77.31,40.8c-9.76.15-20.82.31-28.51,8S41,67.55,40.8,77.31c-.08,5.25-.16,10.67-1.52,13.94-1.47,3.56-5.37,7.63-9.14,11.57C23.51,109.72,16,117.56,16,128s7.51,18.27,14.14,25.18c3.77,3.94,7.67,8,9.14,11.57,1.36,3.27,1.44,8.69,1.52,13.94.15,9.76.31,20.82,8,28.51s18.75,7.85,28.51,8c5.25.08,10.67.16,13.94,1.52,3.56,1.47,7.63,5.37,11.57,9.14C109.72,232.49,117.56,240,128,240s18.27-7.51,25.18-14.14c3.94-3.77,8-7.67,11.57-9.14,3.27-1.36,8.69-1.44,13.94-1.52,9.76-.15,20.82-.31,28.51-8s7.85-18.75,8-28.51c.08-5.25.16-10.67,1.52-13.94,1.47-3.56,5.37-7.63,9.14-11.57C232.49,146.28,240,138.44,240,128S232.49,109.73,225.86,102.82Zm-11.55,39.29c-4.79,5-9.75,10.17-12.38,16.52-2.52,6.1-2.63,13.07-2.73,19.82-.1,7-.21,14.33-3.32,17.43s-10.39,3.22-17.43,3.32c-6.75.1-13.72.21-19.82,2.73-6.35,2.63-11.52,7.59-16.52,12.38S132,224,128,224s-9.15-4.92-14.11-9.69-10.17-9.75-16.52-12.38c-6.1-2.52-13.07-2.63-19.82-2.73-7-.1-14.33-.21-17.43-3.32s-3.22-10.39-3.32-17.43c-.1-6.75-.21-13.72-2.73-19.82-2.63-6.35-7.59-11.52-12.38-16.52S32,132,32,128s4.92-9.15,9.69-14.11,9.75-10.17,12.38-16.52c2.52-6.1,2.63-13.07,2.73-19.82.1-7,.21-14.33,3.32-17.43S70.51,56.9,77.55,56.8c6.75-.1,13.72-.21,19.82-2.73,6.35-2.63,11.52-7.59,16.52-12.38S124,32,128,32s9.15,4.92,14.11,9.69,10.17,9.75,16.52,12.38c6.1,2.52,13.07,2.63,19.82,2.73,7,.1,14.33.21,17.43,3.32s3.22,10.39,3.32,17.43c.1,6.75.21,13.72,2.73,19.82,2.63,6.35,7.59,11.52,12.38,16.52S224,124,224,128,219.08,137.15,214.31,142.11ZM173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34Z"/></svg></div>
          <div class="profile-item-text">${u?.role === 'monitor' ? 'مراقب' : (u?.role === 'general_manager' ? 'المدير العام' : 'كاتب تقارير')}</div>
        </div>
      </div>
      <!-- PWA Install — visible state machine -->
      <div class="profile-item" style="flex-direction:column;align-items:stretch;gap:8px;cursor:default" onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="profile-item-icon" style="background:var(--sage-50);border-color:var(--sage-200)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg></div>
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
          <div class="profile-item-icon" style="background:var(--red-50);border-color:var(--red-200)"><svg style="color:var(--red-500)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M224,56V200a16,16,0,0,1-16,16H48V40H208A16,16,0,0,1,224,56Z" opacity="0.2"/><path d="M120,216a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40a8,8,0,0,1,8-8h64a8,8,0,0,1,0,16H56V208h56A8,8,0,0,1,120,216Zm109.66-93.66-40-40a8,8,0,0,0-11.32,11.32L204.69,120H112a8,8,0,0,0,0,16h92.69l-26.35,26.34a8,8,0,0,0,11.32,11.32l40-40A8,8,0,0,0,229.66,122.34Z"/></svg></div>
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
