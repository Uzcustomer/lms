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
                        $scheduledSlots = $slots->where('no_time', false);
                        $pendingSlots = $slots->where('no_time', true);
                        $totalSlots = $scheduledSlots->count();
                        $overflowSlots = $scheduledSlots->where('overflow', '>', 0)->count();
                        $fullSlots = $scheduledSlots->where('usage_percent', '>=', 100)->count();
                        $totalStudents = $scheduledSlots->sum('occupied');
                        $totalSubmitted = $scheduledSlots->sum('submitted');
                        $totalRemaining = $scheduledSlots->sum('remaining');
                        $pendingGroups = $pendingSlots->sum(fn($r) => count($r['groups']));
                        $pendingStudents = $pendingSlots->sum('occupied');
                    @endphp
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <div class="text-xs text-indigo-700 font-medium">Vaqt slotlari</div>
                            <div class="text-2xl font-bold text-indigo-900">{{ $totalSlots }}</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="text-xs text-blue-700 font-medium">Jami talabalar</div>
                            <div class="text-2xl font-bold text-blue-900">{{ $totalStudents }}</div>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                            <div class="text-xs text-emerald-700 font-medium">Topshirganlar</div>
                            <div class="text-2xl font-bold text-emerald-900">{{ $totalSubmitted }}</div>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="text-xs text-amber-700 font-medium">Qoldi</div>
                            <div class="text-2xl font-bold text-amber-900">{{ $totalRemaining }}</div>
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

                    @if($pendingSlots->isNotEmpty())
                        <div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-3">
                            <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div class="text-sm text-amber-900">
                                <span class="font-semibold">Vaqti qo'yilmagan:</span>
                                {{ $pendingGroups }} guruh
                                @if($pendingStudents > 0)
                                    ({{ $pendingStudents }} talaba)
                                @endif
                                — quyidagi jadvalning oxirida ko'rsatilgan.
                            </div>
                        </div>
                    @endif

                    {{-- Jadval --}}
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Vaqt</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guruhlar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Talabalar</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Topshirdi / Qoldi</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Band / Jami</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Bo'sh</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Bandlik %</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($slots as $i => $slot)
                                    @php
                                        $isNoTime = !empty($slot['no_time']);
                                        if ($isNoTime) {
                                            $rowBg = 'bg-amber-50';
                                            $statusLabel = "Vaqti qo'yilmagan";
                                            $statusClass = 'bg-amber-100 text-amber-800';
                                            $barColor = 'bg-amber-400';
                                        } elseif ($slot['overflow'] > 0) {
                                            $rowBg = 'bg-red-50';
                                            $statusLabel = "Sig'imdan ortiq";
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $barColor = 'bg-red-500';
                                        } elseif ($slot['usage_percent'] >= 100) {
                                            $rowBg = 'bg-yellow-50';
                                            $statusLabel = "To'la band";
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $barColor = 'bg-red-500';
                                        } elseif ($slot['usage_percent'] >= 75) {
                                            $rowBg = '';
                                            $statusLabel = 'Yuqori bandlik';
                                            $statusClass = 'bg-orange-100 text-orange-800';
                                            $barColor = 'bg-orange-500';
                                        } elseif ($slot['usage_percent'] >= 50) {
                                            $rowBg = '';
                                            $statusLabel = 'Normal';
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $barColor = 'bg-yellow-500';
                                        } else {
                                            $rowBg = '';
                                            $statusLabel = 'Normal';
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $barColor = 'bg-green-500';
                                        }

                                        $slotOccupied = (int) $slot['occupied'];
                                        $slotSubmitted = (int) ($slot['submitted'] ?? 0);
                                        $slotRemaining = (int) ($slot['remaining'] ?? max(0, $slotOccupied - $slotSubmitted));
                                        $submitPercent = $slotOccupied > 0 ? round(($slotSubmitted / $slotOccupied) * 100) : 0;
                                    @endphp
                                    <tr class="{{ $rowBg }} hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if($isNoTime)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-amber-100 text-amber-800" title="Vaqti hali belgilanmagan">
                                                    Vaqti qo'yilmagan
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded text-sm font-semibold bg-blue-100 text-blue-800">
                                                    {{ $slot['time'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col gap-1.5">
                                                @foreach($slot['groups'] as $grp)
                                                    @php
                                                        $grpCnt = (int) ($grp['student_count'] ?? 0);
                                                        $grpQuiz = (int) ($grp['quiz_count'] ?? 0);
                                                        $grpRem = (int) ($grp['remaining'] ?? max(0, $grpCnt - $grpQuiz));
                                                        $grpYn = $grp['yn_type'] ?? '';
                                                        $grpAttempt = (int) ($grp['attempt'] ?? 1);
                                                        $grpAttemptClass = match($grpAttempt) {
                                                            2 => 'bg-orange-100 text-orange-800',
                                                            3 => 'bg-red-100 text-red-800',
                                                            default => 'bg-slate-100 text-slate-700',
                                                        };
                                                    @endphp
                                                    <div class="inline-flex items-center gap-2 px-2 py-1 rounded bg-gray-50 border border-gray-200">
                                                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold" title="Guruhdagi jami talabalar">
                                                            {{ $grpCnt }}
                                                        </span>
                                                        @if($grpYn === 'OSKI')
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800">OSKI</span>
                                                        @else
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-cyan-100 text-cyan-800">Test</span>
                                                        @endif
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $grpAttemptClass }}" title="Urinish raqami">
                                                            {{ $grpAttempt }}-urinish
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs whitespace-nowrap">{{ $grp['group_name'] }}</span>
                                                        @if(!empty($grp['subject_name']))
                                                            <span class="text-gray-400 text-xs">—</span>
                                                            <span class="text-gray-700 text-xs">{{ $grp['subject_name'] }}</span>
                                                        @endif
                                                        <span class="text-gray-300 text-xs">·</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] whitespace-nowrap">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold" title="Quizni topshirgan talabalar soni">
                                                                Topshirdi: {{ $grpQuiz }}
                                                            </span>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded {{ $grpRem > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }} font-semibold" title="Hali topshirmagan talabalar soni">
                                                                Qoldi: {{ $grpRem }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center font-semibold text-gray-900">{{ $slotOccupied }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <div class="flex items-center gap-1 text-xs">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold" title="Quizni topshirgan talabalar soni">
                                                        Topshirdi: {{ $slotSubmitted }}
                                                    </span>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded {{ $slotRemaining > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }} font-semibold" title="Hali topshirmagan talabalar soni">
                                                        Qoldi: {{ $slotRemaining }}
                                                    </span>
                                                </div>
                                                <div class="w-24 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-emerald-500 h-full" style="width: {{ $submitPercent }}%"></div>
                                                </div>
                                                <span class="text-[10px] text-gray-500">{{ $submitPercent }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($isNoTime)
                                                <span class="text-gray-400">—</span>
                                            @else
                                                <span class="font-semibold text-indigo-700">{{ $slotOccupied }}</span>
                                                <span class="text-gray-400">/</span>
                                                <span class="text-gray-500">{{ $totalComputers }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($isNoTime)
                                                <span class="text-gray-400">—</span>
                                            @elseif($slot['overflow'] > 0)
                                                <span class="text-red-700 font-semibold">-{{ $slot['overflow'] }} yetmaydi</span>
                                            @else
                                                <span class="font-semibold text-green-700">{{ $slot['free'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($isNoTime)
                                                <span class="text-gray-400">—</span>
                                            @else
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden min-w-[60px]">
                                                        <div class="{{ $barColor }} h-full" style="width: {{ min(100, $slot['usage_percent']) }}%"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-700 w-12 text-right">{{ $slot['usage_percent'] }}%</span>
                                                </div>
                                            @endif
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
