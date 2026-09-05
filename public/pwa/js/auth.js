const Auth = {
  user: null,

  init() {
    try { this.user = JSON.parse(localStorage.getItem('rasd_user')); } catch (e) { this.user = null; }
  },

  isLoggedIn() { return !!API.token && !!this.user; },
  isMonitor() { return this.user?.role === 'monitor'; },
  isWriter() { return this.user?.role === 'report_writer'; },

  setUser(u) { this.user = u; localStorage.setItem('rasd_user', JSON.stringify(u)); },
  clear() { this.user = null; localStorage.removeItem('rasd_user'); API.clearToken(); },

  async login(username, password) {
    const res = await API.login(username, password);
    if (res.success) {
      API.setToken(res.data.token);
      this.setUser(res.data.user);
    }
    return res;
  },

  async check() {
    if (!API.token) return false;
    try {
      const res = await API.me();
      if (res.success) { this.setUser(res.data); return true; }
    } catch (e) { this.clear(); }
    return false;
  },

  async logout() {
    try { await API.logout(); } catch (e) {}
    this.clear();
  }
};

Auth.init();
