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

    var loadingMsg = to === 'en' ? 'Switching to English…' : 'جاري التبديل إلى العربية…';
    try {
      if (window.RASDLoading && window.RASDLoading.showNow) window.RASDLoading.showNow(loadingMsg);
      else if (window.showLoader) window.showLoader();
    } catch (e) {}

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
      try { if (window.RASDLoading && window.RASDLoading.reset) {  } } catch (e3) {}
    }

    function hideLoaderOnError() {
      try {
        if (window.RASDLoading && window.RASDLoading.reset) window.RASDLoading.reset();
        else if (window.hideLoader) window.hideLoader();
      } catch (e) {}
    }

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
        return res.text().then(function (txt) {
          var data = null;
          try { data = JSON.parse(txt); } catch (_) { data = null; }
          if (res.ok && data && data.ok) {
            try { location.reload(); } catch (_) { location.href = location.href; }
            return;
          }
          if (res.ok && txt && txt.indexOf('<html') !== -1) {
            try { location.reload(); } catch (_) {}
            return;
          }
          var msg = (data && (data.message || data.msg)) || '';
          if (msg && window.toast) { try { window.toast(msg); } catch (_) {} }
          restoreBtn();
          if (res.status >= 400 && res.status < 500) { hideLoaderOnError(); return; }
          fallbackFormSubmit(to);
        });
      }).catch(function (err) {
        restoreBtn();
        hideLoaderOnError();
        try {
          if (window.toast) window.toast(err && err.message ? err.message : '');
        } catch (_) {}
        fallbackFormSubmit(to);
      });
    } catch (e) {
      restoreBtn();
      hideLoaderOnError();
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

    if (!window.__rasdLocaleSubmitBound) {
      window.__rasdLocaleSubmitBound = true;
      document.addEventListener('submit', function (e) {
        var form = e.target;
        try {
          if (!form || !form.action) return;
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
