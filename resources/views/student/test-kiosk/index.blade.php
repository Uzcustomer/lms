<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test kiosk</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:620px;background:#fff;border:1px solid #dbe4ef;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);overflow:hidden}
        .head{padding:24px 26px;background:#f8fbff;border-bottom:1px solid #dbe4ef}
        .body{padding:28px 30px}
        .label{display:block;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#475569;margin-bottom:8px}
        .input{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:15px 16px;font-size:24px;font-weight:600;box-sizing:border-box}
        .input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.12)}
        .btn{display:inline-flex;align-items:center;justify-content:center;width:100%;border:none;border-radius:12px;padding:14px 18px;background:#2563eb;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
        .error{margin-top:14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        .hint{margin-top:16px;font-size:14px;line-height:1.7;color:#64748b}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div style="font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">Test kiosk</div>
            <h1 style="margin:10px 0 0;font-size:32px;line-height:1.1;font-weight:600;">Student ID bilan kirish</h1>
            <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#475569;">
                Talaba o'zining Student ID raqamini kiritadi. Agar hozirgi dars uchun test ochiq bo'lsa, shu yerdan testni boshlaydi.
            </p>
        </div>
        <div class="body">
            <form method="POST" action="{{ route('student.test-kiosk.lookup') }}">
                @csrf
                <label class="label">Student ID</label>
                <input
                    type="text"
                    name="student_id_number"
                    class="input"
                    value="{{ old('student_id_number') }}"
                    placeholder="Masalan: 368251100277"
                    autofocus
                    autocomplete="off"
                >
                @error('student_id_number')
                    <div class="error">{{ $message }}</div>
                @enderror
                <div style="margin-top:18px;">
                    <button type="submit" class="btn">Davom etish</button>
                </div>
            </form>

            <div class="hint">
                Test hali ochilmagan bo'lsa, keyingi oynada kutish holati chiqadi. O'qituvchi testni ochgandan keyin `Testni boshlash` tugmasi paydo bo'ladi.
            </div>
        </div>
    </div>
</div>
</body>
</html>
