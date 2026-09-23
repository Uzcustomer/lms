@php
    $isAdminPrefix = request()->routeIs('admin.*');
    $backRoute = $isAdminPrefix
        ? 'admin.academic-schedule.bandlik-kursatkichi'
        : 'teacher.academic-schedule.bandlik-kursatkichi';
    $ynOldiWordRoute = $isAdminPrefix
        ? 'admin.academic-schedule.test-center.generate-yn-oldi-word'
        : 'teacher.academic-schedule.test-center.generate-yn-oldi-word';
    $assignMissingRoute = $isAdminPrefix
        ? 'admin.academic-schedule.bandlik-kursatkichi.assign-missing'
        : 'teacher.academic-schedule.bandlik-kursatkichi.assign-missing';
    $assignMissingStatusRoute = $isAdminPrefix
        ? 'admin.academic-schedule.bandlik-kursatkichi.assign-missing.status'
        : 'teacher.academic-schedule.bandlik-kursatkichi.assign-missing.status';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="bk-head">
            <a href="{{ route($backRoute) }}" class="bk-back">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 19l-7-7 7-7"/></svg>
                Orqaga
            </a>
            <div class="bk-head-title">
                <h2>Kompyuter bandligi ko'rsatkichi</h2>
                <span class="bk-date">{{ $date->format('d.m.Y') }}</span>
                <span class="bk-weekday">{{ $date->isoFormat('dddd') }}</span>
            </div>
            <div class="bk-head-right">
                <span class="bk-chip" title="Test markazidagi ishchi kompyuterlar soni">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Jami kompyuterlar <b>{{ $totalComputers }}</b>
                </span>
            </div>
        </div>
    </x-slot>

    {{-- Sahifa uslubi: Tailwind serverda kompilyatsiya qilinmaydi, shuning
         uchun dizayn shu yerdagi bk-* klasslar bilan. JS tayanadigan
         id/klasslar (bk-table, bk-row, bk-filter, bk-row-select,
         bk-word-export, hidden) o'zgarmagan. --}}
    <style>
        .bk-head { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
        .bk-back { display:inline-flex; align-items:center; gap:4px; padding:7px 12px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#334155; font-size:13px; font-weight:600; text-decoration:none; transition:border-color .15s, color .15s; }
        .bk-back:hover { border-color:#c7d2fe; color:#4338ca; }
        .bk-head-title { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .bk-head-title h2 { margin:0; font-size:18px; font-weight:700; color:#0f172a; letter-spacing:-.01em; }
        .bk-date { padding:4px 10px; border-radius:8px; background:#eef2ff; color:#3730a3; font-weight:700; font-size:13px; font-variant-numeric:tabular-nums; }
        .bk-weekday { font-size:13px; color:#64748b; }
        .bk-head-right { margin-left:auto; }
        .bk-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 12px; border-radius:10px; background:#fff; border:1px solid #e2e8f0; color:#475569; font-size:13px; }
        .bk-chip b { color:#4338ca; font-size:15px; }

        .bk-page { padding:16px 20px 32px; display:flex; flex-direction:column; gap:14px; }
        .bk-empty { padding:48px 20px; text-align:center; background:#fffbeb; border:1px dashed #fcd34d; border-radius:16px; color:#92400e; font-size:14px; }
        .bk-empty svg { width:40px; height:40px; margin:0 auto 10px; color:#d97706; display:block; }

        .bk-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
        .bk-kpi { position:relative; overflow:hidden; display:flex; flex-direction:column; gap:6px; padding:14px 16px 14px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .bk-kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--kpi, #4f46e5); }
        .bk-kpi-label { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:#64748b; }
        .bk-kpi-label svg { width:14px; height:14px; color:var(--kpi, #4f46e5); }
        .bk-kpi-value { font-size:22px; font-weight:800; color:#0f172a; line-height:1.1; font-variant-numeric:tabular-nums; }
        .bk-kpi-value small { font-size:12px; font-weight:500; color:#94a3b8; margin-left:3px; }
        .bk-kpi-value .sep { color:#cbd5e1; margin:0 4px; font-weight:400; }
        .bk-kpi-foot { display:flex; align-items:center; gap:8px; flex-wrap:wrap; min-height:18px; }
        .bk-bar { flex:1; min-width:70px; height:6px; border-radius:999px; background:#e9edf3; overflow:hidden; }
        .bk-bar i { display:block; height:100%; border-radius:999px; }
        .bk-pct { font-size:12px; font-weight:700; color:#475569; font-variant-numeric:tabular-nums; }

        .bk-tag { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; font-size:10.5px; font-weight:700; line-height:1.5; white-space:nowrap; border:1px solid transparent; }
        .bk-tag.is-ok { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
        .bk-tag.is-warn { background:#fffbeb; color:#b45309; border-color:#fde68a; }
        .bk-tag.is-danger { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
        .bk-tag.is-info { background:#ecfeff; color:#0e7490; border-color:#a5f3fc; }
        .bk-tag.is-purple { background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe; }
        .bk-tag.is-pink { background:#fdf4ff; color:#a21caf; border-color:#f5d0fe; }
        .bk-tag.is-muted { background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
        .bk-tag.is-orange { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }

        .bk-alert { display:flex; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:12px; border:1px solid #fde68a; background:#fffbeb; color:#78350f; }
        .bk-alert-icon { flex:0 0 34px; width:34px; height:34px; border-radius:10px; background:#fef3c7; color:#b45309; display:grid; place-items:center; }
        .bk-alert-icon svg { width:18px; height:18px; }
        .bk-alert-body { flex:1; font-size:13px; line-height:1.5; min-width:200px; }
        .bk-alert-body b { color:#92400e; }
        .bk-alert-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .bk-alert-details { margin-top:12px; padding-top:12px; border-top:1px solid #fde68a; }
        .bk-alert-details p { margin:0 0 8px; font-size:12px; color:#92400e; }
        .bk-alert-details code { font-family:ui-monospace, Consolas, monospace; font-size:11px; background:#fef3c7; padding:1px 4px; border-radius:4px; }

        .bk-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:9px; font-size:12.5px; font-weight:700; border:1px solid transparent; cursor:pointer; white-space:nowrap; transition:background .15s, box-shadow .15s; text-decoration:none; }
        .bk-btn svg { width:14px; height:14px; }
        .bk-btn.is-primary { background:#4f46e5; color:#fff; box-shadow:0 1px 2px rgba(79,70,229,.3); }
        .bk-btn.is-primary:hover:not(:disabled) { background:#4338ca; }
        .bk-btn.is-warn { background:#d97706; color:#fff; }
        .bk-btn.is-warn:hover:not(:disabled) { background:#b45309; }
        .bk-btn.is-ghost { background:transparent; color:#92400e; text-decoration:underline; padding-left:6px; padding-right:6px; }
        .bk-btn:disabled { background:#cbd5e1; color:#fff; cursor:not-allowed; box-shadow:none; }

        .bk-mini-table { width:100%; border-collapse:collapse; font-size:11.5px; background:#fff; border-radius:8px; overflow:hidden; }
        .bk-mini-table th, .bk-mini-table td { padding:5px 8px; border:1px solid #fde68a; text-align:left; }
        .bk-mini-table th { background:#fef3c7; color:#92400e; font-weight:700; }
        .bk-mini-table td.r, .bk-mini-table th.r { text-align:right; font-variant-numeric:tabular-nums; }
        .bk-mini-table td.c, .bk-mini-table th.c { text-align:center; }
        .bk-mini-table .mono { font-family:ui-monospace, Consolas, monospace; }
        .bk-mini-table tfoot td { background:#fef3c7; font-weight:700; }

        .bk-toolbar { display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding:10px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
        .bk-check { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#334155; cursor:pointer; user-select:none; }
        .bk-cb { width:16px; height:16px; accent-color:#4f46e5; cursor:pointer; }
        .bk-count { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:#64748b; }
        .bk-count b { display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:22px; padding:0 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:12px; }
        .bk-toolbar .bk-btn { margin-left:auto; }

        .bk-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .bk-table { width:100%; min-width:1180px; border-collapse:separate; border-spacing:0; font-size:13px; color:#0f172a; }
        .bk-table thead { position:sticky; top:0; z-index:2; }
        .bk-table thead th { background:#f8fafc; padding:11px 12px; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#475569; text-align:center; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
        .bk-table thead tr.bk-filters th { background:#fff; padding:6px 8px; border-bottom:1px solid #e2e8f0; }
        .bk-table th.is-left, .bk-table td.is-left { text-align:left; }
        .bk-select { width:100%; min-width:0; font-size:12px; color:#334155; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:4px 6px; }
        .bk-select:focus { outline:none; border-color:#818cf8; box-shadow:0 0 0 3px rgba(129,140,248,.25); }
        .bk-filter-group { display:flex; gap:4px; }
        .bk-filter-group .bk-select { flex:1; }
        .bk-filter-group .bk-select.is-narrow { flex:0 0 auto; width:auto; }
        .bk-reset { width:100%; padding:4px; border:1px solid transparent; border-radius:8px; background:transparent; color:#64748b; font-size:14px; cursor:pointer; }
        .bk-reset:hover { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }

        .bk-row td { padding:11px 12px; text-align:center; vertical-align:middle; border-bottom:1px solid #f1f5f9; }
        .bk-row:last-of-type td { border-bottom:0; }
        .bk-row:hover td { background:#f8fafc; }
        .bk-row td:first-child { border-left:3px solid transparent; }
        .bk-row.is-danger td:first-child { border-left-color:#ef4444; }
        .bk-row.is-full td:first-child { border-left-color:#f59e0b; }
        .bk-row.is-high td:first-child { border-left-color:#fb923c; }
        .bk-row.is-pending td:first-child { border-left-color:#fbbf24; }
        .bk-row.is-danger td { background:#fffafa; }
        .bk-row.is-pending td { background:#fffdf5; }
        .bk-row.is-danger:hover td, .bk-row.is-pending:hover td { background:#f8fafc; }
        .bk-row.hidden { display:none; }
        .bk-idx { color:#94a3b8; font-size:12px; font-variant-numeric:tabular-nums; }

        .bk-time-cell { display:flex; flex-direction:column; align-items:center; gap:6px; }
        .bk-time { display:inline-flex; align-items:center; justify-content:center; min-width:66px; padding:6px 10px; border-radius:10px; background:#eff6ff; color:#1d4ed8; font-weight:800; font-size:14px; letter-spacing:.02em; font-variant-numeric:tabular-nums; }
        .bk-time.is-none { background:#fffbeb; color:#92400e; font-size:11px; font-weight:700; }
        .bk-word { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:8px; font-size:11px; font-weight:700; background:#fff; border:1px solid #c7d2fe; color:#4338ca; cursor:pointer; transition:background .15s; }
        .bk-word svg { width:12px; height:12px; }
        .bk-word:hover:not(:disabled) { background:#eef2ff; }
        .bk-word:disabled { opacity:.6; cursor:wait; }

        .bk-groups { display:flex; flex-direction:column; gap:6px; }
        .bk-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:5px 9px; border-radius:10px; background:#f8fafc; border:1px solid #eef2f7; }
        .bk-num { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; border-radius:999px; background:#4f46e5; color:#fff; font-size:11px; font-weight:800; }
        .bk-group-name { font-size:12.5px; font-weight:700; color:#0f172a; white-space:nowrap; }
        .bk-group-subj { font-size:12px; color:#475569; }
        .bk-sep { color:#cbd5e1; font-size:12px; }
        .bk-mini { display:inline-flex; align-items:center; padding:2px 7px; border-radius:6px; font-size:10.5px; font-weight:700; white-space:nowrap; }
        .bk-mini.is-ok { background:#ecfdf5; color:#047857; }
        .bk-mini.is-warn { background:#fffbeb; color:#b45309; }
        .bk-mini.is-muted { background:#f1f5f9; color:#64748b; }

        .bk-big { font-size:15px; font-weight:800; color:#0f172a; font-variant-numeric:tabular-nums; }
        .bk-stack { display:flex; flex-direction:column; align-items:center; gap:5px; }
        .bk-stack .bk-bar { flex:none; width:96px; height:5px; }
        .bk-stack .bk-pct { font-size:11px; }
        .bk-frac { font-variant-numeric:tabular-nums; font-size:14px; }
        .bk-frac b { color:#4338ca; font-weight:800; }
        .bk-frac span { color:#94a3b8; margin:0 3px; }
        .bk-free { color:#047857; font-weight:800; font-size:14px; }
        .bk-short { color:#b91c1c; font-weight:800; font-size:13px; white-space:nowrap; }
        .bk-dash { color:#cbd5e1; }
        .bk-usage { display:flex; align-items:center; gap:8px; min-width:130px; }
        .bk-usage .bk-pct { width:54px; text-align:right; }

        .bk-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
        .bk-status::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; }
        .bk-status.is-ok { background:#ecfdf5; color:#047857; }
        .bk-status.is-high { background:#fff7ed; color:#c2410c; }
        .bk-status.is-full { background:#fefce8; color:#a16207; }
        .bk-status.is-danger { background:#fef2f2; color:#b91c1c; }
        .bk-status.is-pending { background:#fffbeb; color:#b45309; }

        #bk-empty-row td { padding:32px 12px; text-align:center; color:#64748b; font-size:13px; }
        #bk-empty-row.hidden { display:none; }

        @media (max-width: 640px) {
            .bk-page { padding:12px 12px 24px; }
            .bk-head-right { margin-left:0; }
        }
    </style>

    <div class="bk-page">
        @if($slots->isEmpty())
            <div class="bk-empty">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Bu kunda belgilangan test vaqti topilmadi.
            </div>
        @else
            {{-- Xulosa: kompakt interaktiv ko'rsatkichlar --}}
            @php
                $scheduledSlots = $slots->where('no_time', false);
                $pendingSlots = $slots->where('no_time', true);
                $totalSlots = $scheduledSlots->count();
                $overflowSlots = $scheduledSlots->where('overflow', '>', 0)->count();
                $fullSlots = $scheduledSlots->where('usage_percent', '>=', 100)->count();
                $totalStudents = $scheduledSlots->sum('occupied');
                $totalSubmitted = $scheduledSlots->sum('submitted');
                $totalRemaining = $scheduledSlots->sum('remaining');
                $pendingGroups = $pendingSlots->sum(fn($r) => count($r['groups']));
                $pendingStudents = $pendingSlots->sum('occupied');

                $workStart = $settings['work_hours_start'] ?? '09:00';
                $workEnd = $settings['work_hours_end'] ?? '17:00';
                $lunchStart = $settings['lunch_start'] ?? null;
                $lunchEnd = $settings['lunch_end'] ?? null;
                $hasLunch = $lunchStart && $lunchEnd;
                $testDuration = (int) ($settings['test_duration_minutes'] ?? 15);
                $dailyCapacity = (int) ($dailyCapacity ?? 0);

                // "Yana sig'adi" — har bir slotda alohida hisoblanadi:
                //  - bandlangan slotlar uchun: max(0, kompyuter - band)
                //  - hali bandlanmagan slotlar uchun: to'liq sig'im
                //  Sababi: agar bir slot to'lib ketgan bo'lsa, o'sha vaqtda
                //  qo'shimcha talaba sig'maydi — kunlik jami sig'imdan
                //  oddiy ayirish yolg'on ko'rsatkich beradi.
                $dailyMaxSlots = $totalComputers > 0 ? intdiv($dailyCapacity, $totalComputers) : 0;
                $unusedSlots = max(0, $dailyMaxSlots - $totalSlots);
                $totalOverflow = (int) $scheduledSlots->sum('overflow');
                $freeInScheduled = (int) $scheduledSlots->sum('free');
                $freeCapacity = $freeInScheduled + ($unusedSlots * $totalComputers);

                $capacityPct = $dailyCapacity > 0 ? min(100, round(($totalStudents / $dailyCapacity) * 100)) : 0;
                $capacityBarColor = $capacityPct >= 100 ? '#ef4444' : ($capacityPct >= 80 ? '#f97316' : ($capacityPct >= 50 ? '#eab308' : '#10b981'));
                $submitPct = $totalStudents > 0 ? round(($totalSubmitted / $totalStudents) * 100) : 0;
            @endphp
            <section class="bk-kpis">
                {{-- Ish vaqti --}}
                <div class="bk-kpi" style="--kpi:#4f46e5;" title="Ish vaqti{{ $hasLunch ? ' · Tushlik: '.$lunchStart.'–'.$lunchEnd : '' }} · Bitta test: {{ $testDuration }} daq.">
                    <div class="bk-kpi-label">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ish vaqti
                    </div>
                    <div class="bk-kpi-value">{{ $workStart }}<span class="sep">–</span>{{ $workEnd }}</div>
                    <div class="bk-kpi-foot">
                        @if($hasLunch)
                            <span class="bk-tag is-warn" title="Tushlik tanaffusi">Tushlik {{ $lunchStart }}–{{ $lunchEnd }}</span>
                        @endif
                        <span class="bk-tag is-muted">Test {{ $testDuration }} daq</span>
                    </div>
                </div>

                {{-- Sig'im: hozir / maksimal --}}
                <div class="bk-kpi" style="--kpi:#2563eb;" title="Hozir joylashtirilgan talabalar / Kunlik maksimal sig'im ({{ $totalComputers }} kompyuter × slotlar)">
                    <div class="bk-kpi-label">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Sig'im
                    </div>
                    <div class="bk-kpi-value">{{ $totalStudents }}<span class="sep">/</span>{{ $dailyCapacity }}<small>talaba</small></div>
                    <div class="bk-kpi-foot">
                        <div class="bk-bar"><i style="width:{{ $capacityPct }}%; background:{{ $capacityBarColor }};"></i></div>
                        <span class="bk-pct">{{ $capacityPct }}%</span>
                    </div>
                </div>

                {{-- Bo'sh sig'im --}}
                <div class="bk-kpi" style="--kpi:{{ $freeCapacity > 0 ? '#059669' : '#dc2626' }};" title="Boshqa vaqt slotlariga yana shuncha talabani sig'dirsa bo'ladi (har slot {{ $totalComputers }} kompyuterdan ortmasligi shartida)">
                    <div class="bk-kpi-label">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Yana sig'adi
                    </div>
                    <div class="bk-kpi-value" style="color:{{ $freeCapacity > 0 ? '#047857' : '#b91c1c' }};">{{ $freeCapacity }}<small>talaba</small></div>
                    <div class="bk-kpi-foot">
                        @if($totalOverflow > 0)
                            <span class="bk-tag is-danger" title="{{ $overflowSlots }} ta slotda sig'imdan ortiq {{ $totalOverflow }} talaba bor — ularni boshqa vaqtga ko'chirish kerak">⚠ {{ $totalOverflow }} ortiq</span>
                        @else
                            <span class="bk-tag is-ok">Barcha slotlar sig'imda</span>
                        @endif
                    </div>
                </div>

                {{-- Slotlar --}}
                <div class="bk-kpi" style="--kpi:#7c3aed;" title="Jami vaqt slotlari{{ $fullSlots > 0 ? ' · To\'la band: '.($fullSlots - $overflowSlots) : '' }}{{ $overflowSlots > 0 ? ' · Sig\'imdan ortiq: '.$overflowSlots : '' }}">
                    <div class="bk-kpi-label">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Slotlar
                    </div>
                    <div class="bk-kpi-value">{{ $totalSlots }}</div>
                    <div class="bk-kpi-foot">
                        @if(($fullSlots - $overflowSlots) > 0)
                            <span class="bk-tag is-warn" title="To'la band slotlar">{{ $fullSlots - $overflowSlots }} to'la</span>
                        @endif
                        @if($overflowSlots > 0)
                            <span class="bk-tag is-danger" title="Sig'imdan ortiq slotlar">{{ $overflowSlots }} ortiq</span>
                        @endif
                        @if($pendingSlots->isNotEmpty())
                            <span class="bk-tag is-muted" title="Vaqti qo'yilmagan guruhlar">{{ $pendingGroups }} vaqtsiz</span>
                        @endif
                    </div>
                </div>

                {{-- Topshirdi / Qoldi --}}
                <div class="bk-kpi" style="--kpi:#059669;" title="Quizni topshirgan / hali topshirmagan talabalar">
                    <div class="bk-kpi-label">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Topshirdi / Qoldi
                    </div>
                    <div class="bk-kpi-value"><span style="color:#047857;">{{ $totalSubmitted }}</span><span class="sep">/</span><span style="color:#b45309;">{{ $totalRemaining }}</span></div>
                    <div class="bk-kpi-foot">
                        <div class="bk-bar"><i style="width:{{ $submitPct }}%; background:#10b981;"></i></div>
                        <span class="bk-pct">{{ $submitPct }}%</span>
                    </div>
                </div>
            </section>

            @if($pendingSlots->isNotEmpty())
                <div class="bk-alert">
                    <span class="bk-alert-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div class="bk-alert-body">
                        <b>Vaqti qo'yilmagan:</b>
                        {{ $pendingGroups }} guruh
                        @if($pendingStudents > 0)
                            ({{ $pendingStudents }} talaba)
                        @endif
                        — quyidagi jadvalning oxirida ko'rsatilgan.
                    </div>
                </div>
            @endif

            @if(($pendingComputerStudents ?? 0) > 0)
                <div class="bk-alert" style="flex-wrap:wrap;">
                    <span class="bk-alert-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </span>
                    <div class="bk-alert-body">
                        <b>Kompyuter raqami qo'yilmagan:</b>
                        {{ $pendingComputerStudents }} talaba
                        — YN jadvalidan kompyuter raqamlarini taqsimlash kerak.
                    </div>
                    @if(!empty($pendingDetails))
                        <div class="bk-alert-actions">
                            <button type="button" id="bk-assign-missing-btn" onclick="bkAssignMissing()" class="bk-btn is-warn">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Yetishmaganlarni biriktirish
                            </button>
                            <button type="button" onclick="document.getElementById('pending-comp-details').classList.toggle('hidden')" class="bk-btn is-ghost">
                                Tafsilot ({{ count($pendingDetails) }} qator)
                            </button>
                        </div>
                        <div id="pending-comp-details" class="hidden bk-alert-details" style="flex-basis:100%;">
                            <p>
                                Diagnostika: har qatorda kutilgan (talabalar soni) va biriktirilgan (computer_assignments) solishtirildi
                                — <code>qoldi = kutilgan − biriktirilgan</code>.
                                Guruh qatorlari uchun merged per-student schedule_id'lar ham hisobga olingan.
                            </p>
                            <div style="overflow-x:auto;">
                                <table class="bk-mini-table">
                                    <thead>
                                        <tr>
                                            <th>Vaqt</th>
                                            <th>Guruh</th>
                                            <th>Fan</th>
                                            <th>YN</th>
                                            <th class="c">Urin.</th>
                                            <th class="c">Ind.</th>
                                            <th class="r">Kutilgan</th>
                                            <th class="r">Biriktirilgan</th>
                                            <th class="r">Qoldi</th>
                                            <th>schedule_id</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingDetails as $pd)
                                            <tr>
                                                <td class="mono">{{ $pd['time'] ?? '—' }}</td>
                                                <td>{{ $pd['group_name'] }}</td>
                                                <td>{{ $pd['subject_name'] }}</td>
                                                <td>{{ $pd['yn_type'] }}</td>
                                                <td class="c">{{ $pd['attempt'] }}</td>
                                                <td class="c">{{ $pd['is_individual'] ? '✓' : '' }}</td>
                                                <td class="r mono">{{ $pd['expected'] }}</td>
                                                <td class="r mono">{{ $pd['assigned'] }}</td>
                                                <td class="r mono" style="font-weight:700; color:#b91c1c;">{{ $pd['missing'] }}</td>
                                                <td class="mono" style="font-size:10px;">{{ implode(',', $pd['schedule_ids']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="8">JAMI qoldi (qatorlar bo'yicha):</td>
                                            <td class="r mono" style="color:#b91c1c;">{{ array_sum(array_column($pendingDetails, 'missing')) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Ustun bo'yicha filtrlar uchun unikal qiymatlarni hisoblash --}}
            @php
                $uniqueTimes = [];
                $hasNoTimeRow = false;
                $uniqueGroupNames = [];
                $uniqueSubjects = [];
                $uniqueYnTypes = [];
                $uniqueAttempts = [];
                $uniqueStudents = [];
                $uniqueSubmitted = [];
                $uniqueRemaining = [];
                $uniqueOccupied = [];
                $uniqueFree = [];
                $uniqueUsage = [];
                $uniqueStatuses = [];

                foreach ($slots as $slot) {
                    $no = !empty($slot['no_time']);
                    if ($no) {
                        $hasNoTimeRow = true;
                    } else {
                        $uniqueTimes[(string) $slot['time']] = true;
                    }
                    foreach ($slot['groups'] as $g) {
                        if (!empty($g['group_name'])) $uniqueGroupNames[(string) $g['group_name']] = true;
                        if (!empty($g['subject_name'])) $uniqueSubjects[(string) $g['subject_name']] = true;
                        if (!empty($g['yn_type'])) $uniqueYnTypes[(string) $g['yn_type']] = true;
                        $uniqueAttempts[(int) ($g['attempt'] ?? 1)] = true;
                    }
                    $occ = (int) $slot['occupied'];
                    $sub = (int) ($slot['submitted'] ?? 0);
                    $rem = (int) ($slot['remaining'] ?? max(0, $occ - $sub));
                    $uniqueStudents[$occ] = true;
                    $uniqueSubmitted[$sub] = true;
                    $uniqueRemaining[$rem] = true;
                    if (!$no) {
                        $uniqueOccupied[$occ] = true;
                        $uniqueFree[(int) $slot['free']] = true;
                        $uniqueUsage[(float) $slot['usage_percent']] = true;
                        if ($slot['overflow'] > 0) {
                            $uniqueStatuses["Sig'imdan ortiq"] = true;
                        } elseif ($slot['usage_percent'] >= 100) {
                            $uniqueStatuses["To'la band"] = true;
                        } elseif ($slot['usage_percent'] >= 75) {
                            $uniqueStatuses['Yuqori bandlik'] = true;
                        } else {
                            $uniqueStatuses['Normal'] = true;
                        }
                    } else {
                        $uniqueStatuses["Vaqti qo'yilmagan"] = true;
                    }
                }
                $sortedTimes = array_keys($uniqueTimes); sort($sortedTimes);
                $sortedGroupNames = array_keys($uniqueGroupNames); natcasesort($sortedGroupNames); $sortedGroupNames = array_values($sortedGroupNames);
                $sortedSubjects = array_keys($uniqueSubjects); natcasesort($sortedSubjects); $sortedSubjects = array_values($sortedSubjects);
                $sortedYnTypes = array_keys($uniqueYnTypes); sort($sortedYnTypes);
                $sortedAttempts = array_keys($uniqueAttempts); sort($sortedAttempts);
                $sortedStudents = array_keys($uniqueStudents); sort($sortedStudents, SORT_NUMERIC);
                $sortedSubmitted = array_keys($uniqueSubmitted); sort($sortedSubmitted, SORT_NUMERIC);
                $sortedRemaining = array_keys($uniqueRemaining); sort($sortedRemaining, SORT_NUMERIC);
                $sortedOccupied = array_keys($uniqueOccupied); sort($sortedOccupied, SORT_NUMERIC);
                $sortedFree = array_keys($uniqueFree); sort($sortedFree, SORT_NUMERIC);
                $sortedUsage = array_keys($uniqueUsage); sort($sortedUsage, SORT_NUMERIC);
                $sortedStatuses = array_keys($uniqueStatuses); sort($sortedStatuses);
            @endphp

            {{-- Ko'p slotni tanlab Word'ga chiqarish toolbar'i. Har bir slot
                 qatorida checkbox bor; bu yerda tanlangan slotlar
                 vaqt tartibida bitta .docx'ga birlashtirib yuklab olinadi. --}}
            <div class="bk-toolbar">
                <label class="bk-check">
                    <input type="checkbox" id="bk-select-all" class="bk-cb">
                    Hammasini tanlash
                </label>
                <span class="bk-count">Tanlangan <b id="bk-selected-count">0</b></span>
                <button type="button" id="bk-bulk-word" class="bk-btn is-primary" disabled
                        title="Tanlangan slotlarni vaqt tartibida bitta Word hujjatga chiqarish">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Tanlanganlarni Word'ga chiqarish
                </button>
            </div>

            {{-- Jadval --}}
            <div class="bk-table-wrap">
                <table id="bk-table" class="bk-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th style="width:44px;">#</th>
                            <th>Vaqt</th>
                            <th class="is-left">Guruhlar</th>
                            <th>Talabalar</th>
                            <th>Topshirdi / Qoldi</th>
                            <th>Band / Jami</th>
                            <th>Bo'sh</th>
                            <th>Bandlik %</th>
                            <th>Holat</th>
                        </tr>
                        <tr class="bk-filters">
                            <th></th>
                            <th>
                                <button type="button" id="bk-filter-reset" class="bk-reset" title="Filtrlarni tozalash">↺</button>
                            </th>
                            <th>
                                <select data-bk-filter="time" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedTimes as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                    @if($hasNoTimeRow)
                                        <option value="__no_time__">Vaqti qo'yilmagan</option>
                                    @endif
                                </select>
                            </th>
                            <th>
                                <div class="bk-filter-group">
                                    <select data-bk-filter="group" class="bk-filter bk-select" title="Guruh nomi">
                                        <option value="">Guruh</option>
                                        @foreach($sortedGroupNames as $g)
                                            <option value="{{ $g }}">{{ $g }}</option>
                                        @endforeach
                                    </select>
                                    <select data-bk-filter="subject" class="bk-filter bk-select" title="Fan">
                                        <option value="">Fan</option>
                                        @foreach($sortedSubjects as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                    <select data-bk-filter="yn" class="bk-filter bk-select is-narrow" title="YN turi">
                                        <option value="">YN</option>
                                        @foreach($sortedYnTypes as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                    <select data-bk-filter="attempt" class="bk-filter bk-select is-narrow" title="Urinish">
                                        <option value="">Urinish</option>
                                        @foreach($sortedAttempts as $a)
                                            <option value="{{ $a }}">{{ $a }}-urinish</option>
                                        @endforeach
                                    </select>
                                </div>
                            </th>
                            <th>
                                <select data-bk-filter="students" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedStudents as $v)
                                        <option value="{{ $v }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <div class="bk-filter-group">
                                    <select data-bk-filter="submitted" class="bk-filter bk-select" title="Topshirdi">
                                        <option value="">Topshirdi</option>
                                        @foreach($sortedSubmitted as $v)
                                            <option value="{{ $v }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                    <select data-bk-filter="remaining" class="bk-filter bk-select" title="Qoldi">
                                        <option value="">Qoldi</option>
                                        @foreach($sortedRemaining as $v)
                                            <option value="{{ $v }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </th>
                            <th>
                                <select data-bk-filter="occupied" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedOccupied as $v)
                                        <option value="{{ $v }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <select data-bk-filter="free" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedFree as $v)
                                        <option value="{{ $v }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <select data-bk-filter="usage" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedUsage as $v)
                                        <option value="{{ $v }}">{{ rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') }}%</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <select data-bk-filter="status" class="bk-filter bk-select">
                                    <option value="">Barchasi</option>
                                    @foreach($sortedStatuses as $s)
                                        <option value="{{ $s }}">{{ $s }}</option>
                                    @endforeach
                                </select>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $i => $slot)
                            @php
                                $isNoTime = !empty($slot['no_time']);
                                if ($isNoTime) {
                                    $rowState = 'is-pending';
                                    $statusLabel = "Vaqti qo'yilmagan";
                                    $statusClass = 'is-pending';
                                    $barColor = '#fbbf24';
                                } elseif ($slot['overflow'] > 0) {
                                    $rowState = 'is-danger';
                                    $statusLabel = "Sig'imdan ortiq";
                                    $statusClass = 'is-danger';
                                    $barColor = '#ef4444';
                                } elseif ($slot['usage_percent'] >= 100) {
                                    $rowState = 'is-full';
                                    $statusLabel = "To'la band";
                                    $statusClass = 'is-full';
                                    $barColor = '#ef4444';
                                } elseif ($slot['usage_percent'] >= 75) {
                                    $rowState = 'is-high';
                                    $statusLabel = 'Yuqori bandlik';
                                    $statusClass = 'is-high';
                                    $barColor = '#f97316';
                                } elseif ($slot['usage_percent'] >= 50) {
                                    $rowState = '';
                                    $statusLabel = 'Normal';
                                    $statusClass = 'is-ok';
                                    $barColor = '#eab308';
                                } else {
                                    $rowState = '';
                                    $statusLabel = 'Normal';
                                    $statusClass = 'is-ok';
                                    $barColor = '#10b981';
                                }

                                $slotOccupied = (int) $slot['occupied'];
                                $slotSubmitted = (int) ($slot['submitted'] ?? 0);
                                $slotRemaining = (int) ($slot['remaining'] ?? max(0, $slotOccupied - $slotSubmitted));
                                $submitPercent = $slotOccupied > 0 ? round(($slotSubmitted / $slotOccupied) * 100) : 0;

                                $rowGroupNames = [];
                                $rowSubjects = [];
                                $rowYnTypes = [];
                                $rowAttempts = [];
                                foreach ($slot['groups'] as $_g) {
                                    if (!empty($_g['group_name'])) $rowGroupNames[] = $_g['group_name'];
                                    if (!empty($_g['subject_name'])) $rowSubjects[] = $_g['subject_name'];
                                    if (!empty($_g['yn_type'])) $rowYnTypes[] = $_g['yn_type'];
                                    $rowAttempts[] = (int) ($_g['attempt'] ?? 1);
                                }
                                $dataTime = $isNoTime ? '__no_time__' : (string) $slot['time'];
                            @endphp
                            <tr class="bk-row {{ $rowState }}"
                                data-time="{{ $dataTime }}"
                                data-groups="|{{ implode('|', array_unique($rowGroupNames)) }}|"
                                data-subjects="|{{ implode('|', array_unique($rowSubjects)) }}|"
                                data-yns="|{{ implode('|', array_unique($rowYnTypes)) }}|"
                                data-attempts="|{{ implode('|', array_unique($rowAttempts)) }}|"
                                data-students="{{ $slotOccupied }}"
                                data-submitted="{{ $slotSubmitted }}"
                                data-remaining="{{ $slotRemaining }}"
                                data-occupied="{{ $isNoTime ? '' : $slotOccupied }}"
                                data-free="{{ $isNoTime ? '' : (int) $slot['free'] }}"
                                data-usage="{{ $isNoTime ? '' : (float) $slot['usage_percent'] }}"
                                data-status="{{ $statusLabel }}">
                                <td>
                                    @php
                                        $rowExportItems = [];
                                        foreach ($slot['groups'] as $_grp) {
                                            if (empty($_grp['group_hemis_id']) || empty($_grp['subject_id']) || empty($_grp['semester_code'])) continue;
                                            $rowExportItems[] = [
                                                'group_hemis_id' => (string) $_grp['group_hemis_id'],
                                                'subject_id'     => (string) $_grp['subject_id'],
                                                'semester_code'  => (string) $_grp['semester_code'],
                                                'attempt'        => (int) ($_grp['attempt'] ?? 1),
                                                'student_hemis_id' => !empty($_grp['student_hemis_id']) ? (string) $_grp['student_hemis_id'] : null,
                                                'schedule_id'    => (int) ($_grp['schedule_id'] ?? 0),
                                                'yn_type'        => isset($_grp['yn_type']) ? strtolower((string) $_grp['yn_type']) : null,
                                            ];
                                        }
                                    @endphp
                                    @if(!empty($rowExportItems) && !$isNoTime)
                                        <input type="checkbox"
                                               class="bk-row-select bk-cb"
                                               data-items='@json($rowExportItems)'
                                               data-exam-time="{{ $slot['time'] }}"
                                               title="Bu slotni Word eksportiga qo'shish">
                                    @endif
                                </td>
                                <td class="bk-idx">{{ $i + 1 }}</td>
                                <td>
                                    <div class="bk-time-cell">
                                        @if($isNoTime)
                                            <span class="bk-time is-none" title="Vaqti hali belgilanmagan">Vaqti qo'yilmagan</span>
                                        @else
                                            <span class="bk-time">{{ $slot['time'] }}</span>
                                        @endif
                                        @php
                                            // Bitta slotda guruh-level + per-student qatorlar bir xil
                                            // (group, subject, sem, yn_type, attempt) uchun bir necha marta
                                            // uchrashi mumkin. Har birikma uchun: guruh-level yozuv
                                            // (student_hemis_id bo'sh) bo'lsa — faqat o'shani; aks holda
                                            // barcha per-student yozuvlarni yuboramiz.
                                            // DIQQAT: yn_type va attempt kalitda — aks holda bir guruhning
                                            // OSKI va Test (yoki turli urinish) qatorlari ustma-ust tushib
                                            // Word'dan tushib qolardi.
                                            $exportBuckets = [];
                                            foreach ($slot['groups'] as $_grp) {
                                                if (empty($_grp['group_hemis_id']) || empty($_grp['subject_id']) || empty($_grp['semester_code'])) continue;
                                                $k = $_grp['group_hemis_id'] . '|' . $_grp['subject_id'] . '|' . $_grp['semester_code']
                                                    . '|' . strtolower((string) ($_grp['yn_type'] ?? ''))
                                                    . '|' . (int) ($_grp['attempt'] ?? 1);
                                                $exportBuckets[$k][] = $_grp;
                                            }
                                            $exportItems = [];
                                            foreach ($exportBuckets as $_bucket) {
                                                $_groupLevel = null;
                                                foreach ($_bucket as $_it) {
                                                    if (empty($_it['student_hemis_id'])) { $_groupLevel = $_it; break; }
                                                }
                                                $_chosen = $_groupLevel !== null ? [$_groupLevel] : $_bucket;
                                                foreach ($_chosen as $_it) {
                                                    $exportItems[] = [
                                                        'group_hemis_id' => (string) $_it['group_hemis_id'],
                                                        'subject_id'     => (string) $_it['subject_id'],
                                                        'semester_code'  => (string) $_it['semester_code'],
                                                        'attempt'        => (int) ($_it['attempt'] ?? 1),
                                                        'student_hemis_id' => !empty($_it['student_hemis_id']) ? (string) $_it['student_hemis_id'] : null,
                                                        'schedule_id'    => (int) ($_it['schedule_id'] ?? 0),
                                                        'yn_type'        => isset($_it['yn_type']) ? strtolower((string) $_it['yn_type']) : null,
                                                    ];
                                                }
                                            }
                                        @endphp
                                        @if(!empty($exportItems))
                                            <button type="button"
                                                    class="bk-word-export bk-word"
                                                    data-items='@json($exportItems)'
                                                    data-exam-date="{{ $date->format('Y-m-d') }}"
                                                    data-exam-time="{{ $isNoTime ? '' : $slot['time'] }}"
                                                    title="Bu slotdagi guruhlar uchun 12-shakl YN oldi qaydnomasini Word formatida yuklab olish">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                                Word
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="is-left">
                                    <div class="bk-groups">
                                        @foreach($slot['groups'] as $grp)
                                            @php
                                                $grpCnt = (int) ($grp['student_count'] ?? 0);
                                                $grpQuiz = (int) ($grp['quiz_count'] ?? 0);
                                                $grpRem = (int) ($grp['remaining'] ?? max(0, $grpCnt - $grpQuiz));
                                                $grpYn = $grp['yn_type'] ?? '';
                                                $grpAttempt = (int) ($grp['attempt'] ?? 1);
                                                $grpAttemptClass = match($grpAttempt) {
                                                    2 => 'is-orange',
                                                    3 => 'is-danger',
                                                    default => 'is-muted',
                                                };
                                            @endphp
                                            {{-- Group-level qator 0 talabani ifoda etayotgan bo'lsa
                                                 (per-student qatorlar barchasini qoplagan), ko'rsatmaymiz —
                                                 keraksiz "Qoldi: 0" satrlar bilan UI'ni shovqinlamaymiz. --}}
                                            @if($grpCnt === 0 && empty($grp['student_hemis_id']))
                                                @continue
                                            @endif
                                            <div class="bk-group">
                                                <span class="bk-num" title="Guruhdagi jami talabalar">{{ $grpCnt }}</span>
                                                @if($grpYn === 'OSKI')
                                                    <span class="bk-tag is-purple">OSKI</span>
                                                @else
                                                    <span class="bk-tag is-info">Test</span>
                                                @endif
                                                <span class="bk-tag {{ $grpAttemptClass }}" title="Urinish raqami">{{ $grpAttempt }}-urinish</span>
                                                <span class="bk-group-name">{{ $grp['group_name'] }}</span>
                                                @if(!empty($grp['is_individual']))
                                                    <span class="bk-tag is-pink" title="Individual vaqt qo'yilgan talaba">individual</span>
                                                @endif
                                                @if(!empty($grp['subject_name']))
                                                    <span class="bk-sep">—</span>
                                                    <span class="bk-group-subj">{{ $grp['subject_name'] }}</span>
                                                @endif
                                                <span class="bk-sep">·</span>
                                                <span class="bk-mini is-ok" title="Quizni topshirgan talabalar soni">Topshirdi: {{ $grpQuiz }}</span>
                                                <span class="bk-mini {{ $grpRem > 0 ? 'is-warn' : 'is-muted' }}" title="Hali topshirmagan talabalar soni">Qoldi: {{ $grpRem }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td><span class="bk-big">{{ $slotOccupied }}</span></td>
                                <td>
                                    <div class="bk-stack">
                                        <div style="display:flex; gap:4px;">
                                            <span class="bk-mini is-ok" title="Quizni topshirgan talabalar soni">Topshirdi: {{ $slotSubmitted }}</span>
                                            <span class="bk-mini {{ $slotRemaining > 0 ? 'is-warn' : 'is-muted' }}" title="Hali topshirmagan talabalar soni">Qoldi: {{ $slotRemaining }}</span>
                                        </div>
                                        <div class="bk-bar"><i style="width:{{ $submitPercent }}%; background:#10b981;"></i></div>
                                        <span class="bk-pct">{{ $submitPercent }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($isNoTime)
                                        <span class="bk-dash">—</span>
                                    @else
                                        <span class="bk-frac"><b>{{ $slotOccupied }}</b><span>/</span>{{ $totalComputers }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isNoTime)
                                        <span class="bk-dash">—</span>
                                    @elseif($slot['overflow'] > 0)
                                        <span class="bk-short">−{{ $slot['overflow'] }} yetmaydi</span>
                                    @else
                                        <span class="bk-free">{{ $slot['free'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isNoTime)
                                        <span class="bk-dash">—</span>
                                    @else
                                        <div class="bk-usage">
                                            <div class="bk-bar"><i style="width:{{ min(100, $slot['usage_percent']) }}%; background:{{ $barColor }};"></i></div>
                                            <span class="bk-pct">{{ $slot['usage_percent'] }}%</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="bk-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="bk-empty-row" class="hidden">
                            <td colspan="10">Filtr bo'yicha mos qator topilmadi.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
                    <script>
                        (function() {
                            const filters = document.querySelectorAll('.bk-filter');
                            const rows = document.querySelectorAll('#bk-table .bk-row');
                            const emptyRow = document.getElementById('bk-empty-row');
                            const resetBtn = document.getElementById('bk-filter-reset');

                            function rowMatches(row) {
                                for (const f of filters) {
                                    const v = f.value;
                                    if (!v) continue;
                                    const key = f.dataset.bkFilter;
                                    if (key === 'group' || key === 'subject' || key === 'yn' || key === 'attempt') {
                                        const attr = key === 'group' ? 'groups' :
                                                     key === 'subject' ? 'subjects' :
                                                     key === 'yn' ? 'yns' : 'attempts';
                                        const cell = row.dataset[attr] || '';
                                        if (cell.indexOf('|' + v + '|') === -1) return false;
                                    } else {
                                        const cell = row.dataset[key];
                                        if (cell === undefined || cell === '' || String(cell) !== v) return false;
                                    }
                                }
                                return true;
                            }

                            function applyFilters() {
                                let visible = 0;
                                rows.forEach(r => {
                                    if (rowMatches(r)) {
                                        r.classList.remove('hidden');
                                        visible++;
                                    } else {
                                        r.classList.add('hidden');
                                    }
                                });
                                if (emptyRow) emptyRow.classList.toggle('hidden', visible !== 0 || rows.length === 0);
                            }

                            filters.forEach(f => f.addEventListener('change', applyFilters));
                            if (resetBtn) resetBtn.addEventListener('click', function() {
                                filters.forEach(f => { f.value = ''; });
                                applyFilters();
                            });
                        })();

                        // Slot bo'yicha "Word" tugmasi — 12-shakl YN oldi qaydnomasini yuklab olish.
                        // Slotning barcha guruhlari (group+subject+semester) bitta POST'da yuboriladi va
                        // server tomonida fan bo'yicha guruhlanib bitta .docx fayl qaytaradi (yoki
                        // birdan ortiq fan bo'lsa .zip). Tugma data-items atributidan ro'yxatni o'qiydi.
                        (function() {
                            const url = @json(route($ynOldiWordRoute));
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                            document.querySelectorAll('.bk-word-export').forEach(function(btn) {
                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    let items;
                                    try {
                                        items = JSON.parse(btn.getAttribute('data-items') || '[]');
                                    } catch (_) {
                                        items = [];
                                    }
                                    if (!Array.isArray(items) || items.length === 0) {
                                        alert("Bu slotda yuklab olish uchun guruh topilmadi.");
                                        return;
                                    }

                                    const originalHTML = btn.innerHTML;
                                    btn.disabled = true;
                                    btn.innerHTML = '<svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Yuklanmoqda...';

                                    const payload = {
                                        items: items,
                                        compact: true,
                                        exam_date: btn.getAttribute('data-exam-date') || undefined,
                                    };
                                    const examTime = btn.getAttribute('data-exam-time');
                                    if (examTime) payload.exam_time = examTime;

                                    fetch(url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': csrfToken,
                                        },
                                        body: JSON.stringify(payload),
                                    })
                                    .then(function(response) {
                                        if (!response.ok) {
                                            return response.text().then(function(text) {
                                                let msg = 'Xatolik: ' + response.status;
                                                try { const j = JSON.parse(text); msg = j.error || j.message || msg; } catch (_) {}
                                                throw new Error(msg);
                                            });
                                        }
                                        const contentType = response.headers.get('content-type') || '';
                                        if (contentType.indexOf('application/json') !== -1) {
                                            return response.json().then(function(j) {
                                                throw new Error(j.error || j.message || 'Kutilmagan javob');
                                            });
                                        }
                                        let filename = 'yn_oldi_qaydnoma.docx';
                                        const disposition = response.headers.get('Content-Disposition');
                                        if (disposition) {
                                            const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                                            if (match && match[1]) filename = match[1].replace(/['"]/g, '');
                                        }
                                        return response.blob().then(function(blob) { return { blob: blob, filename: filename }; });
                                    })
                                    .then(function(data) {
                                        if (!data || !data.blob) return;
                                        const objectUrl = window.URL.createObjectURL(data.blob);
                                        const a = document.createElement('a');
                                        a.href = objectUrl;
                                        a.download = data.filename;
                                        document.body.appendChild(a);
                                        a.click();
                                        window.URL.revokeObjectURL(objectUrl);
                                        a.remove();
                                    })
                                    .catch(function(err) {
                                        alert(err.message || 'Yuklab olishda xatolik');
                                    })
                                    .finally(function() {
                                        btn.innerHTML = originalHTML;
                                        btn.disabled = false;
                                    });
                                });
                            });
                        })();

                        // Bulk: bir nechta slotni tanlab vaqt tartibida bitta Word.
                        // Har checked qator data-items va data-exam-time atributlaridan
                        // o'qiladi; har item'ga exam_time qo'shilib serverga yuboriladi —
                        // controller per-item exam_time bo'lsa vaqt tartibida bitta
                        // hujjat yasaydi.
                        (function() {
                            const bulkBtn = document.getElementById('bk-bulk-word');
                            const selectAll = document.getElementById('bk-select-all');
                            const countEl = document.getElementById('bk-selected-count');
                            const url = @json(route($ynOldiWordRoute));
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                            function getRowCheckboxes() {
                                return Array.from(document.querySelectorAll('.bk-row-select'));
                            }
                            function getVisibleCheckboxes() {
                                return getRowCheckboxes().filter(cb => !cb.closest('tr')?.classList.contains('hidden'));
                            }
                            function refreshState() {
                                const checked = getRowCheckboxes().filter(cb => cb.checked);
                                if (countEl) countEl.textContent = String(checked.length);
                                if (bulkBtn) bulkBtn.disabled = checked.length === 0;
                                if (selectAll) {
                                    const visible = getVisibleCheckboxes();
                                    const visibleChecked = visible.filter(cb => cb.checked);
                                    selectAll.checked = visible.length > 0 && visibleChecked.length === visible.length;
                                    selectAll.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visible.length;
                                }
                            }

                            getRowCheckboxes().forEach(cb => cb.addEventListener('change', refreshState));
                            if (selectAll) {
                                selectAll.addEventListener('change', function() {
                                    getVisibleCheckboxes().forEach(cb => { cb.checked = selectAll.checked; });
                                    refreshState();
                                });
                            }
                            // Filtr qo'llanilganda select-all holatini yangilash.
                            document.querySelectorAll('.bk-filter').forEach(f => f.addEventListener('change', refreshState));
                            const resetBtn = document.getElementById('bk-filter-reset');
                            if (resetBtn) resetBtn.addEventListener('click', refreshState);

                            if (bulkBtn) {
                                bulkBtn.addEventListener('click', function() {
                                    const checked = getRowCheckboxes().filter(cb => cb.checked);
                                    if (checked.length === 0) return;

                                    const items = [];
                                    checked.forEach(function(cb) {
                                        let rowItems;
                                        try { rowItems = JSON.parse(cb.getAttribute('data-items') || '[]'); }
                                        catch (_) { rowItems = []; }
                                        const examTime = cb.getAttribute('data-exam-time') || '';
                                        rowItems.forEach(function(it) {
                                            if (examTime) it.exam_time = examTime;
                                            items.push(it);
                                        });
                                    });
                                    if (items.length === 0) {
                                        alert('Tanlangan slotlarda guruh topilmadi.');
                                        return;
                                    }

                                    const originalHTML = bulkBtn.innerHTML;
                                    bulkBtn.disabled = true;
                                    bulkBtn.innerHTML = '<svg class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Yuklanmoqda...';

                                    fetch(url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': csrfToken,
                                        },
                                        body: JSON.stringify({
                                            items: items,
                                            compact: true,
                                            exam_date: @json($date->format('Y-m-d')),
                                        }),
                                    })
                                    .then(function(response) {
                                        if (!response.ok) {
                                            return response.text().then(function(text) {
                                                let msg = 'Xatolik: ' + response.status;
                                                try { const j = JSON.parse(text); msg = j.error || j.message || msg; } catch (_) {}
                                                throw new Error(msg);
                                            });
                                        }
                                        const contentType = response.headers.get('content-type') || '';
                                        if (contentType.indexOf('application/json') !== -1) {
                                            return response.json().then(function(j) {
                                                throw new Error(j.error || j.message || 'Kutilmagan javob');
                                            });
                                        }
                                        let filename = 'yn_oldi_qaydnoma_slotlar.docx';
                                        const disposition = response.headers.get('Content-Disposition');
                                        if (disposition) {
                                            const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                                            if (match && match[1]) filename = match[1].replace(/['"]/g, '');
                                        }
                                        return response.blob().then(function(blob) { return { blob: blob, filename: filename }; });
                                    })
                                    .then(function(data) {
                                        if (!data || !data.blob) return;
                                        const objectUrl = window.URL.createObjectURL(data.blob);
                                        const a = document.createElement('a');
                                        a.href = objectUrl;
                                        a.download = data.filename;
                                        document.body.appendChild(a);
                                        a.click();
                                        window.URL.revokeObjectURL(objectUrl);
                                        a.remove();
                                    })
                                    .catch(function(err) {
                                        alert(err.message || 'Yuklab olishda xatolik');
                                    })
                                    .finally(function() {
                                        bulkBtn.innerHTML = originalHTML;
                                        refreshState();
                                    });
                                });
                            }

                            refreshState();
                        })();
                    </script>
                @endif

                @if(!empty($pendingDetails))
                    @php
                        $pendingItemsForJs = array_map(fn($d) => [
                            'schedule_id' => $d['schedule_id'],
                            'yn_type' => $d['yn_type'],
                            'attempt' => $d['attempt'],
                        ], $pendingDetails);
                    @endphp
                    <script>
                        (function () {
                            // pendingDetails dan items ro'yxati — serverga shu yuboriladi.
                            var PENDING_ITEMS = @json($pendingItemsForJs);
                            var ASSIGN_URL = @json(route($assignMissingRoute));
                            var STATUS_URL = @json(route($assignMissingStatusRoute));

                            window.bkAssignMissing = function () {
                                if (!PENDING_ITEMS.length) return;
                                if (!confirm(PENDING_ITEMS.length + ' ta qatorga kompyuter raqamlarini biriktirish boshlansinmi? Bu qoldiq talabalarga raqam tayinlaydi.')) {
                                    return;
                                }
                                var btn = document.getElementById('bk-assign-missing-btn');
                                var originalHTML = btn.innerHTML;
                                btn.disabled = true;
                                btn.textContent = 'Yuborilmoqda...';
                                fetch(ASSIGN_URL, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    },
                                    body: JSON.stringify({ items: PENDING_ITEMS }),
                                })
                                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                                .then(function (res) {
                                    if (!res.ok || !res.body || !res.body.success) {
                                        alert((res.body && (res.body.message || res.body.error)) || 'Xatolik yuz berdi');
                                        btn.disabled = false; btn.innerHTML = originalHTML;
                                        return;
                                    }
                                    if (!res.body.token) {
                                        alert(res.body.message || 'Navbatga qo\'yildi');
                                        btn.disabled = false; btn.innerHTML = originalHTML;
                                        return;
                                    }
                                    btn.textContent = 'Biriktirilmoqda…';
                                    bkPollMissing(res.body.token, btn, originalHTML, 0);
                                })
                                .catch(function (e) {
                                    alert('Xatolik: ' + e.message);
                                    btn.disabled = false; btn.innerHTML = originalHTML;
                                });
                            };

                            function bkPollMissing(token, btn, originalHTML, attempts) {
                                // 600 × 3s = 30 daqiqa. AssignComputersForRangeJob ham
                                // shunaqa cheklov bilan ishlaydi. Queue worker zudlik
                                // bilan ishlamasa yoki ko'p item bo'lsa shuncha kerak.
                                if (attempts > 600) {
                                    alert('Biriktirish kutilganidan uzoq davom etmoqda. Sahifani qayta yuklab natijani tekshiring.');
                                    btn.disabled = false; btn.innerHTML = originalHTML;
                                    return;
                                }
                                setTimeout(function () {
                                    fetch(STATUS_URL + '?token=' + encodeURIComponent(token), {
                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    })
                                    .then(function (r) { return r.json(); })
                                    .then(function (st) {
                                        if (st && st.status === 'done') {
                                            var msg = '✅ Tayyor! ' + (st.assigned || 0) + ' ta talabaga kompyuter raqami biriktirildi.';
                                            if (st.skipped) msg += '\n⊝ O\'tkazib yuborildi: ' + st.skipped;
                                            if (st.failed_total) {
                                                msg += '\n❗ Muvaffaqiyatsiz: ' + st.failed_total + ' ta qator.';
                                                if (Array.isArray(st.failures) && st.failures.length) {
                                                    msg += '\nMisol sabablari (birinchi ' + st.failures.length + '):';
                                                    st.failures.slice(0, 10).forEach(function (f) {
                                                        msg += '\n  • #' + f.sid + ' ' + f.ynType + '/' + f.attempt + ' — ' + f.reason;
                                                    });
                                                }
                                            }
                                            alert(msg);
                                            window.location.reload();
                                        } else if (st && st.status === 'failed') {
                                            alert('❌ Biriktirishda xatolik yuz berdi: ' + (st.message || 'noma\'lum'));
                                            btn.disabled = false; btn.innerHTML = originalHTML;
                                        } else {
                                            // Tugma matnida progress ko'rsatamiz.
                                            if (st && st.status === 'running' && typeof st.processed === 'number') {
                                                btn.textContent = 'Biriktirilmoqda… ' + st.processed + '/' + (st.total || PENDING_ITEMS.length);
                                            } else if (st && st.status === 'queued') {
                                                btn.textContent = 'Navbatda…';
                                            }
                                            bkPollMissing(token, btn, originalHTML, attempts + 1);
                                        }
                                    })
                                    .catch(function () {
                                        bkPollMissing(token, btn, originalHTML, attempts + 1);
                                    });
                                }, 3000);
                            }
                        })();
                    </script>
                @endif
    </div>
</x-app-layout>
