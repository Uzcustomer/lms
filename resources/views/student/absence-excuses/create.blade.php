<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sababli dars qoldirish arizasi
        </h2>
    </x-slot>

    @push('styles')
    <style>
        /* ======= CARD STYLES ======= */
        .ae-card {
            background: #fff; border-radius: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.06);
            border: 1px solid #f1f5f9; overflow: visible;
        }
        .ae-card-header {
            padding: 18px 24px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 12px;
            border-radius: 18px 18px 0 0; background: #fff;
        }
        .ae-card-header-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ae-card-body { padding: 24px; }

        /* ======= FORM ELEMENTS ======= */
        .ae-label { display: block; font-size: 16px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .ae-label .req { color: #ef4444; margin-left: 2px; }
        .ae-input, .ae-select, .ae-textarea {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 14px 16px; font-size: 17px; color: #1e293b;
            transition: border-color .15s, box-shadow .15s; background: #fff;
        }
        .ae-input:focus, .ae-select:focus, .ae-textarea:focus {
            outline: none; border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,0.15);
        }
        .ae-file-zone {
            border: 2px dashed #d1d5db; border-radius: 12px; padding: 10px 16px;
            text-align: center; cursor: pointer; transition: all .2s; background: #fafbfc;
            display: flex; align-items: center; justify-content: center;
            min-height: 52px;
        }
        .ae-file-zone:hover { border-color: #818cf8; background: #eef2ff; }
        .ae-file-zone.has-file { border-color: #34d399; background: #ecfdf5; }

        /* ======= ASSESSMENT CARD ======= */
        .assessment-card {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px;
            transition: all .2s; overflow: visible;
        }
        .assessment-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 14px rgba(0,0,0,.05); }
        .assessment-card-head {
            padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 6px;
            border-radius: 16px 16px 0 0; background: #fff;
        }

        /* ======= SUBMIT BTN ======= */
        .ae-submit {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 32px; border-radius: 14px; font-size: 18px; font-weight: 700;
            color: #fff; border: none; cursor: pointer; transition: all .2s;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            box-shadow: 0 4px 14px rgba(79,70,229,0.3);
        }
        .ae-submit:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79,70,229,0.4);
        }
        .ae-submit:disabled {
            background: #cbd5e1; box-shadow: none; cursor: not-allowed; transform: none;
        }

        /* ======= BADGE ======= */
        .badge-jn { background: #dbeafe; color: #1e40af; }
        .badge-mt { background: #ede9fe; color: #5b21b6; }
        .badge-oski { background: #fff7ed; color: #c2410c; }
        .badge-test { background: #fef2f2; color: #b91c1c; }

        /* ======= FLATPICKR INDIGO THEME ======= */
        .flatpickr-calendar {
            border-radius: 12px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,.12) !important;
            border: 1.5px solid #e2e8f0 !important;
            font-family: inherit !important;
        }
        .flatpickr-months {
            background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
            border-radius: 10px 10px 0 0 !important;
            padding: 4px 0 !important;
        }
        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month,
        .flatpickr-current-month .cur-month,
        .flatpickr-current-month input.cur-year { color: #fff !important; fill: #fff !important; font-weight: 700 !important; }
        .flatpickr-months .flatpickr-prev-month,
        .flatpickr-months .flatpickr-next-month { fill: #fff !important; color: #fff !important; }
        .flatpickr-months .flatpickr-prev-month:hover,
        .flatpickr-months .flatpickr-next-month:hover { background: rgba(255,255,255,.2) !important; border-radius: 6px; }
        .flatpickr-weekdays { background: #f8fafc !important; border-bottom: 1px solid #e2e8f0; }
        span.flatpickr-weekday { color: #64748b !important; font-weight: 700 !important; font-size: 11px !important; }
        .flatpickr-day {
            font-weight: 600 !important; border-radius: 6px !important;
            color: #334155 !important; font-size: 13px !important;
        }
        .flatpickr-day:hover { background: #eef2ff !important; border-color: #eef2ff !important; }
        .flatpickr-day.today { border-color: #818cf8 !important; background: #eef2ff !important; color: #4f46e5 !important; }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #4f46e5 !important; border-color: #4f46e5 !important; color: #fff !important;
        }
        .flatpickr-day.startRange { border-radius: 6px 2px 2px 6px !important; }
        .flatpickr-day.endRange { border-radius: 2px 6px 6px 2px !important; }
        .flatpickr-day.startRange.endRange { border-radius: 6px !important; }
        .flatpickr-day.inRange {
            background: #e0e7ff !important; border-color: #e0e7ff !important;
            color: #3730a3 !important; box-shadow: -5px 0 0 #e0e7ff, 5px 0 0 #e0e7ff !important;
        }
        .flatpickr-day.flatpickr-disabled { color: #d1d5db !important; }
        .flatpickr-day.flatpickr-disabled:hover { background: transparent !important; border-color: transparent !important; }

        /* Mini flatpickr for assessment cards */
        .mini-fp-wrap .flatpickr-calendar { font-size: 12px !important; }
        .mini-fp-wrap .flatpickr-day { height: 30px !important; line-height: 30px !important; font-size: 11px !important; }
        .mini-fp-wrap .flatpickr-months { padding: 2px 0 !important; }
        .mini-fp-wrap span.flatpickr-weekday { font-size: 9px !important; }

        /* ======= MOBILE ======= */
        @media (max-width: 640px) {
            .ae-card-header { padding: 14px 16px; }
            .ae-card-body { padding: 16px; }
            .assessment-card-head { padding: 12px 14px; }
        }
    </style>
    @endpush

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="absenceForm()" x-init="init()">

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('student.absence-excuses.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- ===== CARD 1: ASOSIY MA'LUMOTLAR ===== --}}
                <div class="ae-card mb-5">
                    <div class="ae-card-header">
                        <div class="ae-card-header-icon bg-indigo-50">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Ariza ma'lumotlari</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Sabab, hujjat raqami va tasdiqlovchi fayl</p>
                        </div>
                    </div>
                    <div class="ae-card-body space-y-6">
                        {{-- ROW 1: Sabab + Hujjat raqami + Tasdiqlovchi hujjat --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="reason" class="ae-label">Sabab <span class="req">*</span></label>
                                <select name="reason" id="reason" required x-model="reason"
                                        @change="onReasonChange()" class="ae-select">
                                    <option value="">Sababni tanlang...</option>
                                    @foreach($reasons as $key => $data)
                                        <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $data['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('reason')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="doc_number" class="ae-label">Hujjat raqami <span class="req">*</span></label>
                                <input type="text" name="doc_number" id="doc_number"
                                       value="{{ old('doc_number') }}" placeholder="Masalan: 123/2026"
                                       required class="ae-input">
                                @error('doc_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="ae-label">Tasdiqlovchi hujjat <span class="req">*</span></label>
                                <label for="file" class="ae-file-zone block" :class="fileName ? 'has-file' : ''"
                                       @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50')"
                                       @dragleave.prevent="$el.classList.remove('border-indigo-400','bg-indigo-50')"
                                       @drop.prevent="handleDrop($event)">
                                    <div x-show="!fileName" class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500">Faylni <span class="text-indigo-600 font-semibold">tanlang</span></span>
                                    </div>
                                    <div x-show="fileName" class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-sm font-semibold text-emerald-700 truncate" x-text="fileName"></span>
                                    </div>
                                </label>
                                <input type="file" name="file" id="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       class="hidden" @change="fileName = $event.target.files[0]?.name || ''">
                                @error('file')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Sabab info (full width tepada) --}}
                        <div x-show="selectedReason" x-transition x-cloak
                             class="flex items-start gap-3 bg-indigo-50/60 border border-indigo-100 rounded-xl p-4">
                            <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-content flex-shrink-0 mt-0.5"
                                 style="display:flex;align-items:center;justify-content:center;">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-indigo-900 text-base">Talab qilinadigan hujjat:</p>
                                <p class="text-indigo-700 mt-1 text-base" x-text="selectedReason?.document"></p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <template x-if="selectedReason?.max_days">
                                        <span class="inline-flex items-center px-3 py-1 text-sm font-bold bg-indigo-100 text-indigo-700 rounded-lg">
                                            Maksimum: <span x-text="selectedReason?.max_days" class="ml-1"></span> kun
                                        </span>
                                    </template>
                                    <template x-if="selectedReason?.note">
                                        <span class="inline-flex items-center px-3 py-1 text-sm italic bg-indigo-50 text-indigo-600 rounded-lg" x-text="selectedReason?.note"></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- ROW 2: Sana oralig'i --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="ae-label">Sana oralig'i <span class="req">*</span></label>

                                <div x-show="!reason" class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3">
                                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <p class="text-sm font-medium text-amber-700">Avval sababni tanlang</p>
                                </div>

                                <div x-show="reason" x-cloak>
                                    <input type="text" x-ref="mainCalendar" readonly
                                           @click="initMainCal(true)"
                                           class="ae-input cursor-pointer"
                                           placeholder="Boshlanish — Tugash">

                                    {{-- Deadline warning --}}
                                    <div x-show="deadlineWarning" x-cloak class="mt-2">
                                        <div :class="deadlineExpired ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'"
                                             class="border rounded-lg p-2 flex items-center gap-2">
                                            <svg class="w-4 h-4 flex-shrink-0"
                                                 :class="deadlineExpired ? 'text-red-500' : 'text-amber-500'"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <p class="text-xs font-medium"
                                               :class="deadlineExpired ? 'text-red-700' : 'text-amber-700'"
                                               x-text="deadlineWarning"></p>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="start_date" :value="startDate">
                                <input type="hidden" name="end_date" :value="endDate">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== NAZORATLAR ===== --}}

                {{-- Loading --}}
                <div x-show="loading" x-cloak class="ae-card mb-5">
                    <div class="p-10 text-center">
                        <svg class="animate-spin mx-auto h-10 w-10 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-3 text-base text-gray-500 font-medium">Nazoratlar tekshirilmoqda...</p>
                    </div>
                </div>

                {{-- Nazoratlar ro'yxati --}}
                <div x-show="assessments.length > 0" x-transition x-cloak>

                    {{-- Section header --}}
                    <div class="ae-card mb-4">
                        <div class="ae-card-header">
                            <div class="ae-card-header-icon bg-amber-50">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-900">O'tkazib yuborilgan nazoratlar</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Har bir nazorat uchun qayta topshirish sanasini tanlang</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-indigo-600" x-text="assessments.filter(a => a.assessment_type === 'jn' ? (a.makeup_start && a.makeup_end) : !!a.makeup_date).length + '/' + assessments.length"></span>
                                <span class="text-gray-400 text-sm">tanlangan</span>
                            </div>
                        </div>
                    </div>

                    {{-- Assessment cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                        <template x-for="(item, index) in assessments" :key="index">
                            <div class="assessment-card">
                                <div class="assessment-card-head">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-base font-bold text-gray-900" x-text="item.subject_name"></span>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-lg"
                                              :class="'badge-' + item.assessment_type"
                                              x-text="getLabel(item.assessment_type)"></span>
                                    </div>
                                    <span class="text-sm text-gray-400">
                                        <span class="font-semibold text-gray-600" x-text="fmtDate(item.original_date)"></span>
                                    </span>
                                </div>
                                <div class="px-4 py-3 mini-fp-wrap">
                                    <input type="text" readonly
                                           x-init="$nextTick(() => initMiniCal(index, $el))"
                                           class="ae-input cursor-pointer"
                                           style="padding: 10px 14px; font-size: 14px; border-radius: 10px;"
                                           :placeholder="item.assessment_type === 'jn' ? 'Sana oralig\'ini tanlang' : 'Sanani tanlang'">

                                    {{-- Hidden inputs --}}
                                    <input type="hidden" :name="'makeup_dates['+index+'][subject_name]'" :value="item.subject_name">
                                    <input type="hidden" :name="'makeup_dates['+index+'][subject_id]'" :value="item.subject_id || ''">
                                    <input type="hidden" :name="'makeup_dates['+index+'][assessment_type]'" :value="item.assessment_type">
                                    <input type="hidden" :name="'makeup_dates['+index+'][assessment_type_code]'" :value="item.assessment_type_code">
                                    <input type="hidden" :name="'makeup_dates['+index+'][original_date]'" :value="item.original_date">
                                    <input type="hidden" :name="'makeup_dates['+index+'][makeup_date]'" :value="item.assessment_type !== 'jn' ? (item.makeup_date || '') : ''">
                                    <input type="hidden" :name="'makeup_dates['+index+'][makeup_start]'" :value="item.assessment_type === 'jn' ? (item.makeup_start || '') : ''">
                                    <input type="hidden" :name="'makeup_dates['+index+'][makeup_end]'" :value="item.assessment_type === 'jn' ? (item.makeup_end || '') : ''">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ===== IZOH ===== --}}
                <div class="ae-card mb-6">
                    <div class="ae-card-body">
                        <label for="description" class="ae-label">Izoh <span class="text-gray-400 font-normal">(ixtiyoriy)</span></label>
                        <textarea name="description" id="description" rows="4" maxlength="1000"
                                  class="ae-textarea" placeholder="Qo'shimcha ma'lumot...">{{ old('description') }}</textarea>
                    </div>
                </div>

                {{-- ===== SUBMIT ===== --}}
                <div class="flex items-center justify-between py-5">
                    <a href="{{ route('student.absence-excuses.index') }}"
                       class="text-base text-gray-500 hover:text-gray-700 font-medium transition">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Orqaga
                    </a>
                    <button type="submit" :disabled="!canSubmit" class="ae-submit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Ariza yuborish
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    function absenceForm() {
        return {
            // Form fields
            reason: '{{ old("reason", "") }}',
            fileName: '',
            reasons: @js($reasons),

            // Date range
            startDate: '{{ old("start_date", "") }}',
            endDate: '{{ old("end_date", "") }}',
            mainFp: null,

            // Deadline
            deadlineWarning: '',
            deadlineExpired: false,

            // Assessments
            assessments: [],
            loading: false,
            searched: false,
            miniFps: {},

            // ---- Computed ----
            get selectedReason() {
                return this.reason ? this.reasons[this.reason] : null;
            },
            get maxDays() {
                return this.selectedReason?.max_days || null;
            },
            get totalDays() {
                if (!this.startDate || !this.endDate) return 0;
                return this._countNonSundays(this.startDate, this.endDate);
            },
            get allDatesSelected() {
                return this.assessments.length > 0 && this.assessments.every(a => {
                    if (a.assessment_type === 'jn') return a.makeup_start && a.makeup_end;
                    return !!a.makeup_date;
                });
            },
            get canSubmit() {
                if (!this.reason || !this.startDate || !this.endDate) return false;
                if (this.deadlineExpired) return false;
                if (this.assessments.length > 0 && !this.allDatesSelected) return false;
                return true;
            },

            // ---- Init ----
            init() {
                this.checkDeadline();
                if (this.startDate && this.endDate) {
                    this.fetchAssessments();
                }
                this.$watch('reason', (val) => {
                    if (val) {
                        this.$nextTick(() => this.initMainCal());
                    }
                });
                if (this.reason) {
                    this.$nextTick(() => this.initMainCal());
                }
            },

            // ---- Main calendar (Flatpickr range) ----
            initMainCal(autoOpen) {
                const el = this.$refs.mainCalendar;
                if (!el || typeof flatpickr === 'undefined') return;
                if (this.mainFp) {
                    if (autoOpen) this.mainFp.open();
                    return;
                }

                const self = this;
                this.mainFp = flatpickr(el, {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    maxDate: 'today',
                    defaultDate: (self.startDate && self.endDate) ? [self.startDate, self.endDate] : [],
                    onChange(selectedDates) {
                        if (selectedDates.length === 2) {
                            self.startDate = self._toStr(selectedDates[0]);
                            self.endDate = self._toStr(selectedDates[1]);
                            self.checkDeadline();
                            if (!self.deadlineExpired) {
                                self.fetchAssessments();
                            }
                        } else if (selectedDates.length === 1) {
                            self.startDate = self._toStr(selectedDates[0]);
                            self.endDate = '';
                            self.assessments = [];
                            self.searched = false;
                            self.deadlineWarning = '';
                            self.deadlineExpired = false;
                        }
                    }
                });
                if (autoOpen) this.mainFp.open();
            },

            onReasonChange() {
                if (this.startDate && this.endDate && this.maxDays) {
                    const nonSunDays = this._countNonSundays(this.startDate, this.endDate);
                    if (nonSunDays > this.maxDays) {
                        this.endDate = '';
                        this.assessments = [];
                        this.searched = false;
                        this.deadlineWarning = '';
                        this.deadlineExpired = false;
                        if (this.mainFp) this.mainFp.clear();
                    }
                }
            },

            // ---- Mini calendars (assessment cards) ----
            initMiniCal(index, el) {
                if (!this.assessments[index]) return;

                const self = this;
                const item = this.assessments[index];
                const isJn = item.assessment_type === 'jn';
                const miniMaxDate = this._getMiniMaxDate();

                const disableFn = function(date) {
                    if (date.getDay() === 0) return true;
                    if (!isJn) {
                        const jnItem = self.assessments.find(a => a.assessment_type === 'jn');
                        if (jnItem && jnItem.makeup_start && jnItem.makeup_end) {
                            const ds = self._toStr(date);
                            if (ds >= jnItem.makeup_start && ds <= jnItem.makeup_end) return true;
                        }
                    }
                    return false;
                };

                const fp = flatpickr(el, {
                    mode: isJn ? 'range' : 'single',
                    dateFormat: 'Y-m-d',
                    minDate: 'today',
                    maxDate: miniMaxDate || undefined,
                    disable: [disableFn],
                    defaultDate: isJn
                        ? (item.makeup_start && item.makeup_end ? [item.makeup_start, item.makeup_end] : [])
                        : (item.makeup_date || undefined),
                    onChange(selectedDates) {
                        if (isJn) {
                            if (selectedDates.length === 2) {
                                item.makeup_start = self._toStr(selectedDates[0]);
                                item.makeup_end = self._toStr(selectedDates[1]);
                                self.assessments.forEach(a => {
                                    if (a.assessment_type !== 'jn' && a.makeup_date) {
                                        if (a.makeup_date >= item.makeup_start && a.makeup_date <= item.makeup_end) {
                                            a.makeup_date = '';
                                        }
                                    }
                                });
                                self._rebuildNonJnPickers();
                            } else if (selectedDates.length === 1) {
                                item.makeup_start = self._toStr(selectedDates[0]);
                                item.makeup_end = '';
                            } else {
                                item.makeup_start = '';
                                item.makeup_end = '';
                            }
                        } else {
                            item.makeup_date = selectedDates.length ? self._toStr(selectedDates[0]) : '';
                        }
                    }
                });

                this.miniFps[index] = fp;
            },

            _rebuildNonJnPickers() {
                const self = this;
                this.assessments.forEach((item, idx) => {
                    if (item.assessment_type === 'jn') return;
                    const fp = self.miniFps[idx];
                    if (!fp) return;
                    const disableFn = function(date) {
                        if (date.getDay() === 0) return true;
                        const jnItem = self.assessments.find(a => a.assessment_type === 'jn');
                        if (jnItem && jnItem.makeup_start && jnItem.makeup_end) {
                            const ds = self._toStr(date);
                            if (ds >= jnItem.makeup_start && ds <= jnItem.makeup_end) return true;
                        }
                        return false;
                    };
                    fp.set('disable', [disableFn]);
                    if (item.makeup_date) {
                        const jnItem = self.assessments.find(a => a.assessment_type === 'jn');
                        if (jnItem && jnItem.makeup_start && jnItem.makeup_end) {
                            if (item.makeup_date >= jnItem.makeup_start && item.makeup_date <= jnItem.makeup_end) {
                                item.makeup_date = '';
                                fp.clear();
                            }
                        }
                    }
                });
            },

            _getMiniMaxDate() {
                const allowed = this.totalDays;
                if (!allowed) return null;
                const today = new Date(); today.setHours(0,0,0,0);
                let count = 0, d = new Date(today);
                while (count < allowed) {
                    if (d.getDay() !== 0) count++;
                    if (count < allowed) d.setDate(d.getDate() + 1);
                }
                return this._toStr(d);
            },

            // ---- Helpers ----
            _toStr(d) {
                return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            },
            _countNonSundays(startStr, endStr) {
                const s = new Date(startStr), e = new Date(endStr);
                if (e < s) return 0;
                let count = 0, d = new Date(s);
                while (d <= e) {
                    if (d.getDay() !== 0) count++;
                    d.setDate(d.getDate() + 1);
                }
                return count;
            },

            checkDeadline() {
                this.deadlineWarning = '';
                this.deadlineExpired = false;
                if (!this.endDate) return;
                const end = new Date(this.endDate);
                let nextDay = new Date(end);
                nextDay.setDate(nextDay.getDate() + 1);
                if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1);
                const today = new Date(); today.setHours(0,0,0,0); nextDay.setHours(0,0,0,0);
                if (today < nextDay) return;
                const diff = Math.floor((today - nextDay) / 86400000);
                if (diff > 10) {
                    this.deadlineWarning = "Muddati o'tgan (" + diff + " kun). 10 kun ichida ariza topshirish kerak edi.";
                    this.deadlineExpired = true;
                } else {
                    this.deadlineWarning = "Ariza topshirish uchun " + (10 - diff) + " kun qoldi.";
                }
            },

            async fetchAssessments() {
                this.loading = true; this.searched = false; this.assessments = [];
                Object.values(this.miniFps).forEach(fp => fp && fp.destroy());
                this.miniFps = {};

                try {
                    const resp = await fetch('{{ route("student.absence-excuses.missed-assessments") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ start_date: this.startDate, end_date: this.endDate }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.assessments = (data.assessments || []).map(a => ({
                            ...a, makeup_date: '', makeup_start: '', makeup_end: ''
                        }));
                    }
                } catch (e) { console.error('Xatolik:', e); }

                const hasJn = this.assessments.some(a => a.assessment_type === 'jn');
                if (!hasJn) {
                    this.assessments.unshift({
                        subject_name: 'Joriy nazorat', subject_id: '',
                        assessment_type: 'jn', assessment_type_code: 'jn',
                        original_date: this.startDate, makeup_date: '',
                        makeup_start: '', makeup_end: '', is_default_jn: true
                    });
                }
                this.assessments.sort((a, b) => a.assessment_type === 'jn' ? -1 : b.assessment_type === 'jn' ? 1 : 0);
                this.loading = false; this.searched = true;
            },

            handleDrop(e) {
                const file = e.dataTransfer.files[0];
                if (file) {
                    const input = document.getElementById('file');
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    this.fileName = file.name;
                }
            },

            getLabel(t) {
                return {jn:"Joriy nazorat",mt:"Mustaqil ta'lim",oski:"OSKI",test:"Yakuniy test"}[t] || t;
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
