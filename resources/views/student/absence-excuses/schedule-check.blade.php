<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Qayta topshirish sanalarini tanlash
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8"
             x-data="{
                makeups: @js($excuse->makeups->map(fn($m) => ['id' => $m->id, 'subject_name' => $m->subject_name, 'assessment_type' => $m->assessment_type, 'assessment_type_label' => $m->assessment_type_label, 'original_date' => $m->original_date->format('Y-m-d'), 'makeup_date' => $m->makeup_date ? $m->makeup_date->format('Y-m-d') : ''])),
                maxDays: {{ $absentDaysCount }},
                calendarDays: [],

                init() {
                    const today = new Date();
                    for (let i = 0; i < 60; i++) {
                        const d = new Date(today);
                        d.setDate(d.getDate() + i);
                        this.calendarDays.push({
                            date: d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'),
                            dayNum: d.getDate(),
                            weekDay: ['Ya','Du','Se','Cho','Pa','Ju','Sha'][d.getDay()],
                            month: ['Yan','Fev','Mar','Apr','May','Iyun','Iyul','Avg','Sen','Okt','Noy','Dek'][d.getMonth()],
                            isSunday: d.getDay() === 0,
                            isToday: i === 0
                        });
                    }
                },

                selectDate(makeupIndex, dateStr) {
                    if (this.makeups[makeupIndex].makeup_date === dateStr) {
                        this.makeups[makeupIndex].makeup_date = '';
                        return;
                    }
                    const currentDates = new Set(
                        this.makeups
                            .filter((m, idx) => idx !== makeupIndex && m.makeup_date)
                            .map(m => m.makeup_date)
                    );
                    currentDates.add(dateStr);
                    if (currentDates.size <= this.maxDays) {
                        this.makeups[makeupIndex].makeup_date = dateStr;
                    }
                },

                get uniqueSelectedDates() {
                    return [...new Set(this.makeups.map(m => m.makeup_date).filter(d => d))];
                },

                get allSelected() {
                    return this.makeups.length > 0 && this.makeups.every(m => m.makeup_date);
                },

                get canSubmit() {
                    return this.allSelected && this.uniqueSelectedDates.length <= this.maxDays;
                },

                getAssessmentColor(type) {
                    const colors = {
                        'jn': 'bg-blue-100 text-blue-800',
                        'mt': 'bg-purple-100 text-purple-800',
                        'oski': 'bg-orange-100 text-orange-800',
                        'test': 'bg-red-100 text-red-800'
                    };
                    return colors[type] || 'bg-gray-100 text-gray-800';
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0') + '.' + d.getFullYear();
                }
             }">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Ariza ma'lumotlari --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Ariza #{{ $excuse->id }}</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $excuse->reason_label }} &mdash;
                                {{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}
                                <span class="text-gray-400">({{ $absentDaysCount }} kun)</span>
                            </p>
                        </div>
                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                            bg-{{ $excuse->status_color }}-100 text-{{ $excuse->status_color }}-800">
                            {{ $excuse->status_label }}
                        </span>
                    </div>
                </div>
            </div>

            @if($excuse->makeups->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nazorat topilmadi</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Siz kelmagan kunlarda JN, MT, OSKI yoki Test nazorat turlari rejalashtirilmagan.
                        </p>
                        <div class="mt-4">
                            <a href="{{ route('student.absence-excuses.show', $excuse->id) }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                Ariza sahifasiga o'tish
                            </a>
                        </div>
                    </div>
                </div>
            @else
                {{-- Cheklov indikatori (sticky) --}}
                <div class="sticky top-0 z-10 bg-white border border-gray-200 rounded-lg shadow-sm mb-4 p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Tanlangan kunlar:</span>
                                <span class="ml-1 font-bold"
                                      :class="uniqueSelectedDates.length > maxDays ? 'text-red-600' : 'text-indigo-600'"
                                      x-text="uniqueSelectedDates.length"></span>
                                <span class="text-gray-400">/</span>
                                <span class="font-bold text-gray-700" x-text="maxDays"></span>
                            </div>
                            <div class="text-sm text-gray-500">
                                <span x-text="makeups.filter(m => m.makeup_date).length"></span>
                                <span class="text-gray-400">/</span>
                                <span x-text="makeups.length"></span>
                                <span class="text-gray-400">nazorat tanlangan</span>
                            </div>
                        </div>
                        <div x-show="!allSelected" class="text-xs text-amber-600 font-medium">
                            Barcha nazoratlar uchun sana tanlang
                        </div>
                        <div x-show="allSelected && uniqueSelectedDates.length > maxDays" class="text-xs text-red-600 font-medium">
                            Kunlar soni limitdan oshib ketdi
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('student.absence-excuses.store-makeup-dates', $excuse->id) }}">
                    @csrf

                    <div class="space-y-4">
                        <template x-for="(makeup, index) in makeups" :key="makeup.id">
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4">
                                    {{-- Nazorat ma'lumotlari --}}
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm font-semibold text-gray-900" x-text="makeup.subject_name"></span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                                  :class="getAssessmentColor(makeup.assessment_type)"
                                                  x-text="makeup.assessment_type_label"></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Asl sana: <span class="font-medium" x-text="formatDate(makeup.original_date)"></span>
                                        </div>
                                    </div>

                                    {{-- Tanlangan sana ko'rsatish --}}
                                    <div class="mb-2 flex items-center justify-between">
                                        <div class="text-sm">
                                            <span class="text-gray-500">Qayta topshirish:</span>
                                            <template x-if="makeup.makeup_date">
                                                <span class="ml-1 font-semibold text-indigo-600" x-text="formatDate(makeup.makeup_date)"></span>
                                            </template>
                                            <template x-if="!makeup.makeup_date">
                                                <span class="ml-1 text-amber-500 italic">tanlanmagan</span>
                                            </template>
                                        </div>
                                        <template x-if="makeup.makeup_date">
                                            <button type="button" @click="makeup.makeup_date = ''"
                                                    class="text-xs text-red-500 hover:text-red-700">
                                                Bekor qilish
                                            </button>
                                        </template>
                                    </div>

                                    {{-- Scroll calendar --}}
                                    <div class="relative">
                                        <div class="flex overflow-x-auto gap-2 py-2 px-1 pb-3"
                                             style="-webkit-overflow-scrolling: touch; scrollbar-width: thin;">
                                            <template x-for="day in calendarDays" :key="day.date">
                                                <button type="button"
                                                        @click="!day.isSunday && selectDate(index, day.date)"
                                                        :disabled="day.isSunday"
                                                        :class="{
                                                            'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300': makeup.makeup_date === day.date,
                                                            'bg-gray-100 text-gray-300 cursor-not-allowed': day.isSunday,
                                                            'bg-white hover:bg-indigo-50 border border-gray-200 text-gray-700': !day.isSunday && makeup.makeup_date !== day.date,
                                                            'ring-2 ring-indigo-200': day.isToday && makeup.makeup_date !== day.date
                                                        }"
                                                        class="flex-shrink-0 w-14 h-16 rounded-xl flex flex-col items-center justify-center text-sm font-medium transition-all duration-150">
                                                    <span class="text-[10px] uppercase font-semibold leading-none"
                                                          :class="{'text-indigo-200': makeup.makeup_date === day.date, 'text-gray-400': makeup.makeup_date !== day.date && !day.isSunday, 'text-gray-300': day.isSunday}"
                                                          x-text="day.weekDay"></span>
                                                    <span class="text-lg font-bold leading-tight mt-0.5" x-text="day.dayNum"></span>
                                                    <span class="text-[10px] leading-none"
                                                          :class="{'text-indigo-200': makeup.makeup_date === day.date, 'text-gray-400': makeup.makeup_date !== day.date && !day.isSunday, 'text-gray-300': day.isSunday}"
                                                          x-text="day.month"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Hidden input --}}
                                    <input type="hidden"
                                           :name="'makeup_dates[' + makeup.id + ']'"
                                           :value="makeup.makeup_date">
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <a href="{{ route('student.absence-excuses.show', $excuse->id) }}"
                           class="text-gray-600 hover:text-gray-800 text-sm">
                            &larr; Keyinroq tanlash
                        </a>
                        <button type="submit"
                                :disabled="!canSubmit"
                                :class="canSubmit ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                                class="px-6 py-2.5 text-white font-semibold rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            Sanalarni saqlash
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-student-app-layout>
