<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Bandlik ko'rsatkichi
            </h2>
            <div class="text-sm text-gray-600">
                Jami komputerlar: <span class="font-bold text-indigo-700">{{ $totalComputers }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">

                {{-- Filter --}}
                <form method="GET" action="{{ route(request()->routeIs('admin.*') ? 'admin.academic-schedule.bandlik-kursatkichi' : 'teacher.academic-schedule.bandlik-kursatkichi') }}"
                      class="flex flex-wrap items-end gap-3 mb-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sanadan</label>
                        <input type="date" name="date_from" value="{{ $dateFrom ?? now()->format('Y-m-d') }}"
                               class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sanagacha</label>
                        <input type="date" name="date_to" value="{{ $dateTo ?? now()->format('Y-m-d') }}"
                               class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <button type="submit" name="search" value="1"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Hisoblash
                    </button>
                </form>

                @if(!$isSearched)
                    <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                        </svg>
                        <p class="mt-3 text-sm text-gray-500">Sana oralig'ini tanlang va "Hisoblash" tugmasini bosing.</p>
                    </div>
                @elseif($slots->isEmpty())
                    <div class="text-center py-12 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="mt-3 text-sm text-yellow-800">Tanlangan sana oralig'ida belgilangan test vaqti topilmadi.</p>
                    </div>
                @else
                    {{-- Xulosa --}}
                    @php
                        $totalSlots = $slots->count();
                        $overflowSlots = $slots->where('overflow', '>', 0)->count();
                        $fullSlots = $slots->where('usage_percent', '>=', 100)->count();
                    @endphp
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <div class="text-xs text-indigo-700 font-medium">Jami slotlar</div>
                            <div class="text-2xl font-bold text-indigo-900">{{ $totalSlots }}</div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="text-xs text-green-700 font-medium">Normal (&lt; 100%)</div>
                            <div class="text-2xl font-bold text-green-900">{{ $totalSlots - $fullSlots }}</div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="text-xs text-yellow-700 font-medium">To'la band</div>
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
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sana</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Vaqt</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">YN turi</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guruhlar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Talabalar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Band</th>
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
                                            $statusLabel = 'Sig\'imdan ortiq';
                                            $statusClass = 'bg-red-100 text-red-800';
                                        } elseif ($slot['usage_percent'] >= 100) {
                                            $rowBg = 'bg-yellow-50';
                                            $statusLabel = 'To\'la band';
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

                                        // Progress bar rangi
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
                                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">
                                            {{ $slot['date']->format('d.m.Y') }}
                                            <div class="text-xs text-gray-500">{{ $slot['date']->isoFormat('dddd') }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
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
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($slot['groups'] as $grp)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700" title="{{ $grp['subject_name'] }}">
                                                        {{ $grp['group_name'] }} <span class="ml-1 text-gray-500">({{ $grp['student_count'] }})</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center font-medium text-gray-900">{{ $slot['occupied'] }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-medium text-indigo-700">{{ $slot['occupied'] }}</span>
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
