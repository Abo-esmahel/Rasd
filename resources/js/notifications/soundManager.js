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
    this.volume = options.volume ?? 0.7; // 0-1
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
      const ctx = this.getAudioCtx();
      if (ctx && ctx.state === 'suspended') {
        await ctx.resume();
        this.log('[SOUND] AudioContext resumed', ctx.state);
      }
      if (ctx && ctx.state === 'running') {
        this.unlocked = true;
        this.blocked = false;
        window.dispatchEvent(new CustomEvent('notification:sound-state', { detail: this.getState() }));
        // Remove listeners after successful unlock
        ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(ev =>
          document.removeEventListener(ev, this._unlockHandler)
        );
        return true;
      }
      // Even without AudioContext, consider unlocked after interaction
      this.unlocked = true;
      return true;
    } catch (e) {
      this.log('[SOUND] unlock failed', e);
      return false;
    }
  }

  bindUnlockOnInteraction() {
    ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(ev => {
      document.addEventListener(ev, this._unlockHandler, { once: false, passive: true });
    });
    // Also unlock on visibility change to running? no
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

      // Define tone sequences per type (freq, duration)
      const sequences = {
        normal: [{ freq: 880, at: 0, dur: 0.26 }, { freq: 660, at: 0.22, dur: 0.26 }],
        success: [{ freq: 660, at: 0, dur: 0.2 }, { freq: 880, at: 0.18, dur: 0.28 }],
        error: [{ freq: 880, at: 0, dur: 0.28 }, { freq: 880, at: 0.24, dur: 0.28 }, { freq: 660, at: 0.48, dur: 0.32 }],
        critical: [{ freq: 1040, at: 0, dur: 0.22 }, { freq: 880, at: 0.2, dur: 0.22 }, { freq: 1040, at: 0.4, dur: 0.22 }, { freq: 660, at: 0.62, dur: 0.4 }],
      };
      const seq = sequences[type] || sequences.normal;

      seq.forEach(({ freq, at, dur }) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        osc.connect(gain);
        gain.connect(gainMaster);
        const t0 = now + at;
        // ADSR-like envelope: quick attack, exponential decay
        gain.gain.setValueAtTime(0.0001, t0);
        gain.gain.exponentialRampToValueAtTime(0.32, t0 + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        osc.start(t0);
        osc.stop(t0 + dur + 0.02);
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

      // Fallback: try HTMLAudio with tiny base64 beep (data URI)
      try {
        // Very short beep WAV (approx 0.2s 880Hz) base64 - minimal size fallback
        // If fails, just vibrate already done.
        const audio = this.fallbackAudio || new Audio();
        // Use WebAudio-generated buffer as Data URI to avoid network? Use oscillator fallback already.
        // As fallback, we create a simple beep via data URI (440Hz sine for 0.3s)
        // This data URI is a 8-bit mono 8000Hz wav with simple tone - generated placeholder
        // We do not need network fetch; using oscillator is primary. So if WebAudio failed for non-block reason, consider failed.
        this.log('[SOUND] fallback not implemented, considering failed');
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
