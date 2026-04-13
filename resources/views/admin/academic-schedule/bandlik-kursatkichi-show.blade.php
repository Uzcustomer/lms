@php
    $backRoute = request()->routeIs('admin.*')
        ? 'admin.academic-schedule.bandlik-kursatkichi'
        : 'teacher.academic-schedule.bandlik-kursatkichi';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route($backRoute) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Orqaga
                </a>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Bandlik ko'rsatkichi — {{ $date->format('d.m.Y') }}
                    <span class="text-sm text-gray-500 font-normal ml-1">({{ $date->isoFormat('dddd') }})</span>
                </h2>
            </div>
            <div class="text-sm text-gray-600">
                Jami komputerlar: <span class="font-bold text-indigo-700">{{ $totalComputers }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">

                @if($slots->isEmpty())
                    <div class="text-center py-12 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="mt-3 text-sm text-yellow-800">Bu kunda belgilangan test vaqti topilmadi.</p>
                    </div>
                @else
                    {{-- Xulosa kartalar --}}
                    @php
                        $totalSlots = $slots->count();
                        $overflowSlots = $slots->where('overflow', '>', 0)->count();
                        $fullSlots = $slots->where('usage_percent', '>=', 100)->count();
                        $totalStudents = $slots->sum('occupied');
                    @endphp
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <div class="text-xs text-indigo-700 font-medium">Vaqt slotlari</div>
                            <div class="text-2xl font-bold text-indigo-900">{{ $totalSlots }}</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="text-xs text-blue-700 font-medium">Jami talabalar</div>
                            <div class="text-2xl font-bold text-blue-900">{{ $totalStudents }}</div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="text-xs text-yellow-700 font-medium">To'la band slotlar</div>
                            <div class="text-2xl font-bold text-yellow-900">{{ $fullSlots - $overflowSlots }}</div>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <div class="text-xs text-red-700 font-medium">Sig'imdan ortiq</div>
                            <div class="text-2xl font-bold text-red-900">{{ $overflowSlots }}</div>
                        </div>
                    </div>

                    {{-- Jadval --}}
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Vaqt</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">YN turi</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guruhlar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Talabalar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Band / Jami</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Bo'sh</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Bandlik %</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($slots as $i => $slot)
                                    @php
                                        if ($slot['overflow'] > 0) {
                                            $rowBg = 'bg-red-50';
                                            $statusLabel = "Sig'imdan ortiq";
                                            $statusClass = 'bg-red-100 text-red-800';
                                        } elseif ($slot['usage_percent'] >= 100) {
                                            $rowBg = 'bg-yellow-50';
                                            $statusLabel = "To'la band";
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                        } elseif ($slot['usage_percent'] >= 75) {
                                            $rowBg = '';
                                            $statusLabel = 'Yuqori bandlik';
                                            $statusClass = 'bg-orange-100 text-orange-800';
                                        } else {
                                            $rowBg = '';
                                            $statusLabel = 'Normal';
                                            $statusClass = 'bg-green-100 text-green-800';
                                        }

                                        if ($slot['usage_percent'] >= 100) {
                                            $barColor = 'bg-red-500';
                                        } elseif ($slot['usage_percent'] >= 75) {
                                            $barColor = 'bg-orange-500';
                                        } elseif ($slot['usage_percent'] >= 50) {
                                            $barColor = 'bg-yellow-500';
                                        } else {
                                            $barColor = 'bg-green-500';
                                        }
                                    @endphp
                                    <tr class="{{ $rowBg }} hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded text-sm font-semibold bg-blue-100 text-blue-800">
                                                {{ $slot['time'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($slot['yn_type'] === 'OSKI')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">OSKI</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800">Test</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col gap-1.5">
                                                @foreach($slot['groups'] as $grp)
                                                    <div class="inline-flex items-center gap-2 px-2 py-1 rounded bg-gray-50 border border-gray-200">
                                                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold">
                                                            {{ $grp['student_count'] }}
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs whitespace-nowrap">{{ $grp['group_name'] }}</span>
                                                        @if(!empty($grp['subject_name']))
                                                            <span class="text-gray-400 text-xs">—</span>
                                                            <span class="text-gray-700 text-xs">{{ $grp['subject_name'] }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center font-semibold text-gray-900">{{ $slot['occupied'] }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-semibold text-indigo-700">{{ $slot['occupied'] }}</span>
                                            <span class="text-gray-400">/</span>
                                            <span class="text-gray-500">{{ $totalComputers }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($slot['overflow'] > 0)
                                                <span class="text-red-700 font-semibold">-{{ $slot['overflow'] }} yetmaydi</span>
                                            @else
                                                <span class="font-semibold text-green-700">{{ $slot['free'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden min-w-[60px]">
                                                    <div class="{{ $barColor }} h-full" style="width: {{ min(100, $slot['usage_percent']) }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-700 w-12 text-right">{{ $slot['usage_percent'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
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
</x-app-layout>
