<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Visa Application</h2>
    </x-slot>

    {{-- intl-tel-input CSS --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/css/intlTelInput.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .va-card {
            box-shadow: 0 24px 60px -28px rgba(59,130,246,0.30), 0 12px 28px -16px rgba(15,23,42,0.10);
        }
        .va-hero {
            background: linear-gradient(135deg, #2b5ea7 0%, #3b82f6 100%);
        }
        .va-input {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            color: #0f172a;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            text-transform: uppercase;
        }
        .va-input::placeholder { text-transform: none; color: #94a3b8; }
        .va-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
            background: #fff;
        }
        .va-input[type=file] { padding: 8px 10px; font-size: 13px; text-transform: none; }
        .va-label { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px; display: block; }
        .va-required { color: #dc2626; }
        .va-hint { font-size: 11px; color: #64748b; margin-top: 3px; }

        .va-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 10px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            background: #f8fafc;
            height: 150px;
            overflow: auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .va-dropzone.has-file {
            align-items: flex-start;
            justify-content: flex-start;
            padding: 6px;
        }
        .va-dropzone:hover { background: #f1f5f9; border-color: #94a3b8; }
        .va-dropzone.has-file { border-color: #10b981; background: #ecfdf5; }
        .va-dropzone canvas, .va-dropzone img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .va-dropzone-placeholder {
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
        .va-dropzone-filename {
            position: sticky;
            top: 0;
            background: rgba(236, 253, 245, 0.95);
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #047857;
            border-bottom: 1px solid #a7f3d0;
            margin: -6px -6px 6px -6px;
            z-index: 1;
        }
        .va-file-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        .va-msg-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 999px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }
        .va-msg-chip[data-active="1"] {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1e3a8a;
        }
        .va-btn-primary {
            background: linear-gradient(135deg,#2b5ea7,#3b82f6);
            color: #fff;
            font-weight: 700;
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 14px;
            box-shadow: 0 4px 14px -4px rgba(59,130,246,0.55);
        }
        .va-btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg,#1e3a8a,#2563eb);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px -4px rgba(59,130,246,0.6);
        }
        .va-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .va-input.va-input-error,
        .iti.va-input-error input,
        .va-dropzone.va-input-error { /* ehtiyot uchun */
            border-color: #ef4444 !important;
            background: #fef2f2 !important;
            box-shadow: 0 0 0 4px rgba(239,68,68,0.12) !important;
        }
        .va-error-text {
            display: none;
            font-size: 11px;
            color: #dc2626;
            font-weight: 600;
            margin-top: 4px;
        }
        .va-error-text.show { display: block; }

        .iti { width: 100%; }
        .iti__selected-dial-code { font-size: 14px; }
        .iti__country { font-size: 14px; }

        .va-status-pending { background:#fef3c7; color:#92400e; }
        .va-status-reviewing { background:#dbeafe; color:#1e40af; }
        .va-status-approved { background:#d1fae5; color:#065f46; }
        .va-status-rejected { background:#fee2e2; color:#991b1b; }

        @keyframes va-pop {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.06); }
        }
    </style>

    <div class="px-3 py-4 sm:py-6">
        <div class="max-w-3xl mx-auto">

            {{-- HERO --}}
            <div class="va-card bg-white rounded-2xl overflow-hidden">
                <div class="va-hero px-5 py-4 text-white flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.18);">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-base sm:text-lg font-bold leading-snug">Visa Application Form</h1>
                        <p class="text-xs sm:text-sm text-white/90 mt-1 leading-snug">
                            Please fill all the fields carefully and check for spelling errors before submitting.
                            This information will be used for applying for visa.
                            <span class="block mt-1 text-yellow-200 font-semibold">⚠ Application may be cancelled if your provided information is wrong!</span>
                        </p>
                    </div>
                </div>

                {{-- STATUS CARD (eng oxirgi arizam) --}}
                @if($latest)
                    @php
                        $statusMeta = [
                            'pending'   => ['label' => 'Pending review', 'icon_bg' => '#fef3c7', 'icon_fg' => '#92400e', 'desc' => 'Your application has been received and is waiting to be reviewed.'],
                            'reviewing' => ['label' => 'Being reviewed', 'icon_bg' => '#dbeafe', 'icon_fg' => '#1e40af', 'desc' => 'Your application is currently being reviewed by the office.'],
                            'approved'  => ['label' => 'Approved',       'icon_bg' => '#d1fae5', 'icon_fg' => '#065f46', 'desc' => 'Your application has been approved. Please follow the next steps from the office.'],
                            'rejected'  => ['label' => 'Rejected',       'icon_bg' => '#fee2e2', 'icon_fg' => '#991b1b', 'desc' => 'Your application was rejected. See the note below and submit a new one if needed.'],
                        ];
                        $m = $statusMeta[$latest->status];
                    @endphp
                    <div class="px-5 py-4 border-b border-slate-200" style="background:#fafbff;">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:{{ $m['icon_bg'] }};color:{{ $m['icon_fg'] }};">
                                @if($latest->status === 'approved')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @elseif($latest->status === 'rejected')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                @elseif($latest->status === 'reviewing')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-800 text-base">{{ $m['label'] }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase va-status-{{ $latest->status }}">#{{ $latest->application_number }}</span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-600 mt-1 leading-snug">{{ $m['desc'] }}</p>
                                <div class="text-[11px] text-slate-500 mt-2">Submitted: {{ $latest->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                        </div>

                        @if($latest->admin_note)
                            <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                <div class="text-[11px] font-bold text-amber-800 uppercase tracking-wide mb-1">Office note</div>
                                <div class="text-sm text-amber-900">{{ $latest->admin_note }}</div>
                            </div>
                        @endif

                        {{-- Avvalgi arizalar tarixi (ixtiyoriy) --}}
                        @if($applications->count() > 1)
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-600 hover:underline">Previous attempts ({{ $applications->count() - 1 }})</summary>
                                <div class="mt-2 space-y-1.5">
                                    @foreach($applications->skip(1) as $app)
                                        <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-slate-200 text-xs">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-slate-800">#{{ $app->application_number }}</span>
                                                <span class="text-slate-500 hidden sm:inline">·</span>
                                                <span class="text-slate-600">{{ $app->created_at->format('d.m.Y H:i') }}</span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase va-status-{{ $app->status }}">{{ $app->status }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        @if($canSubmit && $latest->status === 'rejected')
                            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                                You can submit a new application below.
                            </div>
                        @endif
                    </div>
                @endif

                @if($canSubmit)
                {{-- FORMA --}}
                <form id="visaForm" method="POST" enctype="multipart/form-data" action="{{ route('student.visa-application.store') }}" class="px-5 py-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="sm:col-span-2">
                            <label class="va-label">Student ID <span class="va-required">*</span></label>
                            <input type="text" name="student_number" class="va-input" required
                                   value="{{ $student->student_id_number ?? '' }}">
                            <div class="va-error-text @if($errors->has('student_number')) show @endif" data-error-for="student_number">{{ $errors->first('student_number') }}</div>
                        </div>

                        <div>
                            <label class="va-label">Last name <span class="va-required">*</span></label>
                            <input type="text" name="last_name" class="va-input" required
                                   value="{{ old('last_name', mb_strtoupper($student->second_name ?? '')) }}">
                            <div class="va-hint">If you do not have a last name, write your father's name.</div>
                            <div class="va-error-text @if($errors->has('last_name')) show @endif" data-error-for="last_name">{{ $errors->first('last_name') }}</div>
                        </div>

                        <div>
                            <label class="va-label">First name <span class="va-required">*</span></label>
                            <input type="text" name="first_name" class="va-input" required
                                   value="{{ old('first_name', mb_strtoupper($student->first_name ?? '')) }}">
                            <div class="va-error-text @if($errors->has('first_name')) show @endif" data-error-for="first_name">{{ $errors->first('first_name') }}</div>
                        </div>

                        <div>
                            <label class="va-label">Middle name <span class="va-required">*</span></label>
                            <input type="text" name="middle_name" class="va-input" required
                                   value="{{ old('middle_name', mb_strtoupper($student->third_name ?? '')) }}">
                            <div class="va-hint">Write "—" if you don't have one.</div>
                            <div class="va-error-text @if($errors->has('middle_name')) show @endif" data-error-for="middle_name">{{ $errors->first('middle_name') }}</div>
                        </div>

                        <div>
                            <label class="va-label">Date of birth <span class="va-required">*</span></label>
                            @php
                                $birthVal = old('birth_date');
                                if (!$birthVal && !empty($student->birth_date)) {
                                    $birthVal = $student->birth_date instanceof \Carbon\Carbon
                                        ? $student->birth_date->format('Y-m-d')
                                        : (string) $student->birth_date;
                                }
                            @endphp
                            <input type="text" name="birth_date" id="birthdate" class="va-input" placeholder="dd.mm.yyyy" required
                                   value="{{ $birthVal }}">
                            <div class="va-error-text @if($errors->has('birth_date')) show @endif" data-error-for="birth_date">{{ $errors->first('birth_date') }}</div>
                        </div>

                        <div class="sm:col-span-2">
                            @php
                                $passportCombined = mb_strtoupper(trim((string) old('passport_number', trim(($student->passport_serial ?? '') . ($student->passport_number ?? '')))));
                                $passportSeries = old('passport_series', mb_strtoupper((string) ($student->passport_serial ?? '')));
                                $passportNumberValue = old('passport_number_value', (string) ($student->passport_number ?? ''));

                                if (($passportSeries === '' || $passportNumberValue === '') && $passportCombined !== '') {
                                    if (preg_match('/^([A-Z]*)\s*([0-9]*)$/u', $passportCombined, $matches)) {
                                        if ($passportSeries === '' && !empty($matches[1])) {
                                            $passportSeries = $matches[1];
                                        }
                                        if ($passportNumberValue === '' && !empty($matches[2])) {
                                            $passportNumberValue = $matches[2];
                                        }
                                    }
                                }
                            @endphp
                            <div class="grid grid-cols-10 gap-3">
                                <div class="col-span-3">
                                    <label class="va-label">Passport series <span class="va-required">*</span></label>
                                    <input type="text" name="passport_series" id="passport_series" class="va-input" required maxlength="10"
                                           value="{{ mb_strtoupper($passportSeries) }}" placeholder="AA">
                                    <div class="va-error-text @if($errors->has('passport_series')) show @endif" data-error-for="passport_series">{{ $errors->first('passport_series') }}</div>
                                </div>
                                <div class="col-span-7">
                                    <label class="va-label">Passport number <span class="va-required">*</span></label>
                                    <input type="text" name="passport_number_value" id="passport_number_value" class="va-input" required maxlength="40"
                                           value="{{ preg_replace('/\D+/', '', (string) $passportNumberValue) }}" placeholder="1234567" inputmode="numeric">
                                    <div class="va-error-text @if($errors->has('passport_number_value')) show @endif" data-error-for="passport_number_value">{{ $errors->first('passport_number_value') }}</div>
                                </div>
                            </div>
                            <input type="hidden" name="passport_number" id="passport_number"
                                   value="{{ mb_strtoupper($passportCombined !== '' ? $passportCombined : trim($passportSeries . $passportNumberValue)) }}">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="va-label">Phone number <span class="va-required">*</span></label>
                            <input type="tel" id="phone_number" name="phone_number" class="va-input" required style="text-transform:none;">
                            <div class="va-hint" id="phoneHint">Enter your contact phone number.</div>
                            <div class="va-error-text @if($errors->has('phone_number')) show @endif" id="phoneError" data-error-for="phone_number">{{ $errors->first('phone_number') ?: 'Invalid phone number format.' }}</div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="va-label">Messenger username <span class="va-required">*</span></label>
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <button type="button" class="va-msg-chip" data-msg="telegram" data-active="1" onclick="vaSetMessenger('telegram')">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                    Telegram
                                </button>
                                <button type="button" class="va-msg-chip" data-msg="whatsapp" data-active="0" onclick="vaSetMessenger('whatsapp')">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-.607z"/></svg>
                                    WhatsApp
                                </button>
                            </div>
                            <input type="text" name="messenger_username" id="messenger_username" class="va-input" required
                                   placeholder="@username" style="text-transform:none;">
                            <input type="hidden" name="messenger_type" id="messenger_type" value="telegram">
                            <div class="va-hint">Enter your Telegram or WhatsApp username (e.g. @yourname).</div>
                            <div class="va-error-text @if($errors->has('messenger_username')) show @endif" data-error-for="messenger_username">{{ $errors->first('messenger_username') }}</div>
                        </div>
                    </div>

                    {{-- FAYL YUKLASH --}}
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label class="va-label">Passport Copies (PDF) <span class="va-required">*</span></label>
                            <div class="va-dropzone" id="passDropzone" onclick="document.getElementById('passport_pdf').click()">
                                <div class="va-dropzone-placeholder">
                                    <svg class="w-9 h-9 mx-auto mb-1 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                                    </svg>
                                    Click to upload passport PDF
                                </div>
                            </div>
                            <input type="file" name="passport_pdf" id="passport_pdf" accept="application/pdf" required class="va-file-hidden">
                            <div class="va-hint">First and last page (where your living address is written) in one PDF. Max 2 MB.</div>
                            <div class="va-error-text @if($errors->has('passport_pdf')) show @endif" data-error-for="passport_pdf">{{ $errors->first('passport_pdf') }}</div>
                        </div>

                        <div>
                            <label class="va-label">Filled Application Form (PDF) <span class="va-required">*</span></label>
                            <div class="va-dropzone" id="appDropzone" onclick="document.getElementById('application_pdf').click()">
                                <div class="va-dropzone-placeholder">
                                    <svg class="w-9 h-9 mx-auto mb-1 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                                    </svg>
                                    Click to upload application form
                                </div>
                            </div>
                            <input type="file" name="application_pdf" id="application_pdf" accept="application/pdf" required class="va-file-hidden">
                            <div class="va-hint">PDF only, max 2 MB.</div>
                            <div class="va-error-text @if($errors->has('application_pdf')) show @endif" data-error-for="application_pdf">{{ $errors->first('application_pdf') }}</div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="va-btn-primary w-full sm:w-auto" id="submitBtn">
                            Submit Application
                        </button>
                    </div>
                </form>
                @endif
            </div>

            {{-- SUCCESS MODAL --}}
            <div id="successModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);">
                <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl" style="animation:va-pop 0.4s ease;">
                    <div class="px-5 py-4 text-white" style="background:linear-gradient(135deg,#10b981,#059669);">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.2);">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-base">Application Submitted</div>
                                <div class="text-xs text-white/90 mt-0.5">Your visa application has been recorded.</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full flex items-center justify-center mb-4" style="background:#d1fae5;">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="text-base font-semibold text-slate-800">Application sent successfully</div>
                        <p class="text-sm text-slate-600 mt-2 leading-snug">
                            We have received your visa application. You can track its status on this page.
                        </p>
                    </div>
                    <div class="px-5 pb-5">
                        <button type="button" onclick="vaCloseSuccess()" class="va-btn-primary w-full">Close</button>
                    </div>
                </div>
            </div>

            {{-- ERROR TOAST --}}
            <div id="errorToast" class="hidden fixed bottom-4 left-4 right-4 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 sm:max-w-md z-50 px-4 py-3 rounded-lg bg-red-600 text-white text-sm font-semibold shadow-xl flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span id="errorToastText" class="leading-snug flex-1"></span>
                <button type="button" onclick="document.getElementById('errorToast').classList.add('hidden')" class="text-white/70 hover:text-white flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>

    <script>
    (function () {
        // Faqat forma mavjud bo'lsa ishlaymiz (canSubmit=true)
        const form = document.getElementById('visaForm');
        if (!form) return;

        // Phone — intl-tel-input
        const phoneEl = document.getElementById('phone_number');
        const phoneError = document.getElementById('phoneError');
        const phoneHint = document.getElementById('phoneHint');
        const passDropzone = document.getElementById('passDropzone');
        const appDropzone = document.getElementById('appDropzone');
        const passportSeriesEl = document.getElementById('passport_series');
        const passportNumberValueEl = document.getElementById('passport_number_value');
        const passportHiddenEl = document.getElementById('passport_number');
        const initialErrors = @json($errors->toArray());
        const iti = window.intlTelInput(phoneEl, {
            initialCountry: "uz",
            separateDialCode: true,
            nationalMode: false,
            formatOnDisplay: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js"
        });
        phoneEl.addEventListener('keypress', e => {
            const k = e.which || e.keyCode;
            if (k < 48 || k > 57) e.preventDefault();
        });

        // Telefon raqamni real-time validatsiya — yozayotganda qizilga aylantirish
        const errCodes = {
            0: 'Invalid phone number format.',
            1: 'Selected country does not match the number.',
            2: 'Phone number is too short.',
            3: 'Phone number is too long.',
            4: 'Phone number is not valid.',
            5: 'Invalid length.',
        };
        function vaCheckPhone() {
            const raw = phoneEl.value.trim();
            if (!raw) {
                // Bo'sh — neyutral holat (require'da emas, blur'da emas)
                phoneEl.classList.remove('va-input-error');
                phoneError.classList.remove('show');
                phoneHint.style.display = '';
                return null;
            }
            const ok = iti.isValidNumber();
            if (ok) {
                phoneEl.classList.remove('va-input-error');
                phoneError.classList.remove('show');
                phoneHint.style.display = '';
                return true;
            }
            const errCode = iti.getValidationError ? iti.getValidationError() : 0;
            phoneError.textContent = errCodes[errCode] || errCodes[0];
            phoneEl.classList.add('va-input-error');
            phoneError.classList.add('show');
            phoneHint.style.display = 'none';
            return false;
        }
        phoneEl.addEventListener('input',  vaCheckPhone);
        phoneEl.addEventListener('blur',   vaCheckPhone);
        // Mamlakat o'zgartirilsa ham qayta tekshirish
        phoneEl.addEventListener('countrychange', vaCheckPhone);

        // Date of birth
        const birthdatePicker = flatpickr("#birthdate", { dateFormat: "Y-m-d", altInput: true, altFormat: "d.m.Y", allowInput: true });

        // Messenger toggle (Telegram / WhatsApp)
        window.vaSetMessenger = function (type) {
            document.getElementById('messenger_type').value = type;
            document.querySelectorAll('.va-msg-chip').forEach(b => {
                b.dataset.active = b.dataset.msg === type ? '1' : '0';
            });
        };

        // PDF dropzone wiring — bir xil mantiq ikkala input uchun
        function vaSyncPassportNumber() {
            if (!passportHiddenEl) return;
            const series = (passportSeriesEl?.value || '').toUpperCase().replace(/[^A-Z]/g, '');
            const number = (passportNumberValueEl?.value || '').replace(/\D+/g, '');
            if (passportSeriesEl) passportSeriesEl.value = series;
            if (passportNumberValueEl) passportNumberValueEl.value = number;
            passportHiddenEl.value = (series + number).trim();
        }
        passportSeriesEl?.addEventListener('input', function () {
            vaSyncPassportNumber();
            clearFieldError('passport_series');
        });
        passportNumberValueEl?.addEventListener('input', function () {
            vaSyncPassportNumber();
            clearFieldError('passport_number_value');
        });
        vaSyncPassportNumber();

        function vaWireDropzone(zoneId, inputId, maxBytes) {
            const zone = document.getElementById(zoneId);
            const input = document.getElementById(inputId);
            if (!zone || !input) return;
            const defaultMarkup = zone.innerHTML;
            const fieldName = input.name;

            input.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) {
                    zone.classList.remove('has-file');
                    zone.innerHTML = defaultMarkup;
                    clearFieldError(fieldName);
                    return;
                }
                if (file.type !== 'application/pdf') {
                    setFieldError(fieldName, 'Only PDF files are allowed.');
                    this.value = '';
                    zone.classList.remove('has-file');
                    zone.innerHTML = defaultMarkup;
                    return;
                }
                if (file.size > maxBytes) {
                    setFieldError(fieldName, 'File too large. Max ' + Math.round(maxBytes / 1024) + ' KB allowed.');
                    this.value = '';
                    zone.classList.remove('has-file');
                    zone.innerHTML = defaultMarkup;
                    return;
                }
                // Preview — pdf.js bilan
                clearFieldError(fieldName);
                const fr = new FileReader();
                fr.onload = function () {
                    const arr = new Uint8Array(this.result);
                    pdfjsLib.getDocument(arr).promise.then(pdf => {
                        pdf.getPage(1).then(page => {
                            const viewport = page.getViewport({ scale: 1.2 });
                            const canvas = document.createElement('canvas');
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise.then(() => {
                                zone.classList.add('has-file');
                                zone.innerHTML = '<div class="va-dropzone-filename">✓ ' + file.name + '</div>';
                                zone.appendChild(canvas);
                            });
                        });
                    }).catch(() => {
                        zone.classList.add('has-file');
                        zone.innerHTML = '<div class="va-dropzone-filename">✓ ' + file.name + '</div><div class="p-3 text-xs text-emerald-700 text-center w-full">PDF uploaded (preview unavailable)</div>';
                    });
                };
                fr.readAsArrayBuffer(file);
            });
        }
        vaWireDropzone('passDropzone', 'passport_pdf', 2 * 1024 * 1024);   // 2 MB
        vaWireDropzone('appDropzone',  'application_pdf', 2 * 1024 * 1024); // 2 MB

        function getErrorEl(name) {
            return form.querySelector('[data-error-for="' + name + '"]');
        }

        function getFieldTarget(name) {
            if (name === 'birth_date') {
                return birthdatePicker?.altInput || form.elements[name];
            }
            return form.elements[name] || document.getElementById(name);
        }

        function getFieldFocusTarget(name) {
            if (name === 'birth_date') {
                return birthdatePicker?.altInput || form.elements[name];
            }
            if (name === 'passport_pdf') {
                return passDropzone;
            }
            if (name === 'application_pdf') {
                return appDropzone;
            }
            return getFieldTarget(name);
        }

        function clearFieldError(name) {
            const target = getFieldTarget(name);
            const errorEl = getErrorEl(name);
            target?.classList.remove('va-input-error');
            if (name === 'passport_pdf') {
                passDropzone?.classList.remove('va-input-error');
            }
            if (name === 'application_pdf') {
                appDropzone?.classList.remove('va-input-error');
            }
            if (errorEl) {
                errorEl.classList.remove('show');
                errorEl.textContent = name === 'phone_number' ? 'Invalid phone number format.' : '';
            }
            if (name === 'phone_number') {
                phoneHint.style.display = '';
            }
        }

        function setFieldError(name, message) {
            const target = getFieldTarget(name);
            const errorEl = getErrorEl(name);
            if (name === 'passport_pdf') {
                passDropzone?.classList.add('va-input-error');
            } else if (name === 'application_pdf') {
                appDropzone?.classList.add('va-input-error');
            } else {
                target?.classList.add('va-input-error');
            }
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.add('show');
            }
            if (name === 'phone_number') {
                phoneHint.style.display = 'none';
            }
        }

        function clearAllFieldErrors() {
            [
                'student_number',
                'last_name',
                'first_name',
                'middle_name',
                'birth_date',
                'passport_series',
                'passport_number_value',
                'phone_number',
                'messenger_username',
                'passport_pdf',
                'application_pdf'
            ].forEach(clearFieldError);
        }

        function applyErrors(errors) {
            Object.entries(errors).forEach(([name, message]) => {
                if (!message) return;
                setFieldError(name, Array.isArray(message) ? message[0] : String(message));
            });
        }

        function focusFirstError(errors) {
            const firstField = Object.keys(errors)[0];
            if (!firstField) return;
            const target = getFieldFocusTarget(firstField);
            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof target?.focus === 'function') {
                target.focus({ preventScroll: true });
            }
        }

        function getPhoneValidationMessage() {
            const raw = phoneEl.value.trim();
            if (!raw) return null;
            if (iti.isValidNumber()) return null;
            const errCode = iti.getValidationError ? iti.getValidationError() : 0;
            return errCodes[errCode] || errCodes[0];
        }

        function vaToast(msg) {
            const t = document.getElementById('errorToast');
            const txt = document.getElementById('errorToastText');
            txt.textContent = msg;
            t.classList.remove('hidden');
            // Avvalgi timerni bekor qilish, har gal yangi 6s
            if (window.__vaToastTimer) clearTimeout(window.__vaToastTimer);
            window.__vaToastTimer = setTimeout(() => t.classList.add('hidden'), 6000);
        }

        function vaShowSuccess() {
            const m = document.getElementById('successModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }

        // Forma tomonida nimadir yetishmasligini darhol tushunarli xabar bilan ko'rsatish.
        // (HTML5 required visually-hidden file input'da silent qoladi)
        function vaValidateForm(formEl) {
            const get = name => (formEl.elements[name]?.value || '').trim();
            const checks = [
                ['student_number',     'Student ID is required.'],
                ['last_name',          'Last name is required.'],
                ['first_name',         'First name is required.'],
                ['middle_name',        'Middle name is required (write "—" if you don\'t have one).'],
                ['birth_date',         'Date of birth is required.'],
                ['passport_series',    'Passport series is required.'],
                ['passport_number_value', 'Passport number is required.'],
                ['messenger_username', 'Messenger username is required.'],
            ];
            for (const [name, msg] of checks) {
                if (!get(name)) {
                    formEl.elements[name]?.focus();
                    return msg;
                }
            }
            const passFile = document.getElementById('passport_pdf').files[0];
            if (!passFile) return 'Please upload your passport copies (PDF).';
            if (passFile.type !== 'application/pdf') return 'Passport file must be a PDF.';
            if (passFile.size > 2 * 1024 * 1024) return 'Passport PDF is larger than 2 MB.';
            const appFile = document.getElementById('application_pdf').files[0];
            if (!appFile) return 'Please upload the filled application form (PDF).';
            if (appFile.type !== 'application/pdf') return 'Application file must be a PDF.';
            if (appFile.size > 2 * 1024 * 1024) return 'Application PDF is larger than 2 MB.';
            return null;
        }

        function vaValidateForm(formEl) {
            const get = name => (formEl.elements[name]?.value || '').trim();
            const errors = {};
            const addError = (name, message) => {
                if (!errors[name]) errors[name] = message;
            };

            [
                ['student_number', 'Student ID is required.'],
                ['last_name', 'Last name is required.'],
                ['first_name', 'First name is required.'],
                ['middle_name', 'Middle name is required (write "—" if you do not have one).'],
                ['birth_date', 'Date of birth is required.'],
            ].forEach(([name, message]) => {
                if (!get(name)) addError(name, message);
            });

            const passportSeries = (get('passport_series') || '').toUpperCase().replace(/[^A-Z]/g, '');
            const passportNumber = (get('passport_number_value') || '').replace(/\D+/g, '');
            const messengerUsername = (get('messenger_username') || '').replace(/^@+/, '').trim();

            if (!passportSeries) addError('passport_series', 'Passport series is required.');
            else if (!/^[A-Z]+$/.test(passportSeries)) addError('passport_series', 'Passport series must contain only letters.');

            if (!passportNumber) addError('passport_number_value', 'Passport number is required.');
            else if (!/^[0-9]+$/.test(passportNumber)) addError('passport_number_value', 'Passport number must contain only digits.');

            if (!messengerUsername) addError('messenger_username', 'Messenger username is required.');

            const phoneMessage = !phoneEl.value.trim() ? 'Phone number is required.' : getPhoneValidationMessage();
            if (phoneMessage) addError('phone_number', phoneMessage);

            const passFile = document.getElementById('passport_pdf').files[0];
            if (!passFile) addError('passport_pdf', 'Please upload your passport copies (PDF).');
            else if (passFile.type !== 'application/pdf') addError('passport_pdf', 'Passport file must be a PDF.');
            else if (passFile.size > 2 * 1024 * 1024) addError('passport_pdf', 'Passport PDF is larger than 2 MB.');

            const appFile = document.getElementById('application_pdf').files[0];
            if (!appFile) addError('application_pdf', 'Please upload the filled application form (PDF).');
            else if (appFile.type !== 'application/pdf') addError('application_pdf', 'Application file must be a PDF.');
            else if (appFile.size > 2 * 1024 * 1024) addError('application_pdf', 'Application PDF is larger than 2 MB.');

            return errors;
        }

        ['student_number', 'last_name', 'first_name', 'middle_name', 'messenger_username'].forEach(name => {
            form.elements[name]?.addEventListener('input', function () {
                clearFieldError(name);
            });
        });
        form.elements.birth_date?.addEventListener('change', function () {
            clearFieldError('birth_date');
        });
        birthdatePicker?.altInput?.addEventListener('input', function () {
            clearFieldError('birth_date');
        });
        birthdatePicker?.altInput?.addEventListener('blur', function () {
            clearFieldError('birth_date');
        });
        phoneEl.addEventListener('input', function () {
            if (!phoneEl.value.trim()) {
                clearFieldError('phone_number');
            }
        });

        // Form submit
        form.setAttribute('novalidate', 'novalidate'); // O'zimiz validate qilamiz
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearAllFieldErrors();

            const localErrors = vaValidateForm(this);
            if (Object.keys(localErrors).length > 0) {
                applyErrors(localErrors);
                focusFirstError(localErrors);
                vaToast('Please fix the highlighted fields.');
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            const origText = btn.textContent;
            btn.textContent = 'Submitting...';

            const fd = new FormData(this);
            vaSyncPassportNumber();
            fd.set('phone_number', iti.getNumber());
            const c = iti.getSelectedCountryData();
            fd.set('phone_dial_code', c.dialCode || '');
            fd.set('phone_country_iso2', c.iso2 || '');
            const uname = (fd.get('messenger_username') || '').toString().trim().replace(/^@+/, '');
            fd.set('messenger_username', uname);

            fetch(this.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            }).then(async r => {
                const text = await r.text();
                let data;
                try { data = JSON.parse(text); } catch (e) {
                    throw new Error('Server error (HTTP ' + r.status + '). Please try again.');
                }
                if (!r.ok) {
                    // Laravel 422 — barcha field xatolarini yig'amiz
                    if (data && data.errors && typeof data.errors === 'object') {
                        clearAllFieldErrors();
                        const inlineErrors = {};
                        for (const k of Object.keys(data.errors)) {
                            const arr = data.errors[k];
                            inlineErrors[k] = Array.isArray(arr) ? arr[0] : String(arr);
                        }
                        applyErrors(inlineErrors);
                        focusFirstError(inlineErrors);
                        throw new Error('Please fix the highlighted fields.');
                    }
                    throw new Error((data && (data.message || data.error)) || ('HTTP ' + r.status));
                }
                if (data && data.ok) {
                    vaShowSuccess();
                } else {
                    throw new Error((data && data.message) || 'Unknown error');
                }
            }).catch(err => {
                vaToast(err.message || 'System error');
                btn.disabled = false;
                btn.textContent = origText;
            });
        });

        if (initialErrors && Object.keys(initialErrors).length > 0) {
            applyErrors(initialErrors);
        }
    })();
    // Modal va toast hammaga umumiy — formasiz ham vaCloseSuccess/vaToast kerak emas,
    // shu sababli IIFE ichidan tashqariga global qilamiz:
    function vaCloseSuccess() {
        const m = document.getElementById('successModal');
        if (!m) return;
        m.classList.add('hidden');
        m.classList.remove('flex');
        window.location.reload();
    }
    </script>
</x-student-app-layout>
