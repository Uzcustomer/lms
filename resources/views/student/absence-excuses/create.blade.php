<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sababli dars qoldirish arizasi
        </h2>
    </x-slot>

    @push('styles')
    <style>
        /* ======= RANGE CALENDAR ======= */
        .rc-calendar { user-select: none; }
        .rc-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-radius: 12px 12px 0 0;
        }
        .rc-header-title { color: #fff; font-weight: 700; font-size: 15px; }
        .rc-nav {
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.15); border-radius: 8px; cursor: pointer;
            color: #fff; font-size: 14px; transition: all .15s; border: none;
        }
        .rc-nav:hover { background: rgba(255,255,255,0.3); }
        .rc-weekdays {
            display: grid; grid-template-columns: repeat(7, 1fr);
            background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 8px 8px 6px;
        }
        .rc-weekdays span {
            text-align: center; font-size: 11px; font-weight: 700;
            color: #64748b; text-transform: uppercase;
        }
        .rc-weekdays span:last-child { color: #ef4444; }
        .rc-grid {
            display: grid; grid-template-columns: repeat(7, 1fr);
            gap: 2px; padding: 8px;
        }
        .rc-day {
            display: flex; align-items: center; justify-content: center;
            height: 38px; font-size: 13px; font-weight: 500; color: #334155;
            border-radius: 8px; cursor: pointer; transition: all .12s; position: relative;
            border: none; background: transparent;
        }
        .rc-day:hover:not(.rc-disabled):not(.rc-empty) { background: #eef2ff; }
        .rc-day.rc-empty { cursor: default; }
        .rc-day.rc-disabled { color: #cbd5e1; cursor: not-allowed; }
        .rc-day.rc-sunday { color: #ef4444; font-weight: 600; }
        .rc-day.rc-sunday.rc-disabled { color: #fca5a5; }
        .rc-day.rc-today {
            background: #eef2ff; font-weight: 700; color: #4f46e5;
            box-shadow: inset 0 0 0 1.5px #818cf8;
        }
        .rc-day.rc-start {
            background: #4f46e5; color: #fff; font-weight: 700;
            border-radius: 8px 4px 4px 8px;
        }
        .rc-day.rc-end {
            background: #4f46e5; color: #fff; font-weight: 700;
            border-radius: 4px 8px 8px 4px;
        }
        .rc-day.rc-start.rc-end { border-radius: 8px; }
        .rc-day.rc-in-range { background: #e0e7ff; color: #3730a3; border-radius: 2px; }
        .rc-day.rc-start.rc-today, .rc-day.rc-end.rc-today { box-shadow: none; }

        /* ======= SCROLL CALENDAR (makeup) ======= */
        .sc-strip {
            display: flex; overflow-x: auto; gap: 6px; padding: 8px 4px 12px;
            -webkit-overflow-scrolling: touch; scrollbar-width: thin;
        }
        .sc-strip::-webkit-scrollbar { height: 4px; }
        .sc-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .sc-cell {
            flex-shrink: 0; width: 52px; height: 64px; border-radius: 12px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-weight: 500; transition: all .15s; cursor: pointer;
            border: 1.5px solid #e2e8f0; background: #fff;
        }
        .sc-cell:hover:not(.sc-off) { border-color: #818cf8; background: #eef2ff; }
        .sc-cell.sc-off {
            background: #f8fafc; border-color: #f1f5f9; color: #cbd5e1; cursor: not-allowed;
        }
        .sc-cell.sc-picked {
            background: linear-gradient(135deg, #4f46e5, #6366f1); border-color: transparent;
            color: #fff; box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        .sc-cell.sc-is-today:not(.sc-picked) {
            border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.3);
        }
        .sc-cell-wd { font-size: 10px; text-transform: uppercase; font-weight: 700; line-height: 1; opacity: .6; }
        .sc-cell-d { font-size: 18px; font-weight: 700; line-height: 1.2; }
        .sc-cell-m { font-size: 9px; line-height: 1; opacity: .5; }
        .sc-cell.sc-picked .sc-cell-wd,
        .sc-cell.sc-picked .sc-cell-m { opacity: .7; }

        /* ======= CARD STYLES ======= */
        .ae-card {
            background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06);
            border: 1px solid #f1f5f9; overflow: hidden;
        }
        .ae-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 10px;
        }
        .ae-card-header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ae-card-body { padding: 20px; }

        /* ======= FORM ELEMENTS ======= */
        .ae-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .ae-label .req { color: #ef4444; margin-left: 2px; }
        .ae-input, .ae-select, .ae-textarea {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 10px 14px; font-size: 14px; color: #1e293b;
            transition: border-color .15s, box-shadow .15s; background: #fff;
        }
        .ae-input:focus, .ae-select:focus, .ae-textarea:focus {
            outline: none; border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,0.15);
        }
        .ae-file-zone {
            border: 2px dashed #d1d5db; border-radius: 12px; padding: 24px;
            text-align: center; cursor: pointer; transition: all .2s; background: #fafbfc;
        }
        .ae-file-zone:hover { border-color: #818cf8; background: #eef2ff; }
        .ae-file-zone.has-file { border-color: #34d399; background: #ecfdf5; }

        /* ======= ASSESSMENT CARD ======= */
        .assessment-card {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
            transition: all .2s; overflow: hidden;
        }
        .assessment-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 12px rgba(0,0,0,.04); }
        .assessment-card-head {
            padding: 14px 16px; display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #f1f5f9;
        }

        /* ======= SUBMIT BTN ======= */
        .ae-submit {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px; border-radius: 12px; font-size: 15px; font-weight: 700;
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
    </style>
    @endpush

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8" x-data="absenceForm()" x-init="init()">

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
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
                            <h3 class="text-base font-bold text-gray-900">Ariza ma'lumotlari</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Sabab, hujjat raqami va tasdiqlovchi fayl</p>
                        </div>
                    </div>
                    <div class="ae-card-body space-y-5">
                        {{-- Sabab --}}
                        <div>
                            <label for="reason" class="ae-label">Sabab <span class="req">*</span></label>
                            <select name="reason" id="reason" required x-model="reason" class="ae-select">
                                <option value="">Sababni tanlang...</option>
                                @foreach($reasons as $key => $data)
                                    <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $data['label'] }}</option>
                                @endforeach
                            </select>
                            @error('reason')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sabab info --}}
                        <div x-show="selectedReason" x-transition x-cloak
                             class="flex items-start gap-3 bg-indigo-50/60 border border-indigo-100 rounded-xl p-4">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="text-sm">
                                <p class="font-semibold text-indigo-900">Talab qilinadigan hujjat:</p>
                                <p class="text-indigo-700 mt-0.5" x-text="selectedReason?.document"></p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <template x-if="selectedReason?.max_days">
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-700 rounded-md">
                                            Maks: <span x-text="selectedReason?.max_days" class="ml-1"></span> kun
                                        </span>
                                    </template>
                                </div>
                                <template x-if="selectedReason?.note">
                                    <p class="mt-2 text-xs text-indigo-600 italic" x-text="selectedReason?.note"></p>
                                </template>
                            </div>
                        </div>

                        {{-- Hujjat raqami --}}
                        <div>
                            <label for="doc_number" class="ae-label">Hujjat raqami <span class="req">*</span></label>
                            <input type="text" name="doc_number" id="doc_number"
                                   value="{{ old('doc_number') }}" placeholder="Masalan: 123/2026"
                                   required class="ae-input">
                            @error('doc_number')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Fayl yuklash --}}
                        <div>
                            <label class="ae-label">Tasdiqlovchi hujjat (spravka) <span class="req">*</span></label>
                            <label for="file" class="ae-file-zone block" :class="fileName ? 'has-file' : ''"
                                   @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50')"
                                   @dragleave.prevent="$el.classList.remove('border-indigo-400','bg-indigo-50')"
                                   @drop.prevent="handleDrop($event)">
                                <div x-show="!fileName">
                                    <svg class="mx-auto w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="text-sm text-gray-500">Faylni bu yerga tashlang yoki <span class="text-indigo-600 font-semibold">tanlang</span></p>
                                    <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG, DOC, DOCX (maks. 10MB)</p>
                                </div>
                                <div x-show="fileName" class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-emerald-700" x-text="fileName"></span>
                                </div>
                            </label>
                            <input type="file" name="file" id="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="hidden" @change="fileName = $event.target.files[0]?.name || ''">
                            @error('file')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Izoh --}}
                        <div>
                            <label for="description" class="ae-label">Izoh <span class="text-gray-400 font-normal">(ixtiyoriy)</span></label>
                            <textarea name="description" id="description" rows="2" maxlength="1000"
                                      class="ae-textarea" placeholder="Qo'shimcha ma'lumot...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ===== CARD 2: SANA TANLASH (RANGE CALENDAR) ===== --}}
                <div class="ae-card mb-5">
                    <div class="ae-card-header">
                        <div class="ae-card-header-icon bg-emerald-50">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Sana oralig'i</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Sababli bo'lmagan kunlarni tanlang (faqat o'tmish)</p>
                        </div>
                    </div>
                    <div class="ae-card-body">
                        {{-- Tanlangan oraliq ko'rsatish --}}
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex-1 bg-gray-50 rounded-xl p-3 text-center border"
                                 :class="startDate ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-200'">
                                <p class="text-[10px] uppercase font-bold text-gray-400 mb-0.5">Boshlanish</p>
                                <p class="text-sm font-bold" :class="startDate ? 'text-indigo-700' : 'text-gray-300'"
                                   x-text="startDate ? fmtDate(startDate) : '—'"></p>
                            </div>
                            <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                            <div class="flex-1 bg-gray-50 rounded-xl p-3 text-center border"
                                 :class="endDate ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-200'">
                                <p class="text-[10px] uppercase font-bold text-gray-400 mb-0.5">Tugash</p>
                                <p class="text-sm font-bold" :class="endDate ? 'text-indigo-700' : 'text-gray-300'"
                                   x-text="endDate ? fmtDate(endDate) : '—'"></p>
                            </div>
                            <template x-if="startDate && endDate">
                                <div class="flex-shrink-0 bg-indigo-100 text-indigo-700 px-3 py-2 rounded-xl text-center">
                                    <p class="text-[10px] uppercase font-bold opacity-60">Jami</p>
                                    <p class="text-sm font-bold" x-text="totalDays + ' kun'"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Tozalash --}}
                        <div x-show="startDate || endDate" class="flex justify-end mb-2">
                            <button type="button" @click="clearDates()"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium">Sanalarni tozalash</button>
                        </div>

                        {{-- Kalendar --}}
                        <div class="rc-calendar border border-gray-200 rounded-2xl overflow-hidden">
                            {{-- Header --}}
                            <div class="rc-header">
                                <button type="button" @click="prevMonth()" class="rc-nav">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                </button>
                                <span class="rc-header-title" x-text="monthYearLabel"></span>
                                <button type="button" @click="nextMonth()" class="rc-nav" :disabled="!canGoNext">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                            {{-- Weekdays --}}
                            <div class="rc-weekdays">
                                <span>Du</span><span>Se</span><span>Cho</span><span>Pa</span><span>Ju</span><span>Sha</span><span>Ya</span>
                            </div>
                            {{-- Grid --}}
                            <div class="rc-grid">
                                <template x-for="cell in calendarCells" :key="cell.key">
                                    <button type="button"
                                            @click="cell.date && !cell.disabled && pickDate(cell.dateStr)"
                                            :disabled="cell.disabled || !cell.date"
                                            :class="{
                                                'rc-empty': !cell.date,
                                                'rc-disabled': cell.disabled && cell.date,
                                                'rc-sunday': cell.isSunday && cell.date,
                                                'rc-today': cell.isToday,
                                                'rc-start': cell.dateStr === startDate,
                                                'rc-end': cell.dateStr === endDate,
                                                'rc-in-range': cell.inRange && cell.dateStr !== startDate && cell.dateStr !== endDate
                                            }"
                                            class="rc-day"
                                            x-text="cell.day"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Deadline warning --}}
                        <div x-show="deadlineWarning" x-transition x-cloak class="mt-4">
                            <div :class="deadlineExpired ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'"
                                 class="border rounded-xl p-3 flex items-center gap-2">
                                <svg class="w-5 h-5 flex-shrink-0"
                                     :class="deadlineExpired ? 'text-red-500' : 'text-amber-500'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm font-medium"
                                   :class="deadlineExpired ? 'text-red-700' : 'text-amber-700'"
                                   x-text="deadlineWarning"></p>
                            </div>
                        </div>

                        {{-- Hidden inputs for dates --}}
                        <input type="hidden" name="start_date" :value="startDate">
                        <input type="hidden" name="end_date" :value="endDate">
                    </div>
                </div>

                {{-- ===== CARD 3: O'TKAZIB YUBORILGAN NAZORATLAR ===== --}}

                {{-- Loading --}}
                <div x-show="loading" x-cloak class="ae-card mb-5">
                    <div class="p-8 text-center">
                        <svg class="animate-spin mx-auto h-8 w-8 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-3 text-sm text-gray-500 font-medium">Nazoratlar tekshirilmoqda...</p>
                    </div>
                </div>

                {{-- Nazorat topilmadi --}}
                <div x-show="!loading && searched && assessments.length === 0" x-cloak class="ae-card mb-5">
                    <div class="p-8 text-center">
                        <div class="w-14 h-14 mx-auto bg-emerald-50 rounded-2xl flex items-center justify-center mb-3">
                            <svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900">Nazorat topilmadi</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Siz kelmagan kunlarda nazorat turlari rejalashtirilmagan.
                        </p>
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
                                <h3 class="text-base font-bold text-gray-900">O'tkazib yuborilgan nazoratlar</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Har bir nazorat uchun qayta topshirish sanasini tanlang</p>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="font-bold text-indigo-600" x-text="assessments.filter(a => a.makeup_date).length + '/' + assessments.length"></span>
                                <span class="text-gray-400 text-xs">tanlangan</span>
                            </div>
                        </div>
                    </div>

                    {{-- Assessment cards --}}
                    <div class="space-y-3 mb-5">
                        <template x-for="(item, index) in assessments" :key="index">
                            <div class="assessment-card">
                                <div class="assessment-card-head">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-bold text-gray-900" x-text="item.subject_name"></span>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-md"
                                              :class="'badge-' + item.assessment_type"
                                              x-text="getLabel(item.assessment_type)"></span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-400">
                                            Asl: <span class="font-semibold text-gray-600" x-text="fmtDate(item.original_date)"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="px-4 py-3">
                                    {{-- Status --}}
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="text-sm">
                                            <template x-if="item.makeup_date">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    <span x-text="fmtDate(item.makeup_date)"></span>
                                                </span>
                                            </template>
                                            <template x-if="!item.makeup_date">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path></svg>
                                                    Sana tanlanmagan
                                                </span>
                                            </template>
                                        </div>
                                        <template x-if="item.makeup_date">
                                            <button type="button" @click="item.makeup_date = ''"
                                                    class="text-xs text-red-400 hover:text-red-600 font-medium transition">Bekor qilish</button>
                                        </template>
                                    </div>

                                    {{-- Scroll calendar --}}
                                    <div class="sc-strip">
                                        <template x-for="day in makeupCalendarDays" :key="day.date">
                                            <button type="button"
                                                    @click="!day.isSunday && toggleMakeup(index, day.date)"
                                                    :disabled="day.isSunday"
                                                    :class="{
                                                        'sc-off': day.isSunday,
                                                        'sc-picked': item.makeup_date === day.date,
                                                        'sc-is-today': day.isToday
                                                    }"
                                                    class="sc-cell">
                                                <span class="sc-cell-wd" x-text="day.weekDay"></span>
                                                <span class="sc-cell-d" x-text="day.dayNum"></span>
                                                <span class="sc-cell-m" x-text="day.month"></span>
                                            </button>
                                        </template>
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

                {{-- ===== SUBMIT ===== --}}
                <div class="flex items-center justify-between py-4">
                    <a href="{{ route('student.absence-excuses.index') }}"
                       class="text-sm text-gray-500 hover:text-gray-700 font-medium transition">
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
        const MONTHS_UZ = ['Yanvar','Fevral','Mart','Aprel','May','Iyun','Iyul','Avgust','Sentabr','Oktabr','Noyabr','Dekabr'];
        const WDAYS = ['Ya','Du','Se','Cho','Pa','Ju','Sha'];
        const MSHORT = ['Yan','Fev','Mar','Apr','May','Iyn','Iyl','Avg','Sen','Okt','Noy','Dek'];

        return {
            // Form
            reason: '{{ old("reason", "") }}',
            fileName: '',
            reasons: @js($reasons),

            // Range calendar
            calMonth: null,
            calYear: null,
            startDate: '{{ old("start_date", "") }}',
            endDate: '{{ old("end_date", "") }}',
            selecting: 'start', // 'start' or 'end'

            // Deadline
            deadlineWarning: '',
            deadlineExpired: false,

            // Assessments
            assessments: [],
            makeupCalendarDays: [],
            loading: false,
            searched: false,

            get selectedReason() {
                return this.reason ? this.reasons[this.reason] : null;
            },
            get totalDays() {
                if (!this.startDate || !this.endDate) return 0;
                const s = new Date(this.startDate), e = new Date(this.endDate);
                return e >= s ? Math.floor((e - s) / 86400000) + 1 : 0;
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
            get monthYearLabel() {
                return MONTHS_UZ[this.calMonth] + ' ' + this.calYear;
            },
            get canGoNext() {
                const today = new Date();
                return !(this.calYear === today.getFullYear() && this.calMonth === today.getMonth());
            },

            get calendarCells() {
                const cells = [];
                const first = new Date(this.calYear, this.calMonth, 1);
                let startWd = first.getDay(); // 0=Sun
                startWd = startWd === 0 ? 6 : startWd - 1; // Mon=0
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const today = new Date(); today.setHours(0,0,0,0);
                const todayStr = this._toStr(today);

                // Empty cells
                for (let i = 0; i < startWd; i++) {
                    cells.push({ key: 'e' + i, date: null, day: '', disabled: true });
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const dt = new Date(this.calYear, this.calMonth, d);
                    const ds = this._toStr(dt);
                    const isSun = dt.getDay() === 0;
                    const isFuture = dt > today;
                    cells.push({
                        key: ds,
                        date: dt,
                        dateStr: ds,
                        day: d,
                        isSunday: isSun,
                        isToday: ds === todayStr,
                        disabled: isFuture,
                        inRange: this._inRange(ds)
                    });
                }
                return cells;
            },

            init() {
                const today = new Date();
                // If we have old dates, show that month
                if (this.startDate) {
                    const sd = new Date(this.startDate);
                    this.calMonth = sd.getMonth();
                    this.calYear = sd.getFullYear();
                } else {
                    this.calMonth = today.getMonth();
                    this.calYear = today.getFullYear();
                }
                this.buildMakeupCalendar();
                this.checkDeadline();
                if (this.startDate && this.endDate) {
                    this.selecting = 'start';
                    this.fetchAssessments();
                }
            },

            prevMonth() {
                if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; }
                else this.calMonth--;
            },
            nextMonth() {
                if (!this.canGoNext) return;
                if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; }
                else this.calMonth++;
            },

            pickDate(dateStr) {
                if (this.selecting === 'start') {
                    this.startDate = dateStr;
                    this.endDate = '';
                    this.selecting = 'end';
                    this.assessments = [];
                    this.searched = false;
                } else {
                    if (dateStr < this.startDate) {
                        // Agar oldingi sanani tanlasa, start sifatida qabul qilamiz
                        this.startDate = dateStr;
                        this.endDate = '';
                        this.selecting = 'end';
                        this.assessments = [];
                        this.searched = false;
                        return;
                    }
                    this.endDate = dateStr;
                    this.selecting = 'start';
                    this.checkDeadline();
                    if (!this.deadlineExpired) {
                        this.fetchAssessments();
                    }
                }
                this.checkDeadline();
            },

            clearDates() {
                this.startDate = '';
                this.endDate = '';
                this.selecting = 'start';
                this.assessments = [];
                this.searched = false;
                this.deadlineWarning = '';
                this.deadlineExpired = false;
            },

            _toStr(d) {
                return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            },
            _inRange(ds) {
                if (!this.startDate || !this.endDate) return false;
                return ds >= this.startDate && ds <= this.endDate;
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

            buildMakeupCalendar() {
                const today = new Date();
                this.makeupCalendarDays = [];
                for (let i = 0; i < 60; i++) {
                    const d = new Date(today); d.setDate(d.getDate() + i);
                    this.makeupCalendarDays.push({
                        date: this._toStr(d),
                        dayNum: d.getDate(),
                        weekDay: WDAYS[d.getDay()],
                        month: MSHORT[d.getMonth()],
                        isSunday: d.getDay() === 0,
                        isToday: i === 0
                    });
                }
            },

            async fetchAssessments() {
                this.loading = true; this.searched = false; this.assessments = [];
                try {
                    const resp = await fetch('{{ route("student.absence-excuses.missed-assessments") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ start_date: this.startDate, end_date: this.endDate }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.assessments = (data.assessments || []).map(a => ({...a, makeup_date: ''}));
                    }
                } catch (e) { console.error('Xatolik:', e); }
                this.loading = false; this.searched = true;
            },

            toggleMakeup(idx, dateStr) {
                this.assessments[idx].makeup_date = this.assessments[idx].makeup_date === dateStr ? '' : dateStr;
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
