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
        /* ===== STAT CARDS ===== */
        .stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card {
            background: white; border-radius: 12px; padding: 16px 18px;
            border: 1px solid #e2e8f0; position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #0ea5e9, #06b6d4); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
        .stat-value { font-size: 22px; font-weight: 800; color: #0f172a; }
        .stat-value small { font-size: 13px; font-weight: 500; color: #94a3b8; }
        .dark .stat-card { background: #1f2937; border-color: #374151; }
        .dark .stat-label { color: #94a3b8; }
        .dark .stat-value { color: #f1f5f9; }
        @media (max-width: 768px) { .stat-cards { grid-template-columns: repeat(2, 1fr); } }

        /* ===== TABLE ===== */
        .subjects-card {
            background: linear-gradient(135deg, #1e3a5f 0%, #2b5ea7 50%, #6366f1 100%);
            border-radius: 16px; padding: 2px;
        }
        .subjects-card-inner { background: white; border-radius: 14px; overflow: hidden; }
        .dark .subjects-card-inner { background: #1f2937; }

        .subjects-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .subjects-table thead th {
            padding: 14px 8px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            text-align: center; color: white; white-space: nowrap;
            background: linear-gradient(135deg, #1e3a5f, #2b5ea7);
        }
        .subjects-table thead th:first-child { border-radius: 14px 0 0 0; }
        .subjects-table thead th:last-child { border-radius: 0 14px 0 0; }
        .subjects-table thead th.text-left { text-align: left; }

        .subject-row { transition: all 0.15s; border-left: 4px solid transparent; }
        .subject-row:nth-child(odd) { background: #fafbff; }
        .subject-row:nth-child(even) { background: #ffffff; }
        .subject-row:hover { background: linear-gradient(90deg, #eef2ff, #f5f3ff); border-left-color: #6366f1; }
        .dark .subject-row:nth-child(odd) { background: #1a2332; }
        .dark .subject-row:nth-child(even) { background: #1f2937; }
        .dark .subject-row:hover { background: linear-gradient(90deg, #1e293b, #1e1b3a); border-left-color: #818cf8; }
        .subject-row td { padding: 10px 6px; text-align: center; border-bottom: 1px solid #f1f5f9; }
        .dark .subject-row td { border-bottom-color: #1e293b; }

        .subject-number {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 50%;
            font-weight: 700; font-size: 11px;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4338ca;
        }
        .dark .subject-number { background: #312e81; color: #a5b4fc; }

        .subject-name { font-weight: 600; color: #1e293b; font-size: 13px; line-height: 1.3; }
        .dark .subject-name { color: #e2e8f0; }

        .credit-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 8px;
            font-weight: 800; font-size: 13px;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af;
        }
        .dark .credit-badge { background: #1e3a5f; color: #93c5fd; }

        /* Grade badges */
        .grade-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 42px; padding: 5px 10px; border-radius: 8px;
            font-weight: 700; font-size: 13px;
        }
        .grade-excellent { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
        .grade-good { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
        .grade-ok { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        .grade-fail { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; }
        .grade-empty { background: #f1f5f9; color: #94a3b8; }
        .dark .grade-excellent { background: linear-gradient(135deg, #064e3b, #065f46); color: #6ee7b7; }
        .dark .grade-good { background: linear-gradient(135deg, #1e3a5f, #1e40af); color: #93c5fd; }
        .dark .grade-ok { background: linear-gradient(135deg, #78350f, #92400e); color: #fcd34d; }
        .dark .grade-fail { background: linear-gradient(135deg, #7f1d1d, #991b1b); color: #fca5a5; }
        .dark .grade-empty { background: #374151; color: #6b7280; }

        /* Dav% */
        .dav-bar { height: 4px; border-radius: 2px; background: #e5e7eb; overflow: hidden; margin-top: 3px; }
        .dark .dav-bar { background: #374151; }
        .dav-bar-fill { height: 100%; border-radius: 2px; transition: width 0.8s ease; }
        .dav-text { font-size: 12px; font-weight: 700; }

        /* Batafsil button */
        .btn-detail {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: white; border: none; cursor: pointer; transition: all 0.15s;
            box-shadow: 0 2px 6px rgba(26, 50, 104, 0.3);
        }
        .btn-detail:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26, 50, 104, 0.4); }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-box {
            background: #fff; border-radius: 16px; width: 100%; max-width: 1100px;
            max-height: 90vh; box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            overflow: hidden; display: flex; flex-direction: column;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff;
            flex-shrink: 0;
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
            padding: 14px 20px; background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-grid { display: flex; flex-wrap: wrap; gap: 20px; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .info-value { font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        /* Modal tabs */
        .modal-tabs {
            display: flex; gap: 0; padding: 0 20px;
            background: #f8fafc; border-bottom: 2px solid #e2e8f0;
            position: sticky; top: 0; z-index: 2;
        }
        .modal-tab-btn {
            padding: 10px 20px; font-size: 13px; font-weight: 600;
            color: #64748b; background: transparent; border: none; border-bottom: 2px solid transparent;
            margin-bottom: -2px; cursor: pointer; transition: all 0.15s; white-space: nowrap;
        }
        .modal-tab-btn.active { color: #1e40af; border-bottom-color: #2b5ea7; background: #fff; }
        .modal-tab-btn:hover:not(.active) { color: #475569; }

        /* ===== HORIZONTAL TABLE ===== */
        .h-table-wrap { overflow-x: auto; padding: 16px 20px; }
        .h-table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
        .h-table thead { position: sticky; top: 0; z-index: 1; }
        .h-table th {
            background: linear-gradient(180deg, #f8fafc, #f1f5f9);
            padding: 6px 4px; text-align: center;
            font-weight: 600; font-size: 10px; color: #475569;
            border-bottom: 2px solid #e2e8f0;
            min-width: 48px; width: 48px; height: 80px; vertical-align: bottom;
        }
        .h-table th .date-text {
            writing-mode: vertical-rl; text-orientation: mixed;
            transform: rotate(180deg); white-space: nowrap;
            display: inline-block; font-size: 10.5px;
        }
        .h-table td {
            padding: 10px 4px; text-align: center;
            border-bottom: 1px solid #f1f5f9; font-weight: 600; font-size: 13px;
        }
        .h-table tbody tr:hover td { background: #f0f4ff; }
        .h-table .avg-col {
            background: linear-gradient(180deg, #f0fdf4, #dcfce7) !important;
            font-weight: 800; border-left: 2px solid #bbf7d0; min-width: 70px;
        }
        .h-table .avg-col.th-avg {
            background: linear-gradient(180deg, #dcfce7, #bbf7d0) !important;
            color: #065f46; vertical-align: middle; height: auto;
        }

        .h-grade-cell {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            font-weight: 700; font-size: 12px;
        }
        .h-grade-excellent { background: #d1fae5; color: #065f46; }
        .h-grade-good { background: #dbeafe; color: #1e40af; }
        .h-grade-ok { background: #fef3c7; color: #92400e; }
        .h-grade-fail { background: #fee2e2; color: #991b1b; }
        .h-grade-nb { background: #fee2e2; color: #dc2626; font-size: 10px; }
        .h-grade-empty { color: #cbd5e1; }
        .h-grade-qb { background: #d1fae5; color: #065f46; font-size: 10px; }

        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 13px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .responsive-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .subjects-table { table-layout: auto; }
            .modal-box { max-width: 100vw; border-radius: 12px; }
        }
        @media (max-width: 640px) {
            .stat-cards { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 12px 14px; }
            .stat-value { font-size: 18px; }
            .modal-box { border-radius: 8px; max-height: 95vh; }
            .info-grid { gap: 12px; }
        }
    </style>

    <div class="py-6" x-data="subjectsApp()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Stat Cards --}}
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-label">Jami fanlar</div>
                    <div class="stat-value">{{ $subjectCount }} <small>ta</small></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Jami kredit</div>
                    <div class="stat-value">{{ $totalCredit }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">O'rtacha JN</div>
                    <div class="stat-value">{{ $avgJn }} <small>%</small></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Qoldirgan soat</div>
                    <div class="stat-value">{{ $totalAbsent }} <small>/ {{ $totalAuditorium }}</small></div>
                </div>
            </div>

            {{-- Main Table --}}
            <div class="subjects-card">
                <div class="subjects-card-inner">
                    <div class="responsive-table">
                        <table class="subjects-table">
                            <colgroup>
                                <col style="width: 4%;">
                                <col style="width: 26%;">
                                <col style="width: 6%;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
                                <col style="width: 7%;">
                                <col style="width: 7%;">
                                <col style="width: 14%;">
                                <col style="width: 12%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>T/r</th>
                                    <th class="text-left" style="padding-left: 12px;">Fan</th>
                                    <th>Kredit</th>
                                    <th>JN %</th>
                                    <th>MT %</th>
                                    <th>ON %</th>
                                    <th>OSKI</th>
                                    <th>Test</th>
                                    <th>Dav %</th>
                                    <th>Batafsil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subjects as $index => $subject)
                                    <tr class="subject-row">
                                        <td><span class="subject-number">{{ $index + 1 }}</span></td>
                                        <td style="text-align: left; padding-left: 12px;">
                                            <span class="subject-name">{{ $subject['name'] }}</span>
                                        </td>
                                        <td><span class="credit-badge">{{ $subject['credit'] }}</span></td>
                                        <td>
                                            @php $v = $subject['jn_average']; @endphp
                                            <span class="grade-badge {{ $v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : ($v > 0 ? 'grade-fail' : 'grade-empty'))) }}">
                                                {{ $v > 0 ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            @php $v = $subject['mt_average']; @endphp
                                            <span class="grade-badge {{ $v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : ($v > 0 ? 'grade-fail' : 'grade-empty'))) }}">
                                                {{ $v > 0 ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            @php $v = $subject['on']; @endphp
                                            <span class="grade-badge {{ $v !== null ? ($v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : 'grade-fail'))) : 'grade-empty' }}">
                                                {{ $v !== null ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td><span class="grade-badge grade-empty">-</span></td>
                                        <td><span class="grade-badge grade-empty">-</span></td>
                                        <td>
                                            @php
                                                $dp = $subject['dav_percent'];
                                                $davColor = $dp >= 25 ? '#ef4444' : ($dp >= 15 ? '#f59e0b' : '#22c55e');
                                                $davWidth = min($dp * 2, 100);
                                            @endphp
                                            <span class="dav-text" style="color: {{ $davColor }};" title="Qoldirgan: {{ $subject['absent_hours'] }} soat / {{ $subject['auditorium_hours'] }} soat">
                                                {{ number_format($dp, 2) }}%
                                            </span>
                                            <div class="dav-bar">
                                                <div class="dav-bar-fill" style="width: {{ $davWidth }}%; background: {{ $davColor }};"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn-detail" @click="openModal({{ $index }})">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                Batafsil
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($subjects->isEmpty())
                        <div class="empty-state" style="padding: 48px 20px;">
                            <svg style="width: 48px; height: 48px; color: #cbd5e1; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Bu semestrda fanlar topilmadi
                        </div>
                    @endif
                </div>
            </div>
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
            <div class="modal-box" @click.stop>
                {{-- Modal Header --}}
                <div class="modal-header">
                    <h3 x-text="modalTitle"></h3>
                    <button @click="closeModal()" class="modal-close">&times;</button>
                </div>

                {{-- Scrollable Content --}}
                <div class="modal-scroll">
                    @foreach($subjects as $index => $subject)
                        <div x-show="activeSubject === {{ $index }}">
                            {{-- Info Section --}}
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
                                        <span class="info-value" style="color: {{ $subject['dav_percent'] >= 25 ? '#dc2626' : ($subject['dav_percent'] >= 15 ? '#d97706' : '#059669') }}">
                                            {{ number_format($subject['dav_percent'], 2) }}%
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Qoldirgan</span>
                                        <span class="info-value">{{ $subject['absent_hours'] }} <small style="color: #94a3b8; font-weight: 400;">/ {{ $subject['auditorium_hours'] }} soat</small></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Tabs --}}
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

                            {{-- Tab Content --}}

                            {{-- AMALIY (JN) TAB --}}
                            <div x-show="activeTab === 'amaliy'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
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
                                                                <span class="h-grade-cell {{ $v >= 90 ? 'h-grade-excellent' : ($v >= 70 ? 'h-grade-good' : ($v >= 60 ? 'h-grade-ok' : 'h-grade-fail')) }}">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="h-grade-cell h-grade-nb">NB</span>
                                                            @else
                                                                <span class="h-grade-empty">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="avg-col">
                                                        @php $v = $subject['jn_average']; @endphp
                                                        <span class="h-grade-cell {{ $v >= 90 ? 'h-grade-excellent' : ($v >= 70 ? 'h-grade-good' : ($v >= 60 ? 'h-grade-ok' : ($v > 0 ? 'h-grade-fail' : ''))) }}" style="width: auto; padding: 4px 12px; font-size: 14px;">
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
                            <div x-show="activeTab === 'maruza'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
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
                                                                <span class="h-grade-cell h-grade-nb">NB</span>
                                                            @else
                                                                <span class="h-grade-cell h-grade-qb">QB</span>
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

                            {{-- MUSTAQIL TA'LIM TAB --}}
                            <div x-show="activeTab === 'mt'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
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
                                                                <span class="h-grade-cell {{ $v >= 90 ? 'h-grade-excellent' : ($v >= 70 ? 'h-grade-good' : ($v >= 60 ? 'h-grade-ok' : 'h-grade-fail')) }}">{{ $v }}</span>
                                                            @elseif($day['is_absent'])
                                                                <span class="h-grade-cell h-grade-nb">NB</span>
                                                            @else
                                                                <span class="h-grade-empty">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="avg-col">
                                                        @php $v = $subject['mt_average']; @endphp
                                                        <span class="h-grade-cell {{ $v >= 90 ? 'h-grade-excellent' : ($v >= 70 ? 'h-grade-good' : ($v >= 60 ? 'h-grade-ok' : ($v > 0 ? 'h-grade-fail' : ''))) }}" style="width: auto; padding: 4px 12px; font-size: 14px;">
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
