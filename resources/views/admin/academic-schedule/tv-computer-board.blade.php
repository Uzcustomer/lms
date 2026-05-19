<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>Test markazi — Komp № displeyi</title>
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { background: #0b1220; color: #e5e7eb; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        @keyframes nowPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55); }
            50%      { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
        }
        @keyframes imminentPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.55); }
            50%      { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        }
        .pulse-now { animation: nowPulse 1.6s ease-in-out infinite; }
        .pulse-imminent { animation: imminentPulse 2.0s ease-in-out infinite; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen overflow-hidden">
    <div class="min-h-screen flex flex-col">

        {{-- HEADER --}}
        <header class="flex items-center justify-between px-8 py-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700">
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
                        <span>Keyingi {{ $windowMin }} daq. kutilayotganlar</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-8">
                <div class="flex items-center gap-3 text-[11px] uppercase tracking-wider text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30"></span>Topshirayapti</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-amber-400/30"></span>Hozir kiring</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-400 ring-2 ring-sky-400/30"></span>Kutilmoqda</span>
                </div>
                <div class="text-right">
                    <div id="tv-clock" class="text-3xl font-bold text-white tabular-nums leading-none">{{ $now->format('H:i') }}</div>
                    <div class="text-[11px] text-slate-500 mt-1 uppercase tracking-wider">Joriy vaqt</div>
                </div>
            </div>
        </header>

        {{-- BODY --}}
        <main class="flex-1 px-6 py-6 overflow-hidden">
            @if(empty($items))
                <div class="h-full flex flex-col items-center justify-center text-center">
                    <svg class="w-20 h-20 text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <h2 class="text-3xl font-bold text-slate-400">Hozir kutilayotgan talaba yo'q</h2>
                    <p class="text-slate-500 mt-2">Keyingi imtihonga {{ $windowMin }} daqiqadan ortiq vaqt qoldi.</p>
                </div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                    @foreach($items as $it)
                        @php
                            $statusClass = match($it['status']) {
                                'in_progress' => 'bg-emerald-500/10 border-emerald-400/40 pulse-now',
                                'imminent'    => 'bg-amber-500/10 border-amber-400/40 pulse-imminent',
                                default       => 'bg-slate-800/60 border-slate-700',
                            };
                            $compBg = match($it['status']) {
                                'in_progress' => 'bg-gradient-to-br from-emerald-500 to-emerald-700',
                                'imminent'    => 'bg-gradient-to-br from-amber-500 to-orange-600',
                                default       => 'bg-gradient-to-br from-sky-600 to-indigo-700',
                            };
                            $badgeClass = match($it['status']) {
                                'in_progress' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30',
                                'imminent'    => 'bg-amber-500/20 text-amber-200 border border-amber-400/30',
                                default       => 'bg-sky-500/20 text-sky-200 border border-sky-400/30',
                            };
                            $badgeText = match($it['status']) {
                                'in_progress' => 'TOPSHIRAYAPTI',
                                'imminent'    => 'KIRING',
                                default       => $it['minutes_until'] . ' daq.',
                            };
                        @endphp
                        <div class="rounded-2xl border-2 {{ $statusClass }} p-4 flex flex-col gap-3 transition">
                            {{-- Komp № katta blok --}}
                            <div class="flex items-center gap-3">
                                <div class="w-20 h-20 flex-shrink-0 rounded-2xl {{ $compBg }} flex items-center justify-center shadow-lg">
                                    <div class="text-center">
                                        <div class="text-[10px] uppercase tracking-wider text-white/80 leading-none">Komp</div>
                                        <div class="text-4xl font-black text-white tabular-nums leading-tight">{{ $it['computer_number'] }}</div>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xl font-bold text-white truncate" title="{{ $it['short_name'] }}">
                                        {{ $it['short_name'] }}
                                    </div>
                                    @if($it['subject_name'])
                                        <div class="text-xs text-slate-400 truncate mt-0.5" title="{{ $it['subject_name'] }}">
                                            {{ $it['subject_name'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            {{-- Pastki info satri --}}
                            <div class="flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 text-slate-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-semibold tabular-nums">{{ $it['planned_time'] }}</span>
                                    @if($it['yn_type'])
                                        <span class="mx-1 text-slate-600">·</span>
                                        <span>{{ $it['yn_type'] }}{{ $it['attempt'] > 1 ? ' '.$it['attempt'].'-u' : '' }}</span>
                                    @endif
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                    {{ $badgeText }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </main>

        {{-- FOOTER --}}
        <footer class="px-8 py-2 text-center text-[10px] text-slate-600 uppercase tracking-wider border-t border-slate-800">
            Avtomatik yangilanadi · har 15 sekundda
        </footer>
    </div>

    {{-- Soatni har sekundda yangilab turish (sahifa to'liq yangilanguncha) --}}
    <script>
        (function() {
            const el = document.getElementById('tv-clock');
            if (!el) return;
            function tick() {
                const d = new Date();
                const h = String(d.getHours()).padStart(2, '0');
                const m = String(d.getMinutes()).padStart(2, '0');
                el.textContent = h + ':' + m;
            }
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
