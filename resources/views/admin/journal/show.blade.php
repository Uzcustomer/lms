<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.journal.index') }}" class="flex items-center justify-center w-8 h-8 text-gray-600 hover:text-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-800">Jurnal tafsilotlari</h2>
        </div>
    </x-slot>

    <style>
        /* Zebra striping */
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .journal-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        /* Hover effect for entire row */
        .journal-table tbody tr:hover td {
            background-color: #e5e7eb !important;
        }
        /* Tab styling */
        .tab-inactive {
            background-color: #e5e7eb;
            color: #6b7280;
        }
        .tab-inactive:hover {
            background-color: #d1d5db;
        }
        .tab-active {
            background-color: #ffffff;
            color: #2563eb;
            font-weight: 600;
        }
        /* Auto-fit table */
        .journal-table {
            width: auto;
        }
        .journal-table th, .journal-table td {
            white-space: nowrap;
        }
    </style>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="flex gap-6">
                <!-- Main Content -->
                <div class="flex-1">
                    <!-- Tabs -->
                    <div class="flex rounded-t-lg overflow-hidden shadow-sm">
                        <button id="tab-maruza" onclick="switchTab('maruza')"
                            class="tab-btn flex-1 py-3 px-4 text-center text-sm transition-colors tab-inactive">
                            Ma'ruza
                        </button>
                        <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                            class="tab-btn flex-1 py-3 px-4 text-center text-sm transition-colors tab-active">
                            Amaliyot
                        </button>
                        <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                            class="tab-btn flex-1 py-3 px-4 text-center text-sm transition-colors tab-inactive">
                            Mustaqil ta'lim
                        </button>
                    </div>

                    <!-- Ma'ruza Tab Content -->
                    <div id="content-maruza" class="tab-content hidden">
                        <div class="overflow-hidden bg-white shadow-xl rounded-b-lg">
                            <div class="overflow-x-auto">
                                @if($students->isEmpty())
                                    <div class="p-6 text-center text-gray-500">
                                        <p>Bu guruhda talabalar mavjud emas.</p>
                                    </div>
                                @else
                                    <table class="border-collapse border border-gray-300 journal-table">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    №<br>T/R
                                                </th>
                                                <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Talabaning F.I.SH.
                                                </th>
                                                <th colspan="15" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    JN o'rtacha (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    MT o'rtacha (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Oraliq nazorat (%)
                                                </th>
                                                <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Yakuniy nazorat (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Davomat (%)
                                                </th>
                                            </tr>
                                            <tr class="bg-gray-50">
                                                @for ($i = 1; $i <= 15; $i++)
                                                    <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300">
                                                    </th>
                                                @endfor
                                                <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    OSKI
                                                </th>
                                                <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Test
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($students as $index => $student)
                                                <tr>
                                                    <td class="px-2 py-2 text-sm text-center text-gray-900 border border-gray-300">
                                                        {{ $index + 1 }}
                                                    </td>
                                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300 uppercase">
                                                        {{ $student->full_name }}
                                                    </td>
                                                    @for ($i = 1; $i <= 15; $i++)
                                                        <td class="px-1 py-2 text-sm text-center text-gray-500 border border-gray-300">
                                                        </td>
                                                    @endfor
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                        <span class="text-blue-600 font-medium">{{ $student->jb_average ? round($student->jb_average, 0) : 0 }}</span>
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                        <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Amaliyot Tab Content -->
                    <div id="content-amaliyot" class="tab-content">
                        <div class="overflow-hidden bg-white shadow-xl rounded-b-lg">
                            <div class="overflow-x-auto">
                                @if($students->isEmpty())
                                    <div class="p-6 text-center text-gray-500">
                                        <p>Bu guruhda talabalar mavjud emas.</p>
                                    </div>
                                @else
                                    <table class="border-collapse border border-gray-300 journal-table">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    №<br>T/R
                                                </th>
                                                <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Talabaning F.I.SH.
                                                </th>
                                                <th colspan="15" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    JN o'rtacha (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    MT o'rtacha (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Oraliq nazorat (%)
                                                </th>
                                                <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Yakuniy nazorat (%)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Davomat (%)
                                                </th>
                                            </tr>
                                            <tr class="bg-gray-50">
                                                @for ($i = 1; $i <= 15; $i++)
                                                    <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300">
                                                    </th>
                                                @endfor
                                                <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    OSKI
                                                </th>
                                                <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    Test
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($students as $index => $student)
                                                <tr>
                                                    <td class="px-2 py-2 text-sm text-center text-gray-900 border border-gray-300">
                                                        {{ $index + 1 }}
                                                    </td>
                                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300 uppercase">
                                                        {{ $student->full_name }}
                                                    </td>
                                                    @for ($i = 1; $i <= 15; $i++)
                                                        <td class="px-1 py-2 text-sm text-center text-gray-500 border border-gray-300">
                                                        </td>
                                                    @endfor
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                        <span class="text-blue-600 font-medium">{{ $student->jb_average ? round($student->jb_average, 0) : 0 }}</span>
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                        <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Mustaqil ta'lim Tab Content -->
                    <div id="content-mustaqil" class="tab-content hidden">
                        <div class="overflow-hidden bg-white shadow-xl rounded-b-lg">
                            <div class="overflow-x-auto">
                                @if($students->isEmpty())
                                    <div class="p-6 text-center text-gray-500">
                                        <p>Bu guruhda talabalar mavjud emas.</p>
                                    </div>
                                @else
                                    <table class="border-collapse border border-gray-300 journal-table">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    №<br>T/R
                                                </th>
                                                <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Talabaning F.I.SH.
                                                </th>
                                                <th colspan="15" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                                    Mustaqil ta'lim natijalari (baholash 100% hisobidan)
                                                </th>
                                                <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                                    MT o'rtacha (%)
                                                </th>
                                            </tr>
                                            <tr class="bg-gray-50">
                                                @for ($i = 1; $i <= 15; $i++)
                                                    <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300">
                                                    </th>
                                                @endfor
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($students as $index => $student)
                                                <tr>
                                                    <td class="px-2 py-2 text-sm text-center text-gray-900 border border-gray-300">
                                                        {{ $index + 1 }}
                                                    </td>
                                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300 uppercase">
                                                        {{ $student->full_name }}
                                                    </td>
                                                    @for ($i = 1; $i <= 15; $i++)
                                                        <td class="px-1 py-2 text-sm text-center text-gray-500 border border-gray-300">
                                                        </td>
                                                    @endfor
                                                    <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                        <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="w-72 flex-shrink-0">
                    <div class="bg-white rounded-lg shadow-sm p-4 sticky top-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Jurnal ma'lumotlari</h3>

                        <!-- Guruh Filter -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Guruh</label>
                            <select id="group-filter" onchange="changeGroup(this.value)"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                @foreach($availableGroups as $g)
                                    <option value="{{ $g->id }}" {{ $g->id == $group->id ? 'selected' : '' }}>
                                        {{ $g->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Fan Filter -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Fan</label>
                            <select id="subject-filter" onchange="changeSubject(this.value)"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                @foreach($availableSubjects as $s)
                                    <option value="{{ $s->subject_id }}" {{ $s->subject_id == $subject->subject_id ? 'selected' : '' }}>
                                        {{ $s->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Semestr (Read-only) -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Semestr</label>
                            <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-md text-gray-700">
                                {{ $semester->name ?? $subject->semester_name }}
                            </div>
                        </div>

                        <!-- Talabalar soni -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Talabalar soni</label>
                            <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-md">
                                <span class="text-blue-600 font-semibold">{{ $students->count() }}</span>
                            </div>
                        </div>

                        <!-- O'quv yili -->
                        @if($curriculum)
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">O'quv yili</label>
                            <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-md text-gray-700">
                                {{ $curriculum->education_year_name }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const currentGroupId = {{ $group->id }};
        const currentSubjectId = {{ $subject->subject_id }};
        const currentSemesterCode = '{{ $subject->semester_code }}';

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active');
                btn.classList.add('tab-inactive');
            });

            document.getElementById('content-' + tabName).classList.remove('hidden');

            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('tab-inactive');
            activeTab.classList.add('tab-active');
        }

        function changeGroup(groupId) {
            if (groupId != currentGroupId) {
                window.location.href = `/admin/journal/show/${groupId}/${currentSubjectId}/${currentSemesterCode}`;
            }
        }

        function changeSubject(subjectId) {
            if (subjectId != currentSubjectId) {
                window.location.href = `/admin/journal/show/${currentGroupId}/${subjectId}/${currentSemesterCode}`;
            }
        }
    </script>
</x-app-layout>
