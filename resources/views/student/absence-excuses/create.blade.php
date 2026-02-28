<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Sababli dars qoldirish arizasi
        </h2>
    </x-slot>

    @push('styles')
    <style>
        /* ======= CALENDAR DROPDOWN ======= */
        .cal-dropdown-wrap { position: relative; }
        .cal-trigger {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 14px 16px; font-size: 17px; color: #1e293b; background: #fff;
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer; transition: border-color .15s, box-shadow .15s;
        }
        .cal-trigger:hover { border-color: #c7d2fe; }
        .cal-trigger.active { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,0.15); }
        .cal-trigger .cal-trigger-text { font-weight: 600; }
        .cal-trigger .cal-trigger-placeholder { color: #9ca3af; font-weight: 400; }
        .cal-dropdown {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 50;
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,.12); overflow: hidden;
        }

        /* ======= RANGE CALENDAR ======= */
        .rc-calendar { user-select: none; width: 100%; }
        .rc-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-radius: 0;
        }
        .rc-calendar:first-child .rc-header, .cal-dropdown .rc-header { border-radius: 10px 10px 0 0; }
        .rc-header-title { color: #fff; font-weight: 700; font-size: 13px; letter-spacing: 0.3px; }
        .rc-nav {
            width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.15); border-radius: 6px; cursor: pointer;
            color: #fff; transition: all .15s; border: none;
        }
        .rc-nav:hover { background: rgba(255,255,255,0.3); }
        .rc-nav:disabled { opacity: .3; cursor: not-allowed; }
        .rc-weekdays {
            display: grid; grid-template-columns: repeat(7, 1fr);
            background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 4px 6px 3px;
        }
        .rc-weekdays span {
            text-align: center; font-size: 10px; font-weight: 700;
            color: #64748b; text-transform: uppercase;
        }
        .rc-weekdays span:last-child { color: #ef4444; }
        .rc-grid {
            display: grid; grid-template-columns: repeat(7, 1fr);
            gap: 1px; padding: 4px 6px 6px;
        }
        .rc-day {
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600; color: #334155;
            border-radius: 6px; cursor: pointer; transition: all .12s; position: relative;
            border: none; background: transparent; min-height: 28px;
        }
        .rc-day:hover:not(.rc-disabled):not(.rc-empty) { background: #eef2ff; }
        .rc-day.rc-empty { cursor: default; }
        .rc-day.rc-disabled { color: #d1d5db; cursor: not-allowed; }
        .rc-day.rc-sunday { color: #ef4444; }
        .rc-day.rc-sunday.rc-disabled { color: #fecaca; }
        .rc-day.rc-today {
            background: #eef2ff; font-weight: 800; color: #4f46e5;
            box-shadow: inset 0 0 0 1.5px #818cf8;
        }
        .rc-day.rc-start {
            background: #4f46e5; color: #fff !important; font-weight: 700;
            border-radius: 6px 2px 2px 6px;
        }
        .rc-day.rc-end {
            background: #4f46e5; color: #fff !important; font-weight: 700;
            border-radius: 2px 6px 6px 2px;
        }
        .rc-day.rc-start.rc-end { border-radius: 6px; }
        .rc-day.rc-in-range { background: #e0e7ff; color: #3730a3; border-radius: 2px; }
        .rc-day.rc-start.rc-today, .rc-day.rc-end.rc-today { box-shadow: none; }
        .rc-day.rc-picked-single {
            background: #4f46e5; color: #fff !important; font-weight: 700; border-radius: 6px;
        }
        .rc-day.rc-taken { background: #fef3c7; color: #92400e; cursor: not-allowed; }

        /* ======= MINI CALENDAR (assessment cards) ======= */
        .rc-mini { height: 250px; display: flex; flex-direction: column; }
        .rc-mini .rc-header { padding: 6px 10px; flex-shrink: 0; }
        .rc-mini .rc-header-title { font-size: 12px; }
        .rc-mini .rc-nav { width: 24px; height: 24px; border-radius: 5px; }
        .rc-mini .rc-weekdays { padding: 4px 6px 3px; flex-shrink: 0; }
        .rc-mini .rc-weekdays span { font-size: 9px; }
        .rc-mini .rc-grid { gap: 0; padding: 2px 6px 6px; flex: 1; align-content: start; }
        .rc-mini .rc-day { font-size: 11px; min-height: 30px; border-radius: 5px; }
        .rc-mini .rc-day.rc-start { border-radius: 5px 2px 2px 5px; }
        .rc-mini .rc-day.rc-end { border-radius: 2px 5px 5px 2px; }
        .rc-mini .rc-day.rc-start.rc-end { border-radius: 5px; }
        .rc-mini .rc-day.rc-in-range { border-radius: 2px; }

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

        /* ======= FAN CARD GRID ======= */
        .ae-fan-grid {
            display: grid; gap: 14px;
        }
        .ae-fan-card {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
            overflow: visible; min-width: 0;
        }
        .ae-fan-header {
            padding: 12px 16px; font-size: 14px; font-weight: 700; color: #1e293b;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0; display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; line-height: 1.3;
        }
        .ae-fan-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 8px; font-size: 13px;
            font-weight: 700; color: #6366f1; background: #eef2ff; flex-shrink: 0;
        }
        .ae-fan-name { flex: 1; min-width: 0; }
        .ae-fan-item {
            padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
        }
        .ae-fan-item:last-child { border-bottom: none; }
        .ae-fan-item .cal-dropdown { z-index: 100; }

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

        /* ======= MOBILE ======= */
        @media (max-width: 640px) {
            /* Card */
            .ae-card { border-radius: 12px; margin-bottom: 5px !important; }
            .ae-card-header { padding: 12px 14px; gap: 8px; border-radius: 12px 12px 0 0; }
            .ae-card-header-icon { width: 32px; height: 32px; border-radius: 8px; }
            .ae-card-header-icon svg { width: 16px; height: 16px; }
            .ae-card-header h3 { font-size: 14px; }
            .ae-card-header p { font-size: 11px; }
            .ae-card-body { padding: 14px; }

            /* Form elements */
            .ae-label { font-size: 13px; margin-bottom: 5px; }
            .ae-input, .ae-select, .ae-textarea { font-size: 14px; padding: 10px 12px; border-radius: 10px; }
            .ae-file-zone { min-height: 42px; padding: 8px 12px; border-radius: 10px; }
            .ae-file-zone span { font-size: 12px !important; }

            /* Calendar trigger */
            .cal-trigger { font-size: 14px; padding: 10px 12px; border-radius: 10px; }

            /* Calendar */
            .cal-dropdown { border-radius: 10px; }
            .rc-header { padding: 6px 8px; }
            .rc-header-title { font-size: 11px; }
            .rc-nav { width: 24px; height: 24px; }
            .rc-grid { padding: 3px 3px 4px; gap: 0; }
            .rc-day { font-size: 11px; min-height: 24px; }
            .rc-weekdays { padding: 3px 3px 2px; }
            .rc-weekdays span { font-size: 9px; }

            /* Mini calendar */
            .rc-mini { height: 220px; }
            .rc-mini .rc-day { font-size: 10px; min-height: 26px; }
            .rc-mini .rc-header { padding: 5px 8px; }
            .rc-mini .rc-header-title { font-size: 11px; }

            /* Fan grid mobile */
            .ae-fan-grid { gap: 10px !important; grid-template-columns: 1fr !important; }
            .ae-fan-header { font-size: 12px; padding: 8px 12px; border-radius: 10px 10px 0 0; }
            .ae-fan-card { border-radius: 10px; }
            .ae-fan-item { padding: 8px 12px; }
            .ae-fan-num { width: 20px; height: 20px; font-size: 10px; border-radius: 6px; }

            /* Submit */
            .ae-submit { font-size: 15px; padding: 12px 24px; border-radius: 12px; }

            /* Sabab info */
            .ae-card-body > div[x-show="selectedReason"] { padding: 12px; border-radius: 10px; gap: 8px; }
            .ae-card-body > div[x-show="selectedReason"] .w-9 { width: 28px; height: 28px; border-radius: 6px; }
            .ae-card-body > div[x-show="selectedReason"] .text-base { font-size: 13px !important; }
            .ae-card-body > div[x-show="selectedReason"] .text-sm { font-size: 11px !important; }

            /* Section header counter */
            .ae-card-header .text-lg { font-size: 14px !important; }
            .ae-card-header .text-sm { font-size: 11px !important; }

            /* Deadline warning */
            .ae-card-body .text-xs { font-size: 11px; }

            /* Grid gaps */
            .ae-card-body .gap-6 { gap: 16px; }
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
                                        <span class="text-sm text-gray-500">Faylni <span class="text-indigo-600 font-semibold">tanlang</span> <span class="text-gray-400">(PDF, JPG)</span></span>
                                    </div>
                                    <div x-show="fileName" class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-sm font-semibold text-emerald-700 truncate" x-text="fileName"></span>
                                    </div>
                                </label>
                                <input type="file" name="file" id="file" required accept=".pdf,.jpg,.jpeg"
                                       class="hidden" @change="fileName = $event.target.files[0]?.name || ''">
                                @error('file')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Sabab info --}}
                        <div x-show="selectedReason" x-transition x-cloak
                             class="flex items-start gap-3 bg-indigo-50/60 border border-indigo-100 rounded-xl p-4">
                            <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-indigo-900 text-base">Talab qilinadigan hujjat:</p>
                                <p class="text-indigo-700 mt-1 text-base" x-text="selectedReason?.document"></p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <template x-if="selectedReason?.max_days">
                                        <span class="inline-flex items-center px-3 py-1 text-sm font-bold bg-indigo-100 text-indigo-700 rounded-lg">
                                            Maksimum: <span x-text="selectedReason?.max_days" class="ml-1"></span> kun
                                        </span>
                                    </template>
                                </div>
                                <template x-if="selectedReason?.note">
                                    <p class="mt-2 text-sm text-indigo-600 italic" x-text="selectedReason?.note"></p>
                                </template>
                            </div>
                        </div>

                        {{-- ROW 2: Sana oralig'i (dropdown calendar) --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="cal-dropdown-wrap" @click.outside="showMainCal = false">
                                <label class="ae-label">Sana oralig'i <span class="req">*</span></label>

                                <template x-if="!reason">
                                    <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3">
                                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        <p class="text-sm font-medium text-amber-700">Avval sababni tanlang</p>
                                    </div>
                                </template>

                                <template x-if="reason">
                                    <div>
                                        {{-- Input trigger --}}
                                        <div class="cal-trigger" :class="showMainCal ? 'active' : ''"
                                             @click="showMainCal = !showMainCal">
                                            <div>
                                                <template x-if="startDate && endDate">
                                                    <span class="cal-trigger-text" x-text="fmtDate(startDate) + '  —  ' + fmtDate(endDate) + ' (' + totalDays + ' kun)'"></span>
                                                </template>
                                                <template x-if="startDate && !endDate">
                                                    <span class="cal-trigger-text"><span x-text="fmtDate(startDate)"></span> <span class="text-gray-400 font-normal">— tugash sanasini tanlang</span></span>
                                                </template>
                                                <template x-if="!startDate">
                                                    <span class="cal-trigger-placeholder">Boshlanish — Tugash</span>
                                                </template>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <template x-if="startDate || endDate">
                                                    <button type="button" @click.stop="clearDates(); showMainCal = false"
                                                            class="text-xs text-red-400 hover:text-red-600 font-medium">Tozalash</button>
                                                </template>
                                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showMainCal ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        </div>

                                        {{-- Dropdown calendar --}}
                                        <div x-show="showMainCal" x-transition.origin.top x-cloak class="cal-dropdown">
                                            {{-- Hint --}}
                                            <div x-show="startDate && !endDate && selecting === 'end'" class="px-3 py-1.5 bg-blue-50 text-xs text-blue-600 font-medium flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Tugash sanasini tanlang
                                                <template x-if="maxEndDateLabel">
                                                    <span>(maks: <span x-text="maxEndDateLabel"></span>)</span>
                                                </template>
                                            </div>

                                            <div class="rc-calendar">
                                                <div class="rc-header">
                                                    <button type="button" @click="prevMonth()" class="rc-nav">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                                    </button>
                                                    <span class="rc-header-title" x-text="monthYearLabel"></span>
                                                    <button type="button" @click="nextMonth()" class="rc-nav" :disabled="!canGoNext">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                                    </button>
                                                </div>
                                                <div class="rc-weekdays">
                                                    <span>Du</span><span>Se</span><span>Cho</span><span>Pa</span><span>Ju</span><span>Sha</span><span>Ya</span>
                                                </div>
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
                                            <div x-show="deadlineWarning" class="px-3 py-2">
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
                                    </div>
                                </template>

                                <input type="hidden" name="start_date" :value="startDate">
                                <input type="hidden" name="end_date" :value="endDate">
                            </div>
                            <div></div>
                        </div>
                    </div>
                </div>

                {{-- ===== CARD 3: O'TKAZIB YUBORILGAN NAZORATLAR ===== --}}

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

                    {{-- ===== GRID: Fan cards ===== --}}
                    <div class="ae-fan-grid mb-6"
                         :style="'grid-template-columns: repeat(' + (groupedAssessments.length >= 3 ? 3 : 2) + ', 1fr)'">
                        <template x-for="(group, gi) in groupedAssessments" :key="gi">
                            <div class="ae-fan-card">
                                <div class="ae-fan-header">
                                    <span class="ae-fan-num" x-text="gi + 1"></span>
                                    <span class="ae-fan-name" x-text="group.subject_name"></span>
                                </div>
                                <template x-for="(item, ri) in group.items" :key="item._idx">
                                    <div class="ae-fan-item">
                                        <div style="margin-bottom:6px;">
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-lg inline-block"
                                                  :class="'badge-' + item.assessment_type"
                                                  x-text="getLabel(item.assessment_type)"></span>
                                        </div>
                                        @include('student.absence-excuses._calendar-cell')
                                    </div>
                                </template>
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
            selecting: 'start',
            showMainCal: false,

            // Deadline
            deadlineWarning: '',
            deadlineExpired: false,

            // Assessments
            assessments: [],
            loading: false,
            searched: false,

            // ---- Computed ----
            get selectedReason() {
                return this.reason ? this.reasons[this.reason] : null;
            },
            get maxDays() {
                return this.selectedReason?.max_days || null;
            },
            // Maksimum tugash sanasi — yakshanbalarni hisobga olmasdan maxDays kun
            get maxEndDateStr() {
                if (!this.startDate || !this.maxDays || this.selecting !== 'end') return null;
                const s = new Date(this.startDate);
                let count = 0;
                let d = new Date(s);
                while (count < this.maxDays) {
                    if (d.getDay() !== 0) count++;
                    if (count < this.maxDays) d.setDate(d.getDate() + 1);
                }
                const today = new Date(); today.setHours(0,0,0,0);
                if (d > today) return this._toStr(today);
                return this._toStr(d);
            },
            get maxEndDateLabel() {
                if (!this.maxEndDateStr) return '';
                return this.fmtDate(this.maxEndDateStr);
            },
            // totalDays — faqat yakshanba bo'lmagan kunlar
            get totalDays() {
                if (!this.startDate || !this.endDate) return 0;
                return this._countNonSundays(this.startDate, this.endDate);
            },
            get groupedAssessments() {
                const typeOrder = { jn: 0, mt: 1, oski: 2, test: 3 };
                const groups = [];
                const map = {};
                this.assessments.forEach((item, idx) => {
                    item._idx = idx;
                    const key = item.subject_name;
                    if (!map[key]) {
                        map[key] = { subject_name: key, items: [] };
                        groups.push(map[key]);
                    }
                    map[key].items.push(item);
                });
                // Har bir fan ichida: JN birinchi, keyin MT, OSKI, Test
                groups.forEach(g => {
                    g.items.sort((a, b) => (typeOrder[a.assessment_type] ?? 9) - (typeOrder[b.assessment_type] ?? 9));
                });
                // Non-JN testlari bor fanlar birinchi, faqat JN bor fanlar oxirida
                groups.sort((a, b) => {
                    const aHasNonJn = a.items.some(i => i.assessment_type !== 'jn');
                    const bHasNonJn = b.items.some(i => i.assessment_type !== 'jn');
                    if (aHasNonJn && !bHasNonJn) return -1;
                    if (!aHasNonJn && bHasNonJn) return 1;
                    return 0;
                });
                return groups;
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
            get monthYearLabel() {
                if (this.calMonth === null) return '';
                return MONTHS_UZ[this.calMonth] + ' ' + this.calYear;
            },
            get canGoNext() {
                const today = new Date();
                return !(this.calYear === today.getFullYear() && this.calMonth === today.getMonth());
            },

            get calendarCells() {
                if (this.calMonth === null) return [];
                const cells = [];
                const first = new Date(this.calYear, this.calMonth, 1);
                let startWd = first.getDay();
                startWd = startWd === 0 ? 6 : startWd - 1;
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const today = new Date(); today.setHours(0,0,0,0);
                const todayStr = this._toStr(today);

                for (let i = 0; i < startWd; i++) {
                    cells.push({ key: 'e' + i, date: null, day: '', disabled: true });
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const dt = new Date(this.calYear, this.calMonth, d);
                    const ds = this._toStr(dt);
                    const isSun = dt.getDay() === 0;
                    const isFuture = dt > today;

                    // Max_days cheklovi: end tanlayotganda start+maxDays dan keyingi kunlar disabled
                    let beyondMax = false;
                    if (this.selecting === 'end' && this.startDate && this.maxEndDateStr) {
                        beyondMax = ds > this.maxEndDateStr;
                    }

                    cells.push({
                        key: ds,
                        date: dt,
                        dateStr: ds,
                        day: d,
                        isSunday: isSun,
                        isToday: ds === todayStr,
                        disabled: isFuture || beyondMax,
                        inRange: this._inRange(ds)
                    });
                }
                return cells;
            },

            // ---- Methods ----
            init() {
                const today = new Date();
                if (this.startDate) {
                    const sd = new Date(this.startDate);
                    this.calMonth = sd.getMonth();
                    this.calYear = sd.getFullYear();
                } else {
                    this.calMonth = today.getMonth();
                    this.calYear = today.getFullYear();
                }
                this.checkDeadline();
                if (this.startDate && this.endDate) {
                    this.selecting = 'start';
                    this.fetchAssessments();
                }
            },

            onReasonChange() {
                // Sabab o'zgarganda, agar tanlangan range maxDays (yakshanbasiz) dan katta bo'lsa — endDate ni tozalash
                if (this.startDate && this.endDate && this.maxDays) {
                    const nonSunDays = this._countNonSundays(this.startDate, this.endDate);
                    if (nonSunDays > this.maxDays) {
                        this.endDate = '';
                        this.selecting = 'end';
                        this.assessments = [];
                        this.searched = false;
                        this.deadlineWarning = '';
                        this.deadlineExpired = false;
                    }
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
                    this.deadlineWarning = '';
                    this.deadlineExpired = false;
                } else {
                    // end tanlash
                    if (dateStr < this.startDate) {
                        this.startDate = dateStr;
                        this.endDate = '';
                        this.selecting = 'end';
                        this.assessments = [];
                        this.searched = false;
                        this.deadlineWarning = '';
                        this.deadlineExpired = false;
                        return;
                    }
                    this.endDate = dateStr;
                    this.selecting = 'start';
                    this.showMainCal = false;
                    this.checkDeadline();
                    if (!this.deadlineExpired) {
                        this.fetchAssessments();
                    }
                }
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
            // Yakshanbasiz kunlar soni
            _countNonSundays(startStr, endStr) {
                const s = new Date(startStr), e = new Date(endStr);
                if (e < s) return 0;
                let count = 0;
                let d = new Date(s);
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
                const today = new Date();
                const cm = today.getMonth(), cy = today.getFullYear();
                try {
                    const resp = await fetch('{{ route("student.absence-excuses.missed-assessments") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ start_date: this.startDate, end_date: this.endDate }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.assessments = (data.assessments || []).map(a => ({
                            ...a, makeup_date: '', makeup_start: '', makeup_end: '',
                            jn_selecting: 'start', cal_month: cm, cal_year: cy, show_cal: false
                        }));
                        // Serverdan JN topilmagan fanlar uchun JN card qo'shish
                        const jnSubjects = data.jn_subjects || [];
                        const existingJnSubjects = this.assessments
                            .filter(a => a.assessment_type === 'jn')
                            .map(a => a.subject_name);
                        jnSubjects.forEach(sub => {
                            if (!existingJnSubjects.includes(sub.subject_name)) {
                                this.assessments.push({
                                    subject_name: sub.subject_name, subject_id: sub.subject_id || '',
                                    assessment_type: 'jn', assessment_type_code: '100',
                                    original_date: this.startDate, makeup_date: '',
                                    makeup_start: '', makeup_end: '', jn_selecting: 'start',
                                    cal_month: cm, cal_year: cy, show_cal: false, is_default_jn: true
                                });
                            }
                        });
                    }
                } catch (e) { console.error('Xatolik:', e); }
                this.loading = false; this.searched = true;
            },

            // ---- Mini calendar methods ----
            getMiniMonthLabel(index) {
                const item = this.assessments[index];
                return item ? MONTHS_UZ[item.cal_month] + ' ' + item.cal_year : '';
            },
            miniPrevMonth(index) {
                const item = this.assessments[index];
                const today = new Date();
                const cur = item.cal_year * 12 + item.cal_month;
                const min = today.getFullYear() * 12 + today.getMonth();
                if (cur <= min) return;
                if (item.cal_month === 0) { item.cal_month = 11; item.cal_year--; }
                else item.cal_month--;
            },
            miniNextMonth(index) {
                const item = this.assessments[index];
                if (item.cal_month === 11) { item.cal_month = 0; item.cal_year++; }
                else item.cal_month++;
            },
            // Bugundan boshlab totalDays ta yakshanbasiz kun — max sana
            get miniMaxDateStr() {
                const allowed = this.totalDays;
                if (!allowed) return null;
                const today = new Date(); today.setHours(0,0,0,0);
                let count = 0;
                let d = new Date(today);
                while (count < allowed) {
                    if (d.getDay() !== 0) count++;
                    if (count < allowed) d.setDate(d.getDate() + 1);
                }
                return this._toStr(d);
            },
            getMiniCells(index) {
                const item = this.assessments[index];
                if (!item) return [];
                const cells = [];
                const first = new Date(item.cal_year, item.cal_month, 1);
                let startWd = first.getDay();
                startWd = startWd === 0 ? 6 : startWd - 1;
                const daysInMonth = new Date(item.cal_year, item.cal_month + 1, 0).getDate();
                const today = new Date(); today.setHours(0,0,0,0);
                const todayStr = this._toStr(today);
                const maxDate = this.miniMaxDateStr;
                // Shu fan ichidagi JN range ni topish (faqat non-JN lar uchun)
                let jnStart = '', jnEnd = '';
                if (item.assessment_type !== 'jn') {
                    const sameSubjectJn = this.assessments.find(
                        a => a.assessment_type === 'jn' && a.subject_name === item.subject_name
                    );
                    jnStart = sameSubjectJn?.makeup_start || '';
                    jnEnd = sameSubjectJn?.makeup_end || '';
                }
                // Yakuniy test uchun: OSKI tanlangan kunni bloklash
                let oskiDate = '';
                if (item.assessment_type === 'test') {
                    const sameSubjectOski = this.assessments.find(
                        a => a.assessment_type === 'oski' && a.subject_name === item.subject_name
                    );
                    oskiDate = sameSubjectOski?.makeup_date || '';
                }
                for (let i = 0; i < startWd; i++) {
                    cells.push({ key: 'e' + i, date: null, day: '', disabled: true });
                }
                for (let d = 1; d <= daysInMonth; d++) {
                    const dt = new Date(item.cal_year, item.cal_month, d);
                    const ds = this._toStr(dt);
                    const isSun = dt.getDay() === 0;
                    const isPast = dt < today;
                    const beyondLimit = maxDate ? ds > maxDate : false;
                    // Non-JN: shu fan JN range ichidagi sanalar band
                    let takenByJn = false;
                    if (jnStart && jnEnd) {
                        takenByJn = ds >= jnStart && ds <= jnEnd && !isSun;
                    }
                    // Yakuniy test: OSKI tanlangan kun band
                    let takenByOski = false;
                    if (oskiDate && ds === oskiDate) {
                        takenByOski = true;
                    }
                    cells.push({
                        key: ds, date: dt, dateStr: ds, day: d,
                        isSunday: isSun, isToday: ds === todayStr,
                        disabled: isPast || isSun || beyondLimit || takenByJn || takenByOski,
                        takenByJn: takenByJn,
                        takenByOski: takenByOski
                    });
                }
                return cells;
            },
            miniPickDate(index, dateStr) {
                const item = this.assessments[index];
                if (item.assessment_type === 'jn') {
                    if (item.jn_selecting === 'start') {
                        item.makeup_start = dateStr;
                        item.makeup_end = '';
                        item.jn_selecting = 'end';
                    } else {
                        if (dateStr < item.makeup_start) {
                            item.makeup_start = dateStr;
                            item.makeup_end = '';
                            item.jn_selecting = 'end';
                            return;
                        }
                        item.makeup_end = dateStr;
                        item.jn_selecting = 'start';
                        item.show_cal = false;
                        // Shu fan ichidagi boshqa testlarning JN range ga tushgan sanalarini tozalash
                        this.assessments.forEach(a => {
                            if (a.assessment_type !== 'jn' && a.subject_name === item.subject_name && a.makeup_date) {
                                if (a.makeup_date >= item.makeup_start && a.makeup_date <= item.makeup_end) {
                                    a.makeup_date = '';
                                }
                            }
                        });
                    }
                } else {
                    item.makeup_date = item.makeup_date === dateStr ? '' : dateStr;
                    if (item.makeup_date) item.show_cal = false;
                    // OSKI tanlanganda, shu fan yakuniy test ning o'sha kunini tozalash
                    if (item.assessment_type === 'oski' && item.makeup_date) {
                        this.assessments.forEach(a => {
                            if (a.assessment_type === 'test' && a.subject_name === item.subject_name && a.makeup_date === item.makeup_date) {
                                a.makeup_date = '';
                            }
                        });
                    }
                }
            },
            miniInRange(index, dateStr) {
                const item = this.assessments[index];
                if (item.assessment_type !== 'jn' || !item.makeup_start || !item.makeup_end) return false;
                return dateStr > item.makeup_start && dateStr < item.makeup_end;
            },
            clearMiniDates(index) {
                const item = this.assessments[index];
                if (item.assessment_type === 'jn') {
                    item.makeup_start = '';
                    item.makeup_end = '';
                    item.jn_selecting = 'start';
                } else {
                    item.makeup_date = '';
                }
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
                return {jn:"Joriy nazorat",mt:"Mustaqil ta'lim",oski:"YN (OSKE)",test:"YN (Test)"}[t] || t;
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
