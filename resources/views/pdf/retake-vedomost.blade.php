<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Qayta o'qish vedomosti</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 6px 0; }
        h2 { font-size: 12px; text-align: center; margin: 0 0 12px 0; font-weight: normal; color: #374151; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 3px 6px; border: 1px solid #d1d5db; vertical-align: top; }
        .meta .label { font-weight: bold; background: #f3f4f6; width: 130px; }
        table.grades { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grades th, table.grades td { border: 1px solid #94a3b8; padding: 4px 5px; }
        table.grades th { background: #e5e7eb; font-weight: bold; text-align: center; }
        table.grades td.center { text-align: center; }
        table.grades td.left { text-align: left; }
        .small { font-size: 9px; color: #4b5563; }
        .signs { width: 100%; margin-top: 24px; }
        .signs td { padding: 16px 8px; vertical-align: bottom; }
        .pass { color: #15803d; font-weight: bold; }
        .fail { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>

<h1>QAYTA O'QISH VEDOMOSTI</h1>
<h2>{{ $group->subject_name }} ({{ $group->semester_name }})</h2>

<table class="meta">
    <tr>
        <td class="label">Guruh nomi</td>
        <td>{{ $group->name }}</td>
        <td class="label">O'qituvchi</td>
        <td>{{ $group->teacher_name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Sanalar</td>
        <td>{{ $group->start_date->format('Y-m-d') }} → {{ $group->end_date->format('Y-m-d') }}</td>
        <td class="label">Baholash turi</td>
        <td>
            @php
                $atypeLabels = [
                    'oske' => 'OSKE',
                    'test' => 'TEST',
                    'oske_test' => 'OSKE + TEST',
                    'sinov_fan' => 'Sinov fan',
                ];
            @endphp
            {{ $atypeLabels[$group->assessment_type] ?? '—' }}
        </td>
    </tr>
    @if($group->oske_date || $group->test_date)
    <tr>
        @if($group->oske_date)
            <td class="label">OSKE sanasi</td>
            <td>{{ $group->oske_date->format('Y-m-d') }}</td>
        @else
            <td colspan="2"></td>
        @endif
        @if($group->test_date)
            <td class="label">TEST sanasi</td>
            <td>{{ $group->test_date->format('Y-m-d') }}</td>
        @else
            <td colspan="2"></td>
        @endif
    </tr>
    @endif
    <tr>
        <td class="label">Vedomost sanasi</td>
        <td colspan="3">{{ now()->format('Y-m-d H:i') }}</td>
    </tr>
</table>

<table class="grades">
    <thead>
        <tr>
            <th rowspan="2" style="width:30px;">T/R</th>
            <th rowspan="2" style="min-width:200px;">F.I.SH.</th>
            <th rowspan="2" style="width:80px;">HEMIS ID</th>
            <th colspan="2">Eski baholar</th>
            <th rowspan="2" style="width:60px;">Amaliyot<br>(o'rtacha)</th>
            <th rowspan="2" style="width:60px;">Mustaqil<br>ta'lim</th>
            @if($group->assessment_type === 'oske' || $group->assessment_type === 'oske_test')
                <th rowspan="2" style="width:50px;">OSKE</th>
            @endif
            @if($group->assessment_type === 'test' || $group->assessment_type === 'oske_test')
                <th rowspan="2" style="width:50px;">TEST</th>
            @endif
            <th rowspan="2" style="width:60px;">Yakuniy</th>
        </tr>
        <tr>
            <th style="width:55px;">Joriy</th>
            <th style="width:55px;">Mustaqil</th>
        </tr>
    </thead>
    <tbody>
        @foreach($applications as $i => $app)
            @php
                $student = $app->group->student ?? null;
                $rowGrades = collect($gradesMap[$app->id] ?? [])
                    ->map(fn ($g) => $g->grade)
                    ->filter(fn ($v) => $v !== null);
                $amaliyotAvg = $rowGrades->isNotEmpty() ? round($rowGrades->avg(), 1) : null;
                $mustaqil = $mustaqilMap[$app->id] ?? null;
                $mGrade = $mustaqil?->grade;
                $final = $app->final_grade_value;
                $pass = $final !== null && (float) $final >= 60;
                $finalClass = $final === null ? '' : ($pass ? 'pass' : 'fail');
            @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="left">{{ $student?->full_name ?? '—' }}</td>
                <td class="center">{{ $app->student_hemis_id }}</td>
                <td class="center">
                    {{ $app->previous_joriy_grade !== null ? rtrim(rtrim(number_format($app->previous_joriy_grade, 2, '.', ''), '0'), '.') : '—' }}
                </td>
                <td class="center">
                    {{ $app->previous_mustaqil_grade !== null ? rtrim(rtrim(number_format($app->previous_mustaqil_grade, 2, '.', ''), '0'), '.') : '—' }}
                </td>
                <td class="center">{{ $amaliyotAvg !== null ? $amaliyotAvg : '—' }}</td>
                <td class="center">{{ $mGrade !== null ? rtrim(rtrim(number_format($mGrade, 2, '.', ''), '0'), '.') : '—' }}</td>
                @if($group->assessment_type === 'oske' || $group->assessment_type === 'oske_test')
                    <td class="center">{{ $app->oske_score !== null ? rtrim(rtrim(number_format($app->oske_score, 2, '.', ''), '0'), '.') : '—' }}</td>
                @endif
                @if($group->assessment_type === 'test' || $group->assessment_type === 'oske_test')
                    <td class="center">{{ $app->test_score !== null ? rtrim(rtrim(number_format($app->test_score, 2, '.', ''), '0'), '.') : '—' }}</td>
                @endif
                <td class="center {{ $finalClass }}">
                    {{ $final !== null ? rtrim(rtrim(number_format($final, 2, '.', ''), '0'), '.') : '—' }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="signs">
    <tr>
        <td>O'qituvchi: <u>&nbsp;{{ $group->teacher_name ?? '_______________________' }}&nbsp;</u></td>
        <td style="text-align:right;">Imzo: ____________________</td>
    </tr>
    <tr>
        <td>Test markazi: ____________________________</td>
        <td style="text-align:right;">Imzo: ____________________</td>
    </tr>
    <tr>
        <td>O'quv bo'limi: ____________________________</td>
        <td style="text-align:right;">Imzo: ____________________</td>
    </tr>
</table>

</body>
</html>
