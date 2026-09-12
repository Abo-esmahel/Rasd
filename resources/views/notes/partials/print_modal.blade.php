

<div id="print-selection-modal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm" onclick="closePrintSelectionModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-[#e6e9e1]">
        
        <div class="px-6 py-4 border-b border-[#e6e9e1] flex items-center justify-between gap-4 shrink-0 bg-[#fdfcfa]">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-ink-800">{{ __('ui.print') }}</h2>
                    <p class="text-xs text-[#737373]">{{ __('ui.choose_attachments') }}</p>
                </div>
            </div>
            <button type="button" onclick="closePrintSelectionModal()" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition" aria-label="{{ __('ui.close_camera') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        
        <div class="p-6 overflow-y-auto flex-1 space-y-4">
            
            <div class="flex items-center justify-between gap-3 pb-3 border-b border-[#e6e9e1] flex-wrap">
                <div class="text-xs font-bold text-[#525252] flex items-center gap-2">
                    <span>{{ __('ui.available_attachments') }}</span>
                    <span id="print-selected-count-badge" class="px-2 py-0.5 rounded-full bg-[#eef4f0] text-[#0e6a38] font-mono text-xs">{{ __('ui.print_selected_of', ['count' => 1, 'total' => 1]) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printSelectAll(true)" class="px-2.5 py-1 rounded-lg bg-surface-50 border border-[#e6e9e1] text-xs font-bold text-[#525252] hover:bg-[#eef4f0] hover:text-[#0e6a38] transition">
                        {{ __('ui.select_all') }}
                    </button>
                    <button type="button" onclick="printSelectAll(false)" class="px-2.5 py-1 rounded-lg bg-surface-50 border border-[#e6e9e1] text-xs font-bold text-[#525252] hover:bg-red-50 hover:text-red-600 transition">
                        {{ __('ui.deselect_all') }}
                    </button>
                </div>
            </div>

            


            
            <div id="print-attachments-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                
            </div>

            <div id="print-no-attachments-msg" class="hidden p-8 text-center bg-[#f5f7f5] rounded-xl border border-[#e6e9e1]">
                <p class="text-sm font-bold text-[#525252]">{{ __('ui.no_attachments') }}</p>
            </div>
        </div>

        
        <div class="px-6 py-4 border-t border-[#e6e9e1] flex items-center justify-between gap-3 shrink-0 bg-[#f5f7f5]">
            <button type="button" onclick="closePrintSelectionModal()" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] text-sm font-medium hover:bg-white transition">
                {{ __('ui.cancel_btn') }}
            </button>
            <button type="button" id="print-proceed-preview-btn" onclick="generateAndOpenPreview()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">
                <span id="print-proceed-spinner" class="hidden w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
                <svg id="print-proceed-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span id="print-proceed-text">{{ __('ui.print_preview_a4') }}</span>
            </button>
        </div>
    </div>
</div>

<div id="print-preview-modal" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-2 sm:p-4">
    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closePrintPreviewModal()"></div>
    <div class="relative bg-[#343a34] rounded-2xl shadow-2xl w-full max-w-4xl h-[94vh] flex flex-col overflow-hidden border border-[#525252]">
        
        <div class="px-6 py-3.5 border-b border-[#444c44] flex items-center justify-between gap-4 shrink-0 bg-[#252b26] text-white">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#0e6a38] text-white flex items-center justify-center font-bold text-sm">
                    A4
                </div>
                <div>
                    <h2 class="text-sm font-extrabold text-white">{{ __('ui.a4_official') }}</h2>
                    <p id="print-preview-pages-count" class="text-xs text-white/70">{{ __('ui.print_official_page') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="backToSelectionModal()" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                    {{ __('ui.edit_attachments') }}
                </button>
                <button type="button" onclick="closePrintPreviewModal()" class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center text-white/70 hover:text-white transition" aria-label="{{ __('ui.close_camera') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 flex flex-col items-center gap-6 bg-[#2b312c]">
            <div id="print-preview-pages-container" class="w-full flex flex-col items-center gap-6">
                
            </div>
        </div>

        
        <div class="px-6 py-3.5 border-t border-[#444c44] flex items-center justify-between gap-4 shrink-0 bg-[#252b26]">
            <div class="text-xs text-white/70 hidden sm:block">
                {{ __('ui.a4_layout_ok') }}
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button type="button" onclick="closePrintPreviewModal()" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-medium transition">
                    {{ __('ui.close_camera') }}
                </button>
                <button type="button" onclick="triggerNativePrint()" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#138044] text-white font-extrabold text-sm shadow-lg shadow-[#0e6a38]/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>{{ __('ui.print_now') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>

@media screen {
    #rasd-print-document,
    #printable-a4-doc {
        display: none !important;
    }
    
    .a4-preview-sheet, .a4-print-page {
        width: 210mm !important;
        height: 297mm !important;
        min-width: 210mm !important;
        min-height: 297mm !important;
        max-width: 210mm !important;
        max-height: 297mm !important;
        background: #ffffff !important;
        color: #111827 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        box-sizing: border-box !important;
        position: relative !important;
        overflow: visible !important;
        padding: 0 !important;
        margin: 0 auto !important;
        border: 1px solid #e5e7eb !important;
        transform: none !important;
        zoom: 1 !important;
        display: block !important;
    }
    
    .a4-preview-sheet[data-orientation="landscape"],
    .a4-print-page[data-orientation="landscape"] {
        width: 297mm !important;
        height: 210mm !important;
        min-width: 297mm !important;
        min-height: 210mm !important;
        max-width: 297mm !important;
        max-height: 210mm !important;
    }
    @media print {
        .a4-preview-sheet, .a4-print-page {
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
            transform: none !important;
            zoom: 1 !important;
        }
        #printable-a4-doc .a4-print-page {
            break-after: avoid !important;
            page-break-after: avoid !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }
        #printable-a4-doc { display:block !important; visibility:visible !important; }
        #rasd-print-document, #print-preview-pages-container { display:none !important; visibility:hidden !important; }
    }
    
    .a4-preview-sheet {
        transform: scale(0.92) !important;
        transform-origin: top center !important;
        margin-bottom: -22mm !important;
        overflow: visible !important;
    }
    .a4-preview-sheet[data-orientation="portrait"] {
        
        overflow: visible !important;
    }
    .a4-preview-sheet .a4-print-page {
        overflow: visible !important;
    }
    
    .a4-print-page [data-print-description-wrapper] {
        left: 0 !important;
        width: 100% !important;
        padding-left: 6mm !important;
        padding-right: 6mm !important;
        box-sizing: border-box !important;
    }
    #print-preview-pages-container {
        width: 100% !important;
        max-width: none !important;
        padding: 12px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        gap: 16px !important;
        background: #2b312c !important;
    }
    #print-preview-modal .flex-1.overflow-y-auto {
        padding: 0 !important;
        background: #2b312c !important;
    }
    html.dark .a4-preview-sheet {
        background: #ffffff !important;
        color: #111827 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6), 0 8px 10px -6px rgba(0, 0, 0, 0.6);
        border-color: #e6e9e1 !important;
    }
    html.dark .a4-preview-sheet .bg-\[\#fafbfa\],
    html.dark .a4-preview-sheet .bg-\[\#f6f8f6\],
    html.dark .a4-preview-sheet .bg-white,
    html.dark .a4-preview-sheet .bg-\[\#f5f7f5\] {
        background-color: #fafbfa !important;
        background: #fafbfa !important;
    }
    html.dark .a4-preview-sheet .bg-\[\#f6f8f6\] {
        background-color: #f6f8f6 !important;
        background: #f6f8f6 !important;
    }
    html.dark .a4-preview-sheet .text-ink-800,
    html.dark .a4-preview-sheet .text-ink-700,
    html.dark .a4-preview-sheet .text-\[\#1a2e1f\] {
        color: #1a2e1f !important;
    }
    html.dark .a4-preview-sheet .text-\[\#0e6a38\] {
        color: #0e6a38 !important;
    }
    html.dark .a4-preview-sheet .text-\[\#737373\],
    html.dark .a4-preview-sheet .text-\[\#525252\] {
        color: #525252 !important;
    }
    html.dark .a4-preview-sheet .border-\[\#e6e9e1\],
    html.dark .a4-preview-sheet .border-\[\#e2e8e2\] {
        border-color: #e6e9e1 !important;
    }
}

@media print {
    .no-print, input, textarea, button, form { display: none !important; }
}

@page {
    size: A4 portrait;
    margin: 0;
}
@page :first {
    size: A4 portrait;
    margin: 0;
}

@media print {
    body {
        visibility: hidden !important;
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        height: auto !important;
        position: static !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    html {
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    #printable-a4-doc,
    #printable-a4-doc * {
        visibility: visible !important;
    }
    
    #rasd-print-document,
    #print-preview-pages-container,
    #print-preview-modal,
    #print-selection-modal {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
    }
    
    #printable-a4-doc {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: auto !important;
        height: auto !important;
        max-width: none !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        color: #000000 !important;
        overflow: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    #printable-a4-doc .text-\[\#0e6a38\] {
        color: #0e6a38 !important;
    }
    
    .a4-print-page {
        position: relative !important;
        width: 210mm !important;
        height: 297mm !important;
        min-width: 210mm !important;
        min-height: 297mm !important;
        max-width: 210mm !important;
        max-height: 297mm !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        display: block !important;
        box-sizing: border-box !important;
        background: #ffffff !important;
        background-color: #ffffff !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        page-break-after: auto !important;
        break-after: auto !important;
        border: none !important;
        box-shadow: none !important;
    }
    .a4-print-page[data-orientation="landscape"] {
        width: 297mm !important;
        height: 210mm !important;
        min-width: 297mm !important;
        min-height: 210mm !important;
        max-width: 297mm !important;
        max-height: 210mm !important;
    }
    .a4-print-page:last-child {
        page-break-after: auto !important;
        break-after: auto !important;
    }
    
    [data-print-image] {
        position: absolute !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        background: #ffffff !important;
        background-color: #ffffff !important;
        border: 0.3mm solid #e5e7eb !important;
        border-radius: 1.5mm !important;
        display: block !important;
        
    }
    
    [data-print-image] img {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100% !important;
        max-width: 100% !important;
        max-height: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
        object-fit: contain !important;
        object-position: center !important;
        background: #ffffff !important;
        border: none !important;
        border-radius: 0 !important;
        display: block !important;
        transform: none !important;
        aspect-ratio: auto !important;
        image-rendering: -webkit-optimize-contrast !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    #printable-a4-doc .print-images-grid,
    #printable-a4-doc .print-attachments-grid,
    #printable-a4-doc .print-image-container,
    #printable-a4-doc .print-image-wrapper,
    #printable-a4-doc .video-frame-wrapper {
        display: none !important; 
    }
    
    .print-image-container,
    .print-images-grid,
    .print-attachments-grid {
        display: none !important;
    }
}

@media screen {
    .print-image-container,
    .print-images-grid,
    .print-attachments-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)) !important;
        gap: 12px !important;
        height: auto !important;
        min-height: 480px !important;
        max-height: none !important;
        margin-bottom: 12px !important;
        width: 100% !important;
        align-items: stretch !important;
    }
}
.print-image,
.print-image-wrapper img,
.video-frame-wrapper img {
    
    image-rendering: high-quality !important;
}
.video-frame-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.video-frame-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(14, 106, 56, 0.9);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    z-index: 10;
    pointer-events: none;
}
</style>

@push('scripts')
<script>
console.log("🔥 print_modal loaded — PrintLayoutEngine:", typeof window.PrintLayoutEngine, typeof PrintLayoutEngine);
let currentPrintNote = null;
let currentAttachmentsState = [];
let preloadedAssetsCache = new Map();
// Locale-aware print labels (server-rendered per current locale — no Gemini, no re-translate).
const PRINT_T = {
    locale: @json(app()->getLocale() === 'en' ? 'en' : 'ar'),
    dir: @json(app()->getLocale() === 'en' ? 'ltr' : 'rtl'),
    ministry: @json(__('ui.print_ministry')),
    ministrySub: @json(__('ui.print_ministry_sub')),
    date: @json(__('ui.report_date_label')),
    start: @json(__('ui.print_start')),
    end: @json(__('ui.print_end')),
    cameraNo: @json(__('ui.cam_no')),
    floorNo: @json(__('ui.floor_no')),
    description: @json(__('ui.description')),
    descriptionCont: @json(__('ui.print_description_cont')),
    observer: @json(__('ui.observer')),
    videoFrame: @json(__('ui.print_video_frame')),
    noAttachments: @json(__('ui.print_no_attachments')),
    openFailed: @json(__('ui.print_open_failed')),
    video: @json(__('ui.video')),
    photoBadge: @json(__('ui.photo_badge')),
    selectedOf: @json(__('ui.print_selected_of')),
    previewA4: @json(__('ui.print_preview_a4')),
    analyzing: @json(__('ui.print_analyzing')),
    openPreviewFirst: @json(__('ui.print_open_preview_first')),
    officialCount: @json(__('ui.print_official_count'))
};
function printPageLabel(a, b){
    return (PRINT_T.locale === 'en' ? ('Page ' + a + ' of ' + b) : ('صفحة ' + a + ' من ' + b));
}

function openPrintModal(noteData) {
    currentPrintNote = noteData;
    const modal = document.getElementById('print-selection-modal');
    const listEl = document.getElementById('print-attachments-list');
    const noAttachMsg = document.getElementById('print-no-attachments-msg');
    const attachments = noteData.attachments || [];
    currentAttachmentsState = attachments.map((att, idx) => ({
        id: att.id,
        name: att.name || att.original_name,
        mime: att.mime || att.mime_type,
        url: att.url,
        file_size: att.file_size,
        selected: (idx === 0)
    }));
    if (currentAttachmentsState.length === 0) {
        listEl.innerHTML = '';
        noAttachMsg.classList.remove('hidden');
    } else {
        noAttachMsg.classList.add('hidden');
        renderAttachmentsSelector();
    }
    updatePrintSelectedBadge();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closePrintSelectionModal() {
    document.getElementById('print-selection-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function decodePrintPayload(b64) {
    const bin = window.atob(String(b64).trim());
    const bytes = Uint8Array.from(bin, (c) => c.charCodeAt(0));
    return JSON.parse(new TextDecoder('utf-8').decode(bytes));
}
function openPrintModalFromButton(btn) {
    const nid = (btn && btn.getAttribute && btn.getAttribute('data-print-note-id')) || '?';
    let data = null, how = '';
    const b64 = btn ? btn.getAttribute('data-print-b64') : null;
    if (b64) {
        try { data = decodePrintPayload(b64); how = 'b64'; }
        catch (e) { console.warn('[PRINT BTN] b64 payload decode failed for note #' + nid + ' — trying legacy', e); }
    } else {
        console.warn('[PRINT BTN] no data-print-b64 for note #' + nid + ' — trying legacy');
    }
    if (!data) {
        const legacy = btn ? btn.getAttribute('data-print-note') : null;
        if (legacy) {
            try { data = JSON.parse(legacy); how = 'legacy'; }
            catch (e) { console.error('[PRINT BTN] legacy JSON.parse failed for note #' + nid, e, 'raw head:', String(legacy).slice(0, 300)); }
        } else {
            console.warn('[PRINT BTN] no legacy data-print-note for note #' + nid);
        }
    }
    if (!data || typeof data.id === 'undefined') {
        
        const missingField = !data ? 'payload(BOTH b64+legacy unparseable)' : 'note.id';
        console.error('[PRINT VALIDATION FAILED]', {
            missingField: missingField,
            noteIdAttr: nid,
            payloadSource: how || 'none',
            noteKeys: data ? Object.keys(data) : [],
            attachments: data ? data.attachments : undefined,
            attachmentCount: data && data.attachments ? data.attachments.length : -1,
            descriptionType: data ? typeof data.description : 'n/a'
        });
        window.toast(PRINT_T.openFailed);
        return;
    }
    
    console.log('[PRINT] raw note data:', data);
    console.log('[PRINT] note keys:', Object.keys(data || {}));
    console.log('[PRINT] attachments:', data.attachments);
    console.log('[PRINT] attachment count:', data.attachments ? data.attachments.length : 0);
    console.log('[PRINT] payload:', { source: how, id: data.id });
    console.log('[PRINT CHECK]', {
        loaded: window.__PRINT_SCRIPT_LOADED__,
        engineVersion: window.__PRINT_LAYOUT_ENGINE_VERSION__,
        engineAvailable: typeof window.PrintLayoutEngine
    });
    console.log('[PRINT BTN] opening print for note #' + data.id + ' via ' + how);
    openPrintModal(data);
}
function closePrintPreviewModal() {
    document.getElementById('print-preview-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
function backToSelectionModal() {
    document.getElementById('print-preview-modal').classList.add('hidden');
    document.getElementById('print-selection-modal').classList.remove('hidden');
}
function renderAttachmentsSelector() {
    const listEl = document.getElementById('print-attachments-list');
    let html = '';
    currentAttachmentsState.forEach((item, index) => {
        const isVideo = item.mime && item.mime.includes('video');
        const isImage = item.mime && item.mime.includes('image');
        const imgWord = String(PRINT_T.photoBadge || '').replace(/^—\s*/, '') || @json(__('ui.image_single'));
        const badgeIcon = isVideo
            ? `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-bold border border-amber-200"><svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>${escPrint(PRINT_T.video)}</span>`
            : `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-[#eef4f0] text-[#0e6a38] text-[10px] font-bold border border-[#cde7d6]"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${escPrint(imgWord)}</span>`;
        html += `
            <div onclick="toggleAttachmentSelection(${index})" class="relative flex items-center gap-3 p-3 rounded-xl border transition cursor-pointer select-none ${item.selected ? 'border-[#0e6a38] bg-[#eef4f0]/40 shadow-sm' : 'border-[#e6e9e1] bg-white hover:border-[#c2cbc1]'}">
                <input type="checkbox" id="print-cb-${index}" ${item.selected ? 'checked' : ''} onclick="event.stopPropagation(); toggleAttachmentSelection(${index});" class="rounded border-[#c2cbc1] text-[#0e6a38] focus:ring-[#0e6a38]/20 w-4 h-4 shrink-0">
                <div class="w-14 h-14 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] overflow-hidden flex items-center justify-center shrink-0">
                    ${isImage ? `<img src="${escPrint(item.url)}" alt="${escPrint(item.name)}" class="w-full h-full object-cover">` : ''}
                    ${isVideo ? `<div class="flex flex-col items-center justify-center text-red-500"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg><span class="text-[9px] font-bold text-[#525252]">HD Video</span></div>` : ''}
                </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-ink-800 truncate" title="${escPrint(item.name)}">${escPrint(item.name)}</div>
                    <div class="mt-1 flex items-center gap-2">
                        ${badgeIcon}
                        <span class="text-[10px] text-[#737373]">${item.file_size ? (item.file_size / 1024).toFixed(0) + ' KB' : ''}</span>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}
function toggleAttachmentSelection(index) {
    if (currentAttachmentsState[index]) {
        currentAttachmentsState[index].selected = !currentAttachmentsState[index].selected;
        renderAttachmentsSelector();
        updatePrintSelectedBadge();
    }
}
function printSelectAll(select) {
    currentAttachmentsState.forEach(item => item.selected = select);
    renderAttachmentsSelector();
    updatePrintSelectedBadge();
}
function updatePrintSelectedBadge() {
    const count = currentAttachmentsState.filter(item => item.selected).length;
    const total = currentAttachmentsState.length;
    const badge = document.getElementById('print-selected-count-badge');
    if (badge) badge.textContent = String(PRINT_T.selectedOf || '').split(':count').join(count).split(':total').join(total);
}
async function extractNativeVideoFrame(url) {
    if (preloadedAssetsCache.has(url)) return preloadedAssetsCache.get(url);
    return new Promise((resolve) => {
        const video = document.createElement('video');
        video.preload = 'auto';
        video.muted = true;
        video.playsInline = true;
        video.src = url;
        let completed = false;
        const fallbackTimeout = setTimeout(() => {
            if (!completed) { completed = true; const res = { src: url, isVideo: true, width: 1920, height: 1080, aspect: 16/9 }; preloadedAssetsCache.set(url, res); resolve(res); }
        }, 10000);
        video.onloadedmetadata = () => { video.currentTime = Math.min(0.08, (video.duration && video.duration > 0.1) ? 0.08 : 0); };
        video.onseeked = () => {
            if (completed) return; completed = true; clearTimeout(fallbackTimeout);
            try {
                const nativeW = video.videoWidth || 1920;
                const nativeH = video.videoHeight || 1080;
                const canvas = document.createElement('canvas');
                canvas.width = nativeW; canvas.height = nativeH;
                const dpr = Math.min(window.devicePixelRatio || 1, 3);
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                
                if (dpr > 1) {
                    canvas.width = nativeW * dpr;
                    canvas.height = nativeH * dpr;
                    ctx.scale(dpr, dpr);
                }
                ctx.drawImage(video, 0, 0, nativeW, nativeH);
                const frameDataUrl = canvas.toDataURL('image/png', 1.0);
                const result = { src: frameDataUrl, isVideo: true, width: nativeW, height: nativeH, aspect: nativeW / nativeH };
                preloadedAssetsCache.set(url, result); resolve(result);
            } catch (e) {
                const result = { src: url, isVideo: true, width: 1920, height: 1080, aspect: 16/9 }; preloadedAssetsCache.set(url, result); resolve(result);
            }
        };
        video.onerror = () => { if (!completed) { completed = true; clearTimeout(fallbackTimeout); const result = { src: url, isVideo: true, width: 1280, height: 720, aspect: 16/9 }; preloadedAssetsCache.set(url, result); resolve(result); } };
    });
}
async function preloadNativeImage(url) {
    if (preloadedAssetsCache.has(url)) return preloadedAssetsCache.get(url);
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const w = img.naturalWidth || 1280; const h = img.naturalHeight || 720;
            const result = { src: url, isVideo: false, width: w, height: h, aspect: w / h };
            preloadedAssetsCache.set(url, result); resolve(result);
        };
        img.onerror = () => { const result = { src: url, isVideo: false, width: 1280, height: 720, aspect: 16/9 }; preloadedAssetsCache.set(url, result); resolve(result); };
        img.src = url;
    });
}
function solveOptimalLayout(items) {
    
    
    console.error('[DEPRECATED] solveOptimalLayout() called — PIPELINE FIX §7: ONE RENDERER ONLY is PrintLayoutEngine. This Grid solver is disabled.');
    throw new Error('DEPRECATED solveOptimalLayout() — PIPELINE FIX §6/§7: Engine geometry is single source of truth, Grid fallback disabled.');
}
function partitionItemsIntoPages(items) {
    if (items.length <= 4) return [items];
    if (items.length === 5) return [items];
    const pages = []; const maxPerPage = 4;
    for (let i = 0; i < items.length; i += maxPerPage) pages.push(items.slice(i, i + maxPerPage));
    return pages;
}

let _printEngine = null;
function getPrintEngine(){
    if(_printEngine) return _printEngine;
    const Cls = (typeof PrintLayoutEngine !== 'undefined' ? PrintLayoutEngine : (typeof window !== 'undefined' && window.PrintLayoutEngine ? window.PrintLayoutEngine : null));
    if(!Cls){
        console.warn('[PRINT ENGINE] PrintLayoutEngine not found — window.PrintLayoutEngine:', typeof window.PrintLayoutEngine, 'bare:', typeof PrintLayoutEngine);
        return null;
    }
    try{ _printEngine = new Cls(); }catch(e){ console.error(e); return null; }
    return _printEngine;
}

function renderLayout(layout, container){
    console.error('[DEPRECATED] renderLayout() is dead code — use buildA4PageHtml() only. Engine geometry is single source of truth.');
    throw new Error('DEPRECATED renderLayout() called — PIPELINE FIX §7: ONE RENDERER ONLY is buildA4PageHtml(). This renderer is disabled.');
}
function assertPrintGeometry(layout, container){
    
    const pxPerMm = 3.779527559; 
    const errors=[];
    const pageEl = container.querySelector('.a4-print-page');
    if(pageEl){
        const r = pageEl.getBoundingClientRect();
        const wMm = r.width / pxPerMm, hMm = r.height / pxPerMm;
        if(Math.abs(wMm - layout.paper.width) > 2) errors.push(`page width ${wMm.toFixed(1)}mm != ${layout.paper.width}mm`);
        if(Math.abs(hMm - layout.paper.height) > 2) errors.push(`page height ${hMm.toFixed(1)}mm != ${layout.paper.height}mm`);
    }
    layout.images.forEach((im,i)=>{
        const el = container.querySelectorAll('[data-print-image]')[i] || container.children[i];
        if(!el) return;
        const r = el.getBoundingClientRect();
        const wMm = r.width / pxPerMm, hMm = r.height / pxPerMm;
        if(Math.abs(wMm - im.width) > 1.5) errors.push(`img ${im.id} width ${wMm.toFixed(1)} != ${im.width}`);
    });
    const ok = errors.length===0;
    console.log('[Assert]', ok ? 'PASS' : 'FAIL', {engine:layout, errors});
    return {valid: ok, errors, engine:layout, rendered: container.innerHTML.slice(0,120)};
}
function renderAbsoluteImages(pageItems, imageArea){
    
    console.error('[DEPRECATED] renderAbsoluteImages() called — PIPELINE FIX §6: No silent fallback allowed. Engine error should be visible.');
    throw new Error('DEPRECATED renderAbsoluteImages() — PIPELINE FIX §6/§7: Engine must succeed, no Grid fallback. Fix the Engine error instead.');
}

function assertBeforePrint(printableDoc){
    const errors = [];
    const pxPerMm = 3.779527559;
    const pages = printableDoc.querySelectorAll('.a4-print-page');
    if(!pages.length) {
        errors.push('PRINT PAGE NOT FOUND: no .a4-print-page inside #printable-a4-doc');
        return { valid:false, errors };
    }
    console.log('[PRINT ASSERTION] Checking', pages.length, 'page(s)');
    
    let probedPages = pages;
    let probeContainer = null;
    const firstRect = pages[0].getBoundingClientRect();
    if(firstRect.width < 10){
        console.warn('[PRINT ASSERTION] printableDoc is hidden (rect 0), cloning offscreen for measurement');
        probeContainer = document.createElement('div');
        probeContainer.style.position = 'fixed';
        probeContainer.style.left = '-10000px';
        probeContainer.style.top = '0';
        probeContainer.style.visibility = 'hidden';
        probeContainer.style.pointerEvents = 'none';
        
        probeContainer.innerHTML = printableDoc.innerHTML;
        document.body.appendChild(probeContainer);
        probedPages = probeContainer.querySelectorAll('.a4-print-page');
    }
    probedPages.forEach((page, pi)=>{
        const rect = page.getBoundingClientRect();
        const wMm = rect.width / pxPerMm;
        const hMm = rect.height / pxPerMm;
        const orient = page.getAttribute('data-orientation') || 'portrait';
        const expectedW = orient==='landscape' ? 297 : 210;
        const expectedH = orient==='landscape' ? 210 : 297;
        console.log(`[PRINT PAGE RECT] page ${pi} orientation=${orient}`, { widthPx: Math.round(rect.width), heightPx: Math.round(rect.height), widthMm: wMm.toFixed(2), heightMm: hMm.toFixed(2), expected: `${expectedW}×${expectedH}mm` });
        
        if(rect.width >= 10){
            if(Math.abs(wMm - expectedW) > 3) errors.push(`Page ${pi} width ${wMm.toFixed(1)}mm != expected ${expectedW}mm (${orient})`);
            if(Math.abs(hMm - expectedH) > 3) errors.push(`Page ${pi} height ${hMm.toFixed(1)}mm != expected ${expectedH}mm (${orient})`);
        } else {
            console.warn(`[PRINT ASSERTION] page ${pi} rect still 0, skipping size check — checking data attributes only`);
        }
        
        const imgs = page.querySelectorAll('[data-print-image]');
        const pageW = expectedW;
        const pageH = expectedH;
        imgs.forEach((el, ii)=>{
            const x = parseFloat(el.getAttribute('data-engine-x'));
            const y = parseFloat(el.getAttribute('data-engine-y'));
            const w = parseFloat(el.getAttribute('data-engine-w'));
            const h = parseFloat(el.getAttribute('data-engine-h'));
            const styleLeft = el.style.left;
            const styleTop = el.style.top;
            const styleW = el.style.width;
            const styleH = el.style.height;
            
            if(!styleLeft || !styleLeft.includes('mm')) errors.push(`Page ${pi} img ${ii} left not in mm: ${styleLeft}`);
            if(!styleW || !styleW.includes('mm')) errors.push(`Page ${pi} img ${ii} width not in mm: ${styleW}`);
            
            if(isNaN(x) || isNaN(y) || isNaN(w) || isNaN(h)) {
                errors.push(`Page ${pi} img ${ii} has NaN geometry: x=${x} y=${y} w=${w} h=${h}`);
                return;
            }
            if(w <= 0 || h <= 0) errors.push(`Page ${pi} img ${ii} invalid dims w=${w} h=${h}`);
            if(x < -0.5) errors.push(`Page ${pi} img ${ii} x<0: ${x}`);
            if(y < -0.5) errors.push(`Page ${pi} img ${ii} y<0: ${y}`);
            if(x + w > pageW + 0.5) errors.push(`Page ${pi} img ${ii} overflow: x(${x})+w(${w})=${(x+w).toFixed(1)} > pageW ${pageW}`);
            if(y + h > pageH + 0.5) errors.push(`Page ${pi} img ${ii} overflow: y(${y})+h(${h})=${(y+h).toFixed(1)} > pageH ${pageH}`);
            
            const r = el.getBoundingClientRect();
            if(r.width >= 5){
                const cw = r.width / pxPerMm;
                const ch = r.height / pxPerMm;
                if(Math.abs(cw - w) > 2) errors.push(`Page ${pi} img ${ii} computed width ${cw.toFixed(1)}mm != engine ${w}mm (diff ${(cw-w).toFixed(1)})`);
                if(Math.abs(ch - h) > 2) errors.push(`Page ${pi} img ${ii} computed height ${ch.toFixed(1)}mm != engine ${h}mm`);
            }
            
            for(let j=0;j<ii;j++){
                const prev = imgs[j];
                const px = parseFloat(prev.getAttribute('data-engine-x'));
                const py = parseFloat(prev.getAttribute('data-engine-y'));
                const pw2 = parseFloat(prev.getAttribute('data-engine-w'));
                const ph2 = parseFloat(prev.getAttribute('data-engine-h'));
                const overlap = !(x + w <= px + 0.5 || px + pw2 <= x + 0.5 || y + h <= py + 0.5 || py + ph2 <= y + 0.5);
                if(overlap) errors.push(`Page ${pi} img ${ii} overlaps with img ${j}`);
            }
            
            const inner = el.querySelector('img');
            if(inner){
                const ics = getComputedStyle(inner);
                if(ics.objectFit !== 'fill' && ics.objectFit !== 'fill ') errors.push(`Page ${pi} img ${ii} inner object-fit is ${ics.objectFit} != fill (CSS override?)`);
                if(ics.aspectRatio && ics.aspectRatio !== 'auto' && ics.aspectRatio !== 'auto ') {
                    if(ics.aspectRatio.includes('4 / 3') || ics.aspectRatio.includes('4/3')) errors.push(`Page ${pi} img ${ii} aspect-ratio is ${ics.aspectRatio} != auto`);
                }
            }
        });
        if(imgs.length===0) console.warn(`Page ${pi} has no [data-print-image] — empty page?`);
    });
    
    probedPages.forEach((page,i)=>{
        const cs = getComputedStyle(page);
        if(cs.display === 'flex' || cs.display === 'grid') errors.push(`Page ${i} display is ${cs.display} != block (Grid/Flex override)`);
        if(cs.overflow === 'hidden') {
            console.warn(`Page ${i} overflow is hidden — should be visible to avoid clipping`);
        }
    });
    if(probeContainer) probeContainer.remove();
    return { valid: errors.length===0, errors, pages: pages.length };
}

function escPrint(s){
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
const DESC_LINE_H_MM = 8.2*0.3528*1.45; 
const DESC_META_MM = 16;    
const DESC_MIN_IMG_MM = 50; 
function descCpl(){
    const printableW = 210 - 6*2;
    const avgCW = 8.2*0.3528*0.56;
    return Math.max(1, Math.floor((printableW-7)/avgCW));
}

function countDescLines(text, cpl){
    if(!text || !String(text).trim()) return 0;
    let lines = 0;
    for(const para of String(text).split('\n')){
        const trimmed = para.trim();
        if(!trimmed){ lines += 1; continue; }
        let cur = 0, paraLines = 1;
        for(const w of trimmed.split(/\s+/)){
            if(w.length >= cpl){ paraLines += Math.max(1, Math.ceil(w.length / cpl)); cur = 0; continue; }
            const need = w.length + 1;
            if(cur + need > cpl){ paraLines++; cur = need; } else cur += need;
        }
        lines += paraLines;
    }
    if(/[A-Za-z0-9]/.test(text)) lines = Math.ceil(lines*1.07);
    return lines;
}

function descBlockHMM(text){
    const lines = countDescLines(text, descCpl());
    if(!lines) return 0;
    return Math.ceil(lines*DESC_LINE_H_MM + 12) + DESC_META_MM;
}

function chunkDescLines(text, cpl, firstCap, contCap){
    const outLines = [];
    for(const para of String(text).split('\n')){
        const trimmed = para.trim();
        if(!trimmed){ outLines.push(''); continue; }
        let cur = '', curLen = 0;
        const pushWord = (w)=>{
            while(w.length >= cpl){ outLines.push(w.slice(0, cpl)); w = w.slice(cpl); }
            const need = w.length + 1;
            if(curLen + need > cpl && cur){ outLines.push(cur); cur = w; curLen = need; }
            else { cur = cur ? cur + ' ' + w : w; curLen += need; }
        };
        for(const w of trimmed.split(/\s+/)){ if(w) pushWord(w); }
        if(cur) outLines.push(cur);
    }
    const chunks = [];
    let idx = 0, cap = Math.max(1, firstCap);
    while(idx < outLines.length){
        chunks.push(outLines.slice(idx, idx + cap).join('\n'));
        idx += cap; cap = Math.max(1, contCap);
    }
    return chunks.length ? chunks : [''];
}
function printHeaderHtml(margin, printableW, headerH){
    const d = (typeof PRINT_T !== 'undefined' && PRINT_T.dir) || 'rtl';
    const align = d === 'rtl' ? 'flex-start' : 'flex-start';
    return `
        <div style="position:absolute; left:${margin}mm; top:${margin}mm; width:${printableW}mm; height:${headerH}mm; border-bottom:0.5mm solid #0e6a38; display:flex; align-items:center; justify-content:${align}; box-sizing:border-box; padding-bottom:1mm; direction:${d};">
            <div style="font-size:2.9mm; font-weight:700; color:#1a2e1f; letter-spacing:0; line-height:1.25;">${escPrint(PRINT_T.ministry)}<span style="font-weight:400; color:#525252;"> — ${escPrint(PRINT_T.ministrySub)}</span></div>
        </div>
    `;
}
function buildA4PageHtml(note, pageItems, pageIndex, totalPages, isLastPage, withDesc = true) {
    console.log(`[RENDERER] buildA4PageHtml called — page ${pageIndex+1}/${totalPages}, items:${pageItems.length}`);
    
    
    const headerH = 10;
    const descM = (()=>{ try{ return getPrintEngine() ? null : null }catch(e){ return null }})();
    
    
    const paperW = 210, paperH = 297, margin = 6;
    const printableW = paperW - margin*2;
    
    const descText = note.description || '';
    
    const effDesc = (withDesc && isLastPage) ? descText : '';
    const avgCW = 8.2*0.3528*0.56;
    const cpl = Math.max(1, Math.floor((printableW-7)/avgCW));
    const lines = descText ? Math.ceil(descText.trim().split(/\s+/).reduce((a,w,i)=>{
        const len=w.length+1;
        if(a.cur+len>cpl){a.lines++; a.cur=len;} else a.cur+=len;
        return a;
    },{lines:1,cur:0}).lines) : 0;
    
    
    let descH = effDesc ? Math.ceil(lines*8.2*0.3528*1.45 + 12) + DESC_META_MM : 0;
    const imageArea = { x: margin, y: margin+headerH+2, w: printableW, h: paperH - margin*2 - headerH - descH - 8 };
    
    if(imageArea.h < 20) imageArea.h = 20;

    const headerHtml = printHeaderHtml(margin, printableW, headerH);
    let imagesHtml = '';
    let __pageLayout = null; 
    if(pageItems.length){
        
        const engineImages = pageItems.map((it,i)=> ({...it, width: it.width||1000, height: it.height||1000 }));
        try{
            const eng = getPrintEngine();
            if(!eng) throw new Error('PrintLayoutEngine not loaded — window.PrintLayoutEngine is undefined (module not loaded or cached)');
            
            
            
            const tmp = eng.layout(engineImages.map((it,i)=>({id:'i'+i,width:it.width,height:it.height})), effDesc, {width:210,height:297}, {margin:6, topOffset: headerH, descOpts:{paddingMm:11.5}});
                __pageLayout = tmp;
                console.log(`[ENGINE] Page ${pageIndex} →`, tmp.metrics.topoName, `coverage:${tmp.metrics.imageCoverageRatio?.toFixed(3)}`);
                console.table(tmp.images.map(x=>({id:x.id, x:x.x.toFixed(1), y:x.y.toFixed(1), w:x.width.toFixed(1), h:x.height.toFixed(1), rot:x.rotation})));
                console.log("FINAL ENGINE LAYOUT", JSON.parse(JSON.stringify(tmp)));
                
                console.table(
                    tmp.images.map((img, i) => ({
                        index: i,
                        x: img.x,
                        y: img.y,
                        w: img.width,
                        h: img.height,
                        rotation: img.rotation
                    }))
                );
                console.log('[ENGINE FINAL LAYOUT]', JSON.parse(JSON.stringify(tmp)));
                
                if(typeof window !== 'undefined' && window.__KILL_TEST_AFTER_LAYOUT__ && tmp.images.length===2){
                    console.warn('🧪 KILL TEST §3 ACTIVE (buildA4PageHtml) — forcing Top/Bottom layout');
                    tmp.images[0] = { ...tmp.images[0], x: 0, y: 0, width: 210, height: 130 };
                    tmp.images[1] = { ...tmp.images[1], x: 0, y: 130, width: 210, height: 167 };
                    console.table(tmp.images.map((img,i)=>({index:i,x:img.x,y:img.y,w:img.width,h:img.height,rotation:img.rotation})));
                }
                
                
                
                window.__LAST_ENGINE_LAYOUT__ = JSON.parse(JSON.stringify(tmp));
                
                if(isLastPage && tmp.description && tmp.description.height > 0){
                    descH = tmp.description.height;
                }
                imagesHtml = tmp.images.map((r,i)=>{
                    const it = pageItems[i];
                    
                    
                    return `<div data-print-image="${r.id}" data-engine-x="${r.x}" data-engine-y="${r.y}" data-engine-w="${r.width}" data-engine-h="${r.height}" style="position:absolute; left:${r.x}mm; top:${r.y}mm; width:${r.width}mm; height:${r.height}mm; overflow:hidden; background:white; border:0.3mm solid #e5e7eb; border-radius:1.5mm; box-sizing:border-box;">
                        <img src="${it.src}" style="position:absolute; left:0; top:0; width:100%; height:100%; object-fit:contain; display:block; background:white;" loading="eager" decoding="sync" />
                        ${it.isVideo?`<div style="position:absolute; bottom:2mm; right:2mm; background:#0e6a38 !important; color:#ffffff !important; font-size:3mm; font-weight:700; padding:1mm 2.5mm; border-radius:1.5mm; border:0.2mm solid white; display:flex; gap:1.2mm; align-items:center; box-shadow:0 0.8mm 2mm rgba(0,0,0,0.35); z-index:10; -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;"><svg width="3.2mm" height="3.2mm" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg><span>${escPrint(PRINT_T.videoFrame)}</span></div>`:''}
                    </div>`;
                }).join('');
                
                if(!isLastPage){
                    
                }
        }catch(e){
            console.error('[PRINT ENGINE ERROR] Engine failed for page', pageIndex, e);
            
            throw new Error('[PRINT ENGINE ERROR] page ' + pageIndex + ': ' + (e.message||e));
        }
    } else {
        imagesHtml = `<div style="position:absolute; left:${imageArea.x}mm; top:${imageArea.y + imageArea.h/2}mm; width:${imageArea.w}mm; text-align:center; color:#737373; font-size:3.5mm;">${escPrint(PRINT_T.noAttachments)}</div>`;
    }
    
    const descY = (__pageLayout && __pageLayout.description && __pageLayout.description.y !== undefined)
        ? __pageLayout.description.y
        : paperH - margin - descH;
    const isN3Page = pageItems.length===3;
    const pDir = (typeof PRINT_T !== 'undefined' && PRINT_T.dir) || 'rtl';
    const pAlign = pDir === 'rtl' ? 'right' : 'left';
    const metadataHtml = effDesc ? `
        <div dir="${pDir}" data-print-description-wrapper style="position:absolute; ${isN3Page ? `left:0; width:100%; padding-left:${margin}mm; padding-right:${margin}mm;` : `left:${margin}mm; width:${printableW}mm;`} top:${descY}mm; min-height:${descH}mm; height:auto; overflow:visible; border-top:0.4mm solid #e6e9e1; padding-top:2mm; box-sizing:border-box; direction:${pDir}; text-align:${pAlign};">
            <div style="display:grid; grid-template-columns: repeat(5,1fr); gap:1.4mm; background:#f6f8f6; border:0.25mm solid #e2e8e2; border-radius:1.2mm; padding:1mm 2mm; font-size:2.3mm; box-sizing:border-box;">
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.date)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_date) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.start)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_time_start) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.end)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_time_end) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.cameraNo)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.camera_number)}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.floorNo)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.floor_number)}</span></div>
            </div>
            <div dir="${pDir}" data-print-description style="margin-top:2mm; background:#fafbfa; border:0.3mm solid #e6e9e1; border-radius:1.5mm; padding:2mm 3mm; font-size:2.8mm; line-height:4.2mm; box-sizing:border-box; direction:${pDir}; text-align:${pAlign};">
                <div style="font-weight:700; color:#0e6a38; font-size:2.8mm; margin-bottom:1mm; text-align:${pAlign}; direction:${pDir};">${escPrint(PRINT_T.description)} :</div>
                <div dir="${pDir}" class="print-description-text" style="color:#000; white-space:pre-wrap; word-break:break-word; overflow-wrap:anywhere; direction:${pDir}; text-align:${pAlign}; unicode-bidi:plaintext;">${escPrint(note.description) || '—'}</div>
            </div>
            <div style="margin-top:1.5mm; font-size:2.2mm; color:#737373; display:flex; justify-content:space-between;">
                <span>${escPrint(PRINT_T.observer)}: <strong style="color:#000;">${escPrint(note.owner_name) || '—'}</strong></span>
                <span>${escPrint(printPageLabel(pageIndex+1, totalPages))}</span>
            </div>
        </div>
    ` : (isLastPage ? `<div style="position:absolute; left:${margin}mm; top:${descY}mm; width:${printableW}mm; font-size:2.2mm; color:#737373;">${escPrint(PRINT_T.observer)}: <strong style="color:#000;">${escPrint(note.owner_name)||'—'}</strong></div>` : '');

    
    const pgW = __pageLayout ? __pageLayout.paper.width : paperW;
    const pgH = __pageLayout ? __pageLayout.paper.height : paperH;
    const pgOrient = __pageLayout ? __pageLayout.paper.orientation : 'portrait';
    
    if(pageItems.length===3){
        const tmpDesc = __pageLayout?.description;
        console.log('[N3 TRACE][ENGINE]', {x: tmpDesc?.x, y: tmpDesc?.y, w: tmpDesc?.w, h: tmpDesc?.h, measuredHeight: tmpDesc?.height, engineDesc: tmpDesc, imageArea, paper:{w:paperW,h:paperH}, innerW: printableW, margin, headerH});
        console.log('[N3 TRACE][RENDERER] descH fallback', (descText ? Math.ceil((descText.trim().split(/\s+/).reduce((a,w,i)=>{const cpl=Math.max(1,Math.floor((printableW-7)/(8.2*0.3528*0.56))); const len=w.length+1; if(a.cur+len>cpl){a.lines++; a.cur=len;} else a.cur+=len; return a;},{lines:1,cur:0}).lines*8.2*0.3528*1.45 +12)):0), 'engineDescH', __pageLayout?.description?.height, 'finalDescH', descH, 'finalDescY', descY, 'printableW', printableW);
        console.log('[N3 TRACE][RENDERER HTML] wrapper will be left:'+margin+'mm top:'+descY+'mm width:'+printableW+'mm height:'+descH+'mm — metadataHtml snippet', metadataHtml.slice(0,600));
        
        console.log('[N3 TRACE][GEOMETRY CHECK]', {
            imageArea: {x: imageArea.x, y: imageArea.y, w: imageArea.w, h: imageArea.h, bottom: imageArea.y + imageArea.h},
            description: {x: tmpDesc?.x, y: tmpDesc?.y, w: tmpDesc?.w, h: tmpDesc?.h, bottom: (tmpDesc?.y||0)+(tmpDesc?.h||0)},
            gap: (tmpDesc?.y||0) - (imageArea.y + imageArea.h),
            isFullWidth: tmpDesc?.w === printableW && tmpDesc?.x === margin,
            isBelow: (tmpDesc?.y||0) >= imageArea.y + imageArea.h,
            assertion: {
                xMatch: tmpDesc?.x === imageArea.x && tmpDesc?.x === margin,
                wMatch: tmpDesc?.w === imageArea.w && tmpDesc?.w === printableW,
                yBelow: (tmpDesc?.y||0) >= imageArea.y + imageArea.h,
                fullWidth: Math.abs((tmpDesc?.w||0) - printableW) < 1
            }
        });
    }
    
    const pageDir = (typeof PRINT_T !== 'undefined' && PRINT_T.dir) || 'rtl';
    return `<div class="a4-print-page" dir="${pageDir}" data-orientation="${pgOrient}" style="width:${pgW}mm; height:${pgH}mm; position:relative; background:white; overflow:visible; box-sizing:border-box; direction:${pageDir};">${headerHtml}${imagesHtml}${metadataHtml}</div>`;
}

function buildDescPageHtml(note, chunkText, pageIndex, totalPages, isFirst){
    const margin = 6, headerH = 10, paperW = 210, paperH = 297;
    const printableW = paperW - margin*2;
    const dDir = (typeof PRINT_T !== 'undefined' && PRINT_T.dir) || 'rtl';
    const dAlign = dDir === 'rtl' ? 'right' : 'left';
    const gridHtml = isFirst ? `
            <div style="display:grid; grid-template-columns: repeat(5,1fr); gap:1.4mm; background:#f6f8f6; border:0.25mm solid #e2e8e2; border-radius:1.2mm; padding:1mm 2mm; font-size:2.3mm; box-sizing:border-box; margin-bottom:2mm;">
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.date)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_date) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.start)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_time_start) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.end)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.observed_time_end) || '—'}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.cameraNo)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.camera_number)}</span></div>
                <div><span style="font-weight:700; color:#737373; font-size:1.9mm; display:block; line-height:1.2;">${escPrint(PRINT_T.floorNo)} :</span><span style="font-weight:800; color:#000; font-size:2.3mm;">${escPrint(note.floor_number)}</span></div>
            </div>` : '';
    return `<div class="a4-print-page" dir="${dDir}" data-orientation="portrait" data-desc-page="1" style="width:${paperW}mm; height:${paperH}mm; position:relative; background:white; overflow:visible; box-sizing:border-box; direction:${dDir};">${printHeaderHtml(margin, printableW, headerH)}
        <div dir="${dDir}" data-print-description-wrapper style="position:absolute; left:${margin}mm; top:${margin+headerH+2}mm; width:${printableW}mm; height:auto; overflow:visible; border-top:0.4mm solid #e6e9e1; padding-top:2mm; box-sizing:border-box; direction:${dDir}; text-align:${dAlign};">
            ${gridHtml}
            <div dir="${dDir}" data-print-description style="background:#fafbfa; border:0.3mm solid #e6e9e1; border-radius:1.5mm; padding:2mm 3mm; font-size:2.8mm; line-height:4.2mm; box-sizing:border-box; direction:${dDir}; text-align:${dAlign};">
                <div style="font-weight:700; color:#0e6a38; font-size:2.8mm; margin-bottom:1mm; text-align:${dAlign}; direction:${dDir};">${escPrint(isFirst ? PRINT_T.description : PRINT_T.descriptionCont)} :</div>
                <div dir="${dDir}" class="print-description-text" style="color:#000; white-space:pre-wrap; word-break:break-word; overflow-wrap:anywhere; direction:${dDir}; text-align:${dAlign}; unicode-bidi:plaintext;">${escPrint(chunkText) || '—'}</div>
            </div>
            <div style="margin-top:1.5mm; font-size:2.2mm; color:#737373; display:flex; justify-content:space-between;">
                <span>${escPrint(PRINT_T.observer)}: <strong style="color:#000;">${escPrint(note.owner_name) || '—'}</strong></span>
                <span>${escPrint(printPageLabel(pageIndex+1, totalPages))}</span>
            </div>
        </div>
    </div>`;
}
async function generateAndOpenPreview() {
    const proceedBtn = document.getElementById('print-proceed-preview-btn');
    const spinner = document.getElementById('print-proceed-spinner');
    const icon = document.getElementById('print-proceed-icon');
    const text = document.getElementById('print-proceed-text');
    proceedBtn.disabled = true; spinner.classList.remove('hidden'); icon.classList.add('hidden'); text.textContent = PRINT_T.analyzing;
    try {
        const selectedItems = currentAttachmentsState.filter(item => item.selected);
        
        const resolvedAssets = await Promise.all(selectedItems.map(item =>
            item.mime && item.mime.includes('video') ? extractNativeVideoFrame(item.url) : preloadNativeImage(item.url)
        ));
        const pages = partitionItemsIntoPages(resolvedAssets);
        
        const dText = (currentPrintNote && currentPrintNote.description) || '';
        const innerH = 297 - 6*2 - 10; 
        const dBlockH = descBlockHMM(dText);
        const lastCount = pages.length > 0 ? pages[pages.length-1].length : 0;
        
        
        const capRatio = lastCount === 4 ? 0.35 : 0.40;
        let needOwnPage = false;
        if (dText) {
            if (lastCount === 3) needOwnPage = dBlockH + DESC_MIN_IMG_MM > innerH;
            else needOwnPage = dBlockH > innerH * capRatio;
        }
        let descChunks = null;
        if (needOwnPage) {
            
            const firstCap = Math.floor(((innerH - 10 - 10 - 8) / DESC_LINE_H_MM) * 0.85);
            const contCap = Math.floor(((innerH - 10 - 8) / DESC_LINE_H_MM) * 0.85);
            descChunks = chunkDescLines(dText, descCpl(), firstCap, contCap);
            console.log('[SMART DESC] desc block', dBlockH.toFixed(1) + 'mm exceeds engine reserve — dedicated page(s):', descChunks.length);
        } else if (dText) {
            console.log('[SMART DESC] desc block', dBlockH.toFixed(1) + 'mm fits in engine reserve');
        }
        
        const skipEmptyPage = !!(descChunks && pages.length === 1 && pages[0].length === 0);
        const imgPageCount = skipEmptyPage ? 0 : pages.length;
        const totalPages = imgPageCount + (descChunks ? descChunks.length : 0);
        const previewContainer = document.getElementById('print-preview-pages-container');
        const printableDoc = document.getElementById('printable-a4-doc'); 
        const legacyPrintDoc = document.getElementById('rasd-print-document'); 
        let previewHtml = ''; let printDocHtml = '';
        pages.forEach((pageItems, pIdx) => {
            if (skipEmptyPage) return;
            
            const isLastImagePage = (pIdx === pages.length - 1);
            const pageHtml = buildA4PageHtml(currentPrintNote, pageItems, pIdx, totalPages, isLastImagePage, !descChunks);
            
            const orientation = pageHtml.includes('data-orientation="landscape"') ? 'landscape' : 'portrait';
            previewHtml += `<div class="a4-preview-sheet" data-orientation="${orientation}">${pageHtml}</div>`;
            printDocHtml += pageHtml;
        });
        if (descChunks) {
            descChunks.forEach((chunk, ci) => {
                const pIdx = imgPageCount + ci;
                const descPageHtml = buildDescPageHtml(currentPrintNote, chunk, pIdx, totalPages, ci === 0);
                previewHtml += `<div class="a4-preview-sheet" data-orientation="portrait">${descPageHtml}</div>`;
                printDocHtml += descPageHtml;
            });
        }
        previewContainer.innerHTML = previewHtml;
        
        if (printableDoc) printableDoc.innerHTML = printDocHtml;
        if (legacyPrintDoc) { legacyPrintDoc.innerHTML = printDocHtml; console.warn('[DEPRECATED] #rasd-print-document filled for legacy compat — not used for print'); }
        
        if(pages.length===1 && pages[0].length===3){
            setTimeout(()=>{
                const descWrapper = document.querySelector('[data-print-description-wrapper]');
                const descInner = document.querySelector('[data-print-description]');
                const descTextEl = document.querySelector('.print-description-text');
                const pageEl = document.querySelector('.a4-print-page');
                const heroEl = document.querySelector('[data-print-image]');
                console.log('[N3 TRACE][DOM] page rect', pageEl?.getBoundingClientRect(), 'computed display', pageEl ? getComputedStyle(pageEl).display : null, 'position', pageEl ? getComputedStyle(pageEl).position : null);
                console.log('[N3 TRACE][DOM] hero first image rect', heroEl?.getBoundingClientRect(), 'hero computed', heroEl ? {display:getComputedStyle(heroEl).display, position:getComputedStyle(heroEl).position, left:getComputedStyle(heroEl).left, top:getComputedStyle(heroEl).top, width:getComputedStyle(heroEl).width, height:getComputedStyle(heroEl).height} : null);
                [descWrapper, descInner, descTextEl].forEach((el, idx)=>{
                    if(!el) { console.log(`[N3 TRACE][DOM ${idx}] element not found`); return; }
                    const rect = el.getBoundingClientRect();
                    const cs = getComputedStyle(el);
                    const parentRect = el.parentElement?.getBoundingClientRect();
                    console.log(`[N3 TRACE][DOM ${['wrapper','inner','text'][idx]}]`, {
                        html: el.outerHTML.slice(0,500),
                        rect: {w: rect.width, h: rect.height, top: rect.top, left: rect.left},
                        parentRect: parentRect ? {w: parentRect.width, h: parentRect.height, top: parentRect.top, left: parentRect.left} : null,
                        scrollHeight: el.scrollHeight,
                        clientHeight: el.clientHeight,
                        scrollWidth: el.scrollWidth,
                        clientWidth: el.clientWidth,
                        isInsideHero: !!el.closest('[data-print-image]'),
                        isDirectChildOfPage: el.parentElement?.classList?.contains('a4-print-page'),
                        computed: {
                            width: cs.width,
                            height: cs.height,
                            maxHeight: cs.maxHeight,
                            minHeight: cs.minHeight,
                            overflow: cs.overflow,
                            overflowY: cs.overflowY,
                            display: cs.display,
                            position: cs.position,
                            left: cs.left,
                            top: cs.top,
                            flex: cs.flex,
                            grid: cs.gridTemplateColumns,
                            gridRows: cs.gridTemplateRows
                        }
                    });
                    if(el.scrollHeight > el.clientHeight + 2) console.warn(`[N3 TRACE][CLIPPING] ${['wrapper','inner','text'][idx]} scrollHeight ${el.scrollHeight} > clientHeight ${el.clientHeight} diff ${el.scrollHeight - el.clientHeight} — CLIPPED`);
                });
                
                let parent = descWrapper?.parentElement;
                let level=0;
                while(parent && level<5){
                    const cs = getComputedStyle(parent);
                    const rect = parent.getBoundingClientRect();
                    console.log(`[N3 TRACE][PARENT ${level}]`, {
                        tag: parent.tagName,
                        classes: parent.className?.slice(0,120),
                        id: parent.id,
                        rect: {w: rect.width, h: rect.height, top: rect.top, left: rect.left},
                        computed: {
                            width: cs.width,
                            height: cs.height,
                            maxHeight: cs.maxHeight,
                            minHeight: cs.minHeight,
                            overflow: cs.overflow,
                            overflowY: cs.overflowY,
                            display: cs.display,
                            position: cs.position,
                            gridTemplateColumns: cs.gridTemplateColumns,
                            gridTemplateRows: cs.gridTemplateRows
                        }
                    });
                    if(cs.overflow === 'hidden' || cs.overflowY === 'hidden') console.warn(`[N3 TRACE][PARENT ${level}] HAS overflow:hidden — may clip description`);
                    if(cs.display === 'grid' || cs.display === 'flex') console.log(`[N3 TRACE][PARENT ${level}] is ${cs.display} — check if description is inside grid/flex that constrains it`);
                    parent = parent.parentElement;
                    level++;
                }
                
                if(descWrapper){
                    const isInsideImageArea = !!descWrapper.closest('.print-images-grid') || !!descWrapper.closest('.print-attachments-grid') || !!descWrapper.closest('[data-print-image]');
                    console.log('[N3 TRACE][HIERARCHY] is description inside image grid/hero?', isInsideImageArea, 'parent:', descWrapper.parentElement?.tagName, descWrapper.parentElement?.className?.slice(0,80));
                    const inlineH = descWrapper.getAttribute('style')?.match(/height:\s*([^;]+)/)?.[1];
                    const computedH = getComputedStyle(descWrapper).height;
                    console.log('[N3 TRACE][INLINE vs COMPUTED] inline height', inlineH, 'computed height', computedH, 'inline contains descH?', inlineH?.includes('mm'), 'computed px', computedH);
                    
                    const pageWpx = pageEl?.getBoundingClientRect().width;
                    const wrapperWpx = descWrapper.getBoundingClientRect().width;
                    const heroWpx = heroEl?.getBoundingClientRect().width;
                    console.log('[N3 TRACE][WIDTH CHECK] pageW', pageWpx, 'wrapperW', wrapperWpx, 'heroW', heroWpx, 'wrapper is full width?', Math.abs(wrapperWpx - pageWpx*0.94) < 20, 'wrapper == hero width?', Math.abs(wrapperWpx - heroWpx) < 5);
                }
            }, 700);
        }
        
        
        console.log('[PRINT ROOTS]', document.querySelectorAll('.a4-print-page').length, document.querySelectorAll('.print-image').length, document.querySelectorAll('#printable-a4-doc').length, document.querySelectorAll('#rasd-print-document').length);
        console.log('[PRINT ROOTS DETAIL]', {
            a4PrintPage: document.querySelectorAll('.a4-print-page').length,
            printImage: document.querySelectorAll('.print-image').length,
            printableDoc: document.querySelectorAll('#printable-a4-doc').length,
            rasdPrintDoc: document.querySelectorAll('#rasd-print-document').length,
            previewSheets: document.querySelectorAll('.a4-preview-sheet').length,
            previewImgs: previewContainer.querySelectorAll('img').length,
            printableImgs: printableDoc ? printableDoc.querySelectorAll('img').length : 0
        });
        
        console.log('[VERSION CHECK] window.__PRINT_LAYOUT_ENGINE_VERSION__ =', window.__PRINT_LAYOUT_ENGINE_VERSION__);
        
        setTimeout(()=>{
            console.log(`[RENDERER] DOM after render — preview imgs: ${previewContainer.querySelectorAll('img').length}, printable imgs: ${printableDoc?.querySelectorAll('img').length||0}`);
            const firstAbs = previewContainer.querySelector('[style*="position:absolute"]');
            if(firstAbs){
                console.log('[DOM STYLE] first absolute:', firstAbs.getAttribute('style')?.slice(0,250));
                const cs = getComputedStyle(firstAbs);
                console.table({position:cs.position, display:cs.display, left:cs.left, top:cs.top, width:cs.width, height:cs.height, transform:cs.transform});
            }
            console.log('[CHECK] engine.layout calls:', (previewHtml.match(/a4-print-page/g)||[]).length, 'printable pages:', (printDocHtml.match(/a4-print-page/g)||[]).length);
            
            try{ window.debugPrintGeometry(); }catch(e){ console.warn('debugPrintGeometry failed', e); }
        }, 200);
        document.getElementById('print-preview-pages-count').textContent = String(PRINT_T.officialCount || '').split(':n').join(totalPages);
        closePrintSelectionModal();
        document.getElementById('print-preview-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    } catch (e) {
        console.error('Error generating print preview:', e);
        window.toast(PRINT_T.openFailed);
    } finally {
        proceedBtn.disabled = false; spinner.classList.add('hidden'); icon.classList.remove('hidden'); text.textContent = PRINT_T.previewA4;
    }
}
async function triggerNativePrint() {
    
    const printableDoc = document.getElementById('printable-a4-doc');
    if (!printableDoc || !printableDoc.innerHTML.trim()) {
        console.error('[PRINT ERROR] empty doc');
        window.toast(PRINT_T.openPreviewFirst);
        return;
    }
    let html = printableDoc.innerHTML;

    
    try {
        const assertion = assertBeforePrint(printableDoc);
        if (!assertion.valid) {
            console.warn('[PRINT ASSERTION FAILED] — continuing to print with warning', assertion);
            console.warn('Errors:', assertion.errors.join(' | '));
            
            
        } else {
            console.log('[PRINT ASSERTION] PASS', assertion);
        }
    } catch (err) {
        console.error('[PRINT ASSERTION ERROR]', err);
        
    }

    
    try{
        const previewPages = document.querySelectorAll('#print-preview-pages-container .a4-preview-sheet').length;
        const printablePages = (html.match(/a4-print-page/g)||[]).length;
        console.log('--- PRINT ISOLATED WINDOW DIAGNOSTIC ---');
        console.log(`Preview pages: ${previewPages} | Printable HTML pages: ${printablePages} | Expected: 1`);
        console.log(`HTML chars: ${html.length}, images: ${(html.match(/<img/g)||[]).length}`);
        const probe = document.createElement('div');
        probe.style.width='100mm'; probe.style.position='absolute'; probe.style.visibility='hidden'; probe.style.left='-1000px';
        document.body.appendChild(probe);
        console.log('mmPx probe:', (probe.getBoundingClientRect().width/100).toFixed(3));
        probe.remove();
    }catch(e){}

    
    const win = window.open('', '_blank', 'width=800,height=600');
    if(!win){ window.toast(PRINT_T.openPreviewFirst); return; }


    const hasLandscape = html.includes('data-orientation="landscape"');
    const isoDir = (typeof PRINT_T !== 'undefined' && PRINT_T.dir) || 'rtl';
    const isoLang = (typeof PRINT_T !== 'undefined' && PRINT_T.locale) || 'ar';
    const isoAlign = isoDir === 'rtl' ? 'right' : 'left';
    const isolatedHtml = `<!DOCTYPE html><html dir="${isoDir}" lang="${isoLang}"><head><meta charset="utf-8"><title>${escPrint(PRINT_T.previewA4)}</title>
<style>
  @page { size: A4 ${hasLandscape ? 'landscape' : 'portrait'}; margin: 0; }
  html, body { margin:0 !important; padding:0 !important; background:white !important; direction:${isoDir} !important; }
  html { direction:${isoDir}; }
  body { visibility:visible !important; direction:${isoDir}; background:white !important; overflow:visible !important; width:${hasLandscape ? '297mm' : '210mm'} !important; margin:0 auto !important; }
  #isolated-print-root { width:100% !important; max-width:100% !important; margin:0 !important; background:white !important; direction:${isoDir}; display:block !important; }
  .a4-print-page { position:relative !important; width:210mm !important; height:297mm !important; min-width:210mm !important; min-height:297mm !important; max-width:210mm !important; max-height:297mm !important; margin:0 !important; padding:0 !important; overflow:visible !important; display:block !important; box-sizing:border-box !important; background:white !important; page-break-inside:avoid !important; break-inside:avoid !important; border:none !important; box-shadow:none !important; direction:${isoDir}; }
  .a4-print-page[data-orientation="landscape"] { width:297mm !important; height:210mm !important; min-width:297mm !important; min-height:210mm !important; max-width:297mm !important; max-height:210mm !important; }
  [data-print-image] { position:absolute !important; box-sizing:border-box !important; overflow:hidden !important; background:white !important; border:0.3mm solid #e5e7eb !important; border-radius:1.5mm !important; display:block !important; }
  [data-print-image] img { position:absolute !important; left:0 !important; top:0 !important; width:100% !important; height:100% !important; max-width:none !important; max-height:none !important; object-fit:fill !important; display:block !important; aspect-ratio:auto !important; border:none !important; -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }

  [data-print-description], .print-description-text { direction:${isoDir} !important; unicode-bidi:plaintext !important; text-align:${isoAlign} !important; overflow-wrap:anywhere !important; }
   @media print {
      html, body { margin:0 !important; padding:0 !important; direction:${isoDir} !important; display:block !important; background:white !important; width:${hasLandscape ? '297mm' : '210mm'} !important; }
      #isolated-print-root { width:100% !important; max-width:100% !important; margin:0 !important; padding:0 !important; background:white !important; display:block !important; }
      @page { size:A4 ${hasLandscape ? 'landscape' : 'portrait'}; margin:0; }
      .a4-print-page { break-inside:avoid !important; page-break-inside:avoid !important; direction:${isoDir}; margin:0 !important; }
    }
</style></head><body dir="${isoDir}"><div id="isolated-print-root">${html}</div></body></html>`;
    win.document.open();
    win.document.write(isolatedHtml);
    win.document.close();

    
    await new Promise(r=> { if(win.document.readyState==='complete') r(); else win.addEventListener('load', r, {once:true}); setTimeout(r, 800); });
    const winImgs = win.document.querySelectorAll('img');
    try{
        await Promise.all([...winImgs].map(im=>{
            if(im.complete && im.naturalWidth) return im.decode ? im.decode().catch(()=>{}) : Promise.resolve();
            return new Promise(res=>{ im.onload=()=> res(); im.onerror=()=> res(); setTimeout(res, 3000); });
        }));
    }catch(e){ console.warn('win image decode failed', e); }
    await new Promise(r=> win.requestAnimationFrame(()=> win.requestAnimationFrame(r)));
    await new Promise(r=> setTimeout(r, 100));

    
    try{
        const pages = win.document.querySelectorAll('.a4-print-page');
        console.log('[Isolated Print] pages:', pages.length, 'expected:1');
        pages.forEach((p,i)=>{
            const rect = p.getBoundingClientRect();
            console.log(`[Isolated] page ${i}: ${(rect.width/3.77953).toFixed(1)}mm × ${(rect.height/3.77953).toFixed(1)}mm`);
        });
        const body = win.document.body;
        const root = win.document.getElementById('isolated-print-root');
        const page = win.document.querySelector('.a4-print-page');
        console.log('[Isolated Overflow Check]', {
            bodyScrollWidth: body.scrollWidth,
            bodyClientWidth: body.clientWidth,
            bodyOverflow: win.getComputedStyle(body).overflow,
            rootScrollWidth: root?.scrollWidth,
            rootClientWidth: root?.clientWidth,
            rootComputedWidth: root ? win.getComputedStyle(root).width : null,
            pageWidth: page?.getBoundingClientRect().width,
            pageHeight: page?.getBoundingClientRect().height,
            hasHorizontalScrollbar: body.scrollWidth > body.clientWidth,
            viewportWidth: win.innerWidth,
            devicePixelRatio: win.devicePixelRatio,
            zoom: (win.outerWidth / win.innerWidth).toFixed(2)
        });
        if(body.scrollWidth > body.clientWidth){
            console.warn('[Isolated] HORIZONTAL OVERFLOW DETECTED — body.scrollWidth > body.clientWidth', body.scrollWidth, '>', body.clientWidth);
        } else {
            console.log('[Isolated] No horizontal overflow — body fits viewport');
        }
    }catch(e){}

    win.focus();
    win.print();
    
    win.addEventListener('afterprint', ()=> setTimeout(()=> { try{ win.close(); }catch(e){} }, 400));
}
window.openPrintModal = openPrintModal;
window.openPrintModalFromButton = openPrintModalFromButton;

window.__PRINT_SCRIPT_LOADED__ = true;
console.log('[PRINT SCRIPT]', 'loaded OK — openPrintModalFromButton:', typeof openPrintModalFromButton, 'engine:', window.__PRINT_LAYOUT_ENGINE_VERSION__ || '(engine module not evaluated yet — lazy at preview time)');
window.decodePrintPayload = decodePrintPayload;
window.closePrintSelectionModal = closePrintSelectionModal;
window.closePrintPreviewModal = closePrintPreviewModal;
window.backToSelectionModal = backToSelectionModal;
window.toggleAttachmentSelection = toggleAttachmentSelection;
window.printSelectAll = printSelectAll;
window.generateAndOpenPreview = generateAndOpenPreview;
window.triggerNativePrint = triggerNativePrint;

window.debugPrintGeometry = function(){
    console.log('════════ GEOMETRY TRACE Engine→DOM→computed→rect ════════');
    const layout = window.__LAST_ENGINE_LAYOUT__;
    if(!layout){ console.warn('No __LAST_ENGINE_LAYOUT__ — open preview first'); return; }
    const mmPx = (()=>{ const p=document.createElement('div'); p.style.width='100mm'; p.style.position='absolute'; p.style.visibility='hidden'; p.style.left='-9999px'; document.body.appendChild(p); const v=p.getBoundingClientRect().width/100; p.remove(); return v; })();
    console.log('mmPx probe:', mmPx.toFixed(3));
    
    const rendered = document.querySelectorAll('[data-print-image]');
    console.log(`Engine images: ${layout.images.length}, Rendered DOM containers: ${rendered.length}`);
    layout.images.forEach((img,i)=>{
        const el = rendered[i] || document.querySelectorAll('#print-preview-pages-container [style*="position:absolute"]')[i] || document.querySelectorAll('#printable-a4-doc [style*="position:absolute"]')[i];
        if(!el){ console.warn(`No DOM for engine image ${i} id=${img.id}`); return; }
        const cs = getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        const engine = { x: img.x, y: img.y, w: img.width, h: img.height };
        const css = { left: el.style.left, top: el.style.top, width: el.style.width, height: el.style.height, transform: el.style.transform, position: el.style.position, display: el.style.display };
        const computed = { left: cs.left, top: cs.top, width: cs.width, height: cs.height, display: cs.display, position: cs.position, gridArea: cs.gridArea, objectFit: cs.objectFit, transform: cs.transform };
        const rectMm = { widthMm: (rect.width/mmPx).toFixed(2), heightMm: (rect.height/mmPx).toFixed(2), leftMm: (rect.left/mmPx).toFixed(2), topMm: (rect.top/mmPx).toFixed(2) };
        console.log(`── Image ${i} id=${img.id} ──`);
        console.log({ engine, css, computed, rect: { x: rect.x, y: rect.y, width: rect.width, height: rect.height, widthMm: rectMm.widthMm, heightMm: rectMm.heightMm } });
        
        const wDiff = Math.abs(parseFloat(rectMm.widthMm) - engine.w);
        const hDiff = Math.abs(parseFloat(rectMm.heightMm) - engine.h);
        if(wDiff>1.5 || hDiff>1.5) console.warn(`⚠️ MISMATCH image ${i}: engine ${engine.w}×${engine.h}mm vs rect ${rectMm.widthMm}×${rectMm.heightMm}mm (diff ${wDiff.toFixed(1)}, ${hDiff.toFixed(1)})`);
        else console.log(`✓ MATCH image ${i}: engine ≈ rect`);
        
        const innerImg = el.querySelector('img');
        if(innerImg){
            const ics = getComputedStyle(innerImg);
            const ir = innerImg.getBoundingClientRect();
            console.log(`  inner <img> computed: objectFit=${ics.objectFit} width=${ics.width} height=${ics.height} aspectRatio=${ics.aspectRatio} position=${ics.position}`);
            console.log(`  inner <img> rect: ${(ir.width/mmPx).toFixed(1)}×${(ir.height/mmPx).toFixed(1)}mm`);
        }
    });
    
    const page = document.querySelector('.a4-print-page');
    if(page){
        const pcs = getComputedStyle(page);
        const pr = page.getBoundingClientRect();
        console.log('── PAGE ──');
        console.log({ enginePaper: layout.paper, css: { width: page.style.width, height: page.style.height, position: page.style.position }, computed: { width: pcs.width, height: pcs.height, position: pcs.position, display: pcs.display, padding: pcs.padding, boxSizing: pcs.boxSizing }, rectMm: { w:(pr.width/mmPx).toFixed(1), h:(pr.height/mmPx).toFixed(1) } });
    }
    console.log('════════ END GEOMETRY TRACE ════════');
};
window.debugCssOverrides = function(){
    console.log('════════ CSS OVERRIDE SCAN §5 ════════');
    const keywords = ['display: grid','display:grid','display: flex','display:flex','grid-template','grid-area','flex-direction','justify-content','align-items','gap:','object-fit','width: 100%','height: 100%','aspect-ratio','position:','transform','scale'];
    const styleTags = [...document.querySelectorAll('style')].map(s=> s.textContent);
    const sheets = styleTags.join('\n');
    keywords.forEach(k=>{
        const count = (sheets.match(new RegExp(k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'gi'))||[]).length;
        if(count) console.log(`"${k}" → ${count} matches in <style>`);
    });
    
    ['.a4-print-page','.print-image','.print-images','.a4-preview-sheet','#printable-a4-doc','#rasd-print-document','.a4-print-page img','#printable-a4-doc img'].forEach(sel=>{
        const els = document.querySelectorAll(sel);
        if(els.length) console.log(`${sel} → ${els.length} elements`);
        
        if(els[0]){
            const cs=getComputedStyle(els[0]);
            console.log(`  ${sel}[0] computed: display=${cs.display} position=${cs.position} width=${cs.width} height=${cs.height} gridTemplateColumns=${cs.gridTemplateColumns} objectFit=${cs.objectFit} aspectRatio=${cs.aspectRatio}`);
        }
    });
    
    console.log('Checking inline style vs computed for first image container:');
    const el = document.querySelector('[data-print-image]') || document.querySelector('#print-preview-pages-container [style*=\"position:absolute\"]');
    if(el){
        console.log('inline:', el.getAttribute('style'));
        const cs=getComputedStyle(el);
        console.log('computed left/top/width/height:', cs.left, cs.top, cs.width, cs.height);
        console.log('inline left:', el.style.left, 'computed left:', cs.left);
    }
    console.log('════════ END CSS SCAN ════════');
};
window.debugDuplicateRenderer = function(){
    console.log('════════ DUPLICATE RENDERER SCAN §6 ════════');
    const html = document.documentElement.innerHTML;
    const checks = [
        { pat: 'appendChild', label: 'appendChild' },
        { pat: 'style.width', label: 'style.width' },
        { pat: 'style.height', label: 'style.height' },
        { pat: 'style.left', label: 'style.left' },
        { pat: 'style.top', label: 'style.top' },
        { pat: 'gridTemplateColumns', label: 'gridTemplateColumns' },
        { pat: 'gridTemplateRows', label: 'gridTemplateRows' },
        { pat: 'solveOptimalLayout', label: 'solveOptimalLayout (OLD GRID)' },
        { pat: 'renderAbsoluteImages', label: 'renderAbsoluteImages (fallback)' },
        { pat: 'buildA4PageHtml', label: 'buildA4PageHtml (NEW ENGINE)' },
        { pat: 'renderLayout', label: 'renderLayout (dead code)' },
    ];
    checks.forEach(c=>{
        const re = new RegExp(c.pat.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'g');
        const m = html.match(re);
        console.log(`${c.label}: ${m ? m.length : 0} occurrences in DOM HTML`);
    });
    
    const scripts = [...document.querySelectorAll('script')].map(s=> s.textContent).join('\n');
    checks.forEach(c=>{
        const re = new RegExp(c.pat.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'g');
        const m = scripts.match(re);
        if(m) console.log(`[SCRIPT] ${c.label}: ${m.length} occurrences`);
    });
    
    const hasEngine = typeof window.PrintLayoutEngine !== 'undefined';
    const hasLastLayout = !!window.__LAST_ENGINE_LAYOUT__;
    console.log(`PrintLayoutEngine loaded: ${hasEngine}, last layout exists: ${hasLastLayout}`);
    if(hasLastLayout) console.log('Last layout topo:', window.__LAST_ENGINE_LAYOUT__.metrics.topoName);
    console.log('════════ END DUPLICATE SCAN ════════');
};

window.debugPrintPipeline = function(){
    console.log('════════ PRINT PIPELINE DIAGNOSTICS ════════');
    const q = s => document.querySelectorAll(s);
    console.log('PRINT ROOTS:', q('#printable-a4-doc').length, ' #printable-a4-doc |', q('#rasd-print-document').length, ' #rasd-print-document |', q('#print-preview-pages-container').length, ' #print-preview-pages-container');
    console.log('A4 PAGES:', q('.a4-print-page').length, ' .a4-print-page |', q('[data-print-page]').length, ' [data-print-page]');
    console.log('IMAGES:', q('#printable-a4-doc img').length, ' in printable |', q('.a4-print-page img').length, ' in a4 |', q('img').length, ' total');
    console.log('DESCRIPTIONS:', q('#printable-a4-doc .single-description').length);
    
    const probe = document.createElement('div');
    probe.style.width='100mm'; probe.style.position='absolute'; probe.style.visibility='hidden'; probe.style.left='-1000px';
    document.body.appendChild(probe);
    const mmPx = probe.getBoundingClientRect().width / 100;
    probe.remove();
    console.log('mmPx:', mmPx.toFixed(3), ' (probe 100mm / px)');
    const roots = ['#printable-a4-doc','#rasd-print-document','#print-preview-pages-container','.a4-print-page'];
    roots.forEach(sel=>{
        document.querySelectorAll(sel).forEach((el,i)=>{
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            console.log(`${sel}[${i}] — display:${cs.display} visibility:${cs.visibility} position:${cs.position} width:${cs.width} height:${cs.height} overflow:${cs.overflow} transform:${cs.transform} contain:${cs.contain} break-inside:${cs.breakInside} page-break-inside:${cs.pageBreakInside}`);
            console.table({ widthPx: Math.round(r.width), heightPx: Math.round(r.height), widthMm: (r.width/mmPx).toFixed(1), heightMm: (r.height/mmPx).toFixed(1), scrollW: el.scrollWidth, scrollH: el.scrollHeight, offsetParent: el.offsetParent?.id||el.offsetParent?.tagName||'null' });
        });
    });
    
    const page = document.querySelector('.a4-print-page') || document.querySelector('#printable-a4-doc');
    if(page){
        console.log('--- Ancestor chain for', page.id||page.className);
        let el=page;
        while(el){
            const cs=getComputedStyle(el);
            console.log(el.tagName+(el.id?'#'+el.id:''), {display:cs.display, visibility:cs.visibility, position:cs.position, width:cs.width, height:cs.height, overflow:cs.overflow, transform:cs.transform, zoom:cs.zoom, contain:cs.contain, breakInside:cs.breakInside, boxSizing:cs.boxSizing, border:cs.border, boxShadow:cs.boxShadow, background:cs.backgroundColor});
            el=el.parentElement;
            if(!el || el===document.documentElement) break;
        }
    }
    
    document.querySelectorAll('#printable-a4-doc img').forEach((img,i)=>{
        console.log(`IMAGE ${i}: natural ${img.naturalWidth}x${img.naturalHeight} complete:${img.complete} src:${(img.src||'').slice(0,60)}`);
        const r=img.getBoundingClientRect();
        console.log(`  rendered: ${Math.round(r.width)}x${Math.round(r.height)}px → ${(r.width/mmPx).toFixed(1)}x${(r.height/mmPx).toFixed(1)}mm`);
    });
    
    const allA4 = [...document.querySelectorAll('.a4-print-page')].map((el,i)=> ({i, id:el.id, html: el.innerHTML.length, display:getComputedStyle(el).display}));
    console.table(allA4);
    
    const visible = [...document.querySelectorAll('*')].filter(el=>{
        const cs=getComputedStyle(el);
        return cs.display!=='none' && cs.visibility!=='hidden' && el.getBoundingClientRect().height>0;
    });
    console.log('Visible elements in print tree (approx):', visible.length);
    console.log('--- Expected: pages=1, A4=210×297mm, images=N, blackFrame=NONE ---');
    console.log('════════ END DIAGNOSTICS ════════');
    return { mmPx, previewPages: q('#print-preview-pages-container .a4-preview-sheet').length, printablePages: q('#printable-a4-doc .a4-print-page').length };
};
window.testPrintIsolation = async function(test){
    
    const c = document.getElementById('printable-a4-doc');
    const orig = c.innerHTML;
    const run = async (html, label)=>{
        c.innerHTML = html;
        c.classList.remove('hidden');
        c.style.visibility='visible';
        await new Promise(r=> setTimeout(r, 100));
        const r = c.querySelector('.a4-print-page')?.getBoundingClientRect();
        console.log(`[TEST ${label}] rect:`, r ? `${(r.width/3.77953).toFixed(1)}mm × ${(r.height/3.77953).toFixed(1)}mm` : 'no page');
        
        await new Promise(r=> setTimeout(r, 200));
    };
    if(test==='A' || !test){
        await run(`<div class="a4-print-page" style="width:210mm;height:297mm;background:white;position:relative;box-sizing:border-box;"></div>`, 'A Empty A4');
    }
    if(test==='B' || !test){
        await run(`<div class="a4-print-page" style="width:210mm;height:297mm;background:black;position:relative;"></div>`, 'B Black Rectangle');
    }
    if(test==='C' || !test){
        await run(`<div class="a4-print-page" style="width:210mm;height:297mm;background:white;position:relative;"><img src="data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1000"><rect width="800" height="1000" fill="#eef4f0"/><text x="400" y="500" font-size="48" text-anchor="middle" fill="#0e6a38">TEST</text></svg>')}" style="width:100%;height:100%;object-fit:fill;display:block;" alt="test"></div>`, 'C Image Fill');
    }
    c.innerHTML = orig;
    console.log('Isolation tests done — check Windows Print Preview for each');
};
</script>
@endpush
