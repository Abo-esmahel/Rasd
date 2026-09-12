{{-- ملاءمة ورقة A4 لعرض الشاشات الكبيرة فقط: تصغير متناسب بدل التمرير الأفقي.
     على الجوال (≤640px) لا تصغير أبداً — تنسيق الجوال في report-engine.css
     يعيد تدفق الورقة كمستند قراءة رسمي بعرض الشاشة (بلا نص مجهري).
     الشاشات فقط — الطباعة وPDF untouched. يُستدعى تلقائياً + عبر window.fitPaperPreview()
     بعد أي تحديث AJAX للمعاينة. --}}
<style>
    [data-paper-fit-inner] { transform-origin: top right; }
    /* الجوال: الورقة بعرض الشاشة بلا إطارات مزدوجة ولا تمرير أفقي (الشاشات فقط — الطباعة untouched) */
    @media screen and (max-width: 640px) {
        [data-paper-fit] { overflow: hidden !important; }
        .report-preview { background: transparent !important; border: none !important; padding: 0 !important; }
        .report-preview .report {
            box-shadow: 0 8px 26px rgba(28, 25, 21, .16) !important;
            border-radius: 8px;
        }
    }
    /* الطباعة: تحييد ملاءمة الشاشة (inline transform/width/overflow من
       سكربت الملاءمة أعلاه) حتى لا تتسرب حالة الشاشة إلى وسط الصفحات. */
    @media print {
        [data-paper-fit] { overflow: visible !important; }
        [data-paper-fit-inner] { transform: none !important; width: auto !important; max-width: 100% !important; margin: 0 !important; }
    }
</style>
<script>
(function () {
    var PHONE_Q = '(max-width: 640px)';
    function isPhone() {
        try { return window.matchMedia(PHONE_Q).matches; }
        catch (e) { return (window.innerWidth || 999) <= 640; }
    }
    function reset(inner, wrap) {
        inner.style.transform = '';
        inner.style.width = '';
        inner.style.maxWidth = '';
        inner.style.marginBottom = '';
        wrap.style.overflow = '';
    }
    function fit() {
        var phone = isPhone();
        document.querySelectorAll('[data-paper-fit]').forEach(function (wrap) {
            var inner = wrap.querySelector('[data-paper-fit-inner]');
            if (!inner) return;
            reset(inner, wrap);
            if (phone) {
                // الجوال: تدفق طبيعي بعرض الشاشة — بلا scale وبلا تمرير أفقي.
                wrap.style.overflow = 'hidden';
                return;
            }
            var avail = wrap.clientWidth;
            var need = inner.scrollWidth;
            if (avail > 0 && need > avail + 1) {
                var s = avail / need;
                inner.style.width = need + 'px';
                inner.style.maxWidth = 'none';
                inner.style.transform = 'scale(' + s + ')';
                var h = inner.offsetHeight;
                inner.style.marginBottom = (-(h - h * s)) + 'px';
                wrap.style.overflow = 'hidden';
            }
            inner.querySelectorAll('img').forEach(function (img) {
                if (!img.complete && !img.dataset.fitBound) {
                    img.dataset.fitBound = '1';
                    img.addEventListener('load', fit, { once: true });
                }
            });
        });
    }
    window.fitPaperPreview = fit;
    var t;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(fit, 150); });
    window.addEventListener('load', fit);
    window.addEventListener('orientationchange', function () { setTimeout(fit, 300); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fit(); });
    if (document.readyState !== 'loading') fit();
    else document.addEventListener('DOMContentLoaded', fit);
})();
</script>
