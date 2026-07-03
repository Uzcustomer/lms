<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test kiosk - {{ $student->full_name }}</title>
    <meta http-equiv="refresh" content="20">
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
        .wrap{max-width:1200px;margin:0 auto;padding:24px}
        .top,.card,.lesson{background:#fff;border:1px solid #dbe4ef;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
        .top{padding:20px 22px;background:#f8fbff}
        .card{padding:18px 20px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
        .lessons{display:grid;gap:14px}
        .lesson-top{padding:18px 20px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .lesson-bottom{padding:18px 20px;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
        .chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid transparent}
        .green{background:#ecfdf5;color:#15803d;border-color:#bbf7d0}.blue{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
        .orange{background:#fff7ed;color:#c2410c;border-color:#fdba74}.gray{background:#f8fafc;color:#475569;border-color:#cbd5e1}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:600;text-decoration:none}
        .btn-primary{background:#059669;color:#fff}.btn-light{background:#fff;color:#334155;border:1px solid #cbd5e1}
        .muted{color:#64748b;font-size:14px;line-height:1.6}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <a href="{{ route('student.test-kiosk.index') }}" class="btn btn-light">Boshqa Student ID kiritish</a>
        <div class="muted">Sahifa avtomatik 20 soniyada yangilanadi.</div>
    </div>

    <div class="top">
        <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <div style="font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">Talaba topildi</div>
                <h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;font-weight:600;">{{ $student->full_name }}</h1>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="chip blue">{{ $student->student_id_number }}</span>
                    <span class="chip green">{{ $student->group_name ?: 'Guruh yo‘q' }}</span>
                    @if($student->semester_name)
                        <span class="chip orange">{{ $student->semester_name }}</span>
                    @endif
                </div>
            </div>
            <div class="muted">
                <div><b>Hozirgi vaqt:</b> {{ $now->format('d.m.Y H:i') }}</div>
                <div style="margin-top:6px;"><b>Kurs:</b> {{ $student->level_name ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div style="height:16px;"></div>

    @if($lessonRows->isEmpty())
        <div class="card">
            <div style="font-size:20px;font-weight:800;">Bugun test fan darsi topilmadi</div>
            <p class="muted" style="margin:10px 0 0;">
                Sizga biriktirilgan test fanlar orasida bugungi sana uchun mavzu topilmadi yoki hozircha bu guruhga test fan biriktirilmagan.
            </p>
        </div>
    @else
        <div class="grid" style="margin-bottom:16px;">
            <div class="card">
                <div style="font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Bugungi darslar</div>
                <div style="margin-top:8px;font-size:28px;font-weight:600;">{{ $lessonRows->count() }}</div>
            </div>
            <div class="card">
                <div style="font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Hozir ochiq test</div>
                <div style="margin-top:8px;font-size:28px;font-weight:600;">{{ $lessonRows->where('can_start', true)->count() }}</div>
            </div>
            <div class="card">
                <div style="font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Yakunlangan</div>
                <div style="margin-top:8px;font-size:28px;font-weight:600;">{{ $lessonRows->where('is_submitted', true)->count() }}</div>
            </div>
        </div>

        <div class="lessons">
            @foreach($lessonRows as $row)
                <div class="lesson">
                    <div class="lesson-top">
                        <div>
                            <div style="font-size:22px;font-weight:600;">{{ $row['subject']->name }}</div>
                            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                                <span class="chip blue">{{ $row['lesson']->topic_title ?: (($row['lesson']->topic_order ?? 1) . '-mavzu') }}</span>
                                <span class="chip gray">{{ optional($row['lesson']->lesson_date)->format('d.m.Y') }}</span>
                                <span class="chip orange">{{ $row['lesson']->starts_at ? substr($row['lesson']->starts_at,0,5) : '--:--' }} - {{ $row['lesson']->ends_at ? substr($row['lesson']->ends_at,0,5) : '--:--' }}</span>
                                <span class="chip green">{{ $row['question_count'] }} ta savol</span>
                            </div>
                        </div>
                        <div>
                            <span class="chip {{ $row['status_variant'] }}">{{ $row['status_text'] }}</span>
                        </div>
                    </div>
                    <div class="lesson-bottom">
                        <div class="muted">{{ $row['status_text'] }}</div>
                        <div>
                            @if($row['test_route'] && $row['is_submitted'])
                                <a href="{{ $row['test_route'] }}" class="btn btn-light">Natijani ko‘rish</a>
                            @elseif($row['test_route'] && $row['can_start'])
                                <a href="{{ $row['test_route'] }}" class="btn btn-primary">Testni boshlash</a>
                            @else
                                <span class="btn btn-light" style="opacity:.7;cursor:default;">Kutilmoqda</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
