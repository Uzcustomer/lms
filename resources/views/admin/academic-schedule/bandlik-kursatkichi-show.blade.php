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
                    {{-- Xulosa: kompakt interaktiv ko'rsatkichlar --}}
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

                        $workStart = $settings['work_hours_start'] ?? '09:00';
                        $workEnd = $settings['work_hours_end'] ?? '17:00';
                        $lunchStart = $settings['lunch_start'] ?? null;
                        $lunchEnd = $settings['lunch_end'] ?? null;
                        $hasLunch = $lunchStart && $lunchEnd;
                        $testDuration = (int) ($settings['test_duration_minutes'] ?? 15);
                        $dailyCapacity = (int) ($dailyCapacity ?? 0);
                        $freeCapacity = max(0, $dailyCapacity - $totalStudents);
                        $capacityPct = $dailyCapacity > 0 ? min(100, round(($totalStudents / $dailyCapacity) * 100)) : 0;
                        $capacityBarClass = $capacityPct >= 100 ? 'bg-red-500' : ($capacityPct >= 80 ? 'bg-orange-500' : ($capacityPct >= 50 ? 'bg-yellow-500' : 'bg-emerald-500'));
                        $submitPct = $totalStudents > 0 ? round(($totalSubmitted / $totalStudents) * 100) : 0;
                    @endphp
                    <div class="flex flex-wrap items-stretch gap-2 mb-4">
                        {{-- Ish vaqti --}}
                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-slate-200 shadow-sm hover:border-indigo-300 transition"
                             title="Ish vaqti{{ $hasLunch ? ' · Tushlik: '.$lunchStart.'–'.$lunchEnd : '' }} · Bitta test: {{ $testDuration }} daq.">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Ish vaqti</span>
                                <span class="text-sm font-bold text-slate-800">
                                    {{ $workStart }}<span class="text-slate-400 mx-0.5">–</span>{{ $workEnd }}
                                </span>
                            </div>
                            @if($hasLunch)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-[10px] font-semibold text-amber-800" title="Tushlik tanaffusi">
                                    🍴 {{ $lunchStart }}–{{ $lunchEnd }}
                                </span>
                            @endif
                        </div>

                        {{-- Sig'im: hozir / maksimal --}}
                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-slate-200 shadow-sm hover:border-blue-300 transition"
                             title="Hozir joylashtirilgan talabalar / Kunlik maksimal sig'im ({{ $totalComputers }} komputer × slotlar)">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Sig'im</span>
                                <span class="text-sm font-bold">
                                    <span class="text-blue-700">{{ $totalStudents }}</span>
                                    <span class="text-slate-300">/</span>
                                    <span class="text-slate-700">{{ $dailyCapacity }}</span>
                                    <span class="text-[10px] text-slate-400 font-normal ml-0.5">talaba</span>
                                </span>
                            </div>
                            <div class="flex flex-col items-end gap-0.5 ml-1">
                                <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="{{ $capacityBarClass }} h-full transition-all" style="width: {{ $capacityPct }}%"></div>
                                </div>
                                <span class="text-[10px] text-slate-500 font-semibold">{{ $capacityPct }}%</span>
                            </div>
                        </div>

                        {{-- Bo'sh sig'im --}}
                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-slate-200 shadow-sm hover:border-emerald-300 transition"
                             title="Bugun yana shuncha talabani sig'dirsa bo'ladi">
                            <svg class="w-4 h-4 {{ $freeCapacity > 0 ? 'text-emerald-600' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Yana sig'adi</span>
                                <span class="text-sm font-bold {{ $freeCapacity > 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                    {{ $freeCapacity }}
                                    <span class="text-[10px] text-slate-400 font-normal">talaba</span>
                                </span>
                            </div>
                        </div>

                        {{-- Slotlar --}}
                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-slate-200 shadow-sm hover:border-indigo-300 transition"
                             title="Jami vaqt slotlari{{ $fullSlots > 0 ? ' · To\'la band: '.($fullSlots - $overflowSlots) : '' }}{{ $overflowSlots > 0 ? ' · Sig\'imdan ortiq: '.$overflowSlots : '' }}">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Slotlar</span>
                                <span class="text-sm font-bold text-indigo-900">{{ $totalSlots }}</span>
                            </div>
                            @if(($fullSlots - $overflowSlots) > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-yellow-50 border border-yellow-200 text-[10px] font-semibold text-yellow-800" title="To'la band slotlar">
                                    {{ $fullSlots - $overflowSlots }} to'la
                                </span>
                            @endif
                            @if($overflowSlots > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-red-50 border border-red-200 text-[10px] font-semibold text-red-700" title="Sig'imdan ortiq slotlar">
                                    {{ $overflowSlots }} ortiq
                                </span>
                            @endif
                        </div>

                        {{-- Topshirdi / Qoldi --}}
                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-slate-200 shadow-sm hover:border-emerald-300 transition"
                             title="Quizni topshirgan / hali topshirmagan talabalar">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Topshirdi / Qoldi</span>
                                <span class="text-sm font-bold">
                                    <span class="text-emerald-700">{{ $totalSubmitted }}</span>
                                    <span class="text-slate-300">/</span>
                                    <span class="text-amber-700">{{ $totalRemaining }}</span>
                                </span>
                            </div>
                            <div class="flex flex-col items-end gap-0.5 ml-1">
                                <div class="w-14 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="bg-emerald-500 h-full transition-all" style="width: {{ $submitPct }}%"></div>
                                </div>
                                <span class="text-[10px] text-slate-500 font-semibold">{{ $submitPct }}%</span>
                            </div>
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
