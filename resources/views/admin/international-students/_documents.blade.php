{{--
    Talaba hujjatlari haqiqiy hujjatlar ko'rinishida: pasport ma'lumotlar sahifasi,
    viza stikeri, vaqtinchalik ro'yxatga qo'yish blankasi, yashash guvohnomasi (ID-karta).
    Bu faqat ma'lumotni ko'rsatish: gerb va rasmiy belgilar ataylab chizilmaydi.

    Kutiladi: $student, $visaInfo, $docState (fn(date, days, thresholds)), $regT, $visaT, $permitT
--}}
@php
    [$surname, $given, $patronymic] = \App\Support\Mrz::splitName($student->full_name);
    $givenFull = trim($given . ' ' . $patronymic);
    $nationality = \App\Support\CountryFlag::iso3($student->country_name, $student->country_code);
    $countryFlag = \App\Support\CountryFlag::url($student->country_name, $student->country_code);
    $pt = \App\Support\PassportTheme::for(\App\Support\CountryFlag::code($student->country_name, $student->country_code));
    $ptStyle = "--cover:{$pt['cover']};--ink-accent:{$pt['ink']};--g1:{$pt['g1']};--g2:{$pt['g2']};"
        . "--p1:{$pt['paper'][0]};--p2:{$pt['paper'][1]};--p3:{$pt['paper'][2]};";
    $uzFlag = asset('images/flags/uz.svg');
    $birth = $visaInfo->birth_date ?? $student->birth_date;
    $sex = $student->gender_name;
    $sexCode = match (true) {
        str_starts_with(mb_strtolower((string) $sex), 'erk') => 'M',
        str_starts_with(mb_strtolower((string) $sex), 'ayol') => 'F',
        default => '—',
    };
    $d = fn ($date) => $date?->format('d.m.Y') ?? '—';
    $v = fn ($value) => filled($value) ? $value : '—';
    $photo = filled($student->image) ? $student->image : null;
    $birthPlace = collect([$visaInfo->birth_city, $visaInfo->birth_region, $visaInfo->birth_country])->filter()->implode(', ');

    $passportDays = $visaInfo->passport_expiry_date
        ? (int) now()->startOfDay()->diffInDays($visaInfo->passport_expiry_date, false) : null;
    $entries = $visaInfo->visa_entries_count;
    $entriesText = $entries === null ? '—' : ((int) $entries === 99 ? "Ko'p martalik / Multiple" : $entries);
@endphp

{{-- Foto: HEMIS dagi rasm, bo'lmasa bosh harflar --}}
@php
    $photoHtml = function (string $class) use ($photo, $surname, $given) {
        $initials = mb_substr($surname, 0, 1) . mb_substr($given, 0, 1);
        if ($photo) {
            return '<div class="' . $class . '"><img src="' . e($photo) . '" alt="" referrerpolicy="no-referrer" loading="lazy" onerror="this.remove()"><span>' . e($initials) . '</span></div>';
        }
        return '<div class="' . $class . '"><span>' . e($initials) . '</span></div>';
    };
    $foot = function (string $label, array $state, ?string $file, ?string $process = null) use ($student) {
        $html = '<div class="doc-foot"><span class="doc-foot-label">' . e($label) . '</span>'
            . '<span class="sp-tone sp-tone-' . $state['tone'] . '">' . e($state['text']) . '</span>';
        if ($process) {
            $html .= '<span class="sp-pill sp-pill-info">' . e($process) . '</span>';
        }
        $html .= '<span class="doc-foot-spacer"></span>';
        $html .= $file
            ? '<a class="sp-link" target="_blank" rel="noopener" href="' . e(route('admin.international-students.file', [$student, $file])) . '">Skanerni ochish</a>'
            : '<span class="sp-faint">Skaner yuklanmagan</span>';
        return $html . '</div>';
    };
@endphp

<div class="docs">
    {{-- PASPORT --}}
    <figure class="doc-wrap">
        <div class="doc doc-passport" style="{{ $ptStyle }}">
            <span class="doc-spine" aria-hidden="true"></span>
            @if($countryFlag)<img src="{{ $countryFlag }}" alt="" class="doc-watermark" aria-hidden="true">@endif
            <div class="doc-band">
                <div>
                    <div class="doc-kicker">@if($pt['word'] !== 'PASSPORT')<span>{{ $pt['word'] }}</span> · @endif PASSPORT</div>
                    <div class="doc-band-sub">Tur / Type <b>P</b> &nbsp; Davlat kodi / Code <b>{{ $nationality ?? '—' }}</b></div>
                </div>
                <div class="doc-country">
                    @if($countryFlag)<img src="{{ $countryFlag }}" alt="" class="doc-flag">@endif
                    <span>
                        <span @if(!empty($pt['rtl'])) dir="rtl" lang="ps" @endif class="doc-native">{{ $pt['native'] ?? mb_strtoupper($student->country_name ?: '—') }}</span>
                        @if($pt['en'])<small>{{ $pt['en'] }}</small>@endif
                    </span>
                </div>
            </div>
            <div class="doc-passport-body">
                {!! $photoHtml('doc-photo') !!}
                <div class="doc-fields doc-fields-3">
                    <div class="doc-f doc-f-wide"><span class="doc-l">Familiyasi / Surname</span><span class="doc-v">{{ $v($surname) }}</span></div>
                    <div class="doc-f doc-f-num"><span class="doc-l">Pasport № / Passport No.</span><span class="doc-v doc-v-no">{{ $v($visaInfo->passport_number) }}</span></div>
                    <div class="doc-f doc-f-wide"><span class="doc-l">Ismi / Given names</span><span class="doc-v">{{ $v($givenFull) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Jinsi / Sex</span><span class="doc-v">{{ $sexCode }}</span></div>
                    <div class="doc-f"><span class="doc-l">Fuqaroligi / Nationality</span><span class="doc-v">{{ $v($student->country_name) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Tug'ilgan sanasi / Date of birth</span><span class="doc-v">{{ $d($birth) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Tug'ilgan joyi / Place of birth</span><span class="doc-v">{{ $v($birthPlace) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Berilgan sanasi / Date of issue</span><span class="doc-v">{{ $d($visaInfo->passport_issued_date) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Amal qilish muddati / Date of expiry</span><span class="doc-v">{{ $d($visaInfo->passport_expiry_date) }}</span></div>
                    <div class="doc-f"><span class="doc-l">Bergan organ / Authority</span><span class="doc-v">{{ $v($visaInfo->passport_issued_place) }}</span></div>
                </div>
            </div>
            <div class="doc-mrz" aria-label="Mashinada o'qiladigan zona">@foreach(\App\Support\Mrz::passport($surname, $givenFull, $nationality, $visaInfo->passport_number, $birth, $sex, $visaInfo->passport_expiry_date) as $line)<div>{{ $line }}</div>@endforeach</div>
        </div>
        {!! $foot('Pasport', $docState($visaInfo->passport_expiry_date, $passportDays, ['danger' => 0, 'warning' => 90, 'info' => 90]), $visaInfo->passport_scan_path ? 'passport_scan_path' : null) !!}
    </figure>

    @if($visaInfo->hasResidencePermit())
        {{-- YASHASH GUVOHNOMASI (ID-karta, old va orqa tomoni) --}}
        <figure class="doc-wrap">
            <div class="doc-idpair">
                <div class="doc doc-id">
                    <div class="doc-id-head">
                        <img src="{{ $uzFlag }}" alt="" class="doc-flag doc-flag-id">
                        <div>
                            <div class="doc-id-title">O'zbekiston Respublikasi</div>
                            <div class="doc-id-sub">Xorijiy fuqaro uchun yashash guvohnomasi</div>
                            <div class="doc-id-sub doc-id-en">Republic of Uzbekistan identity card for foreigners resident</div>
                        </div>
                    </div>
                    <div class="doc-id-body">
                        {!! $photoHtml('doc-photo doc-photo-id') !!}
                        <div class="doc-fields doc-fields-2 doc-fields-id">
                            <div class="doc-f doc-f-wide"><span class="doc-l">Familiyasi / Surname</span><span class="doc-v">{{ $v($surname) }}</span></div>
                            <div class="doc-f doc-f-wide"><span class="doc-l">Ismi / Given name(s)</span><span class="doc-v">{{ $v($given) }}</span></div>
                            <div class="doc-f doc-f-wide"><span class="doc-l">Otasining ismi / Patronymic</span><span class="doc-v">{{ $v($patronymic) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Tug'ilgan sanasi / Date of birth</span><span class="doc-v">{{ $d($birth) }}</span></div>
                            <div class="doc-f doc-f-right"><span class="doc-l">Jinsi / Sex</span><span class="doc-v">{{ $v($sex) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Berilgan sanasi / Date of issue</span><span class="doc-v">{{ $d($visaInfo->residence_permit_issued_date) }}</span></div>
                            <div class="doc-f doc-f-right"><span class="doc-l">Fuqaroligi / Citizenship</span><span class="doc-v">{{ $v($student->country_name) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Amal qilish muddati / Date of expiry</span><span class="doc-v">{{ $d($visaInfo->residence_permit_expiry_date) }}</span></div>
                            <div class="doc-f doc-f-right"><span class="doc-l">Karta raqami / Card number</span><span class="doc-v doc-v-no">{{ $v($visaInfo->residence_permit_number) }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="doc doc-id doc-id-back">
                    <div class="doc-id-back-top">
                        <span class="doc-chip" aria-hidden="true"></span>
                        <span class="doc-id-serial">{{ $v($visaInfo->residence_permit_number) }}</span>
                    </div>
                    <div class="doc-fields doc-fields-id doc-fields-back">
                        <div class="doc-f doc-f-wide"><span class="doc-l">Shaxsiy raqami / Personal number</span><span class="doc-v doc-v-no">{{ $v($visaInfo->residence_permit_pinfl) }}</span></div>
                        <div class="doc-f doc-f-wide"><span class="doc-l">Tug'ilgan joyi / Place of birth</span><span class="doc-v">{{ $v($birthPlace) }}</span></div>
                        <div class="doc-f doc-f-wide"><span class="doc-l">Berilgan joyi / Place of issue</span><span class="doc-v">{{ $v($visaInfo->residence_permit_issued_place) }}</span></div>
                    </div>
                    <div class="doc-mrz doc-mrz-id" aria-label="Mashinada o'qiladigan zona">@foreach(\App\Support\Mrz::idCard($surname, $given, $nationality, $visaInfo->residence_permit_number, $visaInfo->residence_permit_pinfl, $birth, $sex, $visaInfo->residence_permit_expiry_date) as $line)<div>{{ $line }}</div>@endforeach</div>
                </div>
            </div>
            {!! $foot('Yashash guvohnomasi', $docState($visaInfo->residence_permit_expiry_date, $visaInfo->residencePermitDaysLeft(), $permitT), $visaInfo->residence_permit_scan_path ? 'residence_permit_scan_path' : null) !!}
        </figure>
    @else
        @if($visaInfo->needsVisa())
            {{-- VIZA STIKERI --}}
            <figure class="doc-wrap">
                <div class="doc doc-visa">
                    <div class="doc-band doc-band-visa">
                        <div class="doc-visa-word">Viza <span>Visa</span></div>
                        <div class="doc-country">
                            <img src="{{ $uzFlag }}" alt="" class="doc-flag">
                            <span>O'ZBEKISTON RESPUBLIKASI<small>Republic of Uzbekistan</small></span>
                        </div>
                        <div class="doc-visa-no">№ {{ $v($visaInfo->visa_number) }}</div>
                    </div>
                    <div class="doc-visa-body">
                        {!! $photoHtml('doc-photo doc-photo-sm') !!}
                        <div class="doc-fields doc-fields-4">
                            <div class="doc-f"><span class="doc-l">Turi / Type</span><span class="doc-v doc-v-type">{{ $v($visaInfo->visa_type) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Kirishlar soni / Entries</span><span class="doc-v">{{ $entriesText }}</span></div>
                            <div class="doc-f"><span class="doc-l">Istiqomat / Duration of stay</span><span class="doc-v">{{ $visaInfo->visa_stay_days ? $visaInfo->visa_stay_days . ' kun' : '—' }}</span></div>
                            <div class="doc-f"><span class="doc-l">Pasport № / Passport No.</span><span class="doc-v">{{ $v($visaInfo->passport_number) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Amal qiladi / Valid from</span><span class="doc-v">{{ $d($visaInfo->visa_start_date) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Gacha / Until</span><span class="doc-v">{{ $d($visaInfo->visa_end_date) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Berilgan joyi / Issued at</span><span class="doc-v">{{ $v($visaInfo->visa_issued_place) }}</span></div>
                            <div class="doc-f"><span class="doc-l">Berilgan sanasi / Date of issue</span><span class="doc-v">{{ $d($visaInfo->visa_issued_date) }}</span></div>
                            <div class="doc-f doc-f-span4"><span class="doc-l">Familiyasi, ismi / Surname, given names</span><span class="doc-v">{{ $v($student->full_name) }}</span></div>
                        </div>
                    </div>
                    <div class="doc-mrz" aria-label="Mashinada o'qiladigan zona">@foreach(\App\Support\Mrz::visa($surname, $givenFull, $nationality, $visaInfo->passport_number, $birth, $sex, $visaInfo->visa_end_date) as $line)<div>{{ $line }}</div>@endforeach</div>
                </div>
                {!! $foot('Viza', $docState($visaInfo->visa_end_date, $visaInfo->visaDaysLeft(), $visaT), $visaInfo->visa_scan_path ? 'visa_scan_path' : null, $visaInfo->isVisaProcessActive() ? 'Yangilanmoqda' : null) !!}
            </figure>
        @else
            <div class="doc-none doc-span">
                <img src="{{ $uzFlag }}" alt="" class="doc-flag">
                <span><b>Vizasiz</b> — {{ $student->country_name ?: 'bu davlat' }} fuqarolari uchun viza talab qilinmaydi. Faqat vaqtinchalik ro'yxatga qo'yish kerak.</span>
            </div>
        @endif

        {{-- VAQTINCHALIK RO'YXATGA QO'YISH --}}
        <figure class="doc-wrap doc-span">
            <div class="doc doc-reg">
                <div class="doc-reg-head">
                    <div>
                        <div class="doc-reg-title">Vaqtinchalik ro'yxatga qo'yish</div>
                        <div class="doc-reg-sub">Temporary registration of a foreign citizen</div>
                    </div>
                    <img src="{{ $uzFlag }}" alt="" class="doc-flag">
                </div>
                <div class="doc-reg-lines">
                    <div class="doc-reg-row"><span class="doc-l">Familiyasi, ismi / Full name</span><span class="doc-reg-v">{{ $v($student->full_name) }}</span></div>
                    <div class="doc-reg-row"><span class="doc-l">Fuqaroligi / Nationality</span><span class="doc-reg-v">{{ $v($student->country_name) }}</span><span class="doc-l">Pasport / Passport</span><span class="doc-reg-v">{{ $v($visaInfo->passport_number) }}</span></div>
                    <div class="doc-reg-row"><span class="doc-l">Yashash manzili / Address</span><span class="doc-reg-v">{{ $visaInfo->residenceAddress() }}</span></div>
                    <div class="doc-reg-row"><span class="doc-l">Muddati / Period</span><span class="doc-reg-v">{{ $d($visaInfo->registration_start_date) }} — {{ $d($visaInfo->registration_end_date) }}</span><span class="doc-l">Kirgan sana / Entry</span><span class="doc-reg-v">{{ $d($visaInfo->entry_date) }}</span></div>
                </div>
                @if($visaInfo->registration_end_date)
                    <svg class="doc-stamp" viewBox="0 0 120 120" aria-hidden="true">
                        <defs><path id="stamp-ring-{{ $visaInfo->id }}" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"/></defs>
                        <circle cx="60" cy="60" r="56" fill="none" stroke="currentColor" stroke-width="2.5"/>
                        <circle cx="60" cy="60" r="34" fill="none" stroke="currentColor" stroke-width="1.2"/>
                        <text font-size="10" font-weight="700" letter-spacing="1.2" fill="currentColor"><textPath href="#stamp-ring-{{ $visaInfo->id }}" startOffset="0">RO'YXATGA OLINDI • REGISTERED •</textPath></text>
                        <text x="60" y="57" text-anchor="middle" font-size="9" font-weight="700" fill="currentColor">GACHA</text>
                        <text x="60" y="70" text-anchor="middle" font-size="10.5" font-weight="700" fill="currentColor">{{ $visaInfo->registration_end_date->format('d.m.y') }}</text>
                    </svg>
                @endif
            </div>
            {!! $foot("Ro'yxatga qo'yish", $docState($visaInfo->registration_end_date, $visaInfo->registrationDaysLeft(), $regT), $visaInfo->registration_doc_path ? 'registration_doc_path' : null, $visaInfo->isRegistrationProcessActive() ? 'Yangilanmoqda' : null) !!}
        </figure>
    @endif
</div>

<style>
    /* Ikki ustun: pasport | viza (yoki guvohnoma); blanka va izoh to'liq qatorda */
    .docs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 24px; align-items: stretch; }
    .doc-span { grid-column: 1 / -1; }
    /* Bir qatordagi kartochkalar teng balandlikda: MRZ va pastki qator bir chiziqda */
    .doc-wrap { margin: 0; min-width: 0; container-type: inline-size; display: flex; flex-direction: column; }
    .doc-wrap > .doc, .doc-wrap > .doc-idpair { flex: 1; }
    .doc-passport, .doc-visa { display: flex; flex-direction: column; }
    .doc-passport > .doc-mrz, .doc-visa > .doc-mrz { margin-top: auto; }
    .doc { position: relative; overflow: hidden; border-radius: 14px; color: #1f2937;
           border: 1px solid rgba(15, 23, 42, .10);
           box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 10px 28px -16px rgba(15, 23, 42, .35); }
    /* Himoya to'ri (guilloche) — ikki qatlam nozik chiziqlar */
    .doc::before { content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none; opacity: .55;
        background:
            repeating-radial-gradient(circle at 18% 120%, transparent 0 9px, var(--g1) 9px 10px),
            repeating-radial-gradient(circle at 92% -20%, transparent 0 11px, var(--g2) 11px 12px); }
    .doc > * { position: relative; }

    .doc-flag { width: 24px; height: 18px; border-radius: 2px; box-shadow: 0 0 0 1px rgba(15,23,42,.15); object-fit: cover; flex: none; }
    .doc-l { display: block; font-size: 9.5px; line-height: 1.2; letter-spacing: .03em; color: #64748b; }
    .doc-v { display: block; margin-top: 2px; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; color: #0f172a; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .doc-v-no { color: #9f1239; letter-spacing: .08em; }
    .doc-fields { display: grid; gap: 10px 18px; align-content: start; min-width: 0; }
    .doc-fields-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .doc-fields-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .doc-fields-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .doc-f-wide { grid-column: span 2; }
    .doc-f-span4 { grid-column: 1 / -1; }
    .doc-fields-3 .doc-f-num { grid-column: 3; grid-row: 1; text-align: right; }
    .doc-fields-3 .doc-f-num .doc-v { font-size: 17px; }

    .doc-photo { position: relative; flex: none; width: 112px; height: 144px; border-radius: 6px; overflow: hidden;
                 background: linear-gradient(160deg, #e2e8f0, #cbd5e1); display: flex; align-items: center; justify-content: center; box-shadow: inset 0 0 0 1px rgba(15,23,42,.12); }
    .doc-photo span { font-size: 30px; font-weight: 600; color: #64748b; letter-spacing: .04em; }
    .doc-photo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; filter: grayscale(.15) contrast(1.05); z-index: 1; }
    .doc-photo-sm { width: 88px; height: 112px; }
    .doc-photo-sm span { font-size: 24px; }

    .doc-band { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px 10px; }
    .doc-kicker { font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-accent); }
    .doc-band-sub { margin-top: 2px; font-size: 10.5px; color: #64748b; letter-spacing: .03em; }
    .doc-band-sub b { color: #0f172a; font-weight: 600; }
    .doc-country { display: inline-flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; letter-spacing: .1em; color: var(--ink-accent); text-align: right; }
    .doc-country small { display: block; font-size: 9.5px; font-weight: 500; letter-spacing: .06em; color: #64748b; }

    /* Pasport */
    /* Pasport — ranglar davlat mavzusidan (PassportTheme) */
    .doc-passport { background: linear-gradient(135deg, var(--p1) 0%, var(--p2) 55%, var(--p3) 100%); padding-left: 10px; }
    .doc-passport .doc-band { border-bottom: 1px solid color-mix(in srgb, var(--ink-accent) 16%, transparent); }
    .doc-passport-body { display: flex; gap: 18px; padding: 14px 20px 16px; }
    .doc > .doc-spine { position: absolute; left: 0; top: 0; bottom: 0; width: 10px; background: var(--cover);
                        box-shadow: inset -2px 0 0 rgba(0, 0, 0, .18); }
    /* Bayroq suv belgisi: chetlari so'nadi, matn ostida xira dog' bo'lib qoladi */
    .doc > .doc-watermark { position: absolute; right: 4%; top: 26%; width: 46%; aspect-ratio: 4 / 3; object-fit: cover; opacity: .11;
                            filter: saturate(.55) blur(.3px); pointer-events: none;
                            -webkit-mask-image: radial-gradient(ellipse at center, #000 18%, transparent 68%);
                            mask-image: radial-gradient(ellipse at center, #000 18%, transparent 68%); }
    .doc-native { display: block; font-size: 12.5px; }
    .doc-native[dir="rtl"] { font-size: 16px; letter-spacing: 0; }

    /* MRZ */
    .doc-mrz { background: rgba(255, 255, 255, .78); border-top: 1px solid rgba(15, 23, 42, .08); padding: 10px 20px 12px;
               font-family: "OCR B", "OCR-B", "OCRB", "Consolas", "Courier New", monospace; font-size: clamp(8px, calc((100cqi - 44px) / 35.5), 17px);
               letter-spacing: .2em; line-height: 1.55; color: #1f2937; white-space: pre; overflow-x: auto; }

    /* Viza */
    .doc-visa { --g1: rgba(21, 128, 61, .08); --g2: rgba(37, 99, 235, .07); --ink-accent: #14532d;
                background: linear-gradient(135deg, #f1f8f2 0%, #eaf4f1 50%, #eef3fa 100%); }
    .doc-band-visa { border-bottom: 1px solid rgba(20, 83, 45, .12); flex-wrap: wrap; }
    .doc-band-visa .doc-country span { white-space: nowrap; }
    .doc-visa-word { font-size: 26px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #14532d; line-height: 1; }
    .doc-visa-word span { font-size: 12px; font-weight: 600; letter-spacing: .2em; color: #4d7c0f; margin-left: 6px; }
    .doc-visa-no { font-size: 18px; font-weight: 700; letter-spacing: .1em; color: #b91c1c; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .doc-visa-body { display: flex; gap: 18px; padding: 14px 20px 16px; }
    .doc-v-type { font-size: 22px; color: #14532d; letter-spacing: .04em; }

    /* Ro'yxatga qo'yish blankasi */
    .doc-reg { --g1: rgba(30, 64, 175, .035); --g2: rgba(30, 64, 175, .03);
               background: #fffdf8; padding: 16px 20px 18px; min-height: 196px; }
    .doc-reg-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 2px solid #1e3a8a; }
    .doc-reg-title { font-size: 14px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #1e3a8a; }
    .doc-reg-sub { font-size: 10.5px; color: #64748b; letter-spacing: .04em; margin-top: 2px; }
    .doc-reg-lines { padding-right: 132px; }
    .doc-reg-row { display: grid; grid-template-columns: 170px minmax(0, 1fr) auto auto; align-items: baseline; gap: 6px 12px; padding: 9px 0 6px; border-bottom: 1px dotted #94a3b8; }
    .doc-reg-row > :nth-child(3) { padding-left: 12px; }
    .doc-reg-v { font-size: 14px; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; }
    .doc-reg-row > .doc-reg-v:only-of-type, .doc-reg-row:not(:has(> :nth-child(3))) .doc-reg-v { grid-column: 2 / -1; }
    .doc-stamp { position: absolute; right: 22px; bottom: 18px; width: 116px; height: 116px; color: #2643a3; opacity: .72; transform: rotate(-14deg); mix-blend-mode: multiply; }

    /* Yashash guvohnomasi (ID-karta, 85.6 × 54 mm) */
    .doc-idpair { display: grid; grid-template-columns: 1fr; gap: 14px; }
    .doc-id { --g1: rgba(14, 116, 144, .08); --g2: rgba(22, 163, 74, .07); --ink-accent: #0e4f6b;
              /* ID-karta ko'rinishi; balandlikni mazmun belgilaydi (tor ustunda nisbat maydonlarni kesardi) */
              min-height: 250px; border-radius: 16px; padding: 12px 14px; display: flex; flex-direction: column;
              background: linear-gradient(120deg, #e4f1f4 0%, #e6f2ee 45%, #e3eef8 100%); }
    .doc-id-head { display: flex; gap: 10px; align-items: flex-start; padding-bottom: 6px; border-bottom: 1px dashed rgba(14, 79, 107, .25); }
    .doc-flag-id { width: 34px; height: 24px; margin-top: 2px; }
    .doc-id-title { font-size: 11.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #334155; }
    .doc-id-sub { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0e4f6b; }
    .doc-id-en { font-weight: 500; color: #64748b; font-size: 8.5px; }
    .doc-id-body { display: flex; gap: 12px; padding-top: 8px; flex: 1; }
    .doc-photo-id { width: 27%; height: auto; aspect-ratio: 3 / 4; align-self: flex-start; }
    .doc-fields-id { gap: 5px 10px; }
    .doc-fields-id .doc-l { font-size: 8.5px; }
    .doc-fields-id .doc-v { font-size: 12px; margin-top: 1px; }
    .doc-fields-id .doc-f-right { text-align: right; }
    .doc-id-back { --g1: rgba(14, 116, 144, .07); --g2: rgba(14, 116, 144, .05); }
    .doc-id-back-top { display: flex; justify-content: space-between; align-items: center; }
    .doc-chip { width: 42px; height: 32px; border-radius: 6px; background: linear-gradient(135deg, #d4b26a, #f0dca0 45%, #c79d4f);
                box-shadow: inset 0 0 0 1px rgba(120, 85, 20, .35); position: relative; }
    .doc-chip::after { content: ""; position: absolute; inset: 9px 12px; border: 1px solid rgba(120, 85, 20, .45); border-radius: 3px; }
    .doc-id-serial { font-size: 13px; font-weight: 700; letter-spacing: .1em; color: #475569; font-variant-numeric: tabular-nums; }
    .doc-fields-back { margin-top: 8px; grid-template-columns: 1fr; }
    .doc-mrz-id { margin: auto -14px -12px; font-size: clamp(8px, calc((100cqi - 32px) / 24.5), 14px); padding: 8px 14px 10px; letter-spacing: .16em; border-radius: 0 0 16px 16px; }

    .doc-foot { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 10px; padding: 10px 4px 0; font-size: 13px; }
    .doc-foot-label { font-weight: 600; color: #0f172a; }
    .doc-foot-spacer { flex: 1; }
    .doc-none { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px dashed #cbd5e1; border-radius: 12px; font-size: 14px; color: #334155; background: #fff; }

    /* Kartochka tor bo'lsa — maydonlar 2 ustunga */
    @container (max-width: 620px) {
        .doc-band-visa .doc-country { order: 3; width: 100%; justify-content: flex-start; text-align: left; }
        .doc-fields-3, .doc-fields-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .doc-fields-3 .doc-f-num { grid-column: 1 / -1; grid-row: auto; text-align: left; }
        .doc-f-wide { grid-column: 1 / -1; }
        .doc-fields-id .doc-f-wide { grid-column: 1 / -1; }
        .doc-photo { width: 92px; height: 118px; }
    }
    @media (max-width: 1023px) {
        .docs { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 720px) {
        .doc-passport-body, .doc-visa-body { flex-direction: column; }
        .doc-passport-body .doc-photo, .doc-visa-body .doc-photo { width: 84px; height: 108px; }
        .doc-fields-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .doc-fields-3 .doc-f-num { grid-column: 1 / -1; grid-row: auto; text-align: left; }
        .doc-band { flex-wrap: wrap; }
        .doc-reg-lines { padding-right: 0; padding-bottom: 96px; }
        .doc-reg-row { grid-template-columns: 1fr; }
        .doc-reg-row > :nth-child(3) { padding-left: 0; }
        .doc-stamp { width: 92px; height: 92px; }
    }
</style>
