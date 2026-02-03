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
    </style>

    <div class="py-2">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Tabs -->
            <div class="mb-0">
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
                        <div class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">
                                            T/R
                                        </th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 200px;">
                                            Talabaning F.I.SH.
                                        </th>
                                        @if(count($jbLessonDates) > 0)
                                            <th colspan="{{ count($jbLessonDates) }}" class="px-2 py-1 font-bold text-gray-700 text-center">
                                                Joriy nazorat natijalari
                                            </th>
                                        @else
                                            <th colspan="1" class="px-2 py-1 font-bold text-gray-700 text-center">
                                                Joriy nazorat
                                            </th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 60px;">
                                            JN %
                                        </th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 60px;">
                                            MT %
                                        </th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 60px;">
                                            ON %
                                        </th>
                                        <th colspan="2" class="px-2 py-1 font-bold text-gray-700 text-center">
                                            Yakuniy nazorat
                                        </th>
                                    </tr>
                                    <tr>
                                        @if(count($jbLessonDates) > 0)
                                            @foreach($jbLessonDates as $lessonDate)
                                                <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 50px; writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                                    {{ \Carbon\Carbon::parse($lessonDate)->format('d.m.y') }}
                                                </th>
                                            @endforeach
                                        @else
                                            <th class="px-1 py-1 font-bold text-gray-400 text-center" style="min-width: 30px;">
                                                -
                                            </th>
                                        @endif
                                        <th class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                            OSKI
                                        </th>
                                        <th class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                            Test
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-2 py-1 text-gray-900 uppercase">
                                                {{ $student->full_name }}
                                            </td>
                                            @if(count($jbLessonDates) > 0)
                                                @foreach($jbLessonDates as $lessonDate)
                                                    <td class="px-1 py-1 text-center">
                                                        @php
                                                            $grade = isset($jbGrades[$student->hemis_id][$lessonDate]) ? $jbGrades[$student->hemis_id][$lessonDate] : null;
                                                        @endphp
                                                        @if($grade !== null)
                                                            <span class="text-gray-900 font-medium">{{ round($grade, 0) }}</span>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @else
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endif
                                            <td class="px-1 py-1 text-center">
                                                <span class="text-blue-600 font-bold">{{ $student->jb_average ? round($student->jb_average, 0) : 0 }}</span>
                                            </td>
                                            <td class="px-1 py-1 text-center">
                                                <span class="text-blue-600 font-bold">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                            </td>
                                            <td class="px-1 py-1 text-center">
                                                <span class="text-gray-900">{{ $student->on_average ? round($student->on_average, 0) : '' }}</span>
                                            </td>
                                            <td class="px-1 py-1 text-center">
                                                <span class="text-gray-900">{{ $student->oski_average ? round($student->oski_average, 0) : '' }}</span>
                                            </td>
                                            <td class="px-1 py-1 text-center">
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
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="journal-table w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">
                                            T/R
                                        </th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-left align-middle" style="min-width: 200px;">
                                            Talabaning F.I.SH.
                                        </th>
                                        @if(count($mtLessonDates) > 0)
                                            <th colspan="{{ count($mtLessonDates) }}" class="px-2 py-1 font-bold text-gray-700 text-center">
                                                Mustaqil ta'lim natijalari
                                            </th>
                                        @else
                                            <th colspan="1" class="px-2 py-1 font-bold text-gray-700 text-center">
                                                Mustaqil ta'lim
                                            </th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 60px;">
                                            MT %
                                        </th>
                                    </tr>
                                    <tr>
                                        @if(count($mtLessonDates) > 0)
                                            @foreach($mtLessonDates as $lessonDate)
                                                <th class="px-1 py-1 font-bold text-gray-600 text-center" style="min-width: 50px; writing-mode: vertical-rl; transform: rotate(180deg); height: 55px;">
                                                    {{ \Carbon\Carbon::parse($lessonDate)->format('d.m.y') }}
                                                </th>
                                            @endforeach
                                        @else
                                            <th class="px-1 py-1 font-bold text-gray-400 text-center" style="min-width: 30px;">
                                                -
                                            </th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-2 py-1 text-gray-900 uppercase">
                                                {{ $student->full_name }}
                                            </td>
                                            @if(count($mtLessonDates) > 0)
                                                @foreach($mtLessonDates as $lessonDate)
                                                    <td class="px-1 py-1 text-center">
                                                        @php
                                                            $grade = isset($mtGrades[$student->hemis_id][$lessonDate]) ? $mtGrades[$student->hemis_id][$lessonDate] : null;
                                                        @endphp
                                                        @if($grade !== null)
                                                            <span class="text-gray-900 font-medium">{{ round($grade, 0) }}</span>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @else
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endif
                                            <td class="px-1 py-1 text-center">
                                                <span class="text-blue-600 font-bold">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
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

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
    </script>
</x-app-layout>
