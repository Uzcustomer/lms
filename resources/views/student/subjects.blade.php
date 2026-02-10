<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Joriy fanlar <span class="text-base font-normal text-gray-500">({{ $semester }})</span>
        </h2>
    </x-slot>

    <style>
        .subjects-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 1px;
        }
        .subjects-card-inner {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }
        .dark .subjects-card-inner { background: #1f2937; }
        .subject-row {
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .subject-row:hover {
            background: linear-gradient(90deg, #f0f4ff 0%, #faf5ff 100%);
            border-left-color: #6366f1;
        }
        .dark .subject-row:hover {
            background: linear-gradient(90deg, #1e293b 0%, #1e1b3a 100%);
            border-left-color: #818cf8;
        }
        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
        }
        .grade-excellent { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
        .grade-good { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
        .grade-ok { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        .grade-fail { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; }
        .grade-empty { background: #f3f4f6; color: #9ca3af; }
        .dark .grade-excellent { background: linear-gradient(135deg, #064e3b, #065f46); color: #6ee7b7; }
        .dark .grade-good { background: linear-gradient(135deg, #1e3a5f, #1e40af); color: #93c5fd; }
        .dark .grade-ok { background: linear-gradient(135deg, #78350f, #92400e); color: #fcd34d; }
        .dark .grade-fail { background: linear-gradient(135deg, #7f1d1d, #991b1b); color: #fca5a5; }
        .dark .grade-empty { background: #374151; color: #6b7280; }
        .dav-bar { height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; margin-top: 4px; }
        .dark .dav-bar { background: #374151; }
        .dav-bar-fill { height: 100%; border-radius: 3px; transition: width 0.8s ease; }
        .detail-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
            opacity: 0;
        }
        .detail-panel.open { max-height: 3000px; opacity: 1; }
        .tab-btn {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px 8px 0 0;
            border: 1px solid #e5e7eb;
            border-bottom: none;
            background: #f9fafb;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .tab-btn.active {
            background: white;
            color: #4f46e5;
            border-color: #c7d2fe;
            z-index: 1;
            margin-bottom: -1px;
        }
        .dark .tab-btn { background: #374151; color: #9ca3af; border-color: #4b5563; }
        .dark .tab-btn.active { background: #1f2937; color: #818cf8; border-color: #6366f1; }
        .tab-content-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0 8px 8px 8px;
            overflow: hidden;
        }
        .dark .tab-content-box { background: #1f2937; border-color: #4b5563; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .dark .detail-table th { background: #111827; color: #94a3b8; border-color: #334155; }
        .detail-table td {
            padding: 8px 12px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .dark .detail-table td { border-color: #1e293b; color: #cbd5e1; }
        .detail-table tbody tr:hover td { background: #f8fafc; }
        .dark .detail-table tbody tr:hover td { background: #111827; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-present { background: #d1fae5; color: #065f46; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-recorded { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-retake { background: #dbeafe; color: #1e40af; }
        .dark .status-present, .dark .status-recorded { background: #064e3b; color: #6ee7b7; }
        .dark .status-absent { background: #7f1d1d; color: #fca5a5; }
        .dark .status-pending { background: #78350f; color: #fcd34d; }
        .dark .status-retake { background: #1e3a5f; color: #93c5fd; }
        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3);
        }
        .btn-detail:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .btn-detail.active { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .btn-detail svg { transition: transform 0.3s ease; }
        .btn-detail.active svg { transform: rotate(180deg); }
        .credit-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 14px;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #4338ca;
        }
        .dark .credit-badge { background: linear-gradient(135deg, #312e81, #3730a3); color: #a5b4fc; }
        .subject-name-cell { font-weight: 600; color: #1e293b; font-size: 14px; }
        .dark .subject-name-cell { color: #e2e8f0; }
        .table-header-cell {
            padding: 14px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-align: center;
            color: #475569;
            white-space: nowrap;
            background: linear-gradient(180deg, #f8fafc, #f1f5f9);
        }
        .dark .table-header-cell { color: #94a3b8; background: linear-gradient(180deg, #111827, #0f172a); }
        .subject-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 12px;
            background: #f1f5f9;
            color: #64748b;
        }
        .dark .subject-number { background: #334155; color: #94a3b8; }
        .empty-state {
            text-align: center;
            padding: 24px;
            color: #9ca3af;
            font-size: 13px;
        }
        .dark .empty-state { color: #6b7280; }
        .detail-grade-cell {
            font-weight: 700;
            font-size: 14px;
        }
        @media (max-width: 1024px) {
            .responsive-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        }
    </style>

    <div class="py-6" x-data="subjectsApp()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="subjects-card">
                <div class="subjects-card-inner">
                    <div class="responsive-table">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b-2 border-indigo-100 dark:border-indigo-900">
                                    <th class="table-header-cell" style="width: 50px;">T/r</th>
                                    <th class="table-header-cell text-left" style="min-width: 200px;">Fan</th>
                                    <th class="table-header-cell" style="width: 60px;">Kredit</th>
                                    <th class="table-header-cell" style="width: 70px;">JN %</th>
                                    <th class="table-header-cell" style="width: 70px;">MT %</th>
                                    <th class="table-header-cell" style="width: 70px;">ON %</th>
                                    <th class="table-header-cell" style="width: 65px;">OSKI</th>
                                    <th class="table-header-cell" style="width: 65px;">Test</th>
                                    <th class="table-header-cell" style="width: 100px;">Dav %</th>
                                    <th class="table-header-cell" style="width: 90px;">Batafsil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subjects as $index => $subject)
                                    <tr class="subject-row border-b border-gray-100 dark:border-gray-700">
                                        <td class="px-2 py-3 text-center">
                                            <span class="subject-number">{{ $index + 1 }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="subject-name-cell">{{ $subject['name'] }}</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="credit-badge">{{ $subject['credit'] }}</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            @php $v = $subject['jn_average']; @endphp
                                            <span class="grade-badge {{ $v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : ($v > 0 ? 'grade-fail' : 'grade-empty'))) }}">
                                                {{ $v > 0 ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            @php $v = $subject['mt_average']; @endphp
                                            <span class="grade-badge {{ $v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : ($v > 0 ? 'grade-fail' : 'grade-empty'))) }}">
                                                {{ $v > 0 ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            @php $v = $subject['on']; @endphp
                                            <span class="grade-badge {{ $v !== null ? ($v >= 90 ? 'grade-excellent' : ($v >= 70 ? 'grade-good' : ($v >= 60 ? 'grade-ok' : 'grade-fail'))) : 'grade-empty' }}">
                                                {{ $v !== null ? $v : '-' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="grade-badge grade-empty">-</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="grade-badge grade-empty">-</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            @php
                                                $dp = $subject['dav_percent'];
                                                $davColor = $dp >= 25 ? '#ef4444' : ($dp >= 15 ? '#f59e0b' : '#22c55e');
                                                $davWidth = min($dp * 2, 100);
                                            @endphp
                                            <span class="dav-label" style="color: {{ $davColor }};" title="Qoldirgan: {{ $subject['absent_hours'] }} soat / {{ $subject['auditorium_hours'] }} soat">{{ number_format($dp, 1) }}%</span>
                                            <div class="dav-bar">
                                                <div class="dav-bar-fill" style="width: {{ $davWidth }}%; background: {{ $davColor }};"></div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <button class="btn-detail"
                                                    :class="{ 'active': openSubject === {{ $index }} }"
                                                    @click="toggleSubject({{ $index }})">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="6 9 12 15 18 9"></polyline>
                                                </svg>
                                                <span x-text="openSubject === {{ $index }} ? 'Yopish' : 'Ochish'">Ochish</span>
                                            </button>
                                        </td>
                                    </tr>
                                    {{-- Batafsil panel --}}
                                    <tr>
                                        <td colspan="10" class="p-0 border-0">
                                            <div class="detail-panel" :class="{ 'open': openSubject === {{ $index }} }">
                                                <div class="px-4 pt-3 pb-4 bg-gradient-to-br from-indigo-50/50 to-purple-50/50 dark:from-gray-800/50 dark:to-gray-900/50" x-data="{ activeTab: 'amaliy' }">
                                                    {{-- Qisqacha statistika --}}
                                                    <div class="flex flex-wrap items-center gap-4 mb-3 text-xs text-gray-500 dark:text-gray-400">
                                                        <span title="Qoldirgan soat">
                                                            <svg class="inline w-3.5 h-3.5 mr-1 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Qoldirgan: <strong class="text-gray-700 dark:text-gray-300">{{ $subject['absent_hours'] }}</strong> soat
                                                        </span>
                                                        <span title="Umumiy auditoriya soati">
                                                            <svg class="inline w-3.5 h-3.5 mr-1 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                            Auditoriya: <strong class="text-gray-700 dark:text-gray-300">{{ $subject['auditorium_hours'] }}</strong> soat
                                                        </span>
                                                    </div>

                                                    {{-- Tablar --}}
                                                    <div class="flex gap-1">
                                                        <button class="tab-btn" :class="{ 'active': activeTab === 'maruza' }" @click="activeTab = 'maruza'">
                                                            Ma'ruza <span class="ml-1 text-xs opacity-60">({{ count($subject['lecture_attendance']) }})</span>
                                                        </button>
                                                        <button class="tab-btn" :class="{ 'active': activeTab === 'amaliy' }" @click="activeTab = 'amaliy'">
                                                            Amaliy <span class="ml-1 text-xs opacity-60">({{ count($subject['amaliy_grades']) }})</span>
                                                        </button>
                                                        <button class="tab-btn" :class="{ 'active': activeTab === 'mt' }" @click="activeTab = 'mt'">
                                                            Mustaqil ta'lim <span class="ml-1 text-xs opacity-60">({{ count($subject['mt_grades']) }})</span>
                                                        </button>
                                                    </div>

                                                    {{-- Tab content --}}
                                                    <div class="tab-content-box">

                                                        {{-- MA'RUZA TAB --}}
                                                        <div x-show="activeTab === 'maruza'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                                            @if(count($subject['lecture_attendance']) > 0)
                                                                <table class="detail-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 40px;">#</th>
                                                                            <th>Sana</th>
                                                                            <th>Juftlik</th>
                                                                            <th>O'qituvchi</th>
                                                                            <th style="width: 100px;">Holat</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($subject['lecture_attendance'] as $li => $lec)
                                                                            <tr>
                                                                                <td class="text-center text-gray-400">{{ $li + 1 }}</td>
                                                                                <td class="font-medium">{{ format_date($lec['lesson_date']) }}</td>
                                                                                <td>{{ $lec['lesson_pair_name'] }} <span class="text-gray-400 text-xs">({{ $lec['lesson_pair_start_time'] }}-{{ $lec['lesson_pair_end_time'] }})</span></td>
                                                                                <td>{{ $lec['employee_name'] }}</td>
                                                                                <td>
                                                                                    @if($lec['status'] === 'NB')
                                                                                        <span class="status-badge status-absent">
                                                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                                            NB
                                                                                        </span>
                                                                                    @else
                                                                                        <span class="status-badge status-present">
                                                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                                            Qatnashdi
                                                                                        </span>
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            @else
                                                                <div class="empty-state">Ma'ruza davomati mavjud emas</div>
                                                            @endif
                                                        </div>

                                                        {{-- AMALIY TAB --}}
                                                        <div x-show="activeTab === 'amaliy'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                                            @if(count($subject['amaliy_grades']) > 0)
                                                                <table class="detail-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 40px;">#</th>
                                                                            <th>Sana</th>
                                                                            <th>Juftlik</th>
                                                                            <th>O'qituvchi</th>
                                                                            <th style="width: 80px;">Baho</th>
                                                                            <th style="width: 100px;">Holat</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($subject['amaliy_grades'] as $ai => $ag)
                                                                            @php
                                                                                $displayGrade = $ag['grade'];
                                                                                $statusClass = 'status-recorded';
                                                                                $statusText = 'Baholangan';
                                                                                if ($ag['status'] === 'pending' && $ag['reason'] === 'absent') {
                                                                                    $displayGrade = '0 (NB)';
                                                                                    $statusClass = 'status-absent';
                                                                                    $statusText = 'NB';
                                                                                } elseif ($ag['status'] === 'pending') {
                                                                                    $statusClass = 'status-pending';
                                                                                    $statusText = 'Kutilmoqda';
                                                                                } elseif ($ag['status'] === 'retake') {
                                                                                    $displayGrade = ($ag['grade'] ?? 0) . '/' . ($ag['retake_grade'] ?? '-');
                                                                                    $statusClass = 'status-retake';
                                                                                    $statusText = 'Qayta';
                                                                                }
                                                                                $gradeNum = is_numeric($ag['retake_grade'] ?? null) ? $ag['retake_grade'] : (is_numeric($ag['grade']) ? $ag['grade'] : 0);
                                                                                $gradeColor = $gradeNum >= 90 ? '#059669' : ($gradeNum >= 70 ? '#2563eb' : ($gradeNum >= 60 ? '#d97706' : '#dc2626'));
                                                                            @endphp
                                                                            <tr>
                                                                                <td class="text-center text-gray-400">{{ $ai + 1 }}</td>
                                                                                <td class="font-medium">{{ format_date($ag['lesson_date']) }}</td>
                                                                                <td>{{ $ag['lesson_pair_name'] }} <span class="text-gray-400 text-xs">({{ $ag['lesson_pair_start_time'] }}-{{ $ag['lesson_pair_end_time'] }})</span></td>
                                                                                <td>{{ $ag['employee_name'] }}</td>
                                                                                <td>
                                                                                    <span class="detail-grade-cell" style="color: {{ $gradeColor }}">{{ $displayGrade }}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <span class="status-badge {{ $statusClass }}">
                                                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                                        {{ $statusText }}
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            @else
                                                                <div class="empty-state">Amaliy baholar mavjud emas</div>
                                                            @endif
                                                        </div>

                                                        {{-- MUSTAQIL TA'LIM TAB --}}
                                                        <div x-show="activeTab === 'mt'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                                            @if(count($subject['mt_grades']) > 0)
                                                                <table class="detail-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 40px;">#</th>
                                                                            <th>Sana</th>
                                                                            <th>Juftlik</th>
                                                                            <th>O'qituvchi</th>
                                                                            <th style="width: 80px;">Baho</th>
                                                                            <th style="width: 100px;">Holat</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($subject['mt_grades'] as $mi => $mg)
                                                                            @php
                                                                                $displayGrade = $mg['grade'];
                                                                                $statusClass = 'status-recorded';
                                                                                $statusText = 'Baholangan';
                                                                                if ($mg['status'] === 'pending' && ($mg['reason'] ?? '') === 'absent') {
                                                                                    $displayGrade = '0 (NB)';
                                                                                    $statusClass = 'status-absent';
                                                                                    $statusText = 'NB';
                                                                                } elseif ($mg['status'] === 'pending') {
                                                                                    $statusClass = 'status-pending';
                                                                                    $statusText = 'Kutilmoqda';
                                                                                } elseif ($mg['status'] === 'retake') {
                                                                                    $displayGrade = ($mg['grade'] ?? 0) . '/' . ($mg['retake_grade'] ?? '-');
                                                                                    $statusClass = 'status-retake';
                                                                                    $statusText = 'Qayta';
                                                                                }
                                                                                $gradeNum = is_numeric($mg['retake_grade'] ?? null) ? $mg['retake_grade'] : (is_numeric($mg['grade']) ? $mg['grade'] : 0);
                                                                                $gradeColor = $gradeNum >= 90 ? '#059669' : ($gradeNum >= 70 ? '#2563eb' : ($gradeNum >= 60 ? '#d97706' : '#dc2626'));
                                                                            @endphp
                                                                            <tr>
                                                                                <td class="text-center text-gray-400">{{ $mi + 1 }}</td>
                                                                                <td class="font-medium">{{ format_date($mg['lesson_date']) }}</td>
                                                                                <td>{{ $mg['lesson_pair_name'] }} <span class="text-gray-400 text-xs">({{ $mg['lesson_pair_start_time'] }}-{{ $mg['lesson_pair_end_time'] }})</span></td>
                                                                                <td>{{ $mg['employee_name'] }}</td>
                                                                                <td>
                                                                                    <span class="detail-grade-cell" style="color: {{ $gradeColor }}">{{ $displayGrade }}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <span class="status-badge {{ $statusClass }}">
                                                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                                        {{ $statusText }}
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            @else
                                                                <div class="empty-state">Mustaqil ta'lim baholar mavjud emas</div>
                                                            @endif
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($subjects->isEmpty())
                        <div class="empty-state py-12">
                            <svg class="mx-auto w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Bu semestrda fanlar topilmadi
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function subjectsApp() {
            return {
                openSubject: null,
                toggleSubject(index) {
                    this.openSubject = this.openSubject === index ? null : index;
                }
            }
        }
    </script>
</x-student-app-layout>
