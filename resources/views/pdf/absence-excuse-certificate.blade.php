<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    @php
        $months = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
            5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
            9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr',
        ];
        $reviewDate = $excuse->reviewed_at ?? now();
        $year = now()->year;
        $month = now()->month;
        $academicYear = $month >= 9 ? $year . '.' . ($year + 1) : ($year - 1) . '.' . $year;
        $daysCount = $excuse->start_date->diffInDays($excuse->end_date) + 1;
    @endphp
    <style>
        @page {
            margin: 20mm 20mm 25mm 25mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }

        /* ===== HEADER: Gerb + Universitet nomi ===== */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-logo {
            width: 70px;
            text-align: center;
        }
        .header-logo img {
            width: 60px;
            height: auto;
        }
        .header-text {
            text-align: center;
            padding-left: 5px;
        }
        .header-ministry {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #003366;
            line-height: 1.3;
        }
        .header-university {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #003366;
            line-height: 1.3;
            margin-top: 2px;
        }
        .header-en {
            font-size: 7.5pt;
            font-style: italic;
            color: #555;
            margin-top: 2px;
            line-height: 1.2;
        }
        .header-address {
            text-align: center;
            font-size: 7pt;
            color: #666;
            margin-top: 4px;
            padding-bottom: 6px;
            border-bottom: 2.5px solid #003366;
        }

        /* ===== FARMOYISH sarlavha ===== */
        .doc-meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .doc-meta td {
            vertical-align: top;
        }
        .meta-left {
            width: 35%;
            font-size: 10pt;
            line-height: 1.6;
        }
        .meta-right {
            width: 65%;
            text-align: center;
        }
        .farmoyish-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #003366;
            line-height: 1.3;
        }
        .academic-year {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 3px;
        }

        /* ===== Asosiy matn ===== */
        .body-text {
            margin-top: 16px;
            text-align: justify;
            font-size: 11pt;
            line-height: 1.6;
        }
        .body-text p {
            text-indent: 20px;
            margin-bottom: 6px;
        }
        .student-name {
            font-weight: bold;
            text-transform: uppercase;
        }

        /* ===== Fanlar jadvali ===== */
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 12px;
            font-size: 10pt;
        }
        .subjects-table th,
        .subjects-table td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: center;
            vertical-align: middle;
        }
        .subjects-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9pt;
        }
        .subjects-table td.left-align {
            text-align: left;
        }

        /* ===== Imzolar ===== */
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 35px;
        }
        .signatures td {
            vertical-align: bottom;
            font-size: 11pt;
        }
        .sig-left {
            width: 50%;
        }
        .sig-right {
            width: 50%;
            text-align: right;
        }

        /* ===== Ijrochi ===== */
        .executor {
            margin-top: 40px;
            font-size: 7.5pt;
            color: #666;
            line-height: 1.5;
        }

        /* ===== QR kod ===== */
        .qr-container {
            position: fixed;
            bottom: 10px;
            right: 20px;
            text-align: center;
        }
        .qr-container img {
            width: 80px;
            height: 80px;
        }
        .qr-label {
            font-size: 6pt;
            color: #999;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    {{-- ===== HEADER ===== --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-logo">
                @if(file_exists(public_path('gerb.png')))
                    <img src="{{ public_path('gerb.png') }}" alt="Gerb">
                @elseif(file_exists(public_path('logo.png')))
                    <img src="{{ public_path('logo.png') }}" alt="Logo">
                @endif
            </td>
            <td class="header-text">
                <div class="header-ministry">
                    O'zbekiston Respublikasi Sog'liqni Saqlash Vazirligi
                </div>
                <div class="header-university">
                    Toshkent Davlat Tibbiyot Universiteti Termiz Filiali
                </div>
                <div class="header-en">
                    Ministry of the Health of the Republic of Uzbekistan<br>
                    Termez Branch of Tashkent State Medical University
                </div>
            </td>
        </tr>
    </table>

    {{-- Manzil + chiziq --}}
    <div class="header-address">
        190100, Surxondaryo viloyati, Termiz shahri | Tel/Fax: (0376) 000-00-00 | web: www.tdtutf.uz
    </div>

    {{-- ===== FARMOYISH META ===== --}}
    <table class="doc-meta" cellpadding="0" cellspacing="0">
        <tr>
            <td class="meta-left">
                {{ $reviewDate->format('Y') }} yil
                &laquo;{{ $reviewDate->format('j') }}&raquo;
                {{ $months[$reviewDate->month] ?? $reviewDate->format('F') }}<br>
                08-{{ str_pad($excuse->id, 5, '0', STR_PAD_LEFT) }} - son
            </td>
            <td class="meta-right">
                <div class="farmoyish-title">
                    Registrator ofisi<br>Farmoyishi
                </div>
                <div class="academic-year">
                    {{ $academicYear }} o'quv yili
                </div>
            </td>
        </tr>
    </table>

    {{-- ===== ASOSIY MATN ===== --}}
    <div class="body-text">
        <p>
            Toshkent davlat tibbiyot universiteti Termiz filiali
            @if($excuse->department_name){{ $excuse->department_name }}@endif
            @if($excuse->group_name){{ $excuse->group_name }} guruh@endif
            talabasi <span class="student-name">{{ $excuse->student_full_name }}</span>
            (HEMIS ID: {{ $excuse->student_hemis_id }})
            {{ mb_strtolower($excuse->reason_label) }} sababli
            {{ $excuse->start_date->format('d.m.Y') }} dan
            {{ $excuse->end_date->format('d.m.Y') }} gacha
            ({{ $daysCount }} kun)
            darslardan qayta topshirish sharti bilan ozod etilsin.
        </p>

        <p>
            Qayta topshirishga ruxsat berilsin va qo'shimcha qaydnoma asosida
            yuqorida ko'rsatilgan muddatda topshirishga ruxsat berilsin hamda
            Hemis platformasida shaxsiy grafik orqali baholari qayd etilsin.
        </p>

        <p>
            <strong>Asos:</strong> Talaba tomonidan taqdim etilgan
            {{ mb_strtolower($excuse->reason_document) }}.
        </p>
    </div>

    {{-- ===== FANLAR JADVALI ===== --}}
    <table class="subjects-table">
        <thead>
            <tr>
                <th style="width: 30px;">T/r</th>
                <th>Fan</th>
                <th style="width: 120px;">Nazorat turlari</th>
                <th style="width: 130px;">Qayta topshirish muddati</th>
            </tr>
        </thead>
        <tbody>
            {{-- Bo'sh qatorlar (to'ldiriladi) --}}
            @for ($i = 1; $i <= 5; $i++)
                <tr>
                    <td>{{ $i }}</td>
                    <td class="left-align">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- ===== IMZOLAR ===== --}}
    <table class="signatures" cellpadding="0" cellspacing="0">
        <tr>
            <td class="sig-left">
                <strong>Bo'lim boshlig'i</strong>
            </td>
            <td class="sig-right">
                <strong>{{ $excuse->reviewed_by_name ?? '_______________' }}</strong>
            </td>
        </tr>
    </table>

    {{-- ===== IJROCHI ===== --}}
    <div class="executor">
        Ijrochi: {{ $excuse->reviewed_by_name ?? '-' }}<br>
        ID {{ str_pad($excuse->id, 6, '0', STR_PAD_LEFT) }}<br>
        Sana: {{ $reviewDate->format('d.m.Y') }}
    </div>

    {{-- ===== QR KOD ===== --}}
    @if(!empty($qrCodeSvg))
        <div class="qr-container">
            {!! $qrCodeSvg !!}
            <div class="qr-label">Tekshirish</div>
        </div>
    @elseif(!empty($qrCodeBase64))
        <div class="qr-container">
            <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="QR Code">
            <div class="qr-label">Tekshirish</div>
        </div>
    @endif

</body>
</html>
