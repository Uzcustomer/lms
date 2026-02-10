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
            border-color: #e0e7ff;
        }
        .dark .subject-card { background: #1e293b; border-color: #334155; }
        .dark .subject-card:hover { border-color: #4f46e5; box-shadow: 0 12px 28px rgba(0,0,0,0.25); }

        .card-accent { height: 4px; }
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

        .empty-page {
            text-align: center; padding: 60px 20px; color: #94a3b8;
            font-size: 14px; background: white; border-radius: 14px;
            border: 1px solid #f1f5f9;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-box {
            background: #fff; border-radius: 16px; width: 100%; max-width: 1100px;
            max-height: 90vh; box-shadow: 0 25px 60px rgba(0,0,0,0.15);
            overflow: hidden; display: flex; flex-direction: column;
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

        /* ===== HORIZONTAL TABLE ===== */
        .h-table-wrap { overflow-x: auto; padding: 16px 20px; }
        .h-table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
        .h-table thead { position: sticky; top: 0; z-index: 1; }
        .h-table th {
            background: #fafbff; padding: 6px 4px; text-align: center;
            font-weight: 600; font-size: 10px; color: #6366f1;
            border-bottom: 2px solid #eef2ff;
            min-width: 48px; width: 48px; height: 80px; vertical-align: bottom;
        }
        .h-table th .date-text {
            writing-mode: vertical-rl; text-orientation: mixed;
            transform: rotate(180deg); white-space: nowrap;
            display: inline-block; font-size: 10.5px;
        }
        .h-table td {
            padding: 10px 4px; text-align: center;
            border-bottom: 1px solid #f8fafc; font-weight: 600; font-size: 13px;
        }
        .h-table tbody tr:hover td { background: #fafbff; }
        .h-table .avg-col {
            background: #f0fdf4 !important;
            font-weight: 800; border-left: 2px solid #d1fae5; min-width: 70px;
        }
        .h-table .avg-col.th-avg {
            background: #dcfce7 !important;
            color: #059669; vertical-align: middle; height: auto;
        }
        .h-cell {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            font-weight: 700; font-size: 12px;
        }
        .hc-excellent { background: #ecfdf5; color: #059669; }
        .hc-good { background: #eff6ff; color: #2563eb; }
        .hc-ok { background: #fffbeb; color: #d97706; }
        .hc-fail { background: #fef2f2; color: #dc2626; }
        .hc-nb { background: #fef2f2; color: #dc2626; font-size: 10px; }
        .hc-qb { background: #eff6ff; color: #2563eb; font-size: 10px; }
        .hc-empty { color: #cbd5e1; }

        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 13px; }

        @media (max-width: 640px) {
            .summary-bar { gap: 10px; padding: 12px 14px; }
            .s-sep { display: none; }
            .modal-box { border-radius: 10px; max-height: 95vh; }
        }
    </style>

    <div class="py-6" x-data="subjectsApp()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

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
                <div class="cards-grid">
                    @foreach($subjects as $index => $subject)
                        @php
                            $jn = $subject['jn_average'];
                            $accentBg = $jn >= 90 ? 'linear-gradient(90deg, #10b981, #34d399)'
                                : ($jn >= 70 ? 'linear-gradient(90deg, #3b82f6, #60a5fa)'
                                : ($jn >= 60 ? 'linear-gradient(90deg, #f59e0b, #fbbf24)'
                                : ($jn > 0 ? 'linear-gradient(90deg, #ef4444, #f87171)'
                                : 'linear-gradient(90deg, #e2e8f0, #cbd5e1)')));

                            $dp = $subject['dav_percent'];
                            $davColor = $dp >= 25 ? '#ef4444' : ($dp >= 15 ? '#f59e0b' : '#6366f1');
                            $davWidth = min($dp * 2, 100);

                            $gradeClass = function($v, $isNull = false) {
                                if ($isNull || $v === null) return 'g-none';
                                if ($v >= 90) return 'g-excellent';
                                if ($v >= 70) return 'g-good';
                                if ($v >= 60) return 'g-ok';
                                if ($v > 0) return 'g-fail';
                                return 'g-none';
                            };
                        @endphp

                        <div class="subject-card">
                            <div class="card-accent" style="background: {{ $accentBg }};"></div>
                            <div class="card-body">
                                <div class="card-header">
                                    <h3 class="card-title">{{ $subject['name'] }}</h3>
                                    <span class="card-credit">{{ $subject['credit'] }} kr</span>
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
                                    <span class="dav-label">Dav</span>
                                    <div class="dav-track">
                                        <div class="dav-fill" style="width: {{ $davWidth }}%; background: {{ $davColor }};"></div>
                                    </div>
                                    <span class="dav-value" style="color: {{ $davColor }};" title="Qoldirgan: {{ $subject['absent_hours'] }} / {{ $subject['auditorium_hours'] }} soat">{{ number_format($dp, 2) }}%</span>
                                </div>

                                <div class="card-footer">
                                    <span class="card-hours"><b>{{ $subject['absent_hours'] }}</b> / {{ $subject['auditorium_hours'] }} soat</span>
                                    <button class="btn-detail" @click="openModal({{ $index }})">
                                        Batafsil
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>
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
                                        <span class="info-value" style="color: {{ $subject['jn_average'] >= 90 ? '#059669' : ($subject['jn_average'] >= 70 ? '#2563eb' : ($subject['jn_average'] >= 60 ? '#d97706' : ($subject['jn_average'] > 0 ? '#dc2626' : '#94a3b8'))) }}">
                                            {{ $subject['jn_average'] > 0 ? $subject['jn_average'] : '-' }}
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">MT %</span>
                                        <span class="info-value" style="color: {{ $subject['mt_average'] >= 90 ? '#059669' : ($subject['mt_average'] >= 70 ? '#2563eb' : ($subject['mt_average'] >= 60 ? '#d97706' : ($subject['mt_average'] > 0 ? '#dc2626' : '#94a3b8'))) }}">
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
                                @php $jbData = $subject['jb_daily_data']; @endphp
                                @if(count($jbData) > 0)
                                    <div class="h-table-wrap">
                                        <table class="h-table">
                                            <thead>
                                                <tr>
                                                    @foreach($jbData as $day)
                                                        <th><div class="date-text">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div></th>
                                                    @endforeach
                                                    <th class="avg-col th-avg">O'rtacha</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($jbData as $day)
                                                        <td>
                                                            @if($day['has_grades'])
                                                                @php $v = $day['average']; @endphp
                                                                <span class="h-cell {{ $v >= 90 ? 'hc-excellent' : ($v >= 70 ? 'hc-good' : ($v >= 60 ? 'hc-ok' : 'hc-fail')) }}">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="h-cell hc-nb">NB</span>
                                                            @else
                                                                <span class="hc-empty">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="avg-col">
                                                        @php $v = $subject['jn_average']; @endphp
                                                        <span class="h-cell {{ $v >= 90 ? 'hc-excellent' : ($v >= 70 ? 'hc-good' : ($v >= 60 ? 'hc-ok' : ($v > 0 ? 'hc-fail' : ''))) }}" style="width: auto; padding: 4px 12px; font-size: 14px;">
                                                            {{ $v > 0 ? $v : '-' }}
                                                        </span>
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
                                @php $lecData = $subject['lecture_by_date']; @endphp
                                @if(count($lecData) > 0)
                                    <div class="h-table-wrap">
                                        <table class="h-table">
                                            <thead>
                                                <tr>
                                                    @foreach($lecData as $day)
                                                        <th><div class="date-text">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div></th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($lecData as $day)
                                                        <td>
                                                            @if($day['status'] === 'NB')
                                                                <span class="h-cell hc-nb">NB</span>
                                                            @else
                                                                <span class="h-cell hc-qb">QB</span>
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
                                @php $mtData = $subject['mt_daily_data']; @endphp
                                @if(count($mtData) > 0)
                                    <div class="h-table-wrap">
                                        <table class="h-table">
                                            <thead>
                                                <tr>
                                                    @foreach($mtData as $day)
                                                        <th><div class="date-text">{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</div></th>
                                                    @endforeach
                                                    <th class="avg-col th-avg">O'rtacha</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach($mtData as $day)
                                                        <td>
                                                            @if($day['has_grades'])
                                                                @php $v = $day['average']; @endphp
                                                                <span class="h-cell {{ $v >= 90 ? 'hc-excellent' : ($v >= 70 ? 'hc-good' : ($v >= 60 ? 'hc-ok' : 'hc-fail')) }}">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="h-cell hc-nb">NB</span>
                                                            @else
                                                                <span class="hc-empty">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="avg-col">
                                                        @php $v = $subject['mt_average']; @endphp
                                                        <span class="h-cell {{ $v >= 90 ? 'hc-excellent' : ($v >= 70 ? 'hc-good' : ($v >= 60 ? 'hc-ok' : ($v > 0 ? 'hc-fail' : ''))) }}" style="width: auto; padding: 4px 12px; font-size: 14px;">
                                                            {{ $v > 0 ? $v : '-' }}
                                                        </span>
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

    <script>
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
    </script>
</x-student-app-layout>
