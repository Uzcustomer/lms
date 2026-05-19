<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test markazi — Komp № displeyi</title>
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { background: #0b1220; color: #e5e7eb; height: 100vh; overflow: hidden; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        @keyframes nowPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55); }
            50%      { box-shadow: 0 0 0 14px rgba(16, 185, 129, 0); }
        }
        @keyframes imminentPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.55); }
            50%      { box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
        }
        .pulse-now { animation: nowPulse 1.6s ease-in-out infinite; }
        .pulse-imminent { animation: imminentPulse 2.0s ease-in-out infinite; }
        ::-webkit-scrollbar { display: none; }

        /* Kartochka — qat'iy o'lcham (mazmun ko'paysa ham shrift kichaymaydi) */
        .tv-card {
            min-height: 150px;
            max-height: 150px;
        }
        /* Auto-fit grid: ko'p kartochka bo'lsa qatorga 5-6 ta tushadi, lekin
           kartochkalar har doim kamida 340px keng — shrift kichraymaydi. */
        .tv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 12px;
            align-content: start;
        }
        /* Pagination: faqat aktiv sahifani ko'rsatamiz */
        .tv-page { display: none; }
        .tv-page.active { display: grid; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .tv-page.active { animation: fadeIn 0.4s ease-out; }
    </style>
</head>
<body>
    <div class="h-screen flex flex-col">

        {{-- HEADER --}}
        <header class="flex items-center justify-between px-8 py-3 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-wide">TEST MARKAZI — KOMPYUTER RAQAMLARI</h1>
                    <div class="text-sm text-slate-400 mt-0.5">
                        {{ $date->format('d.m.Y') }}
                        <span class="mx-2">·</span>
                        <span class="capitalize">{{ $date->isoFormat('dddd') }}</span>
                        <span class="mx-2">·</span>
                        <span>Keyingi {{ $windowMin }} daq. (komp № — {{ $revealMin }} daq. qolganda)</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3 text-[11px] uppercase tracking-wider text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30"></span>Topshirayapti</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-amber-400/30"></span>Kiring</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-400 ring-2 ring-sky-400/30"></span>Komp № ochildi</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-500 ring-2 ring-slate-500/30"></span>Kutmoqda</span>
                </div>
                {{-- Sahifa ko'rsatkichi — bir sahifadan ko'p bo'lsa ko'rinadi --}}
                <div id="tv-pagination" class="hidden text-right">
                    <div class="text-2xl font-bold text-indigo-300 tabular-nums leading-none">
                        <span id="tv-page-current">1</span><span class="text-slate-600">/</span><span id="tv-page-total">1</span>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1 uppercase tracking-wider">Sahifa</div>
                </div>
                <div class="text-right">
                    <div id="tv-clock" class="text-3xl font-bold text-white tabular-nums leading-none">{{ $now->format('H:i') }}</div>
                    <div class="text-[11px] text-slate-500 mt-1 uppercase tracking-wider">Joriy vaqt</div>
                </div>
            </div>
        </header>

        {{-- BODY --}}
        <main id="tv-main" class="flex-1 px-6 py-5 overflow-hidden">
            @if(empty($items))
                <div class="h-full flex flex-col items-center justify-center text-center">
                    <svg class="w-24 h-24 text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <h2 class="text-4xl font-bold text-slate-400">Hozir kutilayotgan talaba yo'q</h2>
                    <p class="text-slate-500 mt-3 text-lg">Keyingi imtihonga {{ $windowMin }} daqiqadan ortiq vaqt qoldi.</p>
                </div>
            @else
                {{-- Barcha kartochkalar yagona flex container'da — JS pagination
                     keyin ularni sahifalarga ajratib joylashtiradi. --}}
                <div id="tv-all-cards" class="hidden">
                    @foreach($items as $it)
                        @php
                            $statusClass = match($it['status']) {
                                'in_progress' => 'bg-emerald-500/10 border-emerald-400/40 pulse-now',
                                'imminent'    => 'bg-amber-500/10 border-amber-400/40 pulse-imminent',
                                'near'        => 'bg-sky-500/10 border-sky-400/40',
                                default       => 'bg-slate-800/40 border-slate-700/60',
                            };
                            $compBg = match($it['status']) {
                                'in_progress' => 'bg-gradient-to-br from-emerald-500 to-emerald-700',
                                'imminent'    => 'bg-gradient-to-br from-amber-500 to-orange-600',
                                'near'        => 'bg-gradient-to-br from-sky-600 to-indigo-700',
                                default       => 'bg-slate-800/80 border border-slate-700',
                            };
                            $badgeClass = match($it['status']) {
                                'in_progress' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30',
                                'imminent'    => 'bg-amber-500/20 text-amber-200 border border-amber-400/30',
                                'near'        => 'bg-sky-500/20 text-sky-200 border border-sky-400/30',
                                default       => 'bg-slate-700/40 text-slate-300 border border-slate-600/40',
                            };
                            $badgeText = match($it['status']) {
                                'in_progress' => 'TOPSHIRAYAPTI',
                                'imminent'    => 'KIRING',
                                'near'        => 'KUTING',
                                default       => $it['minutes_until'] . ' daq.',
                            };
                            $ynBg = $it['yn_type'] === 'OSKI'
                                ? 'bg-purple-500/30 text-purple-100 border-purple-400/40'
                                : 'bg-cyan-500/30 text-cyan-100 border-cyan-400/40';
                        @endphp
                        <div class="tv-card rounded-2xl border-2 {{ $statusClass }} p-3 flex flex-col gap-2 transition">
                            <div class="flex items-center gap-3">
                                {{-- Komp № katta blok --}}
                                <div class="w-24 h-24 flex-shrink-0 rounded-2xl {{ $compBg }} flex items-center justify-center shadow-lg">
                                    @if($it['show_computer'])
                                        <div class="text-center">
                                            <div class="text-xs uppercase tracking-wider text-white/80 leading-none">Komp</div>
                                            <div class="text-5xl font-black text-white tabular-nums leading-tight">{{ $it['computer_number'] }}</div>
                                        </div>
                                    @else
                                        <div class="text-center">
                                            <div class="text-xs uppercase tracking-wider text-slate-400 leading-none">Komp</div>
                                            <div class="text-4xl font-black text-slate-500 leading-tight">?</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-2xl font-extrabold text-white truncate leading-tight" title="{{ $it['short_name'] }}">
                                        {{ $it['short_name'] }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                        @if($it['group_name'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-bold bg-indigo-500/30 text-indigo-100 border border-indigo-400/40">
                                                {{ $it['group_name'] }}
                                            </span>
                                        @endif
                                        @if($it['yn_type'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-bold border {{ $ynBg }}">
                                                {{ $it['yn_type'] }}@if($it['attempt'] > 1) · {{ $it['attempt'] }}-u @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            {{-- Pastki info satri --}}
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2 text-slate-300 min-w-0 flex-1">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-bold tabular-nums">{{ $it['planned_time'] }}</span>
                                    @if($it['subject_name'])
                                        <span class="mx-1 text-slate-600">·</span>
                                        <span class="text-slate-400 truncate" title="{{ $it['subject_name'] }}">{{ $it['subject_name'] }}</span>
                                    @endif
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider whitespace-nowrap ml-2 {{ $badgeClass }}">
                                    {{ $badgeText }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                {{-- Sahifalar JS bu yerda yaratadi --}}
                <div id="tv-pages-host" class="h-full"></div>
            @endif
        </main>

        {{-- FOOTER --}}
        <footer class="px-8 py-2 text-center text-[10px] text-slate-600 uppercase tracking-wider border-t border-slate-800">
            Avtomatik yangilanadi · ma'lumotlar har 30 sekundda
        </footer>
    </div>

    <script>
        // Soat
        (function() {
            const el = document.getElementById('tv-clock');
            if (!el) return;
            function tick() {
                const d = new Date();
                el.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            }
            setInterval(tick, 1000);
        })();

        // Pagination
        (function() {
            const host = document.getElementById('tv-pages-host');
            const source = document.getElementById('tv-all-cards');
            const main = document.getElementById('tv-main');
            const paginationEl = document.getElementById('tv-pagination');
            const pageCurrentEl = document.getElementById('tv-page-current');
            const pageTotalEl = document.getElementById('tv-page-total');
            if (!host || !source) return;

            const cards = Array.from(source.children);
            if (cards.length === 0) return;

            const PAGE_INTERVAL_MS = 10000;   // har 10 sekundda keyingi sahifa
            const REFRESH_AFTER_MS = 30000;   // 30 sekunddan keyin sahifani qayta yuklash
            // Kartochka taxminiy o'lchamlari (CSS bilan mos) — perPage hisoblash uchun.
            const CARD_MIN_WIDTH = 340 + 12;  // minmax(340px) + gap
            const CARD_HEIGHT = 150 + 12;
            const PADDING_X = 48;
            const PADDING_Y = 40;

            let pagesData = [];
            let activeIndex = 0;
            let pageTimer = null;
            let lastBuildAt = Date.now();

            function calcPerPage() {
                const w = main.clientWidth - PADDING_X;
                const h = main.clientHeight - PADDING_Y;
                const cols = Math.max(1, Math.floor(w / CARD_MIN_WIDTH));
                const rows = Math.max(1, Math.floor(h / CARD_HEIGHT));
                return cols * rows;
            }

            function buildPages() {
                host.innerHTML = '';
                const perPage = calcPerPage();
                pagesData = [];
                for (let i = 0; i < cards.length; i += perPage) {
                    const pageEl = document.createElement('div');
                    pageEl.className = 'tv-page tv-grid';
                    cards.slice(i, i + perPage).forEach(c => pageEl.appendChild(c.cloneNode(true)));
                    host.appendChild(pageEl);
                    pagesData.push(pageEl);
                }
                pageTotalEl.textContent = pagesData.length;
                paginationEl.classList.toggle('hidden', pagesData.length <= 1);
                showPage(0);
                lastBuildAt = Date.now();
            }

            function showPage(idx) {
                if (pagesData.length === 0) return;
                activeIndex = idx % pagesData.length;
                pagesData.forEach((p, i) => p.classList.toggle('active', i === activeIndex));
                pageCurrentEl.textContent = activeIndex + 1;
            }

            function nextPage() {
                const isLast = activeIndex === pagesData.length - 1;
                const elapsed = Date.now() - lastBuildAt;
                // Oxirgi sahifani ko'rib bo'lganda VA 30 sek o'tgan bo'lsa —
                // butun sahifani qayta yuklab yangi ma'lumot olamiz.
                if (isLast && elapsed >= REFRESH_AFTER_MS) {
                    window.location.reload();
                    return;
                }
                showPage(activeIndex + 1);
            }

            buildPages();
            if (pagesData.length > 1) {
                pageTimer = setInterval(nextPage, PAGE_INTERVAL_MS);
            } else {
                // Bitta sahifa bo'lsa 30 sek'da qayta yuklab yangi ma'lumot.
                setTimeout(() => window.location.reload(), REFRESH_AFTER_MS);
            }

            // Oyna o'lchami o'zgarsa sahifalarni qayta hisoblash (rare on TV
            // lekin loyaleadigan).
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (pageTimer) clearInterval(pageTimer);
                    buildPages();
                    if (pagesData.length > 1) {
                        pageTimer = setInterval(nextPage, PAGE_INTERVAL_MS);
                    }
                }, 250);
            });
        })();
    </script>
</body>
</html>
