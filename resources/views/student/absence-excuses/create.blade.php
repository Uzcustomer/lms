<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sababli dars qoldirish arizasi
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8" x-data="absenceExcuseForm()" x-init="init()">

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

            <form method="POST" action="{{ route('student.absence-excuses.store') }}"
                  enctype="multipart/form-data">
                @csrf

                {{-- ===== 1. ARIZA MA'LUMOTLARI ===== --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                    <div class="p-6 space-y-5">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Ariza ma'lumotlari</h3>

                        {{-- Sabab tanlash --}}
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">
                                Sabab <span class="text-red-500">*</span>
                            </label>
                            <select name="reason" id="reason" required x-model="reason"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sababni tanlang</option>
                                @foreach($reasons as $key => $data)
                                    <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $data['label'] }}</option>
                                @endforeach
                            </select>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sabab info --}}
                        <div x-show="selectedReason" x-transition x-cloak
                             class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="text-sm">
                                    <p class="font-medium text-blue-800 mb-1">Talab qilinadigan hujjat:</p>
                                    <p class="text-blue-700" x-text="selectedReason?.document"></p>
                                    <template x-if="selectedReason?.max_days">
                                        <p class="mt-1 text-xs text-blue-600">
                                            Maksimum: <span x-text="selectedReason?.max_days" class="font-bold"></span> kun
                                        </p>
                                    </template>
                                    <template x-if="selectedReason?.note">
                                        <p class="mt-1 text-xs text-blue-600 italic" x-text="selectedReason?.note"></p>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Hujjat raqami --}}
                        <div>
                            <label for="doc_number" class="block text-sm font-medium text-gray-700 mb-1">
                                Hujjat raqami <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="doc_number" id="doc_number"
                                   value="{{ old('doc_number') }}"
                                   placeholder="Masalan: 123/2026"
                                   required
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('doc_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sana oralig'i --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Sabab sana oralig'i (faqat o'tmish) <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="start_date" class="block text-xs text-gray-500 mb-1">Boshlanish</label>
                                    <input type="date" name="start_date" id="start_date"
                                           x-model="startDate"
                                           @change="onDateChange()"
                                           :max="yesterday"
                                           value="{{ old('start_date') }}" required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('start_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="end_date" class="block text-xs text-gray-500 mb-1">Tugash</label>
                                    <input type="date" name="end_date" id="end_date"
                                           x-model="endDate"
                                           @change="onDateChange()"
                                           :max="yesterday"
                                           value="{{ old('end_date') }}" required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('end_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Deadline warning --}}
                            <div x-show="deadlineWarning" x-transition x-cloak class="mt-2">
                                <div :class="deadlineExpired ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'"
                                     class="border rounded-lg p-3">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2 flex-shrink-0"
                                             :class="deadlineExpired ? 'text-red-500' : 'text-amber-500'"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <p class="text-sm font-medium"
                                           :class="deadlineExpired ? 'text-red-700' : 'text-amber-700'"
                                           x-text="deadlineWarning"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Kun hisobi --}}
                            <div x-show="startDate && endDate && totalDays > 0 && !deadlineExpired" x-transition x-cloak class="mt-2">
                                <p class="text-sm text-gray-600">
                                    Jami: <span class="font-semibold text-indigo-600" x-text="totalDays"></span> kun
                                </p>
                            </div>
                        </div>

                        {{-- Fayl yuklash --}}
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-1">
                                Tasdiqlovchi hujjat (spravka) <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="file" id="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0 file:text-sm file:font-semibold
                                          file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-1 text-xs text-gray-500">
                                Ruxsat etilgan formatlar: PDF, JPG, PNG, DOC, DOCX. Maksimum hajm: 10MB
                            </p>
                            @error('file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- ===== 2. O'TKAZIB YUBORILGAN NAZORATLAR ===== --}}

                {{-- Loading --}}
                <div x-show="loading" x-cloak class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                    <div class="p-6 text-center">
                        <svg class="animate-spin mx-auto h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">Nazoratlar tekshirilmoqda...</p>
                    </div>
                </div>

                {{-- Nazorat topilmadi --}}
                <div x-show="!loading && searched && assessments.length === 0" x-cloak
                     class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nazorat topilmadi</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Siz kelmagan kunlarda JN, MT, OSKI yoki Test nazorat turlari rejalashtirilmagan.
                        </p>
                    </div>
                </div>

                {{-- Nazoratlar ro'yxati --}}
                <div x-show="assessments.length > 0" x-transition x-cloak>

                    {{-- Sticky indikator --}}
                    <div class="sticky top-0 z-10 bg-white border border-gray-200 rounded-lg shadow-sm mb-4 p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="text-sm">
                                    <span class="font-medium text-gray-700">Nazoratlar:</span>
                                    <span class="ml-1 font-bold text-indigo-600" x-text="assessments.length"></span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span x-text="assessments.filter(a => a.makeup_date).length"></span>
                                    <span class="text-gray-400">/</span>
                                    <span x-text="assessments.length"></span>
                                    <span class="text-gray-400">sana tanlangan</span>
                                </div>
                            </div>
                            <div x-show="!allDatesSelected" class="text-xs text-amber-600 font-medium">
                                Barcha nazoratlar uchun sana tanlang
                            </div>
                        </div>
                    </div>

                    {{-- Har bir nazorat --}}
                    <div class="space-y-4 mb-4">
                        <template x-for="(item, index) in assessments" :key="index">
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4">
                                    {{-- Fan va nazorat turi --}}
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm font-semibold text-gray-900" x-text="item.subject_name"></span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                                  :class="getColor(item.assessment_type)"
                                                  x-text="getLabel(item.assessment_type)"></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Asl sana: <span class="font-medium" x-text="fmtDate(item.original_date)"></span>
                                        </div>
                                    </div>

                                    {{-- Tanlangan sana --}}
                                    <div class="mb-2 flex items-center justify-between">
                                        <div class="text-sm">
                                            <span class="text-gray-500">Qayta topshirish:</span>
                                            <template x-if="item.makeup_date">
                                                <span class="ml-1 font-semibold text-indigo-600" x-text="fmtDate(item.makeup_date)"></span>
                                            </template>
                                            <template x-if="!item.makeup_date">
                                                <span class="ml-1 text-amber-500 italic">tanlanmagan</span>
                                            </template>
                                        </div>
                                        <template x-if="item.makeup_date">
                                            <button type="button" @click="item.makeup_date = ''"
                                                    class="text-xs text-red-500 hover:text-red-700">Bekor qilish</button>
                                        </template>
                                    </div>

                                    {{-- Scroll calendar --}}
                                    <div class="relative">
                                        <div class="flex overflow-x-auto gap-2 py-2 px-1 pb-3"
                                             style="-webkit-overflow-scrolling: touch; scrollbar-width: thin;">
                                            <template x-for="day in calendarDays" :key="day.date">
                                                <button type="button"
                                                        @click="!day.isSunday && pickDate(index, day.date)"
                                                        :disabled="day.isSunday"
                                                        :class="{
                                                            'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300': item.makeup_date === day.date,
                                                            'bg-gray-100 text-gray-300 cursor-not-allowed': day.isSunday,
                                                            'bg-white hover:bg-indigo-50 border border-gray-200 text-gray-700': !day.isSunday && item.makeup_date !== day.date,
                                                            'ring-2 ring-indigo-200': day.isToday && item.makeup_date !== day.date
                                                        }"
                                                        class="flex-shrink-0 w-14 h-16 rounded-xl flex flex-col items-center justify-center text-sm font-medium transition-all duration-150">
                                                    <span class="text-[10px] uppercase font-semibold leading-none"
                                                          :class="{'text-indigo-200': item.makeup_date === day.date, 'text-gray-400': item.makeup_date !== day.date && !day.isSunday, 'text-gray-300': day.isSunday}"
                                                          x-text="day.weekDay"></span>
                                                    <span class="text-lg font-bold leading-tight mt-0.5" x-text="day.dayNum"></span>
                                                    <span class="text-[10px] leading-none"
                                                          :class="{'text-indigo-200': item.makeup_date === day.date, 'text-gray-400': item.makeup_date !== day.date && !day.isSunday, 'text-gray-300': day.isSunday}"
                                                          x-text="day.month"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Hidden inputs --}}
                                    <input type="hidden" :name="'makeup_dates['+index+'][subject_name]'" :value="item.subject_name">
                                    <input type="hidden" :name="'makeup_dates['+index+'][subject_id]'" :value="item.subject_id || ''">
                                    <input type="hidden" :name="'makeup_dates['+index+'][assessment_type]'" :value="item.assessment_type">
                                    <input type="hidden" :name="'makeup_dates['+index+'][assessment_type_code]'" :value="item.assessment_type_code">
                                    <input type="hidden" :name="'makeup_dates['+index+'][original_date]'" :value="item.original_date">
                                    <input type="hidden" :name="'makeup_dates['+index+'][makeup_date]'" :value="item.makeup_date || ''">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex items-center justify-between mt-6">
                    <a href="{{ route('student.absence-excuses.index') }}"
                       class="text-gray-600 hover:text-gray-800 text-sm">Orqaga</a>
                    <button type="submit"
                            :disabled="!canSubmit"
                            :class="canSubmit ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                            class="px-6 py-2.5 text-white font-semibold rounded-md
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        Ariza yuborish
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    function absenceExcuseForm() {
        return {
            reason: '{{ old("reason", "") }}',
            startDate: '{{ old("start_date", "") }}',
            endDate: '{{ old("end_date", "") }}',
            deadlineWarning: '',
            deadlineExpired: false,
            assessments: [],
            calendarDays: [],
            loading: false,
            searched: false,
            reasons: @js($reasons),

            get selectedReason() {
                return this.reason ? this.reasons[this.reason] : null;
            },
            get yesterday() {
                const d = new Date();
                d.setDate(d.getDate() - 1);
                return d.toISOString().split('T')[0];
            },
            get totalDays() {
                if (!this.startDate || !this.endDate) return 0;
                const s = new Date(this.startDate);
                const e = new Date(this.endDate);
                if (e < s) return 0;
                return Math.floor((e - s) / (1000*60*60*24)) + 1;
            },
            get allDatesSelected() {
                return this.assessments.length > 0 && this.assessments.every(a => a.makeup_date);
            },
            get canSubmit() {
                if (!this.reason || !this.startDate || !this.endDate) return false;
                if (this.deadlineExpired) return false;
                if (this.assessments.length > 0 && !this.allDatesSelected) return false;
                return true;
            },

            init() {
                this.buildCalendar();
                this.checkDeadline();
                if (this.startDate && this.endDate) {
                    this.fetchAssessments();
                }
            },

            buildCalendar() {
                const today = new Date();
                this.calendarDays = [];
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

            checkDeadline() {
                this.deadlineWarning = '';
                this.deadlineExpired = false;
                if (!this.endDate) return;
                const end = new Date(this.endDate);
                let nextDay = new Date(end);
                nextDay.setDate(nextDay.getDate() + 1);
                if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1);
                const today = new Date();
                today.setHours(0,0,0,0);
                nextDay.setHours(0,0,0,0);
                if (today < nextDay) return;
                const diffDays = Math.floor((today - nextDay) / (1000*60*60*24));
                if (diffDays > 10) {
                    this.deadlineWarning = "Hujjatlarni taqdim qilish muddati o'tgan (" + diffDays + " kun o'tdi). Tugash sanasidan keyin 10 kun ichida ariza topshirishingiz kerak edi.";
                    this.deadlineExpired = true;
                } else {
                    this.deadlineWarning = "Ariza topshirish uchun " + (10 - diffDays) + " kun qoldi.";
                    this.deadlineExpired = false;
                }
            },

            onDateChange() {
                this.checkDeadline();
                if (this.startDate && this.endDate && !this.deadlineExpired) {
                    const s = new Date(this.startDate);
                    const e = new Date(this.endDate);
                    if (e >= s) {
                        this.fetchAssessments();
                    }
                }
            },

            async fetchAssessments() {
                this.loading = true;
                this.searched = false;
                this.assessments = [];
                try {
                    const resp = await fetch('{{ route("student.absence-excuses.missed-assessments") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            start_date: this.startDate,
                            end_date: this.endDate,
                        }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.assessments = (data.assessments || []).map(a => ({...a, makeup_date: ''}));
                    }
                } catch (e) {
                    console.error('Nazoratlarni yuklashda xatolik:', e);
                }
                this.loading = false;
                this.searched = true;
            },

            pickDate(idx, dateStr) {
                if (this.assessments[idx].makeup_date === dateStr) {
                    this.assessments[idx].makeup_date = '';
                } else {
                    this.assessments[idx].makeup_date = dateStr;
                }
            },

            getColor(type) {
                return {'jn':'bg-blue-100 text-blue-800','mt':'bg-purple-100 text-purple-800','oski':'bg-orange-100 text-orange-800','test':'bg-red-100 text-red-800'}[type] || 'bg-gray-100 text-gray-800';
            },
            getLabel(type) {
                return {'jn':'Joriy nazorat','mt':'Mustaqil ta\'lim','oski':'OSKI','test':'Yakuniy test'}[type] || type;
            },
            fmtDate(s) {
                if (!s) return '';
                const d = new Date(s);
                return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0') + '.' + d.getFullYear();
            }
        };
    }
    </script>
    @endpush
</x-student-app-layout>
