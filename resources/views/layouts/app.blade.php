<!DOCTYPE html>
<html lang="{{ $htmlLocale ?? app()->getLocale() }}" dir="{{ $htmlDir ?? ((($htmlLocale ?? app()->getLocale()) === 'ar') ? 'rtl' : 'ltr') }}">
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
    <meta name="apple-mobile-web-app-title" content="{{ __('ui.app_short') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="{{ __('ui.app_short') }}">
    <meta name="description" content="{{ __('ui.app_name') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/eagle-emblem.svg') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="/pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192.png">
    <meta name="rasd-locale" content="{{ $htmlLocale ?? app()->getLocale() }}">
    <script>(function(){try{var s=null;try{s=localStorage.getItem('rasd_locale');}catch(e){}var c=(document.cookie.match(/(?:^|;\s*)rasd_locale=(ar|en)/)||[])[1];var srv=document.querySelector('meta[name=\"rasd-locale\"]');var srvL=srv?srv.getAttribute('content'):null;var l=s||c||srvL||'ar';if(l!=='ar'&&l!=='en')l='ar';document.documentElement.setAttribute('lang',l);document.documentElement.setAttribute('dir',l==='ar'?'rtl':'ltr');window.RASD_LOCALE=l;}catch(e){window.RASD_LOCALE='ar';}})();</script>
    <script>(function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('rasd_theme');if(t!=='light')document.documentElement.classList.add('dark');}catch(e){document.documentElement.classList.add('dark');}})();</script>
    <script src="/js/loading.js?v=1.0.0"></script>
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
    <title>{{ $title ?? __('ui.app_name') }} — {{ __('ui.ministry') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
    <script type="module" src="/pwa/js/print-layout-engine.js?v=EDITORIAL-v4.2.1-N3-DESCRIPTION-FIX-2026-09-06"></script>
    <style>

        html{scroll-behavior:smooth; scrollbar-gutter:stable; touch-action: pan-x pan-y; -ms-touch-action: pan-x pan-y; overscroll-behavior-y: auto;}
        body{text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased; overflow-x:clip; overscroll-behavior-y: auto; touch-action: pan-x pan-y;}
        *{font-family:'Almarai','Segoe UI',Tahoma,sans-serif}
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
        html.dark #page-loader{background:rgba(30,35,32,0.55) !important}
        html.dark #page-loader .rasd-loader-label{color:#9bb0a0 !important}
        html.dark #page-loader .rasd-loader-card{background:rgba(37,43,38,0.92) !important;border-color:#343a34 !important;box-shadow:0 12px 40px -8px rgba(0,0,0,0.6) !important}

        /* مؤشر الانتظار العام: يُتحكم به عبر كلاس is-visible فقط (بلا animation fill يكسر الظهور) */
        #page-loader{opacity:0;visibility:hidden;pointer-events:none !important;transition:opacity .22s ease,visibility .22s}
        #page-loader.is-visible{opacity:1;visibility:visible}
        #page-loader.hidden{display:none !important}
        .rasd-loader-card{display:flex;align-items:center;gap:12px;background:rgba(253,252,250,0.92);border:1px solid #e6e9e1;border-radius:18px;padding:14px 22px 14px 18px;box-shadow:0 12px 40px -8px rgba(14,60,35,0.25);backdrop-filter:blur(6px)}
        .rasd-spinner{position:relative;width:44px;height:44px;flex:none;border-radius:50%;border:4px solid #dce5dd;border-top-color:#0e6a38;border-left-color:#0e6a38;animation:rasdSpin .7s linear infinite}
        .rasd-spinner::after{content:'';position:absolute;inset:9px;border-radius:50%;background:#0e6a38;opacity:.9;animation:rasdPulse 1.1s ease-in-out infinite}
        @keyframes rasdSpin{to{transform:rotate(360deg)}}
        @keyframes rasdPulse{0%,100%{transform:scale(.55);opacity:.45}50%{transform:scale(1);opacity:.95}}
        .rasd-loader-label{font-size:13px;font-weight:800;color:#4a5a4f;white-space:nowrap}
        .rasd-loader-dots::after{content:'';animation:rasdDots 1.2s steps(4) infinite}
        @keyframes rasdDots{0%{content:''}25%{content:'.'}50%{content:'..'}75%{content:'...'}}
        .rasd-loader-bar{position:absolute;top:0;right:0;left:0;height:3px;overflow:hidden;background:transparent}
        .rasd-loader-bar::before{content:'';position:absolute;top:0;bottom:0;width:38%;border-radius:99px;background:linear-gradient(90deg,transparent,#0e6a38,#4ade80,transparent);animation:rasdBar 1s ease-in-out infinite}
        @keyframes rasdBar{0%{transform:translateX(110%)}100%{transform:translateX(-320%)}}
        @media (prefers-reduced-motion: reduce){
            .rasd-spinner,.rasd-spinner::after,.rasd-loader-bar::before,.rasd-loader-dots::after{animation:none !important}
            #page-loader{transition:none}
        }

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


        .topbar-brand .brand-emblem{height:36px;width:auto;max-width:72px;flex:none}
        @media(min-width:768px){.topbar-start{column-gap:24px}}
        @media(min-width:1024px){.topbar-start{column-gap:32px}}
        /* لغة الواجهة EN: خط لاتيني + ضبط اتجاه */
        html[lang="en"] *{font-family:'Inter','Almarai','Segoe UI',Tahoma,sans-serif}
        html[lang="en"] body{letter-spacing:0}
        html[lang="en"] .font-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    </style>
</head>
    <div id="page-loader" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-[#eceee9]/70 backdrop-blur-[2px]" role="status" aria-label="{{ __('ui.loading') }}" aria-hidden="true" style="pointer-events:none">
        <div class="rasd-loader-bar" aria-hidden="true"></div>
        <div class="rasd-loader-card">
            <div class="rasd-spinner" aria-hidden="true"></div>
            <span class="rasd-loader-label">{{ __('ui.loading') }}<span class="rasd-loader-dots" aria-hidden="true"></span></span>
        </div>
    </div>
<body class="bg-[#eceee9] min-h-screen flex flex-col text-[#1a2e1f] antialiased overflow-x-hidden selection:bg-[#0e6a38] selection:text-[#fdfcfa]">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:right-3 bg-[#0e6a38] text-white px-4 py-2.5 rounded-xl z-[100] text-sm font-bold shadow-lg">{{ __('ui.skip_to_content') }}</a>

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
                        <img src="{{ asset('images/eagle-emblem.svg') }}" alt="شعار النسر السوري" data-no-translate class="brand-emblem h-9 w-auto max-w-[72px] object-contain shrink-0 drop-shadow-sm" loading="eager" fetchpriority="high" decoding="async">
                        <span class="font-bold text-[13px] tracking-tight text-[#1a2e1f] hidden lg:block whitespace-nowrap">{{ __('ui.ministry') }}</span>
                    </a>

                    <nav class="hidden md:flex flex-nowrap items-center gap-1 shrink-0 whitespace-nowrap" aria-label="{{ __('ui.main_nav') }}">
                        <a href="{{ route('notes.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('notes.index') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_notes') }}
                        </a>
                        <a href="{{ route('notes.my') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('notes.my') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_my_notes') }}
                        </a>
                        <a href="{{ route('gallery.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('gallery.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_gallery') }}
                        </a>
                        <a href="{{ route('general-submissions.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('general-submissions.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_submissions') }}
                        </a>
                        <a href="{{ route('reports.index') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('reports.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_reports') }}
                        </a>
                        <a href="{{ route('ranking') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('ranking') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_ranking') }}
                        </a>
                        <a href="{{ route('profile.show') }}" class="px-2 xl:px-3 py-1.5 rounded-lg text-[13px] whitespace-nowrap shrink-0 transition {{ request()->routeIs('profile.*') ? 'text-[#0e6a38] font-bold' : 'text-[#6b7a6e] hover:text-[#1a2e1f] font-medium' }}">
                            {{ __('ui.nav_account') }}
                        </a>
                    </nav>
                </div>

                <div class="flex flex-nowrap items-center gap-2 shrink-0">
                    <button type="button" id="lang-toggle" class="h-8 px-2 rounded-lg flex items-center justify-center gap-1 text-[12px] font-extrabold text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] transition border border-transparent" aria-label="{{ __('ui.language_title') }}" title="{{ __('ui.language_title') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.5 0 4.5-4 4.5-9S14.5 3 12 3 7.5 7 7.5 12s2 9 4.5 9zM3.5 9h17M3.5 15h17"/></svg>
                        <span id="lang-toggle-label">{{ ($htmlLocale ?? app()->getLocale()) === 'ar' ? 'EN' : 'ع' }}</span>
                    </button>
                    <button type="button" id="theme-toggle" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] transition" aria-label="{{ __('ui.dark_mode') }}" title="{{ __('ui.toggle_theme') }}">
                        <svg class="w-4 h-4 sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg class="w-4 h-4 moon-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>


                    <button type="button" id="notification-bell" class="relative w-8 h-8 rounded-lg flex items-center justify-center text-text-secondary dark:text-text-secondary hover:text-text-primary dark:hover:text-text-primary hover:bg-surface-muted dark:hover:bg-surface-muted transition" aria-label="{{ __('ui.notifications') }}" aria-haspopup="dialog" aria-expanded="false" aria-controls="notification-drawer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span id="notification-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center border-2 border-surface-elevated dark:border-surface-elevated" aria-live="polite" aria-atomic="true">0</span>
                    </button>
                    <div id="notification-overlay" class="notif-overlay hidden" aria-hidden="true"></div>
                    <aside id="notification-drawer" class="notif-drawer hidden" role="dialog" aria-modal="true" aria-label="{{ __('ui.notif_center') }}" aria-hidden="true">
                        <div class="notif-topbar">
                            <div class="notif-topbar-main">
                                <h2 class="notif-heading">{{ __('ui.notifications') }}</h2>
                                <span id="notification-drawer-count" class="notif-count hidden">0</span>
                                <span id="notification-connection-status" class="hidden notif-conn" title="{{ __('ui.connected') }}"></span>
                            </div>
                            <div class="notif-topbar-actions">
                                <button type="button" id="notification-settings-toggle" class="notif-iconbtn" aria-label="{{ __('ui.notif_settings') }}" title="{{ __('ui.settings_short') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" id="notification-close" class="notif-iconbtn" aria-label="{{ __('ui.close_notif_drawer') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="notif-subbar">
                            <button type="button" id="mark-all-read" class="notif-markall">{{ __('ui.mark_all_read') }}</button>
                        </div>

                            <div id="notification-settings-panel" class="hidden px-4 py-3 bg-surface-elevated border-b border-border space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <label for="notif-sound-toggle" class="text-xs font-bold text-text-primary dark:text-text-primary flex items-center gap-1.5 cursor-pointer"><span aria-hidden="true">🔊</span> {{ __('ui.notif_sounds') }}</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="notif-sound-toggle" class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label for="notif-volume-slider" class="text-xs font-bold text-text-primary dark:text-text-primary shrink-0">{{ __('ui.volume_level') }}</label>
                                    <input type="range" id="notif-volume-slider" min="0" max="100" value="70" class="flex-1 accent-primary">
                                    <span id="notif-volume-label" class="text-xs font-mono text-text-muted dark:text-text-muted w-8 text-left">70%</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="notif-toast-toggle" class="text-xs font-bold text-text-primary dark:text-text-primary flex items-center gap-1.5 cursor-pointer"><span aria-hidden="true">💬</span> {{ __('ui.instant_toast') }}</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="notif-toast-toggle" class="sr-only peer" checked>
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </div>
                                <p class="text-[11px] text-text-muted dark:text-text-muted leading-4">{{ __('ui.autosaved_short') }}</p>
                            </div>
                            <div id="notification-permission-banner" class="hidden px-4 py-2.5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-900/30 flex items-center justify-between gap-2" role="alert" aria-live="assertive">
                                <span id="notif-banner-text" class="text-xs font-bold text-amber-800 dark:text-amber-400">{{ __('ui.enable_alerts') }}</span>
                                <button type="button" id="enable-notif-btn" class="shrink-0 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary-hover transition">{{ __('ui.enable') }}</button>
                            </div>
                            <div id="notification-list" class="notif-feed" role="feed" aria-busy="false" aria-live="polite">
                                <div class="notif-empty" role="status">
                                    <div class="notif-empty-ic" aria-hidden="true">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    </div>
                                    <p class="notif-empty-title">{{ __('ui.no_notifications') }}</p>
                                </div>
                            </div>
                            <div class="notif-foot">
                                <a href="{{ route('notifications.index') }}" class="notif-footlink">{{ __('ui.view_all_notif') }}</a>
                                <div class="notif-foot-side">
                                    <span id="notification-status-text" class="hidden notif-footnote"></span>
                                </div>
                            </div>
                        </aside>

                    <a href="{{ route('profile.show') }}" class="hidden sm:flex min-w-0 shrink-0 items-center gap-2.5 rounded-full border border-[#e6e9e1] bg-white/80 py-1 pe-3 ps-1 shadow-sm transition-all duration-200 hover:-translate-y-px hover:border-[#0e6a38]/30 hover:shadow-md" aria-label="{{ __('ui.profile_aria') }}" title="{{ auth()->user()->name }}">
                        <span class="relative shrink-0">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" data-no-translate class="h-8 w-8 rounded-full object-cover ring-2 ring-[#0e6a38]/20">
                            @else
                                <span data-no-translate aria-hidden="true" class="flex h-8 w-8 items-center justify-center rounded-full bg-[linear-gradient(135deg,#0e6a38_0%,#149a52_100%)] text-sm font-extrabold text-white ring-2 ring-[#0e6a38]/20">{{ auth()->user()->initial }}</span>
                            @endif
                            <span aria-hidden="true" class="absolute bottom-0 end-0 h-2.5 w-2.5 rounded-full border-2 border-[#fdfcfa] bg-emerald-500"></span>
                        </span>
                        <span class="hidden min-w-0 flex-col leading-tight lg:flex">
                            <span dir="auto" data-no-translate class="max-w-[130px] truncate whitespace-nowrap text-[13px] font-extrabold text-[#1a2e1f]">{{ auth()->user()->name }}</span>
                            <span class="max-w-[130px] truncate whitespace-nowrap text-[10px] font-bold text-[#6b7a6e]">{{ auth()->user()->isReportWriter() ? __('ui.role_writer') : __('ui.role_monitor') }}</span>
                        </span>
                    </a>
                    <a href="{{ route('profile.show') }}" class="flex shrink-0 items-center sm:hidden" aria-label="{{ __('ui.profile_aria') }}">
                        <span class="relative shrink-0">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" data-no-translate class="h-7 w-7 rounded-full object-cover ring-2 ring-[#0e6a38]/20">
                            @else
                                <span data-no-translate aria-hidden="true" class="flex h-7 w-7 items-center justify-center rounded-full bg-[linear-gradient(135deg,#0e6a38_0%,#149a52_100%)] text-xs font-extrabold text-white ring-2 ring-[#0e6a38]/20">{{ auth()->user()->initial }}</span>
                            @endif
                            <span aria-hidden="true" class="absolute bottom-0 end-0 h-2 w-2 rounded-full border-2 border-[#fdfcfa] bg-emerald-500"></span>
                        </span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-[12px] font-medium text-[#6b7a6e] hover:text-[#1a2e1f] px-2 py-1.5 transition">{{ __('ui.logout') }}</button>
                    </form>

                    <button type="button" id="mobile-menu-btn" class="md:hidden w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:bg-[#f6f7f5] transition" aria-expanded="false" aria-controls="mobile-menu" aria-label="{{ __('ui.menu') }}">
                        <svg class="w-5 h-5 menu-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg class="w-5 h-5 menu-close hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu-overlay" class="menu-overlay hidden md:hidden" aria-hidden="true"></div>
        <aside id="mobile-menu" class="menu-drawer md:hidden hidden" role="dialog" aria-modal="true" aria-label="{{ __('ui.menu') }}" aria-hidden="true">
            <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-3 border-b border-[#e6e9e1]">
                <a href="{{ route('profile.show') }}" class="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80 transition" aria-label="{{ __('ui.profile_aria') }}">
                    <span class="relative shrink-0">
                        @if(auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" data-no-translate class="h-10 w-10 rounded-full object-cover ring-2 ring-[#0e6a38]/20">
                        @else
                            <span data-no-translate aria-hidden="true" class="flex h-10 w-10 items-center justify-center rounded-full bg-[linear-gradient(135deg,#0e6a38_0%,#149a52_100%)] text-base font-extrabold text-white ring-2 ring-[#0e6a38]/20">{{ auth()->user()->initial }}</span>
                        @endif
                        <span aria-hidden="true" class="absolute bottom-0 end-0 h-3 w-3 rounded-full border-2 border-[#fdfcfa] bg-emerald-500"></span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span dir="auto" data-no-translate class="block truncate text-sm font-extrabold leading-5 text-[#1a2e1f]">{{ auth()->user()->name }}</span>
                        <span dir="auto" data-no-translate class="block truncate text-xs font-medium leading-4 text-[#6b7a6e]">{{ '@' . auth()->user()->username }}</span>
                    </span>
                </a>
                <button type="button" id="mobile-menu-close" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#6b7a6e] hover:text-[#1a2e1f] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e] transition shrink-0" aria-label="{{ __('ui.close') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="menu-body px-4 py-3 grid gap-1 content-start" aria-label="{{ __('ui.menu') }}">
                <a href="{{ route('notes.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('notes.index') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_notes') }}</a>
                <a href="{{ route('notes.my') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('notes.my') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_my_notes') }}</a>
                <a href="{{ route('gallery.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('gallery.*') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_gallery') }}</a>
                <a href="{{ route('general-submissions.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('general-submissions.*') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_submissions') }}</a>
                <a href="{{ route('reports.index') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('reports.*') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_reports') }}</a>
                <a href="{{ route('ranking') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('ranking') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_ranking') }}</a>
                <a href="{{ route('profile.show') }}" class="px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('profile.*') ? 'bg-[#f6f7f5] dark:bg-[#1e3328] text-[#0e6a38] dark:text-[#4ade80] font-bold' : 'text-[#4a5a4f] dark:text-[#9bb0a0] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e]' }}">{{ __('ui.nav_account') }}</a>
            </nav>
            <div class="px-4 py-3 border-t border-[#e6e9e1]">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-start px-3 py-2.5 rounded-lg text-sm font-medium text-[#9aa99a] dark:text-[#8a9a8a] hover:text-red-600 dark:hover:text-[#f08080] hover:bg-[#f6f7f5] dark:hover:bg-[#2e352e] transition">{{ __('ui.logout') }}</button>
                </form>
            </div>
        </aside>
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
                <button type="button" onclick="this.parentElement.remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#f6f7f5] flex items-center justify-center text-[#9aa99a] hover:text-[#1a2e1f] transition" aria-label="{{ __('ui.close') }}">
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
                <button type="button" onclick="this.parentElement.remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef3c7] flex items-center justify-center text-[#9aa99a] hover:text-[#1a2e1f] transition" aria-label="{{ __('ui.close') }}">
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
                        <h3 class="text-[13px] font-extrabold text-[#1a2e1f]">{{ __('ui.form_errors_title') }}</h3>
                        <ul class="mt-1.5 list-disc list-inside space-y-1 text-[13px] leading-6 text-[#4a5a4f] marker:text-[#c41e1e]/60">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                    <button type="button" onclick="this.closest('div[role=alert]').remove()" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef2f2] flex items-center justify-center text-[#94a8a0] transition" aria-label="{{ __('ui.close') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        @endif
        @yield('content')
    </main>


    <footer class="w-full max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pb-4 mt-auto select-none" aria-label="{{ __('ui.team_info') }}">
        <div class="flex items-center justify-center gap-1.5 text-[11px] text-[#b0bab2] dark:text-[#4a5a4f]">
            <span class="group relative inline-flex items-center gap-1 cursor-help"
                  role="button" tabindex="0"
                  aria-label="{{ __('ui.dev_info') }}"
                  aria-expanded="false"
                  aria-controls="dev-tooltip"
                  onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded')==='true'?'false':'true'); document.getElementById('dev-tooltip').classList.toggle('opacity-0'); document.getElementById('dev-tooltip').classList.toggle('translate-y-1');">
                <span class="font-medium text-[#9aa99a] dark:text-[#6b7a6e] group-hover:text-[#6b7a6e] dark:group-hover:text-[#9aa99a] transition-colors duration-200 text-[11px]">
                    {{ __('ui.developers') }}
                </span>


                <span id="dev-tooltip"
                      class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-[170px] sm:w-max max-w-[calc(100vw-32px)]
                               opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 group-active:opacity-100 translate-y-1 group-hover:translate-y-0 group-focus-within:translate-y-0
                               transition-all duration-200 ease-out z-50"
                      role="tooltip">
                    <span class="block px-3 py-2.5 rounded-xl text-[11px] font-medium text-white text-center leading-5
                                 bg-[#1a2e1f]/95 dark:bg-[#0e1a10]/95 backdrop-blur-sm shadow-lg border border-white/10">
                        <span class="block">طارق عبد الرحمن</span>
                        <span class="block text-[10px] font-normal text-white/50 tracking-wider" dir="ltr">0993832567</span>
                        <span class="block w-8 h-px bg-white/20 mx-auto my-1.5"></span>
                        <span class="block">هادي سهلي</span>
                        <span class="block text-[10px] font-normal text-white/50 tracking-wider" dir="ltr">0962532331</span>
                    </span>
                    <span class="block w-2.5 h-2.5 bg-[#1a2e1f]/95 dark:bg-[#0e1a10]/95 rotate-45 mx-auto -mt-1.5 border-r border-b border-white/10"></span>
                </span>
            </span>
        </div>
    </footer>


    <div id="global-sound-banner" class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-[320px] bg-amber-50 border border-amber-200 rounded-xl shadow-xl p-3 flex items-center gap-3 z-[65]" role="alert" aria-live="polite">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-amber-900 leading-4" id="global-sound-text">{{ __('ui.enable_sound_short') }}</p>
        </div>
        <button type="button" id="global-sound-enable" class="shrink-0 px-3.5 py-2 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] shadow-sm">{{ __('ui.enable') }}</button>
        <button type="button" id="global-sound-dismiss" class="shrink-0 w-7 h-7 rounded-full hover:bg-amber-100 flex items-center justify-center text-amber-600" aria-label="{{ __('ui.close') }}">✕</button>
    </div>
    <div id="rasd-toast" class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-[90] max-w-[calc(100vw-2rem)]" role="status" aria-live="polite">
        <div id="rasd-toast-inner" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1a2e1f]/95 text-white text-[13px] font-bold shadow-xl border border-white/10 backdrop-blur-sm"></div>
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
                    +'<div class="flex-1 min-w-0"><h3 class="text-[13px] font-extrabold text-[#1a2e1f]">{{ __('ui.attach_partial_short') }}</h3>'
                    +'<ul class="mt-1.5 list-disc list-inside space-y-1 text-[13px] leading-6 text-[#4a5a4f]"></ul></div>'
                    +'<button type="button" class="shrink-0 w-8 h-8 rounded-full hover:bg-[#fef3c7] flex items-center justify-center" aria-label="{{ __('ui.close') }}">✕</button>';
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

            })();

        })();
        (function(){
            var mBtn=document.getElementById('mobile-menu-btn'),mMenu=document.getElementById('mobile-menu'),mOverlay=document.getElementById('mobile-menu-overlay'),mClose=document.getElementById('mobile-menu-close');
            if(!mBtn||!mMenu) return;
            var mOpen=false,hideT=null,prevOverflow='';
            function setBtn(open){mBtn.setAttribute('aria-expanded',open?'true':'false');var o=mBtn.querySelector('.menu-open'),c=mBtn.querySelector('.menu-close');if(o)o.classList.toggle('hidden',open);if(c)c.classList.toggle('hidden',!open);}
            function openMenu(){
                if(mOpen) return; mOpen=true; clearTimeout(hideT);
                prevOverflow=document.body.style.overflow;
                if(mOverlay)mOverlay.classList.remove('hidden');
                mMenu.classList.remove('hidden'); mMenu.setAttribute('aria-hidden','false');
                requestAnimationFrame(function(){requestAnimationFrame(function(){
                    if(!mOpen) return;
                    if(mOverlay)mOverlay.classList.add('is-visible');
                    mMenu.classList.add('is-open');
                });});
                document.body.style.overflow='hidden'; setBtn(true);
                try{mClose&&mClose.focus({preventScroll:true});}catch(e){try{mClose&&mClose.focus();}catch(_){}}
            }
            function closeMenu(returnFocus){
                if(!mOpen&&mMenu.classList.contains('hidden')) return; mOpen=false; clearTimeout(hideT);
                if(mOverlay)mOverlay.classList.remove('is-visible');
                mMenu.classList.remove('is-open'); mMenu.setAttribute('aria-hidden','true'); setBtn(false);
                var steal=returnFocus!==false&&mMenu.contains(document.activeElement);
                hideT=setTimeout(function(){
                    if(mOpen) return;
                    if(mOverlay)mOverlay.classList.add('hidden');
                    mMenu.classList.add('hidden');
                    if(!document.querySelector('[data-modal]:not(.hidden)')&&!document.querySelector('#notification-drawer.is-open')){document.body.style.overflow=prevOverflow||'';}
                    if(steal){try{mBtn.focus({preventScroll:true});}catch(e){}}
                },340);
            }
            mBtn.addEventListener('click',function(){mOpen?closeMenu(false):openMenu();});
            if(mClose)mClose.addEventListener('click',function(){closeMenu();});
            if(mOverlay)mOverlay.addEventListener('click',function(){closeMenu(false);});
            document.addEventListener('click',function(e){if(!mOpen)return;if(!mBtn.contains(e.target)&&!mMenu.contains(e.target)){closeMenu(false);}});
            var bell=document.getElementById('notification-bell');
            if(bell)bell.addEventListener('click',function(){closeMenu(false);});
            document.addEventListener('keydown',function(e){if(e.key==='Escape'&&mOpen){closeMenu();}});
            window.addEventListener('resize',function(){if(window.innerWidth>=768)closeMenu(false);});
            window.__closeMobileMenu=closeMenu;
        })();
        setTimeout(()=>{document.querySelectorAll('[role="alert"]').forEach(el=>{if(el.textContent.includes('{{ __('ui.done_keyword') }}') || el.textContent.includes('{{ __('ui.success_keyword') }}')){el.style.transition='opacity .4s,transform .4s';el.style.opacity='0';el.style.transform='translateY(-6px)';setTimeout(()=>el.remove(),400);}});},5000);
        document.querySelectorAll('form:not([data-ajax])').forEach(form=>{form.addEventListener('submit',function(e){const b=this.querySelector('button[type="submit"]:not([formnovalidate])');if(!b||b.dataset.noLoader)return;if(b.disabled&&b.dataset.loaderOn)return;setTimeout(()=>{if(e.defaultPrevented)return;if(!b.dataset.origHtml)b.dataset.origHtml=b.innerHTML;b.dataset.loaderOn='1';b.disabled=true;const __t=@json(__('ui.processing'));b.innerHTML='<span class="inline-flex items-center gap-2"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> '+__t+'</span>';b.classList.add('opacity-80','cursor-wait');},0);});});
        window.addEventListener('pageshow',()=>{document.querySelectorAll('button[data-loader-on]').forEach(b=>{if(b.dataset.origHtml!=null)b.innerHTML=b.dataset.origHtml;b.disabled=false;b.classList.remove('opacity-80','cursor-wait');delete b.dataset.loaderOn;delete b.dataset.origHtml;});});
        document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('[data-modal]').forEach(m=>m.classList.add('hidden'));document.body.style.overflow='';}});
        function openModal(id){const el=document.getElementById(id);if(!el)return;el.classList.remove('hidden');el.style.display='';el.style.visibility='';document.body.style.overflow='hidden';}
        function closeModal(id){const el=document.getElementById(id);if(!el)return;el.classList.add('hidden');el.style.display='';document.body.style.overflow='';}
        window.openModal=openModal;window.closeModal=closeModal;

        window.toast=function(msg){
            try{
                var box=document.getElementById('rasd-toast');var inner=document.getElementById('rasd-toast-inner');
                if(!box||!inner){return;}
                inner.textContent=String(msg||'').slice(0,120);
                box.classList.remove('hidden');
                clearTimeout(window.__toastT);
                window.__toastT=setTimeout(function(){box.classList.add('hidden');},2600);
            }catch(e){}
        };
        window.notifyError=function(msg){window.toast(msg||'{{ __('ui.error_occurred') }}');};

        window.copyTextToClipboard=function(text){
            return new Promise(function(resolve){
                text=String(text==null?'':text);
                function legacy(){
                    try{
                        var ta=document.createElement('textarea');
                        ta.value=text; ta.setAttribute('readonly','');
                        ta.style.cssText='position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
                        document.body.appendChild(ta);
                        ta.focus(); ta.select();
                        try{ ta.setSelectionRange(0, ta.value.length); }catch(e){}
                        var ok=false;
                        try{ ok=document.execCommand('copy'); }catch(e){ ok=false; }
                        document.body.removeChild(ta);
                        resolve(!!ok);
                    }catch(e){ resolve(false); }
                }
                try{
                    if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(text).then(function(){resolve(true);}, legacy); }
                    else legacy();
                }catch(e){ legacy(); }
            });
        };
        /* توافق خلفي: نفس الأسماء القديمة، لكن عبر RASDLoading الذكي (بلا animation fill يكسر الظهور) */
        function showLoader(){try{if(window.RASDLoading){window.RASDLoading.show();return;}}catch(e){}var l=document.getElementById('page-loader');if(!l)return;l.classList.remove('hidden');requestAnimationFrame(function(){l.classList.add('is-visible');l.setAttribute('aria-hidden','false');});}
        function hideLoader(){try{if(window.RASDLoading){window.RASDLoading.reset();return;}}catch(e){}var l=document.getElementById('page-loader');if(!l)return;l.classList.remove('is-visible');l.setAttribute('aria-hidden','true');setTimeout(function(){l.classList.add('hidden');},250);}
        window.showLoader=showLoader;window.hideLoader=hideLoader;

        document.addEventListener('DOMContentLoaded', function(){
            document.body.style.overflow='';
            hideLoader();
        });

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
                        window.toast(Array.isArray(first) ? first[0] : first);
                    } else {
                        window.toast(data.message || '{{ __('ui.error_occurred') }}');
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
            if(!info.isSecureContext) div.innerHTML+='<div style="color:#fbbf24;margin-top:4px">⚠ SecureContext false — ' + (document.documentElement.lang === 'en' ? 'Chrome blocks SW/PWA over http:' : 'Chrome يمنع SW/PWA على http:');
            document.body.appendChild(div);
          } catch(e){}
        }, 3000);
      }
    })();
    </script>

    @stack('scripts')
    <script src="{{ asset('js/rasd-i18n-v2.js') }}?v=2.2.0" defer></script>

    <div id="printable-a4-doc" class="hidden" data-print-root style="display:none;"></div>
    <div id="rasd-print-document" class="hidden" aria-hidden="true" style="display:none;"></div>
</body>
</html>
