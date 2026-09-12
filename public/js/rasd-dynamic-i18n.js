/* RASD dynamic-data i18n — v1.1.0 (Presentation Layer)
 * يترجم البيانات الديناميكية للصفحة الحالية فقط — الأصل العربي لا يُمس.
 * - دفعة منظمة واحدة (POST /translations/page) — ممنوع request لكل عنصر.
 * - الأسماء والقيم التقنية لا تُرسل أصلاً (الباكند يحرس أيضاً).
 * - الحالات والعدادات والتسميات الهيكلية والساعات تُترجم محلياً فورياً بلا AI.
 * - العودة للعربية فورية بلا AI. فشل AI → إبقاء الأصل، بلا كسر للصفحة.
 * - مؤشر صغير فقط (#report-l10n-badge) — بلا تجميد للصفحة.
 */
(function () {
  'use strict';

  var STATUS_EN = {
    draft: 'Draft',
    pending: 'Pending review',
    accepted: 'Accepted',
    rejected: 'Rejected',
    published: 'Published',
    monitor: 'Field Monitor',
    report_writer: 'Report Writer',
  };

  // تسميات هيكلية بعدّادات — قوالب محلية حتمية (labels ثابتة + قيم رقمية)، بلا AI.
  // data-l10n="type" + data-l10n-val="N" (+ data-l10n-attr="title,alt" عند الحاجة).
  function ordEn(n) {
    n = parseInt(n, 10);
    if (n === 1) return '1st';
    if (n === 2) return '2nd';
    if (n === 3) return '3rd';
    return n + 'th';
  }
  function cntEn(n, one, many) {
    n = parseInt(n, 10) || 0;
    return n === 1 ? '1 ' + one : n + ' ' + many;
  }
  var L10N = {
    attachment: { enText: function (v) { return cntEn(v, 'attachment', 'attachments'); } },
    note: { enText: function (v) { return cntEn(v, 'note', 'notes'); } },
    monitor: { enText: function (v) { return cntEn(v, 'monitor', 'monitors'); } },
    report: { enText: function (v) { return cntEn(v, 'report', 'reports'); } },
    fromn: { enText: function (v) { return 'from ' + v; } },
    selected: { enText: function (v) { return v + ' selected'; } },
    ordinal: { enText: function (v) { return ordEn(v); } },
    acceptpct: { enText: function (v) { return v + '% acceptance'; } },
    sheet: { enTitle: 'Sheet no. :n', enAlt: 'Report sheet no. :n' },
    kind_note: { enText: 'Note' },
    kind_sub: { enText: 'Public submission' },
  };

  function applyL10nEl(el, to) {
    try {
      var type = el.getAttribute('data-l10n');
      var map = L10N[type];
      if (!map) return;
      var val = el.getAttribute('data-l10n-val') || '';
      var attrs = (el.getAttribute('data-l10n-attr') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      if (el.dataset.arText === undefined) el.dataset.arText = el.textContent;
      if (to === 'ar') {
        if (el.textContent !== el.dataset.arText) el.textContent = el.dataset.arText;
        attrs.forEach(function (a) {
          var k = 'arA_' + a;
          if (el.dataset[k] !== undefined && el.getAttribute(a) !== el.dataset[k]) el.setAttribute(a, el.dataset[k]);
        });
        return;
      }
      if (typeof map.enText === 'function') {
        el.textContent = map.enText(val);
      } else if (typeof map.enText === 'string') {
        el.textContent = map.enText;
      }
      attrs.forEach(function (a) {
        var k = 'arA_' + a;
        if (el.dataset[k] === undefined) el.dataset[k] = el.getAttribute(a) || '';
        var tpl = a === 'title' ? map.enTitle : (a === 'alt' ? map.enAlt : null);
        if (typeof tpl === 'string') el.setAttribute(a, tpl.split(':n').join(val));
      });
    } catch (e) {}
  }

  function applyL10nAll(to) {
    try {
      var nodes = document.querySelectorAll('[data-l10n]');
      for (var i = 0; i < nodes.length; i++) applyL10nEl(nodes[i], to);
    } catch (e) {}
  }

  // ساعات toTime12 (ص/م) → AM/PM فورياً، والعكس restoration دقيق.
  var CLOCK_RE = /(\d{1,2}:\d{2}(?::\d{2})?)\s*([صم])(?![\u0600-\u06FF])/g;
  var CLOCK_BACK_RE = /(\d{1,2}:\d{2}(?::\d{2})?)\s*(AM|PM)/g;
  function clockSkip(el) {
    try {
      if (!el || el.nodeType !== 1) return true;
      var t = (el.tagName || '').toUpperCase();
      if (t === 'SCRIPT' || t === 'STYLE' || t === 'TEXTAREA' || t === 'INPUT' || t === 'SELECT' || t === 'OPTION') return true;
      if (el.hasAttribute && el.hasAttribute('data-no-translate')) return true;
      if (el.closest && (el.closest('[data-no-translate]') || el.closest('[data-i18n-field]'))) return true;
      return false;
    } catch (e) { return true; }
  }
  function applyClock(to) {
    try {
      var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
      var nodes = [];
      var nd;
      while ((nd = walker.nextNode())) nodes.push(nd);
      for (var i = 0; i < nodes.length; i++) {
        var t = nodes[i];
        var el = t.parentNode;
        if (clockSkip(el)) continue;
        var v = t.nodeValue;
        if (!v || v.length > 300) continue;
        if (to === 'en') {
          if (t._clockAr === undefined) {
            if (!/(\d{1,2}:\d{2}(?::\d{2})?)\s*[صم]/.test(v)) continue;
            t._clockAr = v;
          }
          t.nodeValue = t._clockAr.replace(CLOCK_RE, function (m, time, mer) {
            return time + ' ' + (mer === 'ص' ? 'AM' : 'PM');
          });
        } else {
          if (t._clockAr !== undefined && v !== t._clockAr) t.nodeValue = t._clockAr;
        }
      }
    } catch (e) {}
  }

  var appliedKeys = {}; // key -> translated text (ذاكرة الصفحة فقط؛ الـCache الدائم في السيرفر)
  var inflight = null;

  function currentLocale() {
    try {
      if (window.RASD_I18N && typeof window.RASD_I18N.get === 'function') return window.RASD_I18N.get();
    } catch (e) {}
    return window.RASD_LOCALE === 'en' ? 'en' : 'ar';
  }

  function csrf() {
    try {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : '';
    } catch (e) { return ''; }
  }

  function isModalHidden(el) {
    try {
      var modal = el.closest ? el.closest('[data-modal]') : null;
      if (!modal) return false;
      return modal.classList.contains('hidden');
    } catch (e) { return false; }
  }

  function isVisible(el) {
    try {
      if (isModalHidden(el)) return false;
      if (el.offsetWidth || el.offsetHeight) return true;
      if (typeof el.getClientRects === 'function' && el.getClientRects().length) return true;
      return false;
    } catch (e) { return false; }
  }

  function rememberOriginal(el) {
    try {
      if (el.dataset.arOriginal === undefined) el.dataset.arOriginal = el.textContent;
    } catch (e) {}
  }

  function collectFieldJobs() {
    var jobs = {}; // key -> {type,id,field,text,els:[]}
    var order = [];
    var nodes;
    try {
      nodes = document.querySelectorAll('[data-i18n-field]');
    } catch (e) { return { jobs: jobs, order: order }; }
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      try {
        var scope = el.closest ? el.closest('[data-i18n-entity][data-i18n-id]') : null;
        if (!scope) continue;
        var type = String(scope.getAttribute('data-i18n-entity') || '').toLowerCase();
        var idRaw = String(scope.getAttribute('data-i18n-id') || '').trim();
        var field = String(el.getAttribute('data-i18n-field') || '');
        if ((type !== 'report' && type !== 'note' && type !== 'submission' && type !== 'notification') || !idRaw || !field) continue;
        if (idRaw.length > 64 || !/^[A-Za-z0-9_-]+$/.test(idRaw)) continue;
        if (!isVisible(el)) continue;
        rememberOriginal(el);
        var text = (el.dataset.arOriginal !== undefined ? el.dataset.arOriginal : el.textContent) || '';
        text = String(text).replace(/\s+/g, ' ').trim();
        if (!text) continue;
        // لا حروف عربية = تقني/مرقم غالباً — يُترك كما هو بلا تكلفة.
        if (!/[\u0600-\u06FF]/.test(text)) continue;
        var key = type + ':' + idRaw + ':' + field;
        if (!jobs[key]) {
          jobs[key] = { type: type, id: idRaw, field: field, text: text, els: [] };
          order.push(key);
        }
        jobs[key].els.push(el);
        // طبّق ترجمة معروفة فوراً (Cache الصفحة) بلا انتظار الدفعة.
        if (appliedKeys[key] !== undefined && el.textContent !== appliedKeys[key]) {
          el.textContent = appliedKeys[key];
        }
      } catch (e) {}
    }
    return { jobs: jobs, order: order };
  }

  function applyStatuses(to) {
    var nodes;
    try {
      nodes = document.querySelectorAll('[data-status]');
    } catch (e) { return; }
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      try {
        if (el.dataset.arOriginal === undefined) el.dataset.arOriginal = el.textContent;
        if (to === 'en') {
          var st = String(el.getAttribute('data-status') || '').toLowerCase();
          el.textContent = STATUS_EN[st] || el.dataset.arOriginal;
        } else {
          el.textContent = el.dataset.arOriginal;
        }
      } catch (e) {}
    }
  }

  function restoreFields() {
    var nodes;
    try {
      nodes = document.querySelectorAll('[data-i18n-field]');
    } catch (e) { return; }
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      try {
        if (el.dataset.arOriginal !== undefined && el.textContent !== el.dataset.arOriginal) {
          el.textContent = el.dataset.arOriginal;
        }
      } catch (e) {}
    }
  }

  function badge(show, en) {
    try {
      var b = document.getElementById('report-l10n-badge');
      if (!b) return;
      if (!show) { b.classList.add('hidden'); b.textContent = ''; return; }
      b.textContent = en ? 'Preparing content…' : 'جارٍ تجهيز المحتوى…';
      b.classList.remove('hidden');
    } catch (e) {}
  }

  // —— الوثيقة الرسمية: نفس المحرك عبر localized HTML (عرض فقط، بلا حفظ) ——
  var origReportHtml = {}; // reportId -> original innerHTML

  function reportHolder() {
    return document.getElementById('paper-preview-html')
      || document.getElementById('report-preview-doc')
      || document.getElementById('report-export-doc')
      || null;
  }

  function reportId() {
    try {
      var w = document.querySelector('[data-report-id]');
      return w ? w.getAttribute('data-report-id') : null;
    } catch (e) { return null; }
  }

  function translateReportDoc(to) {
    var id = reportId();
    var holder = reportHolder();
    if (!id || !holder) return;
    try {
      if (origReportHtml[id] === undefined) origReportHtml[id] = holder.innerHTML;
      if (to === 'ar') {
        if (holder.innerHTML !== origReportHtml[id]) holder.innerHTML = origReportHtml[id];
        if (window.fitPaperPreview) window.fitPaperPreview();
        badge(false);
        return;
      }
      badge(true, true);
      fetch('/reports/' + encodeURIComponent(id) + '/localized', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      })
        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { return { res: res, d: d }; }); })
        .then(function (out) {
          badge(false);
          var d = out.d || {};
          if (out.res.ok && d.ok && typeof d.html === 'string' && d.html) {
            holder.innerHTML = d.html;
            if (window.fitPaperPreview) window.fitPaperPreview();
          }
          // فشل صامت → إبقاء الأصل العربي (fallback آمن).
        })
        .catch(function () { badge(false); });
    } catch (e) { badge(false); }
  }

  function translateVisible() {
    applyStatuses('en');
    applyL10nAll('en');
    applyClock('en');
    translateReportDoc('en');

    var collected = collectFieldJobs();
    var jobs = collected.jobs;
    var order = collected.order;
    var missing = [];
    for (var i = 0; i < order.length; i++) {
      var key = order[i];
      if (appliedKeys[key] === undefined && !shouldSkip(jobs[key].text)) missing.push(key);
    }
    if (!missing.length) return;

    // دفعة واحدة منظمة لكل الصفحة.
    var itemsMap = {};
    missing.forEach(function (key) {
      var j = jobs[key];
      var mk = j.type + ':' + j.id;
      if (!itemsMap[mk]) itemsMap[mk] = { type: j.type, id: j.id, fields: {} };
      itemsMap[mk].fields[j.field] = j.text;
    });
    var items = Object.keys(itemsMap).map(function (k) { return itemsMap[k]; });
    if (!items.length) return;
    if (inflight) return; // دفعة واحدة طائرة تكفي — لا 50 request.
    badge(true, true);
    inflight = fetch('/translations/page', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
      body: JSON.stringify({ locale: 'en', items: items }),
    })
      .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { return { res: res, d: d }; }); })
      .then(function (out) {
        var d = out.d || {};
        var map = (out.res.ok && d.ok && d.translations) || {};
        Object.keys(map).forEach(function (key) {
          var t = map[key];
          if (typeof t !== 'string' || !t) return;
          appliedKeys[key] = t;
          var j = jobs[key];
          if (j) {
            j.els.forEach(function (el) {
              try { el.textContent = t; } catch (e) {}
            });
          } else {
            // عنصر ظهر بعد الجمع (lazy) — طبّق عند رصده لاحقاً عبر appliedKeys.
          }
        });
      })
      .catch(function () {
        // fallback صامت: الأصل باقٍ كما هو.
      })
      .finally(function () {
        inflight = null;
        // أخفِ شارة الحقول إن لم تبقَ وثيقة قيد التحميل.
        badge(false);
      });
  }

  function shouldSkip(text) {
    var t = String(text || '').trim();
    if (!t) return true;
    if (/^[\d\s\-+()\/:.]+$/.test(t)) return true;
    if (/^\+?\d[\d\s\-()]{5,}$/.test(t)) return true;
    return false;
  }

  function restoreAll() {
    try {
      if (inflight && typeof inflight.catch === 'function') { /* تُترك لتكتمل بصمت */ }
    } catch (e) {}
    badge(false);
    applyStatuses('ar');
    restoreFields();
    applyL10nAll('ar');
    applyClock('ar');
    translateReportDoc('ar');
  }

  function refresh() {
    if (currentLocale() === 'en') translateVisible();
    else restoreAll();
  }

  // modal يُفتح لاحقاً (lazy): ترجم محتواه عند الظهور فقط.
  var sched = false;
  function schedule() {
    if (sched) return;
    sched = true;
    setTimeout(function () {
      sched = false;
      try {
        if (currentLocale() === 'en') translateVisible();
      } catch (e) {}
    }, 250);
  }

  function init() {
    try {
      document.addEventListener('rasd:locale', function (e) {
        var to = (e && e.detail && e.detail.locale) || currentLocale();
        if (to === 'en') translateVisible();
        else restoreAll();
      });
      var run = function () { refresh(); };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
      else run();
      try {
        var obs = new MutationObserver(function (muts) {
          if (currentLocale() !== 'en') return;
          for (var i = 0; i < muts.length; i++) {
            var m = muts[i];
            // فتح modal/درج (class) أو محتوى جديد (childList) — ترجم الظاهر فقط.
            if ((m.type === 'attributes' && m.attributeName === 'class') ||
                (m.type === 'childList' && m.addedNodes && m.addedNodes.length)) {
              schedule();
              return;
            }
          }
        });
        obs.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
      } catch (e) {}
    } catch (e) {}
  }

  window.RASD_DYN_I18N = { refresh: refresh, toEn: translateVisible, toAr: restoreAll };

  init();
})();
