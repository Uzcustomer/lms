<x-app-layout>
    <style>
        /* Zebra striping */
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .journal-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        /* Hover effect for entire row */
        .journal-table tbody tr:hover td {
            background-color: #e0f2fe !important;
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
                                    <!-- First header row -->
                                    <tr>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="width: 50px;">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-3 text-xs font-medium text-gray-500 border-b border-gray-200 text-left align-bottom" style="min-width: 280px;">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="10" class="px-3 py-2 text-xs font-medium text-center text-gray-500 border-b border-gray-200">
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
                                    <!-- Second header row -->
                                    <tr>
                                        @for ($i = 1; $i <= 10; $i++)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-400 border-b border-gray-200" style="min-width: 36px;">
                                                {{-- Sana --}}
                                            </th>
                                        @endfor
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
                                            @for ($i = 1; $i <= 10; $i++)
                                                <td class="px-1 py-3 text-sm text-center text-gray-500 border-b border-gray-100">
                                                    {{-- Baho --}}
                                                </td>
                                            @endfor
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
                                        <th colspan="10" class="px-3 py-2 text-xs font-medium text-center text-gray-500 border-b border-gray-200">
                                            Mustaqil ta'lim natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-2 py-2 text-xs font-medium text-gray-500 border-b border-gray-200 align-bottom" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 100px;">
                                            MT o'rtacha (%)
                                        </th>
                                    </tr>
                                    <tr>
                                        @for ($i = 1; $i <= 10; $i++)
                                            <th class="px-1 py-2 text-xs font-medium text-center text-gray-400 border-b border-gray-200" style="min-width: 36px;">
                                                {{-- Topshiriq --}}
                                            </th>
                                        @endfor
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
                                            @for ($i = 1; $i <= 10; $i++)
                                                <td class="px-1 py-3 text-sm text-center text-gray-500 border-b border-gray-100">
                                                    {{-- Baho --}}
                                                </td>
                                            @endfor
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

    <script>
        function switchTab(tabName) {
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
    </script>
</x-app-layout>
