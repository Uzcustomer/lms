<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; margin: 0; padding: 0; }
        .page { padding: 30px 40px; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { text-align: right; font-size: 11px; margin-bottom: 10px; }
        .header-left { float: left; text-align: left; font-size: 11px; font-style: italic; }
        .title { text-align: center; font-weight: bold; font-size: 16px; margin: 15px 0; text-decoration: underline; }
        .intro { text-align: justify; margin-bottom: 10px; font-size: 11.5px; }
        .field { margin-bottom: 3px; font-size: 11.5px; }
        .field b { font-weight: bold; }
        .underline { text-decoration: underline; }
        .footer { margin-top: 40px; }
        .footer-sign { display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; }
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
        <div class="clearfix">
            <div class="header-left">
                <i>Hurmatli S.Eshqobilov</i><br>
                <i>qonuniy xal qiling</i>
            </div>
            <div class="header">
                <u>Termiz Shahar IIB M va FRB</u><br>
                <u>boshlig'i podpolkovnik</u><br>
                <u>S. S. Kabilovga</u>
            </div>
        </div>

        <div class="title">TALABNOMA</div>

        <div class="intro">
            Toshkent davlat tibbiyot universiteti Termiz filiali Sizdan quyidagi chet el fuqarosi yoki fuqaroligi
            bo'lmagan shaxsni vaqtincha ro'yxatga olishni (vaqtincha ro'yxat muddatini uzaytirshni) so'raydi:
        </div>

        <div class="field">1. <b>F.I.O:</b> <span class="underline">{{ $student->full_name }}</span></div>
        <div class="field" style="font-size:10px;color:#666;">(chet el fuqarosi yoki fuqaroligi bo'lmagan shaxsning familiyasi, ismi, otasining ismi hujjat bo'yicha lotinchada yoziladi)</div>
        <div class="field">2. <b>Farzandlari:</b> _______________</div>
        <div class="field" style="font-size:10px;color:#666;">(16 yoshgacha bo'lgan farzandlarning familiyasi, ismi, otasining ismi tug'ilgan yili)</div>
        <div class="field">3. <b>Fuqaroligi:</b> <span class="underline">{{ $student->country_name ?? '___' }}</span> &nbsp;&nbsp; 4. <b>Jinsi:</b> <span class="underline">{{ $student->gender_name ?? '___' }}</span></div>
        <div class="field">5. <b>Tug'ilgan joyi va sanasi:</b> <span class="underline">{{ $v?->birth_city ?? $student->district_name ?? '___' }},{{ $v?->birth_region ?? $student->province_name ?? '' }}</span>, &nbsp; <span class="underline">{{ $student->birth_date?->format('d.m.Y') ?? '___' }}</span></div>
        <div class="field">6. <b>Ish joyi va lavozimi:</b> Toshkent davlat tibbiyot universiteti Termiz filiali talaba</div>
        <div class="field">7. <b>Passport/harakatlanish hujjati:</b> <span class="underline">{{ $v?->passport_number ?? '___' }}</span></div>
        <div class="field">8. <b>Viza turi:</b> {{ $v?->visa_type ?? '___' }};  № {{ $v?->visa_number ?? '___' }};  {{ $v?->visa_entries_count ?? '___' }} MARTALIK</div>
        <div class="field">9. <b>Viza kim tomonidan rasmiylashtirib berilgan va uning muddati:</b> <span class="underline">{{ $v?->visa_issued_place ?? '___' }} ({{ $v?->visa_type ?? '' }}, № {{ $v?->visa_number ?? '' }}; {{ $v?->visa_start_date?->format('d.m.Y') ?? '___' }} dan {{ $v?->visa_end_date?->format('d.m.Y') ?? '___' }} gacha)</span></div>
        <div class="field">10. <b>So'ralayotgan vaqtincha ro'yxat muddati (kunlarda):</b> ___ joy</div>
        <div class="field">11. <b>O'zbekistonga kirib kelgan sanasi (nazorat o'tish punkti):</b> <span class="underline">{{ $v?->entry_date?->format('d.m.Y') ?? '___' }}</span></div>
        <div class="field">12. <b>Vaqtincha yashash manzili:</b> Termiz shahar I.Karimov ko'chasi 64-uy</div>
        <div class="field">13. <b>Uy joy maydon bergan shaxsning F.I.O:</b> Toshkent davlat tibbiyot universiteti Termiz filiali yotoqxona</div>
        <div class="field">&nbsp;&nbsp;&nbsp;&nbsp; <b>Kadastr raqami:</b> 19:15:01:03:01:0704</div>
        <div class="field">14. <b>Hujjatlarni rasmiylashtirish va taqdim etishga mas'ul bo'lgan shaxsning F.I.O:</b></div>
        <div class="field">&nbsp;&nbsp;&nbsp;&nbsp; <span class="underline">Temirov Shukrullo Xonimqulovich</span></div>
        <div class="field">&nbsp;&nbsp;&nbsp;&nbsp; Passport harakatlanish hujjat seriyasi va raqami: <span class="underline">AC 2275461</span></div>
        <div class="field">&nbsp;&nbsp;&nbsp;&nbsp; Xizmat tel raqami_______________ uvali tel. raqami <span class="underline">+998995721774</span></div>

        <div class="footer clearfix" style="margin-top:50px;">
            <div class="footer-left"><b>Direktor</b></div>
            <div class="footer-right"><b>F.A.Otamuradov</b></div>
        </div>

        <div class="small">
            <u>Ijrochi:Sh.Temirov</u><br>
            <u>Tel:+998995721774</u>
        </div>
    </div>
@endforeach
</body>
</html>
