<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Test') · TTA Termiz filiali</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 0;
            background: #eef3fa; color: #16263f;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .k-wrap { max-width: 880px; margin: 0 auto; padding: 28px 18px 60px; }
        .k-wide { max-width: 980px; }
        .k-brand {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-bottom: 22px; color: #4a627f; font-size: 12px; font-weight: 800;
            letter-spacing: .14em; text-transform: uppercase;
        }
        .k-card {
            overflow: hidden; border: 1px solid #d9e4f2; border-radius: 18px;
            background: #fff; box-shadow: 0 14px 40px rgba(23, 52, 94, .09);
        }
        .k-head {
            padding: 24px 28px; color: #fff;
            background: linear-gradient(120deg, #12386b, #2563eb);
        }
        .k-head h1 { margin: 0; font-size: 22px; font-weight: 900; }
        .k-head p { margin: 7px 0 0; color: #cfe0fb; font-size: 13px; }
        .k-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .k-chip {
            padding: 5px 11px; border-radius: 999px;
            background: rgba(255,255,255,.16); color: #fff; font-size: 11px; font-weight: 800;
        }
        .k-body { padding: 26px 28px 28px; }
        .k-label { display: block; margin-bottom: 7px; color: #47597a; font-size: 12px; font-weight: 800; }
        .k-input {
            width: 100%; height: 56px; padding: 0 16px;
            border: 2px solid #cbdaee; border-radius: 12px;
            background: #fff; color: #16263f; font-size: 20px; font-weight: 700;
            letter-spacing: .04em; outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .k-input:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,.13); }
        .k-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 54px; border: 0; border-radius: 12px;
            background: #2563eb; color: #fff; font-size: 16px; font-weight: 800;
            cursor: pointer; transition: background .15s, transform .15s;
        }
        .k-btn:hover { background: #1d4ed8; transform: translateY(-1px); }
        .k-btn-lg { font-size: 17px; height: 58px; }
        .k-error {
            margin-bottom: 16px; padding: 13px 15px;
            border: 1px solid #fecaca; border-radius: 11px;
            background: #fef2f2; color: #b91c1c; font-size: 13px; font-weight: 700;
        }
        .k-note { margin-top: 14px; color: #7e91ad; font-size: 12px; text-align: center; }
        @media (max-width: 560px) {
            .k-wrap { padding: 16px 12px 40px; }
            .k-head, .k-body { padding: 18px; }
        }
        @yield('styles')
    </style>
</head>
<body>
    <div class="k-wrap @yield('wrap-class')">
        <div class="k-brand">Toshkent tibbiyot akademiyasi · Termiz filiali</div>
        @yield('content')
    </div>
    @yield('scripts')
</body>
</html>
