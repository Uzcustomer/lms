<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test markazi — Guruhlar jadvali</title>
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { background: #0b1220; color: #e5e7eb; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .row-flash-now { animation: nowPulse 1.6s ease-in-out infinite; }
        .row-flash-soon { animation: soonPulse 2s ease-in-out infinite; }
        @keyframes nowPulse {
            0%, 100% { background-color: rgba(16, 185, 129, 0.18); }
            50%      { background-color: rgba(16, 185, 129, 0.42); }
        }
        @keyframes soonPulse {
            0%, 100% { background-color: rgba(234, 179, 8, 0.12); }
            50%      { background-color: rgba(234, 179, 8, 0.30); }
        }
        .ticker {
            animation: tick 1s steps(2) infinite;
        }
        @keyframes tick { 50% { opacity: 0.25; } }
        /* Hide scrollbars on TV */
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen overflow-hidden">
    <div class="h-screen flex flex-col">

        {{-- HEADER --}}
        <header class="flex items-center justify-between px-8 py-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-wide">TEST MARKAZI — KIRISH JADVALI</h1>
                    <div class="text-sm text-slate-400 mt-0.5">
                        <span id="tv-date" data-date="{{ $date->format('Y-m-d') }}">{{ $date->format('d.m.Y') }}</span>
                        <span class="mx-2">·</span>
                        <span class="capitalize">{{ $date->isoFormat('dddd') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-8">
                {{-- Legenda --}}
                <div class="flex items-center gap-3 text-[11px] uppercase tracking-wider text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30"></span>Hozir</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-amber-400/30"></span>Tayyorlaning</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-400 ring-2 ring-sky-400/30"></span>Kutilmoqda</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-500 ring-2 ring-slate-500/30"></span>Tugadi</span>
                </div>
                {{-- Soat --}}
                <div class="text-right">
                    <div id="tv-clock" class="text-4xl font-bold text-white tabular-nums tracking-tight">--:--:--</div>
                    <div class="text-[11px] uppercase tracking-widest text-slate-500 mt-0.5">Joriy vaqt</div>
                </div>
            </div>
        </header>

        {{-- BODY --}}
        <main class="flex-1 overflow-hidden px-8 py-4">
            @if(count($items) === 0)
                <div id="tv-empty" class="h-full flex flex-col items-center justify-center text-center">
                    <svg class="w-24 h-24 text-slate-700 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <div class="text-3xl font-semibold text-slate-400">Bugun uchun jadval belgilanmagan</div>
                    <div class="text-sm text-slate-600 mt-2">Sahifa har 30 soniyada avtomatik yangilanadi</div>
                </div>
            @endif

            <div id="tv-table-wrap" class="h-full {{ count($items) === 0 ? 'hidden' : '' }}">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-widest text-slate-500 border-b border-slate-700">
                            <th class="px-3 py-2 w-16 text-center">#</th>
                            <th class="px-3 py-2 w-32">Vaqt</th>
                            <th class="px-3 py-2 w-28">Tur</th>
                            <th class="px-3 py-2 w-32">Urinish</th>
                            <th class="px-3 py-2 w-44">Guruh</th>
                            <th class="px-3 py-2">Fan</th>
                            <th class="px-3 py-2 w-48 text-right">Holat</th>
                        </tr>
                    </thead>
                    <tbody id="tv-tbody" data-duration="{{ $testDurationMinutes }}">
                        {{-- Qatorlar JS tomonidan render qilinadi (server tomonidan ham boshlang'ich holat berilgan) --}}
                        @foreach($items as $i => $row)
                            <tr class="tv-row border-b border-slate-800/60 text-2xl"
                                data-time="{{ $row['time'] }}">
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-800 text-slate-300 text-base font-bold">
                                        {{ $i + 1 }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-bold text-white tabular-nums tracking-tight">{{ $row['time'] }}</td>
                                <td class="px-3 py-3">
                                    @if($row['yn_type'] === 'OSKI')
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-base font-bold uppercase bg-purple-500/20 text-purple-200 border border-purple-500/40">OSKI</span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-base font-bold uppercase bg-cyan-500/20 text-cyan-200 border border-cyan-500/40">Test</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @php
                                        $attemptClass = match((int) $row['attempt']) {
                                            2 => 'bg-orange-500/20 text-orange-200 border-orange-500/40',
                                            3 => 'bg-red-500/20 text-red-200 border-red-500/40',
                                            default => 'bg-slate-700/40 text-slate-300 border-slate-600/40',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-md text-base font-semibold uppercase border {{ $attemptClass }}">
                                        {{ $row['attempt'] }}-urinish
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-bold text-white tracking-wide">{{ $row['group_name'] }}</td>
                                <td class="px-3 py-3 text-slate-200 truncate">{{ $row['subject_name'] }}</td>
                                <td class="px-3 py-3 text-right">
                                    <span class="tv-status inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-base font-bold uppercase tracking-wider bg-slate-700/40 text-slate-300 border border-slate-600/40">
                                        <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
                                        Yuklanmoqda
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </main>

        {{-- FOOTER --}}
        <footer class="px-8 py-2 border-t border-slate-800 bg-slate-900/60 flex items-center justify-between text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="ticker">●</span>
                <span>Jonli jadval · ma'lumotlar har 30 soniyada yangilanadi</span>
            </div>
            <div id="tv-last-update">Yangilangan: hozir</div>
        </footer>
    </div>

    <script>
    (function () {
        const tbody = document.getElementById('tv-tbody');
        const tableWrap = document.getElementById('tv-table-wrap');
        const emptyEl = document.getElementById('tv-empty');
        const clockEl = document.getElementById('tv-clock');
        const lastUpdateEl = document.getElementById('tv-last-update');
        const dateEl = document.getElementById('tv-date');
        const sourceDate = dateEl ? dateEl.dataset.date : '';
        const dataUrl = @json(route('tv.jadval')) + '?format=json' + (sourceDate ? '&date=' + sourceDate : '');

        const STATUS = {
            done:    { label: 'Tugadi',       dot: 'bg-slate-500',  badge: 'bg-slate-700/40 text-slate-400 border-slate-600/40',     rowExtra: 'opacity-40' },
            now:     { label: 'Hozir kirish', dot: 'bg-emerald-400 animate-pulse', badge: 'bg-emerald-500/25 text-emerald-100 border-emerald-400/50', rowExtra: 'row-flash-now' },
            soon:    { label: 'Tayyorlaning', dot: 'bg-amber-400 animate-pulse',   badge: 'bg-amber-500/25 text-amber-100 border-amber-400/50',       rowExtra: 'row-flash-soon' },
            waiting: { label: 'Kutilmoqda',   dot: 'bg-sky-400',                   badge: 'bg-sky-500/20 text-sky-200 border-sky-500/40',             rowExtra: '' },
        };

        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function fmtClock(d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }

        function parseSlotDate(timeStr) {
            // sourceDate (YYYY-MM-DD) bilan birga sahifaga belgilangan kun uchun
            // mahalliy Date hosil qilamiz — TV brauzeri serverdagi vaqt zonasida
            // ishlayotgani taxmin qilinadi.
            const [y, m, d] = (sourceDate || (new Date()).toISOString().slice(0, 10)).split('-').map(Number);
            const [hh, mm] = timeStr.split(':').map(Number);
            return new Date(y, m - 1, d, hh, mm, 0, 0);
        }

        function statusFor(slotDate, durationMin, now) {
            const startMs = slotDate.getTime();
            const endMs = startMs + durationMin * 60 * 1000;
            const nowMs = now.getTime();
            const SOON_WINDOW = 15 * 60 * 1000;
            if (nowMs >= endMs) return STATUS.done;
            if (nowMs >= startMs) return STATUS.now;
            if (startMs - nowMs <= SOON_WINDOW) return STATUS.soon;
            return STATUS.waiting;
        }

        function applyStatuses() {
            if (!tbody) return;
            const now = new Date();
            const duration = parseInt(tbody.dataset.duration || '15', 10);
            const rows = tbody.querySelectorAll('.tv-row');
            rows.forEach(function (row) {
                const time = row.dataset.time;
                if (!time) return;
                const slotDate = parseSlotDate(time);
                const st = statusFor(slotDate, duration, now);
                // Qator ranglarini almashtirish
                row.classList.remove('row-flash-now', 'row-flash-soon', 'opacity-40');
                if (st.rowExtra) row.classList.add(...st.rowExtra.split(' '));
                // Holat badge'ini yangilash
                const badge = row.querySelector('.tv-status');
                if (badge) {
                    badge.className = 'tv-status inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-base font-bold uppercase tracking-wider border ' + st.badge;
                    badge.innerHTML = '<span class="w-2.5 h-2.5 rounded-full ' + st.dot + '"></span>' + st.label;
                }
            });
        }

        function tickClock() {
            if (clockEl) clockEl.textContent = fmtClock(new Date());
        }

        function renderItems(items) {
            if (!tbody) return;
            const duration = parseInt(tbody.dataset.duration || '15', 10);
            if (!items || items.length === 0) {
                tbody.innerHTML = '';
                if (tableWrap) tableWrap.classList.add('hidden');
                if (emptyEl) emptyEl.classList.remove('hidden');
                return;
            }
            if (tableWrap) tableWrap.classList.remove('hidden');
            if (emptyEl) emptyEl.classList.add('hidden');

            const html = items.map(function (row, i) {
                const ynBadge = row.yn_type === 'OSKI'
                    ? '<span class="inline-flex items-center px-3 py-1 rounded-md text-base font-bold uppercase bg-purple-500/20 text-purple-200 border border-purple-500/40">OSKI</span>'
                    : '<span class="inline-flex items-center px-3 py-1 rounded-md text-base font-bold uppercase bg-cyan-500/20 text-cyan-200 border border-cyan-500/40">Test</span>';
                const attemptN = parseInt(row.attempt || 1, 10);
                const attemptCls = attemptN === 3
                    ? 'bg-red-500/20 text-red-200 border-red-500/40'
                    : (attemptN === 2 ? 'bg-orange-500/20 text-orange-200 border-orange-500/40' : 'bg-slate-700/40 text-slate-300 border-slate-600/40');
                return '<tr class="tv-row border-b border-slate-800/60 text-2xl" data-time="' + row.time + '">' +
                    '<td class="px-3 py-3 text-center"><span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-800 text-slate-300 text-base font-bold">' + (i + 1) + '</span></td>' +
                    '<td class="px-3 py-3 font-bold text-white tabular-nums tracking-tight">' + row.time + '</td>' +
                    '<td class="px-3 py-3">' + ynBadge + '</td>' +
                    '<td class="px-3 py-3"><span class="inline-flex items-center px-3 py-1 rounded-md text-base font-semibold uppercase border ' + attemptCls + '">' + attemptN + '-urinish</span></td>' +
                    '<td class="px-3 py-3 font-bold text-white tracking-wide">' + escapeHtml(row.group_name || '') + '</td>' +
                    '<td class="px-3 py-3 text-slate-200 truncate">' + escapeHtml(row.subject_name || '') + '</td>' +
                    '<td class="px-3 py-3 text-right"><span class="tv-status inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-base font-bold uppercase tracking-wider bg-slate-700/40 text-slate-300 border border-slate-600/40"><span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>Yuklanmoqda</span></td>' +
                    '</tr>';
            }).join('');
            tbody.innerHTML = html;
            tbody.dataset.duration = duration;
            applyStatuses();
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
            });
        }

        async function refreshData() {
            try {
                const res = await fetch(dataUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (!res.ok) return;
                const json = await res.json();
                renderItems(json.items || []);
                if (lastUpdateEl) {
                    const d = new Date();
                    lastUpdateEl.textContent = 'Yangilangan: ' + fmtClock(d);
                }
            } catch (e) {
                // Tarmoq xatoligi — keyingi urinishda qayta o'qiymiz
            }
        }

        // Soatni har soniyada yangilab turish va statuslarni qayta hisoblash
        setInterval(function () {
            tickClock();
            applyStatuses();
        }, 1000);

        // Ma'lumotni har 30 soniyada serverdan qayta olib kelish
        setInterval(refreshData, 30 * 1000);

        // Boshlang'ich holat
        tickClock();
        applyStatuses();
    })();
    </script>
</body>
</html>
