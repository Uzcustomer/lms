{{-- Admission wizard form (5 steps) embedded inside QABUL tab --}}
@php
    $a = $admissionData;
    $tugilgan = $a && $a->tugilgan_sana ? \Carbon\Carbon::parse($a->tugilgan_sana)->format('d.m.Y') : '';
    $passportSana = $a && $a->passport_sana ? \Carbon\Carbon::parse($a->passport_sana)->format('d.m.Y') : '';
    $chetTillari = $a?->chet_tillari ?? [];
    if (is_string($chetTillari)) { $chetTillari = json_decode($chetTillari, true) ?: []; }
    $iqtidori = $a?->iqtidori ?? [];
    if (is_string($iqtidori)) { $iqtidori = json_decode($iqtidori, true) ?: []; }
    $sportQob = $a?->sport_qobiliyat ?? [];
    if (is_string($sportQob)) { $sportQob = json_decode($sportQob, true) ?: []; }
    $existingFiles = \App\Models\StudentFile::where('student_id', $student->id)->pluck('path', 'name')->toArray();

    $sel = function($field, $optionValue) use ($a) {
        $v = old($field, $a?->{$field} ?? '');
        return (string)$v === (string)$optionValue ? 'selected' : '';
    };
    $chk = function($field) use ($a) {
        return (bool) old($field, $a?->{$field} ?? false) ? 'checked' : '';
    };
    $val = function($field, $default = '') use ($a) {
        return e(old($field, $a?->{$field} ?? $default));
    };
@endphp

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css">

<style>
.qw-wrap, .qw-wrap * { font-family: "Times New Roman", Times, serif; }
.qw-wrap { background:#f6f8fb; color:#1f2937; padding: 1.5rem; border-radius: 16px; }
.qw-wrap .h3 { font-size: 24px; }
.qw-wrap .h5 { font-size: 18px; }
.qw-wrap .card { border:0; box-shadow:0 8px 24px rgba(16,24,40,.08); border-radius:20px; }
.qw-wrap .stepper{ display:flex; gap:16px; align-items:center; justify-content:center; flex-wrap: wrap; }
.qw-wrap .step{ display:flex; align-items:center; gap:10px; }
.qw-wrap .step .index{ width:40px; height:40px; border-radius:50%; display:grid; place-items:center; font-weight:600; background:#e7f1ff; color:#0b5ed7; border:1px solid #cfe2ff; font-size:16px; }
.qw-wrap .step.active .index{ background:#0d6efd; color:#fff; border-color:#0d6efd; }
.qw-wrap .step .label{ font-weight:600; color:#6b7280; font-size:16px; }
.qw-wrap .step.active .label{ color:#1f2937; }
.qw-wrap .divider{ height:2px; background:#e5e7eb; flex:1 1 auto; min-width:40px; border-radius:2px; }
.qw-wrap .required::after{ content:" *"; color:#ef4444; font-weight:700; }
.qw-wrap .preview-embed{ border:1px dashed #cbd5e1; border-radius:12px; overflow:hidden; background:#fff; }
.qw-wrap .floating-actions{ display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #eef2f7; padding-top:1rem; }
.qw-wrap .floating-actions button { font-size: 16px; }
.qw-wrap .form-label, .qw-wrap .form-control, .qw-wrap .form-select { font-size: 16px !important; }
.qw-wrap form input:not([type=file]):not([type=checkbox]):not([type=radio]) { height: 40px; }
.qw-wrap .form-check { display: flex; align-items: center; gap: 10px; font-size: 18px; }
.qw-wrap .ts-parent { position: relative; }
.qw-wrap .ts-parent .ts-dropdown { position: absolute !important; top: 100% !important; left: 0 !important; right: 0 !important; max-height: 260px; overflow-y: auto; z-index: 1100; }
.qw-wrap .ts-parent, .qw-wrap .ts-wrapper { overflow: visible !important; }
.qw-wrap .ts-dropdown [data-selectable].option { font-size: 16px; }
.flatpickr-calendar.fp-year-only .flatpickr-months .flatpickr-monthDropdown-months,
.flatpickr-calendar.fp-year-only .flatpickr-weekdays,
.flatpickr-calendar.fp-year-only .flatpickr-innerContainer .flatpickr-days,
.flatpickr-calendar.fp-year-only .flatpickr-months .flatpickr-prev-month,
.flatpickr-calendar.fp-year-only .flatpickr-months .flatpickr-next-month { display: none; }
.qw-wrap .dy-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; }
.qw-wrap .dy-map  { width:100%; height:400px; border:1px solid #e5e7eb; border-radius:12px; }
.qw-wrap .marsshtab { margin-top: 15px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.qw-wrap .marsshtab input { background:#fff; border:1px solid #d1d5db; border-radius:6px; line-height:1.5; padding:.375rem .75rem; height: 38px; }
.qw-wrap .existing-file { display:inline-block; margin-top:6px; font-size:14px; color:#0b5ed7; }
@media (max-width:768px){ .qw-wrap .divider { display: none; } }
</style>

<div class="qw-wrap">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="mb-4">
        <p class="h3 fw-bold mb-4">Umumiy ma'lumotlar — Ko'p bosqichli forma</p>
        <div class="stepper mb-2">
            <div class="step active"><div class="index">1</div><div class="label">Shaxsiy</div></div>
            <div class="divider"></div>
            <div class="step"><div class="index">2</div><div class="label">Ta'lim</div></div>
            <div class="divider"></div>
            <div class="step"><div class="index">3</div><div class="label">DTM ma'lumotlari</div></div>
            <div class="divider"></div>
            <div class="step"><div class="index">4</div><div class="label">Ota-ona</div></div>
            <div class="divider"></div>
            <div class="step"><div class="index">5</div><div class="label">Ijtimoiy / Yakun</div></div>
        </div>
    </div>

    <form id="qwForm" method="POST" action="{{ route('admin.students.admission-data.save', $student) }}" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- ============ STEP 1: SHAXSIY ============ --}}
        <div class="card" id="qwCard1">
            <div class="card-body p-4">
                <div id="qwStep1" class="step-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Shaxsiy ma'lumotlar</h2>
                        @if($a || count($existingFiles))
                        <button type="button" id="qwClearBtn" class="btn btn-outline-danger btn-sm"
                                data-url="{{ route('admin.students.admission-data.clear', $student) }}">
                            🗑 Barcha ma'lumotlarni tozalash
                        </button>
                        @endif
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label required">Familiya</label><input type="text" class="form-control" name="familya" value="{{ $val('familya') }}" required></div>
                        <div class="col-md-4"><label class="form-label required">Ism</label><input type="text" class="form-control" name="ism" value="{{ $val('ism') }}" required></div>
                        <div class="col-md-4"><label class="form-label required">Sharif</label><input type="text" class="form-control" name="otasining_ismi" value="{{ $val('otasining_ismi') }}" required></div>

                        <div class="col-md-4"><label class="form-label required">Tug'ilgan sana</label><input type="text" class="form-control qw-date" name="tugilgan_sana" value="{{ $tugilgan }}" maxlength="10" required></div>
                        <div class="col-md-4"><label class="form-label required">JSHSHIR (14 raqam)</label><input class="form-control" name="jshshir" value="{{ $val('jshshir') }}" maxlength="14" required></div>
                        <div class="col-md-4">
                            <label class="form-label required">Jinsi</label>
                            <select class="form-select" name="jinsi" required>
                                <option value="">Tanlang…</option>
                                <option value="erkak" {{ $sel('jinsi', 'erkak') }}>Erkak</option>
                                <option value="ayol" {{ $sel('jinsi', 'ayol') }}>Ayol</option>
                            </select>
                        </div>

                        <div class="col-md-4"><label class="form-label required">Telefon raqam</label><input class="form-control qw-phone" name="tel1" value="{{ $val('tel1') }}" required></div>
                        <div class="col-md-4"><label class="form-label required">Qo'shimcha telefon</label><input class="form-control qw-phone" name="tel2" value="{{ $val('tel2') }}" required></div>
                        <div class="col-md-4"><label class="form-label required">Email</label><input type="email" class="form-control" name="email" value="{{ $val('email') }}" required></div>

                        <div class="col-md-4">
                            <label class="form-label required">Millat</label>
                            <select class="form-select" id="qw_millat" name="millat" required>
                                <option value="">Tanlang…</option>
                                @foreach(['uzbek'=>"O'zbek",'tojik'=>'Tojik','rus'=>'Rus','afgon'=>"Afg'on",'tatar'=>'Tatar','turkman'=>'Turkman','boshqa'=>'Boshqa'] as $k=>$v)
                                    <option value="{{ $k }}" {{ $sel('millat', $k) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 {{ ($a?->millat==='boshqa') ? '' : 'd-none' }}" id="qw_millat_other_wrap">
                            <label class="form-label">Millat (boshqa)</label>
                            <input class="form-control" name="millat_other" value="{{ $val('millat_other') }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Tug'ilgan joy</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Davlat</label>
                            <select class="form-select" name="tugilgan_davlat" required>
                                <option value="O`zbekiston Respublikasi" {{ $sel('tugilgan_davlat', 'O`zbekiston Respublikasi') }}>O'zbekiston Respublikasi</option>
                                <option value="Boshqa" {{ $sel('tugilgan_davlat', 'Boshqa') }}>Boshqa</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tug'ilgan viloyat</label>
                            <select class="form-select qw-viloyat" data-target="qw_tugilgan_tuman" name="tugilgan_viloyat" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Andijon viloyati','Buxoro viloyati',"Farg'ona viloyati",'Jizzax viloyati','Namangan viloyati','Navoiy viloyati','Qashqadaryo viloyati',"Qoraqalpog'iston Respublikasi",'Samarqand viloyati','Sirdaryo viloyati','Surxondaryo viloyati','Toshkent viloyati','Toshkent shahri','Xorazm viloyati'] as $v)
                                    <option {{ $sel('tugilgan_viloyat', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tug'ilgan tuman</label>
                            <select class="form-select" id="qw_tugilgan_tuman" name="tugulgan_tuman" data-current="{{ $val('tugulgan_tuman') }}" required>
                                <option value="">Tanlang…</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none">
                            <label class="form-label">Viloyat (matn)</label>
                            <input type="text" class="form-control" name="tugilgan_viloyat_text" value="{{ $val('tugilgan_viloyat_text') }}">
                        </div>
                        <div class="col-md-4 d-none">
                            <label class="form-label">Tuman (matn)</label>
                            <input type="text" class="form-control" name="tugilgan_tuman_text" value="{{ $val('tugilgan_tuman_text') }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Passport ma'lumotlari</h2>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label required">Passport seriyasi</label><input class="form-control text-uppercase" name="passport_seriya" value="{{ $val('passport_seriya') }}" maxlength="3" required></div>
                        <div class="col-md-3"><label class="form-label required">Passport raqami</label><input class="form-control" name="passport_raqam" value="{{ $val('passport_raqam') }}" maxlength="10" required></div>
                        <div class="col-md-3"><label class="form-label required">Berilgan sana</label><input class="form-control qw-date" name="passport_sana" value="{{ $passportSana }}" maxlength="10" required></div>
                        <div class="col-md-3"><label class="form-label required">Berilgan joy</label><input class="form-control" name="passport_joy" value="{{ $val('passport_joy') }}" required></div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Oliy ma'lumoti mavjudligi</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Oliy ma'lumoti mavjudligi</label>
                            <select class="form-select" id="qw_oliy_malumot" name="oliy_malumot" required>
                                <option value="">Tanlang…</option>
                                <option value="ha" {{ $sel('oliy_malumot', 'ha') }}>Ha</option>
                                <option value="yoq" {{ $sel('oliy_malumot', 'yoq') }}>Yo'q</option>
                            </select>
                        </div>
                        <div class="col-md-6 {{ ($a?->oliy_malumot === 'ha') ? '' : 'd-none' }}" id="qw_prev_otm_wrap">
                            <label class="form-label">Avval tamomlagan OTM nomi</label>
                            <input class="form-control" name="prev_otm_nomi" value="{{ $val('prev_otm_nomi') }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Yuklanadigan hujjatlar</h2>
                    <div class="row g-3">
                        @foreach([
                            ['passport_pdf', 'Pasport nusxasi (PDF)', 'application/pdf'],
                            ['propiska_pdf', 'Propiska joyi (PDF)', 'application/pdf'],
                            ['rasm_pdf', 'Rasm 3.5x4.5 (JPG)', 'image/jpeg'],
                        ] as $f)
                            <div class="col-md-6">
                                <label class="form-label required">{{ $f[1] }}</label>
                                <input class="form-control" type="file" name="{{ $f[0] }}" accept="{{ $f[2] }}" @if(!isset($existingFiles[$f[0]])) required @endif>
                                @if(isset($existingFiles[$f[0]]))
                                    <a href="{{ route('admin.students.admission-files.view', [$student, $f[0]]) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="floating-actions mt-4">
                        <button type="button" class="btn btn-primary btn-lg qw-next" data-next="2">Keyingi bosqich</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STEP 2: TA'LIM ============ --}}
        <div class="card d-none mt-3" id="qwCard2">
            <div class="card-body p-4">
                <div id="qwStep2" class="step-section">
                    <h2 class="h5 fw-bold mb-3">Oliy ta'lim muassasasi ma'lumotlari</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">OTM nomi</label>
                            <input class="form-control" name="otm_nomi" value="{{ $val('otm_nomi', 'Toshkent davlat tibbiyot universiteti Termiz filiali') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Ta'lim turi</label>
                            <select class="form-select" name="talim_turi" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Bakalavr','Magistr'] as $v)<option {{ $sel('talim_turi', $v) }}>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Ta'lim shakli</label>
                            <select class="form-select" name="talim_shakli" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Kunduzgi','Kechki','Sirtqi','Onlayn'] as $v)<option {{ $sel('talim_shakli', $v) }}>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Mutaxassislik</label>
                            <select class="form-select" name="mutaxassislik" required>
                                <option value="">Tanlang…</option>
                                @foreach([
                                    'Davolash ishi','Farmatsiya','Fundamental tibbiyot','Pediatriya ishi','Stomatologiya','Tibbiy profilaktika ishi',
                                    'Davolash ishi (Termiz tumani)','Davolash ishi (Termiz shahri)','Davolash ishi (Angor tumani)','Davolash ishi (Bandixon tumani)',
                                    'Davolash ishi (Boysun tumani)','Davolash ishi (Denov tumani)','Davolash ishi (Jarqoʻrgʻon tumani)','Davolash ishi (Oltinsoy tumani)',
                                    'Davolash ishi (Qiziriq tumani)','Davolash ishi (Sherobod tumani)','Davolash ishi (Qumqoʻrgʻon tumani)','Davolash ishi (Shoʻrchi tumani)',
                                    'Davolash ishi (Uzun tumani)','Davolash ishi (Sariosiyo tumani)','Davolash ishi (Muzrabot tumani)','Davolash ishi (Chiroqchi tumani)',
                                    'Davolash ishi (Qarshi tumani)','Davolash ishi (Qarshi shahri)','Davolash ishi (Dehqonobod tumani)','Davolash ishi (Gʻuzor tumani)',
                                    'Davolash ishi (Kasbi tumani)','Davolash ishi (Kitob tumani)','Davolash ishi (Koʻkdala tumani)','Davolash ishi (Koson tumani)',
                                    'Davolash ishi (Mirishkor tumani)','Davolash ishi (Muborak tumani)','Davolash ishi (Nishon tumani)','Davolash ishi (Qamashi tumani)',
                                    'Davolash ishi (Shahrisabz tumani)','Davolash ishi (Yakkabogʻ tumani)',
                                    'Pediatriya ishi (Angor tumani)','Pediatriya ishi (Bandixon tumani)','Pediatriya ishi (Boysun tumani)','Pediatriya ishi (Denov tumani)',
                                    'Pediatriya ishi (Jarqoʻrgʻon tumani)','Pediatriya ishi (Muzrabot tumani)','Pediatriya ishi (Oltinsoy tumani)','Pediatriya ishi (Qiziriq tumani)',
                                    'Pediatriya ishi (Qumqoʻrgʻon tumani)','Pediatriya ishi (Sariosiyo tumani)','Pediatriya ishi (Sherobod tumani)','Pediatriya ishi (Shoʻrchi tumani)',
                                    'Pediatriya ishi (Termiz shahri)','Pediatriya ishi (Termiz tumani)','Pediatriya ishi (Uzun tumani)',
                                ] as $v)
                                    <option {{ $sel('mutaxassislik', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="floating-actions mt-4">
                        <button type="button" class="btn btn-secondary btn-lg qw-back" data-back="1">⬅ Orqaga</button>
                        <button type="button" class="btn btn-primary btn-lg qw-next" data-next="3">Keyingi bosqich</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STEP 3: DTM ============ --}}
        <div class="card d-none mt-3" id="qwCard3">
            <div class="card-body p-4">
                <div id="qwStep3" class="step-section">
                    <h2 class="h5 fw-bold mb-3">DTM ma'lumotlari</h2>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label required">Abituriyent ID raqami</label><input class="form-control" name="abituriyent_id" value="{{ $val('abituriyent_id') }}" inputmode="numeric" required></div>
                        <div class="col-md-6"><label class="form-label required">Javoblar varaqasi raqami</label><input class="form-control" name="javoblar_varaqasi" value="{{ $val('javoblar_varaqasi') }}" required></div>
                        <div class="col-md-6">
                            <label class="form-label required">Ta'lim tili</label>
                            <select class="form-select" name="talim_tili" required>
                                <option value="">Tanlang…</option>
                                @foreach(["O'zbekcha",'Ruscha','Inglizcha'] as $v)<option {{ $sel('talim_tili', $v) }}>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Imtihon alifbosi</label>
                            <select class="form-select" name="imtihon_alifbosi" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Lotin','Kiril'] as $v)<option {{ $sel('imtihon_alifbosi', $v) }}>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label required">To'plagan ball</label><input type="number" class="form-control" name="toplagan_ball" value="{{ $val('toplagan_ball') }}" step="0.01" min="0" max="200" required></div>
                        <div class="col-md-6">
                            <label class="form-label required">Tavsiya turi</label>
                            <select class="form-select" name="tolov_shakli" required>
                                <option value="">Tanlang…</option>
                                @foreach([
                                    "To'lov-kontrakti asosida talabalikka tavsiya etildi",
                                    "Muddatli harbiy xizmatni o'tab harbiy qism qo'mondonligi tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha to'lov-kontrakti asosida talabalikka tavsiya etildi",
                                    "Davlat granti asosida talabalikka tavsiya etildi",
                                    "Davlat grantlari asosida qo'shimcha qabul (Kambag'al oila reyestriga kiritilgan oilalarning farzandlari)",
                                    "Nogironligi bo'lgan shaxslarni uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "Mutaxassisligi bo'yicha kamida besh yil mehnat stajiga ega bo'lgan xotin-qizlar tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha to'lov-kontrakti asosida talabalikka tavsiya etildi",
                                    "Xotin-qizlarni qo'llab-quvvatlash maqsadida berilgan tavsiyanoma bilan oliy ta'lim muassasalariga ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "O'zbekiston Respublikasi ichki ishlar organlari xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "O'zbekiston Respublikasi Qurolli Kuchlari xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "Muddatli harbiy xizmatni o'tab harbiy qism qo'mondonligi tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "O'zbekiston Respublikasi Bojxona xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "Mehribonlik uyi va Bolalar shaharchasining bitiruvchilari bo'lgan chin yetim abituriyentlar uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                    "Tabaqalashtirilgan to'lov kontrakt asosida",
                                ] as $v)
                                    <option {{ $sel('tolov_shakli', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Avvalgi ta'lim ma'lumotlari</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Davlat</label>
                            <select class="form-select" name="talim_davlat" required>
                                <option value="O`zbekiston Respublikasi" {{ $sel('talim_davlat', 'O`zbekiston Respublikasi') }}>O'zbekiston Respublikasi</option>
                                <option value="Boshqa" {{ $sel('talim_davlat', 'Boshqa') }}>Boshqa</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Ta'lim olgan viloyati</label>
                            <select class="form-select qw-viloyat" data-target="qw_talim_tuman" name="talim_viloyat" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Andijon viloyati','Buxoro viloyati',"Farg'ona viloyati",'Jizzax viloyati','Namangan viloyati','Navoiy viloyati','Qashqadaryo viloyati',"Qoraqalpog'iston Respublikasi",'Samarqand viloyati','Sirdaryo viloyati','Surxondaryo viloyati','Toshkent viloyati','Toshkent shahri','Xorazm viloyati'] as $v)
                                    <option {{ $sel('talim_viloyat', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Ta'lim olgan tumani</label>
                            <select class="form-select" id="qw_talim_tuman" name="talim_tuman" data-current="{{ $val('talim_tuman') }}" required>
                                <option value="">Tanlang…</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none">
                            <label class="form-label">Viloyat (matn)</label>
                            <input type="text" class="form-control" name="talim_viloyat_text" value="{{ $val('talim_viloyat_text') }}">
                        </div>
                        <div class="col-md-6 d-none">
                            <label class="form-label">Tuman (matn)</label>
                            <input type="text" class="form-control" name="talim_tuman_text" value="{{ $val('talim_tuman_text') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Muassasa turi</label>
                            <select class="form-select" name="muassasa_turi" required>
                                <option value="">Tanlang…</option>
                                @foreach(["O'rta maktab",'Akademik litsey, kasb-hunar maktab','Texnikum, kasb-hunar kollej',"Boshqa o'quv yurti"] as $v)
                                    <option {{ $sel('muassasa_turi', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label required">Muassasa nomi</label><input class="form-control" name="muassasa_nomi" value="{{ $val('muassasa_nomi') }}" required></div>
                        <div class="col-md-3"><label class="form-label required">O'qigan yili (boshi)</label><input class="form-control qw-year" name="oqigan_yili_boshi" value="{{ $val('oqigan_yili_boshi') }}" required></div>
                        <div class="col-md-3"><label class="form-label required">O'qigan yili (tugashi)</label><input class="form-control qw-year" name="oqigan_yili_tugashi" value="{{ $val('oqigan_yili_tugashi') }}" required></div>
                        <div class="col-md-6"><label class="form-label required">Hujjat seriya va raqami</label><input class="form-control text-uppercase" name="hujjat_seriya" value="{{ $val('hujjat_seriya') }}" placeholder="KT777111" required></div>
                        <div class="col-md-6"><label class="form-label required">O'rtacha attestat/diplom bali</label><input type="number" step="0.01" min="0" max="100" class="form-control" name="ortalacha_ball" value="{{ $val('ortalacha_ball') }}" required></div>
                        <div class="col-12">
                            <label class="form-label required">Attestat/diplom skan (PDF)</label>
                            <input class="form-control" type="file" name="attestat_pdf" accept="application/pdf" @if(!isset($existingFiles['attestat_pdf'])) required @endif>
                            @if(isset($existingFiles['attestat_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'attestat_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 fw-bold mb-3">Sertifikatlar va chet tillari</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Milliy sertifikat mavjudmi?</label>
                            <select class="form-select" id="qw_milliy_sertifikat" name="milliy_sertifikat">
                                <option value="">Tanlang…</option>
                                <option value="ha" {{ $sel('milliy_sertifikat', 'ha') }}>Ha</option>
                                <option value="yoq" {{ $sel('milliy_sertifikat', 'yoq') }}>Yo'q</option>
                            </select>
                        </div>
                        <div class="col-md-6 {{ ($a?->milliy_sertifikat === 'ha') ? '' : 'd-none' }}" id="qw_milliy_sert_pdf_wrap">
                            <label class="form-label required">Milliy sertifikat fayli (PDF)</label>
                            <input class="form-control" type="file" name="milliy_sertifikat_pdf" accept="application/pdf" @if(!isset($existingFiles['milliy_sertifikat_pdf'])) required @endif>
                            @if(isset($existingFiles['milliy_sertifikat_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'milliy_sertifikat_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                        </div>

                        <div class="col-12">
                            <label class="form-label">Qaysi chet tillarini biladi</label>
                            <select class="form-select" id="qw_chet_tillari" name="chet_tillari[]" multiple>
                                @foreach(["O'zbek",'Ingliz','Rus','Nemis','Fransuz','Arab','Turk','Xitoy','Yapon','Koreys'] as $v)
                                    <option {{ in_array($v, $chetTillari) ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                                <option value="boshqa" {{ in_array('boshqa', $chetTillari) ? 'selected' : '' }}>Boshqa</option>
                            </select>
                        </div>
                        <div class="col-md-6 {{ in_array('boshqa', $chetTillari) ? '' : 'd-none' }}" id="qw_chet_til_boshqa_wrap">
                            <label class="form-label">Boshqa til nomi</label>
                            <input class="form-control" name="chet_til_boshqa" value="{{ $val('chet_til_boshqa') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Chet tili sertifikati</label>
                            <select class="form-select" id="qw_sertifikat_turi" name="sertifikat_turi">
                                <option value="mavjud_emas" {{ $sel('sertifikat_turi', 'mavjud_emas') }}>Mavjud emas</option>
                                @foreach(['Milliy sertifikat','IELTS','TOEFL','DELF','DALF','Goethe-sertifikat','TOPIK','TORFL','JLPT','CEFR'] as $v)
                                    <option {{ $sel('sertifikat_turi', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 {{ ($a?->sertifikat_turi && $a->sertifikat_turi !== 'mavjud_emas') ? '' : 'd-none' }}" id="qw_sertifikat_ball_wrap">
                            <label class="form-label">Sertifikat bali</label>
                            <input type="number" step="0.5" min="0" max="200" class="form-control" name="sertifikat_ball" value="{{ $val('sertifikat_ball') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Abituriyent ruxsatnomasi (PDF)</label>
                            <input class="form-control" type="file" name="ruxsatnoma_pdf" accept="application/pdf" @if(!isset($existingFiles['ruxsatnoma_pdf'])) required @endif>
                            @if(isset($existingFiles['ruxsatnoma_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'ruxsatnoma_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">DTM javob varaqasi (PDF)</label>
                            <input class="form-control" type="file" name="dtm_varaqa_pdf" accept="application/pdf" @if(!isset($existingFiles['dtm_varaqa_pdf'])) required @endif>
                            @if(isset($existingFiles['dtm_varaqa_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'dtm_varaqa_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                        </div>
                    </div>

                    <div class="floating-actions mt-4">
                        <button type="button" class="btn btn-secondary btn-lg qw-back" data-back="2">⬅ Orqaga</button>
                        <button type="button" class="btn btn-primary btn-lg qw-next" data-next="4">Keyingi bosqich</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STEP 4: YASHASH + OTA-ONA ============ --}}
        <div class="card d-none mt-3" id="qwCard4">
            <div class="card-body p-4">
                <div id="qwStep4" class="step-section">
                    <h3 class="h5 fw-bold mb-3">Yashash joyi</h3>
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label required">Davlat</label>
                            <select class="form-select" name="yashash_davlat" required>
                                <option value="O`zbekiston Respublikasi" {{ $sel('yashash_davlat', 'O`zbekiston Respublikasi') }}>O'zbekiston Respublikasi</option>
                                <option value="Boshqa" {{ $sel('yashash_davlat', 'Boshqa') }}>Boshqa</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label required">Viloyat</label>
                            <select class="form-select qw-viloyat" data-target="qw_yashash_tuman" name="yashash_viloyat" required>
                                <option value="">Tanlang…</option>
                                @foreach(['Andijon viloyati','Buxoro viloyati',"Farg'ona viloyati",'Jizzax viloyati','Namangan viloyati','Navoiy viloyati','Qashqadaryo viloyati',"Qoraqalpog'iston Respublikasi",'Samarqand viloyati','Sirdaryo viloyati','Surxondaryo viloyati','Toshkent viloyati','Toshkent shahri','Xorazm viloyati'] as $v)
                                    <option {{ $sel('yashash_viloyat', $v) }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label required">Tuman</label>
                            <select class="form-select" id="qw_yashash_tuman" name="yashash_tuman" data-current="{{ $val('yashash_tuman') }}" required>
                                <option value="">Tanlang…</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-4 d-none">
                            <label class="form-label">Viloyat (matn)</label>
                            <input type="text" class="form-control" name="yashash_viloyat_text" value="{{ $val('yashash_viloyat_text') }}">
                        </div>
                        <div class="col-12 col-lg-4 d-none">
                            <label class="form-label">Tuman (matn)</label>
                            <input type="text" class="form-control" name="yashash_tuman_text" value="{{ $val('yashash_tuman_text') }}">
                        </div>
                        <div class="col-12 col-lg-12">
                            <label class="form-label required">Doimiy manzil</label>
                            <input class="form-control" name="doimiy_manzil" value="{{ $val('doimiy_manzil') }}" placeholder="Yangi hayot MFY, Navoiy ko'chasi, 9-uy" required>
                        </div>

                        <div class="col-12">
                            <div class="dy-card">
                                <div class="d-flex gap-2 mb-2">
                                    <button id="qwBtnLocate" type="button" class="btn btn-primary btn-sm">📍 Mening joylashuvim</button>
                                </div>
                                <div id="qwMap" class="dy-map"></div>
                                <div class="marsshtab">
                                    <span>Kenglik</span>
                                    <input id="qw_lat" name="kenglik" value="{{ $val('kenglik') }}" required>
                                    <span>Uzunlik</span>
                                    <input id="qw_lon" name="uzunlik" value="{{ $val('uzunlik') }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <h3 class="h5 fw-bold mb-3">Otaning ma'lumotlari</h3>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label required">Familiyasi</label><input class="form-control qw-upper" name="ota_familiya" value="{{ $val('ota_familiya') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Ismi</label><input class="form-control qw-upper" name="ota_ismi" value="{{ $val('ota_ismi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Sharifi</label><input class="form-control qw-upper" name="ota_sharifi" value="{{ $val('ota_sharifi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Telefon raqami</label><input class="form-control qw-phone" name="ota_tel" value="{{ $val('ota_tel') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Ish joyi</label><input class="form-control qw-upper" name="ota_ish_joyi" value="{{ $val('ota_ish_joyi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Lavozimi</label><input class="form-control qw-upper" name="ota_lavozimi" value="{{ $val('ota_lavozimi') }}" required></div>
                                <div class="col-12">
                                    <label class="form-label required">Otaning pasport nusxasi (PDF)</label>
                                    <input class="form-control" type="file" name="ota_passport_pdf" accept="application/pdf" @if(!isset($existingFiles['ota_passport_pdf'])) required @endif>
                                    @if(isset($existingFiles['ota_passport_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'ota_passport_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <h3 class="h5 fw-bold mb-3">Onaning ma'lumotlari</h3>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label required">Familiyasi</label><input class="form-control qw-upper" name="ona_familiya" value="{{ $val('ona_familiya') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Ismi</label><input class="form-control qw-upper" name="ona_ismi" value="{{ $val('ona_ismi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Sharifi</label><input class="form-control qw-upper" name="ona_sharifi" value="{{ $val('ona_sharifi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Telefon raqami</label><input class="form-control qw-phone" name="ona_tel" value="{{ $val('ona_tel') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Ish joyi</label><input class="form-control qw-upper" name="ona_ish_joyi" value="{{ $val('ona_ish_joyi') }}" required></div>
                                <div class="col-md-6"><label class="form-label required">Lavozimi</label><input class="form-control qw-upper" name="ona_lavozimi" value="{{ $val('ona_lavozimi') }}" required></div>
                                <div class="col-12">
                                    <label class="form-label required">Onaning pasport nusxasi (PDF)</label>
                                    <input class="form-control" type="file" name="ona_passport_pdf" accept="application/pdf" @if(!isset($existingFiles['ona_passport_pdf'])) required @endif>
                                    @if(isset($existingFiles['ona_passport_pdf']))<a href="{{ route('admin.students.admission-files.view', [$student, 'ona_passport_pdf']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <label class="form-label required">Shaxsiy ma'lumotnoma (Obyektivka, Word fayl)</label>
                        <input class="form-control" type="file" name="obyektivka" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @if(!isset($existingFiles['obyektivka'])) required @endif>
                        @if(isset($existingFiles['obyektivka']))<a href="{{ route('admin.students.admission-files.view', [$student, 'obyektivka']) }}" target="_blank" class="existing-file">Yuklangan faylni ko'rish</a>@endif
                    </div>

                    <div class="floating-actions mt-4">
                        <button type="button" class="btn btn-secondary btn-lg qw-back" data-back="3">⬅ Orqaga</button>
                        <button type="button" class="btn btn-primary btn-lg qw-next" data-next="5">Keyingi bosqich</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STEP 5: IJTIMOIY + YUTUQLAR + IQTIDOR + SPORT ============ --}}
        <div class="card d-none mt-3" id="qwCard5">
            <div class="card-body p-4">
                <div id="qwStep5" class="step-section">
                    <h3 class="h5 fw-bold mb-3">Ijtimoiy ma'lumotlari</h3>
                    <div class="row g-3">
                        @php
                            $simpleChks = [
                                ['d_kiritilgan', "Daftarlarga kiritilganligi", 'd_kiritilgan_turi', ['Temir daftar','Yoshlar daftari','Ayollar daftari']],
                                ['d_oila_azosi', "Oila a'zolaridan biri daftarlarga kiritilganligi", 'd_oila_turi', ['Temir daftar','Yoshlar daftari','Ayollar daftari']],
                            ];
                        @endphp
                        @foreach($simpleChks as $row)
                            <div class="col-12">
                                <input type="hidden" name="{{ $row[0] }}" value="0">
                                <div class="form-check">
                                    <input class="form-check-input qw-toggle" type="checkbox" data-wrap="qw_{{ $row[0] }}_wrap" id="qw_{{ $row[0] }}" name="{{ $row[0] }}" value="1" {{ $chk($row[0]) }}>
                                    <label class="form-check-label" for="qw_{{ $row[0] }}">{{ $row[1] }}</label>
                                </div>
                                <div class="mt-2 {{ $a?->{$row[0]} ? '' : 'd-none' }}" id="qw_{{ $row[0] }}_wrap">
                                    <label class="form-label">Daftar turi</label>
                                    <select class="form-select" name="{{ $row[2] }}">
                                        <option value="">Tanlang…</option>
                                        @foreach($row[3] as $v)<option {{ $sel($row[2], $v) }}>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach

                        @foreach([
                            ['kam_taminlangan', "Kam ta'minlangan oila farzandlari"],
                            ['harbiy_qaytgan', "Muddatli harbiy xizmatdan qaytganlar"],
                            ['nafaqa_oluvchi', "Nafaqa oluvchilar"],
                        ] as $row)
                            <div class="col-12">
                                <input type="hidden" name="{{ $row[0] }}" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qw_{{ $row[0] }}" name="{{ $row[0] }}" value="1" {{ $chk($row[0]) }}>
                                    <label class="form-check-label" for="qw_{{ $row[0] }}">{{ $row[1] }}</label>
                                </div>
                            </div>
                        @endforeach

                        <div class="col-12">
                            <input type="hidden" name="nogironligi" value="0">
                            <div class="form-check">
                                <input class="form-check-input qw-toggle" type="checkbox" data-wrap="qw_nogiron_extra_wrap" id="qw_nogironligi" name="nogironligi" value="1" {{ $chk('nogironligi') }}>
                                <label class="form-check-label" for="qw_nogironligi">Nogironligi mavjud</label>
                            </div>
                            <div class="row g-2 mt-2 {{ $a?->nogironligi ? '' : 'd-none' }}" id="qw_nogiron_extra_wrap">
                                <div class="col-md-4">
                                    <label class="form-label">Nogironlik guruhi</label>
                                    <select class="form-select" name="nogiron_guruh">
                                        <option value="">Tanlang…</option>
                                        @foreach(['1-guruh','2-guruh','3-guruh','4-guruh'] as $v)<option {{ $sel('nogiron_guruh', $v) }}>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Nogironlik toifasi</label>
                                    <select class="form-select" name="nogiron_toifa">
                                        <option value="">Tanlang…</option>
                                        @foreach(['Zaif ko`ruvchilar','Zaif eshituvchilar','Tayanch harakati a`zolari shikaslanganlar','Ko`zi ojizlar','Kar yoshlar','Nutqida nuqsoni borlar','Boshqa turdagi nogironligi bo`lganlar'] as $v)
                                            <option {{ $sel('nogiron_toifa', $v) }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <input type="hidden" name="yetim_talaba" value="0">
                            <div class="form-check">
                                <input class="form-check-input qw-toggle" type="checkbox" data-wrap="qw_yetim_turi_wrap" id="qw_yetim_talaba" name="yetim_talaba" value="1" {{ $chk('yetim_talaba') }}>
                                <label class="form-check-label" for="qw_yetim_talaba">Yetim talabalar</label>
                            </div>
                            <div class="mt-2 {{ $a?->yetim_talaba ? '' : 'd-none' }}" id="qw_yetim_turi_wrap">
                                <label class="form-label">Toifa</label>
                                <select class="form-select" name="yetim_turi">
                                    <option value="">Tanlang…</option>
                                    @foreach(['Chin yetim','Yarim yetim','Ijtimoiy yetim'] as $v)<option {{ $sel('yetim_turi', $v) }}>{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h3 class="h5 fw-bold mb-3">Fan olimpiadalari va boshqa yutuqlari</h3>
                    <div class="row g-3">
                        @foreach([
                            ['davlat_mukofoti', "Davlat mukofoti bilan taqdirlanganlar"],
                            ['kokrak_nishoni', "Ko'krak nishonlari bilan taqdirlanganlar"],
                            ['prezident_stip', "Prezident stipendiyasi sohiblari"],
                            ['davlat_stip', "Davlat stipendiyasi sohiblari"],
                            ['xalqaro_stip', "Xalqaro stipendiyalar sohiblari"],
                            ['resp_sport', "Respublika sport musobaqalari g'oliblari"],
                            ['xal_sport', "Xalqaro sport musobaqalari g'oliblari"],
                            ['resp_fan_olimp', "Respublika fan olimpiyadalari g'oliblari"],
                            ['xal_fan_olimp', "Xalqaro fan olimpiyadalari g'oliblari"],
                            ['boshqa_yutuq', "Boshqa yutuq va imtiyozlar"],
                        ] as $row)
                            <div class="col-12">
                                <input type="hidden" name="{{ $row[0] }}" value="0">
                                <div class="form-check">
                                    <input class="form-check-input qw-toggle" type="checkbox" data-wrap="qw_{{ $row[0] }}_wrap" id="qw_{{ $row[0] }}" name="{{ $row[0] }}" value="1" {{ $chk($row[0]) }}>
                                    <label class="form-check-label" for="qw_{{ $row[0] }}">{{ $row[1] }}</label>
                                </div>
                                <div class="mt-2 {{ $a?->{$row[0]} ? '' : 'd-none' }}" id="qw_{{ $row[0] }}_wrap">
                                    <textarea class="form-control" name="{{ $row[0] }}_desc" rows="2" placeholder="Yutuq haqida batafsil…">{{ $val($row[0].'_desc') }}</textarea>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <hr class="my-4">
                    <h3 class="h5 fw-bold mb-3">Iqtidori</h3>
                    <select class="form-select" id="qw_iqtidori" name="iqtidori[]" multiple>
                        @foreach(['Liderlik','Jamoada ishlash','Muloqot','Tahliliy fikrlash','Dasturlash','Dizayn','Ilmiy tadqiqot','Ijodkorlik','Boshqaruv','Tadbirkorlik','Mahoratli notiqlik'] as $v)
                            <option {{ in_array($v, $iqtidori) ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                        <option value="boshqa" {{ in_array('boshqa', $iqtidori) ? 'selected' : '' }}>Boshqa</option>
                    </select>
                    <div class="mt-2 {{ in_array('boshqa', $iqtidori) ? '' : 'd-none' }}" id="qw_iqtidori_boshqa_wrap">
                        <label class="form-label">Boshqa iqtidor</label>
                        <input class="form-control" name="iqtidori_boshqa" value="{{ $val('iqtidori_boshqa') }}">
                    </div>

                    <hr class="my-4">
                    <h3 class="h5 fw-bold mb-3">Sport sohasidagi qobiliyatlari</h3>
                    <select class="form-select" id="qw_sport_qobiliyat" name="sport_qobiliyat[]" multiple>
                        @foreach(['Futbol','Basketbol','Voleybol','Yengil atletika','Suzish','Kurash','Dzyudo','Boks','Shaxmat','Badminton','Stol tennisi'] as $v)
                            <option {{ in_array($v, $sportQob) ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                        <option value="boshqa" {{ in_array('boshqa', $sportQob) ? 'selected' : '' }}>Boshqa</option>
                    </select>
                    <div class="mt-2 {{ in_array('boshqa', $sportQob) ? '' : 'd-none' }}" id="qw_sport_boshqa_wrap">
                        <label class="form-label">Boshqa sport turi</label>
                        <input class="form-control" name="sport_boshqa" value="{{ $val('sport_boshqa') }}">
                    </div>

                    <div class="floating-actions mt-4">
                        <button type="button" class="btn btn-secondary btn-lg qw-back" data-back="4">⬅ Orqaga</button>
                        <button type="submit" class="btn btn-success btn-lg">Ma'lumotlarni saqlash</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
(function(){
    if (window.__qwInit) return;
    window.__qwInit = true;

    const TUMAN_BY_VILOYAT = {
        "Andijon viloyati":["Andijon shahri","Andijon tumani","Asaka tumani","Baliqchi tumani","Bo'ston tumani","Buloqboshi tumani","Izboskan tumani","Jalaquduq tumani","Marhamat tumani","Oltinko'l tumani","Paxtaobod tumani","Qo'rg'ontepa tumani","Shahrixon tumani","Xo'jaobod tumani","Ulug'nor tumani"],
        "Buxoro viloyati":["Buxoro shahri","Buxoro tumani","Kogon shahri","Kogon tumani","G'ijduvon tumani","Jondor tumani","Olot tumani","Peshku tumani","Qorako'l tumani","Qorovulbozor tumani","Romitan tumani","Shofirkon tumani","Vobkent tumani"],
        "Farg'ona viloyati":["Farg'ona shahri","Farg'ona tumani","Qo'qon shahri","Quvasoy shahri","Beshariq tumani","Bog'dod tumani","Buvayda tumani","Dang'ara tumani","Furqat tumani","Oltiariq tumani","Quva tumani","Rishton tumani","So'x tumani","Toshloq tumani","Uchko'prik tumani","Yozyovon tumani","O'zbekiston tumani"],
        "Jizzax viloyati":["Jizzax shahri","Arnasoy tumani","Baxmal tumani","Do'stlik tumani","Forish tumani","G'allaorol tumani","Mirzacho'l tumani","Paxtakor tumani","Sharof Rashidov tumani","Yangiobod tumani","Zafarobod tumani","Zarbdor tumani","Zomin tumani"],
        "Namangan viloyati":["Namangan shahri","Namangan tumani","Chortoq tumani","Chust tumani","Kosonsoy tumani","Mingbuloq tumani","Norin tumani","Pop tumani","To'raqo'rg'on tumani","Uchqo'rg'on tumani","Uychi tumani","Yangiqo'rg'on tumani"],
        "Navoiy viloyati":["Navoiy shahri","Zarafshon shahri","Karmana tumani","Konimex tumani","Navbahor tumani","Nurota tumani","Qiziltepa tumani","Xatirchi tumani","Tomdi tumani","Uchquduq tumani"],
        "Qashqadaryo viloyati":["Qarshi shahri","Qarshi tumani","G'uzor tumani","Dehqonobod tumani","Kasbi tumani","Kitob tumani","Qamashi tumani","Koson tumani","Mirishkor tumani","Muborak tumani","Nishon tumani","Chiroqchi tumani","Ko'kdala tumani","Shahrisabz tumani","Yakkabog' tumani"],
        "Qoraqalpog'iston Respublikasi":["Nukus shahri","Amudaryo tumani","Beruniy tumani","Chimboy tumani","Ellikqal'a tumani","Kegeyli tumani","Mo'ynoq tumani","Qanliko'l tumani","Qorao'zak tumani","Qo'ng'irot tumani","Shumanay tumani","Taxtako'pir tumani","To'rtko'l tumani","Xo'jayli tumani"],
        "Samarqand viloyati":["Samarqand shahri","Samarqand tumani","Bulung'ur tumani","Ishtixon tumani","Jomboy tumani","Kattaqo'rg'on shahri","Kattaqo'rg'on tumani","Narpay tumani","Nurobod tumani","Oqdaryo tumani","Pastdarg'om tumani","Paxtachi tumani","Payariq tumani","Qo'shrabot tumani","Toyloq tumani","Urgut tumani"],
        "Sirdaryo viloyati":["Guliston shahri","Guliston tumani","Shirin shahri","Yangiyer shahri","Boyovut tumani","Mirzaobod tumani","Oqoltin tumani","Sardoba tumani","Sayxunobod tumani","Sirdaryo tumani","Xovos tumani"],
        "Surxondaryo viloyati":["Termiz shahri","Termiz tumani","Angor tumani","Bandixon tumani","Boysun tumani","Denov tumani","Jarqo'rg'on tumani","Muzrabot tumani","Oltinsoy tumani","Qiziriq tumani","Qumqo'rg'on tumani","Sariosiyo tumani","Sherobod tumani","Sho'rchi tumani","Uzun tumani"],
        "Toshkent viloyati":["Nurafshon shahri","Angren shahri","Bekobod shahri","Chirchiq shahri","Olmaliq shahri","Bekobod tumani","Bo'ka tumani","Bo'stonliq tumani","Chinoz tumani","Ohangaron tumani","Parkent tumani","Piskent tumani","Oqqo'rg'on tumani","Qibray tumani","Quyi Chirchiq tumani","Yuqori Chirchiq tumani","Yangiyo'l tumani","Zangiota tumani","Toshkent tumani"],
        "Toshkent shahri":["Bektemir tumani","Chilonzor tumani","Mirobod tumani","Mirzo Ulug'bek tumani","Sergeli tumani","Shayxontohur tumani","Uchtepa tumani","Yakkasaroy tumani","Yashnobod tumani","Yunusobod tumani","Olmazor tumani","Yangihayot tumani"],
        "Xorazm viloyati":["Urganch shahri","Urganch tumani","Bog'ot tumani","Gurlan tumani","Xonqa tumani","Hazorasp tumani","Xiva tumani","Qo'shko'pir tumani","Shovot tumani","Yangiariq tumani","Yangibozor tumani","Tuproqqal'a tumani"]
    };

    function fillTumanSelect(viloyatSel, tumanSel){
        const $v = $(viloyatSel), $t = $(tumanSel);
        if(!$v.length || !$t.length) return;
        function fill(region){
            const current = $t.attr('data-current') || '';
            const list = TUMAN_BY_VILOYAT[(region||'').trim()] || [];
            let html = '<option value="">Tanlang…</option>';
            for(const n of list){
                const sel = (n === current) ? 'selected' : '';
                html += `<option ${sel}>${n}</option>`;
            }
            $t.html(html);
        }
        fill($v.val());
        $v.on('change', function(){ $t.removeAttr('data-current'); fill(this.value); });
    }

    $(function(){
        // Step navigation
        const $cards = $('#qwCard1, #qwCard2, #qwCard3, #qwCard4, #qwCard5');
        const $steps = $('.qw-wrap .step');
        function goStep(n){
            $cards.addClass('d-none');
            $('#qwCard'+n).removeClass('d-none');
            $steps.removeClass('active').eq(n-1).addClass('active');
            const $sec = $('#qwStep'+n);
            if($sec.length){ $('html, body').animate({scrollTop: Math.max($sec.offset().top - 120, 0)}, 200); }
        }

        // ===== Step validation =====
        function setRequired($el, isReq){
            if (isReq) $el.prop('required', true);
            else { $el.prop('required', false).removeClass('is-invalid'); }
        }
        function validateStep(stepNum, options = {}){
            const showAlert = options.showAlert !== false;
            const $section = $('#qwStep' + stepNum);
            if (!$section.length) return true;
            const $required = $section.find('[required]').filter(function(){
                return $(this).closest('.d-none').length === 0 && $(this).is(':visible');
            });
            let firstInvalid = null;
            $required.each(function(){
                const $el = $(this);
                const type = (this.type || '').toLowerCase();
                let empty = false;
                if (type === 'file') {
                    const hasExisting = $el.siblings('.existing-file').length > 0;
                    empty = (!this.files || this.files.length === 0) && !hasExisting;
                } else if (this.multiple) {
                    const v = $el.val();
                    empty = !v || (Array.isArray(v) && v.length === 0);
                } else {
                    const v = ($el.val() || '').toString().trim();
                    empty = !v;
                }
                if (empty) {
                    $el.addClass('is-invalid');
                    if (!firstInvalid) firstInvalid = this;
                } else {
                    $el.removeClass('is-invalid');
                }
            });
            if (firstInvalid) {
                try { firstInvalid.focus({preventScroll: true}); } catch(_) { $(firstInvalid).trigger('focus'); }
                const off = $(firstInvalid).offset();
                if (off) $('html, body').animate({scrollTop: Math.max(off.top - 120, 0)}, 250);
                if (showAlert) {
                    setTimeout(()=> alert("Iltimos, barcha majburiy maydonlarni to'ldiring."), 100);
                }
                return false;
            }
            return true;
        }
        $(document).on('click', '.qw-next', function(){
            const next = +$(this).data('next');
            goStep(next);
        });
        $(document).on('click', '.qw-back', function(){ goStep(+$(this).data('back')); });
        $(document).on('input change', '.qw-wrap input, .qw-wrap select, .qw-wrap textarea', function(){
            $(this).removeClass('is-invalid');
        });
        $('#qwForm').on('submit', function(e){
            for (let step = 1; step <= 5; step++) {
                if (!validateStep(step, { showAlert: false })) {
                    e.preventDefault();
                    goStep(step);
                    setTimeout(() => alert(`Iltimos, ${step}-bosqichdagi barcha majburiy maydonlarni to'ldiring.`), 100);
                    return false;
                }
            }
        });

        // ===== Clear all data button =====
        $('#qwClearBtn').on('click', function(){
            const url = $(this).data('url');
            if (!confirm("Diqqat! Barcha qabul ma'lumotlari va yuklangan fayllar to'liq o'chiriladi. Davom etasizmi?")) return;
            if (!confirm("Rostan ham o'chirmoqchimisiz? Bu amalni qaytarib bo'lmaydi.")) return;
            const csrf = $('input[name="_token"]').first().val();
            const $f = $('<form>').attr({method:'POST', action:url}).css('display','none');
            $f.append($('<input>').attr({type:'hidden', name:'_token', value:csrf}));
            $f.append($('<input>').attr({type:'hidden', name:'_method', value:'DELETE'}));
            $('body').append($f);
            $f.submit();
        });

        // Conditional toggles (checkbox)
        $(document).on('change', '.qw-toggle', function(){
            const wrap = $(this).data('wrap');
            if(!wrap) return;
            $('#'+wrap).toggleClass('d-none', !this.checked);
        });

        // Millat → millat_other (conditional required)
        $('#qw_millat').on('change', function(){
            const isOther = this.value === 'boshqa';
            $('#qw_millat_other_wrap').toggleClass('d-none', !isOther);
            setRequired($('input[name="millat_other"]'), isOther);
        }).trigger('change');

        // Oliy ma'lumot → prev_otm_nomi (conditional required)
        $('#qw_oliy_malumot').on('change', function(){
            const isHa = this.value === 'ha';
            $('#qw_prev_otm_wrap').toggleClass('d-none', !isHa);
            setRequired($('input[name="prev_otm_nomi"]'), isHa);
        }).trigger('change');

        // Milliy sertifikat (conditional required for file)
        $('#qw_milliy_sertifikat').on('change', function(){
            const isHa = this.value === 'ha';
            $('#qw_milliy_sert_pdf_wrap').toggleClass('d-none', !isHa);
            const $file = $('input[name="milliy_sertifikat_pdf"]');
            const hasExisting = $file.siblings('.existing-file').length > 0;
            setRequired($file, isHa && !hasExisting);
        });

        // Davlat (country) → toggle viloyat/tuman vs text inputs and their required
        function bindCountryToggle(countryName, selectViloyat, selectTuman, textViloyat, textTuman){
            const $country = $('select[name="' + countryName + '"]');
            if (!$country.length) return;
            const $sv = $('select[name="' + selectViloyat + '"]');
            const $st = $('select[name="' + selectTuman + '"]');
            const $tv = $('input[name="' + textViloyat + '"]');
            const $tt = $('input[name="' + textTuman + '"]');
            function apply(){
                const isUz = ($country.val() || '').indexOf('zbekiston') !== -1;
                $sv.closest('.col-md-4, .col-md-6, .col-lg-4, .col-lg-6').toggleClass('d-none', !isUz);
                $st.closest('.col-md-4, .col-md-6, .col-lg-4, .col-lg-6').toggleClass('d-none', !isUz);
                $tv.closest('.col-md-4, .col-md-6, .col-lg-4, .col-lg-6').toggleClass('d-none', isUz);
                $tt.closest('.col-md-4, .col-md-6, .col-lg-4, .col-lg-6').toggleClass('d-none', isUz);
                setRequired($sv, isUz);
                setRequired($st, isUz);
                setRequired($tv, !isUz);
                setRequired($tt, !isUz);
            }
            $country.on('change', apply);
            apply();
        }
        bindCountryToggle('tugilgan_davlat', 'tugilgan_viloyat', 'tugulgan_tuman', 'tugilgan_viloyat_text', 'tugilgan_tuman_text');
        bindCountryToggle('talim_davlat',    'talim_viloyat',    'talim_tuman',     'talim_viloyat_text',    'talim_tuman_text');
        bindCountryToggle('yashash_davlat',  'yashash_viloyat',  'yashash_tuman',   'yashash_viloyat_text',  'yashash_tuman_text');

        // Sertifikat turi → ball
        $('#qw_sertifikat_turi').on('change', function(){
            const show = this.value && this.value !== 'mavjud_emas';
            $('#qw_sertifikat_ball_wrap').toggleClass('d-none', !show);
        });

        // Viloyat → Tuman chains
        $('.qw-viloyat').each(function(){
            const target = '#' + $(this).data('target');
            fillTumanSelect(this, target);
        });

        // Date pickers
        if (typeof flatpickr !== 'undefined') {
            flatpickr('.qw-date', { dateFormat:'d.m.Y', allowInput:true, altInput:false, locale:'uz', maxDate:'today', disableMobile:true });
            flatpickr('.qw-year', {
                dateFormat:'Y', altInput:true, altFormat:'Y', allowInput:true, clickOpens:true, disableMobile:true,
                onReady:(_,__,inst)=>{ inst.calendarContainer.classList.add('fp-year-only'); },
                onYearChange:(_,__,inst)=>{
                    const y = inst.currentYearElement && inst.currentYearElement.value;
                    if (y && /^\d{4}$/.test(y)) { inst.setDate(`${y}-01-01`, true); inst.close(); }
                }
            });
        }

        // Phone mask
        $('.qw-phone').each(function(){
            $(this).on('focus', function(){ if(!this.value) this.value = '+998 '; });
            $(this).on('input', function(){
                let v = this.value.replace(/[^\d]/g,'');
                if(!v.startsWith('998')) v='998'+v;
                v = v.slice(0,12);
                let out='+998';
                if(v.length>3) out+=' '+v.slice(3,5);
                if(v.length>5) out+=' '+v.slice(5,8);
                if(v.length>8) out+=' '+v.slice(8,10);
                if(v.length>10) out+=' '+v.slice(10,12);
                this.value = out;
            });
        });

        // Uppercase for parent fields
        $(document).on('input', '.qw-upper', function(){
            const pos = this.selectionStart;
            this.value = String(this.value || '').toLocaleUpperCase('uz-Latn');
            try { this.setSelectionRange(pos, pos); } catch(_) {}
        });

        // JSHSHIR digits only
        $('input[name="jshshir"]').on('input', function(){ this.value = this.value.replace(/\D+/g,'').slice(0,14); });
        $('input[name="passport_seriya"]').on('input', function(){ this.value = this.value.toUpperCase().replace(/[^A-Z]/g,'').slice(0,3); });
        $('input[name="passport_raqam"]').on('input', function(){ this.value = this.value.replace(/\D+/g,'').slice(0,10); });
        $('input[name="abituriyent_id"]').on('input', function(){ this.value = this.value.replace(/\D+/g,''); });

        // TomSelect for multi-selects
        if (typeof TomSelect !== 'undefined') {
            ['#qw_chet_tillari','#qw_iqtidori','#qw_sport_qobiliyat'].forEach(sel => {
                const $el = $(sel);
                if(!$el.length || $el[0].tomselect) return;
                if(!$el.parent().hasClass('ts-parent')) $el.wrap('<div class="ts-parent"></div>');
                new TomSelect($el[0], { plugins:['remove_button'], maxItems:null, create:false, persist:false, closeAfterSelect:false, placeholder:'Tanlang…', dropdownParent: $el.parent()[0] });
            });
        }

        // "Boshqa" toggle for chet_tillari / iqtidori / sport_qobiliyat
        function bindOtherToggle(selectId, wrapId){
            $(selectId).on('change', function(){
                const vals = $(this).val() || [];
                $(wrapId).toggleClass('d-none', !vals.includes('boshqa'));
            });
        }
        bindOtherToggle('#qw_chet_tillari', '#qw_chet_til_boshqa_wrap');
        bindOtherToggle('#qw_iqtidori', '#qw_iqtidori_boshqa_wrap');
        bindOtherToggle('#qw_sport_qobiliyat', '#qw_sport_boshqa_wrap');

        // Yandex Map
        const Y_KEY = "9745d495-d17b-411e-8e09-196da5cd23cc";
        let mapInited = false;
        function loadYMaps(cb){
            if (window.ymaps && ymaps.ready) { ymaps.ready(cb); return; }
            const s = document.createElement('script');
            s.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(Y_KEY) + '&lang=ru_RU';
            s.onload = () => ymaps.ready(cb);
            document.head.appendChild(s);
        }
        function initMap(){
            if (mapInited) return;
            if (!document.getElementById('qwMap')) return;
            mapInited = true;
            loadYMaps(function(){
                const $LAT = $('#qw_lat'), $LON = $('#qw_lon');
                const startLat = parseFloat($LAT.val()), startLon = parseFloat($LON.val());
                const hasStart = !isNaN(startLat) && !isNaN(startLon);
                const map = new ymaps.Map('qwMap', {
                    center: hasStart ? [startLat, startLon] : [41.3775, 64.5853],
                    zoom: hasStart ? 16 : 6,
                    controls: []
                });
                map.controls.add(new ymaps.control.SearchControl({ options:{ provider:'yandex#search', noPlacemark:true, useMapBounds:false, strictBounds:false, size:'large', placeholderContent:"Manzil yoki tashkilot nomi…" } }));
                map.controls.add('zoomControl');
                let pm = null;
                function place(coords){
                    if (!pm) {
                        pm = new ymaps.Placemark(coords, {}, { draggable:true, preset:'islands#redIcon' });
                        map.geoObjects.add(pm);
                        pm.events.add('dragend', () => {
                            const c = pm.geometry.getCoordinates();
                            $LAT.val(Number(c[0]).toFixed(6));
                            $LON.val(Number(c[1]).toFixed(6));
                        });
                    } else {
                        pm.geometry.setCoordinates(coords);
                    }
                    $LAT.val(Number(coords[0]).toFixed(6));
                    $LON.val(Number(coords[1]).toFixed(6));
                }
                if (hasStart) place([startLat, startLon]);
                map.events.add('click', e => place(e.get('coords')));
                $('#qwBtnLocate').on('click', function(){
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => { const c=[pos.coords.latitude,pos.coords.longitude]; place(c); map.setCenter(c,16); },
                            () => { ymaps.geolocation.get({provider:'yandex'}).then(r => { const c=r.geoObjects.position||[41.3775,64.5853]; place(c); map.setCenter(c,16); }); },
                            { enableHighAccuracy:true, timeout:8000, maximumAge:0 }
                        );
                    }
                });
            });
        }
        const card4 = document.getElementById('qwCard4');
        if (card4) {
            if (!card4.classList.contains('d-none')) initMap();
            new MutationObserver(() => { if (!card4.classList.contains('d-none')) initMap(); })
                .observe(card4, { attributes:true, attributeFilter:['class'] });
        }
    });
})();
</script>
