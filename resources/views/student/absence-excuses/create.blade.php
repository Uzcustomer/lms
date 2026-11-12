<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Sababli dars qoldirish arizasi') }}
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
        /* Oqimda turadi: ochilganda sahifa cho'ziladi va skroll qilinadi
           (absolute bo'lsa pastdagi kontent kam bo'lganda kesilib qolardi) */
        .cal-dropdown {
            position: relative; margin-top: 4px; z-index: 50; scroll-margin-bottom: 96px;
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

        /* ======= QAYTA TOPSHIRISH JADVALI (bir fandan kuniga bitta retake) ======= */
        .ae-plan-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 12px; }
        .ae-plan-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .ae-plan-table th {
            text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;
            letter-spacing: .04em; background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0;
        }
        .ae-plan-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
        .ae-plan-table tr:last-child td { border-bottom: none; }
        .ae-plan-table td.ae-plan-date { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }
        .ae-plan-wd { font-weight: 500; color: #94a3b8; font-size: 12px; margin-left: 4px; }
        .ae-plan-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .ae-plan-moved { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #fef3c7; color: #92400e; }
        .ae-plan-move { padding: 4px 10px; border: 1px solid #c7d2fe; border-radius: 8px; font-size: 12px; font-weight: 700; color: #4f46e5; background: #fff; }
        .ae-plan-move:hover { background: #eef2ff; }
        .ae-plan-day { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #c7d2fe; border-radius: 8px; font-size: 13px; font-weight: 600; color: #1e293b; background: #fff; }
        .ae-plan-day:hover { border-color: #4f46e5; background: #eef2ff; }
        .ae-plan-day-load { font-size: 11px; font-weight: 700; color: #4f46e5; }

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

            /* Qayta topshirish jadvali */
            .ae-plan-table { font-size: 12px; }
            .ae-plan-table th, .ae-plan-table td { padding: 6px 8px; }

            /* Fan grid mobile */

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

        /* Submit modal spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        [x-cloak] { display: none !important; }
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

            <form method="POST" action="{{ route('student.absence-excuses.store') }}" enctype="multipart/form-data"
                  @submit="submitting = true">
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
                            <h3 class="text-lg font-bold text-gray-900">{{ __('Ariza ma\'lumotlari') }}</h3>
                            <p class="text-sm text-gray-500 mt-0.5">{{ __('Sabab, hujjat raqami va tasdiqlovchi fayl') }}</p>
                        </div>
                    </div>
                    <div class="ae-card-body space-y-6">
                        {{-- ROW 1: Sabab + Hujjat raqami + Tasdiqlovchi hujjat --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="reason" class="ae-label">{{ __('Sabab') }} <span class="req">*</span></label>
                                <select name="reason" id="reason" required x-model="reason"
                                        @change="onReasonChange()" class="ae-select">
                                    <option value="">{{ __('Sababni tanlang...') }}</option>
                                    @foreach($reasons as $key => $data)
                                        <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $data['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('reason')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="doc_number" class="ae-label">{{ __('Hujjat raqami') }} <span class="req">*</span></label>
                                <input type="text" name="doc_number" id="doc_number"
                                       value="{{ old('doc_number') }}" placeholder="{{ __('Masalan: 123/2026') }}"
                                       required class="ae-input">
                                @error('doc_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="ae-label">{{ __('Tasdiqlovchi hujjat') }} <span class="req">*</span></label>
                                <label for="file" class="ae-file-zone block" :class="fileName ? 'has-file' : ''"
                                       @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50')"
                                       @dragleave.prevent="$el.classList.remove('border-indigo-400','bg-indigo-50')"
                                       @drop.prevent="handleDrop($event)">
                                    <div x-show="!fileName" class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500">{{ __('Faylni') }} <span class="text-indigo-600 font-semibold">{{ __('tanlang') }}</span> <span class="text-gray-400">(PDF, JPG)</span></span>
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
                                <p class="font-semibold text-indigo-900 text-base">{{ __('Talab qilinadigan hujjat:') }}</p>
                                <p class="text-indigo-700 mt-1 text-base" x-text="selectedReason?.document"></p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <template x-if="selectedReason?.max_days">
                                        <span class="inline-flex items-center px-3 py-1 text-sm font-bold bg-indigo-100 text-indigo-700 rounded-lg">
                                            {{ __('Maksimum:') }} <span x-text="selectedReason?.max_days" class="ml-1"></span> {{ __('kun') }}
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
                                <label class="ae-label">{{ __('Sana oralig\'i') }} <span class="req">*</span></label>

                                <template x-if="!reason">
                                    <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3">
                                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        <p class="text-sm font-medium text-amber-700">{{ __('Avval sababni tanlang') }}</p>
                                    </div>
                                </template>

                                <template x-if="reason">
                                    <div>
                                        {{-- Input trigger --}}
                                        <div class="cal-trigger" :class="showMainCal ? 'active' : ''"
                                             @click="showMainCal = !showMainCal; if (showMainCal) $nextTick(() => $refs.mainCal.scrollIntoView({ block: 'nearest', behavior: 'smooth' }))">
                                            <div>
                                                <template x-if="startDate && endDate">
                                                    <span class="cal-trigger-text" x-text="fmtDate(startDate) + '  —  ' + fmtDate(endDate) + ' (' + totalDays + ' kun)'"></span>
                                                </template>
                                                <template x-if="startDate && !endDate">
                                                    <span class="cal-trigger-text"><span x-text="fmtDate(startDate)"></span> <span class="text-gray-400 font-normal">— {{ __('tugash sanasini tanlang') }}</span></span>
                                                </template>
                                                <template x-if="!startDate">
                                                    <span class="cal-trigger-placeholder">{{ __('Boshlanish') }} — {{ __('Tugash') }}</span>
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
                                        <div x-show="showMainCal" x-ref="mainCal" x-transition.origin.top x-cloak class="cal-dropdown">
                                            {{-- Hint --}}
                                            <div x-show="startDate && !endDate && selecting === 'end'" class="px-3 py-1.5 bg-blue-50 text-xs text-blue-600 font-medium flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                {{ __('Tugash sanasini tanlang') }}
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

                {{-- Sanalar boshqa arizaga tushsa — yuborib bo'lmaydi --}}
                <div x-show="overlapError" x-cloak class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4" role="alert">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm font-semibold text-red-700" x-text="overlapError"></p>
                </div>

                {{-- ===== CARD 3: O'TKAZIB YUBORILGAN NAZORATLAR ===== --}}

                {{-- Loading --}}
                <div x-show="loading" x-cloak class="ae-card mb-5">
                    <div class="p-10 text-center">
                        <svg class="animate-spin mx-auto h-10 w-10 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-3 text-base text-gray-500 font-medium">{{ __('Nazoratlar tekshirilmoqda...') }}</p>
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
                                <h3 class="text-lg font-bold text-gray-900">{{ __('O\'tkazib yuborilgan nazoratlar') }}</h3>
                                <p class="text-sm text-gray-500 mt-0.5">{{ __('Qayta topshirish kunlari avtomatik belgilanadi — bir fandan kuniga bitta retake') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-indigo-600" x-text="retakeDaysCount"></span>
                                <span class="text-gray-400 text-sm">{{ __('kun') }}</span>
                            </div>
                        </div>
                        <div class="ae-card-body">
                            <div x-show="schedule.length > 0" class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <p class="text-sm text-gray-500">{{ __('Kunni o\'zgartirish uchun «Ko\'chirish» ni bosing — faqat bo\'sh kunlar ko\'rsatiladi (bir kunda ko\'pi bilan 3 ta fan).') }}</p>
                                <button type="button" x-show="manual" x-cloak @click="resetPlan()"
                                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">{{ __('Avtomatik jadvalga qaytarish') }}</button>
                            </div>
                            <div class="ae-plan-table-wrap" x-show="schedule.length > 0">
                                <table class="ae-plan-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Sana') }}</th>
                                            <th>{{ __('Fan') }}</th>
                                            <th>{{ __('Nazorat') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <template x-for="row in schedule" :key="row.key">
                                        <tbody>
                                            <tr :class="moving === row.idx ? 'bg-indigo-50' : ''">
                                                <td class="ae-plan-date"><span x-text="fmtDate(row.date)"></span><span class="ae-plan-wd" x-text="weekdayName(row.date)"></span></td>
                                                <td>
                                                    <span x-text="row.subject_name"></span>
                                                    <span x-show="row.moved" class="ae-plan-moved">{{ __('ko\'chirildi') }}</span>
                                                </td>
                                                <td><span class="ae-plan-badge" :class="'badge-' + row.type" x-text="row.label"></span></td>
                                                <td class="text-right whitespace-nowrap">
                                                    <button type="button" x-show="row.type !== 'mt'" @click="moving = moving === row.idx ? null : row.idx"
                                                            class="ae-plan-move">
                                                        <span x-show="moving !== row.idx">{{ __('Ko\'chirish') }}</span>
                                                        <span x-show="moving === row.idx">{{ __('Yopish') }}</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr x-show="moving === row.idx" class="bg-indigo-50">
                                                <td colspan="4">
                                                    <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('Bo\'sh kunlar (shu kuni retake qiladigan fanlar soni):') }}</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        <template x-for="c in (moving === row.idx ? candidateDays(row.idx) : [])" :key="c.date">
                                                            <button type="button" @click="moveTo(row.idx, c.date)" class="ae-plan-day">
                                                                <span x-text="fmtDate(c.date)"></span>
                                                                <span class="text-gray-400" x-text="weekdayName(c.date).slice(0, 3)"></span>
                                                                <span class="ae-plan-day-load" x-text="c.load + '/' + MAX_PER_DAY"></span>
                                                            </button>
                                                        </template>
                                                        <p x-show="moving === row.idx && candidateDays(row.idx).length === 0" class="text-sm text-gray-500">{{ __('Bo\'sh kun topilmadi.') }}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </template>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Har bir nazorat uchun yashirin maydonlar — sana serverda qayta hisoblanadi --}}
                    <template x-for="(item, i) in assessments" :key="i">
                        <div>
                            <input type="hidden" :name="'makeup_dates['+i+'][subject_name]'" :value="item.subject_name">
                            <input type="hidden" :name="'makeup_dates['+i+'][subject_id]'" :value="item.subject_id || ''">
                            <input type="hidden" :name="'makeup_dates['+i+'][assessment_type]'" :value="item.assessment_type">
                            <input type="hidden" :name="'makeup_dates['+i+'][assessment_type_code]'" :value="item.assessment_type_code">
                            <input type="hidden" :name="'makeup_dates['+i+'][original_date]'" :value="item.original_date">
                            <input type="hidden" :name="'makeup_dates['+i+'][makeup_date]'" :value="plan[i] || ''">
                            <input type="hidden" :name="'makeup_dates['+i+'][jn_submitted]'" :value="item.already_submitted ? '1' : '0'">
                            <input type="hidden" :name="'makeup_dates['+i+'][is_makeup_period]'" :value="item.is_makeup_period ? '1' : '0'">
                            <input type="hidden" :name="'makeup_dates['+i+'][already_submitted]'" :value="item.already_submitted ? '1' : '0'">
                        </div>
                    </template>
                    {{-- Talaba kunlarni ko'chirgan — server shu jadvalni qoidalar bo'yicha tekshiradi --}}
                    <input type="hidden" name="retake_manual" :value="manual ? '1' : '0'">
                </div>

                {{-- ===== SUBMIT ===== --}}
                <div class="flex items-center justify-end py-5">
                    <button type="submit" :disabled="!canSubmit || submitting" class="ae-submit">
                        <template x-if="!submitting">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </template>
                        <template x-if="submitting">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </template>
                        <span x-text="submitting ? '{{ __('Yuborilmoqda...') }}' : '{{ __('Ariza yuborish') }}'"></span>
                    </button>
                </div>
            </form>

            {{-- ===== LOADING MODAL ===== --}}
            <div x-show="submitting" x-cloak
                 style="position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,0.55);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;"
                 x-transition.opacity>
                <div style="background:#fff;padding:32px 40px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center;min-width:280px;max-width:90%;">
                    <div style="width:56px;height:56px;margin:0 auto 16px;border:4px solid #e0e7ff;border-top-color:#4f46e5;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                    <p style="font-size:16px;font-weight:700;color:#1e293b;margin:0 0 6px;">{{ __('Ariza yuborilmoqda...') }}</p>
                    <p style="font-size:13px;color:#64748b;margin:0;">{{ __("Iltimos kuting, sahifani yopmang.") }}</p>
                </div>
            </div>
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
            // Sanalar boshqa (kutilayotgan/tasdiqlangan) arizaga tushsa — server matni
            overlapError: '',
            noDayLimit: @js($noDayLimit ?? false),

            // Assessments
            assessments: [],
            loading: false,
            searched: false,

            // Qayta topshirish boshlanadigan kun (Y-m-d)
            retakeStart: '',
            // Bir kunda ko'pi bilan nechta fan (SababliRetakePlan::MAX_SUBJECTS_PER_DAY)
            MAX_PER_DAY: @js(\App\Services\SababliRetakePlan::MAX_SUBJECTS_PER_DAY),
            // Talaba ko'chirgan jadval (indeks => 'Y-m-d', MT siz); null — avtomatik
            manual: null,
            // Kuni ko'chirilayotgan nazorat indeksi
            moving: null,

            // Submit state (loading modal uchun)
            submitting: false,

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
            get canSubmit() {
                if (!this.reason || !this.startDate || !this.endDate) return false;
                if (this.deadlineExpired) return false;
                if (this.overlapError) return false;
                return true;
            },
            // Qayta topshirish kunlari: indeks => 'Y-m-d'.
            // App\Services\SababliRetakePlan::assign bilan bir xil — saqlashda serverda qayta hisoblanadi.
            // Bir fandan kuniga bitta retake, bir kunda ko'pi bilan 3 ta fan; navbat dars sanasi
            // bo'yicha, kun to'lsa — keyingi ish kuni. OSKI fanning oxirgi retake kunida; test bir kun oraliq bilan.
            get autoPlan() {
                const out = {};
                if (!this.retakeStart) return out;
                const MAX_SUBJECTS_PER_DAY = this.MAX_PER_DAY;
                const first = this._firstDay(this.retakeStart);
                const cmp = (x, y) => (x < y ? -1 : (x > y ? 1 : 0));
                const subj = i => String(this.assessments[i].subject_name || '').trim().toLowerCase();
                const pending = { jn: [], oski: [], test: [], mt: [] };
                this.assessments.forEach((a, i) => {
                    if (!this._isPending(a)) return;
                    (pending[a.assessment_type] || pending.jn).push(i);
                });
                const byDate = keys => keys.slice().sort((x, y) =>
                    cmp(String(this.assessments[x].original_date), String(this.assessments[y].original_date)) || cmp(subj(x), subj(y)));

                const busy = {}, next = {}, lastRetake = {}, last = {}, oskiShared = {};
                // from dan boshlab shu fan yo'q va to'lmagan birinchi kunga joylaydi
                const place = (s, from) => {
                    let d = from;
                    while (!(busy[d] && busy[d][s]) && Object.keys(busy[d] || {}).length >= MAX_SUBJECTS_PER_DAY) d = this._nextDay(d);
                    (busy[d] = busy[d] || {})[s] = true;
                    return d;
                };

                byDate(pending.jn).forEach(i => {
                    const s = subj(i), d = place(s, next[s] || first);
                    lastRetake[s] = last[s] = d; next[s] = this._nextDay(d); out[i] = d;
                });
                byDate(pending.oski).forEach(i => {
                    const s = subj(i);
                    let d;
                    if (lastRetake[s] && !oskiShared[s]) { d = lastRetake[s]; oskiShared[s] = true; }
                    else { d = place(s, next[s] || first); next[s] = this._nextDay(d); }
                    last[s] = d; out[i] = d;
                });
                byDate(pending.test).forEach(i => {
                    const s = subj(i), d = place(s, last[s] ? this._nextDay(this._nextDay(last[s])) : (next[s] || first));
                    last[s] = d; next[s] = this._nextDay(d); out[i] = d;
                });
                pending.mt.forEach(i => { const s = subj(i); out[i] = lastRetake[s] || last[s] || next[s] || first; });
                return out;
            },
            // Amaldagi jadval: talaba ko'chirgan bo'lsa — uniki (MT muddati qayta hisoblanadi), aks holda avtomatik
            get plan() {
                return this.manual ? this._withMt(this.manual) : this.autoPlan;
            },
            // Sana tartibidagi jadval (farmoyishdagi kabi)
            get schedule() {
                const plan = this.plan;
                const auto = this.manual ? this.autoPlan : plan;
                const order = { jn: 0, oski: 1, mt: 2, test: 3 };
                return Object.keys(plan).map(i => {
                    const a = this.assessments[i];
                    return {
                        key: 'p' + i,
                        idx: +i,
                        moved: a.assessment_type !== 'mt' && plan[i] !== auto[i],
                        date: plan[i],
                        subject_name: a.subject_name,
                        type: a.assessment_type,
                        order: order[a.assessment_type] ?? 9,
                        label: this.getLabel(a.assessment_type)
                            + (a.assessment_type === 'jn' ? ' — ' + this.fmtDate(a.original_date) + ' darsi' : ''),
                    };
                }).sort((x, y) => (x.date < y.date ? -1 : (x.date > y.date ? 1 : x.order - y.order)));
            },
            get retakeDaysCount() {
                return new Set(Object.values(this.plan)).size;
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
                if (!this.retakeStart) {
                    // Ertangi kundan (yakshanba bo'lsa — dushanbadan)
                    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
                    this.retakeStart = this._firstDay(this._toStr(tomorrow));
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
                        this.overlapError = '';
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
                    this.overlapError = '';
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
                        this.overlapError = '';
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
                this.overlapError = '';
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
            _parse(ds) {
                const [y, m, d] = String(ds).slice(0, 10).split('-').map(Number);
                return new Date(y, m - 1, d);
            },
            // Yakshanba bo'lsa — keyingi kun
            _firstDay(ds) {
                const d = this._parse(ds);
                if (d.getDay() === 0) d.setDate(d.getDate() + 1);
                return this._toStr(d);
            },
            // Keyingi ish kuni (yakshanbasiz)
            _nextDay(ds) {
                const d = this._parse(ds);
                d.setDate(d.getDate() + 1);
                if (d.getDay() === 0) d.setDate(d.getDate() + 1);
                return this._toStr(d);
            },
            // Topshirilgan / o'z vaqtida / MT yuklangan — kun ajratilmaydi
            _isPending(a) {
                return !a.already_submitted && !a.jn_submitted;
            },
            _subj(a) {
                return String(a.subject_name || '').trim().toLowerCase();
            },
            // MT muddati — fanning oxirgi retake kuni (SababliRetakePlan::manual bilan bir xil)
            _withMt(p) {
                const out = { ...p }, lastJn = {}, lastAny = {};
                Object.keys(p).forEach(i => {
                    const a = this.assessments[i], s = this._subj(a), d = p[i];
                    if (!lastAny[s] || d > lastAny[s]) lastAny[s] = d;
                    if (a.assessment_type === 'jn' && (!lastJn[s] || d > lastJn[s])) lastJn[s] = d;
                });
                const first = this._firstDay(this.retakeStart);
                this.assessments.forEach((a, i) => {
                    if (a.assessment_type === 'mt' && this._isPending(a)) {
                        const s = this._subj(a);
                        out[i] = lastJn[s] || lastAny[s] || first;
                    }
                });
                return out;
            },
            // Jadval qoidalari (SababliRetakePlan::validate bilan bir xil): xato matni yoki null
            planError(p) {
                const first = this._firstDay(this.retakeStart);
                const days = {}, subs = {};
                for (const i of Object.keys(p)) {
                    const a = this.assessments[i];
                    if (!a || !this._isPending(a) || a.assessment_type === 'mt') continue;
                    const t = a.assessment_type === 'oski' || a.assessment_type === 'test' ? a.assessment_type : 'jn';
                    const s = this._subj(a), d = p[i];
                    if (!d) return a.subject_name + ': kun tanlanmagan';
                    if (this._parse(d).getDay() === 0 || d < first) return a.subject_name + ': ' + this.fmtDate(d) + ' — mumkin emas';
                    days[d] = days[d] || {};
                    days[d][s] = days[d][s] || { jn: 0, oski: 0, test: 0 };
                    days[d][s][t]++;
                    subs[s] = subs[s] || { name: a.subject_name, jn: [], oski: [], test: [] };
                    subs[s][t].push(d);
                }
                for (const d of Object.keys(days)) {
                    if (Object.keys(days[d]).length > this.MAX_PER_DAY) return this.fmtDate(d) + ' kuni ' + this.MAX_PER_DAY + ' tadan ortiq fan';
                    for (const s of Object.keys(days[d])) {
                        const c = days[d][s];
                        if (c.jn + c.test > 1 || c.oski > 1) return subs[s].name + ': ' + this.fmtDate(d) + ' kuni bittadan ortiq retake';
                    }
                }
                const maxOf = list => list.reduce((m, d) => (d > m ? d : m), '');
                for (const x of Object.values(subs)) {
                    const lastJn = maxOf(x.jn);
                    if (x.oski.some(d => lastJn && d < lastJn)) return x.name + ': OSKI oxirgi retake kunidan oldin';
                    const before = maxOf([...x.jn, ...x.oski]);
                    if (before) {
                        const min = this._nextDay(this._nextDay(before));
                        if (x.test.some(d => d < min)) return x.name + ': test retake/OSKI dan bir kun oraliq bilan';
                    }
                }
                return null;
            },
            // Nazoratni ko'chirish mumkin bo'lgan kunlar: jadval qoidalari buzilmaydigan ish kunlari —
            // avtomatik jadvalning oxirgi kunidan keyin ko'pi bilan 12 ish kuni (2 hafta);
            // load — shu kuni retake qiladigan fanlar soni (ko'chirilgandan keyin)
            candidateDays(idx) {
                const base = this.manual || this._withoutMt(this.autoPlan);
                const subj = this._subj(this.assessments[idx]);
                let limit = Object.values(this._withoutMt(this.autoPlan)).reduce((m, d) => (d > m ? d : m), this._firstDay(this.retakeStart));
                for (let n = 0; n < 12; n++) limit = this._nextDay(limit);
                const out = [];
                for (let d = this._firstDay(this.retakeStart); d <= limit; d = this._nextDay(d)) {
                    if (d === base[idx] || this.planError({ ...base, [idx]: d })) continue;
                    const load = new Set(Object.keys(base)
                        .filter(i => base[i] === d && this._subj(this.assessments[i]) !== subj)
                        .map(i => this._subj(this.assessments[i]))).size;
                    out.push({ date: d, load: load + 1 });
                }
                return out;
            },
            _withoutMt(p) {
                const out = {};
                Object.keys(p).forEach(i => { if (this.assessments[i].assessment_type !== 'mt') out[i] = p[i]; });
                return out;
            },
            moveTo(idx, date) {
                const next = { ...(this.manual || this._withoutMt(this.autoPlan)), [idx]: date };
                if (this.planError(next)) return;
                this.manual = next;
                this.moving = null;
            },
            resetPlan() {
                this.manual = null;
                this.moving = null;
            },
            weekdayName(ds) {
                if (!ds) return '';
                return ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'][this._parse(ds).getDay()];
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
                if (this.noDayLimit) return;
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
                this.loading = true; this.searched = false; this.assessments = []; this.overlapError = '';
                this.manual = null; this.moving = null;
                try {
                    const resp = await fetch('{{ route("student.absence-excuses.missed-assessments") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ start_date: this.startDate, end_date: this.endDate }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.overlapError = data.overlap || '';
                        // JN — NB olingan har bir dars kuni alohida qator (server qaytaradi)
                        this.assessments = (data.assessments || []).map(a => ({
                            ...a,
                            is_makeup_period: a.is_makeup_period || false,
                            is_previous_makeup: a.is_previous_makeup || false,
                            previous_makeup_date: a.previous_makeup_date || null,
                            jn_submitted: false
                        }));                    }
                } catch (e) { console.error('Xatolik:', e); }
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
