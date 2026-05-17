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

                    {{-- Ustun bo'yicha filtrlar uchun unikal qiymatlarni hisoblash --}}
                    @php
                        $uniqueTimes = [];
                        $hasNoTimeRow = false;
                        $uniqueGroupNames = [];
                        $uniqueSubjects = [];
                        $uniqueYnTypes = [];
                        $uniqueAttempts = [];
                        $uniqueStudents = [];
                        $uniqueSubmitted = [];
                        $uniqueRemaining = [];
                        $uniqueOccupied = [];
                        $uniqueFree = [];
                        $uniqueUsage = [];
                        $uniqueStatuses = [];

                        foreach ($slots as $slot) {
                            $no = !empty($slot['no_time']);
                            if ($no) {
                                $hasNoTimeRow = true;
                            } else {
                                $uniqueTimes[(string) $slot['time']] = true;
                            }
                            foreach ($slot['groups'] as $g) {
                                if (!empty($g['group_name'])) $uniqueGroupNames[(string) $g['group_name']] = true;
                                if (!empty($g['subject_name'])) $uniqueSubjects[(string) $g['subject_name']] = true;
                                if (!empty($g['yn_type'])) $uniqueYnTypes[(string) $g['yn_type']] = true;
                                $uniqueAttempts[(int) ($g['attempt'] ?? 1)] = true;
                            }
                            $occ = (int) $slot['occupied'];
                            $sub = (int) ($slot['submitted'] ?? 0);
                            $rem = (int) ($slot['remaining'] ?? max(0, $occ - $sub));
                            $uniqueStudents[$occ] = true;
                            $uniqueSubmitted[$sub] = true;
                            $uniqueRemaining[$rem] = true;
                            if (!$no) {
                                $uniqueOccupied[$occ] = true;
                                $uniqueFree[(int) $slot['free']] = true;
                                $uniqueUsage[(float) $slot['usage_percent']] = true;
                                if ($slot['overflow'] > 0) {
                                    $uniqueStatuses["Sig'imdan ortiq"] = true;
                                } elseif ($slot['usage_percent'] >= 100) {
                                    $uniqueStatuses["To'la band"] = true;
                                } elseif ($slot['usage_percent'] >= 75) {
                                    $uniqueStatuses['Yuqori bandlik'] = true;
                                } else {
                                    $uniqueStatuses['Normal'] = true;
                                }
                            } else {
                                $uniqueStatuses["Vaqti qo'yilmagan"] = true;
                            }
                        }
                        $sortedTimes = array_keys($uniqueTimes); sort($sortedTimes);
                        $sortedGroupNames = array_keys($uniqueGroupNames); natcasesort($sortedGroupNames); $sortedGroupNames = array_values($sortedGroupNames);
                        $sortedSubjects = array_keys($uniqueSubjects); natcasesort($sortedSubjects); $sortedSubjects = array_values($sortedSubjects);
                        $sortedYnTypes = array_keys($uniqueYnTypes); sort($sortedYnTypes);
                        $sortedAttempts = array_keys($uniqueAttempts); sort($sortedAttempts);
                        $sortedStudents = array_keys($uniqueStudents); sort($sortedStudents, SORT_NUMERIC);
                        $sortedSubmitted = array_keys($uniqueSubmitted); sort($sortedSubmitted, SORT_NUMERIC);
                        $sortedRemaining = array_keys($uniqueRemaining); sort($sortedRemaining, SORT_NUMERIC);
                        $sortedOccupied = array_keys($uniqueOccupied); sort($sortedOccupied, SORT_NUMERIC);
                        $sortedFree = array_keys($uniqueFree); sort($sortedFree, SORT_NUMERIC);
                        $sortedUsage = array_keys($uniqueUsage); sort($sortedUsage, SORT_NUMERIC);
                        $sortedStatuses = array_keys($uniqueStatuses); sort($sortedStatuses);
                    @endphp

                    {{-- Jadval --}}
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table id="bk-table" class="min-w-full divide-y divide-gray-200 text-sm">
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
                                <tr class="bg-gray-50 border-t border-gray-200">
                                    <th class="px-2 py-1.5">
                                        <button type="button" id="bk-filter-reset" class="w-full text-[10px] font-semibold text-slate-500 hover:text-indigo-700 hover:bg-indigo-50 rounded px-1 py-0.5 transition" title="Filtrlarni tozalash">↺</button>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="time" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedTimes as $t)
                                                <option value="{{ $t }}">{{ $t }}</option>
                                            @endforeach
                                            @if($hasNoTimeRow)
                                                <option value="__no_time__">Vaqti qo'yilmagan</option>
                                            @endif
                                        </select>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <div class="flex gap-1">
                                            <select data-bk-filter="group" class="bk-filter flex-1 min-w-0 text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="Guruh nomi">
                                                <option value="">Guruh</option>
                                                @foreach($sortedGroupNames as $g)
                                                    <option value="{{ $g }}">{{ $g }}</option>
                                                @endforeach
                                            </select>
                                            <select data-bk-filter="subject" class="bk-filter flex-1 min-w-0 text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="Fan">
                                                <option value="">Fan</option>
                                                @foreach($sortedSubjects as $s)
                                                    <option value="{{ $s }}">{{ $s }}</option>
                                                @endforeach
                                            </select>
                                            <select data-bk-filter="yn" class="bk-filter text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="YN turi">
                                                <option value="">YN</option>
                                                @foreach($sortedYnTypes as $y)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endforeach
                                            </select>
                                            <select data-bk-filter="attempt" class="bk-filter text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="Urinish">
                                                <option value="">Urinish</option>
                                                @foreach($sortedAttempts as $a)
                                                    <option value="{{ $a }}">{{ $a }}-urinish</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="students" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedStudents as $v)
                                                <option value="{{ $v }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <div class="flex gap-1">
                                            <select data-bk-filter="submitted" class="bk-filter flex-1 min-w-0 text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="Topshirdi">
                                                <option value="">Topshirdi</option>
                                                @foreach($sortedSubmitted as $v)
                                                    <option value="{{ $v }}">{{ $v }}</option>
                                                @endforeach
                                            </select>
                                            <select data-bk-filter="remaining" class="bk-filter flex-1 min-w-0 text-xs border border-slate-300 rounded px-1 py-0.5 bg-white" title="Qoldi">
                                                <option value="">Qoldi</option>
                                                @foreach($sortedRemaining as $v)
                                                    <option value="{{ $v }}">{{ $v }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="occupied" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedOccupied as $v)
                                                <option value="{{ $v }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="free" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedFree as $v)
                                                <option value="{{ $v }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="usage" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedUsage as $v)
                                                <option value="{{ $v }}">{{ rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') }}%</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1.5">
                                        <select data-bk-filter="status" class="bk-filter w-full text-xs border border-slate-300 rounded px-1 py-0.5 bg-white">
                                            <option value="">Barchasi</option>
                                            @foreach($sortedStatuses as $s)
                                                <option value="{{ $s }}">{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </th>
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

                                        $rowGroupNames = [];
                                        $rowSubjects = [];
                                        $rowYnTypes = [];
                                        $rowAttempts = [];
                                        foreach ($slot['groups'] as $_g) {
                                            if (!empty($_g['group_name'])) $rowGroupNames[] = $_g['group_name'];
                                            if (!empty($_g['subject_name'])) $rowSubjects[] = $_g['subject_name'];
                                            if (!empty($_g['yn_type'])) $rowYnTypes[] = $_g['yn_type'];
                                            $rowAttempts[] = (int) ($_g['attempt'] ?? 1);
                                        }
                                        $dataTime = $isNoTime ? '__no_time__' : (string) $slot['time'];
                                    @endphp
                                    <tr class="bk-row {{ $rowBg }} hover:bg-gray-50"
                                        data-time="{{ $dataTime }}"
                                        data-groups="|{{ implode('|', array_unique($rowGroupNames)) }}|"
                                        data-subjects="|{{ implode('|', array_unique($rowSubjects)) }}|"
                                        data-yns="|{{ implode('|', array_unique($rowYnTypes)) }}|"
                                        data-attempts="|{{ implode('|', array_unique($rowAttempts)) }}|"
                                        data-students="{{ $slotOccupied }}"
                                        data-submitted="{{ $slotSubmitted }}"
                                        data-remaining="{{ $slotRemaining }}"
                                        data-occupied="{{ $isNoTime ? '' : $slotOccupied }}"
                                        data-free="{{ $isNoTime ? '' : (int) $slot['free'] }}"
                                        data-usage="{{ $isNoTime ? '' : (float) $slot['usage_percent'] }}"
                                        data-status="{{ $statusLabel }}">
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
                                <tr id="bk-empty-row" class="hidden">
                                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                                        Filtr bo'yicha mos qator topilmadi.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <script>
                        (function() {
                            const filters = document.querySelectorAll('.bk-filter');
                            const rows = document.querySelectorAll('#bk-table .bk-row');
                            const emptyRow = document.getElementById('bk-empty-row');
                            const resetBtn = document.getElementById('bk-filter-reset');

                            function rowMatches(row) {
                                for (const f of filters) {
                                    const v = f.value;
                                    if (!v) continue;
                                    const key = f.dataset.bkFilter;
                                    if (key === 'group' || key === 'subject' || key === 'yn' || key === 'attempt') {
                                        const attr = key === 'group' ? 'groups' :
                                                     key === 'subject' ? 'subjects' :
                                                     key === 'yn' ? 'yns' : 'attempts';
                                        const cell = row.dataset[attr] || '';
                                        if (cell.indexOf('|' + v + '|') === -1) return false;
                                    } else {
                                        const cell = row.dataset[key];
                                        if (cell === undefined || cell === '' || String(cell) !== v) return false;
                                    }
                                }
                                return true;
                            }

                            function applyFilters() {
                                let visible = 0;
                                rows.forEach(r => {
                                    if (rowMatches(r)) {
                                        r.classList.remove('hidden');
                                        visible++;
                                    } else {
                                        r.classList.add('hidden');
                                    }
                                });
                                if (emptyRow) emptyRow.classList.toggle('hidden', visible !== 0 || rows.length === 0);
                            }

                            filters.forEach(f => f.addEventListener('change', applyFilters));
                            if (resetBtn) resetBtn.addEventListener('click', function() {
                                filters.forEach(f => { f.value = ''; });
                                applyFilters();
                            });
                        })();
                    </script>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
