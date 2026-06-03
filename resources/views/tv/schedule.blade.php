<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Dars jadvali — keyingi 60 daqiqa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #0b1220;
            --panel: #131c30;
            --border: #243049;
            --text: #e8eef9;
            --muted: #8c9bb5;
            --accent: #3b82f6;
            --ongoing: #22c55e;
            --soon: #38bdf8;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        .screen {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2.2vh 2.2vw;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1.6vh;
            border-bottom: 2px solid var(--border);
        }
        header .title h1 {
            font-size: 3.4vh;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        header .title p {
            font-size: 1.9vh;
            color: var(--muted);
            margin-top: 0.4vh;
            text-transform: capitalize;
        }
        header .clock {
            text-align: right;
        }
        header .clock .time {
            font-size: 6.2vh;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        header .clock .label {
            font-size: 1.7vh;
            color: var(--muted);
            margin-top: 0.6vh;
        }

        main {
            flex: 1;
            margin-top: 1.8vh;
            overflow: hidden;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(30vw, 1fr));
            gap: 1.4vh 1.4vw;
            align-content: start;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-left: 0.7vw solid var(--accent);
            border-radius: 1.1vh;
            padding: 1.6vh 1.5vw;
            display: flex;
            flex-direction: column;
            gap: 0.7vh;
        }
        .card.ongoing { border-left-color: var(--ongoing); }
        .card.soon { border-left-color: var(--soon); }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1vw;
        }
        .card-time {
            font-size: 2.6vh;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .badge {
            font-size: 1.6vh;
            font-weight: 700;
            padding: 0.5vh 0.9vw;
            border-radius: 0.7vh;
            white-space: nowrap;
        }
        .badge.ongoing { background: rgba(34, 197, 94, 0.18); color: var(--ongoing); }
        .badge.soon { background: rgba(56, 189, 248, 0.18); color: var(--soon); }

        .card-subject {
            font-size: 2.5vh;
            font-weight: 800;
            line-height: 1.2;
        }
        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4vh 1.6vw;
            font-size: 1.9vh;
            color: var(--muted);
        }
        .card-meta b { color: var(--text); font-weight: 600; }
        .card-room {
            font-size: 2.1vh;
            font-weight: 700;
            color: #fbbf24;
        }

        .empty, .error {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5vh;
            color: var(--muted);
        }
        .empty .icon { font-size: 9vh; opacity: 0.4; }
        .empty .msg { font-size: 3vh; font-weight: 700; }
        .empty .sub { font-size: 2vh; }
        .error .msg { font-size: 2.4vh; color: #f87171; font-weight: 700; }

        footer {
            padding-top: 1.2vh;
            margin-top: 1.2vh;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 1.6vh;
            color: var(--muted);
        }
        .legend { display: flex; gap: 1.6vw; }
        .legend span { display: flex; align-items: center; gap: 0.5vw; }
        .dot { width: 1.4vh; height: 1.4vh; border-radius: 50%; display: inline-block; }
        .dot.ongoing { background: var(--ongoing); }
        .dot.soon { background: var(--soon); }
    </style>
</head>
<body>
<div class="screen">
    <header>
        <div class="title">
            <h1>Dars jadvali — keyingi 60 daqiqa</h1>
            <p id="date">&nbsp;</p>
        </div>
        <div class="clock">
            <div class="time" id="clock">--:--</div>
            <div class="label">Hozirgi vaqt</div>
        </div>
    </header>

    <main id="main">
        <div class="empty">
            <div class="msg">Yuklanmoqda...</div>
        </div>
    </main>

    <footer>
        <div class="legend">
            <span><i class="dot ongoing"></i> Hozir ketmoqda</span>
            <span><i class="dot soon"></i> Tez orada boshlanadi</span>
        </div>
        <div id="status">&nbsp;</div>
    </footer>
</div>

<script>
    const DATA_URL = "{{ route('tv.schedule.data') }}";
    const REFRESH_MS = 30000;

    function tickClock() {
        const d = new Date();
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        document.getElementById('clock').textContent = hh + ':' + mm;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function renderLesson(l) {
        const cls = l.status === 'ongoing' ? 'ongoing' : 'soon';
        const badge = l.status === 'ongoing'
            ? '<span class="badge ongoing">Hozir ketmoqda</span>'
            : '<span class="badge soon">' + l.minutes_to_start + ' daqiqadan keyin</span>';

        const meta = [];
        if (l.group_name) meta.push('<span>Guruh: <b>' + escapeHtml(l.group_name) + '</b></span>');
        if (l.employee_name) meta.push('<span>O‘qituvchi: <b>' + escapeHtml(l.employee_name) + '</b></span>');
        if (l.training_type_name) meta.push('<span><b>' + escapeHtml(l.training_type_name) + '</b></span>');

        let room = escapeHtml(l.auditorium_name);
        if (l.building_name) room += ' · ' + escapeHtml(l.building_name);

        return '<div class="card ' + cls + '">'
            + '<div class="card-top">'
            + '<span class="card-time">' + escapeHtml(l.start) + ' – ' + escapeHtml(l.end) + '</span>'
            + badge
            + '</div>'
            + '<div class="card-subject">' + escapeHtml(l.subject_name) + '</div>'
            + '<div class="card-room">📍 ' + room + '</div>'
            + (meta.length ? '<div class="card-meta">' + meta.join('') + '</div>' : '')
            + '</div>';
    }

    function render(data) {
        document.getElementById('date').textContent = data.date || '';

        const main = document.getElementById('main');
        if (!data.lessons || data.lessons.length === 0) {
            main.innerHTML = '<div class="empty">'
                + '<div class="icon">📅</div>'
                + '<div class="msg">Keyingi 60 daqiqada dars yo‘q</div>'
                + '<div class="sub">Hozir ketayotgan yoki tez orada boshlanadigan darslar mavjud emas</div>'
                + '</div>';
        } else {
            main.innerHTML = '<div class="grid">'
                + data.lessons.map(renderLesson).join('')
                + '</div>';
        }

        const t = new Date();
        document.getElementById('status').textContent = 'Yangilandi: '
            + String(t.getHours()).padStart(2, '0') + ':'
            + String(t.getMinutes()).padStart(2, '0') + ':'
            + String(t.getSeconds()).padStart(2, '0');
    }

    function loadData() {
        fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(render)
            .catch(err => {
                document.getElementById('status').textContent = 'Ulanishda xatolik — qayta urinilmoqda...';
                console.error('TV schedule fetch failed:', err);
            });
    }

    tickClock();
    setInterval(tickClock, 1000);
    loadData();
    setInterval(loadData, REFRESH_MS);
</script>
</body>
</html>
