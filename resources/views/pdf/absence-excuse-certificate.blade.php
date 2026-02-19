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
    @endphp
    <style>
        @page {
            margin: 25px 35px 55px 35px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #000;
        }

        /* Gerb / Logo */
        .gerb {
            text-align: center;
            margin-bottom: 6px;
        }
        .gerb img {
            width: 70px;
            height: auto;
        }

        /* Universitet nomi */
        .header-uz {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.4;
            color: #003366;
        }
        .header-en {
            text-align: center;
            font-size: 8px;
            font-style: italic;
            color: #444;
            margin-top: 3px;
            line-height: 1.3;
        }
        .address {
            text-align: center;
            font-size: 7.5px;
            color: #666;
            margin-top: 4px;
            padding-bottom: 8px;
            border-bottom: 2.5px solid #003366;
        }

        /* Hujjat meta */
        .doc-meta {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
        }
        .doc-meta td {
            vertical-align: top;
        }
        .doc-meta .left-col {
            width: 35%;
            font-size: 11px;
            line-height: 1.6;
        }
        .doc-meta .right-col {
            width: 65%;
            text-align: center;
        }

        /* Farmoyish sarlavha */
        .farmoyish-title {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            color: #003366;
            line-height: 1.3;
        }
        .academic-year {
            font-size: 11px;
            font-weight: bold;
            margin-top: 3px;
        }

        /* Asosiy matn */
        .body-text {
            margin-top: 18px;
            text-align: justify;
            font-size: 12px;
            line-height: 1.7;
        }
        .body-text p {
            text-indent: 25px;
            margin-bottom: 8px;
        }
        .student-name {
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Imzolar */
        .signatures {
            width: 100%;
            margin-top: 45px;
            border-collapse: collapse;
        }
        .signatures td {
            vertical-align: bottom;
            font-size: 12px;
        }
        .signatures .sig-left {
            width: 50%;
        }
        .signatures .sig-right {
            width: 50%;
            text-align: right;
        }

        /* Ijrochi */
        .executor {
            margin-top: 50px;
            font-size: 8px;
            color: #666;
            line-height: 1.5;
        }

        /* QR kod */
        .qr-container {
            position: fixed;
            bottom: 10px;
            right: 35px;
            text-align: center;
        }
        .qr-container img {
            width: 90px;
            height: 90px;
        }
    </style>
</head>
<body>

    {{-- Gerb / Logo --}}
    <div class="gerb">
        @if(file_exists(public_path('gerb.png')))
            <img src="{{ public_path('gerb.png') }}" alt="Gerb">
        @elseif(file_exists(public_path('logo.png')))
            <img src="{{ public_path('logo.png') }}" alt="Logo">
        @endif
    </div>

    {{-- Universitet nomi (o'zbekcha) --}}
    <div class="header-uz">
        O'ZBEKISTON RESPUBLIKASI SOG'LIQNI SAQLASH VAZIRLIGI<br>
        TOSHKENT DAVLAT TIBBIYOT UNIVERSITETI TERMIZ FILIALI
    </div>

    {{-- Universitet nomi (inglizcha) --}}
    <div class="header-en">
        MINISTRY OF THE HEALTH OF THE REPUBLIC OF UZBEKISTAN<br>
        TERMEZ BRANCH OF TASHKENT STATE MEDICAL UNIVERSITY
    </div>

    {{-- Manzil --}}
    <div class="address">
        190100, Surxondaryo viloyati, Termiz shahri | Tel/Fax: (0376) 000-00-00 | web: www.tdtutf.uz
    </div>

    {{-- Hujjat meta: sana + raqam (chap) va sarlavha (o'ng) --}}
    <table class="doc-meta" cellpadding="0" cellspacing="0">
        <tr>
            <td class="left-col">
                {{ $reviewDate->format('Y') }} yil
                &laquo;{{ $reviewDate->format('j') }}&raquo;
                {{ $months[$reviewDate->month] ?? $reviewDate->format('F') }}<br>
                08-{{ str_pad($excuse->id, 5, '0', STR_PAD_LEFT) }} - son
            </td>
            <td class="right-col">
                <div class="farmoyish-title">
                    REGISTRATOR OFISI<br>FARMOYISHI
                </div>
                <div class="academic-year">
                    {{ $academicYear }} o'quv yili
                </div>
            </td>
        </tr>
    </table>

    {{-- Asosiy matn --}}
    <div class="body-text">
        <p>
            Toshkent davlat tibbiyot universiteti Termiz filiali
            {{ $excuse->department_name ? $excuse->department_name : '' }}
            {{ $excuse->group_name ? $excuse->group_name . ' guruh' : '' }}
            talabasi <span class="student-name">{{ $excuse->student_full_name }}</span>
            (HEMIS ID: {{ $excuse->student_hemis_id }})
            {{ mb_strtolower($excuse->reason_label) }} sababli
            {{ $excuse->start_date->format('Y') }}-yil
            {{ $excuse->start_date->format('j') }}-{{ $excuse->end_date->format('j') }}-{{ $months[$excuse->start_date->month] ?? $excuse->start_date->format('F') }}
            kunlari ({{ $excuse->start_date->diffInDays($excuse->end_date) + 1 }} kun)
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

    {{-- Imzolar --}}
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

    {{-- Ijrochi ma'lumotlari --}}
    <div class="executor">
        Ijrochi: {{ $excuse->reviewed_by_name ?? '-' }}<br>
        ID {{ str_pad($excuse->id, 6, '0', STR_PAD_LEFT) }}<br>
        Sana: {{ $reviewDate->format('d.m.Y') }}
    </div>

    {{-- QR kod --}}
    @if(isset($qrCodeBase64))
        <div class="qr-container">
            <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="QR Code">
        </div>
    @endif

</body>
</html>
