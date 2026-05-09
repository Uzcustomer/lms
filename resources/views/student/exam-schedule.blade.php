<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Imtihon jadvali') }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl sm:text-2xl font-bold">{{ __('Yakuniy nazorat (YN) imtihon jadvali') }}</h3>
                    </div>

                    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>{{ $student->full_name }}</strong> &mdash;
                            {{ $student->group_name }} |
                            {{ $student->semester_name ?? $student->semester_code . '-semestr' }}
                        </p>
                    </div>

                    @if(!empty($currentComputerNumber))
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800">
                                {{ __('Hozir siz') }} <strong>№{{ $currentComputerNumber }}</strong> {{ __('kompyuterda turibsiz.') }}
                            </p>
                        </div>
                    @endif

                    {{-- Bugungi imtihon banneri (JIT) — JS polling bilan to'ldiriladi --}}
                    <div id="today-exam-banner" class="hidden mb-5"></div>

                    @if($examSchedules->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('Imtihon jadvali topilmadi') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Hozircha sizning semestr uchun imtihon jadvali kiritilmagan.') }}</p>
                        </div>
                    @else
                        {{-- Desktop table --}}
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Fan nomi') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('OSKI sanasi') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('OSKI vaqti') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('OSKI №') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Test sanasi') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Test vaqti') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Test №') }}</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Holat') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($examSchedules as $index => $schedule)
                                        @php
                                            $today = \Carbon\Carbon::today();
                                            $oskiDate = $schedule->oski_date;
                                            $testDate = $schedule->test_date;

                                            $nextExamDate = null;
                                            if ($oskiDate && !$schedule->oski_na && $oskiDate->gte($today)) {
                                                $nextExamDate = $oskiDate;
                                            } elseif ($testDate && !$schedule->test_na && $testDate->gte($today)) {
                                                $nextExamDate = $testDate;
                                            }

                                            $daysLeft = $nextExamDate ? $today->diffInDays($nextExamDate) : null;

                                            if ($daysLeft !== null && $daysLeft <= 1) {
                                                $rowClass = 'bg-red-50';
                                            } elseif ($daysLeft !== null && $daysLeft <= 3) {
                                                $rowClass = 'bg-yellow-50';
                                            } else {
                                                $rowClass = '';
                                            }

                                            $assignmentsCol = isset($assignments) ? $assignments : collect();
                                            $oskiAssign = $assignmentsCol->get($schedule->id . ':oski');
                                            $testAssign = $assignmentsCol->get($schedule->id . ':test');
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $schedule->subject_name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($schedule->oski_na)
                                                    <span class="text-gray-400">-</span>
                                                @elseif($schedule->oski_date)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $oskiDate->lt($today) ? 'bg-gray-100 text-gray-600' : 'bg-indigo-100 text-indigo-800' }}">
                                                        {{ $schedule->oski_date->format('d.m.Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">{{ __('Belgilanmagan') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($schedule->oski_time)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        {{ \Carbon\Carbon::parse($schedule->oski_time)->format('H:i') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($oskiAssign && $oskiAssign->computer_number)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-600 text-white">
                                                        №{{ $oskiAssign->computer_number }}
                                                    </span>
                                                @elseif($oskiAssign)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600" title="{{ __('Test boshlanishidan oldin beriladi') }}">
                                                        {{ __('Tez orada') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($schedule->test_na)
                                                    <span class="text-gray-400">-</span>
                                                @elseif($schedule->test_date)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $testDate->lt($today) ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-800' }}">
                                                        {{ $schedule->test_date->format('d.m.Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">{{ __('Belgilanmagan') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($schedule->test_time)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ \Carbon\Carbon::parse($schedule->test_time)->format('H:i') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($testAssign && $testAssign->computer_number)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-600 text-white">
                                                        №{{ $testAssign->computer_number }}
                                                    </span>
                                                @elseif($testAssign)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600" title="{{ __('Test boshlanishidan oldin beriladi') }}">
                                                        {{ __('Tez orada') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($daysLeft !== null)
                                                    @if($daysLeft == 0)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            {{ __('Bugun!') }}
                                                        </span>
                                                    @elseif($daysLeft == 1)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            {{ __('Ertaga!') }}
                                                        </span>
                                                    @elseif($daysLeft <= 3)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            {{ $daysLeft }} {{ __('kun qoldi') }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $daysLeft }} {{ __('kun qoldi') }}
                                                        </span>
                                                    @endif
                                                @else
                                                    @php
                                                        $bothPassed = true;
                                                        if (!$schedule->oski_na && $oskiDate && $oskiDate->gte($today)) $bothPassed = false;
                                                        if (!$schedule->test_na && $testDate && $testDate->gte($today)) $bothPassed = false;
                                                        if (!$oskiDate && !$testDate) $bothPassed = false;
                                                    @endphp
                                                    @if($bothPassed && ($oskiDate || $testDate))
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                            {{ __('Tugagan') }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile cards --}}
                        <div class="sm:hidden space-y-3">
                            @foreach($examSchedules as $index => $schedule)
                                @php
                                    $today = \Carbon\Carbon::today();
                                    $oskiDate = $schedule->oski_date;
                                    $testDate = $schedule->test_date;

                                    $nextExamDate = null;
                                    if ($oskiDate && !$schedule->oski_na && $oskiDate->gte($today)) {
                                        $nextExamDate = $oskiDate;
                                    } elseif ($testDate && !$schedule->test_na && $testDate->gte($today)) {
                                        $nextExamDate = $testDate;
                                    }

                                    $daysLeft = $nextExamDate ? $today->diffInDays($nextExamDate) : null;

                                    if ($daysLeft !== null && $daysLeft <= 1) {
                                        $cardBorder = 'border-red-300 bg-red-50';
                                    } elseif ($daysLeft !== null && $daysLeft <= 3) {
                                        $cardBorder = 'border-yellow-300 bg-yellow-50';
                                    } else {
                                        $cardBorder = 'border-gray-200 bg-white';
                                    }

                                    $assignmentsCol = isset($assignments) ? $assignments : collect();
                                    $oskiAssign = $assignmentsCol->get($schedule->id . ':oski');
                                    $testAssign = $assignmentsCol->get($schedule->id . ':test');
                                @endphp
                                <div class="border rounded-lg p-4 {{ $cardBorder }}">
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="text-sm font-semibold text-gray-900 flex-1">
                                            {{ $index + 1 }}. {{ $schedule->subject_name }}
                                        </h4>
                                        @if($daysLeft !== null)
                                            @if($daysLeft == 0)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ __('Bugun!') }}</span>
                                            @elseif($daysLeft == 1)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ __('Ertaga!') }}</span>
                                            @elseif($daysLeft <= 3)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ $daysLeft }} {{ __('kun') }}</span>
                                            @else
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $daysLeft }} {{ __('kun') }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <span class="text-gray-500">{{ __('OSKI') }}:</span>
                                            @if($schedule->oski_na)
                                                <span class="text-gray-400 ml-1">-</span>
                                            @elseif($schedule->oski_date)
                                                <span class="font-medium ml-1 {{ $oskiDate->lt($today) ? 'text-gray-500' : 'text-indigo-700' }}">{{ $schedule->oski_date->format('d.m.Y') }}</span>
                                            @else
                                                <span class="text-gray-400 ml-1">{{ __('Belgilanmagan') }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="text-gray-500">{{ __('Test') }}:</span>
                                            @if($schedule->test_na)
                                                <span class="text-gray-400 ml-1">-</span>
                                            @elseif($schedule->test_date)
                                                <span class="font-medium ml-1 {{ $testDate->lt($today) ? 'text-gray-500' : 'text-green-700' }}">{{ $schedule->test_date->format('d.m.Y') }}</span>
                                            @else
                                                <span class="text-gray-400 ml-1">{{ __('Belgilanmagan') }}</span>
                                            @endif
                                        </div>
                                        @if($schedule->oski_time)
                                        <div>
                                            <span class="text-gray-500">{{ __('OSKI vaqti') }}:</span>
                                            <span class="font-medium ml-1 text-indigo-700">{{ \Carbon\Carbon::parse($schedule->oski_time)->format('H:i') }}</span>
                                        </div>
                                        @endif
                                        @if($schedule->test_time)
                                        <div>
                                            <span class="text-gray-500">{{ __('Test vaqti') }}:</span>
                                            <span class="font-medium ml-1 text-blue-700">{{ \Carbon\Carbon::parse($schedule->test_time)->format('H:i') }}</span>
                                        </div>
                                        @endif
                                        @if($oskiAssign && $oskiAssign->computer_number)
                                        <div>
                                            <span class="text-gray-500">{{ __('OSKI kompyuter') }}:</span>
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-600 text-white">№{{ $oskiAssign->computer_number }}</span>
                                        </div>
                                        @elseif($oskiAssign)
                                        <div>
                                            <span class="text-gray-500">{{ __('OSKI kompyuter') }}:</span>
                                            <span class="ml-1 text-xs text-gray-500">{{ __('Tez orada') }}</span>
                                        </div>
                                        @endif
                                        @if($testAssign && $testAssign->computer_number)
                                        <div>
                                            <span class="text-gray-500">{{ __('Test kompyuter') }}:</span>
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-600 text-white">№{{ $testAssign->computer_number }}</span>
                                        </div>
                                        @elseif($testAssign)
                                        <div>
                                            <span class="text-gray-500">{{ __('Test kompyuter') }}:</span>
                                            <span class="ml-1 text-xs text-gray-500">{{ __('Tez orada') }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Bugungi imtihon banneri uchun JS — har 30 soniyada /student/exam/status ga so'rov yuborib,
         vaqt yaqinlashganda kompyuter raqamini va "Testni boshlash" tugmasini ko'rsatadi. --}}
    <script>
    (function () {
        const banner = document.getElementById('today-exam-banner');
        if (!banner) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const STATUS_URL = "{{ url('/student/exam/status') }}";
        const START_URL = "{{ url('/student/exam/start') }}";

        function fmtCountdown(seconds) {
            if (seconds <= 0) return '00:00';
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function render(state) {
            if (!state || !state.has_assignment) {
                banner.classList.add('hidden');
                banner.innerHTML = '';
                return;
            }

            const now = Date.now();
            const start = state.planned_start ? new Date(state.planned_start).getTime() : null;
            const end = state.planned_end ? new Date(state.planned_end).getTime() : null;
            if (start === null) {
                banner.classList.add('hidden');
                return;
            }

            // Show banner only on the day of the exam
            const today = new Date();
            const startDate = new Date(state.planned_start);
            if (startDate.toDateString() !== today.toDateString()) {
                banner.classList.add('hidden');
                return;
            }

            // If the slot has finished long ago, hide
            if (end && now > end + 60 * 60 * 1000) {
                banner.classList.add('hidden');
                return;
            }

            banner.classList.remove('hidden');

            const secondsToStart = Math.floor((start - now) / 1000);
            const revealed = state.revealed && state.computer_number;
            const onCorrect = state.on_correct_computer === true;
            const detected = state.detected_computer;

            // Decide tone
            let tone = 'bg-blue-50 border-blue-200 text-blue-900';
            let badge = '';
            let actionHtml = '';

            if (state.status === 'finished' || state.status === 'abandoned') {
                tone = 'bg-gray-50 border-gray-200 text-gray-700';
                badge = '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">{{ __('Tugagan') }}</span>';
            } else if (state.status === 'in_progress') {
                tone = 'bg-emerald-50 border-emerald-200 text-emerald-900';
                badge = '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-200 text-emerald-800">{{ __('Davom etmoqda') }}</span>';
            } else if (revealed) {
                tone = 'bg-amber-50 border-amber-300 text-amber-900';
                badge = '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-white">{{ __('Kompyuter berildi') }}</span>';
            }

            // Computer-number area
            let pcHtml = '';
            if (revealed) {
                pcHtml = `
                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex-1">
                            <div class="text-xs uppercase tracking-wide text-amber-700">{{ __('Sizning kompyuteringiz') }}</div>
                            <div class="text-3xl font-bold text-amber-900">№${state.computer_number}${state.is_reserve ? ' <span class="text-xs font-normal">({{ __('zahira') }})</span>' : ''}</div>
                        </div>
                    </div>
                `;
                if (detected !== null && detected !== undefined) {
                    if (onCorrect) {
                        pcHtml += `<div class="mt-2 text-sm text-emerald-700">✓ {{ __('Siz to\'g\'ri kompyuterda turibsiz') }}</div>`;
                    } else {
                        pcHtml += `<div class="mt-2 text-sm text-red-700 font-medium">⚠️ {{ __('Siz') }} #${detected} {{ __('kompyuterdasiz, lekin sizga') }} #${state.computer_number} {{ __('biriktirilgan') }}</div>`;
                    }
                }
            } else {
                pcHtml = `
                    <div class="mt-3">
                        <div class="text-xs uppercase tracking-wide text-blue-700">{{ __('Sizning kompyuteringiz') }}</div>
                        <div class="text-base text-blue-900 mt-1">{{ __('Test boshlanishidan ~5 daqiqa oldin Telegramga va shu sahifaga keladi.') }}</div>
                    </div>
                `;
            }

            // Start button
            if (revealed && state.status !== 'finished' && state.status !== 'abandoned') {
                if (onCorrect) {
                    actionHtml = `
                        <button id="start-exam-btn" class="mt-4 inline-flex items-center px-5 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow">
                            {{ __('Testni boshlash') }} →
                        </button>
                        <div id="start-exam-error" class="mt-2 text-sm text-red-700 hidden"></div>
                    `;
                } else {
                    actionHtml = `
                        <div class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-red-100 text-red-800 text-sm font-medium">
                            {{ __('Testni boshlash uchun #') }}${state.computer_number} {{ __('kompyuterga o\'ting') }}
                        </div>
                    `;
                }
            }

            const startTime = new Date(state.planned_start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            let countdownHtml = '';
            if (secondsToStart > 0 && state.status === 'scheduled') {
                countdownHtml = `<span class="ml-3 text-base font-semibold tabular-nums">⏳ ${fmtCountdown(secondsToStart)}</span>`;
            }

            banner.innerHTML = `
                <div class="border-2 rounded-xl p-5 ${tone}">
                    <div class="flex items-center flex-wrap">
                        <div class="text-base font-semibold">{{ __('Bugungi imtihon — boshlanish vaqti') }}: <span class="text-lg">${startTime}</span></div>
                        ${badge}
                        ${countdownHtml}
                    </div>
                    ${pcHtml}
                    ${actionHtml}
                </div>
            `;

            const btn = document.getElementById('start-exam-btn');
            if (btn) btn.addEventListener('click', startExam);
        }

        function startExam() {
            const btn = document.getElementById('start-exam-btn');
            const err = document.getElementById('start-exam-error');
            if (!btn) return;
            btn.disabled = true;
            btn.style.opacity = '0.7';
            if (err) { err.classList.add('hidden'); err.textContent = ''; }

            fetch(START_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(r => r.json().then(d => ({ ok: r.ok, body: d })))
            .then(({ ok, body }) => {
                if (ok && body.success) {
                    if (body.moodle_url) {
                        window.location.href = body.moodle_url;
                    } else {
                        if (err) {
                            err.textContent = '{{ __('Moodle manzili sozlanmagan. Test markazi xodimiga murojaat qiling.') }}';
                            err.classList.remove('hidden');
                        }
                        btn.disabled = false; btn.style.opacity = '1';
                    }
                } else {
                    const msg = body.message || '{{ __('Testni boshlashga ruxsat berilmadi.') }}';
                    if (err) { err.textContent = msg; err.classList.remove('hidden'); }
                    btn.disabled = false; btn.style.opacity = '1';
                }
            })
            .catch(() => {
                if (err) { err.textContent = '{{ __('Tarmoq xatosi. Qayta urinib ko\'ring.') }}'; err.classList.remove('hidden'); }
                btn.disabled = false; btn.style.opacity = '1';
            });
        }

        let lastState = null;
        function poll() {
            fetch(STATUS_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json())
                .then(state => {
                    lastState = state;
                    render(state);
                })
                .catch(() => {});
        }

        // Initial fetch + 30s polling.
        poll();
        setInterval(poll, 30000);

        // 1s tick — refresh just the countdown without hitting the server.
        setInterval(function () {
            if (lastState && lastState.has_assignment) render(lastState);
        }, 1000);
    })();
    </script>
</x-student-app-layout>
