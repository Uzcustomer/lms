<x-student-app-layout>
    @php
        $dayColors = [
            'Monday'    => ['bg' => 'bg-blue-50 dark:bg-blue-900/30',    'border' => 'border-blue-200 dark:border-blue-700',    'accent' => 'bg-blue-500',    'text' => 'text-blue-700 dark:text-blue-300',    'light' => 'bg-blue-100 dark:bg-blue-800/40',  'dot' => 'bg-blue-400'],
            'Tuesday'   => ['bg' => 'bg-purple-50 dark:bg-purple-900/30','border' => 'border-purple-200 dark:border-purple-700','accent' => 'bg-purple-500','text' => 'text-purple-700 dark:text-purple-300','light' => 'bg-purple-100 dark:bg-purple-800/40','dot' => 'bg-purple-400'],
            'Wednesday' => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/30','border' => 'border-emerald-200 dark:border-emerald-700','accent' => 'bg-emerald-500','text' => 'text-emerald-700 dark:text-emerald-300','light' => 'bg-emerald-100 dark:bg-emerald-800/40','dot' => 'bg-emerald-400'],
            'Thursday'  => ['bg' => 'bg-orange-50 dark:bg-orange-900/30','border' => 'border-orange-200 dark:border-orange-700','accent' => 'bg-orange-500','text' => 'text-orange-700 dark:text-orange-300','light' => 'bg-orange-100 dark:bg-orange-800/40','dot' => 'bg-orange-400'],
            'Friday'    => ['bg' => 'bg-rose-50 dark:bg-rose-900/30',    'border' => 'border-rose-200 dark:border-rose-700',    'accent' => 'bg-rose-500',    'text' => 'text-rose-700 dark:text-rose-300',    'light' => 'bg-rose-100 dark:bg-rose-800/40',  'dot' => 'bg-rose-400'],
            'Saturday'  => ['bg' => 'bg-teal-50 dark:bg-teal-900/30',    'border' => 'border-teal-200 dark:border-teal-700',    'accent' => 'bg-teal-500',    'text' => 'text-teal-700 dark:text-teal-300',    'light' => 'bg-teal-100 dark:bg-teal-800/40',  'dot' => 'bg-teal-400'],
        ];
        $days = [
            'Monday' => 'Dushanba', 'Tuesday' => 'Seshanba', 'Wednesday' => 'Chorshanba',
            'Thursday' => 'Payshanba', 'Friday' => 'Juma', 'Saturday' => 'Shanba'
        ];
    @endphp

    <div x-data="{
        view: 'week',
        selectedDay: '{{ $todayDayEn }}',
        semesterOpen: false
    }" class="min-h-screen bg-gray-50 dark:bg-gray-900">

        {{-- Compact Header --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm">
            <div class="px-4 py-2.5">
                {{-- Title + Semester --}}
                <div class="flex items-center justify-between mb-2">
                    <h1 class="text-base font-bold text-gray-800 dark:text-gray-100">Dars jadvali</h1>
                    <div class="relative">
                        <button @click="semesterOpen = !semesterOpen" class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            {{ $selectedSemester['name'] ?? '' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="semesterOpen" @click.away="semesterOpen = false" x-cloak
                             class="absolute right-0 top-full mt-1 w-56 bg-white dark:bg-gray-700 rounded-xl shadow-xl border border-gray-200 dark:border-gray-600 z-50 py-1 max-h-60 overflow-y-auto">
                            @foreach($semesters as $sem)
                                <a href="{{ route('student.schedule', ['semester_id' => $sem['id']]) }}"
                                   class="block px-4 py-2 text-sm {{ $sem['id'] == ($selectedSemester['id'] ?? null) ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                    {{ $sem['name'] }} ({{ $sem['education_year']['name'] }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Week Navigation --}}
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2">
                        @if($prevWeekId)
                            <a href="{{ route('student.schedule', ['semester_id' => $selectedSemester['id'] ?? '', 'week_id' => $prevWeekId]) }}"
                               class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                            </a>
                        @else
                            <div class="w-7 h-7"></div>
                        @endif
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            @if($weekStart && $weekEnd)
                                {{ $weekStart->format('d M') }} - {{ $weekEnd->format('d M Y') }}
                            @endif
                        </span>
                        @if($nextWeekId)
                            <a href="{{ route('student.schedule', ['semester_id' => $selectedSemester['id'] ?? '', 'week_id' => $nextWeekId]) }}"
                               class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        @else
                            <div class="w-7 h-7"></div>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5">
                        {{-- Hafta / Kun toggle --}}
                        <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                            <button @click="view = 'week'" :class="view === 'week' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-800 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'" class="px-2.5 py-1 text-xs font-medium rounded-md transition">Hafta</button>
                            <button @click="view = 'day'" :class="view === 'day' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-800 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'" class="px-2.5 py-1 text-xs font-medium rounded-md transition">Kun</button>
                        </div>
                        {{-- Bugun button --}}
                        @php
                            $todayWeek = $weeks->first(function ($w) {
                                return \Carbon\Carbon::now()->between(\Carbon\Carbon::createFromTimestamp($w['start_date']), \Carbon\Carbon::createFromTimestamp($w['end_date']));
                            });
                        @endphp
                        @if($todayWeek && $todayWeek['id'] != $selectedWeekId)
                            <a href="{{ route('student.schedule', ['semester_id' => $selectedSemester['id'] ?? '', 'week_id' => $todayWeek['id']]) }}"
                               class="px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
                                Bugun
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Day selector row --}}
                <div class="flex gap-1">
                    @foreach($weekDates as $wd)
                        @php $color = $dayColors[$wd['day_en']]; @endphp
                        <button @click="view = 'day'; selectedDay = '{{ $wd['day_en'] }}'"
                                :class="view === 'day' && selectedDay === '{{ $wd['day_en'] }}' ? '{{ $color['accent'] }} text-white shadow-md scale-105' : '{{ $wd['is_today'] ? $color['light'] . ' ' . $color['text'] . ' ring-1 ring-current' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}'"
                                class="flex-1 flex flex-col items-center py-1.5 rounded-xl transition-all duration-200">
                            <span class="text-[10px] font-medium leading-tight">{{ $wd['day_short'] }}</span>
                            <span class="text-sm font-bold leading-tight">{{ $wd['day_num'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- HAFTALIK KO'RINISH --}}
        <div x-show="view === 'week'" class="px-3 py-3 space-y-3">
            @foreach($days as $dayEn => $dayUz)
                @php $color = $dayColors[$dayEn]; @endphp
                <div>
                    {{-- Kun sarlavhasi --}}
                    <div class="flex items-center gap-2 mb-1.5 px-1">
                        <div class="w-2 h-2 rounded-full {{ $color['dot'] }}"></div>
                        <h3 class="text-xs font-bold uppercase tracking-wider {{ $color['text'] }}">
                            {{ $dayUz }}
                            @foreach($weekDates as $wd)
                                @if($wd['day_en'] === $dayEn)
                                    <span class="font-normal normal-case tracking-normal text-gray-400 dark:text-gray-500 ml-1">{{ $wd['day_num'] }} {{ $wd['month'] }}</span>
                                @endif
                            @endforeach
                        </h3>
                        @if($dayEn === $todayDayEn)
                            <span class="text-[9px] font-bold uppercase bg-indigo-500 text-white px-1.5 py-0.5 rounded-full">Bugun</span>
                        @endif
                    </div>

                    @if(isset($groupedSchedule[$dayEn]) && count($groupedSchedule[$dayEn]) > 0)
                        <div class="space-y-1.5">
                            @foreach($groupedSchedule[$dayEn] as $lesson)
                                <div class="flex gap-2.5 items-start">
                                    {{-- Vaqt --}}
                                    <div class="w-12 flex-shrink-0 text-right pt-2">
                                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($lesson['lessonPair']['start_time'])->format('H:i') }}</div>
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ \Carbon\Carbon::parse($lesson['lessonPair']['end_time'])->format('H:i') }}</div>
                                    </div>
                                    {{-- Dars kartochkasi --}}
                                    <div class="flex-1 {{ $color['bg'] }} border {{ $color['border'] }} rounded-xl px-3 py-2 relative overflow-hidden">
                                        <div class="absolute left-0 top-0 bottom-0 w-1 {{ $color['accent'] }} rounded-l-xl"></div>
                                        <div class="pl-1.5">
                                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-tight">{{ $lesson['subject']['name'] }}</h4>
                                            <div class="flex items-center gap-3 mt-1">
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-0.5">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                                    {{ $lesson['employee']['name'] }}
                                                </span>
                                                @if($lesson['auditorium']['name'])
                                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-0.5">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                                        {{ $lesson['auditorium']['name'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($lesson['training_type'])
                                                <span class="inline-block mt-1 text-[10px] font-medium {{ $color['text'] }} {{ $color['light'] }} px-1.5 py-0.5 rounded-md">{{ $lesson['training_type'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex gap-2.5 items-center">
                            <div class="w-12 flex-shrink-0"></div>
                            <div class="flex-1 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl py-4 text-center">
                                <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">Dars yo'q</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- KUNLIK KO'RINISH --}}
        <div x-show="view === 'day'" x-cloak class="px-3 py-3">
            @foreach($days as $dayEn => $dayUz)
                @php $color = $dayColors[$dayEn]; @endphp
                <div x-show="selectedDay === '{{ $dayEn }}'">
                    {{-- Kun sarlavhasi --}}
                    <div class="flex items-center gap-2 mb-3 px-1">
                        <div class="w-2.5 h-2.5 rounded-full {{ $color['dot'] }}"></div>
                        <h3 class="text-sm font-bold {{ $color['text'] }}">{{ $dayUz }}</h3>
                        @if($dayEn === $todayDayEn)
                            <span class="text-[9px] font-bold uppercase bg-indigo-500 text-white px-1.5 py-0.5 rounded-full">Bugun</span>
                        @endif
                    </div>

                    @if(isset($groupedSchedule[$dayEn]) && count($groupedSchedule[$dayEn]) > 0)
                        <div class="space-y-2">
                            @foreach($groupedSchedule[$dayEn] as $lesson)
                                <div class="flex gap-3 items-start">
                                    {{-- Vaqt --}}
                                    <div class="w-14 flex-shrink-0 text-right pt-3">
                                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($lesson['lessonPair']['start_time'])->format('H:i') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ \Carbon\Carbon::parse($lesson['lessonPair']['end_time'])->format('H:i') }}</div>
                                    </div>
                                    {{-- Dars kartochkasi (kattaroq) --}}
                                    <div class="flex-1 {{ $color['bg'] }} border {{ $color['border'] }} rounded-2xl px-4 py-3 relative overflow-hidden">
                                        <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $color['accent'] }} rounded-l-2xl"></div>
                                        <div class="pl-2">
                                            <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 leading-snug">{{ $lesson['subject']['name'] }}</h4>
                                            @if($lesson['training_type'])
                                                <span class="inline-block mt-1 text-[11px] font-medium {{ $color['text'] }} {{ $color['light'] }} px-2 py-0.5 rounded-md">{{ $lesson['training_type'] }}</span>
                                            @endif
                                            <div class="flex flex-col gap-1 mt-2">
                                                <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                                    {{ $lesson['employee']['name'] }}
                                                </span>
                                                @if($lesson['auditorium']['name'])
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                                        {{ $lesson['auditorium']['name'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center justify-center py-16">
                            <div class="text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                <p class="text-sm text-gray-400 dark:text-gray-500 font-medium">Bu kunda dars yo'q</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Hafta tanlash (select dropdown) --}}
        <div class="px-4 pb-4">
            <form id="weekForm" action="{{ route('student.schedule') }}" method="GET">
                <input type="hidden" name="semester_id" value="{{ $selectedSemester['id'] ?? '' }}">
                <select name="week_id" onchange="document.getElementById('weekForm').submit()"
                        class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($weeks as $week)
                        @php
                            $ws = \Carbon\Carbon::createFromTimestamp($week['start_date']);
                            $we = \Carbon\Carbon::createFromTimestamp($week['end_date']);
                        @endphp
                        <option value="{{ $week['id'] }}" {{ $week['id'] == $selectedWeekId ? 'selected' : '' }}>
                            {{ $ws->format('d M') }} - {{ $we->format('d M Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</x-student-app-layout>
