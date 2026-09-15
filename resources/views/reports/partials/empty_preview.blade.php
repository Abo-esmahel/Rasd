{{-- معاينة ذكية للتقرير الفارغ (0 ملاحظات): رسم فقط بلا حشو نصي --}}
<div class="bg-white dark:bg-[#232926] border border-[#e6e9e1] dark:border-[#333b34] rounded-xl overflow-hidden">
    <style>
        .ep-scope{
            --ep-bg1:#f4f7f4; --ep-bg2:#e7eee7;
            --ep-grid:rgba(14,106,56,.10);
            --ep-doc:#ffffff; --ep-edge:#d9e2d9; --ep-dash:#b9c6b9;
            --ep-ink:#1a2e1f; --ep-mut:#8a9a8e; --ep-line:#e2e8e2;
            --ep-acc:#0e6a38; --ep-accsoft:#e8f3ec;
            --ep-zero:#c44040; --ep-amber:#b45309;
            --ep-panel:#fdfcfa; --ep-scan:rgba(14,106,56,.14);
        }
        html.dark .ep-scope{
            --ep-bg1:#222823; --ep-bg2:#1b211c;
            --ep-grid:rgba(148,196,160,.10);
            --ep-doc:#2a312b; --ep-edge:#3a443b; --ep-dash:#55645a;
            --ep-ink:#e7ece5; --ep-mut:#8a9a8e; --ep-line:#333b34;
            --ep-acc:#4ade80; --ep-accsoft:#1e3328;
            --ep-zero:#f08080; --ep-amber:#e8c24a;
            --ep-panel:#242b25; --ep-scan:rgba(74,222,128,.12);
        }
        .ep-scope .ep-dashflow{ stroke-dasharray:7 6; animation:ep-flow 1.6s linear infinite; }
        @keyframes ep-flow{ to{ stroke-dashoffset:-26; } }
        .ep-scope .ep-pulse{ animation:ep-pulse 2.2s ease-in-out infinite; transform-box:fill-box; transform-origin:center; }
        @keyframes ep-pulse{ 0%,100%{ transform:scale(1); opacity:1; } 50%{ transform:scale(1.08); opacity:.85; } }
        .ep-scope .ep-sweep{ animation:ep-sweep 3.2s linear infinite; transform-box:fill-box; transform-origin:18% 62%; }
        @keyframes ep-sweep{ from{ transform:rotate(0deg);} to{ transform:rotate(360deg);} }
        @media (prefers-reduced-motion: reduce){ .ep-scope .ep-dashflow,.ep-scope .ep-pulse,.ep-scope .ep-sweep{ animation:none !important; } }
    </style>
    <svg viewBox="0 0 640 340" class="ep-scope block w-full h-auto" role="img" aria-label="{{ __('ui.empty_preview_title') }}">
        <title>{{ __('ui.empty_preview_title') }}</title>
        <defs>
            <linearGradient id="epg2" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" style="stop-color:var(--ep-bg1)"/>
                <stop offset="1" style="stop-color:var(--ep-bg2)"/>
            </linearGradient>
        </defs>
        <rect width="640" height="340" style="fill:var(--ep-bg1)"/>
        <rect width="640" height="340" style="fill:url(#epg2)" opacity="0.55"/>
        <g style="stroke:var(--ep-grid)" stroke-width="1">
            <line x1="0" y1="70" x2="640" y2="70"/><line x1="0" y1="140" x2="640" y2="140"/><line x1="0" y1="210" x2="640" y2="210"/><line x1="0" y1="280" x2="640" y2="280"/>
            <line x1="128" y1="0" x2="128" y2="340"/><line x1="256" y1="0" x2="256" y2="340"/><line x1="384" y1="0" x2="384" y2="340"/><line x1="512" y1="0" x2="512" y2="340"/>
        </g>

        {{-- هيكل وثيقة التقرير: 3 أقسام فارغة بدقة --}}
        <g>
            <rect x="56" y="36" width="220" height="268" rx="12" style="fill:var(--ep-doc);stroke:var(--ep-edge)" stroke-width="2"/>
            {{-- ترويسة الوثيقة --}}
            <rect x="76" y="52" width="180" height="12" rx="6" style="fill:var(--ep-acc)" opacity="0.9"/>
            <rect x="76" y="70" width="118" height="8" rx="4" style="fill:var(--ep-mut)" opacity="0.65"/>
            {{-- 1) الملخص --}}
            <rect x="76" y="90" width="64" height="14" rx="7" style="fill:var(--ep-accsoft)"/>
            <rect x="76" y="110" width="180" height="7" rx="3.5" style="fill:var(--ep-line)"/>
            <rect x="76" y="121" width="132" height="7" rx="3.5" style="fill:var(--ep-line)"/>
            {{-- 2) جدول الملاحظات: 3 صفوف فارغة + شارة 0 --}}
            <rect x="76" y="140" width="96" height="14" rx="7" style="fill:var(--ep-accsoft)"/>
            <g style="stroke:var(--ep-dash)" stroke-width="1.6" stroke-dasharray="6 4" fill="none">
                <rect x="76" y="160" width="180" height="20" rx="6"/>
                <rect x="76" y="184" width="180" height="20" rx="6"/>
                <rect x="76" y="208" width="180" height="20" rx="6"/>
            </g>
            <g style="fill:var(--ep-mut)" opacity="0.8">
                <circle cx="90" cy="170" r="4"/><circle cx="90" cy="194" r="4"/><circle cx="90" cy="218" r="4"/>
            </g>
            {{-- 3) التوصيات --}}
            <rect x="76" y="238" width="76" height="14" rx="7" style="fill:var(--ep-accsoft)"/>
            <rect x="76" y="258" width="180" height="7" rx="3.5" style="fill:var(--ep-line)"/>
            <rect x="76" y="271" width="110" height="7" rx="3.5" style="fill:var(--ep-line)"/>
            {{-- سطر التوقيع --}}
            <line x1="76" y1="292" x2="256" y2="292" style="stroke:var(--ep-edge)" stroke-width="1.5"/>
            {{-- شارة الصفر فوق الجدول --}}
            <g class="ep-pulse">
                <circle cx="262" cy="150" r="24" style="fill:var(--ep-zero)" stroke="#fff" stroke-width="3.5"/>
                <text x="262" y="160" font-size="26" font-weight="800" fill="#fff" text-anchor="middle" font-family="sans-serif">0</text>
            </g>
        </g>

        {{-- تدفق بلا بيانات: من الخط الزمني إلى الوثيقة --}}
        <line x1="292" y1="194" x2="352" y2="194" class="ep-dashflow" style="stroke:var(--ep-dash)" stroke-width="2.5" fill="none"/>
        <g style="stroke:var(--ep-zero)" stroke-width="2.5" stroke-linecap="round">
            <line x1="316" y1="187" x2="328" y2="199"/>
            <line x1="328" y1="187" x2="316" y2="199"/>
        </g>

        {{-- لوحة الخط الزمني 24h بلا وقائع + مسح راداري --}}
        <g>
            <rect x="352" y="36" width="232" height="268" rx="12" style="fill:var(--ep-panel);stroke:var(--ep-edge)" stroke-width="2"/>
            <rect x="372" y="52" width="150" height="12" rx="6" style="fill:var(--ep-acc)" opacity="0.9"/>
            {{-- محور 24 ساعة: تكات فارغة --}}
            <g style="fill:var(--ep-line)">
                @for($i = 0; $i < 12; $i++)
                <rect x="{{ 372 + $i * 17 }}" y="86" width="9" height="26" rx="4.5"/>
                @endfor
            </g>
            <line x1="372" y1="122" x2="564" y2="122" style="stroke:var(--ep-edge)" stroke-width="2"/>
            {{-- رادار المسح --}}
            <g>
                <circle cx="468" cy="205" r="52" fill="none" style="stroke:var(--ep-edge)" stroke-width="2"/>
                <circle cx="468" cy="205" r="36" fill="none" style="stroke:var(--ep-edge)" stroke-width="1.5" stroke-dasharray="4 4"/>
                <circle cx="468" cy="205" r="5" style="fill:var(--ep-acc)"/>
                <g class="ep-sweep">
                    <line x1="468" y1="205" x2="468" y2="157" style="stroke:var(--ep-acc)" stroke-width="3" stroke-linecap="round" opacity="0.9"/>
                </g>
                <rect x="416" y="153" width="104" height="104" rx="52" style="fill:var(--ep-scan)"/>
            </g>
            {{-- عدسة فوق الفراغ --}}
            <g>
                <circle cx="540" cy="248" r="22" fill="none" style="stroke:var(--ep-mut)" stroke-width="4"/>
                <line x1="556" y1="264" x2="572" y2="280" style="stroke:var(--ep-mut)" stroke-width="7" stroke-linecap="round"/>
                <line x1="532" y1="248" x2="548" y2="248" style="stroke:var(--ep-dash)" stroke-width="2.5" stroke-linecap="round"/>
            </g>
            {{-- شريط حالة المسودة --}}
            <g>
                <circle cx="384" cy="288" r="5" style="fill:var(--ep-amber)"/>
                <circle cx="402" cy="288" r="5" style="fill:var(--ep-line)"/>
                <circle cx="420" cy="288" r="5" style="fill:var(--ep-line)"/>
            </g>
        </g>
        <rect x="0.5" y="0.5" width="639" height="339" rx="4" fill="none" style="stroke:var(--ep-edge)" stroke-width="1.5"/>
    </svg>
</div>
