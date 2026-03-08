<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Face ID Test</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="font-family:sans-serif; padding:20px; background:#f8fafc;">

<div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 8px rgba(0,0,0,0.08);">
    <h1 style="font-size:1.2rem; font-weight:700; color:#1e293b; margin-bottom:16px;">
        🧪 Face ID — Sinov sahifasi <span style="font-size:11px; color:#10b981; font-weight:normal;">[minimal test]</span>
    </h1>

    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:16px;">
        <div style="padding:12px; background:#f0f9ff; border-radius:8px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#0369a1;">{{ $enrolledCount }}</div>
            <div style="font-size:11px; color:#64748b;">Descriptor</div>
        </div>
        <div style="padding:12px; background:#f0fdf4; border-radius:8px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#15803d;">{{ $totalStudents }}</div>
            <div style="font-size:11px; color:#64748b;">Talabalar</div>
        </div>
        <div style="padding:12px; background:#fff7ed; border-radius:8px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#9a3412;">{{ $totalTeachers }}</div>
            <div style="font-size:11px; color:#64748b;">Xodimlar</div>
        </div>
    </div>

    <div style="padding:12px; background:#f1f5f9; border-radius:8px; font-size:12px; color:#475569;">
        <strong>Threshold:</strong> {{ $settings['threshold'] }} &nbsp;|&nbsp;
        <strong>Blinks:</strong> {{ $settings['blinks_required'] }} &nbsp;|&nbsp;
        <strong>Timeout:</strong> {{ $settings['liveness_timeout'] }}s
    </div>

    <div style="margin-top:16px; padding:12px; background:#dcfce7; border-radius:8px; color:#15803d; font-size:13px; font-weight:600; text-align:center;">
        ✅ Sahifa muvaffaqiyatli yuklandi! (minimal versiya)
    </div>

    <div style="margin-top:16px; text-align:center;">
        <a href="{{ route('admin.face-id.settings') }}" style="font-size:12px; color:#6366f1;">← Sozlamalar</a>
    </div>
</div>

</body>
</html>
