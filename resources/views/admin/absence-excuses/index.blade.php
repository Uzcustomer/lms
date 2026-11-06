<x-app-layout>
    <style>
        /* Sababli arizalar ro'yxati — sahifaning o'z uslubi (Tailwind build talab qilmaydi; Bootstrap bilan to'qnashmasligi uchun ae- prefiksi) */
        .ae-page {
            --ae-navy: #1e3a8a; --ae-navy-2: #1e40af; --ae-ink: #0f172a; --ae-text: #334155; --ae-muted: #64748b;
            --ae-line: #e2e8f0; --ae-soft: #f8fafc;
            --ae-amber: #d97706; --ae-amber-bg: #fffbeb; --ae-amber-line: #fde68a;
            --ae-green: #059669; --ae-green-bg: #ecfdf5; --ae-green-line: #a7f3d0;
            --ae-red: #e11d48; --ae-red-bg: #fff1f2; --ae-red-line: #fecdd3;
            --ae-blue: #2563eb; --ae-blue-bg: #eff6ff; --ae-blue-line: #bfdbfe;
            color: var(--ae-text); font-size: 14px;
        }
        .ae-page *, .ae-page *::before, .ae-page *::after { box-sizing: border-box; }
        .ae-tnum { font-variant-numeric: tabular-nums; }

        /* Sarlavha */
        .ae-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .ae-title { margin: 0; font-size: 24px; line-height: 1.25; font-weight: 700; letter-spacing: -0.01em; color: var(--ae-ink); text-wrap: balance; }
        .ae-sub { margin: 4px 0 0; font-size: 13px; color: var(--ae-muted); }
        .ae-sub b { color: var(--ae-ink); font-weight: 600; }
        .ae-sub .ae-sub-pending { color: var(--ae-amber); font-weight: 600; }

        /* Tugmalar */
        .ae-btns { display: flex; flex-wrap: wrap; gap: 8px; }
        .ae-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 8px 16px; border-radius: 8px;
            font-size: 13px; font-weight: 600; line-height: 1.4; border: 1px solid transparent; cursor: pointer; text-decoration: none !important;
            transition: background-color .15s, border-color .15s, box-shadow .15s; white-space: nowrap; }
        .ae-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
        .ae-btn:focus-visible { outline: 2px solid var(--ae-blue); outline-offset: 2px; }
        .ae-btn--primary { background: var(--ae-navy); color: #fff !important; box-shadow: 0 1px 2px rgba(15,23,42,.12); }
        .ae-btn--primary:hover { background: var(--ae-navy-2); }
        .ae-btn--ghost { background: #fff; color: var(--ae-text) !important; border-color: #cbd5e1; box-shadow: 0 1px 2px rgba(15,23,42,.06); }
        .ae-btn--ghost:hover { background: var(--ae-soft); border-color: #94a3b8; }
        .ae-btn--ghost svg { color: var(--ae-green); }
        .ae-btn--success { background: var(--ae-green); color: #fff !important; }
        .ae-btn--success:hover { background: #047857; }
        .ae-btn--muted { background: #f1f5f9; color: var(--ae-text) !important; }
        .ae-btn--muted:hover { background: #e2e8f0; }
        .ae-btn--sm { padding: 6px 12px; font-size: 12px; }

        /* Import paneli */
        .ae-import { margin-top: 16px; padding: 16px; border-radius: 12px; border: 1px solid var(--ae-blue-line); background: var(--ae-blue-bg); }
        .ae-import form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin: 0; }
        .ae-import label { display: block; margin-bottom: 4px; font-size: 13px; font-weight: 600; color: var(--ae-ink); }
        .ae-import input[type=file] { display: block; width: 100%; padding: 6px; font-size: 13px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; }
        .ae-import p { margin: 8px 0 0; font-size: 12px; color: var(--ae-muted); }

        /* Xabarlar */
        .ae-alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; margin-bottom: 16px; border-radius: 12px; border: 1px solid; font-size: 13px; }
        .ae-alert svg { width: 20px; height: 20px; flex-shrink: 0; }
        .ae-alert--success { background: var(--ae-green-bg); border-color: var(--ae-green-line); color: #065f46; }
        .ae-alert--warning { background: var(--ae-amber-bg); border-color: var(--ae-amber-line); color: #92400e; }
        .ae-alert--error { background: var(--ae-red-bg); border-color: var(--ae-red-line); color: #9f1239; }
        .ae-alert ul { margin: 6px 0 0; padding-left: 18px; }
        .ae-alert--scroll { display: block; max-height: 240px; overflow-y: auto; }

        /* Holat kartalari */
        .ae-stats { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 20px; }
        @media (min-width: 640px) { .ae-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1200px) { .ae-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .ae-stat { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 16px; border-radius: 12px; background: #fff;
            border: 1px solid var(--ae-line); box-shadow: 0 1px 2px rgba(15,23,42,.05); text-decoration: none !important; color: inherit !important;
            text-align: left; cursor: pointer; font: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; overflow: hidden; }
        .ae-stat:hover { box-shadow: 0 6px 16px rgba(15,23,42,.08); transform: translateY(-1px); }
        .ae-stat:focus-visible { outline: 2px solid var(--ae-blue); outline-offset: 2px; }
        .ae-stat-top { display: flex; align-items: center; gap: 12px; }
        .ae-stat-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0; }
        .ae-stat-icon svg { width: 22px; height: 22px; }
        .ae-stat-label { font-size: 12px; font-weight: 600; letter-spacing: .02em; color: var(--ae-muted); }
        .ae-stat-value { font-size: 26px; line-height: 1.1; font-weight: 700; color: var(--ae-ink); font-variant-numeric: tabular-nums; }
        .ae-stat-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--ae-muted); }
        .ae-stat-bar { height: 6px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
        .ae-stat-bar span { display: block; height: 100%; border-radius: 999px; }
        .ae-stat--amber .ae-stat-icon { background: var(--ae-amber-bg); color: var(--ae-amber); }
        .ae-stat--amber .ae-stat-bar span { background: #f59e0b; }
        .ae-stat--amber.is-active { border-color: #f59e0b; box-shadow: 0 0 0 3px var(--ae-amber-line); }
        .ae-stat--green .ae-stat-icon { background: var(--ae-green-bg); color: var(--ae-green); }
        .ae-stat--green .ae-stat-bar span { background: #10b981; }
        .ae-stat--green.is-active { border-color: #10b981; box-shadow: 0 0 0 3px var(--ae-green-line); }
        .ae-stat--red .ae-stat-icon { background: var(--ae-red-bg); color: var(--ae-red); }
        .ae-stat--red .ae-stat-bar span { background: #f43f5e; }
        .ae-stat--red.is-active { border-color: #f43f5e; box-shadow: 0 0 0 3px var(--ae-red-line); }
        .ae-stat--navy { background: var(--ae-navy); border-color: var(--ae-navy); }
        .ae-stat--navy .ae-stat-icon { background: rgba(255,255,255,.14); color: #fff; }
        .ae-stat--navy .ae-stat-label, .ae-stat--navy .ae-stat-foot { color: #c7d2fe; }
        .ae-stat--navy .ae-stat-value { color: #fff; }
        .ae-stat--navy:hover { background: var(--ae-navy-2); }
        .ae-stat-chip { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }

        /* Kartalar (ariza sahifasidagidek to'q ko'k sarlavha) */
        .ae-card { margin-bottom: 20px; border-radius: 12px; background: #fff; border: 1px solid var(--ae-line); box-shadow: 0 1px 2px rgba(15,23,42,.05); overflow: hidden; }
        .ae-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; background: var(--ae-navy); color: #fff; }
        .ae-card-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; font-weight: 700; color: #fff; }
        .ae-card-title svg { width: 18px; height: 18px; }
        .ae-card-meta { font-size: 12px; color: #c7d2fe; }
        .ae-card-meta b { color: #fff; }

        /* Filtrlar */
        .ae-filter { padding: 16px; }
        .ae-filter form { display: grid; grid-template-columns: 1fr; gap: 12px; margin: 0; }
        @media (min-width: 640px) { .ae-filter form { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1200px) { .ae-filter form { grid-template-columns: 1fr 1.4fr 1fr 1.6fr 1fr 1fr; } }
        .ae-field label { display: block; margin-bottom: 4px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--ae-muted); }
        .ae-field input, .ae-field select { display: block; width: 100%; height: 36px; padding: 0 10px; font-size: 13px; color: var(--ae-ink);
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: none; }
        .ae-field input:focus, .ae-field select:focus { outline: none; border-color: var(--ae-blue); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .ae-filter-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .ae-filter-note { margin: 0 16px 16px; padding: 8px 12px; border-radius: 8px; background: var(--ae-blue-bg); border: 1px solid var(--ae-blue-line);
            display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; color: #1e40af; }
        .ae-filter-note a { font-weight: 600; color: var(--ae-blue); white-space: nowrap; }

        /* Jadval */
        .ae-table-scroll { overflow-x: auto; }
        .ae-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .ae-table thead th { position: sticky; top: 0; z-index: 1; padding: 10px 12px; text-align: left; white-space: nowrap; background: var(--ae-soft);
            border-bottom: 1px solid var(--ae-line); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ae-muted); }
        .ae-table tbody td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .ae-table tbody tr { transition: background-color .12s; }
        .ae-table tbody tr:hover { background: #f8faff; }
        .ae-table tbody tr:last-child td { border-bottom: 0; }
        .ae-row--pending td { background: #fffdf5; }
        .ae-row--pending td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .ae-num { color: #94a3b8; font-weight: 600; font-variant-numeric: tabular-nums; }
        .ae-name { font-weight: 600; color: var(--ae-ink); white-space: nowrap; }
        .ae-meta { margin-top: 2px; font-size: 12px; color: var(--ae-muted); white-space: nowrap; }
        .ae-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; }
        .ae-strong { font-weight: 600; color: var(--ae-ink); white-space: nowrap; }
        .ae-reason { display: block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ae-text); }
        .ae-dash { color: #cbd5e1; }

        .ae-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; border: 1px solid; }
        .ae-pill::before { content: ""; width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
        .ae-pill--pending { background: var(--ae-amber-bg); color: #b45309; border-color: var(--ae-amber-line); }
        .ae-pill--approved { background: var(--ae-green-bg); color: #047857; border-color: var(--ae-green-line); }
        .ae-pill--rejected { background: var(--ae-red-bg); color: #be123c; border-color: var(--ae-red-line); }

        .ae-file { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600;
            white-space: nowrap; text-decoration: none !important; border: 1px solid; transition: background-color .15s; }
        .ae-file svg { width: 14px; height: 14px; }
        .ae-file--doc { color: var(--ae-blue) !important; background: var(--ae-blue-bg); border-color: var(--ae-blue-line); }
        .ae-file--doc:hover { background: #dbeafe; }
        .ae-file--pdf { color: var(--ae-green) !important; background: var(--ae-green-bg); border-color: var(--ae-green-line); }
        .ae-file--pdf:hover { background: #d1fae5; }

        .ae-actions { display: flex; align-items: center; gap: 6px; }
        .ae-actions form { margin: 0; }
        .ae-act { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;
            white-space: nowrap; text-decoration: none !important; border: 1px solid transparent; background: none; transition: background-color .15s; }
        .ae-act--view { background: var(--ae-navy); color: #fff !important; }
        .ae-act--view:hover { background: var(--ae-navy-2); }
        .ae-act--danger { color: var(--ae-red); border-color: var(--ae-red-line); }
        .ae-act--danger:hover { background: var(--ae-red-bg); }
        .ae-act--warn { color: #b45309; border-color: var(--ae-amber-line); }
        .ae-act--warn:hover { background: var(--ae-amber-bg); }

        .ae-empty { padding: 48px 16px; text-align: center; color: var(--ae-muted); }
        .ae-empty svg { width: 40px; height: 40px; margin: 0 auto 8px; color: #cbd5e1; }
        .ae-pager { padding: 12px 16px; border-top: 1px solid var(--ae-line); display: flex; justify-content: center; }

        @media (prefers-reduced-motion: reduce) { .ae-stat, .ae-btn, .ae-table tbody tr { transition: none; } .ae-stat:hover { transform: none; } }
    </style>

    <div class="p-4 sm:ml-64">
        <div class="mt-14 ae-page">
            @php
                $statsTotal = $stats['pending'] + $stats['approved'] + $stats['rejected'];
                $pct = fn ($n) => $statsTotal > 0 ? round($n / $statsTotal * 100) : 0;
                $activeStatus = request('status');
            @endphp

            {{-- Sarlavha va amallar --}}
            <div x-data="{ showImport: {{ session('import_errors') ? 'true' : 'false' }} }">
                <div class="ae-head">
                    <div>
                        <h1 class="ae-title">Sababli dars qoldirish arizalari</h1>
                        <p class="ae-sub">
                            Jami <b class="ae-tnum">{{ $statsTotal }}</b> ta ariza
                            @if($stats['pending'] > 0)
                                · <span class="ae-sub-pending ae-tnum">{{ $stats['pending'] }}</span> tasi ko'rib chiqilishini kutmoqda
                            @endif
                        </p>
                    </div>
                    <div class="ae-btns">
                        <button type="button" @click="showImport = !showImport" class="ae-btn ae-btn--primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Exceldan import
                        </button>
                        <a href="{{ route('admin.absence-excuses.import-template') }}" class="ae-btn ae-btn--ghost">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Shablon yuklab olish
                        </a>
                    </div>
                </div>

                <div x-show="showImport" x-cloak x-transition class="ae-import" style="margin-top: 0; margin-bottom: 20px;">
                    <form action="{{ route('admin.absence-excuses.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div style="flex: 1 1 280px;">
                            <label for="excuse-import-file">Excel fayl (.xlsx, .xls, .csv)</label>
                            <input id="excuse-import-file" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <button type="submit" class="ae-btn ae-btn--success">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Import qilish
                        </button>
                    </form>
                    <p>Avval shablonni yuklab oling, ustunlarni to'ldirib, shu yerga yuklang. Dublikat yozuvlar avtomatik o'tkazib yuboriladi.</p>
                </div>
            </div>

            {{-- Xabarlar --}}
            @if(session('success'))
                <div class="ae-alert ae-alert--success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('warning'))
                <div class="ae-alert ae-alert--warning">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="ae-alert ae-alert--error">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if(session('import_errors'))
                <div class="ae-alert ae-alert--error ae-alert--scroll">
                    <strong>Import xatoliklari:</strong>
                    <ul>
                        @foreach(session('import_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Holat kartalari + xodimlar statistikasi --}}
            <div class="ae-stats">
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'pending']) }}" class="ae-stat ae-stat--amber {{ $activeStatus === 'pending' ? 'is-active' : '' }}">
                    <div class="ae-stat-top">
                        <span class="ae-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <div>
                            <div class="ae-stat-label">Kutilmoqda</div>
                            <div class="ae-stat-value">{{ $stats['pending'] }}</div>
                        </div>
                    </div>
                    <div class="ae-stat-bar"><span style="width: {{ $pct($stats['pending']) }}%"></span></div>
                    <div class="ae-stat-foot"><span>Ko'rib chiqilmagan</span><span class="ae-tnum">{{ $pct($stats['pending']) }}%</span></div>
                </a>
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'approved']) }}" class="ae-stat ae-stat--green {{ $activeStatus === 'approved' ? 'is-active' : '' }}">
                    <div class="ae-stat-top">
                        <span class="ae-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <div>
                            <div class="ae-stat-label">Tasdiqlangan</div>
                            <div class="ae-stat-value">{{ $stats['approved'] }}</div>
                        </div>
                    </div>
                    <div class="ae-stat-bar"><span style="width: {{ $pct($stats['approved']) }}%"></span></div>
                    <div class="ae-stat-foot"><span>Barcha arizalardan</span><span class="ae-tnum">{{ $pct($stats['approved']) }}%</span></div>
                </a>
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'rejected']) }}" class="ae-stat ae-stat--red {{ $activeStatus === 'rejected' ? 'is-active' : '' }}">
                    <div class="ae-stat-top">
                        <span class="ae-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <div>
                            <div class="ae-stat-label">Rad etilgan</div>
                            <div class="ae-stat-value">{{ $stats['rejected'] }}</div>
                        </div>
                    </div>
                    <div class="ae-stat-bar"><span style="width: {{ $pct($stats['rejected']) }}%"></span></div>
                    <div class="ae-stat-foot"><span>Barcha arizalardan</span><span class="ae-tnum">{{ $pct($stats['rejected']) }}%</span></div>
                </a>
                <button type="button" onclick="document.getElementById('reviewerStatsModal').classList.remove('hidden')" class="ae-stat ae-stat--navy">
                    <div class="ae-stat-top">
                        <span class="ae-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>
                        <div>
                            <div class="ae-stat-label">Xodimlar statistikasi</div>
                            <div class="ae-stat-value">{{ $reviewerStats->count() }} <span style="font-size: 14px; font-weight: 600; color: #c7d2fe;">xodim</span></div>
                        </div>
                    </div>
                    <div class="ae-stat-foot" style="margin-top: auto;"><span>Kim nechta ko'rib chiqqan</span><span>Ochish →</span></div>
                </button>
            </div>

            {{-- Reviewer Statistika Modal --}}
            <div id="reviewerStatsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" x-data="{ openReviewer: null, openName: '', filterStatus: null }">
                <div class="flex items-center justify-center min-h-screen px-4 py-8">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('reviewerStatsModal').classList.add('hidden')"></div>
                    <div class="relative bg-white rounded-lg shadow-xl w-full z-10 max-h-[90vh] flex flex-col mx-4" @click.stop>
                        <div class="flex items-center justify-between p-4 border-b bg-blue-50 rounded-t-lg flex-shrink-0">
                            <h3 class="text-lg font-semibold text-blue-800">Xodimlar statistikasi</h3>
                            <button onclick="document.getElementById('reviewerStatsModal').classList.add('hidden')"
                                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="flex-1 overflow-y-auto">
                            @if($reviewerStats->isEmpty())
                                <p class="text-gray-500 text-center py-6">Hali hech qanday ariza ko'rib chiqilmagan.</p>
                            @else
                                <div class="p-4">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Xodim</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tasdiqlagan</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rad etgan</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Jami</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach($reviewerStats as $i => $reviewer)
                                                    <tr class="cursor-pointer transition hover:bg-gray-50"
                                                        @click.stop="openReviewer = {{ $reviewer->reviewed_by }}; openName = '{{ addslashes($reviewer->reviewed_by_name) }}'; filterStatus = null">
                                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $reviewer->reviewed_by_name }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ $reviewer->approved_count }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">{{ $reviewer->rejected_count }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $reviewer->total_count }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Ikkinchi modal: Reviewer arizalari --}}
                <div x-show="openReviewer !== null"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 overflow-y-auto" style="z-index: 60;"
                     @keydown.escape.window="openReviewer = null">
                    <div class="flex items-center justify-center min-h-screen px-4 py-4">
                        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" @click="openReviewer = null; filterStatus = null"></div>
                        <div class="relative bg-white rounded-lg shadow-2xl w-full z-10 flex flex-col mx-4" style="height: 600px;">
                            <div class="flex items-center justify-between p-4 border-b bg-indigo-50 rounded-t-lg flex-shrink-0">
                                <h3 class="text-lg font-semibold text-indigo-800" x-text="openName + ' — arizalari'"></h3>
                                <div class="flex items-center gap-3">
                                    <div class="flex gap-2">
                                        <button @click="filterStatus = null"
                                                :class="filterStatus === null ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Barchasi
                                        </button>
                                        <button @click="filterStatus = 'approved'"
                                                :class="filterStatus === 'approved' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 hover:bg-green-50 border-green-200'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Tasdiqlangan
                                        </button>
                                        <button @click="filterStatus = 'rejected'"
                                                :class="filterStatus === 'rejected' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-red-700 hover:bg-red-50 border-red-200'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Rad etilgan
                                        </button>
                                    </div>
                                    <button @click="openReviewer = null; filterStatus = null"
                                            class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-2">&times;</button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">ID</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Talaba</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Guruh</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Sabab</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Sanalar</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Holat</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Ko'rib chiqilgan</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($reviewerStats as $reviewer)
                                            @foreach(($reviewerExcuses[$reviewer->reviewed_by] ?? collect()) as $exc)
                                                <tr x-show="openReviewer === {{ $reviewer->reviewed_by }} && (filterStatus === null || filterStatus === '{{ $exc->status }}')"
                                                    class="hover:bg-blue-50 transition">
                                                    <td class="px-4 py-3 text-gray-500">{{ $exc->id }}</td>
                                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $exc->student_full_name }}</td>
                                                    <td class="px-4 py-3 text-gray-500">{{ $exc->group_name }}</td>
                                                    <td class="px-4 py-3 text-gray-700">{{ $exc->reason_label }}</td>
                                                    <td class="px-4 py-3 text-gray-500">{{ $exc->start_date->format('d.m') }}-{{ $exc->end_date->format('d.m.Y') }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        @if($exc->status === 'approved')
                                                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold text-xs">Tasdiqlangan</span>
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold text-xs">Rad etilgan</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-500">{{ $exc->reviewed_at ? $exc->reviewed_at->format('d.m.Y H:i') : '-' }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        <div class="flex items-center justify-center gap-2">
                                                            <a href="{{ route('admin.absence-excuses.show', $exc->id) }}"
                                                               class="text-indigo-600 hover:text-indigo-800 font-medium">Ko'rish</a>
                                                            @if(\App\Models\Setting::get('feature_absence_excuse_delete', '0') === '1')
                                                            <form method="POST" action="{{ route('admin.absence-excuses.destroy', $exc->id) }}" onsubmit="return confirm('Haqiqatan o\'chirmoqchimisiz? Talabadan ham o\'chadi.')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-red-500 hover:text-red-700 font-medium">O'chirish</button>
                                                            </form>
                                                            @if($exc->status === 'approved')
                                                            <form method="POST" action="{{ route('admin.absence-excuses.reset-to-pending', $exc->id) }}" onsubmit="return confirm('Ariza pending holatiga qaytariladi va PDF o\'chiriladi. Davom etilsinmi?')">
                                                                @csrf
                                                                <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium">Qaytarish</button>
                                                            </form>
                                                            @endif
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtrlar --}}
            <div class="ae-card">
                <div class="ae-card-head">
                    <h2 class="ae-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Qidirish va filtr
                    </h2>
                </div>
                <div class="ae-filter">
                    <form method="GET" action="{{ route('admin.absence-excuses.index') }}">
                        <div class="ae-field">
                            <label for="ae-student-id">Student ID</label>
                            <input id="ae-student-id" type="number" name="student_id" value="{{ request('student_id') }}" placeholder="368…">
                        </div>
                        <div class="ae-field">
                            <label for="ae-search">Ism, HEMIS ID yoki guruh</label>
                            <input id="ae-search" type="text" name="search" value="{{ request('search') }}" placeholder="Masalan: d1/d22-01a">
                        </div>
                        <div class="ae-field">
                            <label for="ae-status">Holat</label>
                            <select id="ae-status" name="status">
                                <option value="">Barcha holatlar</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                            </select>
                        </div>
                        <div class="ae-field">
                            <label for="ae-reason">Sabab</label>
                            <select id="ae-reason" name="reason">
                                <option value="">Barcha sabablar</option>
                                @foreach($reasons as $key => $label)
                                    <option value="{{ $key }}" {{ request('reason') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ae-field">
                            <label for="ae-date-from">Sabab davri: sanadan</label>
                            <input id="ae-date-from" type="date" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="ae-field">
                            <label for="ae-date-to">Sabab davri: sanagacha</label>
                            <input id="ae-date-to" type="date" name="date_to" value="{{ request('date_to') }}">
                        </div>
                        <div class="ae-filter-actions">
                            <button type="submit" class="ae-btn ae-btn--primary ae-btn--sm">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                                Qidirish
                            </button>
                            <a href="{{ route('admin.absence-excuses.index') }}" class="ae-btn ae-btn--muted ae-btn--sm">Tozalash</a>
                            @if(in_array(request('status'), ['pending', 'approved', 'rejected'], true))
                                <a href="{{ route('admin.absence-excuses.export-status', request('status')) }}" class="ae-btn ae-btn--success ae-btn--sm">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    Excel yuklash
                                </a>
                            @endif
                            @if(request('status') === 'approved')
                                <a href="{{ route('admin.absence-excuses.export-grades', request()->only(['date_from', 'date_to', 'search', 'reason', 'student_id', 'reviewed_by'])) }}"
                                   class="ae-btn ae-btn--primary ae-btn--sm" title="Har bir ariza × fan bo'yicha: HEMIS'da va Markda (NB ustiga) qo'yilgan baholar">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    Baholar Excel (fanlar bo'yicha)
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
                @if(request('reviewed_by'))
                    <div class="ae-filter-note">
                        <span>
                            <strong>{{ $reviewerStats->firstWhere('reviewed_by', request('reviewed_by'))?->reviewed_by_name ?? 'Noma\'lum' }}</strong> tomonidan ko'rib chiqilgan arizalar
                            @if(request('status') == 'approved') (tasdiqlangan) @elseif(request('status') == 'rejected') (rad etilgan) @endif
                        </span>
                        <a href="{{ route('admin.absence-excuses.index', request()->except(['reviewed_by'])) }}">Tozalash ×</a>
                    </div>
                @endif
            </div>

            {{-- Jadval --}}
            <div class="ae-card">
                <div class="ae-card-head">
                    <h2 class="ae-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Arizalar ro'yxati
                    </h2>
                    @if(!$excuses->isEmpty())
                        <span class="ae-card-meta ae-tnum"><b>{{ $excuses->firstItem() }}–{{ $excuses->lastItem() }}</b> / {{ $excuses->total() }}</span>
                    @endif
                </div>

                @if($excuses->isEmpty())
                    <div class="ae-empty">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>Bu filtr bo'yicha ariza topilmadi.</div>
                    </div>
                @else
                    <div class="ae-table-scroll">
                        <table class="ae-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Talaba</th>
                                    <th>Fakultet / yo'nalish</th>
                                    <th>Guruh</th>
                                    <th>Sabab va davr</th>
                                    <th>Asos hujjat</th>
                                    <th>Holat</th>
                                    <th>Natija</th>
                                    <th>Ko'rib chiqqan</th>
                                    <th>Ariza vaqti</th>
                                    <th>Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($excuses as $excuse)
                                    @php
                                        $pillClass = match ($excuse->status) {
                                            'approved' => 'ae-pill--approved',
                                            'rejected' => 'ae-pill--rejected',
                                            default => 'ae-pill--pending',
                                        };
                                    @endphp
                                    <tr class="{{ $excuse->isPending() ? 'ae-row--pending' : '' }}">
                                        <td class="ae-num">{{ $excuses->firstItem() + $loop->index }}</td>
                                        <td>
                                            <div class="ae-name">{{ $excuse->student_full_name }}</div>
                                            <div class="ae-meta ae-mono">{{ $excuse->student_hemis_id }}</div>
                                        </td>
                                        <td>
                                            <div class="ae-strong">{{ $excuse->department_name ?? $excuse->student?->department_name ?? '—' }}</div>
                                            <div class="ae-meta">{{ $excuse->student?->specialty_name ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <div class="ae-strong">{{ $excuse->group_name }}</div>
                                            <div class="ae-meta">{{ $excuse->student?->semester_name ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <span class="ae-reason" title="{{ $excuse->reason_label }}">{{ $excuse->reason_label }}</span>
                                            <div class="ae-meta ae-tnum">
                                                {{ $excuse->start_date?->format('d.m.Y') }} – {{ $excuse->end_date?->format('d.m.Y') }}
                                            </div>
                                        </td>
                                        <td>
                                            @if($excuse->file_path)
                                                @php
                                                    $docUrl = Str::startsWith($excuse->file_path, ['http://', 'https://'])
                                                        ? $excuse->file_path
                                                        : route('admin.absence-excuses.download', $excuse->id);
                                                @endphp
                                                <a href="{{ $docUrl }}" target="_blank" class="ae-file ae-file--doc" title="{{ $excuse->file_original_name }}">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Yuklab olish
                                                </a>
                                            @else
                                                <span class="ae-dash">—</span>
                                            @endif
                                        </td>
                                        <td><span class="ae-pill {{ $pillClass }}">{{ $excuse->status_label }}</span></td>
                                        <td>
                                            @if($excuse->isApproved() && $excuse->approved_pdf_path)
                                                @php
                                                    $pdfUrl = Str::startsWith($excuse->approved_pdf_path, ['http://', 'https://'])
                                                        ? $excuse->approved_pdf_path
                                                        : route('admin.absence-excuses.download-pdf', $excuse->id);
                                                @endphp
                                                <a href="{{ $pdfUrl }}" target="_blank" class="ae-file ae-file--pdf">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    PDF
                                                </a>
                                            @else
                                                <span class="ae-dash">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($excuse->reviewed_by_name)
                                                <div class="ae-strong">{{ $excuse->reviewed_by_name }}</div>
                                                <div class="ae-meta ae-tnum">{{ $excuse->reviewed_at?->format('d.m.Y H:i') }}</div>
                                            @else
                                                <span class="ae-dash">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="ae-strong ae-tnum" style="font-weight: 500;">{{ $excuse->created_at?->format('d.m.Y') }}</div>
                                            <div class="ae-meta ae-tnum">{{ $excuse->created_at?->format('H:i') }}</div>
                                        </td>
                                        <td>
                                            <div class="ae-actions">
                                                <a href="{{ route('admin.absence-excuses.show', $excuse->id) }}" class="ae-act ae-act--view">Ko'rish</a>
                                                @if(\App\Models\Setting::get('feature_absence_excuse_delete', '0') === '1')
                                                    <form method="POST" action="{{ route('admin.absence-excuses.destroy', $excuse->id) }}" onsubmit="return confirm('Haqiqatan o\'chirmoqchimisiz? Talabadan ham o\'chadi.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ae-act ae-act--danger">O'chirish</button>
                                                    </form>
                                                    @if($excuse->status === 'approved')
                                                        <form method="POST" action="{{ route('admin.absence-excuses.reset-to-pending', $excuse->id) }}" onsubmit="return confirm('Ariza pending holatiga qaytariladi va PDF o\'chiriladi. Davom etilsinmi?')">
                                                            @csrf
                                                            <button type="submit" class="ae-act ae-act--warn">Qaytarish</button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="ae-pager">
                        {{ $excuses->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

</x-app-layout>
