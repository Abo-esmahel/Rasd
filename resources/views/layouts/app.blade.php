<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <script>(function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('rasd_theme');if(t!=='light')document.documentElement.classList.add('dark');}catch(e){document.documentElement.classList.add('dark');}})();</script>
    <title>{{ $title ?? 'نظام ملاحظات كاميرات المراقبة' }} — وزارة الإعلام</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'cairo': ['Cairo','Segoe UI','Tahoma','sans-serif'] },
                }
            }
        }
    </script>
    <style>html body{opacity:1} html.hydrated body{opacity:1}</style>
    <script>document.documentElement.classList.add('hydrated');</script>
    <script type="module" src="/pwa/js/print-layout-engine.js?v=EDITORIAL-v4.2.1-N3-DESCRIPTION-FIX-2026-09-06"></script>
    <style>
        *{font-family:'Cairo','Segoe UI',Tahoma,sans-serif}
        html{scroll-behavior:smooth; scrollbar-gutter:stable}
        body{text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased; overflow-x:hidden}
        :focus-visible{outline:2px solid #0e6a38; outline-offset:2px}
        ::-webkit-scrollbar{width:8px;height:8px}
        ::-webkit-scrollbar-track{background:#eceee9}
        ::-webkit-scrollbar-thumb{background:#c2cbc1;border-radius:4px}
        ::-webkit-scrollbar-thumb:hover{background:#9aa99a}
        img:not([class*="h-"]),video,iframe{max-width:100%;height:auto}
        .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        html.dark ::-webkit-scrollbar-track{background:#232923}
        html.dark ::-webkit-scrollbar-thumb{background:#343a34}
        html.dark ::-webkit-scrollbar-thumb:hover{background:#404840}
        html.dark{color-scheme:dark}
        html.dark body{background:#1e2320 !important;color:#e7ece5 !important}
        html.dark .bg-\[\#fdfcfa\],html.dark .bg-white,html.dark .bg-white\/80{background-color:#252b26 !important}
        html.dark .bg-\[\#f1f3f0\],html.dark .bg-surface-100{background-color:#1e2320 !important}
        html.dark .bg-\[\#f6f7f5\],html.dark .bg-\[\#f5f7f5\],html.dark .bg-surface-50,html.dark .bg-surface-200{background-color:#2a302b !important}
        html.dark .bg-\[\#eceee9\]{background-color:#1e2320 !important}
        html.dark .bg-surface-300{background-color:#2e352e !important}
        html.dark .bg-sage-50{background-color:#1e2e22 !important}
        html.dark .bg-sage-100{background-color:#1e3328 !important}
        html.dark .bg-sage-600{background-color:#1a7a3f !important}
        html.dark .bg-red-50{background-color:#2d1f1f !important}
        html.dark .bg-amber-50{background-color:#2e2716 !important}
        html.dark .bg-ink-100{background-color:#2a2f2a !important}
        html.dark .bg-ink-900\/40{background-color:rgba(20,30,20,0.6) !important}
        html.dark .bg-\[\#eef4f0\]{background-color:#1e3328 !important}
        html.dark .border-\[\#e6e9e1\],html.dark .border-surface-300{border-color:#343a34 !important}
        html.dark .border-\[\#eceee9\]{border-color:#2e352e !important}
        html.dark .border-sage-200{border-color:#1e3d25 !important}
        html.dark .border-red-200{border-color:#3d2626 !important}
        html.dark .border-amber-200{border-color:#3d3416 !important}
        html.dark .border-\[\#cde7d6\]{border-color:#1e3d25 !important}
        html.dark .border-\[\#fecaca\]{border-color:#3d2626 !important}
        html.dark .text-\[\#1a2e1f\],html.dark .text-ink-800,html.dark .text-ink-700{color:#e7ece5 !important}
        html.dark .text-\[\#6b7a6e\],html.dark .text-\[\#737373\],html.dark .text-\[\#525252\],html.dark .text-ink-400,html.dark .text-ink-500{color:#9bb0a0 !important}
        html.dark .text-ink-300{color:#8a9a8a !important}
        html.dark .text-\[\#0e6a38\],html.dark .text-sage-700,html.dark .text-sage-600{color:#4ade80 !important}
        html.dark .text-red-700,html.dark .text-red-600{color:#f08080 !important}
        html.dark .text-amber-700{color:#f0c040 !important}
        html.dark input,html.dark textarea,html.dark select{background-color:#2a302b !important;border-color:#343a34 !important;color:#e7ece5 !important}
        html.dark input::placeholder,html.dark textarea::placeholder{color:#7e8e7e !important}
        html.dark .divide-surface-300 > :not([hidden]) ~ :not([hidden]){border-color:#2e352e !important}
        html.dark .hover\:bg-white:hover,html.dark .hover\:bg-white\/80:hover{background-color:#2e352e !important}
        html.dark .hover\:bg-\[\#fdfcfa\]:hover,html.dark .hover\:bg-\[\#fdfcfa\]\/80:hover{background-color:#2e352e !important}
        html.dark .hover\:bg-\[\#eceee9\]:hover{background-color:#2a302b !important}
        html.dark .hover\:bg-\[\#f1f3f0\]:hover{background-color:#2e352e !important}
        html.dark .hover\:bg-\[\#f6f7f5\]:hover,html.dark .hover\:bg-\[\#f5f7f5\]:hover,html.dark .hover\:bg-surface-50:hover,html.dark .hover\:bg-surface-100:hover,html.dark .hover\:bg-surface-200:hover{background-color:#2e352e !important}
        html.dark .hover\:bg-\[\#fef2f2\]:hover{background-color:#3d2626 !important}
        html.dark .hover\:text-\[\#1a2e1f\]:hover,html.dark .hover\:text-ink-800:hover,html.dark .hover\:text-ink-700:hover{color:#e7ece5 !important}
        html.dark .hover\:text-\[\#0e6a38\]:hover{color:#4ade80 !important}
        html.dark #page-loader{background:rgba(30,35,32,0.85) !important}
        html.dark #page-loader span{color:#9bb0a0 !important}
        /* إصلاح التعتيم الغامق: إخفاء قسري للـ loader + كل المودالات */
        #page-loader { animation: loaderAutoHide 0.4s ease 0.6s forwards; }
        @keyframes loaderAutoHide { to { opacity:0; visibility:hidden; pointer-events:none; display:none; } }
        /* أي مودال عليه hidden يجب أن يختفي تماماً — يمنع تسريب bg-ink-900/40 */
        [data-modal].hidden, #print-selection-modal.hidden, #print-preview-modal.hidden { display:none !important; visibility:hidden !important; opacity:0 !important; pointer-events:none !important; }
        [data-modal]:not(.hidden) { display:flex !important; }
        /* إصلاح التعتيم الغامق على الشاشة (Screen) — ليس الطباعة */
        .hidden .bg-ink-900\/40, .hidden .bg-ink-900\/60, .hidden .bg-ink-900\/70,
        .hidden.backdrop-blur-sm, [data-modal].hidden * { background:transparent !important; backdrop-filter:none !important; }
        #page-loader.hidden { display:none !important; opacity:0 !important; visibility:hidden !important; }
    </style>
    <script>/* إخفاء قسري للـ loader فقط — لا تلمس المودالات */setTimeout(function(){var l=document.getElementById('page-loader');if(l){l.style.opacity='0';l.style.visibility='hidden';l.classList.add('hidden');}},700);</script>
</head>
    <div id="page-loader" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-[#eceee9] backdrop-blur-[2px] transition-opacity duration-200" style="pointer-events:none">
        <div class="flex flex-col items-center gap-3">
            <div class="w-10 h-10 rounded-full border-[3px] border-[#e6e9e1] border-t-[#0e6a38] animate-spin"></div>
            <span class="text-xs font-bold text-[#525252]">جارٍ التحميل...</span>
        </div>
    </div>
<body class="bg-[#eceee9] min-h-screen flex flex-col text-[#1a2e1f] antialiased overflow-x-hidden selection:bg-[#0e6a38] selection:text-[#fdfcfa]">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:right-3 bg-[#0e6a38] text-white px-4 py-2.5 rounded-xl z-[100] text-sm font-bold shadow-lg">تخطي إلى المحتوى</a>

    @auth
    <header class="sticky top-0 z-40 bg-[#fdfcfa] border-b border-[#e6e9e1]">
        <div class="h-[2px] w-full flex">
            <div class="flex-1 bg-[#0e6a38]"></div>
            <div class="flex-1 bg-white"></div>
            <div class="flex-1 bg-[#0f1a13]"></div>
        </div>
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 gap-4">
                <div class="flex items-center gap-6 min-w-0">
                    <a href="{{ route('notes.index') }}" class="flex items-center gap-2.5 shrink-0 group">
                        <div class="w-8 h-8 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center overflow-hidden group-hover:border-[#d4ddd3] transition shrink-0">
                            <svg width="22" height="22" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="شعار وزارة الإعلام">
                                <rect x="2" y="4" width="32" height="28" rx="6" fill="white" stroke="#e3ebe5"/>
                                <path d="M6 9.5C6 8.672 6.672 8 7.5 8H28.5C29.328 8 30 8.672 30 9.5V13H6V9.5Z" fill="#0e6a38"/>
                                <rect x="6" y="13" width="24" height="6" fill="white"/>
                                <g fill="#ce1126">
                                    <path d="M12 16.6l1.15 0.85 -0.44 -1.36 1.15 -0.84H12.44L12 14l-0.44 1.25H10.09l1.15 0.84 -0.44 1.36L12 16.6Z"/>
                                    <path d="M18 16.6l1.15 0.85 -0.44 -1.36 1.15 -0.84H18.44L18 14l-0.44 1.25H16.09l1.15 0.84 -0.44 1.36L18 16.6Z"/>
                                    <path d="M24 16.6l1.15 0.85 -0.44 -1.36 1.15 -0.84H24.44L24 14l-0.44 1.25H22.09l1.15 0.84 -0.44 1.36L24 16.6Z"/>
                                </g>
                                <path d="M6 19H30V26.5C30 27.328 29.328 28 28.5 28H7.5C6.672 28 6 27.328 6 26.5V19Z" fill="#0f1a13"/>
                            </svg>
                        </div>
                        <span class="font-bold text-[14px] tracking-tight text-[#1a2e1f] hidden sm:block">وزارة الإعلام</span>
                    </a>

                    <nav class="hidden md:flex items-center gap-1" aria-label="التنقل">
                        <a href="{{ route('notes.index') }}" class="px-3 py-1.5 rounded-lg text-[13px] transition {{ request()->routeIs('notes.index') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            الملاحظات
                        </a>
                        <a href="{{ route('notes.my') }}" class="px-3 py-1.5 rounded-lg text-[13px] transition {{ request()->routeIs('notes.my') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            ملاحظاتي
                        </a>
                        <a href="{{ route('ranking') }}" class="px-3 py-1.5 rounded-lg text-[13px] transition {{ request()->routeIs('ranking') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            الترتيب
                        </a>
                        <a href="{{ route('profile.show') }}" class="px-3 py-1.5 rounded-lg text-[13px] transition {{ request()->routeIs('profile.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            حسابي
                        </a>
                    </nav>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="theme-toggle" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] transition" aria-label="الوضع الداكن" title="تبديل الوضع">
                        <svg class="w-4 h-4 sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg class="w-4 h-4 moon-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>

                    {{-- Notifications Bell — شريط الإشعارات (خارج الموقع) --}}
                    <div class="relative">
                        <button type="button" id="notification-bell" class="relative w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] transition" aria-label="الإشعارات">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span id="notification-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center border-2 border-[#fdfcfa]">0</span>
                        </button>
                        <div id="notification-dropdown" class="hidden absolute left-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-[#e6e9e1] overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-[#e6e9e1] flex items-center justify-between bg-[#f5f7f5]">
                                <h3 class="text-sm font-extrabold text-ink-800">الإشعارات</h3>
                                <button type="button" id="mark-all-read" class="text-xs font-bold text-[#0e6a38] hover:underline">تحديد الكل كمقروء</button>
                            </div>
                            <div id="notification-list" class="max-h-96 overflow-y-auto divide-y divide-[#e6e9e1]">
                                <div class="p-8 text-center text-sm text-ink-400">لا توجد إشعارات</div>
                            </div>
                            <div class="px-4 py-2 border-t border-[#e6e9e1] bg-[#f5f7f5] text-center">
                                <a href="{{ route('notifications.index') }}" class="text-xs font-bold text-[#0e6a38] hover:underline">عرض كل الإشعارات</a>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('profile.show') }}" class="hidden sm:flex items-center gap-2 hover:opacity-80 transition">
                        @if(auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-7 h-7 rounded-full object-cover border border-[#e6e9e1] shrink-0">
                        @else
                            <div class="w-7 h-7 rounded-full bg-[#0e6a38] flex items-center justify-center text-white text-xs font-bold shrink-0">{{ auth()->user()->initial }}</div>
                        @endif
                        <span class="text-[13px] font-semibold text-[#1a2e1f] hidden lg:block truncate max-w-[140px]">{{ auth()->user()->name }}</span>
                    </a>
                    <a href="{{ route('profile.show') }}" class="sm:hidden">
                        @if(auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-7 h-7 rounded-full object-cover border border-[#e6e9e1] shrink-0">
                        @else
                            <div class="w-7 h-7 rounded-full bg-[#0e6a38] flex items-center justify-center text-white text-xs font-bold shrink-0">{{ auth()->user()->initial }}</div>
                        @endif
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-[12px] font-medium text-[#6b7a6e] hover:text-[#1a2e1f] px-2 py-1.5 transition">خروج</button>
                    </form>

                    <button type="button" id="mobile-menu-btn" class="md:hidden w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:bg-[#f6f7f5] transition" aria-expanded="false" aria-controls="mobile-menu" aria-label="القائمة">
                        <svg class="w-5 h-5 menu-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg class="w-5 h-5 menu-close hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="md:hidden hidden border-t border-[#e6e9e1] bg-[#fdfcfa]">
            <div class="px-4 py-3 space-y-2">
                <div class="flex items-center gap-3 py-2">
                    @if(auth()->user()->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border border-[#e6e9e1] shrink-0">
                    @else
                        <div class="w-9 h-9 rounded-full bg-[#0e6a38] text-white flex items-center justify-center font-bold text-sm shrink-0">{{ auth()->user()->initial }}</div>
                    @endif
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-[#1a2e1f] truncate">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-[#6b7a6e] truncate">{{ '@' . auth()->user()->username }}</div>
                    </div>
                </div>
                <nav class="grid gap-1 pt-2 border-t border-[#e6e9e1]">
                    <a href="{{ route('notes.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('notes.index') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">الملاحظات</a>
                    <a href="{{ route('notes.my') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('notes.my') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">ملاحظاتي</a>
                    <a href="{{ route('ranking') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('ranking') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">الترتيب</a>
                    <a href="{{ route('profile.show') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('profile.*') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">حسابي</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-right px-3 py-2.5 rounded-lg text-sm font-medium text-[#9aa99a] hover:text-red-600">خروج</button>
                    </form>
                </nav>
            </div>
        </div>
    </header>
    @endauth

    <main id="main-content" class="flex-1 w-full max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
        @if(session('success'))
            <div class="mb-6 flex items-start gap-3 p-4 bg-[#fdfcfa] border border-[#e6e9e1] rounded-2xl shadow-sm overflow-hidden relative" role="alert">
                <div class="absolute inset-y-0 right-0 w-1 bg-[#0e6a38]"></div>
                <div class="shrink-0 w-9 h-9 rounded-xl bg-[#0e6a38] text-[#fdfcfa] flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="flex-1 min-w-0 pt-0.5"><p class="text-[14px] font-bold leading-6 text-[#1a2e1f]">{{ session('success') }}</p></div>
                <button type="button" onclick="this.parentElement.remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#f6f7f5] flex items-center justify-center text-[#9aa99a] hover:text-[#1a2e1f] transition" aria-label="إغلاق">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
        @if(session('warning'))
            <div class="mb-6 flex items-start gap-3 p-4 bg-[#fffbeb] border border-[#fde68a] rounded-2xl shadow-sm overflow-hidden relative" role="alert">
                <div class="absolute inset-y-0 right-0 w-1 bg-[#d97706]"></div>
                <div class="shrink-0 w-9 h-9 rounded-xl bg-[#d97706] text-[#fffbeb] flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0 pt-0.5"><p class="text-[14px] font-bold leading-6 text-[#1a2e1f]">{{ session('warning') }}</p></div>
                <button type="button" onclick="this.parentElement.remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef3c7] flex items-center justify-center text-[#9aa99a] hover:text-[#1a2e1f] transition" aria-label="إغلاق">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
        <div id="js-flash"></div>
        @if($errors->any())
            <div class="mb-6 p-4 bg-[#fdfcfa] border border-[#fecaca] rounded-2xl shadow-sm overflow-hidden relative" role="alert">
                <div class="absolute inset-y-0 right-0 w-1 bg-[#c41e1e]"></div>
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-[#c41e1e] text-[#fdfcfa] flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-[13px] font-extrabold text-[#1a2e1f]">تنبيه — يرجى المراجعة</h3>
                        <ul class="mt-1.5 list-disc list-inside space-y-1 text-[13px] leading-6 text-[#4a5a4f] marker:text-[#c41e1e]/60">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                    <button type="button" onclick="this.closest('div[role=alert]').remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef2f2] flex items-center justify-center text-[#94a8a0] transition" aria-label="إغلاق">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <script>
        // عرض أخطاء المرفقات القادمة من إرسال fetch (تُخزن في sessionStorage قبل التحويل)
        (function(){
            try{
                var raw=sessionStorage.getItem('attach_errors');
                if(!raw) return;
                sessionStorage.removeItem('attach_errors');
                var errs=JSON.parse(raw);
                if(!errs||!errs.length) return;
                var box=document.getElementById('js-flash');
                if(!box) return;
                var div=document.createElement('div');
                div.className='mb-6 flex items-start gap-3 p-4 bg-[#fffbeb] border border-[#fde68a] rounded-2xl shadow-sm overflow-hidden relative';
                div.setAttribute('role','alert');
                div.innerHTML='<div class="absolute inset-y-0 right-0 w-1 bg-[#d97706]"></div>'
                    +'<div class="flex-1 min-w-0"><h3 class="text-[13px] font-extrabold text-[#1a2e1f]">تم الحفظ، لكن بعض المرفقات لم تُرفع:</h3>'
                    +'<ul class="mt-1.5 list-disc list-inside space-y-1 text-[13px] leading-6 text-[#4a5a4f]"></ul></div>'
                    +'<button type="button" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef3c7] flex items-center justify-center" aria-label="إغلاق">✕</button>';
                var ul=div.querySelector('ul');
                errs.forEach(function(m){var li=document.createElement('li');li.textContent=m;ul.appendChild(li);});
                div.querySelector('button').onclick=function(){div.remove();};
                box.appendChild(div);
                div.scrollIntoView({behavior:'smooth',block:'center'});
            }catch(e){}
        })();
    </script>

    <script>
        (function(){
            const html=document.documentElement;
            const t=document.getElementById('theme-toggle');
            function sync(){
                const d=html.classList.contains('dark');
                document.querySelectorAll('.sun-icon').forEach(e=>e.classList.toggle('hidden',d));
                document.querySelectorAll('.moon-icon').forEach(e=>e.classList.toggle('hidden',!d));
            }
            function applyStoredTheme(){
                try{
                    var s=localStorage.getItem('theme')||localStorage.getItem('rasd_theme');
                    if(s==='light') html.classList.remove('dark'); else html.classList.add('dark');
                    sync();
                }catch(e){}
            }
            function toggle(){
                html.classList.toggle('dark');
                const d=html.classList.contains('dark');
                try{localStorage.setItem('theme',d?'dark':'light');localStorage.setItem('rasd_theme',d?'dark':'light');}catch(e){}
                sync();
            }
            sync();
            t?.addEventListener('click',toggle);
            // — ثبات المود على مستوى النظام (bfcache + رجوع) —
            window.addEventListener('pageshow', applyStoredTheme);
            window.addEventListener('storage', function(e){ if(e.key==='theme'||e.key==='rasd_theme') applyStoredTheme(); });
            // — Notifications — شريط الإشعارات خارج الموقع (Browser Notification) —
            const bell = document.getElementById('notification-bell');
            const badge = document.getElementById('notification-badge');
            const dropdown = document.getElementById('notification-dropdown');
            const listEl = document.getElementById('notification-list');
            const markAllBtn = document.getElementById('mark-all-read');
            let lastUnreadIds = new Set();

            function escapeHtml(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

            async function fetchNotifications(showBrowser = false){
                try{
                    const res = await fetch('{{ route('notifications.index') }}', { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }});
                    if(!res.ok) return;
                    const data = await res.json();
                    const unread = data.unread_count || 0;
                    const notifications = data.notifications || [];

                    if(unread > 0){
                        badge.textContent = unread > 9 ? '9+' : unread;
                        badge.classList.remove('hidden');
                        badge.classList.add('flex');
                    } else {
                        badge.classList.add('hidden');
                        badge.classList.remove('flex');
                    }

                    // Browser Notification — خارج الموقع (شريط النظام)
                    if(showBrowser && window.Notification && Notification.permission === 'granted'){
                        const newIds = notifications.filter(n=>!n.read_at).map(n=>n.id).filter(id=>!lastUnreadIds.has(id));
                        newIds.forEach(id=>{
                            const n = notifications.find(x=>x.id===id);
                            if(n){
                                const title = 'تم رفض ملاحظتك';
                                const body = (n.data.reason || '').substring(0,120);
                                const notif = new Notification(title, {
                                    body: body,
                                    icon: '/favicon.ico',
                                    tag: n.id,
                                    requireInteraction: false
                                });
                                notif.onclick = ()=>{ window.focus(); openModal('notification-'+n.id); };
                            }
                        });
                    }
                    lastUnreadIds = new Set(notifications.filter(n=>!n.read_at).map(n=>n.id));

                    // Render dropdown list
                    if(notifications.length===0){
                        listEl.innerHTML = '<div class="p-8 text-center text-sm text-ink-400">لا توجد إشعارات</div>';
                    } else {
                        listEl.innerHTML = notifications.map(n=>`
                            <div class="p-4 hover:bg-[#f5f7f5] transition ${!n.read_at ? 'bg-[#eef4f0]/50' : ''}" data-id="${n.id}">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg ${!n.read_at ? 'bg-red-50 border border-red-200' : 'bg-surface-100 border border-surface-300'} flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 ${!n.read_at ? 'text-red-500' : 'text-ink-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-ink-800 leading-5">${escapeHtml(n.data.message || 'تم رفض ملاحظتك')}</p>
                                        <p class="mt-1 text-xs leading-5 text-ink-500 bg-white border border-surface-300 rounded-lg p-2.5">سبب الرفض: ${escapeHtml(n.data.reason || '—')}</p>
                                        <p class="mt-1.5 text-[11px] text-ink-400">${escapeHtml(n.created_at)} • ${n.read_at ? 'مقروء' : '<span class="text-red-500 font-bold">غير مقروء</span>'}</p>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                }catch(e){ console.error(e); }
            }

            // طلب إذن الإشعارات — خارج الموقع
            async function ensureNotificationPermission(){
                if(!window.Notification) return;
                if(Notification.permission === 'default'){
                    try{ await Notification.requestPermission(); }catch(e){}
                }
            }

            bell?.addEventListener('click', async (e)=>{
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
                if(!dropdown.classList.contains('hidden')){
                    await fetchNotifications(false);
                    ensureNotificationPermission();
                }
            });
            document.addEventListener('click', (e)=>{
                if(!bell.contains(e.target) && !dropdown.contains(e.target)){
                    dropdown.classList.add('hidden');
                }
            });
            markAllBtn?.addEventListener('click', async ()=>{
                await fetch('{{ route('notifications.markRead') }}', { method:'POST', headers:{ 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept':'application/json' }, body: new URLSearchParams({})});
                await fetchNotifications(false);
            });

            // Polling كل 20 ثانية + Browser Notification
            ensureNotificationPermission();
            fetchNotifications(false);
            setInterval(()=> fetchNotifications(true), 20000);
            // عند العودة للتبويب
            document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) fetchNotifications(true); });

        })();
        const btn=document.getElementById('mobile-menu-btn'),menu=document.getElementById('mobile-menu');
        if(btn&&menu){btn.addEventListener('click',()=>{const h=menu.classList.contains('hidden');menu.classList.toggle('hidden',!h);btn.setAttribute('aria-expanded',h?'true':'false');btn.querySelector('.menu-open')?.classList.toggle('hidden',h);btn.querySelector('.menu-close')?.classList.toggle('hidden',!h);});}
        setTimeout(()=>{document.querySelectorAll('[role="alert"]').forEach(el=>{if(el.textContent.includes('تم') || el.textContent.includes('بنجاح')){el.style.transition='opacity .4s,transform .4s';el.style.opacity='0';el.style.transform='translateY(-6px)';setTimeout(()=>el.remove(),400);}});},5000);
        document.querySelectorAll('form:not([data-ajax])').forEach(form=>{form.addEventListener('submit',function(){const b=this.querySelector('button[type="submit"]:not([formnovalidate])');if(b&&!b.dataset.noLoader){b.disabled=true;b.innerHTML='<span class="inline-flex items-center gap-2"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> جاري التنفيذ...</span>';b.classList.add('opacity-80','cursor-wait');}});});
        document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('[data-modal]').forEach(m=>m.classList.add('hidden'));document.body.style.overflow='';}});
        function openModal(id){const el=document.getElementById(id);if(!el)return;el.classList.remove('hidden');el.style.display='';el.style.visibility='';document.body.style.overflow='hidden';}
        function closeModal(id){const el=document.getElementById(id);if(!el)return;el.classList.add('hidden');el.style.display='';document.body.style.overflow='';}
        window.openModal=openModal;window.closeModal=closeModal;
        const loader=document.getElementById('page-loader');
        function showLoader(){if(!loader)return;loader.classList.remove('hidden');loader.style.opacity='1';loader.style.visibility='visible';loader.style.display='flex'}
        function hideLoader(){if(!loader)return;loader.style.opacity='0';loader.style.visibility='hidden';setTimeout(function(){loader.classList.add('hidden');loader.style.display='none'},200)}
        // FOUC: إبقاء اللودر حتى اكتمال Tailwind والخطوط — مع إخفاء قسري
        window.addEventListener('load', hideLoader);
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(hideLoader, 400);
            document.body.style.overflow='';
            setTimeout(()=>{ hideLoader(); }, 1000);
        });
        document.addEventListener('click',function(e){
            const a=e.target.closest('a[href]');
            if(!a)return;
            if(a.hasAttribute('target')||a.hasAttribute('download')||a.getAttribute('href')==='#'||a.getAttribute('href')?.startsWith('javascript'))return;
            if(a.closest('[data-no-loader]'))return;
            if(a.closest('form'))return;
            showLoader();
        });
        document.addEventListener('submit',function(e){
            if(e.target.hasAttribute('data-no-loader'))return;
            showLoader();
        });
        window.addEventListener('pageshow',hideLoader);
        // ===== AJAX Form Handler =====
        async function ajaxSubmit(form, onSuccess) {
            const btn = form.querySelector('button[type="submit"]:not([formnovalidate])');
            const origHtml = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>'; }
            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: form.method || 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    if (onSuccess) onSuccess(data);
                    else if (data.redirect) window.location.href = data.redirect;
                    else location.reload();
                } else {
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        alert(Array.isArray(first) ? first[0] : first);
                    } else {
                        alert(data.message || 'حدث خطأ');
                    }
                    if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
                }
            } catch(e) {
                form.submit();
            }
        }
        // ===== AJAX Tab Filter =====
        async function ajaxFilter(url) {
            const container = document.getElementById('notes-list');
            if (!container) return window.location.href = url;
            container.style.opacity = '0.5';
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newList = doc.getElementById('notes-list');
                const newTabs = doc.querySelector('.tabs-container');
                if (newList) container.innerHTML = newList.innerHTML;
                if (newTabs) document.querySelector('.tabs-container').innerHTML = newTabs.innerHTML;
                container.style.opacity = '1';
                history.pushState(null, '', url);
            } catch(e) { window.location.href = url; }
        }
        window.ajaxSubmit = ajaxSubmit;
        window.ajaxFilter = ajaxFilter;
    </script>
    @stack('scripts')
    <!-- Print Root — مباشر تحت body لمنع 2 pages من ancestor display:none -->
    <div id="printable-a4-doc" class="hidden" data-print-root style="display:none;"></div>
    <div id="rasd-print-document" class="hidden" aria-hidden="true" style="display:none;"></div>
</body>
</html>
