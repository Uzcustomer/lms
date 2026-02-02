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

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- Tabs -->
            <div class="mb-4 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                        class="tab-btn active whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                        Amaliyot
                    </button>
                    <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                        class="tab-btn whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Mustaqil ta'lim
                    </button>
                </nav>
            </div>

            <!-- Info Panel -->
            <div class="mb-4 p-4 bg-white rounded-lg shadow-sm">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Guruh:</span>
                        <span class="font-medium ml-1">{{ $group->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Fan:</span>
                        <span class="font-medium ml-1">{{ $subject->subject_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Semestr:</span>
                        <span class="font-medium ml-1">{{ $semester->name ?? $subject->semester_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Talabalar soni:</span>
                        <span class="font-medium ml-1">{{ $students->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Amaliyot Tab Content -->
            <div id="content-amaliyot" class="tab-content">
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        @if($students->isEmpty())
                            <div class="p-6 text-center text-gray-500">
                                <p>Bu guruhda talabalar mavjud emas.</p>
                            </div>
                        @else
                            <table class="min-w-full border-collapse border border-gray-300">
                                <thead>
                                    <!-- First header row -->
                                    <tr class="bg-gray-50">
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-gray-700 border border-gray-300 whitespace-nowrap">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-left text-gray-700 border border-gray-300 min-w-[250px]">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="10" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Davomat va joriy yil natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            JN o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            TMI o'rtacha (%)
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            Oraliq nazorat (%)
                                        </th>
                                        <th colspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Yakuniy nazorat (%)
                                        </th>
                                    </tr>
                                    <!-- Second header row (for date columns and yakuniy nazorat sub-columns) -->
                                    <tr class="bg-gray-50">
                                        <!-- Date columns - placeholder for now -->
                                        @for ($i = 1; $i <= 10; $i++)
                                            <th class="px-2 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300 min-w-[40px]">
                                                {{-- Kunlar shu yerda ko'rinadi --}}
                                            </th>
                                        @endfor
                                        <!-- Yakuniy nazorat sub-columns -->
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            OSKI/OSI
                                        </th>
                                        <th class="px-2 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            Komp. (yazma)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr class="hover:bg-gray-50">
                                            <!-- T/R -->
                                            <td class="px-3 py-2 text-sm text-center text-gray-900 border border-gray-300">
                                                {{ $index + 1 }}
                                            </td>
                                            <!-- F.I.SH. -->
                                            <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">
                                                {{ $student->full_name }}
                                            </td>
                                            <!-- Davomat - kunlar (placeholder for now) -->
                                            @for ($i = 1; $i <= 10; $i++)
                                                <td class="px-2 py-2 text-sm text-center text-gray-500 border border-gray-300">
                                                    {{-- Baholar shu yerda --}}
                                                </td>
                                            @endfor
                                            <!-- JN o'rtacha -->
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                <span class="text-blue-600 font-medium">{{ $student->jb_average ? round($student->jb_average, 0) : 0 }}</span>
                                            </td>
                                            <!-- TMI o'rtacha -->
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                <span class="text-blue-600 font-medium">{{ $student->mt_average ? round($student->mt_average, 0) : 0 }}</span>
                                            </td>
                                            <!-- Oraliq nazorat -->
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                {{-- ON qiymati --}}
                                            </td>
                                            <!-- OSKI/OSI -->
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                {{-- OSKI qiymati --}}
                                            </td>
                                            <!-- Komp. (yazma) -->
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300">
                                                {{-- Komp qiymati --}}
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
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        @if($students->isEmpty())
                            <div class="p-6 text-center text-gray-500">
                                <p>Bu guruhda talabalar mavjud emas.</p>
                            </div>
                        @else
                            <table class="min-w-full border-collapse border border-gray-300">
                                <thead>
                                    <!-- First header row -->
                                    <tr class="bg-gray-50">
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-gray-700 border border-gray-300 whitespace-nowrap">
                                            №<br>T/R
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-left text-gray-700 border border-gray-300 min-w-[250px]">
                                            Talabaning F.I.SH.
                                        </th>
                                        <th colspan="10" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300">
                                            Mustaqil ta'lim natijalari (baholash 100% hisobidan)
                                        </th>
                                        <th rowspan="2" class="px-3 py-2 text-xs font-medium text-center text-gray-700 border border-gray-300 whitespace-nowrap" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                            MT o'rtacha (%)
                                        </th>
                                    </tr>
                                    <!-- Second header row (for assignment columns) -->
                                    <tr class="bg-gray-50">
                                        @for ($i = 1; $i <= 10; $i++)
                                            <th class="px-2 py-2 text-xs font-medium text-center text-gray-600 border border-gray-300 min-w-[40px]">
                                                {{-- Topshiriqlar shu yerda --}}
                                            </th>
                                        @endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr class="hover:bg-gray-50">
                                            <!-- T/R -->
                                            <td class="px-3 py-2 text-sm text-center text-gray-900 border border-gray-300">
                                                {{ $index + 1 }}
                                            </td>
                                            <!-- F.I.SH. -->
                                            <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">
                                                {{ $student->full_name }}
                                            </td>
                                            <!-- Mustaqil ta'lim (placeholder) -->
                                            @for ($i = 1; $i <= 10; $i++)
                                                <td class="px-2 py-2 text-sm text-center text-gray-500 border border-gray-300">
                                                    {{-- Baholar --}}
                                                </td>
                                            @endfor
                                            <!-- MT o'rtacha -->
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
