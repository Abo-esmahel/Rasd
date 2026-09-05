const API = {
  BASE: '/api',
  token: localStorage.getItem('rasd_token'),
  _retryCount: 2,
  _retryDelay: 800,

  setToken(t) {
    this.token = t;
    if (t) localStorage.setItem('rasd_token', t);
    else localStorage.removeItem('rasd_token');
  },
  clearToken() { this.token = null; localStorage.removeItem('rasd_token'); },

  async request(method, path, body = null, isForm = false, retries = this._retryCount) {
    const opts = { method, headers: {} };
    if (this.token) opts.headers['Authorization'] = 'Bearer ' + this.token;
    if (body && !isForm) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body && isForm) {
      opts.body = body;
    }

    for (let attempt = 0; attempt <= retries; attempt++) {
      try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        opts.signal = controller.signal;

        const r = await fetch(this.BASE + path, opts);
        clearTimeout(timeout);

        if (r.status === 204) return { success: true };
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw { status: r.status, ...data };
        return data;
      } catch (err) {
        if (attempt < retries && !err.status) {
          await new Promise(r => setTimeout(r, this._retryDelay * (attempt + 1)));
          continue;
        }
        throw err;
      }
    }
  },

  isOnline() { return navigator.onLine; },

  login(u, p) { return this.request('POST', '/login', { username: u, password: p }, false, 0); },
  me() { return this.request('GET', '/me', null, false, 0); },
  logout() { return this.request('POST', '/logout', null, false, 0).finally(() => this.clearToken()); },

  notes(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request('GET', '/notes' + (q ? '?' + q : ''));
  },
  myNotes(params = {}) {
    const q = new URLSearchParams(params).toString();
    return this.request('GET', '/my-notes' + (q ? '?' + q : ''));
  },
  note(id) { return this.request('GET', '/notes/' + id); },
  createNote(d) { return this.request('POST', '/notes', d); },
  updateNote(id, d) { return this.request('PUT', '/notes/' + id, d); },
  deleteNote(id) { return this.request('DELETE', '/notes/' + id); },
  sendNote(id) { return this.request('POST', '/notes/' + id + '/send'); },
  acceptNote(id) { return this.request('POST', '/notes/' + id + '/accept'); },
  rejectNote(id, reason) { return this.request('POST', '/notes/' + id + '/reject', { rejection_reason: reason }); },
  resendNote(id) { return this.request('POST', '/notes/' + id + '/resend'); },

  async uploadAttachment(noteId, file) {
    const fd = new FormData();
    fd.append('file', file);
    return this.request('POST', '/notes/' + noteId + '/attachments', fd, true, 0);
  },
  deleteAttachment(noteId, attId) { return this.request('DELETE', '/notes/' + noteId + '/attachments/' + attId); },
  downloadUrl(attId) { return this.BASE + '/attachments/' + attId + '?token=' + this.token; },
};
