<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dars jadvali — {{ $batch->file_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10px; color: #1a1a1a; background: #fff; }

        .print-header {
            text-align: center;
            padding: 16px 20px 12px;
            border-bottom: 2px solid #2b5ea7;
        }
        .print-header h1 { font-size: 16px; color: #1a3268; margin-bottom: 4px; }
        .print-header p { font-size: 11px; color: #64748b; }

        .print-actions {
            text-align: center;
            padding: 12px;
            background: #f0f4ff;
            border-bottom: 1px solid #e2e8f0;
        }
        .print-actions button {
            padding: 8px 24px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            background: #2b5ea7;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 0 4px;
        }
        .print-actions button:hover { background: #1a3268; }
        .print-actions .btn-back { background: #64748b; }
        .print-actions .btn-back:hover { background: #475569; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: top;
        }
        th {
            background: #2b5ea7;
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            padding: 6px 4px;
        }
        .time-cell {
            background: #f0f4ff;
            text-align: center;
            font-weight: 600;
            width: 80px;
            min-width: 80px;
            vertical-align: middle;
        }
        .time-cell .pair-name { font-size: 10px; color: #1a3268; }
        .time-cell .pair-time { font-size: 8px; color: #64748b; margin-top: 2px; }

        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 3px 4px;
            margin-bottom: 2px;
            font-size: 8px;
            line-height: 1.35;
        }
        .card:last-child { margin-bottom: 0; }
        .card-subject { font-weight: 700; font-size: 8.5px; color: #1e40af; }
        .card-teacher { color: #475569; }
        .card-meta { color: #64748b; font-size: 7.5px; }
        .card-group { font-weight: 600; color: #374151; font-size: 7.5px; }
        .card-week { display: inline-block; background: #e0e7ff; color: #4338ca; font-size: 7px; padding: 0 3px; border-radius: 2px; font-weight: 600; }
        .card.conflict { border-color: #ef4444; background: #fef2f2; }

        .cell-content { min-height: 50px; }

        @media print {
            .print-actions { display: none; }
            body { font-size: 9px; }
            @page { size: A3 landscape; margin: 8mm; }
            th { padding: 4px 3px; }
            .card { font-size: 7.5px; }
            .print-header { padding: 10px 16px 8px; }
            .print-header h1 { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>Dars jadvali — {{ $batch->file_name }}</h1>
        <p>{{ $weekLabel }} | Yaratilgan: {{ $batch->created_at->format('d.m.Y H:i') }}</p>
    </div>

    <div class="print-actions">
        <button onclick="window.print()">Chop etish / PDF saqlash</button>
        <button class="btn-back" onclick="window.close()">Yopish</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:80px">Juftlik</th>
                @foreach($days as $dayNum => $dayName)
                    <th>{{ $dayName }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($pairCodes as $pairCode)
                <tr>
                    <td class="time-cell">
                        <div class="pair-name">{{ $pairCode }}-juftlik</div>
                        @if(isset($pairTimes[(int)$pairCode]))
                            <div class="pair-time">{{ $pairTimes[(int)$pairCode]['start'] }} - {{ $pairTimes[(int)$pairCode]['end'] }}</div>
                        @endif
                    </td>
                    @foreach($days as $dayNum => $dayName)
                        <td>
                            <div class="cell-content">
                                @foreach($grid[$dayNum . '_' . $pairCode] ?? [] as $card)
                                    <div class="card {{ $card->has_conflict ? 'conflict' : '' }}">
                                        <div class="card-subject">
                                            {{ $card->subject_name }}
                                            @if($card->week_parity || $card->weeks)
                                                <span class="card-week">{{ $weekLabelFn($card) }}</span>
                                            @endif
                                        </div>
                                        @if($card->employee_name)
                                            <div class="card-teacher">{{ $card->employee_name }}</div>
                                        @endif
                                        @php
                                            $meta = array_filter([$card->building_name, $card->auditorium_name]);
                                        @endphp
                                        @if($meta)
                                            <div class="card-meta">{{ implode(', ', $meta) }}</div>
                                        @endif
                                        <div class="card-group">
                                            {{ $card->group_source ?: $card->group_name }}
                                            @if($card->training_type_name)
                                                | {{ $card->training_type_name }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
