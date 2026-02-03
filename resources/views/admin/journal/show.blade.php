<x-app-layout>
    <style>
        .journal-table {
            border: 1px solid #d1d5db;
        }
        .journal-table th,
        .journal-table td {
            border: 1px solid #d1d5db;
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
    </style>

    <div class="py-2">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Tabs and View Toggle -->
            <div class="mb-0 flex justify-between items-center">
                <nav class="flex space-x-4">
                    <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                        class="tab-btn px-2 py-1 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                        Amaliyot
                    </button>
                    <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                        class="tab-btn px-2 py-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Mustaqil ta'lim
                    </button>
                </nav>
                <div class="flex items-center">
                    <span class="text-xs text-gray-500 mr-2">Ko'rinish:</span>
                    <button id="view-compact" onclick="switchView('compact')" class="view-btn active">Ixcham</button>
                    <button id="view-detailed" onclick="switchView('detailed')" class="view-btn">Batafsil</button>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="py-2 bg-gray-50 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-1 text-sm">
                    <div>
                        <span class="text-gray-500">Guruh:</span>
                        <span class="font-medium text-blue-600 ml-1">{{ $group->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Fan:</span>
                        <span class="font-medium text-gray-900 ml-1">{{ $subject->subject_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Semestr:</span>
                        <span class="font-medium text-gray-900 ml-1">{{ $semester->name ?? $subject->semester_name }}</span>
                    </div>
                    <div class="ml-auto">
                        <span class="text-gray-500">Talabalar soni:</span>
                        <span class="font-medium text-gray-900 ml-1">{{ $students->count() }}</span>
                    </div>
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
                        <!-- Compact View (Ixcham) -->
                        <div id="jb-compact-view" class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($jbLessonDates) > 0)
                                            <th colspan="{{ count($jbLessonDates) }}" class="px-1 py-1 font-bold text-gray-700 text-center">Joriy nazorat (kunlik o'rtacha)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                    </tr>
                                    <tr>
                                        @forelse($jbLessonDates as $date)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 45px; writing-mode: vertical-rl; transform: rotate(180deg); height: 50px;">
                                                {{ \Carbon\Carbon::parse($date)->format('d.m.y') }}
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
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $dailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $jnAverage = count($dailyAverages) > 0
                                                ? round(array_sum($dailyAverages) / count($dailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailyAverages = [];
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $mtDailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $mtAverage = count($mtDailyAverages) > 0
                                                ? round(array_sum($mtDailyAverages) / count($mtDailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($jbLessonDates as $date)
                                                @php
                                                    $dayGrades = $studentJbGrades[$date] ?? [];
                                                    $dayAvg = isset($dailyAverages[$date]) ? $dailyAverages[$date] : null;
                                                    $gradesText = implode(', ', array_map(fn($g) => round($g, 0), $dayGrades));
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }}">
                                                    @if($dayAvg !== null)
                                                        <span class="text-gray-900 font-medium">{{ $dayAvg }}</span>
                                                        @if(count($dayGrades) > 1)
                                                            <span class="tooltip-content">{{ $gradesText }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $jnAverage }}</span></td>
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed View (Batafsil) -->
                        <div id="jb-detailed-view" class="overflow-x-auto hidden">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($jbColumns) > 0)
                                            <th colspan="{{ count($jbColumns) }}" class="px-1 py-1 font-bold text-gray-700 text-center">Joriy nazorat (har bir dars)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                    </tr>
                                    <tr>
                                        @forelse($jbColumns as $col)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 40px; writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                                {{ \Carbon\Carbon::parse($col['date'])->format('d.m') }}({{ $col['pair'] }})
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
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $dailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $jnAverage = count($dailyAverages) > 0
                                                ? round(array_sum($dailyAverages) / count($dailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailyAverages = [];
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $mtDailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $mtAverage = count($mtDailyAverages) > 0
                                                ? round(array_sum($mtDailyAverages) / count($mtDailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($jbColumns as $col)
                                                @php
                                                    $grade = $studentJbGrades[$col['date']][$col['pair']] ?? null;
                                                @endphp
                                                <td class="px-1 py-1 text-center">
                                                    @if($grade !== null)
                                                        <span class="text-gray-900 font-medium">{{ round($grade, 0) }}</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $jnAverage }}</span></td>
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
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
                        <!-- Compact View (Ixcham) -->
                        <div id="mt-compact-view" class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($mtLessonDates) > 0)
                                            <th colspan="{{ count($mtLessonDates) }}" class="px-1 py-1 font-bold text-gray-700 text-center">Mustaqil ta'lim (kunlik o'rtacha)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">MT</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                    </tr>
                                    <tr>
                                        @forelse($mtLessonDates as $date)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 45px; writing-mode: vertical-rl; transform: rotate(180deg); height: 50px;">
                                                {{ \Carbon\Carbon::parse($date)->format('d.m.y') }}
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
                                            $dailyAverages = [];
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $dailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $mtAverage = count($dailyAverages) > 0
                                                ? round(array_sum($dailyAverages) / count($dailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($mtLessonDates as $date)
                                                @php
                                                    $dayGrades = $studentMtGrades[$date] ?? [];
                                                    $dayAvg = isset($dailyAverages[$date]) ? $dailyAverages[$date] : null;
                                                    $gradesText = implode(', ', array_map(fn($g) => round($g, 0), $dayGrades));
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }}">
                                                    @if($dayAvg !== null)
                                                        <span class="text-gray-900 font-medium">{{ $dayAvg }}</span>
                                                        @if(count($dayGrades) > 1)
                                                            <span class="tooltip-content">{{ $gradesText }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $mtAverage }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed View (Batafsil) -->
                        <div id="mt-detailed-view" class="overflow-x-auto hidden">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($mtColumns) > 0)
                                            <th colspan="{{ count($mtColumns) }}" class="px-1 py-1 font-bold text-gray-700 text-center">Mustaqil ta'lim (har bir dars)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-1 font-bold text-gray-700 text-center">MT</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                    </tr>
                                    <tr>
                                        @forelse($mtColumns as $col)
                                            <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 40px; writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                                {{ \Carbon\Carbon::parse($col['date'])->format('d.m') }}({{ $col['pair'] }})
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
                                            $dailyAverages = [];
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                if (count($dayGrades) > 0) {
                                                    $avg = array_sum($dayGrades) / count($dayGrades);
                                                    $dailyAverages[$date] = round($avg, 0, PHP_ROUND_HALF_UP);
                                                }
                                            }
                                            $mtAverage = count($dailyAverages) > 0
                                                ? round(array_sum($dailyAverages) / count($dailyAverages), 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs">{{ $student->full_name }}</td>
                                            @forelse($mtColumns as $col)
                                                @php
                                                    $grade = $studentMtGrades[$col['date']][$col['pair']] ?? null;
                                                @endphp
                                                <td class="px-1 py-1 text-center">
                                                    @if($grade !== null)
                                                        <span class="text-gray-900 font-medium">{{ round($grade, 0) }}</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="text-blue-600 font-bold">{{ $mtAverage }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }

        function switchView(viewType) {
            document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-' + viewType).classList.add('active');

            if (viewType === 'compact') {
                document.getElementById('jb-compact-view').classList.remove('hidden');
                document.getElementById('jb-detailed-view').classList.add('hidden');
                document.getElementById('mt-compact-view').classList.remove('hidden');
                document.getElementById('mt-detailed-view').classList.add('hidden');
            } else {
                document.getElementById('jb-compact-view').classList.add('hidden');
                document.getElementById('jb-detailed-view').classList.remove('hidden');
                document.getElementById('mt-compact-view').classList.add('hidden');
                document.getElementById('mt-detailed-view').classList.remove('hidden');
            }
        }
    </script>
</x-app-layout>
