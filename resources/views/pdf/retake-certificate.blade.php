<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Qayta o'qish tasdiqnomasi</title>
    <style>
        @page { margin: 1.6cm 1.5cm 1.4cm 1.8cm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; }
        .header { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 14px; margin: 0 0 4px; }
        .header .sub { font-size: 10px; color: #4b5563; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin: 18px 0 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #4b5563; margin-bottom: 18px; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-grid td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .info-grid td.label { width: 30%; color: #6b7280; }
        table.subjects { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10.5px; }
        table.subjects th { background: #f3f4f6; padding: 6px; text-align: left; border: 1px solid #d1d5db; font-weight: bold; }
        table.subjects td { padding: 6px; border: 1px solid #d1d5db; vertical-align: top; }
        .totals { margin-top: 8px; font-size: 11px; text-align: right; }
        .footer { position: fixed; bottom: 0.6cm; left: 1.8cm; right: 1.5cm; }
        .footer-row { display: table; width: 100%; border-top: 1px solid #d1d5db; padding-top: 8px; }
        .qr-box { display: table-cell; vertical-align: middle; width: 110px; text-align: left; }
        .qr-box svg { width: 100px; height: 100px; }
        .footer-text { display: table-cell; vertical-align: middle; padding-left: 14px; font-size: 9px; color: #4b5563; }
        .footer-text .verify-token { font-family: monospace; word-break: break-all; }
        .stamp { margin-top: 14px; font-size: 10px; color: #6b7280; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TOSHKENT DAVLAT TIBBIYOT UNIVERSITETI</h1>
        <div class="sub">TERMIZ FILIALI</div>
    </div>

    <div class="title">QAYTA O'QISH TASDIQNOMASI</div>
    <div class="subtitle">Akademik qarzdorlik bo'yicha qayta o'qish ruxsati</div>

    <table class="info-grid">
        <tr>
            <td class="label">Talaba F.I.SH.</td>
            <td><strong>{{ $student->full_name ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td class="label">HEMIS ID</td>
            <td>{{ $group->student_hemis_id }}</td>
        </tr>
        <tr>
            <td class="label">Fakultet</td>
            <td>{{ $student->department_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Yo'nalish</td>
            <td>{{ $student->specialty_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Kurs · Guruh</td>
            <td>{{ $student->level_name ?? $student->level_code }} · {{ $student->group_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Ariza UUID</td>
            <td style="font-family: monospace; font-size: 10px;">{{ $group->group_uuid }}</td>
        </tr>
        <tr>
            <td class="label">Ariza yuborilgan sana</td>
            <td>{{ $group->created_at->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <h2>Tasdiqlangan fanlar</h2>
    <table class="subjects">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th>Fan</th>
                <th style="width: 17%;">Semestr</th>
                <th style="width: 7%; text-align: right;">Kredit</th>
                <th style="width: 18%;">O'qituvchi</th>
                <th style="width: 18%;">Sanalar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($approvedApps as $i => $app)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $app->subject_name }}
                        @if($app->retakeGroup)
                            <div style="color:#6b7280; font-size: 9.5px; margin-top:2px;">
                                Guruh: {{ $app->retakeGroup->name }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $app->semester_name }}</td>
                    <td style="text-align: right;">{{ number_format((float)$app->credit, 1) }}</td>
                    <td>{{ $app->retakeGroup?->teacher_name ?? '—' }}</td>
                    <td>
                        @if($app->retakeGroup)
                            {{ $app->retakeGroup->start_date?->format('Y-m-d') }}
                            <br>
                            {{ $app->retakeGroup->end_date?->format('Y-m-d') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <strong>Jami: {{ number_format($totalCredits, 1) }} kredit</strong>
        &nbsp;·&nbsp;
        To'lov: <strong>{{ number_format($totalAmount, 0, '.', ' ') }} UZS</strong>
    </div>

    <div class="stamp">
        Ushbu tasdiqnoma elektron tizim tomonidan generatsiya qilingan.
        QR kod orqali hujjatning haqiqiyligini tekshirish mumkin.
    </div>

    <div class="footer">
        <div class="footer-row">
            <div class="qr-box">
                {!! $qrSvg !!}
            </div>
            <div class="footer-text">
                <div><strong>Hujjatni tekshirish:</strong></div>
                <div>{{ $verifyUrl }}</div>
                <div class="verify-token">Token: {{ $verificationToken }}</div>
                <div style="margin-top:6px;">Generatsiya sanasi: {{ now()->format('Y-m-d H:i') }}</div>
            </div>
        </div>
    </div>
</body>
</html>
