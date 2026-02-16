<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Sessiya tugagan</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f1f5f9;
        }
        .card {
            text-align: center;
            background: white;
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.07);
            max-width: 360px;
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 20px; color: #1e293b; margin: 0 0 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0 0 20px; }
        a {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
        }
        a:hover { box-shadow: 0 4px 12px rgba(30,64,175,0.3); }
        .timer { font-size: 12px; color: #94a3b8; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#128274;</div>
        <h1>Sessiya tugagan</h1>
        <p>Sahifa muddati tugagan. Qaytadan kirish uchun quyidagi tugmani bosing.</p>
        <a href="{{ url('/') }}">Bosh sahifaga qaytish</a>
        <p class="timer" id="countdown">Avtomatik qaytish: <span id="sec">5</span> soniya</p>
    </div>
    <script>
        var s = 5;
        var el = document.getElementById('sec');
        var t = setInterval(function() {
            s--;
            el.textContent = s;
            if (s <= 0) {
                clearInterval(t);
                window.location.href = '{{ url("/") }}';
            }
        }, 1000);
    </script>
</body>
</html>
