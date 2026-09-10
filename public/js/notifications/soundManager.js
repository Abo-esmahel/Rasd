/**
 * NotificationSoundManager
 * Handles audio with respect to Browser Autoplay Policies.
 * 
 * Features:
 *  - Lazy AudioContext creation + unlock on first user interaction
 *  - Preload flag, enabled/disabled, volume (0-1)
 *  - Per-type sound selection (normal/success/error/critical)
 *  - Graceful failure when autoplay blocked -> sets state 'blocked'
 *  - Persistence via localStorage + backend preferences
 *  - Debug logging
 */

export class NotificationSoundManager {
  constructor(options = {}) {
    this.enabled = options.enabled ?? true;
    this.volume = options.volume ?? 0.95; // 0-1 (مرتفع افتراضياً — كان 0.7 ناصي)
    this.blocked = false;
    this.unlocked = false;
    this.audioCtx = null;
    this.debug = options.debug ?? false;
    this.theme = options.theme ?? 'default';

    // Fallback HTMLAudio (for environments where WebAudio not available)
    this.fallbackAudio = null;

    // tab-scoped unlock handler bound once
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
    // Try to emit event for UI banner update
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
      // Also persist to backend async (fire-and-forget)
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
      // iOS يلزم إنشاء AudioContext داخل نفس حدث المستخدم — ننشئه هنا لأول مرة فقط عند اللمس
      const ctx = this.getAudioCtx();
      if (ctx) {
        if (ctx.state === 'suspended') {
          await ctx.resume();
          this.log('[SOUND] AudioContext resumed', ctx.state);
        }
        // iOS prime: شغل بافر صامت قصير جداً داخل نفس الـ gesture لفك القفل
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
      // حتى بدون AudioContext (مثلاً iOS قديم) نعتبره مفتوح بعد تفاعل
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
    // mobile: touchend مهم جداً لـ iOS، و passive:false لضمان اعتبارها user activation
    ['click', 'keydown', 'touchstart', 'touchend', 'pointerdown', 'pointerup'].forEach(ev => {
      document.addEventListener(ev, this._unlockHandler, { once: false, passive: false });
    });
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) this.unlock().catch(()=>{});
    });
    window.addEventListener('notification:toast', () => {
      if (this.blocked) this.unlock().catch(()=>{});
    });
    // محاولة فك مبكرة بعد تحميل الصفحة إذا كان هناك تفاعل سابق في نفس الجلسة (localStorage)
    try{
      if(sessionStorage.getItem('notif_unlocked')==='1'){
        setTimeout(()=>this.unlock().catch(()=>{}), 400);
      }
      // عند نجاح unlock نحفظ علامة
      window.addEventListener('notification:sound-state', (e)=>{
        if(e.detail?.unlocked) try{ sessionStorage.setItem('notif_unlocked','1'); }catch{}
      });
    }catch{}
  }

  /**
   * Play notification sound. Respects enabled/disabled and handles autoplay block.
   * @param {string} type - 'normal' | 'success' | 'error' | 'critical'
   * @returns {Promise<boolean>} true if played, false if blocked/skipped
   */
  async play(type = 'normal') {
    if (!this.enabled) {
      this.log('[SOUND] play skipped (disabled)', type);
      return false;
    }
    if (this.volume <= 0.01) {
      this.log('[SOUND] play skipped (volume 0)', type);
      return false;
    }

    // Try to unlock if not yet
    if (!this.unlocked) {
      await this.unlock();
    }

    // Haptic vibration (if supported) — not blocked by autoplay
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

    // Attempt Web Audio synthesis
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

      // Define tone sequences per type — طويلة/ضخمة/أكبر (كان قصير 0.2s، الآن 2x)
      const sequences = {
        normal: [{ freq: 880, at: 0, dur: 0.45 }, { freq: 660, at: 0.38, dur: 0.5 }, { freq: 880, at: 0.78, dur: 0.6 }],
        success: [{ freq: 660, at: 0, dur: 0.35 }, { freq: 880, at: 0.3, dur: 0.45 }, { freq: 1100, at: 0.68, dur: 0.6 }],
        error: [{ freq: 880, at: 0, dur: 0.45 }, { freq: 880, at: 0.4, dur: 0.45 }, { freq: 660, at: 0.78, dur: 0.55 }, { freq: 440, at: 1.25, dur: 0.7 }],
        critical: [{ freq: 1040, at: 0, dur: 0.35 }, { freq: 880, at: 0.3, dur: 0.35 }, { freq: 1040, at: 0.6, dur: 0.4 }, { freq: 880, at: 0.95, dur: 0.45 }, { freq: 660, at: 1.3, dur: 0.85 }],
      };
      const seq = sequences[type] || sequences.normal;

      seq.forEach(({ freq, at, dur }) => {
        const osc = ctx.createOscillator();
        const osc2 = ctx.createOscillator(); // طبقة bass ثانية لأضخم/أكبر
        const gain = ctx.createGain();
        const gain2 = ctx.createGain();
        osc.type = type === 'critical' || type === 'error' ? 'triangle' : 'sine';
        osc2.type = 'sine';
        osc.frequency.value = freq;
        osc2.frequency.value = freq * 0.5; // أوكتاف أخفض لضخامة
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

      // Check if it's autoplay block
      const isBlocked = e && (e.name === 'NotAllowedError' || e.name === 'NotSupportedError' || /not allowed|autoplay|permission/i.test(e.message || ''));
      if (isBlocked) {
        this.blocked = true;
        this.unlocked = false;
        window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
        this.log('[SOUND] blocked (autoplay policy)');
        return false;
      }

      // Fallback: HTMLAudio بملف wav مضمن — نغمة 880Hz واضحة 0.6s (مسموعة حتى لو WebAudio محجوب)
      try {
        const wavBase64 = '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';
        // إنشاء عنصر صوت جديد لكل تشغيل لضمان عدم حجب المتصفح لإعادة التشغيل
        const audio = new Audio('data:audio/wav;base64,' + wavBase64);
        audio.volume = Math.max(0.3, Math.min(1, this.volume));
        audio.playsInline = true;
        // iOS يلزم playsInline و muted false
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

  // Test helper
  async test(type = 'normal') {
    await this.unlock();
    return this.play(type);
  }
}
