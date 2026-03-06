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
                        <h3 class="text-xl sm:text-2xl font-bold">Yakuniy nazorat (YN) imtihon jadvali</h3>
                    </div>

                    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>{{ $student->full_name }}</strong> &mdash;
                            {{ $student->group_name }} |
                            {{ $student->semester_name ?? $student->semester_code . '-semestr' }}
                        </p>
                    </div>

                    @if($examSchedules->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Imtihon jadvali topilmadi</h3>
                            <p class="mt-1 text-sm text-gray-500">Hozircha sizning semestr uchun imtihon jadvali kiritilmagan.</p>
                        </div>
                    @else
                        {{-- Desktop table --}}
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan nomi</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">OSKI sanasi</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Test sanasi</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Test vaqti</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Holat</th>
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
                                                    <span class="text-gray-400">Belgilanmagan</span>
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
                                                    <span class="text-gray-400">Belgilanmagan</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($schedule->test_time)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ substr($schedule->test_time, 0, 5) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if($daysLeft !== null)
                                                    @if($daysLeft == 0)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Bugun!
                                                        </span>
                                                    @elseif($daysLeft == 1)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Ertaga!
                                                        </span>
                                                    @elseif($daysLeft <= 3)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            {{ $daysLeft }} kun qoldi
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $daysLeft }} kun qoldi
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
                                                            Tugagan
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
                                @endphp
                                <div class="border rounded-lg p-4 {{ $cardBorder }}">
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="text-sm font-semibold text-gray-900 flex-1">
                                            {{ $index + 1 }}. {{ $schedule->subject_name }}
                                        </h4>
                                        @if($daysLeft !== null)
                                            @if($daysLeft == 0)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Bugun!</span>
                                            @elseif($daysLeft == 1)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Ertaga!</span>
                                            @elseif($daysLeft <= 3)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ $daysLeft }} kun</span>
                                            @else
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $daysLeft }} kun</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <span class="text-gray-500">OSKI:</span>
                                            @if($schedule->oski_na)
                                                <span class="text-gray-400 ml-1">-</span>
                                            @elseif($schedule->oski_date)
                                                <span class="font-medium ml-1 {{ $oskiDate->lt($today) ? 'text-gray-500' : 'text-indigo-700' }}">{{ $schedule->oski_date->format('d.m.Y') }}</span>
                                            @else
                                                <span class="text-gray-400 ml-1">Belgilanmagan</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Test:</span>
                                            @if($schedule->test_na)
                                                <span class="text-gray-400 ml-1">-</span>
                                            @elseif($schedule->test_date)
                                                <span class="font-medium ml-1 {{ $testDate->lt($today) ? 'text-gray-500' : 'text-green-700' }}">{{ $schedule->test_date->format('d.m.Y') }}</span>
                                            @else
                                                <span class="text-gray-400 ml-1">Belgilanmagan</span>
                                            @endif
                                        </div>
                                        @if($schedule->test_time)
                                        <div>
                                            <span class="text-gray-500">Vaqt:</span>
                                            <span class="font-medium ml-1 text-blue-700">{{ substr($schedule->test_time, 0, 5) }}</span>
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
</x-student-app-layout>
