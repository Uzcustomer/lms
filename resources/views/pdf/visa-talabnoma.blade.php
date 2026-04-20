<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; margin: 0; padding: 0; }
        .page { padding: 30px 40px; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { text-align: right; font-size: 11px; margin-bottom: 15px; }
        .title { text-align: center; font-weight: bold; font-size: 16px; margin: 15px 0; text-decoration: underline; }
        .intro { text-align: justify; margin-bottom: 10px; font-size: 11.5px; }
        .field { margin-bottom: 3px; font-size: 11.5px; }
        .field b { font-weight: bold; }
        .underline { text-decoration: underline; }
        .footer { margin-top: 40px; }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .small { font-size: 10px; margin-top: 30px; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
@foreach($students as $student)
    @php $v = $student->visaInfo; @endphp
    <div class="page">
        <div class="header">
            Surxondaryo viloyati IIB Migratsiya va fuqarolikni<br>
            rasmiylashtirish boshqarmasi boshlig'iga
        </div>

        <div class="title">TALABNOMA</div>

        <div class="intro">
            Toshkent davlat tibbiyot universiteti Termiz filiali quyidagi xorijiy talaba vizasining amal qilish muddatini
            <b>3 oy muddatga (2 martalik)</b> uzaytirib berishda amaliy yordam berishingizni so'raydi
        </div>

        <div class="field">1. <b>F.I.SH:</b> <span class="underline">{{ $student->full_name }}</span> &nbsp;&nbsp; 2. <b>Fuqaroligi:</b> <span class="underline">{{ $student->country_name ?? '___' }}</span></div>
        <div class="field">3. <b>Jinsi:</b> <span class="underline">{{ $student->gender_name ?? '___' }}</span> &nbsp;&nbsp; 4. <b>Tug'ilgan sanasi:</b> <span class="underline">{{ $student->birth_date?->format('d.m.Y') ?? '___' }}</span></div>
        <div class="field">5. <b>Tug'ilgan joyi:</b> <span class="underline">{{ $v?->birth_city ?? $student->district_name ?? '___' }},{{ $v?->birth_region ?? $student->province_name ?? '' }}</span></div>
        <div class="field">6. <b>Ish joyi va lavozimi:</b> Toshkent davlat tibbiyot universiteti Termiz filiali {{ $student->department_name ?? '' }} "{{ $student->specialty_code ?? '' }}" {{ $student->level_code ?? '' }}-bosqich talabasi</div>
        <div class="field">7. <b>Milliy passport:</b> <span class="underline">{{ $v?->passport_number ?? '___' }}</span> &nbsp;&nbsp; 8. <b>Viza turi raqami hamda safarlar soni:</b> {{ $v?->visa_type ?? '___' }};№ {{ $v?->visa_number ?? '___' }}; {{ $v?->visa_entries_count ?? '___' }} MARTALIK</div>
        <div class="field">9. <b>Farzandlari:</b> yo'q</div>
        <div class="field">10. <b>Viza kim tomonidan rasmiyashtirilb berilgan (turi, raqam va amal qilish muddati):</b> <span class="underline">{{ $v?->visa_issued_place ?? '___' }} ({{ $v?->visa_type ?? '' }}, № {{ $v?->visa_number ?? '' }}; {{ $v?->visa_start_date?->format('d.m.Y') ?? '___' }} dan {{ $v?->visa_end_date?->format('d.m.Y') ?? '___' }} gacha)</span></div>
        <div class="field">11. <b>Viza uzaytirish so'ralayotgan muddat (kunlarda):</b> <span class="underline">{{ $v?->visa_stay_days ?? '___' }} kun</span></div>
        <div class="field">12. <b>Chegara nazorat maskanidan O'zbekiston Respublikasiga kirib kelgan sanasi:</b> <span class="underline">{{ $v?->entry_date?->format('d.m.Y') ?? '___' }}</span></div>
        <div class="field">13. <b>Vaqtincha yashash manzili (uy. telefon r.):</b> MA'RIFAT MFY, Islom Karimov ko'chasi, 64-uy</div>
        <div class="field">14. <b>Uy joy taqdim etayotgan shaxs yoki tashkilot nomi:</b> Toshkent davlat tibbiyot universiteti Termiz filiali</div>
        <div class="field">15. <b>TTV akkredatsiyadan o'tgan ro'yxat raqami:</b> <span class="underline">yo'q</span></div>
        <div class="field">16. <b>Adliya Vazirligi yoki Hokimiyatdan o'tgan ro'yxat raqami:</b> <span class="underline">yo'q</span></div>
        <div class="field">17. <b>B va MM vazirligidan o'tgan ro'yxat va muddati:</b> <span class="underline">yo'q</span></div>
        <div class="field">18. <b>Moliya vazirligidan o'tgan yat raqami va muddati:</b> <span class="underline">yo'q</span></div>
        <div class="field">19. <b>Hujjatlarni rasmiylashtirish va topshirishga mas'ul bo'lgan shaxsning F.I.SH, passport ma'lumotlari hamda telefon raqami:</b> Temirov Shukrullo Xonimqulovich AC 2275461 +998995721774</div>

        <div class="footer clearfix" style="margin-top:50px;">
            <div class="footer-left"><b>Direktor</b></div>
            <div class="footer-right"><b>F.A.Otamuradov</b></div>
        </div>

        <div class="small">
            <u>Ijrochi:Temirov.Sh</u><br>
            <u>Tel:+998995721774</u>
        </div>
    </div>
@endforeach
</body>
</html>
