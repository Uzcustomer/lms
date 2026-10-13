<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <title>{{ $subject->subject_name }} — mavzular</title>
    <style>
        @page { margin: 18mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; }
        .head { border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .uni { font-size: 8.5pt; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
        h1 { margin: 4px 0 2px; font-size: 15pt; color: #0f2748; }
        .meta { font-size: 9pt; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a8a; color: #fff; font-size: 8.5pt; padding: 5px 6px; text-align: left; }
        td { border-bottom: 1px solid #e2e8f0; padding: 5px 6px; vertical-align: top; font-size: 9.5pt; }
        td.c { text-align: center; white-space: nowrap; }
        tr.week-start td { border-top: 1.5px solid #94a3b8; }
        .type { font-weight: bold; font-size: 8.5pt; }
        .type.lec { color: #1e40af; }
        .type.prc { color: #166534; }
        .type.oth { color: #5b21b6; }
        .none { color: #94a3b8; font-style: italic; }
        .foot { margin-top: 10px; font-size: 8pt; color: #94a3b8; }
    </style>
</head>
<body>
@php
    $tone = function (string $name) {
        $n = mb_strtolower($name);
        if (str_contains($n, 'ma\'ruza') || str_contains($n, 'maruza') || str_contains($n, 'ma’ruza') || str_contains($n, 'maʼruza')) return 'lec';
        if (str_contains($n, 'amaliy')) return 'prc';
        return 'oth';
    };
@endphp
    <div class="head">
        <div class="uni">Toshkent davlat tibbiyot universiteti Termiz filiali</div>
        <h1>{{ $subject->subject_name }}</h1>
        <div class="meta">
            {{ $subject->semester_name }} · Mashg'ulotlar mavzulari (KTR bo'yicha)<br>
            Talaba: {{ $student->full_name }} · {{ $student->group_name }}
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 9%;">Hafta</th>
            <th style="width: 13%;">Sana</th>
            <th style="width: 15%;">Turi</th>
            <th>Mavzu</th>
            <th style="width: 8%;">Soat</th>
        </tr>
        </thead>
        <tbody>
        @foreach($topics['weeks'] as $week)
            @foreach($week['items'] as $i => $item)
                <tr class="{{ $i === 0 ? 'week-start' : '' }}">
                    <td class="c">{{ $i === 0 ? $week['week'] : '' }}</td>
                    <td class="c">{{ $i === 0 ? $week['dates'] : '' }}</td>
                    <td><span class="type {{ $tone($item['type']) }}">{{ $item['type'] }}</span></td>
                    <td>@if($item['topic'] !== ''){{ $item['topic'] }}@else<span class="none">Mavzu kiritilmagan</span>@endif</td>
                    <td class="c">{{ $item['hours'] > 0 ? $item['hours'] : '' }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>

    <div class="foot">Yuklab olingan: {{ now('Asia/Tashkent')->format('d.m.Y H:i') }} · mark.tashmedunitf.uz</div>
</body>
</html>
