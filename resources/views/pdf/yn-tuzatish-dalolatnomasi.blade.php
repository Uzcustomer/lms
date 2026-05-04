<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Tuzatish dalolatnomasi #{{ $correction->id }}</title>
    <style>
        @page { size: A4; margin: 1.5cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.4; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 4px 0; text-transform: uppercase; }
        h2 { font-size: 12pt; text-align: center; margin: 0 0 16px 0; font-weight: normal; }
        .header { margin-bottom: 20px; }
        .meta { font-size: 10pt; text-align: right; color: #555; margin-bottom: 12px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-table td { padding: 5px 8px; vertical-align: top; }
        .info-table td.label { width: 35%; font-weight: bold; color: #444; }
        .info-table tr { border-bottom: 1px solid #e5e5e5; }
        .reason-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 10px 12px; margin: 12px 0; font-size: 10.5pt; }
        .grades-table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 10pt; }
        .grades-table th, .grades-table td { border: 1px solid #ccc; padding: 5px 8px; }
        .grades-table th { background: #f3f4f6; text-align: left; font-weight: bold; }
        .signature-block { margin-top: 40px; }
        .signature-row { margin-bottom: 28px; }
        .signature-row .label { display: inline-block; width: 35%; }
        .signature-row .line { display: inline-block; width: 28%; border-bottom: 1px solid #555; }
        .signature-row .name { display: inline-block; margin-left: 8px; color: #555; font-style: italic; }
        .footer { margin-top: 24px; font-size: 9pt; color: #777; text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 9pt; font-weight: bold; }
        .badge-amber { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="meta">
        Hujjat raqami: TD-{{ str_pad($correction->id, 5, '0', STR_PAD_LEFT) }}<br>
        Sana: {{ $generatedAt->format('d.m.Y H:i') }}
    </div>

    <div class="header">
        <h1>Tuzatish dalolatnomasi</h1>
        <h2>{{ $formName }} bo'yicha kechikkan sababli ma'lumotnoma</h2>
    </div>

    <p style="margin-bottom: 14px;">
        Quyidagi tafsilotlarda ko'rsatilgan talaba uchun <strong>{{ $formName }}</strong> yakuniy
        qilingandan keyin sababli ma'lumotnoma keltirilgan va tasdiqlanganligi to'g'risida
        ushbu dalolatnoma tuzildi.
    </p>

    <table class="info-table">
        <tr>
            <td class="label">Talaba (FISH):</td>
            <td>{{ $correction->student_name ?? $correction->student_hemis_id }}</td>
        </tr>
        <tr>
            <td class="label">Talaba HEMIS ID:</td>
            <td>{{ $correction->student_hemis_id }}</td>
        </tr>
        <tr>
            <td class="label">Guruh:</td>
            <td>{{ $correction->group_name ?? $correction->group_hemis_id }}</td>
        </tr>
        <tr>
            <td class="label">Fan:</td>
            <td>{{ $correction->subject_name ?? $correction->subject_id }}</td>
        </tr>
        <tr>
            <td class="label">Semestr:</td>
            <td>{{ $correction->semester_code }}</td>
        </tr>
        <tr>
            <td class="label">Yakuniylashtirilgan shakl:</td>
            <td><span class="badge badge-amber">{{ $formName }}</span></td>
        </tr>
        @if($correction->excuse_doc_number)
        <tr>
            <td class="label">Sababli ariza raqami:</td>
            <td>#{{ $correction->absence_excuse_id }} — hujjat: {{ $correction->excuse_doc_number }}</td>
        </tr>
        @endif
        @if($correction->excuse_start_date && $correction->excuse_end_date)
        <tr>
            <td class="label">Sababli davr:</td>
            <td>
                {{ \Carbon\Carbon::parse($correction->excuse_start_date)->format('d.m.Y') }}
                —
                {{ \Carbon\Carbon::parse($correction->excuse_end_date)->format('d.m.Y') }}
            </td>
        </tr>
        @endif
        @if($correction->excuse_reason)
        <tr>
            <td class="label">Sababli ariza turi:</td>
            <td>{{ $correction->excuse_reason }}</td>
        </tr>
        @endif
        @if($correction->excuse_reviewed_at && $correction->excuse_reviewed_by_name)
        <tr>
            <td class="label">Ariza tasdiqlangan:</td>
            <td>{{ \Carbon\Carbon::parse($correction->excuse_reviewed_at)->format('d.m.Y H:i') }} — {{ $correction->excuse_reviewed_by_name }}</td>
        </tr>
        @endif
    </table>

    <div class="reason-box">
        <strong>Tuzatish sababi:</strong> {{ $correction->reason ?: 'Yakuniy qilingandan keyin sababli ariza keldi.' }}
    </div>

    @if(!empty($originalGrades) && count($originalGrades) > 0)
        <h3 style="font-size: 11pt; margin: 18px 0 6px 0;">Joriy OSKI / Test natijalari:</h3>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Tur</th>
                    <th>Asl baho</th>
                    <th>Otrabotka</th>
                    <th>Sababli</th>
                    <th>Sana</th>
                </tr>
            </thead>
            <tbody>
                @foreach($originalGrades as $g)
                    <tr>
                        <td>{{ $g->training_type_name }}</td>
                        <td>{{ $g->grade !== null ? round($g->grade) : '—' }}</td>
                        <td>{{ $g->retake_grade !== null ? round($g->retake_grade) : '—' }}</td>
                        <td>{{ !empty($g->retake_was_sababli) ? 'Ha' : 'Yo\'q' }}</td>
                        <td>{{ $g->lesson_date ? \Carbon\Carbon::parse($g->lesson_date)->format('d.m.Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 16px;">
        Ushbu dalolatnoma asosida talaba <strong>{{ $correction->student_name ?? $correction->student_hemis_id }}</strong>
        ning sababli ma'lumotnomasi {{ $formName }} qaydnomasiga qo'shilgan tuzatish sifatida e'tirof etiladi.
    </p>

    <div class="signature-block">
        <div class="signature-row">
            <span class="label">Tasdiqlovchi (qabul qilgan):</span>
            <span class="line"></span>
            <span class="name">{{ $correction->performed_by_name ?? '' }}</span>
        </div>
        <div class="signature-row">
            <span class="label">Dekan / dekan o'rinbosari:</span>
            <span class="line"></span>
            <span class="name">_______________________</span>
        </div>
        <div class="signature-row">
            <span class="label">Kafedra mudiri:</span>
            <span class="line"></span>
            <span class="name">_______________________</span>
        </div>
        <div class="signature-row">
            <span class="label">O'qituvchi:</span>
            <span class="line"></span>
            <span class="name">_______________________</span>
        </div>
    </div>

    <div class="footer">
        Tuzilgan: {{ $generatedAt->format('d.m.Y H:i') }}
        | Hujjat ID: TD-{{ str_pad($correction->id, 5, '0', STR_PAD_LEFT) }}
    </div>
</body>
</html>
