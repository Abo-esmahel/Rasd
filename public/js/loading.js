/* RASD Global Loading Manager — ذكي وخفيف (بلا build وبلا polling)
 * يظهر دائرة التحميل فقط عند وجود انتظار حقيقي:
 *  - تنقّل (رابط/نموذج عادي)      -> يظهر بسرعة
 *  - شبكة (fetch/XHR) بطيئة فقط   -> يظهر بعد مهلة قصيرة حتى لا يومض للطلبات السريعة
 * يتجاهل تلقائياً: نبض الإشعارات، live=1، الـ push/broadcast، روابط نفس الصفحة، data-no-loader
 * التكلفة: مستمعان خاملان + عدّاد + مؤقّتان كحد أقصى. لا intervals ولا فحص دوري.
 */
(function () {
    'use strict';

    var SHOW_DELAY_NAV = 70;    // مهلة قبل الإظهار للتنقل (ms)
    var SHOW_DELAY_NET = 180;   // مهلة قبل الإظهار للشبكة — الطلبات الأسرع لا تُظهر شيئاً
    var MIN_VISIBLE = 220;      // أقصر مدة ظهور لمنع الوميض
    var MAX_VISIBLE = 12000;    // صمّام أمان
    var REDUCED = false;

    try { REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

    // طلبات خلفية صامتة — لا تستحق مؤشر انتظار أبداً
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

    var pending = 0;        // عدد المهام المنتظرة
    var showTimer = null;   // مؤقت الإظهار المؤجل
    var shownAt = 0;        // متى ظهر
    var visible = false;
    var maxTimer = null;

    function el() { return document.getElementById('page-loader'); }

    function isExcluded(url) {
        if (!url) return true;
        var u = String(url);
        for (var i = 0; i < EXCLUDE.length; i++) {
            if (EXCLUDE[i].test(u)) return true;
        }
        return false;
    }

    function paint() {
        var loader = el();
        if (!loader) return;
        // إلغاء أي بقايا من النظام القديم (inline styles + animation fill)
        try { loader.style.animation = 'none'; } catch (e) {}
        loader.classList.remove('hidden');
        // إزالة الـ inline القديمة حتى تتحكم الكلاسات وحدها
        loader.style.display = '';
        loader.style.opacity = '';
        loader.style.visibility = '';
        loader.style.pointerEvents = 'none';
        // تفعيل الانتقال في الإطار التالي (دفعة رسم واحدة)
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                loader.classList.add('is-visible');
                loader.setAttribute('aria-hidden', 'false');
            });
        });
    }

    function unpaint() {
        var loader = el();
        if (!loader) return;
        loader.classList.remove('is-visible');
        loader.setAttribute('aria-hidden', 'true');
        setTimeout(function () {
            if (!visible && pending <= 0) loader.classList.add('hidden');
        }, 240);
    }

    function scheduleShow(delay) {
        if (visible || showTimer) return;
        showTimer = setTimeout(function () {
            showTimer = null;
            if (pending <= 0 || document.hidden) return;
            visible = true;
            shownAt = Date.now();
            paint();
            try { document.dispatchEvent(new CustomEvent('rasd:loading-show')); } catch (e) {}
            clearTimeout(maxTimer);
            maxTimer = setTimeout(reset, MAX_VISIBLE);
        }, REDUCED ? Math.min(delay, 60) : delay);
    }

    function begin(kind) {
        pending++;
        scheduleShow(kind === 'nav' ? SHOW_DELAY_NAV : SHOW_DELAY_NET);
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
            if (loader) loader.classList.add('hidden');
        }
    }

    // — تتبّع fetch (طلبات المستخدم البطيئة فقط) —
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

    // — تتبّع XHR (رفع الملفات وغيرها) —
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

    // — تنقّل الروابط: فقط تنقّل حقيقي لصفحة أخرى —
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
            // رابط ajax يمنع التنقل الافتراضي -> الشبكة هي من تُظهر المؤشر إن طال الانتظار
            // (مستمع العنصر يعمل قبل مستمع document، و defaultPrevented تكشفه)
            setTimeout(function () { if (!e.defaultPrevented) begin('nav'); }, 0);
        } catch (err) {}
    }, { passive: true });

    // — إرسال النماذج: العادية فقط (ajax تُغطيها الشبكة) —
    document.addEventListener('submit', function (e) {
        try {
            var form = e.target;
            if (!form || e.defaultPrevented) return;
            if (form.hasAttribute && (form.hasAttribute('data-no-loader') || form.hasAttribute('data-ajax'))) return;
            begin('nav');
        } catch (err) {}
    }, { passive: true });

    // — إعادة الضبط عند اكتمال التحميل أو العودة من الكاش —
    window.addEventListener('load', reset);
    window.addEventListener('pageshow', reset);
    window.addEventListener('error', function () { if (pending <= 0) reset(); }, true);

    window.RASDLoading = {
        show: function () { begin('net'); },
        hide: end,
        begin: begin,
        end: end,
        reset: reset,
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
