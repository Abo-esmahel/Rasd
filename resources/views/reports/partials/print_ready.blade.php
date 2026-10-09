<script>
async function printReportWhenReady(button) {
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    try {
        if (document.fonts && document.fonts.ready) {
            await Promise.race([
                document.fonts.ready,
                new Promise(resolve => window.setTimeout(resolve, 8000))
            ]);
        }

        const imagesReady = Promise.all(Array.from(document.images).map(image => {
            if (image.complete) return Promise.resolve();
            if (typeof image.decode === 'function') return image.decode().catch(() => {});
            return new Promise(resolve => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        }));
        await Promise.race([
            imagesReady,
            new Promise(resolve => window.setTimeout(resolve, 6000))
        ]);

        // Legacy fallback pagination (PV.build) is measured against live font
        // metrics: rebuild once after fonts settle so screen sheets and
        // printed sheets break identically (avoids fallback-metric splits).
        try {
            if (window.PV && typeof window.PV.build === 'function'
                && document.getElementById('preview-root')
                && !document.getElementById('sheet-page')) {
                window.PV.build();
            }
        } catch (e) { console.warn('[PRINT] PV rebuild failed', e); }

        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.print();
    } finally {
        window.setTimeout(() => {
            if (!button) return;
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }, 1000);
    }
}
</script>
