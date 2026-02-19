<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #1a1a1a;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1a365d;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header h2 {
            font-size: 13px;
            font-weight: normal;
            color: #4a5568;
            margin-top: 5px;
        }
        .title {
            text-align: center;
            margin: 30px 0;
        }
        .title h3 {
            font-size: 20px;
            font-weight: bold;
            color: #1a365d;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .title .number {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }
        .content {
            margin: 20px 0;
        }
        .content p {
            margin-bottom: 10px;
            text-align: justify;
        }
        .info-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .info-table .label {
            background-color: #f7fafc;
            font-weight: bold;
            width: 40%;
            color: #4a5568;
        }
        .info-table .value {
            color: #1a202c;
        }
        .footer {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            width: 60%;
            vertical-align: bottom;
        }
        .footer-right {
            display: table-cell;
            width: 40%;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line {
            margin-top: 40px;
        }
        .signature-line .line {
            border-top: 1px solid #1a1a1a;
            width: 200px;
            margin-top: 5px;
        }
        .signature-line .label {
            font-size: 11px;
            color: #718096;
            margin-top: 3px;
        }
        .qr-section {
            text-align: center;
        }
        .qr-section img {
            width: 120px;
            height: 120px;
        }
        .qr-section .qr-label {
            font-size: 9px;
            color: #a0aec0;
            margin-top: 5px;
        }
        .stamp-area {
            margin-top: 15px;
            font-size: 11px;
            color: #718096;
        }
        .date-line {
            margin-top: 20px;
            font-size: 12px;
            color: #4a5568;
        }
    </style>
</head>
<body>

    {{-- Sarlavha --}}
    <div class="header">
        <h1>TDTU Termiz filiali</h1>
        <h2>Registrator ofisi</h2>
    </div>

    {{-- Hujjat nomi --}}
    <div class="title">
        <h3>Ma'lumotnoma</h3>
        <div class="number">Ariza raqami: #{{ $excuse->id }} | {{ $excuse->reviewed_at ? $excuse->reviewed_at->format('d.m.Y') : now()->format('d.m.Y') }}</div>
    </div>

    {{-- Asosiy matn --}}
    <div class="content">
        <p>
            Mazkur ma'lumotnoma <strong>{{ $excuse->student_full_name }}</strong> (HEMIS ID: {{ $excuse->student_hemis_id }})
            {{ $excuse->group_name ? $excuse->group_name . ' guruhi' : '' }}
            talabasi {{ $excuse->start_date->format('d.m.Y') }} sanadan {{ $excuse->end_date->format('d.m.Y') }} sanagacha
            darslarni <strong>{{ mb_strtolower($excuse->reason_label) }}</strong> sababli qoldirganligini tasdiqlovchi
            hujjatlari asosida sababli deb topilganligini bildiradi.
        </p>
    </div>

    {{-- Ma'lumotlar jadvali --}}
    <table class="info-table">
        <tr>
            <td class="label">Talaba FIO</td>
            <td class="value">{{ $excuse->student_full_name }}</td>
        </tr>
        <tr>
            <td class="label">HEMIS ID</td>
            <td class="value">{{ $excuse->student_hemis_id }}</td>
        </tr>
        @if($excuse->group_name)
        <tr>
            <td class="label">Guruh</td>
            <td class="value">{{ $excuse->group_name }}</td>
        </tr>
        @endif
        @if($excuse->department_name)
        <tr>
            <td class="label">Fakultet</td>
            <td class="value">{{ $excuse->department_name }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Sabab</td>
            <td class="value">{{ $excuse->reason_label }}</td>
        </tr>
        <tr>
            <td class="label">Dars qoldirish davri</td>
            <td class="value">{{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td class="label">Tasdiqlangan sana</td>
            <td class="value">{{ $excuse->reviewed_at ? $excuse->reviewed_at->format('d.m.Y H:i') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tasdiqlagan</td>
            <td class="value">{{ $excuse->reviewed_by_name ?? '-' }}</td>
        </tr>
    </table>

    {{-- Imzo va QR kod --}}
    <div class="footer">
        <div class="footer-left">
            <div class="date-line">
                Berilgan sana: {{ $excuse->reviewed_at ? $excuse->reviewed_at->format('d.m.Y') : now()->format('d.m.Y') }}
            </div>

            <div class="signature-line">
                <div>Registrator ofisi mas'uli:</div>
                <div class="line"></div>
                <div class="label">{{ $excuse->reviewed_by_name ?? '_______________' }}</div>
            </div>

            <div class="stamp-area">
                M.O'
            </div>
        </div>
        <div class="footer-right">
            <div class="qr-section">
                @if(isset($qrCodeBase64))
                    <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="QR Code">
                @endif
                <div class="qr-label">Hujjat haqiqiyligini<br>tekshirish uchun skanerlang</div>
            </div>
        </div>
    </div>

</body>
</html>
