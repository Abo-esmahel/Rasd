

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
    officialCount: @json(__('ui.print_official_count')),
    imageSingle: @json(__('ui.image_single'))
};
</script>
<script src="{{ asset('js/print-modal.js') }}"></script>
@endpush
