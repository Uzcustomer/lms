<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Joriy fanlar <span class="text-base font-normal text-gray-500">({{ $semester }})</span>
        </h2>
    </x-slot>

    @php
        $subjectCount = $subjects->count();
        $totalCredit = 0;
        $totalAbsent = 0;
        $totalAuditorium = 0;
        $jnSum = 0;
        $jnCount = 0;
        foreach ($subjects as $s) {
            $totalCredit += $s['credit'] ?? 0;
            $totalAbsent += $s['absent_hours'] ?? 0;
            $totalAuditorium += $s['auditorium_hours'] ?? 0;
            if (($s['jn_average'] ?? 0) > 0) {
                $jnSum += $s['jn_average'];
                $jnCount++;
            }
        }
        $avgJn = $jnCount > 0 ? round($jnSum / $jnCount) : 0;
    @endphp

    <style>
        /* ===== SUMMARY BAR ===== */
        .summary-bar {
            display: flex; align-items: center; flex-wrap: wrap; gap: 20px;
            padding: 14px 22px; background: white; border-radius: 12px;
            margin-bottom: 20px; border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .dark .summary-bar { background: #1e293b; border-color: #334155; }
        .s-item { display: flex; align-items: center; gap: 8px; }
        .s-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .s-text { font-size: 13px; color: #64748b; }
        .s-text b { color: #1e293b; font-weight: 800; }
        .dark .s-text { color: #94a3b8; }
        .dark .s-text b { color: #f1f5f9; }
        .s-sep { width: 1px; height: 20px; background: #e2e8f0; }
        .dark .s-sep { background: #334155; }

        /* ===== CARDS GRID ===== */
        .cards-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
        }
        @media (max-width: 1200px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .cards-grid { grid-template-columns: 1fr; } }

        /* ===== SUBJECT CARD ===== */
        .subject-card {
            background: white; border-radius: 14px; overflow: hidden;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .subject-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.03);
            border-color: var(--card-hover-border, #e0e7ff);
        }
        .dark .subject-card { background: #1e293b !important; border-color: #334155; }
        .dark .subject-card:hover { border-color: var(--card-hover-border, #4f46e5); box-shadow: 0 12px 28px rgba(0,0,0,0.25); }

        .card-accent { height: 5px; }
        .card-body { padding: 18px; }

        .card-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 10px; margin-bottom: 16px;
        }
        .card-title {
            font-size: 13.5px; font-weight: 700; color: #0f172a;
            line-height: 1.35; margin: 0; flex: 1;
        }
        .dark .card-title { color: #f1f5f9; }
        .card-credit {
            padding: 3px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700;
            background: #f1f5f9; color: #475569;
            white-space: nowrap; flex-shrink: 0;
        }
        .dark .card-credit { background: #334155; color: #94a3b8; }

        /* ===== GRADES GRID ===== */
        .grades-grid {
            display: grid; grid-template-columns: repeat(6, 1fr);
            gap: 6px; margin-bottom: 14px;
        }
        .g-item { text-align: center; }
        .g-label {
            font-size: 9.5px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;
        }
        .g-value {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; height: 32px; border-radius: 8px;
            font-weight: 700; font-size: 14px; padding: 0 4px;
        }
        .g-excellent { background: #ecfdf5; color: #059669; }
        .g-good { background: #eff6ff; color: #2563eb; }
        .g-ok { background: #fffbeb; color: #d97706; }
        .g-fail { background: #fef2f2; color: #dc2626; }
        .g-none { background: #f8fafc; color: #cbd5e1; }
        .dark .g-excellent { background: #064e3b; color: #6ee7b7; }
        .dark .g-good { background: #1e3a5f; color: #93c5fd; }
        .dark .g-ok { background: #78350f; color: #fcd34d; }
        .dark .g-fail { background: #7f1d1d; color: #fca5a5; }
        .dark .g-none { background: #1e293b; color: #475569; }

        /* ===== DAV SECTION ===== */
        .dav-section {
            display: flex; align-items: center; gap: 10px;
            padding-top: 14px; margin-bottom: 14px;
            border-top: 1px solid #f1f5f9;
        }
        .dark .dav-section { border-top-color: #334155; }
        .dav-label {
            font-size: 9.5px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
        }
        .dav-track {
            flex: 1; height: 6px; border-radius: 3px;
            background: #f1f5f9; overflow: hidden;
        }
        .dark .dav-track { background: #334155; }
        .dav-fill { height: 100%; border-radius: 3px; transition: width 0.8s ease; }
        .dav-value { font-size: 12px; font-weight: 700; white-space: nowrap; min-width: 50px; text-align: right; }

        /* ===== CARD FOOTER ===== */
        .card-footer {
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-hours { font-size: 11px; color: #94a3b8; }
        .card-hours b { color: #64748b; }
        .dark .card-hours { color: #64748b; }
        .dark .card-hours b { color: #94a3b8; }

        /* ===== BATAFSIL BUTTON ===== */
        .btn-detail {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 16px; border-radius: 8px;
            font-size: 12px; font-weight: 600;
            border: 1.5px solid #e2e8f0; background: white;
            color: #475569; cursor: pointer; transition: all 0.2s ease;
        }
        .btn-detail:hover {
            background: #6366f1; border-color: #6366f1; color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transform: translateY(-1px);
        }
        .dark .btn-detail { border-color: #475569; background: #334155; color: #94a3b8; }
        .dark .btn-detail:hover { background: #6366f1; border-color: #6366f1; color: white; }
        .btn-detail svg { transition: transform 0.2s; }
        .btn-detail:hover svg { transform: translateX(2px); }

        /* ===== MT BUTTON ===== */
        .btn-mt {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 6px 12px; border-radius: 8px;
            font-size: 11px; font-weight: 600;
            border: 1.5px solid; cursor: pointer; transition: all 0.2s ease;
        }
        .btn-mt:hover { transform: translateY(-1px); }
        .btn-mt-amber { background: #fffbeb; color: #92400e; border-color: #fcd34d; }
        .btn-mt-amber:hover { background: #fef3c7; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); }
        .btn-mt-red { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
        .btn-mt-red:hover { background: #fee2e2; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-mt-blue { background: #eff6ff; color: #1e40af; border-color: #93c5fd; }
        .btn-mt-blue:hover { background: #dbeafe; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); }
        .btn-mt-green { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
        .btn-mt-green:hover { background: #d1fae5; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-mt-orange { background: #fff7ed; color: #9a3412; border-color: #fdba74; }
        .btn-mt-orange:hover { background: #ffedd5; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2); }
        .btn-mt-gray { background: #f8fafc; color: #64748b; border-color: #cbd5e1; }
        .btn-mt-gray:hover { background: #f1f5f9; }
        .btn-mt-danger { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
        .btn-mt-yellow { background: #fefce8; color: #854d0e; border-color: #facc15; }
        .btn-mt-yellow:hover { background: #fef9c3; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.2); }
        @keyframes mt-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .btn-mt-pulse { animation: mt-pulse 2s ease-in-out infinite; }

        .empty-page {
            text-align: center; padding: 60px 20px; color: #94a3b8;
            font-size: 14px; background: white; border-radius: 14px;
            border: 1px solid #f1f5f9;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000;
            display: flex !important; align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-overlay[style*="display: none"] { display: none !important; }
        .modal-box {
            background: #fff; border-radius: 16px; width: 100%; max-width: 1100px;
            max-height: 90vh; box-shadow: 0 25px 60px rgba(0,0,0,0.15);
            overflow: hidden; display: flex; flex-direction: column;
            margin: auto;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; flex-shrink: 0;
        }
        .modal-header h3 { font-size: 15px; font-weight: 700; color: #fff; margin: 0; }
        .modal-close {
            width: 32px; height: 32px; border: none;
            background: rgba(255,255,255,0.15); color: #fff;
            border-radius: 8px; font-size: 20px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.3); }

        .modal-scroll { overflow-y: auto; flex: 1; }

        .modal-info {
            padding: 14px 20px; background: #fafbff;
            border-bottom: 1px solid #eef2ff;
        }
        .info-grid { display: flex; flex-wrap: wrap; gap: 20px; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .info-value { font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        /* Modal tabs */
        .modal-tabs {
            display: flex; gap: 0; padding: 0 20px;
            background: #fafbff; border-bottom: 2px solid #eef2ff;
            position: sticky; top: 0; z-index: 2;
        }
        .modal-tab-btn {
            padding: 10px 20px; font-size: 13px; font-weight: 600;
            color: #64748b; background: transparent; border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px; cursor: pointer; transition: all 0.15s; white-space: nowrap;
        }
        .modal-tab-btn.active { color: #6366f1; border-bottom-color: #6366f1; background: #fff; }
        .modal-tab-btn:hover:not(.active) { color: #475569; }

        /* ===== JOURNAL TABLE (exact copy from admin journal) ===== */
        .j-table-wrap { overflow-x: auto; padding: 16px 20px; }
        .journal-table {
            border: 1px solid #cbd5e1;
            width: auto;
            table-layout: auto;
            border-collapse: collapse;
            font-size: 12px;
        }
        .journal-table th,
        .journal-table td {
            border: 1px solid #94a3b8 !important;
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
        }
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .journal-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .journal-table tbody td:hover {
            background-color: #e0f2fe !important;
        }
        .journal-table thead th {
            background-color: #f3f4f6;
        }
        .journal-table .date-header-cell {
            padding: 0 !important;
            vertical-align: middle;
        }
        .journal-table .date-header-cell .date-text-wrapper {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            height: 90px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            line-height: 1.2;
            margin: 0 auto;
            padding: 2px 0;
        }
        .journal-table .joriy-header {
            height: 44px;
            font-size: 13px;
            font-weight: 700 !important;
            letter-spacing: 0.02em;
        }
        .date-separator {
            border-left: 2px solid #94a3b8 !important;
        }
        .date-end {
            border-right: 2px solid #94a3b8 !important;
        }
        .grade-fail {
            color: #dc2626 !important;
        }

        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 13px; }

        @media (max-width: 640px) {
            .summary-bar { gap: 10px; padding: 12px 14px; }
            .s-sep { display: none; }
            .modal-box { border-radius: 10px; max-height: 95vh; }
        }
    </style>

    <div class="py-6" x-data="subjectsApp()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div style="margin-bottom: 16px; padding: 10px 16px; background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; border-radius: 8px; font-size: 13px;">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div style="margin-bottom: 16px; padding: 10px 16px; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; font-size: 13px;">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div style="margin-bottom: 16px; padding: 10px 16px; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; font-size: 13px;">
                    <ul style="list-style: disc; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary Bar --}}
            <div class="summary-bar">
                <div class="s-item">
                    <span class="s-dot" style="background: #6366f1;"></span>
                    <span class="s-text"><b>{{ $subjectCount }}</b> ta fan</span>
                </div>
                <span class="s-sep"></span>
                <div class="s-item">
                    <span class="s-dot" style="background: #8b5cf6;"></span>
                    <span class="s-text"><b>{{ $totalCredit }}</b> kredit</span>
                </div>
                <span class="s-sep"></span>
                <div class="s-item">
                    <span class="s-dot" style="background: #10b981;"></span>
                    <span class="s-text">O'rt JN: <b>{{ $avgJn }}%</b></span>
                </div>
                <span class="s-sep"></span>
                <div class="s-item">
                    <span class="s-dot" style="background: {{ $totalAbsent > 0 ? '#f59e0b' : '#10b981' }};"></span>
                    <span class="s-text">Qoldirgan: <b>{{ $totalAbsent }}</b> / {{ $totalAuditorium }} soat</span>
                </div>
            </div>

            {{-- Cards Grid --}}
            @if($subjects->isNotEmpty())
                @php
                    $cardThemes = [
                        ['bg' => '#f5f3ff', 'border' => '#c4b5fd', 'accent' => 'linear-gradient(135deg, #7c3aed, #a78bfa)', 'creditBg' => '#ede9fe', 'creditColor' => '#6d28d9', 'hoverBorder' => '#a78bfa'],
                        ['bg' => '#ecfdf5', 'border' => '#6ee7b7', 'accent' => 'linear-gradient(135deg, #059669, #34d399)', 'creditBg' => '#d1fae5', 'creditColor' => '#047857', 'hoverBorder' => '#6ee7b7'],
                        ['bg' => '#fff7ed', 'border' => '#fdba74', 'accent' => 'linear-gradient(135deg, #ea580c, #fb923c)', 'creditBg' => '#ffedd5', 'creditColor' => '#c2410c', 'hoverBorder' => '#fdba74'],
                        ['bg' => '#fdf2f8', 'border' => '#f9a8d4', 'accent' => 'linear-gradient(135deg, #db2777, #f472b6)', 'creditBg' => '#fce7f3', 'creditColor' => '#be185d', 'hoverBorder' => '#f9a8d4'],
                        ['bg' => '#ecfeff', 'border' => '#67e8f9', 'accent' => 'linear-gradient(135deg, #0891b2, #22d3ee)', 'creditBg' => '#cffafe', 'creditColor' => '#0e7490', 'hoverBorder' => '#67e8f9'],
                        ['bg' => '#eef2ff', 'border' => '#a5b4fc', 'accent' => 'linear-gradient(135deg, #4f46e5, #818cf8)', 'creditBg' => '#e0e7ff', 'creditColor' => '#4338ca', 'hoverBorder' => '#a5b4fc'],
                        ['bg' => '#fefce8', 'border' => '#fde047', 'accent' => 'linear-gradient(135deg, #ca8a04, #facc15)', 'creditBg' => '#fef9c3', 'creditColor' => '#a16207', 'hoverBorder' => '#fde047'],
                        ['bg' => '#fef2f2', 'border' => '#fca5a5', 'accent' => 'linear-gradient(135deg, #dc2626, #f87171)', 'creditBg' => '#fee2e2', 'creditColor' => '#b91c1c', 'hoverBorder' => '#fca5a5'],
                        ['bg' => '#f0fdf4', 'border' => '#86efac', 'accent' => 'linear-gradient(135deg, #16a34a, #4ade80)', 'creditBg' => '#dcfce7', 'creditColor' => '#15803d', 'hoverBorder' => '#86efac'],
                        ['bg' => '#faf5ff', 'border' => '#d8b4fe', 'accent' => 'linear-gradient(135deg, #9333ea, #c084fc)', 'creditBg' => '#f3e8ff', 'creditColor' => '#7e22ce', 'hoverBorder' => '#d8b4fe'],
                    ];
                @endphp
                <div class="cards-grid">
                    @foreach($subjects as $index => $subject)
                        @php
                            $theme = $cardThemes[$index % count($cardThemes)];

                            $jn = $subject['jn_average'];

                            $dp = $subject['dav_percent'];
                            $davColor = $dp >= 25 ? '#ef4444' : ($dp >= 15 ? '#f59e0b' : '#6366f1');
                            $davWidth = min($dp * 2, 100);

                            $gradeClass = function($v, $isNull = false) use ($minimumLimit) {
                                $min = $minimumLimit ?? 60;
                                if ($isNull || $v === null) return 'g-none';
                                if ($v >= 90) return 'g-excellent';
                                if ($v >= 70) return 'g-good';
                                if ($v >= $min) return 'g-ok';
                                if ($v > 0) return 'g-fail';
                                return 'g-none';
                            };
                        @endphp

                        <div class="subject-card" style="background: {{ $theme['bg'] }}; --card-hover-border: {{ $theme['hoverBorder'] }};">
                            <div class="card-accent" style="background: {{ $theme['accent'] }};"></div>
                            <div class="card-body">
                                <div class="card-header">
                                    <h3 class="card-title">{{ $subject['name'] }}</h3>
                                    <span class="card-credit" style="background: {{ $theme['creditBg'] }}; color: {{ $theme['creditColor'] }};">{{ $subject['credit'] }} kr</span>
                                </div>

                                <div class="grades-grid">
                                    <div class="g-item">
                                        <div class="g-label">JN</div>
                                        <div class="g-value {{ $gradeClass($jn) }}">{{ $jn > 0 ? $jn : '-' }}</div>
                                    </div>
                                    <div class="g-item">
                                        @php $v = $subject['mt_average']; @endphp
                                        <div class="g-label">MT</div>
                                        <div class="g-value {{ $gradeClass($v) }}">{{ $v > 0 ? $v : '-' }}</div>
                                    </div>
                                    <div class="g-item">
                                        @php $v = $subject['on']; @endphp
                                        <div class="g-label">ON</div>
                                        <div class="g-value {{ $gradeClass($v, $v === null) }}">{{ $v !== null ? $v : '-' }}</div>
                                    </div>
                                    <div class="g-item">
                                        <div class="g-label">OSKI</div>
                                        <div class="g-value g-none">-</div>
                                    </div>
                                    <div class="g-item">
                                        <div class="g-label">Test</div>
                                        <div class="g-value g-none">-</div>
                                    </div>
                                    <div class="g-item">
                                        <div class="g-label">YN</div>
                                        <div class="g-value g-none">-</div>
                                    </div>
                                </div>

                                <div class="dav-section">
                                    <span class="dav-label">Davomat</span>
                                    <div class="dav-track">
                                        <div class="dav-fill" style="width: {{ $davWidth }}%; background: {{ $davColor }};"></div>
                                    </div>
                                    <span class="dav-value" style="color: {{ $davColor }};" title="Qoldirgan: {{ $subject['absent_hours'] }} / {{ $subject['auditorium_hours'] }} soat">{{ number_format($dp, 2) }}%</span>
                                </div>

                                <div class="card-footer">
                                    <span class="card-hours"><b>{{ $subject['absent_hours'] }}</b> / {{ $subject['auditorium_hours'] }} soat</span>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        {{-- MT yuklash button --}}
                                        @if($subject['mt'])
                                            @php $mt = $subject['mt']; @endphp
                                            @if($mt['grade_locked'])
                                                {{-- Baho >= minimumLimit: Baholangan --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-green">
                                                    Baholangan <b>{{ $mt['grade'] }}</b>
                                                </button>
                                            @elseif($mt['can_resubmit'])
                                                {{-- Baho < minimumLimit, deadline ichida, urinish bor: Qayta yuklash --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-orange btn-mt-pulse">
                                                    Qayta yuklash
                                                </button>
                                            @elseif($mt['submission'] && $mt['grade'] !== null && $mt['grade'] < ($minimumLimit ?? 60) && $mt['remaining_attempts'] <= 0)
                                                {{-- Baho < minimumLimit, urinish limiti tugagan --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-danger">
                                                    Limit tugagan <b>{{ $mt['grade'] }}</b>
                                                </button>
                                            @elseif($mt['submission'] && $mt['grade'] !== null && $mt['grade'] < ($minimumLimit ?? 60))
                                                {{-- Baho < minimumLimit, deadline o'tgan --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-danger">
                                                    Muddat tugagan <b>{{ $mt['grade'] }}</b>
                                                </button>
                                            @elseif($mt['submission'] && $mt['is_viewed'])
                                                {{-- Yuklangan + o'qituvchi ko'rgan: Tekshirilmoqda --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-yellow">
                                                    Tekshirilmoqda
                                                </button>
                                            @elseif($mt['submission'])
                                                {{-- Yuklangan, o'qituvchi hali ko'rmagan: Yuklangan --}}
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-blue">
                                                    Yuklangan
                                                </button>
                                            @elseif($mt['is_overdue'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-gray">
                                                    Muddat tugagan
                                                </button>
                                            @elseif($mt['is_warning'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-red btn-mt-pulse">
                                                    MT yuklash
                                                </button>
                                            @else
                                                <button onclick="toggleMtPopover(event, {{ $index }})" class="btn-mt btn-mt-amber">
                                                    MT yuklash
                                                </button>
                                            @endif
                                        @endif

                                        <button class="btn-detail" @click="openModal({{ $index }})">
                                            Batafsil
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-page">
                    <svg style="width: 48px; height: 48px; color: #cbd5e1; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Bu semestrda fanlar topilmadi
                </div>
            @endif
        </div>

        {{-- ===== MODAL ===== --}}
        <div x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="modal-overlay"
             @click.self="closeModal()"
             @keydown.escape.window="closeModal()"
             style="display: none;">
            <div class="modal-box" @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="modal-header">
                    <h3 x-text="modalTitle"></h3>
                    <button @click="closeModal()" class="modal-close">&times;</button>
                </div>

                <div class="modal-scroll">
                    @foreach($subjects as $index => $subject)
                        <div x-show="activeSubject === {{ $index }}">
                            <div class="modal-info">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Kredit</span>
                                        <span class="info-value">{{ $subject['credit'] }}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">JN %</span>
                                        <span class="info-value" style="color: {{ $subject['jn_average'] >= 90 ? '#059669' : ($subject['jn_average'] >= 70 ? '#2563eb' : ($subject['jn_average'] >= ($minimumLimit ?? 60) ? '#d97706' : ($subject['jn_average'] > 0 ? '#dc2626' : '#94a3b8'))) }}">
                                            {{ $subject['jn_average'] > 0 ? $subject['jn_average'] : '-' }}
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">MT %</span>
                                        <span class="info-value" style="color: {{ $subject['mt_average'] >= 90 ? '#059669' : ($subject['mt_average'] >= 70 ? '#2563eb' : ($subject['mt_average'] >= ($minimumLimit ?? 60) ? '#d97706' : ($subject['mt_average'] > 0 ? '#dc2626' : '#94a3b8'))) }}">
                                            {{ $subject['mt_average'] > 0 ? $subject['mt_average'] : '-' }}
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Dav %</span>
                                        <span class="info-value" style="color: {{ $subject['dav_percent'] >= 25 ? '#dc2626' : ($subject['dav_percent'] >= 15 ? '#d97706' : '#6366f1') }}">
                                            {{ number_format($subject['dav_percent'], 2) }}%
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Qoldirgan</span>
                                        <span class="info-value">{{ $subject['absent_hours'] }} <small style="color: #94a3b8; font-weight: 400;">/ {{ $subject['auditorium_hours'] }} soat</small></span>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-tabs">
                                <button class="modal-tab-btn" :class="{ 'active': activeTab === 'amaliy' }" @click="activeTab = 'amaliy'">
                                    Amaliy (JN) <span style="opacity: 0.5; font-size: 11px;">({{ count($subject['jb_daily_data']) }})</span>
                                </button>
                                <button class="modal-tab-btn" :class="{ 'active': activeTab === 'maruza' }" @click="activeTab = 'maruza'">
                                    Ma'ruza <span style="opacity: 0.5; font-size: 11px;">({{ count($subject['lecture_by_date']) }})</span>
                                </button>
                                <button class="modal-tab-btn" :class="{ 'active': activeTab === 'mt' }" @click="activeTab = 'mt'">
                                    Mustaqil ta'lim <span style="opacity: 0.5; font-size: 11px;">({{ count($subject['mt_daily_data']) }})</span>
                                </button>
                            </div>

                            {{-- AMALIY TAB --}}
                            <div x-show="activeTab === 'amaliy'" x-transition.opacity.duration.150ms>
                                @php $jbData = $subject['jb_daily_data']; $jbCount = count($jbData); @endphp
                                @if($jbCount > 0)
                                    <div class="j-table-wrap">
                                        <table class="journal-table border-collapse text-xs">
                                            <thead>
                                                <tr>
                                                    <th colspan="{{ $jbCount }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Joriy nazorat (kunlik o'rtacha)</th>
                                                    <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">JN %</th>
                                                </tr>
                                                <tr>
                                                    @foreach($jbData as $idx => $day)
                                                        <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $jbCount - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px;">
                                                            <div class="date-text-wrapper">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($jbData as $idx => $day)
                                                        <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $jbCount - 1 ? 'date-end' : '' }}">
                                                            @if($day['has_grades'])
                                                                @php $v = $day['average']; @endphp
                                                                <span class="font-medium text-gray-900">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="text-red-600 font-medium">NB</span>
                                                            @else
                                                                <span class="text-gray-300">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="px-1 py-1 text-center">
                                                        @php $v = $subject['jn_average']; @endphp
                                                        <span class="font-bold {{ $v < ($minimumLimit ?? 60) ? 'grade-fail' : 'text-blue-600' }}">{{ $v > 0 ? $v : '-' }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="empty-state">Amaliy (JN) baholar mavjud emas</div>
                                @endif
                            </div>

                            {{-- MA'RUZA TAB --}}
                            <div x-show="activeTab === 'maruza'" x-transition.opacity.duration.150ms>
                                @php $lecData = $subject['lecture_by_date']; $lecCount = count($lecData); @endphp
                                @if($lecCount > 0)
                                    <div class="j-table-wrap">
                                        <table class="journal-table border-collapse text-xs">
                                            <thead>
                                                <tr>
                                                    @foreach($lecData as $idx => $day)
                                                        <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $lecCount - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px;">
                                                            <div class="date-text-wrapper">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($lecData as $idx => $day)
                                                        <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $lecCount - 1 ? 'date-end' : '' }}">
                                                            @if($day['status'] === 'NB')
                                                                <span class="text-red-600 font-medium">NB</span>
                                                            @else
                                                                <span class="text-green-600 font-bold">+</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="empty-state">Ma'ruza davomati mavjud emas</div>
                                @endif
                            </div>

                            {{-- MT TAB --}}
                            <div x-show="activeTab === 'mt'" x-transition.opacity.duration.150ms>
                                @php $mtData = $subject['mt_daily_data']; $mtCount = count($mtData); @endphp
                                @if($mtCount > 0)
                                    <div class="j-table-wrap">
                                        <table class="journal-table border-collapse text-xs">
                                            <thead>
                                                <tr>
                                                    <th colspan="{{ $mtCount }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Mustaqil ta'lim (kunlik o'rtacha)</th>
                                                    <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">MT %</th>
                                                </tr>
                                                <tr>
                                                    @foreach($mtData as $idx => $day)
                                                        <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $mtCount - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px;">
                                                            <div class="date-text-wrapper">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($mtData as $idx => $day)
                                                        <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === $mtCount - 1 ? 'date-end' : '' }}">
                                                            @if($day['has_grades'])
                                                                @php $v = $day['average']; @endphp
                                                                <span class="font-medium text-gray-900">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="text-red-600 font-medium">NB</span>
                                                            @else
                                                                <span class="text-gray-300">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="px-1 py-1 text-center">
                                                        @php $v = $subject['mt_average']; @endphp
                                                        <span class="font-bold {{ $v < ($minimumLimit ?? 60) ? 'grade-fail' : 'text-blue-600' }}">{{ $v > 0 ? $v : '-' }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="empty-state">Mustaqil ta'lim baholar mavjud emas</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Compression overlay --}}
    <div id="compress-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:24px 32px; text-align:center; max-width:360px;">
            <div style="margin-bottom:12px;">
                <svg style="display:inline; animation: spin 1s linear infinite; height:32px; width:32px; color:#3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            <p id="compress-status" style="font-size:14px; color:#1e40af; font-weight:600;">Fayl siqilmoqda...</p>
            <p id="compress-detail" style="font-size:12px; color:#6b7280; margin-top:4px;"></p>
        </div>
    </div>
    <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>

    {{-- MT Popovers (outside Alpine scope, fixed position) --}}
    @foreach($subjects as $index => $subject)
        @if($subject['mt'])
            @php $mt = $subject['mt']; @endphp
            <div id="mt-popover-{{ $index }}" class="hidden fixed z-[9999] bg-white rounded-xl shadow-2xl border border-gray-200" style="max-height: 80vh; overflow-y: auto; width: 320px;" onclick="event.stopPropagation()">
                <div style="padding: 16px;">
                    {{-- Header --}}
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0;">Mustaqil ta'lim</h3>
                        <button onclick="closeAllMtPopovers()" style="background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 18px; line-height: 1;">&times;</button>
                    </div>

                    {{-- Deadline info --}}
                    <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: {{ $mt['is_overdue'] ? '#fef2f2' : ($mt['is_warning'] ? '#fff7ed' : '#eff6ff') }};">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 11px; color: #64748b; font-weight: 500;">Muddat:</span>
                            <span style="font-size: 11px; font-weight: 700; color: {{ $mt['is_overdue'] ? '#dc2626' : ($mt['is_warning'] ? '#ea580c' : '#1e293b') }};">
                                {{ $mt['deadline'] }} ({{ $mt['deadline_time'] }} gacha)
                            </span>
                        </div>
                        @if($mt['is_overdue'])
                            <div style="font-size: 11px; color: #dc2626; font-weight: 600; text-align: right; margin-top: 4px;">Muddat tugagan</div>
                        @elseif($mt['is_warning'])
                            <div style="font-size: 11px; color: #ea580c; font-weight: 600; text-align: right; margin-top: 4px;">
                                Qolgan: {{ $mt['days_remaining'] ?? 0 }} kun — Shoshiling!
                            </div>
                        @elseif($mt['days_remaining'] !== null)
                            <div style="font-size: 11px; color: #2563eb; font-weight: 500; text-align: right; margin-top: 4px;">
                                Qolgan: {{ $mt['days_remaining'] }} kun
                            </div>
                        @endif
                    </div>

                    {{-- Current submission info --}}
                    @if($mt['submission'])
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #ecfdf5;">
                            <div style="font-size: 11px; font-weight: 600; color: #065f46; margin-bottom: 4px;">Yuklangan fayl</div>
                            <a href="{{ route('student.independents.download', $mt['submission']->id) }}" target="_blank"
                               style="font-size: 11px; color: #2563eb; word-break: break-all;">
                                {{ $mt['submission']->file_original_name }}
                            </a>
                            <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">
                                {{ $mt['submission']->submitted_at ? $mt['submission']->submitted_at->format('d.m.Y H:i') : '' }}
                            </div>
                        </div>
                    @endif

                    {{-- Grade info --}}
                    @if($mt['grade'] !== null)
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: {{ $mt['grade'] >= ($minimumLimit ?? 60) ? '#ecfdf5' : '#fef2f2' }};">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 11px; color: #64748b; font-weight: 500;">Baho:</span>
                                <span style="font-size: 14px; font-weight: 700; color: {{ $mt['grade'] >= ($minimumLimit ?? 60) ? '#059669' : '#dc2626' }};">
                                    {{ $mt['grade'] }}
                                    @if($mt['grade_locked'])
                                        <span style="font-size: 10px; font-weight: 400; color: #059669;">(Qabul qilindi)</span>
                                    @else
                                        <span style="font-size: 10px; font-weight: 400; color: #dc2626;">(Qoniqarsiz)</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Grade history --}}
                    @if($mt['grade_history']->count() > 0)
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #f8fafc;">
                            <span style="font-size: 11px; color: #64748b; font-weight: 500;">Oldingi baholar:</span>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px;">
                                @foreach($mt['grade_history'] as $history)
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 12px; background: {{ $history->grade >= ($minimumLimit ?? 60) ? '#ecfdf5' : '#fef2f2' }}; color: {{ $history->grade >= ($minimumLimit ?? 60) ? '#059669' : '#dc2626' }};">
                                        {{ $history->grade }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- File upload section --}}
                    @if($mt['grade_locked'])
                        {{-- Locked: no upload --}}
                    @elseif($mt['can_resubmit'])
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #fff7ed; border: 1px solid #fdba74;">
                            <div style="font-size: 11px; color: #9a3412; font-weight: 600; margin-bottom: 8px;">
                                Qayta yuklash ({{ $mt['remaining_attempts'] }} marta qoldi)
                            </div>
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                                @csrf
                                <input type="file" name="file" required accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       style="width: 100%; font-size: 11px; margin-bottom: 8px;" class="mt-file-input">
                                <button type="submit" style="width: 100%; padding: 6px 12px; background: #ea580c; color: white; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                    Qayta yuklash
                                </button>
                            </form>
                            <p style="font-size: 10px; color: #94a3b8; margin-top: 4px;">Max 10MB (zip, doc, ppt, pdf) — katta fayllar avtomatik siqiladi</p>
                        </div>
                    @elseif($mt['submission'] && $mt['grade'] === null && !$mt['is_overdue'])
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #eff6ff; border: 1px solid #93c5fd;">
                            <div style="font-size: 11px; color: #1e40af; font-weight: 600; margin-bottom: 8px;">Faylni yangilash</div>
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                                @csrf
                                <input type="file" name="file" required accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       style="width: 100%; font-size: 11px; margin-bottom: 8px;" class="mt-file-input">
                                <button type="submit" style="width: 100%; padding: 6px 12px; background: #2563eb; color: white; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                    Yangilash
                                </button>
                            </form>
                            <p style="font-size: 10px; color: #94a3b8; margin-top: 4px;">Max 10MB (zip, doc, ppt, pdf) — katta fayllar avtomatik siqiladi</p>
                        </div>
                    @elseif(!$mt['submission'] && !$mt['is_overdue'])
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #fffbeb; border: 1px solid #fcd34d;">
                            <div style="font-size: 11px; color: #92400e; font-weight: 600; margin-bottom: 8px;">Fayl yuklash</div>
                            @if($mt['file_path'])
                                <div style="margin-bottom: 8px;">
                                    <a href="{{ asset('storage/' . $mt['file_path']) }}" target="_blank"
                                       style="font-size: 11px; color: #2563eb;">
                                        Topshiriq faylini ko'rish
                                    </a>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                                @csrf
                                <input type="file" name="file" required accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       style="width: 100%; font-size: 11px; margin-bottom: 8px;" class="mt-file-input">
                                <button type="submit" style="width: 100%; padding: 6px 12px; background: #d97706; color: white; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                    Yuklash
                                </button>
                            </form>
                            <p style="font-size: 10px; color: #94a3b8; margin-top: 4px;">Max 10MB (zip, doc, ppt, pdf) — katta fayllar avtomatik siqiladi</p>
                        </div>
                    @elseif($mt['is_overdue'] && !$mt['submission'])
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #f8fafc;">
                            <span style="font-size: 11px; color: #dc2626; font-weight: 500;">Muddat tugagan — fayl yuklanmagan</span>
                        </div>
                    @elseif($mt['grade'] !== null && $mt['grade'] < ($minimumLimit ?? 60) && $mt['remaining_attempts'] <= 0)
                        <div style="margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; background: #f8fafc;">
                            <span style="font-size: 11px; color: #dc2626; font-weight: 500;">MT topshirig'ini qayta yuklash imkoniyati tugagan</span>
                        </div>
                    @endif

                    {{-- Task file download --}}
                    @if($mt['file_path'] && ($mt['submission'] || $mt['is_overdue']))
                        <div style="margin-bottom: 12px;">
                            <a href="{{ asset('storage/' . $mt['file_path']) }}" target="_blank"
                               style="font-size: 11px; color: #2563eb;">
                                Topshiriq faylini ko'rish
                            </a>
                        </div>
                    @endif

                    {{-- Reminder text --}}
                    <div style="padding: 8px 10px; border-radius: 8px; background: #fef9c3; border: 1px solid #facc15;">
                        <div style="display: flex; align-items: flex-start; gap: 6px;">
                            <span style="font-size: 14px; line-height: 1; flex-shrink: 0;">&#9888;</span>
                            <p style="font-size: 10px; color: #854d0e; line-height: 1.5; margin: 0;">
                                MT topshiriq muddati oxirgi darsdan bitta oldingi darsda soat 17.00 gacha yuklanishi shart.
                                Muddatida yuklanmagan MT topshiriqlari ko'rib chiqilmaydi va baholanmaydi.
                                MT dan qoniqarsiz baho olgan yoki baholanmagan talabalar fandan akademik qarzdor hisoblanadi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <script>
        // Alpine.js app for modal
        function subjectsApp() {
            return {
                modalOpen: false,
                activeSubject: -1,
                activeTab: 'amaliy',
                subjectNames: @json($subjects->pluck('name')->values()),
                get modalTitle() {
                    return this.activeSubject >= 0 ? this.subjectNames[this.activeSubject] : '';
                },
                openModal(index) {
                    this.activeSubject = index;
                    this.activeTab = 'amaliy';
                    this.modalOpen = true;
                    document.body.style.overflow = 'hidden';
                },
                closeModal() {
                    this.modalOpen = false;
                    document.body.style.overflow = '';
                }
            }
        }

        // MT Popover functions
        var activeMtPopoverIndex = null;

        function toggleMtPopover(event, index) {
            event.stopPropagation();
            var popover = document.getElementById('mt-popover-' + index);
            var isHidden = popover.classList.contains('hidden');

            closeAllMtPopovers();

            if (isHidden) {
                var rect = event.currentTarget.getBoundingClientRect();
                var popoverWidth = 320;
                var viewportWidth = window.innerWidth;
                var viewportHeight = window.innerHeight;

                var top = rect.bottom + 6;
                var left = rect.right - popoverWidth;

                if (left < 8) left = 8;
                if (left + popoverWidth > viewportWidth - 8) left = viewportWidth - popoverWidth - 8;

                if (top + 300 > viewportHeight) {
                    top = Math.max(8, rect.top - 300 - 6);
                }

                popover.style.top = top + 'px';
                popover.style.left = left + 'px';
                popover.classList.remove('hidden');
                activeMtPopoverIndex = index;
            }
        }

        function closeAllMtPopovers() {
            document.querySelectorAll('[id^="mt-popover-"]').forEach(function(el) {
                el.classList.add('hidden');
            });
            activeMtPopoverIndex = null;
        }

        // Close popover on outside click (instead of overlay)
        document.addEventListener('click', function(e) {
            if (activeMtPopoverIndex === null) return;
            var popover = document.getElementById('mt-popover-' + activeMtPopoverIndex);
            if (popover && !popover.contains(e.target) && !e.target.closest('.btn-mt')) {
                closeAllMtPopovers();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAllMtPopovers();
        });

        // MT file upload auto-compression
        (function() {
            var COMPRESS_THRESHOLD = 2 * 1024 * 1024; // 2MB dan katta fayllarni siqish
            var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB maksimal hajm

            document.querySelectorAll('.mt-upload-form').forEach(function(form) {
                var fileInput = form.querySelector('.mt-file-input');
                if (!fileInput) return;

                form.addEventListener('submit', function(e) {
                    var file = fileInput.files[0];
                    if (!file || file.size <= COMPRESS_THRESHOLD) return;

                    var ext = file.name.split('.').pop().toLowerCase();
                    if (ext === 'zip') {
                        if (file.size > MAX_FILE_SIZE) {
                            e.preventDefault();
                            alert('Fayl hajmi ' + (file.size / 1024 / 1024).toFixed(1) + 'MB. Maksimal hajm 10MB.');
                            fileInput.value = '';
                        }
                        return;
                    }

                    if (typeof JSZip === 'undefined') {
                        // JSZip yuklanmagan — oddiy yuklash
                        return;
                    }

                    e.preventDefault();

                    var overlay = document.getElementById('compress-overlay');
                    var statusEl = document.getElementById('compress-status');
                    var detailEl = document.getElementById('compress-detail');
                    overlay.style.display = 'flex';
                    statusEl.textContent = 'Fayl siqilmoqda...';
                    var originalSizeMB = (file.size / 1024 / 1024).toFixed(1);
                    detailEl.textContent = 'Asl hajm: ' + originalSizeMB + 'MB';

                    var zip = new JSZip();
                    zip.file(file.name, file);

                    zip.generateAsync({
                        type: 'blob',
                        compression: 'DEFLATE',
                        compressionOptions: { level: 6 }
                    }, function(metadata) {
                        detailEl.textContent = 'Siqilmoqda... ' + metadata.percent.toFixed(0) + '%';
                    }).then(function(blob) {
                        var compressedSizeMB = (blob.size / 1024 / 1024).toFixed(1);

                        if (blob.size > MAX_FILE_SIZE) {
                            overlay.style.display = 'none';
                            alert('Siqilgandan keyin ham fayl hajmi ' + compressedSizeMB + 'MB (' + originalSizeMB + 'MB dan). Maksimal hajm 10MB. Iltimos, faylni kichikroq qiling.');
                            fileInput.value = '';
                            return;
                        }

                        statusEl.textContent = 'Yuklanmoqda...';
                        var savedPercent = ((1 - blob.size / file.size) * 100).toFixed(0);
                        detailEl.textContent = originalSizeMB + 'MB → ' + compressedSizeMB + 'MB (' + savedPercent + '% siqildi)';

                        var zipFileName = file.name.replace(/\.[^.]+$/, '') + '.zip';
                        var zipFile = new File([blob], zipFileName, { type: 'application/zip' });

                        var dataTransfer = new DataTransfer();
                        dataTransfer.items.add(zipFile);
                        fileInput.files = dataTransfer.files;

                        form.submit();
                    }).catch(function(err) {
                        overlay.style.display = 'none';
                        alert('Faylni siqishda xatolik: ' + err.message);
                        fileInput.value = '';
                    });
                });
            });
        })();
    </script>
</x-student-app-layout>
