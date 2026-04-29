<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Tasdiqnoma — {{ $application->id }}</title>
    <style>
        @page { margin: 30mm 20mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d3d6d;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 14px;
            color: #0d3d6d;
            margin: 0 0 4px;
            font-weight: bold;
        }
        .header .title {
            font-size: 22px;
            color: #1f2937;
            margin: 14px 0 4px;
            font-weight: bold;
            letter-spacing: 4px;
        }
        .header .subtitle {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }
        .content {
            margin: 20px 0;
        }
        .row {
            margin-bottom: 8px;
            display: table;
            width: 100%;
        }
        .label {
            display: table-cell;
            width: 35%;
            color: #6b7280;
            font-size: 11px;
            vertical-align: top;
            padding-right: 10px;
        }
        .value {
            display: table-cell;
            width: 65%;
            font-weight: 600;
            color: #1f2937;
            font-size: 12px;
            vertical-align: top;
        }
        .section-title {
            font-size: 11px;
            color: #0d3d6d;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        .footer {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
            font-size: 11px;
            color: #4b5563;
        }
        .footer-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }
        .qr-box {
            display: inline-block;
            border: 1px solid #e5e7eb;
            padding: 8px;
            border-radius: 4px;
            background: #ffffff;
        }
        .qr-caption {
            font-size: 10px;
            color: #6b7280;
            margin-top: 6px;
            line-height: 1.4;
            max-width: 220px;
        }
        .verify-url {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            color: #1f2937;
            word-break: break-all;
        }
        .check-mark {
            color: #10b981;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Toshkent davlat tibbiyot universiteti Termiz filiali</h1>
        <p class="title">TASDIQNOMA</p>
        <p class="subtitle">Qayta o'qish uchun ruxsat</p>
    </div>

    <div class="content">

        <div class="section-title">Talaba</div>
        <div class="row">
            <div class="label">F.I.Sh.:</div>
            <div class="value">{{ $student?->full_name }}</div>
        </div>
        <div class="row">
            <div class="label">Fakultet:</div>
            <div class="value">{{ $student?->department_name }}</div>
        </div>
        <div class="row">
            <div class="label">Yo'nalish:</div>
            <div class="value">{{ $student?->specialty_name }}</div>
        </div>
        <div class="row">
            <div class="label">Guruh:</div>
            <div class="value">{{ $student?->group_name }}</div>
        </div>

        <div class="section-title">Fan</div>
        <div class="row">
            <div class="label">Fan:</div>
            <div class="value">{{ $application->subject_name }}</div>
        </div>
        <div class="row">
            <div class="label">Semestr:</div>
            <div class="value">{{ $application->semester_name }}</div>
        </div>
        <div class="row">
            <div class="label">Kredit:</div>
            <div class="value">{{ number_format((float) $application->credit, 1) }}</div>
        </div>

        @if($group)
        <div class="section-title">Qayta o'qish guruhi</div>
        <div class="row">
            <div class="label">Guruh:</div>
            <div class="value">{{ $group->name }}</div>
        </div>
        <div class="row">
            <div class="label">Sanalar:</div>
            <div class="value">
                {{ $group->start_date->format('d.m.Y') }} → {{ $group->end_date->format('d.m.Y') }}
            </div>
        </div>
        <div class="row">
            <div class="label">O'qituvchi:</div>
            <div class="value">{{ $teacher?->full_name }}</div>
        </div>
        @endif

        <div class="section-title">Tasdiq</div>
        <div class="row">
            <div class="label">Tasdiqlangan sana:</div>
            <div class="value">{{ $application->academic_dept_reviewed_at?->format('d.m.Y') }}</div>
        </div>
        <div class="row">
            <div class="label">Ariza №:</div>
            <div class="value">{{ str_pad((string) $application->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>

    </div>

    <div class="footer">
        <div class="footer-left">
            <p style="margin: 0 0 4px;"><span class="check-mark">✓</span> Ushbu tasdiqnoma haqiqiy.</p>
            <p style="margin: 0 0 4px;">Haqiqiyligini quyidagi havola yoki QR kod orqali tekshirish mumkin:</p>
            <p class="verify-url">{{ $verifyUrl }}</p>
        </div>
        <div class="footer-right">
            <div class="qr-box">
                {!! $qrSvg !!}
            </div>
            <p class="qr-caption">QR kodni skanerlang</p>
        </div>
    </div>

</body>
</html>
