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
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            background: #f8fafc;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .va-dropzone:hover { background: #f1f5f9; border-color: #94a3b8; }
        .va-dropzone.has-file { border-color: #10b981; background: #ecfdf5; }
        .va-dropzone canvas, .va-dropzone img {
            max-width: 100%;
            max-height: 280px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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

                {{-- AVVALGI ARIZALAR --}}
                @if($applications->isNotEmpty())
                    <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                        <div class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Your previous applications</div>
                        <div class="space-y-1.5">
                            @foreach($applications as $app)
                                <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-slate-200 text-xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-bold text-slate-800">#{{ $app->application_number }}</span>
                                        <span class="text-slate-500 hidden sm:inline">·</span>
                                        <span class="text-slate-600 truncate">{{ $app->created_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase va-status-{{ $app->status }}">{{ $app->status }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- FORMA --}}
                <form id="visaForm" method="POST" enctype="multipart/form-data" action="{{ route('student.visa-application.store') }}" class="px-5 py-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="sm:col-span-2">
                            <label class="va-label">Student ID <span class="va-required">*</span></label>
                            <input type="text" name="student_number" class="va-input" required
                                   value="{{ $student->student_id_number ?? '' }}">
                        </div>

                        <div>
                            <label class="va-label">Last name <span class="va-required">*</span></label>
                            <input type="text" name="last_name" class="va-input" required>
                            <div class="va-hint">If you do not have a last name, write your father's name.</div>
                        </div>

                        <div>
                            <label class="va-label">First name <span class="va-required">*</span></label>
                            <input type="text" name="first_name" class="va-input" required>
                        </div>

                        <div>
                            <label class="va-label">Middle name <span class="va-required">*</span></label>
                            <input type="text" name="middle_name" class="va-input" required>
                            <div class="va-hint">Write "—" if you don't have one.</div>
                        </div>

                        <div>
                            <label class="va-label">Date of birth <span class="va-required">*</span></label>
                            <input type="text" name="birth_date" id="birthdate" class="va-input" placeholder="dd.mm.yyyy" required>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="va-label">Passport number <span class="va-required">*</span></label>
                            <input type="text" name="passport_number" class="va-input" required>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="va-label">Telegram number <span class="va-required">*</span></label>
                            <input type="tel" id="phone_number" name="phone_number" class="va-input" required style="text-transform:none;">
                            <div class="va-hint">Enter the phone number that has a Telegram account.</div>
                        </div>
                    </div>

                    {{-- FAYL YUKLASH --}}
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label class="va-label">Passport Copies (PDF) <span class="va-required">*</span></label>
                            <input type="file" name="passport_pdf" id="passport_pdf" class="va-input" accept="application/pdf" required>
                            <div class="va-hint">First and last page (where your living address is written) in one PDF. Max 5 MB.</div>
                        </div>

                        <div>
                            <label class="va-label">Filled Application Form (PDF) <span class="va-required">*</span></label>
                            <div class="va-dropzone" id="appDropzone">
                                <div class="text-center text-slate-500 text-xs" id="appDropzoneText">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                                    </svg>
                                    Click to upload filled application form
                                </div>
                            </div>
                            <input type="file" name="application_pdf" id="application_pdf" accept="application/pdf" required class="hidden">
                            <div class="va-hint">PDF only, max 256 KB.</div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="va-btn-primary w-full sm:w-auto" id="submitBtn">
                            Submit Application
                        </button>
                    </div>
                </form>
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
                    <div class="px-5 py-5 text-center">
                        <div class="text-sm text-slate-700 mb-4" id="successName">—</div>
                        <div id="qrContainer" class="flex justify-center mb-4"></div>
                        <div class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Application number</div>
                        <div class="text-3xl font-extrabold text-slate-900 mt-1 tracking-wider" id="appNum">—</div>
                    </div>
                    <div class="px-5 pb-5">
                        <button type="button" onclick="vaCloseSuccess()" class="va-btn-primary w-full">Close</button>
                    </div>
                </div>
            </div>

            {{-- ERROR TOAST --}}
            <div id="errorToast" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-lg bg-red-600 text-white text-sm font-semibold shadow-xl">
                <span id="errorToastText"></span>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator/qrcode.min.js"></script>

    <script>
        // Phone — intl-tel-input
        const phoneEl = document.getElementById('phone_number');
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

        // Date of birth
        flatpickr("#birthdate", { dateFormat: "Y-m-d", altInput: true, altFormat: "d.m.Y", allowInput: true });

        // Dropzone for application PDF
        const dropzone = document.getElementById('appDropzone');
        const appInput = document.getElementById('application_pdf');
        const appText = document.getElementById('appDropzoneText');
        dropzone.addEventListener('click', () => appInput.click());

        appInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            if (file.size > 262144) {
                vaToast('PDF must be smaller than 256 KB');
                this.value = '';
                return;
            }
            if (file.type !== 'application/pdf') {
                vaToast('Only PDF files are allowed');
                this.value = '';
                return;
            }
            // Preview
            const fr = new FileReader();
            fr.onload = function () {
                const arr = new Uint8Array(this.result);
                pdfjsLib.getDocument(arr).promise.then(pdf => {
                    pdf.getPage(1).then(page => {
                        const viewport = page.getViewport({ scale: 1 });
                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise.then(() => {
                            dropzone.classList.add('has-file');
                            dropzone.innerHTML = '';
                            dropzone.appendChild(canvas);
                        });
                    });
                }).catch(() => {
                    dropzone.classList.add('has-file');
                    appText.innerHTML = '<div class="text-emerald-700 font-semibold">✓ ' + file.name + '</div>';
                });
            };
            fr.readAsArrayBuffer(file);
        });

        // Passport PDF size check
        document.getElementById('passport_pdf').addEventListener('change', function () {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                vaToast('Passport PDF must be smaller than 5 MB');
                this.value = '';
            }
        });

        function vaToast(msg) {
            const t = document.getElementById('errorToast');
            document.getElementById('errorToastText').textContent = msg;
            t.classList.remove('hidden');
            setTimeout(() => t.classList.add('hidden'), 4000);
        }

        function vaShowSuccess(data, name) {
            document.getElementById('successName').innerHTML =
                '<strong>' + name + '</strong>, your application has been submitted.';
            document.getElementById('appNum').textContent = data.application_number;
            // QR
            const qr = qrcode(0, 'M');
            qr.addData(data.verify_url || (window.location.origin + '/visa-application/verify?app=' + data.application_number));
            qr.make();
            document.getElementById('qrContainer').innerHTML = qr.createImgTag(5);
            const m = document.getElementById('successModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function vaCloseSuccess() {
            const m = document.getElementById('successModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
            // Optionally reset and reload list:
            window.location.reload();
        }

        // Form submit
        document.getElementById('visaForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (!iti.isValidNumber()) {
                vaToast('Please enter a valid Telegram phone number');
                phoneEl.focus();
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            const origText = btn.textContent;
            btn.textContent = 'Submitting...';

            const fd = new FormData(this);
            fd.set('phone_number', iti.getNumber());
            const c = iti.getSelectedCountryData();
            fd.set('phone_dial_code', c.dialCode || '');
            fd.set('phone_country_iso2', c.iso2 || '');

            fetch(this.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd,
            }).then(async r => {
                const text = await r.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Server error (HTTP ' + r.status + ')'); }
                if (!r.ok) {
                    const msg = data && data.message ? data.message : ('Validation failed');
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        throw new Error(Array.isArray(first) ? first[0] : msg);
                    }
                    throw new Error(msg);
                }
                if (data.ok) {
                    const fn = fd.get('first_name') || '';
                    const ln = fd.get('last_name') || '';
                    vaShowSuccess(data, (fn + ' ' + ln).toUpperCase());
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            }).catch(err => {
                vaToast(err.message || 'System error');
                btn.disabled = false;
                btn.textContent = origText;
            });
        });
    </script>
</x-student-app-layout>
