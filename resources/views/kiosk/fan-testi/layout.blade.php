<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Test') · TTA Termiz filiali</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto:300,400,500,700,900|roboto-slab:400,600,700&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:        #0f2748;
            --navy-soft:   #1b3a63;
            --gold:        #c9a227;
            --gold-soft:   #e3c964;
            --ink:         #17233a;
            --ink-soft:    #4d6180;
            --muted:       #8798b1;
            --line:        #dde5ef;
            --line-soft:   #eef2f8;
            --paper:       #ffffff;
            --bg:          #eef1f6;
            --ok:          #0f7a52;
            --ok-bg:       #e9f7f0;
            --bad:         #b3261e;
            --bad-bg:      #fdeceb;
            --warn:        #a35a06;
            --warn-bg:     #fdf3e4;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            background-image:
                radial-gradient(circle at 12% 0%, rgba(27, 58, 99, .07), transparent 42%),
                radial-gradient(circle at 88% 8%, rgba(201, 162, 39, .07), transparent 38%);
            background-attachment: fixed;
            color: var(--ink);
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 15px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, .serif { font-family: 'Roboto Slab', Georgia, serif; }

        /* ---------- Sarlavha (universitet identifikatsiyasi) ---------- */
        .k-top {
            border-bottom: 3px solid var(--gold);
            background: linear-gradient(180deg, var(--navy), var(--navy-soft));
        }
        .k-top-in {
            display: flex; align-items: center; gap: 14px;
            max-width: 1040px; margin: 0 auto; padding: 15px 20px;
        }
        .k-seal {
            flex: none; display: grid; place-items: center;
            width: 42px; height: 42px; border-radius: 50%;
            border: 2px solid var(--gold-soft);
            background: rgba(255, 255, 255, .06);
            color: var(--gold-soft);
            font-family: 'Roboto Slab', serif; font-size: 15px; font-weight: 700;
            letter-spacing: .02em;
        }
        .k-org b {
            display: block; color: #fff; font-size: 14px; font-weight: 500; letter-spacing: .01em;
        }
        .k-org span {
            display: block; margin-top: 1px;
            color: rgba(255, 255, 255, .62); font-size: 11px; font-weight: 400;
            letter-spacing: .16em; text-transform: uppercase;
        }

        .k-wrap { max-width: 720px; margin: 0 auto; padding: 30px 20px 64px; }
        .k-wide { max-width: 1040px; }

        /* ---------- Karta ---------- */
        .k-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--paper);
            box-shadow: 0 1px 2px rgba(15, 39, 72, .05), 0 12px 34px rgba(15, 39, 72, .08);
        }
        .k-head {
            padding: 26px 30px 24px;
            border-bottom: 1px solid var(--line-soft);
            background: linear-gradient(180deg, #fbfcfe, #f4f7fb);
        }
        .k-eyebrow {
            margin-bottom: 9px;
            color: var(--gold); font-size: 10px; font-weight: 700;
            letter-spacing: .2em; text-transform: uppercase;
        }
        .k-head h1 {
            margin: 0; color: var(--navy);
            font-size: 25px; font-weight: 700; letter-spacing: -.01em; line-height: 1.25;
        }
        .k-head p { margin: 7px 0 0; color: var(--ink-soft); font-size: 14px; }

        .k-meta {
            display: flex; flex-wrap: wrap; gap: 0;
            margin-top: 20px; padding-top: 17px; border-top: 1px solid var(--line);
        }
        .k-meta-item { padding-right: 26px; margin-right: 26px; border-right: 1px solid var(--line); }
        .k-meta-item:last-child { padding-right: 0; margin-right: 0; border-right: 0; }
        .k-meta-item b {
            display: block; color: var(--navy);
            font-family: 'Roboto Slab', serif; font-size: 19px; font-weight: 600;
        }
        .k-meta-item span {
            display: block; margin-top: 1px;
            color: var(--muted); font-size: 10px; font-weight: 500;
            letter-spacing: .11em; text-transform: uppercase;
        }

        .k-body { padding: 28px 30px 30px; }

        /* ---------- Forma ---------- */
        .k-label {
            display: block; margin-bottom: 9px;
            color: var(--ink-soft); font-size: 12px; font-weight: 500;
            letter-spacing: .04em;
        }
        .k-input {
            width: 100%; height: 58px; padding: 0 17px;
            border: 1px solid #c4d0e0; border-radius: 5px;
            background: #fcfdff; color: var(--ink);
            font-family: 'Roboto', sans-serif; font-size: 21px; font-weight: 500;
            letter-spacing: .06em; text-align: center;
            outline: none; transition: border-color .16s, box-shadow .16s, background .16s;
        }
        .k-input::placeholder { color: #b4c1d3; font-size: 16px; letter-spacing: .02em; }
        .k-input:focus {
            border-color: var(--navy-soft); background: #fff;
            box-shadow: 0 0 0 3px rgba(27, 58, 99, .1);
        }

        .k-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            width: 100%; height: 52px; padding: 0 22px;
            border: 0; border-radius: 5px;
            background: var(--navy); color: #fff;
            font-family: 'Roboto', sans-serif; font-size: 15px; font-weight: 500;
            letter-spacing: .04em; text-decoration: none; cursor: pointer;
            transition: background .16s, box-shadow .16s;
        }
        .k-btn:hover { background: var(--navy-soft); box-shadow: 0 4px 14px rgba(15, 39, 72, .22); }
        .k-btn:disabled { background: #9aa9bd; cursor: not-allowed; box-shadow: none; }
        .k-btn-lg { height: 56px; font-size: 16px; }
        .k-btn-ghost {
            border: 1px solid #c4d0e0; background: #fff; color: var(--navy);
        }
        .k-btn-ghost:hover { background: #f5f8fc; box-shadow: none; }

        .k-error {
            display: flex; gap: 10px; margin-bottom: 18px; padding: 13px 16px;
            border: 1px solid #f2c9c6; border-left: 3px solid var(--bad); border-radius: 4px;
            background: var(--bad-bg); color: #8f1d17; font-size: 13.5px;
        }
        .k-note {
            margin-top: 20px; padding-top: 17px; border-top: 1px solid var(--line-soft);
            color: var(--muted); font-size: 12.5px; text-align: center; line-height: 1.75;
        }
        .k-foot {
            max-width: 1040px; margin: 26px auto 0; padding: 0 20px;
            color: #9dabc0; font-size: 11px; text-align: center; letter-spacing: .03em;
        }

        @media (max-width: 600px) {
            .k-wrap { padding: 18px 13px 42px; }
            .k-head, .k-body { padding: 20px 18px; }
            .k-head h1 { font-size: 21px; }
            .k-meta-item { padding-right: 18px; margin-right: 18px; }
        }

@yield('styles')
    </style>
</head>
<body>
    <header class="k-top">
        <div class="k-top-in">
            <div class="k-seal">TTA</div>
            <div class="k-org">
                <b>Toshkent tibbiyot akademiyasi</b>
                <span>Termiz filiali</span>
            </div>
        </div>
    </header>

    <div class="k-wrap @yield('wrap-class')">
        @yield('content')
    </div>

    <div class="k-foot">© {{ date('Y') }} Toshkent tibbiyot akademiyasi Termiz filiali · Elektron ta'lim tizimi</div>

    @yield('scripts')
</body>
</html>
