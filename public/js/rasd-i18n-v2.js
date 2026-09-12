/* RASD i18n v2 — Presentation Layer (SSR, no DOM translation)
 * - UI static text: Blade __() via app()->getLocale()
 * - Dynamic data: server preloads via LocalizedPresenter + l10n_text()
 * - Language switch: fetch POST /locale (JSON) -> reload, fallback to form POST.
 *   Never navigates to raw JSON; response is always handled in JS.
 *   Persists optimistically (cookie/localStorage) before fetch so early inline script avoids FOUC.
 *   Both header (#lang-toggle) and profile ([data-lang-btn]) use the SAME fetch path.
 */
(function () {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function persistLocal(to) {
    try { localStorage.setItem('rasd_locale', to); } catch (e) {}
    try {
      var exp = new Date(); exp.setFullYear(exp.getFullYear() + 1);
      document.cookie = 'rasd_locale=' + to + '; path=/; expires=' + exp.toUTCString() + '; SameSite=Lax';
    } catch (e) {}
    try {
      document.documentElement.setAttribute('lang', to);
      document.documentElement.setAttribute('dir', to === 'ar' ? 'rtl' : 'ltr');
      window.RASD_LOCALE = to;
    } catch (e) {}
  }

  function updateLabel(to) {
    try {
      var lbl = document.getElementById('lang-toggle-label');
      if (lbl) lbl.textContent = to === 'ar' ? 'EN' : 'ع';
    } catch (e) {}
  }

  function fallbackFormSubmit(to) {
    try {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/locale';
      form.style.display = 'none';
      var tok = document.createElement('input');
      tok.type = 'hidden';
      tok.name = '_token';
      tok.value = csrf();
      var loc = document.createElement('input');
      loc.type = 'hidden';
      loc.name = 'locale';
      loc.value = to;
      form.appendChild(tok);
      form.appendChild(loc);
      document.body.appendChild(form);
      form.submit();
      return true;
    } catch (e) {
      try { location.reload(); } catch (_) {}
      return false;
    }
  }

  function setLocale(to, btn) {
    to = to === 'en' ? 'en' : 'ar';
    persistLocal(to);
    updateLabel(to);

    var origOpacity = '';
    var wasDisabled = false;
    try {
      if (btn) {
        wasDisabled = !!btn.disabled;
        origOpacity = btn.style.opacity || '';
        btn.disabled = true;
        btn.style.opacity = '0.6';
      }
    } catch (e2) {}

    function restoreBtn() {
      try {
        if (btn) {
          btn.disabled = wasDisabled;
          btn.style.opacity = origOpacity;
        }
      } catch (e) {}
    }

    // Unified fetch path — handles JSON response correctly, never displays raw token/page
    try {
      fetch('/locale', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ locale: to })
      }).then(function (res) {
        // Try to parse as JSON; server returns {ok:true,locale,dir} on success
        return res.text().then(function (txt) {
          var data = null;
          try { data = JSON.parse(txt); } catch (_) { data = null; }
          if (res.ok && data && data.ok) {
            // Success — reload to get SSR HTML in new locale (cookie already set via Set-Cookie + document.cookie)
            try { location.reload(); } catch (_) { location.href = location.href; }
            return;
          }
          // If response is HTML redirect (fetch followed 302), res.ok && content is HTML -> treat as success
          if (res.ok && txt && txt.indexOf('<html') !== -1) {
            try { location.reload(); } catch (_) {}
            return;
          }
          // Server returned error JSON
          var msg = (data && (data.message || data.msg)) || '';
          if (msg && window.toast) { try { window.toast(msg); } catch (_) {} }
          // Even on error, reload to reflect persisted cookie (fallback)
          // But first restore button and allow user to retry
          restoreBtn();
          // If validation error, don't reload automatically; let toast show
          if (res.status >= 400 && res.status < 500) return;
          // For other errors, fallback to form submit to ensure server sync
          fallbackFormSubmit(to);
        });
      }).catch(function (err) {
        restoreBtn();
        try {
          if (window.toast) window.toast(err && err.message ? err.message : '');
        } catch (_) {}
        // Network failure — try traditional form as last resort (will update DB & cookie via 302)
        fallbackFormSubmit(to);
      });
    } catch (e) {
      restoreBtn();
      fallbackFormSubmit(to);
    }
  }

  function init() {
    var btn = document.getElementById('lang-toggle');
    if (btn && !btn.dataset.i18nV2Bound) {
      btn.dataset.i18nV2Bound = '1';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var current = document.documentElement.getAttribute('lang') || window.RASD_LOCALE || 'ar';
        if (current !== 'ar' && current !== 'en') current = 'ar';
        var next = current === 'ar' ? 'en' : 'ar';
        setLocale(next, btn);
      });
    }

    // Profile buttons: intercept both click and form submit so header & profile use SAME fetch path
    document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
      if (b.dataset.i18nV2Bound) return;
      b.dataset.i18nV2Bound = '1';
      b.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var l = b.getAttribute('data-lang-btn');
        if (l !== 'ar' && l !== 'en') l = 'ar';
        setLocale(l, b);
      });
    });

    // Also intercept native form submit for /locale (covers Enter key, programmatic submit, no-JS fallback is still handled via fetch)
    // Use capture to beat other handlers
    if (!window.__rasdLocaleSubmitBound) {
      window.__rasdLocaleSubmitBound = true;
      document.addEventListener('submit', function (e) {
        var form = e.target;
        try {
          if (!form || !form.action) return;
          // Only intercept locale forms
          var action = '';
          try { action = new URL(form.action, location.href).pathname; } catch (_) { action = form.getAttribute('action') || ''; }
          if (action !== '/locale' && !action.endsWith('/locale')) return;
          e.preventDefault();
          e.stopPropagation();
          var fd = new FormData(form);
          var locVal = fd.get('locale');
          if (!locVal) {
            var inp = form.querySelector('[name="locale"]');
            locVal = inp ? inp.value : '';
          }
          var loc = (locVal || '').toString();
          if (loc !== 'ar' && loc !== 'en') loc = 'ar';
          var b = form.querySelector('[data-lang-btn]') || form.querySelector('button[type="submit"]');
          setLocale(loc, b);
        } catch (_) {}
      }, true);
    }

    // Ensure label matches current html lang on load (covers back/forward cache)
    try {
      var cur = document.documentElement.getAttribute('lang') || 'ar';
      var lb = document.getElementById('lang-toggle-label');
      if (lb) lb.textContent = cur === 'ar' ? 'EN' : 'ع';
    } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  window.addEventListener('pageshow', function () {
    try { init(); } catch (e) {}
  });

  window.RASD_I18N = {
    setLocale: setLocale,
    get: function () { return document.documentElement.getAttribute('lang') || window.RASD_LOCALE || 'ar'; }
  };
})();
