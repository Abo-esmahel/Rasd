(function () {
    'use strict';

    // Fast + explicit loader: nav shows almost instantly, net shows quickly.
    var SHOW_DELAY_NAV = 15;
    var SHOW_DELAY_NET = 60;
    var MIN_VISIBLE = 220;
    var MAX_VISIBLE = 15000;
    var REDUCED = false;

    try { REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

    var EXCLUDE = [
        /\/notifications(\?|\/|$)/,
        /[?&]live=1\b/,
        /\/push\//,
        /\/broadcasting\//,
        /\/(reverb|pusher|sockjs)/i,
        /\/sw\.js(\?|$)/,
        /^data:/,
        /^blob:/
    ];

    var pending = 0;
    var showTimer = null;
    var shownAt = 0;
    var visible = false;
    var maxTimer = null;
    var currentMessage = null;

    function el() { return document.getElementById('page-loader'); }

    function labelEl() { return document.getElementById('rasd-loader-text'); }

    function isExcluded(url) {
        if (!url) return true;
        var u = String(url);
        for (var i = 0; i < EXCLUDE.length; i++) {
            if (EXCLUDE[i].test(u)) return true;
        }
        return false;
    }

    function setMessage(msg) {
        currentMessage = msg || null;
        var lab = labelEl();
        if (lab && msg) {
            // Keep dots animation span if present
            var dots = lab.querySelector ? lab.querySelector('.rasd-loader-dots') : null;
            try {
                lab.childNodes[0].nodeValue = String(msg);
            } catch (e) {
                lab.textContent = String(msg);
                if (dots) lab.appendChild(dots);
                return;
            }
            if (!dots) {
                // label structure: text node + dots span (server-rendered). If missing, append.
                var s = document.createElement('span');
                s.className = 'rasd-loader-dots';
                s.setAttribute('aria-hidden', 'true');
                lab.appendChild(s);
            }
        }
        try {
            if (msg) sessionStorage.setItem('rasd_loader_msg', String(msg).slice(0, 80));
        } catch (e) {}
    }

    function clearPersistedMessage() {
        try { sessionStorage.removeItem('rasd_loader_msg'); } catch (e) {}
        try { sessionStorage.removeItem('rasd_loader_on'); } catch (e) {}
    }

    function paint(blocking) {
        var loader = el();
        if (!loader) return;
        try { loader.style.animation = 'none'; } catch (e) {}
        loader.classList.remove('hidden');
        // Force reflow so transition runs even on fast nav
        try { void loader.offsetWidth; } catch (e) {}
        loader.style.display = '';
        loader.style.opacity = '';
        loader.style.visibility = '';
        if (blocking) {
            loader.classList.add('is-blocking');
            loader.style.pointerEvents = 'auto';
        } else {
            loader.style.pointerEvents = 'none';
        }
        // Show synchronously for instant feedback (no double-rAF delay on showNow path)
        loader.classList.add('is-visible');
        loader.setAttribute('aria-hidden', 'false');
        try { document.body.setAttribute('aria-busy', 'true'); } catch (e) {}
    }

    function unpaint() {
        var loader = el();
        if (!loader) return;
        loader.classList.remove('is-visible');
        loader.classList.remove('is-blocking');
        loader.setAttribute('aria-hidden', 'true');
        try { document.body.removeAttribute('aria-busy'); } catch (e) {}
        clearPersistedMessage();
        currentMessage = null;
        setTimeout(function () {
            if (!visible && pending <= 0) {
                loader.classList.add('hidden');
                loader.style.pointerEvents = 'none';
            }
        }, 240);
    }

    function scheduleShow(delay, blocking) {
        if (visible || showTimer) return;
        if (delay <= 0) {
            visible = true;
            shownAt = Date.now();
            paint(blocking);
            try { document.dispatchEvent(new CustomEvent('rasd:loading-show')); } catch (e) {}
            clearTimeout(maxTimer);
            maxTimer = setTimeout(reset, MAX_VISIBLE);
            return;
        }
        showTimer = setTimeout(function () {
            showTimer = null;
            if (pending <= 0 || document.hidden) return;
            visible = true;
            shownAt = Date.now();
            paint(blocking);
            try { document.dispatchEvent(new CustomEvent('rasd:loading-show')); } catch (e) {}
            clearTimeout(maxTimer);
            maxTimer = setTimeout(reset, MAX_VISIBLE);
        }, REDUCED ? Math.min(delay, 60) : delay);
    }

    function begin(kind, opts) {
        pending++;
        var blocking = !!(opts && opts.blocking);
        var msg = opts && opts.message ? opts.message : null;
        if (msg) setMessage(msg);
        scheduleShow(kind === 'nav' ? SHOW_DELAY_NAV : SHOW_DELAY_NET, blocking || kind === 'nav');
        return pending;
    }

    // Immediate, explicit show — used for language switch & report open.
    // Persists across reload so the NEXT page shows the spinner instantly (no white gap).
    function showNow(msg) {
        if (msg) setMessage(msg);
        try { sessionStorage.setItem('rasd_loader_on', '1'); } catch (e) {}
        pending++;
        clearTimeout(showTimer);
        showTimer = null;
        if (!visible) {
            visible = true;
            shownAt = Date.now();
            paint(true);
            try { document.dispatchEvent(new CustomEvent('rasd:loading-show')); } catch (e) {}
            clearTimeout(maxTimer);
            maxTimer = setTimeout(reset, MAX_VISIBLE);
        } else {
            paint(true);
            if (msg) setMessage(msg);
        }
        return pending;
    }

    function end() {
        if (pending > 0) pending--;
        if (pending <= 0) {
            pending = 0;
            clearTimeout(showTimer);
            showTimer = null;
            if (visible) {
                var wait = MIN_VISIBLE - (Date.now() - shownAt);
                if (wait > 0) {
                    setTimeout(function () { if (pending <= 0) { visible = false; unpaint(); } }, wait);
                } else {
                    visible = false;
                    unpaint();
                }
                try { document.dispatchEvent(new CustomEvent('rasd:loading-hide')); } catch (e) {}
            } else {
                clearPersistedMessage();
            }
        }
    }

    function reset() {
        pending = 0;
        clearTimeout(showTimer);
        showTimer = null;
        clearTimeout(maxTimer);
        if (visible) {
            visible = false;
            unpaint();
        } else {
            var loader = el();
            if (loader) {
                loader.classList.add('hidden');
                loader.classList.remove('is-blocking');
            }
            clearPersistedMessage();
        }
    }

    function defaultNavMessage(href) {
        try {
            var h = String(href || '');
            if (h.indexOf('/reports/') !== -1 && h.match(/\/reports\/\d+/)) return document.documentElement.getAttribute('lang') === 'en' ? 'Opening report…' : 'جاري فتح التقرير…';
            if (h.indexOf('/reports') !== -1) return document.documentElement.getAttribute('lang') === 'en' ? 'Loading reports…' : 'جاري تحميل التقارير…';
            if (h.indexOf('/notes') !== -1) return document.documentElement.getAttribute('lang') === 'en' ? 'Loading…' : 'جاري التحميل…';
        } catch (e) {}
        return null;
    }

    try {
        if (window.fetch && !window.fetch.__rasdPatched) {
            var nativeFetch = window.fetch;
            var patched = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                if (isExcluded(url)) return nativeFetch.apply(this, arguments);
                begin('net');
                return nativeFetch.apply(this, arguments).then(
                    function (res) { end(); return res; },
                    function (err) { end(); throw err; }
                );
            };
            patched.__rasdPatched = true;
            window.fetch = patched;
        }
    } catch (e) {}

    try {
        var proto = window.XMLHttpRequest && window.XMLHttpRequest.prototype;
        if (proto && !proto.__rasdPatched) {
            var nativeOpen = proto.open;
            var nativeSend = proto.send;
            proto.open = function (method, url) {
                try { this.__rasdUrl = String(url || ''); } catch (e) { this.__rasdUrl = ''; }
                return nativeOpen.apply(this, arguments);
            };
            proto.send = function () {
                var xhr = this;
                if (isExcluded(xhr.__rasdUrl)) return nativeSend.apply(this, arguments);
                begin('net');
                var done = false;
                var finish = function () { if (!done) { done = true; end(); } };
                try {
                    xhr.addEventListener('loadend', finish, { once: true });
                } catch (e) {
                    var st = setInterval(function () {
                        if (xhr.readyState === 4) { clearInterval(st); finish(); }
                    }, 400);
                }
                return nativeSend.apply(this, arguments);
            };
            proto.__rasdPatched = true;
        }
    } catch (e) {}

    document.addEventListener('click', function (e) {
        try {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            if (a.hasAttribute('target') || a.hasAttribute('download') || a.hasAttribute('data-no-loader')) return;
            if (a.closest('[data-no-loader]')) return;
            var low = href.toLowerCase();
            if (low.indexOf('mailto:') === 0 || low.indexOf('tel:') === 0) return;
            // Instant explicit feedback for report links (the slowest pages)
            var msg = defaultNavMessage(href);
            if (msg) {
                showNow(msg);
                return;
            }
            setTimeout(function () { if (!e.defaultPrevented) begin('nav'); }, 0);
        } catch (err) {}
    }, { passive: true });

    document.addEventListener('submit', function (e) {
        try {
            var form = e.target;
            if (!form || e.defaultPrevented) return;
            if (form.hasAttribute && (form.hasAttribute('data-no-loader') || form.hasAttribute('data-ajax'))) return;
            begin('nav');
        } catch (err) {}
    }, { passive: true });

    // If previous page requested a persistent loader (locale switch / report nav),
    // show it the moment this page's loader element exists.
    function restorePersisted() {
        try {
            var on = sessionStorage.getItem('rasd_loader_on') === '1';
            if (!on) return;
            var msg = sessionStorage.getItem('rasd_loader_msg') || null;
            var loader = el();
            if (!loader) return;
            if (msg) {
                var lab = labelEl();
                if (lab) {
                    try {
                        lab.childNodes[0].nodeValue = String(msg);
                    } catch (e2) {
                        lab.textContent = String(msg);
                    }
                }
            }
            pending = Math.max(pending, 1);
            visible = true;
            shownAt = Date.now();
            paint(true);
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            restorePersisted();
            // Safety: never trap the user behind the spinner
            setTimeout(function () { if (pending <= 0 && visible) { visible = false; unpaint(); } }, 4000);
        });
    } else {
        restorePersisted();
    }

    window.addEventListener('load', function () {
        // New page finished: hide persisted loader shortly after paint
        setTimeout(reset, 120);
    });
    window.addEventListener('pageshow', function (ev) {
        if (ev && ev.persisted) { reset(); return; }
        // bfcache or normal: ensure no stale spinner
        setTimeout(function () { if (pending <= 0) reset(); }, 150);
    });
    window.addEventListener('error', function () { if (pending <= 0) reset(); }, true);

    window.RASDLoading = {
        show: function (msg) { if (msg) return showNow(msg); begin('net'); },
        showNow: showNow,
        hide: end,
        begin: begin,
        end: end,
        reset: reset,
        setMessage: setMessage,
        track: function (promise) {
            begin('net');
            if (promise && typeof promise.finally === 'function') promise.finally(end);
            else if (promise && typeof promise.then === 'function') promise.then(end, end);
            else end();
            return promise;
        },
        get pending() { return pending; },
        get visible() { return visible; }
    };
})();
