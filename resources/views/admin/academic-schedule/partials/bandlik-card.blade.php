@php
    $routeName = request()->routeIs('admin.*')
        ? 'admin.academic-schedule.bandlik-kursatkichi.show'
        : 'teacher.academic-schedule.bandlik-kursatkichi.show';

    if ($card['has_overflow']) {
        $borderClass = 'border-red-300 hover:border-red-400';
        $bgHeader = 'bg-gradient-to-r from-red-500 to-red-600';
        $badgeClass = 'bg-red-100 text-red-800';
        $badgeText = "Sig'imdan ortiq";
    } elseif ($card['is_today']) {
        $borderClass = 'border-indigo-400 hover:border-indigo-500 ring-2 ring-indigo-100';
        $bgHeader = 'bg-gradient-to-r from-indigo-500 to-indigo-600';
        $badgeClass = 'bg-indigo-100 text-indigo-800';
        $badgeText = 'Bugun';
    } elseif ($card['is_past']) {
        $borderClass = 'border-gray-200 hover:border-gray-300 opacity-75';
        $bgHeader = 'bg-gradient-to-r from-gray-500 to-gray-600';
        $badgeClass = 'bg-gray-100 text-gray-700';
        $badgeText = "O'tgan";
    } else {
        $borderClass = 'border-gray-200 hover:border-blue-400';
        $bgHeader = 'bg-gradient-to-r from-blue-500 to-blue-600';
        $badgeClass = 'bg-blue-100 text-blue-800';
        $badgeText = 'Kelgusi';
    }
@endphp

<a href="{{ route($routeName, ['date' => $card['date_str']]) }}"
   class="block bg-white border-2 {{ $borderClass }} rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden">

    <div class="{{ $bgHeader }} px-4 py-3 text-white">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-2xl font-bold">{{ $card['date']->format('d.m.Y') }}</div>
                <div class="text-xs opacity-90 mt-0.5">{{ $card['date']->isoFormat('dddd') }}</div>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-white/20 text-white">
                {{ $badgeText }}
            </span>
        </div>
    </div>

    <div class="p-4 space-y-2.5">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Vaqt slotlari
            </span>
            <span class="font-semibold text-gray-900">{{ $card['slot_count'] }}</span>
        </div>

        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Guruhlar
            </span>
            <span class="font-semibold text-gray-900">{{ $card['group_count'] }}</span>
        </div>

        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Eng yuqori band
            </span>
            <span class="font-semibold {{ $card['has_overflow'] ? 'text-red-600' : 'text-gray-900' }}">
                {{ $card['max_occupied'] }}/{{ $totalComputers }}
            </span>
        </div>

        {{-- Progress bar eng yuqori bandlik uchun --}}
        @php
            $percent = $totalComputers > 0 ? min(100, round(($card['max_occupied'] / $totalComputers) * 100)) : 0;
            if ($card['has_overflow']) $barColor = 'bg-red-500';
            elseif ($percent >= 75) $barColor = 'bg-orange-500';
            elseif ($percent >= 50) $barColor = 'bg-yellow-500';
            else $barColor = 'bg-green-500';
        @endphp
        <div class="pt-1">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                <span>Bandlik</span>
                <span class="font-semibold {{ $card['has_overflow'] ? 'text-red-600' : 'text-gray-700' }}">
                    @if($card['has_overflow'])
                        {{ round(($card['max_occupied'] / $totalComputers) * 100) }}%
                    @else
                        {{ $percent }}%
                    @endif
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                <div class="{{ $barColor }} h-full transition-all" style="width: {{ $percent }}%"></div>
            </div>
        </div>

        <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs">
            <span class="text-gray-500">Batafsil ko'rish</span>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </div>
    </div>
</a>
