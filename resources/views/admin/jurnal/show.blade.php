<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Jurnal') }} - {{ $subject->subject_name }}
            </h2>
            <a href="{{ route('admin.jurnal.index') }}" class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm">
                <i class="fas fa-arrow-left mr-2"></i> Orqaga
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="px-4">
            <!-- Ma'lumotlar -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-blue-600 text-sm font-medium">Fan:</span>
                        <p class="text-gray-900">{{ $subject->subject_name }}</p>
                    </div>
                    <div>
                        <span class="text-blue-600 text-sm font-medium">Guruh:</span>
                        <p class="text-gray-900">{{ $group->name }}</p>
                    </div>
                    <div>
                        <span class="text-blue-600 text-sm font-medium">Semestr:</span>
                        <p class="text-gray-900">{{ $semester->name }}</p>
                    </div>
                    <div>
                        <span class="text-blue-600 text-sm font-medium">O'qituvchi:</span>
                        <p class="text-gray-900">{{ $teacherName }}</p>
                    </div>
                </div>
            </div>

            <!-- Jurnal jadvali -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase border-b border-r" style="min-width: 40px;">T/R</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase border-b border-r" style="min-width: 200px;">Talaba F.I.Sh</th>

                                @if($dates->count() > 0)
                                <th colspan="{{ $dates->count() }}" class="px-2 py-1 text-center text-xs font-medium text-gray-500 uppercase border-b border-r">
                                    Kunlik baholar
                                </th>
                                @endif

                                <th rowspan="2" class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase border-b border-r bg-blue-50" style="min-width: 50px;">JN</th>
                                <th rowspan="2" class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase border-b border-r bg-green-50" style="min-width: 50px;">MT</th>
                                <th rowspan="2" class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase border-b border-r bg-yellow-50" style="min-width: 50px;">ON</th>
                                <th rowspan="2" class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase border-b border-r bg-purple-50" style="min-width: 50px;">OSKI</th>
                                <th rowspan="2" class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase border-b bg-red-50" style="min-width: 50px;">Test</th>
                            </tr>
                            <tr>
                                @foreach($dates as $date)
                                <th class="px-1 py-1 text-center text-xs font-medium text-gray-500 border-b border-r" style="min-width: 35px;">
                                    <div style="writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg); height: 60px; display: flex; align-items: center; justify-content: center;">
                                        {{ $date->format('d.m') }}
                                    </div>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $student)
                                @php
                                    $data = $gradesData[$student->hemis_id] ?? null;
                                @endphp
                                <tr class="{{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                                    <td class="px-3 py-2 text-sm text-gray-500 border-r">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 border-r font-medium">{{ $student->full_name }}</td>

                                    @foreach($dates as $date)
                                        @php
                                            $dateKey = $date->format('Y-m-d');
                                            $grade = $data['daily'][$dateKey] ?? null;
                                            $isAbsent = $data['daily'][$dateKey . '_absent'] ?? false;
                                            $cellClass = '';
                                            if ($isAbsent) {
                                                $cellClass = 'bg-red-100 text-red-600 font-bold';
                                            } elseif ($grade !== null && $grade < 60) {
                                                $cellClass = 'bg-yellow-100 text-yellow-700';
                                            } elseif ($grade !== null && $grade >= 80) {
                                                $cellClass = 'bg-green-100 text-green-700';
                                            }
                                        @endphp
                                        <td class="px-1 py-2 text-center text-sm border-r {{ $cellClass }}">
                                            @if($isAbsent)
                                                Nb
                                            @else
                                                {{ $grade ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach

                                    <!-- JN -->
                                    <td class="px-2 py-2 text-center text-sm border-r bg-blue-50 font-medium {{ ($data['jn'] ?? 0) < 60 ? 'text-red-600' : (($data['jn'] ?? 0) >= 80 ? 'text-green-600' : '') }}">
                                        {{ $data['jn'] ?? 0 }}
                                    </td>

                                    <!-- MT -->
                                    <td class="px-2 py-2 text-center text-sm border-r bg-green-50 font-medium {{ ($data['mt'] ?? 0) < 60 ? 'text-red-600' : (($data['mt'] ?? 0) >= 80 ? 'text-green-600' : '') }}">
                                        {{ $data['mt'] ?? 0 }}
                                    </td>

                                    <!-- ON -->
                                    <td class="px-2 py-2 text-center text-sm border-r bg-yellow-50 font-medium {{ ($data['oraliq'] ?? 0) < 60 ? 'text-red-600' : (($data['oraliq'] ?? 0) >= 80 ? 'text-green-600' : '') }}">
                                        {{ $data['oraliq'] ?? 0 }}
                                    </td>

                                    <!-- OSKI -->
                                    <td class="px-2 py-2 text-center text-sm border-r bg-purple-50 font-medium {{ ($data['oski'] ?? 0) < 60 ? 'text-red-600' : (($data['oski'] ?? 0) >= 80 ? 'text-green-600' : '') }}">
                                        {{ $data['oski'] ?? 0 }}
                                    </td>

                                    <!-- Test -->
                                    <td class="px-2 py-2 text-center text-sm bg-red-50 font-medium {{ ($data['test'] ?? 0) < 60 ? 'text-red-600' : (($data['test'] ?? 0) >= 80 ? 'text-green-600' : '') }}">
                                        {{ $data['test'] ?? 0 }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 7 + $dates->count() }}" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-users text-4xl mb-3 block text-gray-300"></i>
                                        <p>Talabalar topilmadi</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Legend -->
                <div class="px-4 py-3 bg-gray-50 border-t flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center">
                        <span class="w-4 h-4 bg-green-100 rounded mr-1"></span>
                        <span class="text-gray-600">80+ ball</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-4 h-4 bg-yellow-100 rounded mr-1"></span>
                        <span class="text-gray-600">60 dan past</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-4 h-4 bg-red-100 rounded mr-1"></span>
                        <span class="text-gray-600">Nb - Kelmagan</span>
                    </div>
                    <div class="flex items-center ml-auto">
                        <span class="text-gray-500">Jami: {{ $students->count() }} ta talaba</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
