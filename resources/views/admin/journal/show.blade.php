@php
use \Illuminate\Support\Carbon;
@endphp

<x-app-layout>
    <style>
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .journal-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .journal-table tbody tr:hover td {
            background-color: #e0f2fe !important;
        }

        /* Retake-eligible cells */
        .retake-cell {
            cursor: pointer;
            min-height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background-color 0.15s;
        }
        .retake-cell:hover {
            background-color: #eff6ff;
        }

        /* Retake input */
        .retake-input {
            width: 44px;
            text-align: center;
            font-size: 13px;
            border: 1px solid #93c5fd;
            border-radius: 4px;
            padding: 3px 2px;
            outline: none;
        }
        .retake-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        /* Retaked value display */
        .retaked-display {
            display: flex;
            align-items: center;
            gap: 3px;
            justify-content: center;
        }
        .retaked-original {
            text-decoration: line-through;
            color: #9ca3af;
            font-size: 11px;
        }
        .retaked-new {
            color: #2563eb;
            font-weight: 600;
        }

        /* Date header vertical text */
        .date-header {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.25s, transform 0.25s;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .toast.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Tabs -->
            <div class="mb-0">
                <nav class="flex space-x-6">
                    <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                        class="tab-btn px-1 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                        Amaliyot
                    </button>
                    <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                        class="tab-btn px-1 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Mustaqil ta'lim
                    </button>
                </nav>
            </div>

            <!-- Info Panel -->
            <div class="py-4 bg-gray-50 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-x-12 gap-y-2 text-sm">
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
                        <div class="p-6 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="width: 50px;">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="min-width: 280px;">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="{{ count($amaliyotDates) }}" class="px-3 py-2 text-xs font-medium text-center text-gray-500 border-b border-gray-200">
                                            Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 100px;">
                                            JN o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 100px;">
                                            MT o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 100px;">
                                            Oraliq nazorat (%)
                                        </th>
                                        <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-500 border-b border-gray-200">
                                            Yakuniy nazorat (%)
                                        </th>
                                    </tr>
                                    <tr>
                                        @foreach ($amaliyotDates as $date)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-400 border-b border-gray-200" style="min-width: 36px;">
                                                <div class="date-header">{{ $date }}</div>
                                            </th>
                                        @endforeach
                                        <th class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            OSKI
                                        </th>
                                        <th class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            Test
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-3 py-3 text-sm text-gray-900 border-b border-gray-100">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900 border-b border-gray-100 uppercase">
                                                {{ $student->full_name }}
                                            </td>
                                            @foreach ($amaliyotDates as $date)
                                                @php
                                                    $rec = $amaliyotMap[(string)$student->hemis_id][$date] ?? null;
                                                    $canRetake = false;
                                                    $isRetaked = false;
                                                    $isNB = false;
                                                    $gradeVal = null;

                                                    if ($rec) {
                                                        $gradeVal = $rec->grade !== null ? (float) $rec->grade : null;

                                                        if ($rec->retake_grade !== null) {
                                                            $isRetaked = true;
                                                        } else {
                                                            if ($rec->reason === 'absent' && ($gradeVal === null || $gradeVal == 0)) {
                                                                $isNB = true;
                                                            }
                                                            if (($gradeVal === null || $gradeVal < 60) && $rec->status !== 'closed') {
                                                                $lessonDate = Carbon::parse($date);
                                                                $isAdmin = auth()->user()->hasRole('admin');
                                                                if ($deadlineDays > 0) {
                                                                    $canRetake = $isAdmin || $lessonDate->copy()->addDays($deadlineDays)->isAfter(Carbon::now());
                                                                } else {
                                                                    $canRetake = $isAdmin;
                                                                }
                                                            }
                                                        }
                                                    }
                                                @endphp

                                                <td class="px-1 py-2 text-sm text-center border-b border-gray-100"
                                                    @if ($canRetake)
                                                        onclick="startRetakeEdit(this, {{ $rec->id }}, {{ $gradeVal ?? 0 }})"
                                                    @endif>
                                                    @if ($rec)
                                                        @if ($isRetaked)
                                                            <div class="retaked-display">
                                                                <span class="retaked-original">{{ round($gradeVal ?? 0) }}</span>
                                                                <span class="retaked-new">{{ round((float)$rec->retake_grade) }}</span>
                                                            </div>
                                                        @elseif ($canRetake)
                                                            <div class="retake-cell">
                                                                @if ($isNB)
                                                                    <span class="text-orange-500 italic text-xs">NB</span>
                                                                @else
                                                                    <span class="text-red-500">{{ round($gradeVal) }}</span>
                                                                @endif
                                                            </div>
                                                        @elseif ($isNB)
                                                            <span class="text-gray-400 italic text-xs">NB</span>
                                                        @elseif ($gradeVal !== null && $gradeVal < 60)
                                                            <span class="text-red-500">{{ round($gradeVal) }}</span>
                                                        @elseif ($gradeVal !== null)
                                                            <span>{{ round($gradeVal) }}</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-blue-600 font-medium">{{ $student->jb_average ? round($student->jb_average, 0) : 0 }}</span>
                                            </td>
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                            </td>
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-gray-900">{{ $student->on_average ? round($student->on_average, 0) : '' }}</span>
                                            </td>
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-gray-900">{{ $student->oski_average ? round($student->oski_average, 0) : '' }}</span>
                                            </td>
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-gray-900">{{ $student->test_average ? round($student->test_average, 0) : '' }}</span>
                                            </td>
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
                        <div class="p-6 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="width: 50px;">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="min-width: 280px;">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="{{ count($mtDates) }}" class="px-3 py-2 text-xs font-medium text-center text-gray-500 border-b border-gray-200">
                                            Mustaqil ta'lim natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 100px;">
                                            MT o'rtacha (%)
                                        </th>
                                    </tr>
                                    <tr>
                                        @foreach ($mtDates as $date)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-400 border-b border-gray-200" style="min-width: 36px;">
                                                <div class="date-header">{{ $date }}</div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-3 py-3 text-sm text-gray-900 border-b border-gray-100">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900 border-b border-gray-100 uppercase">
                                                {{ $student->full_name }}
                                            </td>
                                            @foreach ($mtDates as $date)
                                                @php
                                                    $rec = $mtMap[(string)$student->hemis_id][$date] ?? null;
                                                    $canRetake = false;
                                                    $isRetaked = false;
                                                    $isNB = false;
                                                    $gradeVal = null;

                                                    if ($rec) {
                                                        $gradeVal = $rec->grade !== null ? (float) $rec->grade : null;

                                                        if ($rec->retake_grade !== null) {
                                                            $isRetaked = true;
                                                        } else {
                                                            if ($rec->reason === 'absent' && ($gradeVal === null || $gradeVal == 0)) {
                                                                $isNB = true;
                                                            }
                                                            if (($gradeVal === null || $gradeVal < 60) && $rec->status !== 'closed') {
                                                                $lessonDate = Carbon::parse($date);
                                                                $isAdmin = auth()->user()->hasRole('admin');
                                                                if ($deadlineDays > 0) {
                                                                    $canRetake = $isAdmin || $lessonDate->copy()->addDays($deadlineDays)->isAfter(Carbon::now());
                                                                } else {
                                                                    $canRetake = $isAdmin;
                                                                }
                                                            }
                                                        }
                                                    }
                                                @endphp

                                                <td class="px-1 py-2 text-sm text-center border-b border-gray-100"
                                                    @if ($canRetake)
                                                        onclick="startRetakeEdit(this, {{ $rec->id }}, {{ $gradeVal ?? 0 }})"
                                                    @endif>
                                                    @if ($rec)
                                                        @if ($isRetaked)
                                                            <div class="retaked-display">
                                                                <span class="retaked-original">{{ round($gradeVal ?? 0) }}</span>
                                                                <span class="retaked-new">{{ round((float)$rec->retake_grade) }}</span>
                                                            </div>
                                                        @elseif ($canRetake)
                                                            <div class="retake-cell">
                                                                @if ($isNB)
                                                                    <span class="text-orange-500 italic text-xs">NB</span>
                                                                @else
                                                                    <span class="text-red-500">{{ round($gradeVal) }}</span>
                                                                @endif
                                                            </div>
                                                        @elseif ($isNB)
                                                            <span class="text-gray-400 italic text-xs">NB</span>
                                                        @elseif ($gradeVal !== null && $gradeVal < 60)
                                                            <span class="text-red-500">{{ round($gradeVal) }}</span>
                                                        @elseif ($gradeVal !== null)
                                                            <span>{{ round($gradeVal) }}</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-2 py-3 text-sm text-center border-b border-gray-100">
                                                <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                            </td>
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

    <!-- Toast notification -->
    <div id="toast" class="toast"></div>

    <script>
        var RETAKE_URL_BASE = '{{ url("/admin/journal/retake-grade") }}';
        var CSRF_TOKEN = '{{ csrf_token() }}';

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.add('hidden');
            });
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById('content-' + tabName).classList.remove('hidden');
            var activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }

        function startRetakeEdit(cell, gradeId, originalGrade) {
            cell.setAttribute('data-original-html', cell.innerHTML);
            cell.setAttribute('data-original-grade', originalGrade);
            cell.innerHTML =
                '<input type="number" class="retake-input" min="0" max="100"' +
                ' data-grade-id="' + gradeId + '" autofocus>';
            var input = cell.querySelector('input');
            input.addEventListener('blur', function () {
                saveRetakeGrade(this);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
                if (e.key === 'Escape') { cancelRetakeEdit(this); }
            });
            input.focus();
        }

        function cancelRetakeEdit(input) {
            var cell = input.parentElement;
            cell.innerHTML = cell.getAttribute('data-original-html');
        }

        function saveRetakeGrade(input) {
            var cell = input.parentElement;
            var gradeId = input.dataset.gradeId;
            var grade = input.value.trim();
            var originalGrade = cell.getAttribute('data-original-grade');
            var originalHtml = cell.getAttribute('data-original-html');

            if (grade === '') {
                cancelRetakeEdit(input);
                return;
            }

            cell.innerHTML = '<span style="color:#6b7280;font-style:italic;font-size:11px;">...</span>';

            fetch(RETAKE_URL_BASE + '/' + gradeId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ grade: grade })
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    cell.innerHTML =
                        '<div class="retaked-display">' +
                        '<span class="retaked-original">' + originalGrade + '</span>' +
                        '<span class="retaked-new">' + Math.round(data.retake_grade) + '</span>' +
                        '</div>';
                    cell.removeAttribute('onclick');
                    showToast('Baho muvaffaqiyatli saqlanindi.', 'success');
                } else {
                    cell.innerHTML = originalHtml;
                    showToast(data.error, 'error');
                }
            })
            .catch(function () {
                cell.innerHTML = originalHtml;
                showToast('Xato yuzaga keldi. Qarayting.', 'error');
            });
        }

        function showToast(message, type) {
            var toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(function () {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</x-app-layout>
