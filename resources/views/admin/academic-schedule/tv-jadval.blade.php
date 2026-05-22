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
        /* Pagination: sahifalar host ichida ustma-ust joylashadi — almashish
           paytida ikkalasi ham bir lahza ko'rinib, effekt bilan o'tadi. */
        #tv-pages-host { position: relative; }
        .tv-page {
            display: none;
            position: absolute;
            inset: 0;
            height: 100%;
            flex-direction: column;
        }
        .tv-page.active { display: flex; z-index: 2; }
        .tv-page.tv-page-exit { display: flex; z-index: 1; }

        /* Sahifa almashish effekti — yangi sahifa o'ngdan suzib kiradi,
           eskisi chapga suzib chiqadi. Almashayotgani aniq sezilsin. */
        @keyframes tvPageEnter {
            0%   { opacity: 0; transform: translateX(7%) scale(0.97); filter: blur(5px); }
            100% { opacity: 1; transform: translateX(0)  scale(1);    filter: blur(0); }
        }
        @keyframes tvPageExit {
            0%   { opacity: 1; transform: translateX(0)   scale(1);    filter: blur(0); }
            100% { opacity: 0; transform: translateX(-7%) scale(0.97); filter: blur(5px); }
        }
        .tv-page-enter { animation: tvPageEnter 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) both; }
        .tv-page-exit  { animation: tvPageExit  0.5s cubic-bezier(0.55, 0.06, 0.68, 0.19) both; }

        /* Sahifa raqami almashganda urg'u beradi. */
        @keyframes pageBump {
            0%   { transform: scale(1);    color: #a5b4fc; }
            40%  { transform: scale(1.35); color: #ffffff; }
            100% { transform: scale(1);    color: #a5b4fc; }
        }
        .page-bump { display: inline-block; animation: pageBump 0.6s ease-out; }
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
                        <div class="tv-card rounded-2xl border-2 {{ $statusClass }} p-3 flex flex-col gap-2 transition"
                             data-time="{{ $it['planned_time'] }}">
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
                            {{-- Pastki info satri — vaqt sahifa sarlavhasida, shu yerda fan --}}
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2 text-slate-300 min-w-0 flex-1">
                                    @if($it['subject_name'])
                                        <span class="text-slate-300 truncate font-medium" title="{{ $it['subject_name'] }}">{{ $it['subject_name'] }}</span>
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

    {{-- Ovozni yoqish ko'rsatkichi — brauzer autoplay siyosati tufayli ovoz
         birinchi marta ekranga bosgandan keyin ishga tushadi. --}}
    <div id="tv-sound-hint"
         style="position:fixed;right:14px;bottom:12px;z-index:50;display:none;
                padding:8px 16px;border-radius:9999px;background:rgba(99,102,241,0.94);
                color:#fff;font-size:13px;font-weight:600;cursor:pointer;
                box-shadow:0 4px 14px rgba(0,0,0,0.45);
                font-family:'Inter',system-ui,-apple-system,sans-serif;">
        🔊 Ovozni yoqish uchun ekranga bir marta bosing
    </div>

    <script>
        // Aeroport uslubidagi "ting-ting-ting" ovozi — Web Audio API orqali
        // generatsiya qilinadi (tashqi audio fayl shart emas). Brauzer autoplay
        // siyosati tufayli ovoz birinchi marta foydalanuvchi ekranga bosgandan
        // keyin ochiladi; shundan so'ng sahifa qayta yuklansa ham (domen bilan
        // muloqot bo'lgani uchun) avtomatik chiqaveradi.
        window.tvChime = (function() {
            const AC = window.AudioContext || window.webkitAudioContext;
            let ctx = null;

            function hint(show) {
                const h = document.getElementById('tv-sound-hint');
                if (h) h.style.display = show ? 'block' : 'none';
            }

            function ensureCtx() {
                if (!AC) return null;
                if (!ctx) ctx = new AC();
                return ctx;
            }

            // Bitta jarangli "ting" tovushi — asosiy ton + oktava overton,
            // tez ko'tarilib, sekin so'nadigan qo'ng'iroqsimon envelope bilan.
            function note(c, freq, startAt, duration, peak) {
                const osc = c.createOscillator();
                const overtone = c.createOscillator();
                const gain = c.createGain();
                const overGain = c.createGain();
                osc.type = 'sine';
                overtone.type = 'sine';
                osc.frequency.value = freq;
                overtone.frequency.value = freq * 2.01;
                overGain.gain.value = 0.3;
                osc.connect(gain);
                overtone.connect(overGain);
                overGain.connect(gain);
                gain.connect(c.destination);
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.012);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                osc.start(startAt);
                overtone.start(startAt);
                osc.stop(startAt + duration + 0.05);
                overtone.stop(startAt + duration + 0.05);
            }

            function play() {
                const c = ensureCtx();
                if (!c) return;
                if (c.state === 'suspended') c.resume().catch(function() {});
                if (c.state !== 'running') { hint(true); return; }
                hint(false);
                const t0 = c.currentTime + 0.04;
                const freqs = [783.99, 1046.50, 1318.51]; // G5 → C6 → E6, ko'tariluvchi
                const gap = 0.22;
                freqs.forEach(function(f, i) {
                    note(c, f, t0 + i * gap, 0.55, 0.3);
                });
            }

            function unlock() {
                const c = ensureCtx();
                if (!c) return;
                if (c.state === 'suspended') {
                    c.resume().then(function() { hint(false); }).catch(function() {});
                } else {
                    hint(false);
                }
            }

            function init() {
                const c = ensureCtx();
                if (!c || c.state === 'suspended') {
                    hint(true);
                    ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(function(ev) {
                        document.addEventListener(ev, unlock, { passive: true });
                    });
                }
            }
            init();

            return { play: play, unlock: unlock };
        })();

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

        // Pagination — vaqt bo'yicha seksiyalar bilan. Har sahifa bir
        // nechta vaqt seksiyasini o'z ichiga olishi mumkin; sahifaga sig'masa
        // boshqa sahifaga o'tadi. Bitta vaqtning kartochkalari ko'p bo'lsa
        // shu vaqtning header'i takrorlanib boshqa sahifaga davom etadi.
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
            const REFRESH_AFTER_MS = 60000;   // 60 sekunddan keyin (oxirgi sahifada bo'lganda) qayta yuklash
            const CARD_MIN_WIDTH = 340 + 12;
            const CARD_HEIGHT = 162;          // tv-card max-height (150) + gap
            const SECTION_HEADER_HEIGHT = 80; // gradient pill + pastki margin (mb-3)
            const SECTION_GAP = 24;
            const PADDING_X = 48;
            const PADDING_Y = 40;

            let pagesData = [];
            let activeIndex = 0;
            let pageTimer = null;
            let lastBuildAt = Date.now();
            let lastAnnouncedTime = null;

            function calcCols() {
                const w = main.clientWidth - PADDING_X;
                return Math.max(1, Math.floor(w / CARD_MIN_WIDTH));
            }

            function groupByTime(allCards) {
                const groups = [];
                const seen = new Map();
                allCards.forEach(c => {
                    const t = c.getAttribute('data-time') || '—';
                    if (!seen.has(t)) {
                        seen.set(t, groups.length);
                        groups.push({ time: t, cards: [] });
                    }
                    groups[seen.get(t)].cards.push(c);
                });
                return groups;
            }

            function makeTimeHeader(time, count, continued) {
                const wrap = document.createElement('div');
                wrap.className = 'tv-time-header flex items-center justify-center gap-4 mb-3';
                wrap.innerHTML = `
                    <div class="flex-1 h-0.5 bg-gradient-to-r from-transparent to-indigo-500/40"></div>
                    <div class="flex items-center gap-3 px-6 py-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 shadow-2xl">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-5xl font-black text-white tabular-nums tracking-wide">${time}</span>
                        <span class="text-base text-white/80 font-bold border-l border-white/30 pl-3 ml-1">${count} talaba${continued ? ' · davomi' : ''}</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-gradient-to-l from-transparent to-indigo-500/40"></div>
                `;
                return wrap;
            }

            function makeSection(time, cardEls, count, continued) {
                const sec = document.createElement('div');
                sec.className = 'tv-section flex flex-col';
                sec.dataset.time = time;
                sec.style.marginBottom = SECTION_GAP + 'px';
                sec.appendChild(makeTimeHeader(time, count, continued));
                const grid = document.createElement('div');
                grid.className = 'tv-grid';
                cardEls.forEach(c => grid.appendChild(c.cloneNode(true)));
                sec.appendChild(grid);
                return sec;
            }

            function buildPages() {
                host.innerHTML = '';
                pagesData = [];
                const cols = calcCols();
                const maxHeight = main.clientHeight - PADDING_Y;
                const groups = groupByTime(cards);

                // Soda algoritm: har vaqt seksiyasini ketma-ket joriy sahifaga
                // qo'shamiz. Sig'masa qancha qator sig'sa shuncha kartochkani
                // joylashtirib, joriy sahifani yopamiz va yangi sahifa
                // boshlaymiz. Bir vaqtning kartochkalari sahifaga sig'masa
                // bir nechta sahifaga davomi sifatida bo'linadi.
                let currentPage = null;
                let currentHeight = 0;

                const startNewPage = () => {
                    currentPage = document.createElement('div');
                    // Faqat 'tv-page' — Tailwind .flex display:none ni bekor
                    // qilib yuborardi; display/flex-direction'ni CSS boshqaradi.
                    currentPage.className = 'tv-page';
                    host.appendChild(currentPage);
                    pagesData.push(currentPage);
                    currentHeight = 0;
                };

                for (const g of groups) {
                    let remaining = g.cards.slice();
                    let firstChunk = true;
                    while (remaining.length > 0) {
                        if (!currentPage) startNewPage();
                        const availH = maxHeight - currentHeight - SECTION_HEADER_HEIGHT;
                        const rowsCanFit = Math.max(0, Math.floor(availH / CARD_HEIGHT));
                        if (rowsCanFit === 0) {
                            // Sahifada joy yo'q — yangisi boshlaymiz
                            currentPage = null;
                            currentHeight = 0;
                            continue;
                        }
                        const cardsCanFit = rowsCanFit * cols;
                        const chunk = remaining.slice(0, cardsCanFit);
                        const chunkRows = Math.ceil(chunk.length / cols);
                        const chunkH = SECTION_HEADER_HEIGHT + chunkRows * CARD_HEIGHT + SECTION_GAP;

                        const sec = makeSection(g.time, chunk, g.cards.length, !firstChunk);
                        currentPage.appendChild(sec);
                        currentHeight += chunkH;
                        remaining = remaining.slice(chunk.length);
                        firstChunk = false;
                        // Davomi bor bo'lsa hozirgi sahifani yopamiz —
                        // davomi yangi sahifada to'liq vaqt header bilan
                        // boshlanadi.
                        if (remaining.length > 0) {
                            currentPage = null;
                            currentHeight = 0;
                        }
                    }
                }

                pageTotalEl.textContent = pagesData.length;
                paginationEl.classList.toggle('hidden', pagesData.length <= 1);
                showPage(0, false);
                lastBuildAt = Date.now();
            }

            function bumpPageIndicator() {
                pageCurrentEl.classList.remove('page-bump');
                void pageCurrentEl.offsetWidth; // reflow — animatsiyani qayta tetiklaydi
                pageCurrentEl.classList.add('page-bump');
            }

            // Sahifada ko'rsatilayotgan birinchi vaqt seksiyasi oldingisidan
            // farq qilsa — aeroport uslubidagi "ting-ting-ting" ovozini chiqaramiz.
            function announceTime(pageEl) {
                if (!pageEl) return;
                const firstSection = pageEl.querySelector('.tv-section');
                const t = firstSection ? firstSection.dataset.time : null;
                if (t && t !== lastAnnouncedTime) {
                    lastAnnouncedTime = t;
                    if (window.tvChime) window.tvChime.play();
                }
            }

            function showPage(idx, animate) {
                if (pagesData.length === 0) return;
                const total = pagesData.length;
                const newIndex = ((idx % total) + total) % total;
                const prev = pagesData[activeIndex];
                const next = pagesData[newIndex];
                activeIndex = newIndex;
                pageCurrentEl.textContent = activeIndex + 1;
                announceTime(next);

                // Animatsiyasiz holat: dastlabki ko'rsatish yoki bitta sahifa.
                if (!animate || prev === next || total <= 1) {
                    pagesData.forEach((p, i) => {
                        p.classList.remove('tv-page-enter', 'tv-page-exit');
                        p.classList.toggle('active', i === activeIndex);
                    });
                    return;
                }

                bumpPageIndicator();

                // Eski sahifa chapga suzib chiqadi.
                prev.classList.remove('active');
                prev.classList.add('tv-page-exit');
                prev.addEventListener('animationend', function onExit() {
                    prev.classList.remove('tv-page-exit');
                    prev.removeEventListener('animationend', onExit);
                }, { once: true });

                // Yangi sahifa o'ngdan suzib kiradi.
                next.classList.remove('tv-page-exit');
                next.classList.add('active', 'tv-page-enter');
                next.addEventListener('animationend', function onEnter() {
                    next.classList.remove('tv-page-enter');
                    next.removeEventListener('animationend', onEnter);
                }, { once: true });
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
                showPage(activeIndex + 1, true);
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
