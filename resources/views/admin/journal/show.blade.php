<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.journal.index') }}" class="flex items-center justify-center w-10 h-10 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Jurnal tafsilotlari
            </h2>
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
    </style>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
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

            <!-- Info Panel (connected to tabs) -->
            <div class="p-4 bg-white shadow-sm">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Guruh:</span>
                        <span class="font-semibold text-blue-600 ml-1">{{ $group->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Fan:</span>
                        <span class="font-semibold text-gray-800 ml-1">{{ $subject->subject_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Semestr:</span>
                        <span class="font-semibold text-gray-800 ml-1">{{ $semester->name ?? $subject->semester_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Talabalar soni:</span>
                        <span class="font-semibold text-gray-800 ml-1">{{ $students->count() }}</span>
                    </div>
                </div>
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
                            <table class="min-w-full border-collapse border border-gray-300 journal-table">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th rowspan="2" class="px-1 py-2 text-xs font-medium text-gray-700 border border-gray-300 w-10">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-left text-gray-700 border border-gray-300 min-w-[250px]">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="15" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            JN o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            TMI o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            Oraliq nazorat (%)
                                        </th>
                                        <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Yakuniy nazorat (%)
                                        </th>
                                    </tr>
                                    <tr class="bg-gray-50">
                                        @for ($i = 1; $i <= 15; $i++)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300 min-w-[35px]">
                                            </th>
                                        @endfor
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            OSKI/OSI
                                        </th>
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            Komp. (yazma)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-1 py-2 text-sm text-center text-gray-900 border border-gray-300 w-10">
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
                            <table class="min-w-full border-collapse border border-gray-300 journal-table">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th rowspan="2" class="px-1 py-2 text-xs font-medium text-gray-700 border border-gray-300 w-10">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-left text-gray-700 border border-gray-300 min-w-[250px]">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="15" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            JN o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            TMI o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 bg-gray-50" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            Oraliq nazorat (%)
                                        </th>
                                        <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Yakuniy nazorat (%)
                                        </th>
                                    </tr>
                                    <tr class="bg-gray-50">
                                        @for ($i = 1; $i <= 15; $i++)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300 min-w-[35px]">
                                            </th>
                                        @endfor
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            OSKI/OSI
                                        </th>
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px;">
                                            Komp. (yazma)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-1 py-2 text-sm text-center text-gray-900 border border-gray-300 w-10">
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
                            <table class="min-w-full border-collapse border border-gray-300 journal-table">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th rowspan="2" class="px-1 py-2 text-xs font-medium text-gray-700 border border-gray-300 w-10">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-left text-gray-700 border border-gray-300 min-w-[250px]">
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
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300 min-w-[35px]">
                                            </th>
                                        @endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="px-1 py-2 text-sm text-center text-gray-900 border border-gray-300 w-10">
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
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Reset all tabs to inactive
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active');
                btn.classList.add('tab-inactive');
            });

            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Set active tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('tab-inactive');
            activeTab.classList.add('tab-active');
        }
    </script>
</x-app-layout>
