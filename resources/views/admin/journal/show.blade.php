<x-app-layout>
    <style>
        .journal-table {
            border: 1px solid #000;
            width: auto;
            table-layout: auto;
        }
        .journal-table th,
        .journal-table td {
            border: 1px solid #000;
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
        .journal-table tbody tr:hover td {
            background-color: #e0f2fe !important;
        }
        .journal-table thead th {
            background-color: #f3f4f6;
        }
        .tab-container {
            background: #374151;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 10px 10px 0 10px;
            gap: 4px;
            width: 100%;
        }
        .journal-layout {
            display: flex;
            gap: 0;
            align-items: flex-start;
        }
        .journal-main-content {
            flex: 1;
            min-width: 0;
            overflow-x: auto;
        }
        .journal-sidebar {
            width: 280px;
            flex-shrink: 0;
            background: #f8fafc;
            border-left: 2px solid #e2e8f0;
            border-radius: 0 8px 8px 0;
            padding: 0;
            position: sticky;
            top: 0;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }
        .sidebar-header {
            background: #374151;
            color: #fff;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 0 8px 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-field {
            padding: 8px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .sidebar-field:last-child {
            border-bottom: none;
        }
        .sidebar-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .sidebar-select {
            width: 100%;
            padding: 6px 28px 6px 10px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.15s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
        }
        .sidebar-select:hover {
            border-color: #3b82f6;
        }
        .sidebar-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .sidebar-value {
            font-size: 13px;
            font-weight: 500;
            color: #1f2937;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .sidebar-loading {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b7280;
            padding: 4px 0;
        }
        .sidebar-loading.active {
            display: flex;
        }
        .sidebar-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .tab-btn {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px 8px 0 0;
            background: #6b7280;
            color: #d1d5db;
            transition: all 0.2s;
            cursor: pointer;
            outline: none;
        }
        .tab-btn:hover:not(.active) {
            background: #9ca3af;
            color: #fff;
        }
        .tab-btn.active {
            background: #f3f4f6;
            color: #1f2937;
            font-weight: 700;
        }
        .view-btn {
            padding: 4px 12px;
            font-size: 12px;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        .view-btn:first-child {
            border-radius: 4px 0 0 4px;
        }
        .view-btn:last-child {
            border-radius: 0 4px 4px 0;
            margin-left: -1px;
        }
        .view-btn.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .tooltip-cell {
            position: relative;
            cursor: help;
        }
        .tooltip-cell:hover .tooltip-content {
            display: block;
        }
        .tooltip-content {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 100;
            margin-bottom: 4px;
        }
        .tooltip-content::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1f2937;
        }
        .date-separator {
            border-left: 1px solid #000 !important;
        }
        .date-end {
            border-right: 1px solid #000 !important;
        }
        .detailed-date-start {
            border-left: 3px double #000 !important;
        }
        .detailed-date-end {
            border-right: 3px double #000 !important;
        }
        .inconsistent-grade {
            background-color: #fef3c7 !important;
        }
        .grade-fail {
            color: #dc2626 !important;
        }
        .grade-retake {
            color: #7c3aed !important;
        }
        .editable-cell {
            position: relative;
            min-height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .editable-cell:hover {
            background-color: #dbeafe !important;
            cursor: cell;
            border: 1px solid #3b82f6;
        }
        .editable-cell::after {
            content: '✎';
            position: absolute;
            top: 1px;
            right: 2px;
            font-size: 10px;
            color: #3b82f6;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .editable-cell:hover::after {
            opacity: 0.7;
        }
    </style>

    <div class="py-2">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Full-width Tabs with View Toggle -->
            <div class="mb-0">
                <nav class="tab-container">
                    <div style="display: flex; align-items: flex-end; gap: 4px;">
                        <button id="tab-maruza" onclick="switchTab('maruza')"
                            class="tab-btn">
                            Ma'ruza
                        </button>
                        <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                            class="tab-btn active">
                            Amaliyot
                        </button>
                        <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                            class="tab-btn">
                            Mustaqil ta'lim
                        </button>
                    </div>
                    <div class="flex items-center" style="padding-bottom: 8px;">
                        <span class="text-xs mr-2" style="color: #9ca3af;">Ko'rinish:</span>
                        <button id="view-compact" onclick="switchView('compact')" class="view-btn active">Ixcham</button>
                        <button id="view-detailed" onclick="switchView('detailed')" class="view-btn">Batafsil</button>
                    </div>
                </nav>
            </div>

            <!-- Main Layout: Content + Sidebar -->
            <div class="journal-layout">
                <!-- Main content area -->
                <div class="journal-main-content">

            <!-- Ma'ruza Tab Content -->
            <div id="content-maruza" class="tab-content hidden">
                <div class="bg-white">
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $totalLectureDays = count($lectureLessonDates);
                            $lecturePairsByDate = collect($lectureColumns)
                                ->groupBy('date')
                                ->map(fn($items) => $items->pluck('pair')->values()->toArray())
                                ->toArray();
                        @endphp

                        <div id="mz-compact-view" class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="min-width: 180px;">F.I.SH.</th>
                                        @forelse($lectureLessonDates as $idx => $date)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($lectureLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 62px; width: 62px; writing-mode: vertical-rl; transform: rotate(180deg); height: 58px;">
                                                {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">Bo'sh</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php $studentLecture = $lectureAttendance[$student->hemis_id] ?? []; @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($lectureLessonDates as $idx => $date)
                                                @php
                                                    $scheduledPairs = $lecturePairsByDate[$date] ?? [];
                                                    $isDayMarked = false;
                                                    $isAbsent = false;

                                                    foreach ($scheduledPairs as $pair) {
                                                        if (isset($lectureMarkedPairs[$date][$pair])) {
                                                            $isDayMarked = true;
                                                            if (($studentLecture[$date][$pair] ?? null) === 'NB') {
                                                                $isAbsent = true;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($lectureLessonDates) - 1 ? 'date-end' : '' }}">
                                                    @if($isAbsent)
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @elseif($isDayMarked)
                                                        <span class="text-green-600 font-bold">+</span>
                                                    @else
                                                        <span>&nbsp;</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center"><span>&nbsp;</span></td>
                                            @endforelse
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div id="mz-detailed-view" class="overflow-x-auto hidden mt-4 border-t pt-4">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="min-width: 180px;">F.I.SH.</th>
                                        @forelse($lectureColumns as $idx => $col)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $idx === 0 || $lectureColumns[$idx - 1]['date'] !== $col['date'] ? 'detailed-date-start' : '' }} {{ !isset($lectureColumns[$idx + 1]) || $lectureColumns[$idx + 1]['date'] !== $col['date'] ? 'detailed-date-end' : '' }}" style="min-width: 68px; width: 68px; writing-mode: vertical-rl; transform: rotate(180deg); height: 68px;">
                                                {{ \Carbon\Carbon::parse($col['date'])->format('d.m.Y') }}({{ $col['pair'] }})
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">Bo'sh</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php $studentLecture = $lectureAttendance[$student->hemis_id] ?? []; @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($lectureColumns as $idx => $col)
                                                @php
                                                    $lectureMark = $studentLecture[$col['date']][$col['pair']] ?? null;
                                                    $isMarkedPair = isset($lectureMarkedPairs[$col['date']][$col['pair']]);
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 || $lectureColumns[$idx - 1]['date'] !== $col['date'] ? 'detailed-date-start' : '' }} {{ !isset($lectureColumns[$idx + 1]) || $lectureColumns[$idx + 1]['date'] !== $col['date'] ? 'detailed-date-end' : '' }}">
                                                    @if($isMarkedPair && $lectureMark === 'NB')
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @elseif($isMarkedPair)
                                                        <span class="text-green-600 font-bold">+</span>
                                                    @else
                                                        <span>&nbsp;</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center"><span>&nbsp;</span></td>
                                            @endforelse
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Amaliyot Tab Content -->
            <div id="content-amaliyot" class="tab-content">
                <div class="bg-white">
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $totalJbDays = count($jbLessonDates);
                            $totalMtDays = count($mtLessonDates);
                            $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->subDay()->startOfDay();
                            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
                                return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
                            }));
                            $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
                            $totalJbDaysForAverage = count($jbLessonDatesForAverage);
                        @endphp
                        <!-- Compact View (Ixcham) -->
                        <div id="jb-compact-view" class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if($totalJbDays > 0)
                                            <th colspan="{{ $totalJbDays }}" class="px-1 py-1 font-bold text-gray-700 text-center date-separator date-end">Joriy nazorat (kunlik o'rtacha)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 50px;">Dav %</th>
                                    </tr>
                                    <tr>
                                        @forelse($jbLessonDates as $idx => $date)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($jbLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 62px; width: 62px; writing-mode: vertical-rl; transform: rotate(180deg); height: 58px;">
                                                {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentJbGrades = $jbGrades[$student->hemis_id] ?? [];
                                            $dailyAverages = [];
                                            $dailySum = 0;
                                            $hasRetakeInDay = [];
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                // Divide by total pairs in day, not just student's grades
                                                $dailyAverages[$date] = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                if (isset($jbLessonDatesForAverageLookup[$date])) {
                                                    $dailySum += $dailyAverages[$date];
                                                }
                                                $hasRetakeInDay[$date] = count($dayGrades) > 0 && collect($dayGrades)->contains(fn($g) => $g['is_retake']);
                                            }
                                            $jnAverage = $totalJbDaysForAverage > 0
                                                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];

                                            // Calculate attendance percentage with 2 decimal places
                                            $absentOff = $attendanceData[$student->hemis_id] ?? 0;
                                            $davomatPercent = $totalAcload > 0 ? round(($absentOff / $totalAcload) * 100, 2) : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($jbLessonDates as $idx => $date)
                                                @php
                                                    $dayGrades = $studentJbGrades[$date] ?? [];
                                                    $dayAbsences = $jbAbsences[$student->hemis_id][$date] ?? [];
                                                    $dayAvg = $dailyAverages[$date];
                                                    $hasGrades = count($dayGrades) > 0;
                                                    $hasAbsenceNoGrade = !$hasGrades && count($dayAbsences) > 0;
                                                    $gradeValues = $hasGrades ? array_map(fn($g) => round($g['grade'], 0), $dayGrades) : [];
                                                    $gradesText = implode(', ', $gradeValues);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                    $isRetake = $hasRetakeInDay[$date] ?? false;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($jbLessonDates) - 1 ? 'date-end' : '' }} {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @if($hasGrades)
                                                        <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ $dayAvg }}</span>
                                                        @if(count($dayGrades) > 1)
                                                            <span class="tooltip-content">{{ $gradesText }}</span>
                                                        @endif
                                                    @elseif($hasAbsenceNoGrade)
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $jnAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $jnAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalJbDaysForAverage }})</span></td>
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $mtAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center"><span class="{{ $davomatPercent >= 25 ? 'grade-fail font-bold' : 'text-gray-900' }}">{{ number_format($davomatPercent, 2) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed View (Batafsil) -->
                        <div id="jb-detailed-view" class="overflow-x-auto hidden">
                            @php
                                // Group columns by date for separators
                                $prevDate = null;
                                $dateGroups = [];
                                foreach ($jbColumns as $idx => $col) {
                                    $dateGroups[$col['date']][] = $idx;
                                }
                            @endphp
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($jbColumns) > 0)
                                            <th colspan="{{ count($jbColumns) }}" class="px-1 py-1 font-bold text-gray-700 text-center date-separator date-end">Joriy nazorat (har bir dars)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 50px;">Dav %</th>
                                    </tr>
                                    <tr>
                                        @php $prevDate = null; @endphp
                                        @forelse($jbColumns as $colIndex => $col)
                                            @php
                                                $isFirstOfDate = $prevDate !== $col['date'];
                                                $isLastOfDate = !isset($jbColumns[$colIndex + 1]) || $jbColumns[$colIndex + 1]['date'] !== $col['date'];
                                                $prevDate = $col['date'];
                                            @endphp
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }}" style="min-width: 68px; width: 68px; writing-mode: vertical-rl; transform: rotate(180deg); height: 68px;">
                                                {{ \Carbon\Carbon::parse($col['date'])->format('d.m.Y') }}({{ $col['pair'] }})
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentJbGrades = $jbGrades[$student->hemis_id] ?? [];
                                            $dailySum = 0;
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                if (isset($jbLessonDatesForAverageLookup[$date])) {
                                                    $dailySum += $dayAverage;
                                                }
                                            }
                                            $jnAverage = $totalJbDaysForAverage > 0
                                                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];

                                            $absentOff = $attendanceData[$student->hemis_id] ?? 0;
                                            $davomatPercent = $totalAcload > 0 ? round(($absentOff / $totalAcload) * 100, 2) : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @php $prevDate = null; @endphp
                                            @forelse($jbColumns as $colIndex => $col)
                                                @php
                                                    $gradeData = $studentJbGrades[$col['date']][$col['pair']] ?? null;
                                                    $grade = $gradeData ? $gradeData['grade'] : null;
                                                    $isRetake = $gradeData ? $gradeData['is_retake'] : false;
                                                    $isAbsent = isset($jbAbsences[$student->hemis_id][$col['date']][$col['pair']]);
                                                    $isFirstOfDate = $prevDate !== $col['date'];
                                                    $isLastOfDate = !isset($jbColumns[$colIndex + 1]) || $jbColumns[$colIndex + 1]['date'] !== $col['date'];
                                                    $prevDate = $col['date'];

                                                    $dayGrades = $studentJbGrades[$col['date']] ?? [];
                                                    $gradeValues = array_map(fn($g) => round($g['grade'], 0), $dayGrades);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @php
                                                        $canRate = auth()->user()->hasRole('admin');
                                                        $showRatingInput = false;
                                                        $gradeRecordId = null;
                                                        $hasRetake = false;
                                                        $isEmpty = false;

                                                        if ($isAbsent && isset($jbAbsences[$student->hemis_id][$col['date']][$col['pair']])) {
                                                            $absenceData = $jbAbsences[$student->hemis_id][$col['date']][$col['pair']];
                                                            $gradeRecordId = $absenceData['id'];
                                                            $hasRetake = $absenceData['retake_grade'] !== null;
                                                            $showRatingInput = $canRate && !$hasRetake;
                                                        } elseif ($grade !== null && round($grade, 0) < 60 && $gradeData) {
                                                            $gradeRecordId = $gradeData['id'];
                                                            $hasRetake = $gradeData['retake_grade'] !== null;
                                                            $showRatingInput = $canRate && !$hasRetake;
                                                        } elseif (!$isAbsent && $grade === null) {
                                                            // Empty cell - allow creating new grade
                                                            $isEmpty = true;
                                                            $showRatingInput = $canRate;
                                                        }
                                                    @endphp
                                                    @if($grade !== null)
                                                        @if($showRatingInput)
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50" onclick="makeEditable(this, {{ $gradeRecordId }})" title="Bosib baho kiriting">
                                                                <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ round($grade, 0) }}</span>
                                                            </div>
                                                        @elseif($hasRetake)
                                                            <div class="flex items-center justify-center gap-1">
                                                                <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ round($grade, 0) }}</span>
                                                                <span class="text-green-600 text-xs" title="Retake bahosi qo'yilgan">✓</span>
                                                            </div>
                                                        @else
                                                            <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ round($grade, 0) }}</span>
                                                        @endif
                                                    @elseif($isAbsent)
                                                        @if($showRatingInput)
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50" onclick="makeEditable(this, {{ $gradeRecordId }})" title="Bosib baho kiriting">
                                                                <span class="text-red-600 font-medium">NB</span>
                                                            </div>
                                                        @elseif($hasRetake)
                                                            <div class="flex items-center justify-center gap-1">
                                                                <span class="text-red-600 font-medium">NB</span>
                                                                <span class="text-green-600 text-xs" title="Retake bahosi qo'yilgan">✓</span>
                                                            </div>
                                                        @else
                                                            <span class="text-red-600 font-medium">NB</span>
                                                        @endif
                                                    @else
                                                        @if($canRate && $isEmpty)
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50"
                                                                 onclick="makeEditableEmpty(this, '{{ $student->hemis_id }}', '{{ $col['date'] }}', '{{ $col['pair'] }}', '{{ $subjectId }}', '{{ $semesterCode }}')"
                                                                 title="Bosib baho kiriting">
                                                                <span class="text-gray-400">-</span>
                                                            </div>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $jnAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $jnAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalJbDaysForAverage }})</span></td>
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $mtAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center"><span class="{{ $davomatPercent >= 25 ? 'grade-fail font-bold' : 'text-gray-900' }}">{{ number_format($davomatPercent, 2) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Mustaqil ta'lim Tab Content -->
            <div id="content-mustaqil" class="tab-content hidden">
                <div class="bg-white">
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        <!-- Manual Grade Entry Table -->
                        <div class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 80px;">Baho</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 60px;">Saqlash</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            // Get existing manual MT grade (without lesson_date)
                                            $manualGrade = $manualMtGrades[$student->hemis_id] ?? null;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            <td class="px-1 py-1 text-center">
                                                <input type="number"
                                                    id="mt-grade-{{ $student->hemis_id }}"
                                                    class="mt-grade-input w-16 px-1 py-0.5 text-center text-xs border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                                                    min="0" max="100" step="1"
                                                    value="{{ $manualGrade !== null ? round($manualGrade) : '' }}"
                                                    data-student-id="{{ $student->hemis_id }}"
                                                    placeholder="0-100">
                                            </td>
                                            <td class="px-1 py-1 text-center">
                                                <button type="button"
                                                    onclick="saveMtGrade('{{ $student->hemis_id }}')"
                                                    class="save-btn px-2 py-0.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none">
                                                    Saqlash
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $totalMtDays = count($mtLessonDates);
                        @endphp

                        @if($totalMtDays > 0)
                        <div class="mt-4 border-t pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-2">API'dan kelgan baholar</h4>
                            <!-- Compact View (Ixcham) -->
                            <div id="mt-compact-view" class="overflow-x-auto">
                                <table class="journal-table border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                            <th colspan="{{ $totalMtDays }}" class="px-1 py-1 font-bold text-gray-700 text-center date-separator date-end">Mustaqil ta'lim (kunlik o'rtacha)</th>
                                            <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">MT %</th>
                                        </tr>
                                        <tr>
                                            @foreach($mtLessonDates as $idx => $date)
                                                <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($mtLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 62px; width: 62px; writing-mode: vertical-rl; transform: rotate(180deg); height: 58px;">
                                                    {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($students as $index => $student)
                                            @php
                                                $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                                $dailyAverages = [];
                                                $dailySum = 0;
                                                $hasRetakeInDay = [];
                                                foreach ($mtLessonDates as $date) {
                                                    $dayGrades = $studentMtGrades[$date] ?? [];
                                                    $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                    $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                    $gradeSum = array_sum($gradeValues);
                                                    $dailyAverages[$date] = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                    $dailySum += $dailyAverages[$date];
                                                    $hasRetakeInDay[$date] = count($dayGrades) > 0 && collect($dayGrades)->contains(fn($g) => $g['is_retake']);
                                                }
                                                $mtAverage = $totalMtDays > 0
                                                    ? round($dailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                    : 0;
                                            @endphp
                                            <tr>
                                                <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                                <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                                @foreach($mtLessonDates as $idx => $date)
                                                    @php
                                                        $dayGrades = $studentMtGrades[$date] ?? [];
                                                        $dayAbsences = $mtAbsences[$student->hemis_id][$date] ?? [];
                                                        $dayAvg = $dailyAverages[$date];
                                                        $hasGrades = count($dayGrades) > 0;
                                                        $hasAbsenceNoGrade = !$hasGrades && count($dayAbsences) > 0;
                                                        $gradeValues = $hasGrades ? array_map(fn($g) => round($g['grade'], 0), $dayGrades) : [];
                                                        $gradesText = implode(', ', $gradeValues);
                                                        $uniqueGrades = array_unique($gradeValues);
                                                        $isInconsistent = count($uniqueGrades) > 1;
                                                        $isRetake = $hasRetakeInDay[$date] ?? false;
                                                    @endphp
                                                    <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($mtLessonDates) - 1 ? 'date-end' : '' }} {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                        @if($hasGrades)
                                                            <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ $dayAvg }}</span>
                                                            @if(count($dayGrades) > 1)
                                                                <span class="tooltip-content">{{ $gradesText }}</span>
                                                            @endif
                                                        @elseif($hasAbsenceNoGrade)
                                                            <span class="text-red-600 font-medium">NB</span>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="px-1 py-1 text-center"><span class="font-bold {{ $mtAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $mtAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalMtDays }})</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Detailed View (Batafsil) -->
                            <div id="mt-detailed-view" class="overflow-x-auto hidden">
                                <table class="journal-table border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                            @if(count($mtColumns) > 0)
                                                <th colspan="{{ count($mtColumns) }}" class="px-1 py-1 font-bold text-gray-700 text-center date-separator date-end">Mustaqil ta'lim (har bir dars)</th>
                                            @else
                                                <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">MT</th>
                                            @endif
                                            <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">MT %</th>
                                    </tr>
                                    <tr>
                                        @php $prevDate = null; @endphp
                                        @forelse($mtColumns as $colIndex => $col)
                                            @php
                                                $isFirstOfDate = $prevDate !== $col['date'];
                                                $isLastOfDate = !isset($mtColumns[$colIndex + 1]) || $mtColumns[$colIndex + 1]['date'] !== $col['date'];
                                                $prevDate = $col['date'];
                                            @endphp
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }}" style="min-width: 68px; width: 68px; writing-mode: vertical-rl; transform: rotate(180deg); height: 68px;">
                                                {{ \Carbon\Carbon::parse($col['date'])->format('d.m.Y') }}({{ $col['pair'] }})
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $dailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $dailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($dailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @php $prevDate = null; @endphp
                                            @forelse($mtColumns as $colIndex => $col)
                                                @php
                                                    $gradeData = $studentMtGrades[$col['date']][$col['pair']] ?? null;
                                                    $grade = $gradeData ? $gradeData['grade'] : null;
                                                    $isRetake = $gradeData ? $gradeData['is_retake'] : false;
                                                    $isAbsent = isset($mtAbsences[$student->hemis_id][$col['date']][$col['pair']]);
                                                    $isFirstOfDate = $prevDate !== $col['date'];
                                                    $isLastOfDate = !isset($mtColumns[$colIndex + 1]) || $mtColumns[$colIndex + 1]['date'] !== $col['date'];
                                                    $prevDate = $col['date'];

                                                    $dayGrades = $studentMtGrades[$col['date']] ?? [];
                                                    $gradeValues = array_map(fn($g) => round($g['grade'], 0), $dayGrades);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @if($grade !== null)
                                                        <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ round($grade, 0) }}</span>
                                                    @elseif($isAbsent)
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $mtAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $mtAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalMtDays }})</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

                </div><!-- /.journal-main-content -->

                <!-- Right Sidebar: Filters -->
                <div class="journal-sidebar">
                    <div class="sidebar-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                        Filtrlar
                    </div>

                    <!-- Fakultet (erkin) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Fakultet</div>
                        <select id="filter-faculty" class="sidebar-select" style="font-size: 12px;" onchange="onFacultyChange()">
                            <option value="">Barchasi</option>
                            @if($facultyId)
                                <option value="{{ $facultyId }}" selected>{{ $facultyName }}</option>
                            @endif
                        </select>
                        <div id="loading-faculty" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Yo'nalish (fakultetga bog'liq) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Yo'nalish</div>
                        <select id="filter-specialty" class="sidebar-select" style="font-size: 12px;" onchange="onSpecialtyChange()">
                            <option value="">Barchasi</option>
                            @if($specialtyId)
                                <option value="{{ $specialtyId }}" selected>{{ $specialtyName }}</option>
                            @endif
                        </select>
                        <div id="loading-specialty" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Kafedra (ma'lumot - fan almashsa o'zgaradi) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Kafedra</div>
                        <div id="kafedra-display" class="sidebar-value" style="font-size: 12px;">{{ $kafedraName }}</div>
                    </div>

                    <!-- Kurs (yo'nalishga bog'liq) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Kurs</div>
                        <select id="filter-level" class="sidebar-select" onchange="onLevelChange()">
                            <option value="">Barchasi</option>
                            @if($levelCode)
                                <option value="{{ $levelCode }}" selected>{{ $kursName }}</option>
                            @endif
                        </select>
                        <div id="loading-level" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Semestr (kursga bog'liq) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Semestr</div>
                        <select id="filter-semester" class="sidebar-select" onchange="onSemesterChange()">
                            <option value="">Barchasi</option>
                            @if($semesterCode)
                                <option value="{{ $semesterCode }}" selected>{{ $semester->name ?? $subject->semester_name }}</option>
                            @endif
                        </select>
                        <div id="loading-semester" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Guruh (semestrga bog'liq, fan bilan ikki tomonlama) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Guruh</div>
                        <select id="filter-group" class="sidebar-select" onchange="onGroupChange(this.value)">
                            <option value="{{ $groupId }}" selected>{{ $group->name }}</option>
                        </select>
                        <div id="loading-group" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Fan (semestrga bog'liq, guruh bilan ikki tomonlama) -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Fan</div>
                        <select id="filter-subject" class="sidebar-select" onchange="onSubjectChange(this.value)">
                            <option value="{{ $subjectId }}" selected>{{ $subject->subject_name }}</option>
                        </select>
                        <div id="loading-subject" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- Ma'ruza o'qituvchisi -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Ma'ruza o'qituvchisi</div>
                        <div id="lecture-teacher-display" class="sidebar-value" style="font-size: 12px;">
                            @if($lectureTeacher)
                                {{ $lectureTeacher['name'] }} ({{ $lectureTeacher['hours'] }} soat)
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <!-- Amaliyot o'qituvchisi -->
                    <div class="sidebar-field">
                        <div class="sidebar-label">Amaliyot o'qituvchisi</div>
                        <div id="practice-teacher-display" class="sidebar-value" style="font-size: 12px;">
                            @if(count($practiceTeachers) > 0)
                                @foreach($practiceTeachers as $pt)
                                    <div>{{ $pt['name'] }} ({{ $pt['hours'] }} soat)</div>
                                @endforeach
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <!-- Talabalar soni -->
                    <div class="sidebar-field" style="background: #eff6ff;">
                        <div class="sidebar-label">Talabalar soni</div>
                        <div class="sidebar-value" style="font-weight: 700; color: #2563eb; border-color: #bfdbfe;">{{ $students->count() }}</div>
                    </div>
                </div>

            </div><!-- /.journal-layout -->
        </div>
    </div>

    <script>
        // ====== Cascading Sidebar Filters ======
        // Zanjir: Fakultet(erkin) → Yo'nalish → Kurs → Semestr → [Guruh ↔ Fan]
        // Kafedra erkin, fanga ta'sir qiladi.
        const currentGroupId = '{{ $groupId }}';
        const currentSubjectId = '{{ $subjectId }}';
        const currentSemesterCode = '{{ $semesterCode }}';
        const currentFacultyId = '{{ $facultyId }}';
        const currentSpecialtyId = '{{ $specialtyId }}';
        const currentLevelCode = '{{ $levelCode }}';
        const journalShowBaseUrl = '{{ url("/admin/journal/show") }}';
        const sidebarOptionsUrl = '{{ route("admin.journal.get-sidebar-options") }}';

        // URL params - navigatsiyada filtr qiymatlarini saqlash uchun
        const urlParams = new URLSearchParams(window.location.search);

        function getVal(id) { return document.getElementById(id)?.value || ''; }

        function getFilterValues() {
            return {
                faculty_id: getVal('filter-faculty'),
                specialty_id: getVal('filter-specialty'),
                level_code: getVal('filter-level'),
                semester_code: getVal('filter-semester'),
                group_id: getVal('filter-group'),
                subject_id: getVal('filter-subject'),
            };
        }

        function buildQS(params) {
            return Object.entries(params)
                .filter(([k, v]) => v !== '')
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');
        }

        function navigateToJournal(groupId, subjectId, semesterCode) {
            const narrowParams = {
                faculty_id: getVal('filter-faculty'),
                specialty_id: getVal('filter-specialty'),
                level_code: getVal('filter-level'),
            };
            let url = `${journalShowBaseUrl}/${groupId}/${subjectId}/${semesterCode}`;
            const qs = Object.entries(narrowParams)
                .filter(([k, v]) => v !== '')
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');
            if (qs) url += '?' + qs;
            window.location.href = url;
        }

        function setLoading(field, show) {
            const el = document.getElementById('loading-' + field);
            if (el) el.classList.toggle('active', show);
        }

        function populateSelect(selectId, data, currentValue, addAll) {
            const select = document.getElementById(selectId);
            if (!select) return;
            select.innerHTML = '';
            if (addAll) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Barchasi';
                select.appendChild(opt);
            }
            let found = false;
            const entries = Object.entries(data).sort((a, b) => a[1].localeCompare(b[1]));
            for (const [key, value] of entries) {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = value;
                if (String(key) === String(currentValue)) {
                    opt.selected = true;
                    found = true;
                }
                select.appendChild(opt);
            }
            if (!found && !addAll && select.options.length > 0) {
                select.options[0].selected = true;
            }
        }

        // Kaskad reset - ota filtr o'zgarganda bolalar tozalanadi
        function cascadeReset(from) {
            const chains = {
                'faculty':   ['specialty', 'level', 'semester', 'group', 'subject'],
                'specialty': ['level', 'semester', 'group', 'subject'],
                'level':     ['semester', 'group', 'subject'],
                'semester':  ['group', 'subject'],
            };
            (chains[from] || []).forEach(f => {
                const el = document.getElementById('filter-' + f);
                if (el) el.value = '';
            });
        }

        // Barcha dropdownlarni yangilash
        let abortCtrl = null;
        function refreshFilters(overrideValues) {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            const values = overrideValues || getFilterValues();
            const qs = buildQS(values);

            const fields = ['faculty', 'specialty', 'level', 'semester', 'group', 'subject'];
            fields.forEach(f => setLoading(f, true));

            fetch(`${sidebarOptionsUrl}?${qs}`, { signal: abortCtrl.signal })
                .then(r => r.json())
                .then(data => {
                    populateSelect('filter-faculty', data.faculties, values.faculty_id, true);
                    populateSelect('filter-specialty', data.specialties, values.specialty_id, true);
                    populateSelect('filter-level', data.levels, values.level_code, true);
                    populateSelect('filter-semester', data.semesters, values.semester_code, true);
                    populateSelect('filter-group', data.groups, values.group_id, false);
                    populateSelect('filter-subject', data.subjects, values.subject_id, false);
                    // O'qituvchi ma'lumotlarini yangilash (tur bo'yicha)
                    const lectureEl = document.getElementById('lecture-teacher-display');
                    const practiceEl = document.getElementById('practice-teacher-display');
                    if (lectureEl) {
                        if (data.teacher_data && data.teacher_data.lecture_teacher) {
                            const t = data.teacher_data.lecture_teacher;
                            lectureEl.textContent = t.name + ' (' + t.hours + ' soat)';
                        } else {
                            lectureEl.textContent = '-';
                        }
                    }
                    if (practiceEl) {
                        if (data.teacher_data && data.teacher_data.practice_teachers && data.teacher_data.practice_teachers.length > 0) {
                            practiceEl.innerHTML = data.teacher_data.practice_teachers
                                .map(t => '<div>' + t.name + ' (' + t.hours + ' soat)</div>')
                                .join('');
                        } else {
                            practiceEl.textContent = '-';
                        }
                    }
                    // Kafedra ma'lumot sifatida yangilanadi
                    const kafedraEl = document.getElementById('kafedra-display');
                    if (kafedraEl) kafedraEl.textContent = data.kafedra_name || '';
                    fields.forEach(f => setLoading(f, false));
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        fields.forEach(f => setLoading(f, false));
                    }
                });
        }

        // ===== Kaskad filtr handlerlari =====

        // Fakultet o'zgardi → yo'nalish, kurs, semestr, guruh, fan tozalanadi
        function onFacultyChange() {
            cascadeReset('faculty');
            refreshFilters();
        }

        // Yo'nalish o'zgardi → kurs, semestr, guruh, fan tozalanadi
        function onSpecialtyChange() {
            cascadeReset('specialty');
            refreshFilters();
        }

        // Kurs o'zgardi → semestr, guruh, fan tozalanadi
        function onLevelChange() {
            cascadeReset('level');
            refreshFilters();
        }

        // Semestr o'zgardi → guruh, fan tozalanadi va yangilanadi
        function onSemesterChange() {
            cascadeReset('semester');
            refreshFilters();
        }

        // ===== Navigatsiya filtrlari =====

        // Guruh o'zgardi → mos fan topiladi → navigatsiya
        function onGroupChange(newGroupId) {
            if (!newGroupId) return;
            const values = getFilterValues();
            values.group_id = newGroupId;

            setLoading('subject', true);
            fetch(`${sidebarOptionsUrl}?${buildQS(values)}`)
                .then(r => r.json())
                .then(data => {
                    setLoading('subject', false);
                    if (!data.subjects || Object.keys(data.subjects).length === 0) {
                        alert('Bu guruh uchun fanlar topilmadi');
                        return;
                    }
                    let targetSubject = values.subject_id;
                    if (!data.subjects[targetSubject]) {
                        targetSubject = Object.keys(data.subjects)[0];
                    }
                    let targetSemester = values.semester_code;
                    if (!targetSemester || (data.semesters && !data.semesters[targetSemester])) {
                        targetSemester = data.semesters ? Object.keys(data.semesters)[0] : '';
                    }
                    if (targetSubject && targetSemester) {
                        navigateToJournal(newGroupId, targetSubject, targetSemester);
                    }
                })
                .catch(() => {
                    setLoading('subject', false);
                    alert('Xatolik yuz berdi');
                });
        }

        // Fan o'zgardi → mos guruh topiladi → navigatsiya
        function onSubjectChange(newSubjectId) {
            if (!newSubjectId) return;
            const values = getFilterValues();
            values.subject_id = newSubjectId;

            setLoading('group', true);
            fetch(`${sidebarOptionsUrl}?${buildQS(values)}`)
                .then(r => r.json())
                .then(data => {
                    setLoading('group', false);
                    if (!data.groups || Object.keys(data.groups).length === 0) {
                        alert('Bu fan uchun guruhlar topilmadi');
                        return;
                    }
                    let targetGroup = values.group_id;
                    if (!data.groups[targetGroup]) {
                        targetGroup = Object.keys(data.groups)[0];
                    }
                    let targetSemester = values.semester_code;
                    if (!targetSemester || (data.semesters && !data.semesters[targetSemester])) {
                        targetSemester = data.semesters ? Object.keys(data.semesters)[0] : '';
                    }
                    if (targetGroup && targetSemester) {
                        navigateToJournal(targetGroup, newSubjectId, targetSemester);
                    }
                })
                .catch(() => {
                    setLoading('group', false);
                    alert('Xatolik yuz berdi');
                });
        }

        // Sahifa yuklanganda filtrlarni to'ldirish
        document.addEventListener('DOMContentLoaded', function() {
            refreshFilters({
                faculty_id: urlParams.get('faculty_id') || currentFacultyId,
                specialty_id: urlParams.get('specialty_id') || currentSpecialtyId,
                level_code: urlParams.get('level_code') || currentLevelCode,
                semester_code: currentSemesterCode,
                group_id: currentGroupId,
                subject_id: currentSubjectId,
            });
        });

        // MT Grade save configuration
        const mtGradeConfig = {
            url: '{{ route("admin.journal.save-mt-grade") }}',
            subjectId: '{{ $subjectId }}',
            semesterCode: '{{ $semesterCode }}',
            csrfToken: '{{ csrf_token() }}'
        };

        function saveMtGrade(studentHemisId) {
            const input = document.getElementById('mt-grade-' + studentHemisId);
            const grade = input.value;

            if (grade === '' || isNaN(grade) || grade < 0 || grade > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                return;
            }

            const button = input.closest('tr').querySelector('.save-btn');
            const originalText = button.textContent;
            button.textContent = '...';
            button.disabled = true;

            fetch(mtGradeConfig.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': mtGradeConfig.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    student_hemis_id: studentHemisId,
                    subject_id: mtGradeConfig.subjectId,
                    semester_code: mtGradeConfig.semesterCode,
                    grade: parseFloat(grade)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = 'OK!';
                    button.classList.remove('bg-blue-500');
                    button.classList.add('bg-green-500');
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('bg-green-500');
                        button.classList.add('bg-blue-500');
                        button.disabled = false;
                    }, 1500);
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.add('active');
        }

        function switchView(viewType) {
            document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-' + viewType).classList.add('active');

            if (viewType === 'compact') {
                document.getElementById('mz-compact-view')?.classList.remove('hidden');
                document.getElementById('mz-detailed-view')?.classList.add('hidden');
                document.getElementById('jb-compact-view')?.classList.remove('hidden');
                document.getElementById('jb-detailed-view')?.classList.add('hidden');
                document.getElementById('mt-compact-view')?.classList.remove('hidden');
                document.getElementById('mt-detailed-view')?.classList.add('hidden');
            } else {
                document.getElementById('mz-compact-view')?.classList.add('hidden');
                document.getElementById('mz-detailed-view')?.classList.remove('hidden');
                document.getElementById('jb-compact-view')?.classList.add('hidden');
                document.getElementById('jb-detailed-view')?.classList.remove('hidden');
                document.getElementById('mt-compact-view')?.classList.add('hidden');
                document.getElementById('mt-detailed-view')?.classList.remove('hidden');
            }
        }

        // Retake grade functionality - Excel-like inline editing
        let currentEditingCell = null;

        function makeEditable(cellDiv, gradeId) {
            // Prevent multiple edits at once
            if (currentEditingCell) {
                return;
            }

            currentEditingCell = cellDiv;
            const originalContent = cellDiv.innerHTML;

            // Create input field
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.max = '100';
            input.value = '';
            input.className = 'w-full text-center border border-blue-500 rounded px-1 py-0.5 focus:outline-none focus:ring-2 focus:ring-blue-300';
            input.style.width = '50px';
            input.style.height = '28px';

            // Replace cell content with input
            cellDiv.innerHTML = '';
            cellDiv.appendChild(input);
            input.focus();
            input.select();

            // Save on Enter key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveInlineGrade(gradeId, input.value, cellDiv, originalContent);
                } else if (e.key === 'Escape') {
                    // Cancel editing
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });

            // Save on blur (clicking outside)
            input.addEventListener('blur', function() {
                if (input.value.trim() !== '') {
                    saveInlineGrade(gradeId, input.value, cellDiv, originalContent);
                } else {
                    // Cancel if empty
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });
        }

        function makeEditableEmpty(cellDiv, studentHemisId, lessonDate, lessonPair, subjectId, semesterCode) {
            // Prevent multiple edits at once
            if (currentEditingCell) {
                return;
            }

            currentEditingCell = cellDiv;
            const originalContent = cellDiv.innerHTML;

            // Create input field
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.max = '100';
            input.value = '';
            input.className = 'w-full text-center border border-blue-500 rounded px-1 py-0.5 focus:outline-none focus:ring-2 focus:ring-blue-300';
            input.style.width = '50px';
            input.style.height = '28px';

            // Replace cell content with input
            cellDiv.innerHTML = '';
            cellDiv.appendChild(input);
            input.focus();
            input.select();

            // Save on Enter key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, input.value, cellDiv, originalContent);
                } else if (e.key === 'Escape') {
                    // Cancel editing
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });

            // Save on blur (clicking outside)
            input.addEventListener('blur', function() {
                if (input.value.trim() !== '') {
                    saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, input.value, cellDiv, originalContent);
                } else {
                    // Cancel if empty
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });
        }

        function saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, gradeValue, cellDiv, originalContent) {
            const gradeNum = parseFloat(gradeValue);

            if (isNaN(gradeNum) || gradeNum < 0 || gradeNum > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
                return;
            }

            // Show loading
            cellDiv.innerHTML = '<span class="text-gray-500">...</span>';

            fetch('{{ route("admin.journal.create-retake-grade") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    student_hemis_id: studentHemisId,
                    lesson_date: lessonDate,
                    lesson_pair_code: lessonPair,
                    subject_id: subjectId,
                    semester_code: semesterCode,
                    grade: gradeNum
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success with calculated grade
                    cellDiv.innerHTML = `<div class="flex items-center justify-center gap-1">
                        <span class="grade-retake font-medium">${Math.round(data.retake_grade)}</span>
                        <span class="text-green-600 text-xs" title="Retake bahosi qo'yilgan: ${data.percentage}%">✓</span>
                    </div>`;

                    // Show success notification briefly
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
                    notification.textContent = `Saqlandi: ${Math.round(data.retake_grade)} (${data.percentage}%)`;
                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    cellDiv.innerHTML = originalContent;
                }
                currentEditingCell = null;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
            });
        }

        function saveInlineGrade(gradeId, gradeValue, cellDiv, originalContent) {
            const gradeNum = parseFloat(gradeValue);

            if (isNaN(gradeNum) || gradeNum < 0 || gradeNum > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
                return;
            }

            // Show loading
            cellDiv.innerHTML = '<span class="text-gray-500">...</span>';

            fetch('{{ route("admin.journal.save-retake-grade") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    grade_id: gradeId,
                    grade: gradeNum
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success with calculated grade
                    cellDiv.innerHTML = `<div class="flex items-center justify-center gap-1">
                        <span class="grade-retake font-medium">${Math.round(data.retake_grade)}</span>
                        <span class="text-green-600 text-xs" title="Retake bahosi qo'yilgan: ${data.percentage}%">✓</span>
                    </div>`;

                    // Show success notification briefly
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
                    notification.textContent = `Saqlandi: ${Math.round(data.retake_grade)} (${data.percentage}%)`;
                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    cellDiv.innerHTML = originalContent;
                }
                currentEditingCell = null;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
            });
        }
    </script>
</x-app-layout>
