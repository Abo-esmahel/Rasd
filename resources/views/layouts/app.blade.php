<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="app-debug" content="{{ config('app.debug') ? '1' : '0' }}">
    @endauth

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1f6f4a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ملاحظة">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="ملاحظة">
    <meta name="description" content="نظام ملاحظة كاميرات المراقبة">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/eagle-emblem.svg') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="/pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192.png">
    <script>(function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('rasd_theme');if(t!=='light')document.documentElement.classList.add('dark');}catch(e){document.documentElement.classList.add('dark');}})();</script>
    <script>

    (function(){
      try{
        document.addEventListener('gesturestart', function(e){ e.preventDefault(); }, {passive:false});
        document.addEventListener('gesturechange', function(e){ e.preventDefault(); }, {passive:false});
        document.addEventListener('gestureend', function(e){ e.preventDefault(); }, {passive:false});
        document.addEventListener('wheel', function(e){ if(e.ctrlKey) e.preventDefault(); }, {passive:false});
        document.addEventListener('keydown', function(e){
          if((e.ctrlKey||e.metaKey) && (e.key==='+'||e.key==='-'||e.key==='='||e.key==='0'|| e.keyCode===61|| e.keyCode===173|| e.keyCode===48)) e.preventDefault();
        });
        document.addEventListener('touchmove', function(e){ if(e.touches && e.touches.length>1) e.preventDefault(); }, {passive:false});

        let lastTouch=0;
        document.addEventListener('touchend', function(e){
          const now=Date.now();
          if(now-lastTouch<=300) e.preventDefault();
          lastTouch=now;
        }, {passive:false});
      }catch(e){}
    })();
    </script>
    <title>{{ $title ?? 'نظام ملاحظات كاميرات المراقبة' }} — وزارة الإعلام</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
    <script type="module" src="/pwa/js/print-layout-engine.js?v=EDITORIAL-v4.2.1-N3-DESCRIPTION-FIX-2026-09-06"></script>
    <style>

        html{scroll-behavior:smooth; scrollbar-gutter:stable; touch-action: pan-x pan-y; -ms-touch-action: pan-x pan-y; overscroll-behavior: contain;}
        body{text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased; overflow-x:hidden; overscroll-behavior: contain; touch-action: pan-x pan-y;}
        *{font-family:'Cairo','Segoe UI',Tahoma,sans-serif}
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
        html.dark .bg-\[\#fdfcfa\],html.dark .bg-white,html.dark .bg-white\/80,html.dark .bg-white\/90{background-color:#252b26 !important}
        html.dark .bg-\[\#f1f3f0\],html.dark .bg-surface-100,html.dark .bg-surface-50\/50{background-color:#1e2320 !important}
        html.dark .bg-\[\#f6f7f5\],html.dark .bg-\[\#f5f7f5\],html.dark .bg-surface-50,html.dark .bg-surface-200,html.dark .bg-\[\#f5f7f5\]\/50{background-color:#2a302b !important}
        html.dark .bg-\[\#eceee9\]{background-color:#1e2320 !important}
        html.dark .bg-surface-300,html.dark .border-surface-300\/50{background-color:#2e352e !important}
        html.dark .prose,html.dark pre,html.dark code,html.dark .log-panel{background-color:#1e2320 !important;color:#e7ece5 !important;border-color:#2e352e !important}
        html.dark .bg-white pre,html.dark .bg-\[\#fdfcfa\] pre{background-color:#1a1f1a !important}
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
        html.dark .hover\:bg-white:hover,html.dark .hover\:bg-white\/80:hover{background-color:#3a443b !important}
        html.dark .hover\:bg-\[\#fdfcfa\]:hover,html.dark .hover\:bg-\[\#fdfcfa\]\/80:hover{background-color:#3a443b !important}
        html.dark .hover\:bg-\[\#eceee9\]:hover{background-color:#3a443b !important}
        html.dark .hover\:bg-\[\#f1f3f0\]:hover{background-color:#3a443b !important}
        html.dark .hover\:bg-\[\#f6f7f5\]:hover,html.dark .hover\:bg-\[\#f5f7f5\]:hover,html.dark .hover\:bg-surface-50:hover,html.dark .hover\:bg-surface-100:hover,html.dark .hover\:bg-surface-200:hover{background-color:#3a443b !important}
        /* — Notifications Drawer shell: Dark Mode for remaining utility classes (scoped, dark-only) — */
        html.dark #notification-drawer .bg-surface-elevated{background-color:#252b26 !important}
        html.dark #notification-drawer .bg-surface-muted{background-color:#1e2320 !important}
        html.dark #notification-drawer .bg-gray-200{background-color:#343a34 !important}
        html.dark #notification-drawer .border-border{border-color:#343a34 !important}
        html.dark #notification-drawer .text-text-primary{color:#e7ece5 !important}
        html.dark #notification-drawer .text-text-secondary{color:#9bb0a0 !important}
        html.dark #notification-drawer .text-text-muted{color:#9bb0a0 !important}
        html.dark #notification-drawer .text-primary{color:#4ade80 !important}
        html.dark #notification-drawer .text-amber-600{color:#f0c040 !important}
        html.dark #notification-drawer .text-amber-800{color:#f0c040 !important}
        /* — Notifications log page (/notifications): Dark Mode (scoped, dark-only) — */
        html.dark #notif-center-page .bg-surface-elevated{background-color:#252b26 !important}
        html.dark #notif-center-page .bg-surface-muted{background-color:#1e2320 !important}
        html.dark #notif-center-page .border-border{border-color:#343a34 !important}
        html.dark #notif-center-page .text-text-primary{color:#e7ece5 !important}
        html.dark #notif-center-page .text-text-secondary{color:#9bb0a0 !important}
        html.dark #notif-center-page .text-text-muted{color:#9bb0a0 !important}
        html.dark #notif-center-page .text-primary{color:#4ade80 !important}
        html.dark #notif-center-page .text-amber-600{color:#f0c040 !important}
        html.dark #notif-center-page .border-primary{border-color:#4ade80 !important}
        html.dark #notif-center-page .border-r-primary{border-right-color:#4ade80 !important}
        html.dark #notif-center-page .hover\:bg-surface-muted:hover{background-color:#2e352e !important}
        html.dark #notif-center-page .hover\:border-border-strong:hover{border-color:#343a34 !important}
        html.dark #notif-center-page .hover\:text-text-secondary:hover{color:#9bb0a0 !important}
        html.dark #notif-center-page nav .text-gray-500,html.dark #notif-center-page nav .text-gray-600,html.dark #notif-center-page nav .text-gray-700{color:#9bb0a0 !important}
        html.dark #notif-center-page nav .text-gray-800{color:#e7ece5 !important}
        html.dark #notif-center-page nav .border-gray-300{border-color:#343a34 !important}
        html.dark #notif-center-page nav .bg-gray-200{background-color:#2e352e !important}
        html.dark #notif-center-page nav .hover\:bg-gray-100:hover{background-color:#2e352e !important}
        html.dark #notif-center-page nav .hover\:text-gray-700:hover,html.dark #notif-center-page nav .hover\:text-gray-400:hover{color:#e7ece5 !important}
        html.dark .hover\:bg-\[\#fef2f2\]:hover{background-color:#522a2a !important}
        html.dark .hover\:bg-sage-700:hover{background-color:#156b35 !important}
        html.dark .hover\:bg-ink-900:hover{background-color:#1a1f1a !important}
        html.dark .hover\:bg-amber-600:hover{background-color:#b8860b !important}
        html.dark .hover\:bg-red-600:hover{background-color:#b91c1c !important}
        html.dark .hover\:border-sage-200:hover{border-color:#2e6b3a !important}
        html.dark .hover\:border-sage-600:hover{border-color:#4ade80 !important}
        html.dark .hover\:border-l-sage-600:hover{border-left-color:#4ade80 !important}
        html.dark .hover\:bg-sage-50:hover{background-color:#1e3328 !important}
        html.dark .hover\:shadow-lg:hover{box-shadow:0 8px 16px -4px rgba(0,0,0,0.5) !important}
        html.dark .hover\:text-\[\#1a2e1f\]:hover,html.dark .hover\:text-ink-800:hover,html.dark .hover\:text-ink-700:hover{color:#e7ece5 !important}
        html.dark .hover\:text-\[\#0e6a38\]:hover{color:#4ade80 !important}
        html.dark #page-loader{background:rgba(30,35,32,0.85) !important}
        html.dark #page-loader span{color:#9bb0a0 !important}

        #page-loader { animation: loaderAutoHide 0.4s ease 0.6s forwards; }
        @keyframes loaderAutoHide { to { opacity:0; visibility:hidden; pointer-events:none; display:none; } }

        [data-modal].hidden, #print-selection-modal.hidden, #print-preview-modal.hidden { display:none !important; visibility:hidden !important; opacity:0 !important; pointer-events:none !important; }
        [data-modal]:not(.hidden) { display:flex !important; }

        .hidden .bg-ink-900\/40, .hidden .bg-ink-900\/60, .hidden .bg-ink-900\/70,
        .hidden.backdrop-blur-sm, [data-modal].hidden * { background:transparent !important; backdrop-filter:none !important; }
        #page-loader.hidden { display:none !important; opacity:0 !important; visibility:hidden !important; }

        #notification-toast-container { scrollbar-width: thin; }
        @media (prefers-reduced-motion: reduce) {
            #notification-toast-container [data-toast] { transition: none !important; animation: none !important; }
            #notification-badge { animation: none !important; }
        }
        #notification-badge:not(.hidden) { animation: badgePulse 0.3s ease-out; }
        @keyframes badgePulse { 0%{transform:scale(1)} 50%{transform:scale(1.18)} 100%{transform:scale(1)} }
        .notif-enter { animation: notifSlideIn 0.32s ease-out forwards; }
        @keyframes notifSlideIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        #notification-drawer .notif-feed { scrollbar-width: thin; }
        .notif-card:focus-visible { outline:2px solid #0e6a38; outline-offset:-2px; }

        /* — Topbar: fixed emblem box + guaranteed gap between brand and nav.
           The global unlayered `img{height:auto;max-width:100%}` rule beats Tailwind's
           layered `h-9`/`max-w-[72px]`, so the viewBox-only SVG ballooned and the brand
           link overlapped the nav. These unlayered class rules win by specificity. */
        .topbar-brand .brand-emblem{height:36px;width:auto;max-width:72px;flex:none}
        @media(min-width:768px){.topbar-start{column-gap:24px}}
        @media(min-width:1024px){.topbar-start{column-gap:32px}}
    </style>
    <script>setTimeout(function(){var l=document.getElementById('page-loader');if(l){l.style.opacity='0';l.style.visibility='hidden';l.classList.add('hidden');}},700);</script>
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
            <div class="flex flex-nowrap items-center justify-between h-14 gap-4">
                <div class="topbar-start flex min-w-0 flex-1 flex-nowrap items-center gap-3 md:gap-4 lg:gap-6">
                    <a href="{{ route('notes.index') }}" class="topbar-brand flex flex-nowrap items-center gap-2.5 shrink-0 group">
                        <img src="{{ asset('images/eagle-emblem.svg') }}" alt="شعار النسر السوري" class="brand-emblem h-9 w-auto max-w-[72px] object-contain shrink-0 drop-shadow-sm" loading="eager" fetchpriority="high" decoding="async">
                        <span class="font-bold text-[13px] tracking-tight text-[#1a2e1f] hidden lg:block whitespace-nowrap">وزارة الإعلام</span>
                    </a>

                    <nav class="hidden md:flex flex-nowrap items-center gap-1 shrink-0 whitespace-nowrap" aria-label="التنقل">
                        <a href="{{ route('notes.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('notes.index') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            الملاحظات
                        </a>
                        <a href="{{ route('notes.my') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('notes.my') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            ملاحظاتي
                        </a>
                        <a href="{{ route('general-submissions.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('general-submissions.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            الإرسالات العامة
                        </a>
                        <a href="{{ route('ranking') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('ranking') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            الترتيب
                        </a>
                        <a href="{{ route('profile.show') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('profile.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            حسابي
                        </a>
                    </nav>
                </div>

                <div class="flex flex-nowrap items-center gap-2 shrink-0">
                    <button type="button" id="theme-toggle" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] transition" aria-label="الوضع الداكن" title="تبديل الوضع">
                        <svg class="w-4 h-4 sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg class="w-4 h-4 moon-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>


                    {{-- Notifications Bell — opens side drawer (logic in NotificationManager) --}}
                    <button type="button" id="notification-bell" class="relative w-8 h-8 rounded-lg flex items-center justify-center text-text-secondary dark:text-text-secondary hover:text-text-primary dark:hover:text-text-primary hover:bg-surface-muted dark:hover:bg-surface-muted transition" aria-label="الإشعارات" aria-haspopup="dialog" aria-expanded="false" aria-controls="notification-drawer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span id="notification-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center border-2 border-surface-elevated dark:border-surface-elevated" aria-live="polite" aria-atomic="true">0</span>
                    </button>
                    <div id="notification-overlay" class="notif-overlay hidden" aria-hidden="true"></div>
                    <aside id="notification-drawer" class="notif-drawer hidden" role="dialog" aria-modal="true" aria-label="مركز الإشعارات" aria-hidden="true">
                        <div class="notif-topbar">
                            <div class="notif-topbar-main">
                                <h2 class="notif-heading">الإشعارات</h2>
                                <span id="notification-drawer-count" class="notif-count hidden">0</span>
                                <span id="notification-connection-status" class="hidden notif-conn" title="متصل"></span>
                            </div>
                            <div class="notif-topbar-actions">
                                <button type="button" id="notification-settings-toggle" class="notif-iconbtn" aria-label="إعدادات الإشعارات" title="الإعدادات">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" id="notification-close" class="notif-iconbtn" aria-label="إغلاق لوحة الإشعارات">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="notif-subbar">
                            <button type="button" id="mark-all-read" class="notif-markall">تحديد الكل كمقروء</button>
                        </div>

                            <div id="notification-settings-panel" class="hidden px-4 py-3 bg-surface-elevated border-b border-border space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <label for="notif-sound-toggle" class="text-xs font-bold text-text-primary dark:text-text-primary flex items-center gap-1.5 cursor-pointer">🔊 أصوات الإشعارات</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="notif-sound-toggle" class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label for="notif-volume-slider" class="text-xs font-bold text-text-primary dark:text-text-primary shrink-0">مستوى الصوت</label>
                                    <input type="range" id="notif-volume-slider" min="0" max="100" value="70" class="flex-1 accent-primary">
                                    <span id="notif-volume-label" class="text-xs font-mono text-text-muted dark:text-text-muted w-8 text-left">70%</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="notif-toast-toggle" class="text-xs font-bold text-text-primary dark:text-text-primary flex items-center gap-1.5 cursor-pointer">💬 التوست الفوري</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="notif-toast-toggle" class="sr-only peer" checked>
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </div>
                                <p class="text-[11px] text-text-muted dark:text-text-muted leading-4">يتم حفظ اختيارك تلقائياً. عند كتم الصوت لن تسمع نغمة حتى لو وصل إشعار جديد.</p>
                            </div>
                            <div id="notification-permission-banner" class="hidden px-4 py-2.5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-900/30 flex items-center justify-between gap-2" role="alert" aria-live="assertive">
                                <span id="notif-banner-text" class="text-xs font-bold text-amber-800 dark:text-amber-400">فعّل الصوت والإشعارات ليصلك التنبيه فوراً</span>
                                <button type="button" id="enable-notif-btn" class="shrink-0 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary-hover transition">تفعيل 🔔</button>
                            </div>
                            <div id="notification-list" class="notif-feed" role="feed" aria-busy="false" aria-live="polite">
                                <div class="notif-empty" role="status">
                                    <div class="notif-empty-ic" aria-hidden="true">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    </div>
                                    <p class="notif-empty-title">لا توجد إشعارات</p>
                                    <p class="notif-empty-hint">ستظهر الإشعارات الواردة هنا فور وصولها</p>
                                </div>
                            </div>
                            <div class="notif-foot">
                                <a href="{{ route('notifications.index') }}" class="notif-footlink">عرض كل الإشعارات</a>
                                <div class="notif-foot-side">
                                    <span id="notification-status-text" class="hidden notif-footnote"></span>
                                </div>
                            </div>
                        </aside>

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
                <a href="{{ route('profile.show') }}" class="flex items-center gap-3 py-2 hover:opacity-80 transition" aria-label="عرض الملف الشخصي">
                    @if(auth()->user()->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border border-[#e6e9e1] shrink-0">
                    @else
                        <div class="w-9 h-9 rounded-full bg-[#0e6a38] text-white flex items-center justify-center font-bold text-sm shrink-0">{{ auth()->user()->initial }}</div>
                    @endif
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-[#1a2e1f] truncate">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-[#6b7a6e] truncate">{{ '@' . auth()->user()->username }}</div>
                    </div>
                </a>
                <nav class="grid gap-1 pt-2 border-t border-[#e6e9e1]">
                    <a href="{{ route('notes.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('notes.index') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">الملاحظات</a>
                    <a href="{{ route('notes.my') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('notes.my') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">ملاحظاتي</a>
                    <a href="{{ route('general-submissions.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('general-submissions.*') ? 'bg-[#f6f7f5] text-[#0e6a38] font-bold' : 'text-[#4a5a4f]' }}">الإرسالات العامة</a>
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


    <footer class="w-full max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pb-4 mt-auto select-none" aria-label="معلومات الفريق">
        <div class="flex items-center justify-center gap-1.5 text-[11px] text-[#b0bab2] dark:text-[#4a5a4f]">
            <span class="group relative inline-flex items-center gap-1 cursor-help"
                  role="button" tabindex="0"
                  aria-label="معلومات المطورين"
                  aria-expanded="false"
                  aria-controls="dev-tooltip"
                  onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded')==='true'?'false':'true'); document.getElementById('dev-tooltip').classList.toggle('opacity-0'); document.getElementById('dev-tooltip').classList.toggle('translate-y-1');">
                <span class="font-medium text-[#9aa99a] dark:text-[#6b7a6e] group-hover:text-[#6b7a6e] dark:group-hover:text-[#9aa99a] transition-colors duration-200 text-[11px]">
                    المطورون
                </span>


                <span id="dev-tooltip"
                      class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-[170px] sm:w-max max-w-[calc(100vw-32px)]
                               opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 group-active:opacity-100 translate-y-1 group-hover:translate-y-0 group-focus-within:translate-y-0
                               transition-all duration-200 ease-out z-50"
                      role="tooltip">
                    <span class="block px-3 py-2.5 rounded-xl text-[11px] font-medium text-white text-center leading-5
                                 bg-[#1a2e1f]/95 dark:bg-[#0e1a10]/95 backdrop-blur-sm shadow-lg border border-white/10">
                        <span class="block">طارق عبد الرحمن</span>
                        <span class="block w-6 h-px bg-white/20 mx-auto my-1"></span>
                        <span class="block">هادي سهلي</span>
                    </span>
                    <span class="block w-2.5 h-2.5 bg-[#1a2e1f]/95 dark:bg-[#0e1a10]/95 rotate-45 mx-auto -mt-1.5 border-r border-b border-white/10"></span>
                </span>
            </span>
        </div>
    </footer>


    <div id="global-sound-banner" class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-[360px] bg-amber-50 border border-amber-200 rounded-xl shadow-xl p-3 flex items-center gap-3 z-[65]" role="alert" aria-live="polite">
        <div class="w-9 h-9 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center shrink-0 text-amber-700">🔊</div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-amber-900 leading-4" id="global-sound-text">فعّل الصوت ليصلك التنبيه فوراً</p>
            <p class="text-[11px] text-amber-700 leading-4">اضغط مرة واحدة — ضروري لـ iOS/Android</p>
        </div>
        <button type="button" id="global-sound-enable" class="shrink-0 px-3.5 py-2 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] shadow-sm">تفعيل</button>
        <button type="button" id="global-sound-dismiss" class="shrink-0 w-7 h-7 rounded-full hover:bg-amber-100 flex items-center justify-center text-amber-600" aria-label="إغلاق">✕</button>
    </div>

    <script>

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

            window.addEventListener('pageshow', applyStoredTheme);
            window.addEventListener('storage', function(e){ if(e.key==='theme'||e.key==='rasd_theme') applyStoredTheme(); });



            (function(){
                const settingsBtn = document.getElementById('notification-settings-toggle');
                const settingsPanel = document.getElementById('notification-settings-panel');
                if(settingsBtn && settingsPanel){
                    settingsBtn.addEventListener('click', (e)=>{
                        e.stopPropagation();
                        settingsPanel.classList.toggle('hidden');
                    });
                }
                // Drawer open/close, overlay, Escape & aria-expanded are owned by NotificationManager (bindUI).
            })();

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
        function showLoader(){if(!loader)return;loader.classList.remove('hidden');loader.style.opacity='1';loader.style.visibility='visible';loader.style.display='flex';loader.style.pointerEvents='none';}
        function hideLoader(){if(!loader)return;try{loader.style.opacity='0';loader.style.visibility='hidden';loader.style.pointerEvents='none';document.body.style.overflow='';setTimeout(function(){try{loader.classList.add('hidden');loader.style.display='none';loader.style.visibility='hidden';}catch(e){}},200);}catch(e){}}

        setTimeout(hideLoader, 800);
        setTimeout(hideLoader, 1500);
        setTimeout(hideLoader, 3000);
        window.addEventListener('load', hideLoader);
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(hideLoader, 400);
            document.body.style.overflow='';
            setTimeout(()=>{ hideLoader(); }, 1000);
        });

        window.addEventListener('error', hideLoader);
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

    @auth
    <script>
        window.NOTIF_USER_ID = {{ auth()->id() }};
        window.NOTIF_BROADCAST_DRIVER = "{{ config('broadcasting.default') }}";
        window.SHARE_BASE = @json(config('app.share_url') ?: '');
        /* Dynamic share host: manual override, else the current server address as-is (IP or domain). */
        window.shareBase = function(){
            if (window.SHARE_BASE) return window.SHARE_BASE;
            var h = window.location.hostname || '';
            var port = window.location.port ? ':' + window.location.port : '';
            return window.location.protocol + '//' + h + port;
        };
    </script>

    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/js/app.js'])
    @else
        <script type="module" src="/js/notifications/app-bootstrap.js"></script>
    @endif
    @endauth


    <script>
    (function(){
      if (!('serviceWorker' in navigator)) { console.warn('[PWA ROOT] serviceWorker not supported'); return; }
      window.addEventListener('load', function(){
        navigator.serviceWorker.register('/sw.js', {scope: '/'}).then(function(reg){
          console.log('[PWA ROOT] SW registered scope=' + reg.scope);
          return navigator.serviceWorker.ready;
        }).then(function(){
          console.log('[PWA ROOT] ready controller=' + (navigator.serviceWorker.controller ? 'present' : 'none (reload to activate)'));
        }).catch(function(err){
          console.error('[PWA ROOT] SW registration failed', err);
        });
      });
      window.addEventListener('beforeinstallprompt', function(e){
        console.log('[PWA ROOT] beforeinstallprompt fired — installable');
        e.preventDefault();
        window.deferredRootPrompt = e;
        window.__PWA_BEFOREINSTALLPROMPT_FIRED__ = true;
        window.dispatchEvent(new CustomEvent('pwa:ready', {detail:e}));
      });
      window.addEventListener('appinstalled', function(){ console.log('[PWA ROOT] appinstalled'); });

      if (location.search.includes('diag=1')) {
        setTimeout(function(){
          var info = {
            isSecureContext: window.isSecureContext,
            hasSW: 'serviceWorker' in navigator,
            standalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone===true,
            beforePrompt: !!window.__PWA_BEFOREINSTALLPROMPT_FIRED__
          };
          console.log('[PWA ROOT DIAG]', info);
          try {
            var div = document.createElement('div');
            div.style.cssText='position:fixed;bottom:0;left:0;right:0;background:#0f172a;color:#e2e8f0;font:11px monospace;padding:8px;z-index:9999;border-top:2px solid #1f6f4a';
            div.innerHTML='<b>PWA ROOT DIAG</b> isSecureContext='+info.isSecureContext+' hasSW='+info.hasSW+' standalone='+info.standalone+' beforeinstallprompt='+info.beforePrompt+' <button onclick="this.parentElement.remove()" style="float:left;background:#1f6f4a;color:#fff;border:none;padding:4px 8px;border-radius:6px">×</button><div>origin='+location.origin+' manifest=/manifest.json sw=/sw.js scope=/</div>';
            if(!info.isSecureContext) div.innerHTML+='<div style="color:#fbbf24;margin-top:4px">⚠ SecureContext false — Chrome يمنع SW/PWA على http:
            document.body.appendChild(div);
          } catch(e){}
        }, 3000);
      }
    })();
    </script>

    @stack('scripts')

    <div id="printable-a4-doc" class="hidden" data-print-root style="display:none;"></div>
    <div id="rasd-print-document" class="hidden" aria-hidden="true" style="display:none;"></div>
</body>
</html>
