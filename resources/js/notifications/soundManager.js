

export class NotificationSoundManager {
  constructor(options = {}) {
    this.enabled = options.enabled ?? true;
    this.volume = options.volume ?? 1.0; 
    this.blocked = false;
    this.unlocked = false;
    this.audioCtx = null;
    this.debug = options.debug ?? false;
    this.theme = options.theme ?? 'default';

    
    this.fallbackAudio = null;

    
    this._unlockHandler = this.unlock.bind(this);

    this.log('[SOUND] initialized', { enabled: this.enabled, volume: this.volume });
  }

  log(...args) {
    if (this.debug) console.log(...args);
  }

  setDebug(enabled) {
    this.debug = enabled;
  }

  setEnabled(enabled) {
    this.enabled = Boolean(enabled);
    this.persist();
    this.log('[SOUND] setEnabled', this.enabled);
    
    window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
  }

  setVolume(v) {
    const vol = Math.max(0, Math.min(100, Number(v))) / 100;
    this.volume = vol;
    this.persist();
    this.log('[SOUND] setVolume', this.volume);
    window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
  }

  getState() {
    return {
      enabled: this.enabled,
      blocked: this.blocked,
      unlocked: this.unlocked,
      volume: Math.round(this.volume * 100),
      audioContextState: this.audioCtx?.state || 'none',
    };
  }

  persist() {
    try {
      localStorage.setItem('notif_sound_enabled', this.enabled ? '1' : '0');
      localStorage.setItem('notif_sound_volume', String(Math.round(this.volume * 100)));
      localStorage.setItem('notif_sound_theme', this.theme);
      
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      if (token && window.fetch) {
        fetch('/notifications/preferences', {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            sound_enabled: this.enabled,
            volume: Math.round(this.volume * 100),
            sound_theme: this.theme,
          }),
        }).catch(() => {});
      }
    } catch {}
  }

  loadPersisted() {
    try {
      const e = localStorage.getItem('notif_sound_enabled');
      if (e !== null) this.enabled = e === '1';
      const v = localStorage.getItem('notif_sound_volume');
      if (v !== null) this.volume = Math.max(0, Math.min(100, parseInt(v) || 70)) / 100;
      const t = localStorage.getItem('notif_sound_theme');
      if (t) this.theme = t;
    } catch {}
  }

  async loadFromServer() {
    try {
      const res = await fetch('/notifications/preferences', { headers: { Accept: 'application/json' } });
      if (res.ok) {
        const data = await res.json();
        if (typeof data.sound_enabled === 'boolean') this.enabled = data.sound_enabled;
        if (typeof data.volume === 'number') this.volume = Math.max(0, Math.min(100, data.volume)) / 100;
        if (data.sound_theme) this.theme = data.sound_theme;
        this.log('[SOUND] loaded from server', data);
      }
    } catch {}
  }

  getAudioCtx() {
    if (this.audioCtx) return this.audioCtx;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      this.audioCtx = new Ctx();
      this.log('[SOUND] AudioContext created', this.audioCtx.state);
      return this.audioCtx;
    } catch (e) {
      this.log('[SOUND] AudioContext creation failed', e);
      return null;
    }
  }

  async unlock() {
    try {
      
      const ctx = this.getAudioCtx();
      if (ctx) {
        if (ctx.state === 'suspended') {
          await ctx.resume();
          this.log('[SOUND] AudioContext resumed', ctx.state);
        }
        
        if (ctx.state === 'running') {
          try {
            const buf = ctx.createBuffer(1, 1, 22050);
            const src = ctx.createBufferSource();
            src.buffer = buf;
            src.connect(ctx.destination);
            src.start(0);
          } catch {}
        }
      }
      if (ctx && ctx.state === 'running') {
        this.unlocked = true;
        this.blocked = false;
        window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
        ['click', 'keydown', 'touchstart', 'touchend', 'pointerdown', 'pointerup'].forEach(ev =>
          document.removeEventListener(ev, this._unlockHandler)
        );
        return true;
      }
      
      this.unlocked = true;
      this.blocked = false;
      window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
      return true;
    } catch (e) {
      this.log('[SOUND] unlock failed', e);
      return false;
    }
  }

  bindUnlockOnInteraction() {
    
    ['click', 'keydown', 'touchstart', 'touchend', 'pointerdown', 'pointerup'].forEach(ev => {
      document.addEventListener(ev, this._unlockHandler, { once: false, passive: false });
    });
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) this.unlock().catch(()=>{});
    });
    window.addEventListener('notification:toast', () => {
      if (this.blocked) this.unlock().catch(()=>{});
    });
    
    try{
      if(sessionStorage.getItem('notif_unlocked')==='1'){
        setTimeout(()=>this.unlock().catch(()=>{}), 400);
      }
      
      window.addEventListener('notification:sound-state', (e)=>{
        if(e.detail?.unlocked) try{ sessionStorage.setItem('notif_unlocked','1'); }catch{}
      });
    }catch{}
  }

  
  async play(type = 'normal') {
    if (!this.enabled) {
      this.log('[SOUND] play skipped (disabled)', type);
      return false;
    }
    if (this.volume <= 0.01) {
      this.log('[SOUND] play skipped (volume 0)', type);
      return false;
    }

    
    if (!this.unlocked) {
      await this.unlock();
    }

    
    try {
      if ('vibrate' in navigator) {
        const patterns = {
          normal: [200, 100, 200],
          success: [150, 80, 150],
          error: [250, 100, 250, 100, 250],
          critical: [300, 100, 300, 100, 500],
        };
        const pat = patterns[type] || patterns.normal;
        navigator.vibrate(pat);
      }
    } catch {}

    
    try {
      const ctx = this.getAudioCtx();
      if (!ctx) throw new Error('no AudioContext');

      if (ctx.state === 'suspended') {
        await ctx.resume();
      }
      if (ctx.state !== 'running') {
        throw new DOMException('AudioContext not running', 'NotAllowedError');
      }

      const now = ctx.currentTime;
      const gainMaster = ctx.createGain();
      gainMaster.gain.value = this.volume;
      gainMaster.connect(ctx.destination);

      
      const sequences = {
        normal: [{ freq: 880, at: 0, dur: 0.45 }, { freq: 660, at: 0.38, dur: 0.5 }, { freq: 880, at: 0.78, dur: 0.6 }],
        success: [{ freq: 660, at: 0, dur: 0.35 }, { freq: 880, at: 0.3, dur: 0.45 }, { freq: 1100, at: 0.68, dur: 0.6 }],
        error: [{ freq: 880, at: 0, dur: 0.45 }, { freq: 880, at: 0.4, dur: 0.45 }, { freq: 660, at: 0.78, dur: 0.55 }, { freq: 440, at: 1.25, dur: 0.7 }],
        critical: [{ freq: 1040, at: 0, dur: 0.35 }, { freq: 880, at: 0.3, dur: 0.35 }, { freq: 1040, at: 0.6, dur: 0.4 }, { freq: 880, at: 0.95, dur: 0.45 }, { freq: 660, at: 1.3, dur: 0.85 }],
      };
      const seq = sequences[type] || sequences.normal;

      seq.forEach(({ freq, at, dur }) => {
        const osc = ctx.createOscillator();
        const osc2 = ctx.createOscillator(); 
        const gain = ctx.createGain();
        const gain2 = ctx.createGain();
        osc.type = type === 'critical' || type === 'error' ? 'triangle' : 'sine';
        osc2.type = 'sine';
        osc.frequency.value = freq;
        osc2.frequency.value = freq * 0.5; 
        osc.connect(gain);
        osc2.connect(gain2);
        gain.connect(gainMaster);
        gain2.connect(gainMaster);
        const t0 = now + at;
        gain.gain.setValueAtTime(0.0001, t0);
        gain.gain.exponentialRampToValueAtTime(type === 'critical' ? 0.9 : 0.78, t0 + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        gain2.gain.setValueAtTime(0.0001, t0);
        gain2.gain.exponentialRampToValueAtTime(type === 'critical' ? 0.45 : 0.35, t0 + 0.02);
        gain2.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        osc.start(t0);
        osc2.start(t0);
        osc.stop(t0 + dur + 0.02);
        osc2.stop(t0 + dur + 0.02);
      });

      this.blocked = false;
      this.unlocked = true;
      this.log('[SOUND] play', type, 'volume', this.volume);
      window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
      return true;
    } catch (e) {
      this.log('[SOUND] WebAudio play failed', e?.name, e?.message);

      
      const isBlocked = e && (e.name === 'NotAllowedError' || e.name === 'NotSupportedError' || /not allowed|autoplay|permission/i.test(e.message || ''));
      if (isBlocked) {
        this.blocked = true;
        this.unlocked = false;
        window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
        this.log('[SOUND] blocked (autoplay policy)');
        return false;
      }

      
      try {
        const wavBase64 = 'UklGRqQlAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YYAlAAAAAGgAQQGuAfEAA==';
        const audio = new Audio('data:audio/wav;base64,' + wavBase64);
        audio.volume = Math.max(0.3, Math.min(1, this.volume));
        audio.playsInline = true;
        
        try{ audio.muted = false; }catch{}
        const played = audio.play();
        if (played && typeof played.then === 'function') {
          await played;
          this.log('[SOUND] fallback HTMLAudio played');
          return true;
        }
        return false;
      } catch (e2) {
        this.log('[SOUND] fallback failed', e2);
        return false;
      }
    }
  }

  
  async test(type = 'normal') {
    await this.unlock();
    return this.play(type);
  }
}
