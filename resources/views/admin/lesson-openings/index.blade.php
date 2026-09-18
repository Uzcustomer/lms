<x-app-layout>
    @php
        $tiles = [
            'pending' => ['Kutilmoqda', 'amber', 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z'],
            'active' => ['Ochilgan', 'green', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            'expired' => ['Muddati tugagan', 'slate', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            'rejected' => ['Rad etilgan', 'red', 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ];
        $statusLabels = [
            'pending' => ['Kutilmoqda', 'pending'],
            'active' => ['Ochilgan', 'active'],
            'expired' => ['Muddati tugagan', 'expired'],
            'closed' => ['Yopilgan', 'expired'],
            'rejected' => ['Rad etilgan', 'rejected'],
        ];
        $showActions = $canReview && in_array($status, ['pending', 'all'], true);
        // Hozir tasdiqlansa o'qituvchi qachongacha baho qo'ya oladi
        $approveDeadline = \Carbon\Carbon::now('Asia/Tashkent')->addDays($openingDays)->endOfDay()->format('d.m.Y H:i');

        $ago = function ($from) {
            if (!$from) {
                return '';
            }
            $minutes = (int) abs($from->diffInMinutes(now()));
            if ($minutes < 60) {
                return max($minutes, 1) . ' daqiqa';
            }
            if ($minutes < 1440) {
                return intdiv($minutes, 60) . ' soat';
            }
            return intdiv($minutes, 1440) . ' kun';
        };
        $initials = function ($name) {
            $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
            $letters = array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
            return mb_strtoupper(implode('', $letters)) ?: '?';
        };
    @endphp

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Dars ochish so'rovlari</h2>
    </x-slot>

    <style>
        .lo-page { color:#334155; }
        .lo-alert { display:flex; align-items:center; gap:10px; margin-bottom:12px; padding:11px 14px; border:1px solid #a7f3d0; border-radius:10px; background:#ecfdf5; color:#065f46; font-size:13px; font-weight:600; }
        .lo-alert svg { width:18px; height:18px; flex:0 0 18px; }
        .lo-alert-error { border-color:#fecaca; background:#fef2f2; color:#991b1b; }

        .lo-panel { overflow:hidden; border:1px solid #dbe4ef; border-radius:12px; background:#fff; box-shadow:0 4px 16px rgba(15,23,42,.06); }
        .lo-panel + .lo-panel { margin-top:14px; }

        .lo-hero { display:flex; align-items:center; justify-content:space-between; gap:16px; min-height:72px; padding:14px 18px; color:#fff; background:linear-gradient(135deg,#1f4f91,#2b67ae 58%,#3b82c4); }
        .lo-hero-title { display:flex; align-items:center; gap:12px; }
        .lo-hero-title h1 { margin:0; font-size:18px; line-height:1.25; font-weight:800; color:#fff; }
        .lo-hero-title p { margin:3px 0 0; font-size:12px; color:#dbeafe; }
        .lo-hero-icon { width:42px; height:42px; flex:0 0 42px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.24); border-radius:11px; background:rgba(255,255,255,.13); }
        .lo-hero-icon svg { width:22px; height:22px; }
        .lo-hero-chip { display:inline-flex; align-items:center; gap:7px; flex:none; padding:8px 12px; border:1px solid rgba(255,255,255,.28); border-radius:10px; background:rgba(255,255,255,.12); font-size:12px; font-weight:600; color:#fff; }
        .lo-hero-chip svg { width:16px; height:16px; }
        .lo-hero-chip b { font-weight:800; }

        .lo-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)) auto; gap:10px; padding:12px; background:#f8fafc; }
        .lo-stat { display:flex; align-items:center; gap:11px; min-height:68px; padding:10px 13px; border:1px solid #e2e8f0; border-top-width:3px; border-radius:10px; background:#fff; text-decoration:none; box-shadow:0 2px 7px rgba(15,23,42,.04); transition:transform .12s, box-shadow .12s; }
        .lo-stat:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.08); }
        .lo-stat-icon { width:38px; height:38px; flex:0 0 38px; display:flex; align-items:center; justify-content:center; border-radius:10px; }
        .lo-stat-icon svg { width:20px; height:20px; }
        .lo-stat span { display:block; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#64748b; }
        .lo-stat strong { display:block; margin-top:3px; font-size:21px; line-height:1; font-weight:800; color:#172554; }
        .lo-stat-amber { border-top-color:#f59e0b; } .lo-stat-amber .lo-stat-icon { background:#fffbeb; color:#d97706; }
        .lo-stat-green { border-top-color:#10b981; } .lo-stat-green .lo-stat-icon { background:#ecfdf5; color:#059669; }
        .lo-stat-slate { border-top-color:#94a3b8; } .lo-stat-slate .lo-stat-icon { background:#f1f5f9; color:#475569; }
        .lo-stat-red { border-top-color:#ef4444; } .lo-stat-red .lo-stat-icon { background:#fef2f2; color:#dc2626; }
        .lo-stat.is-current { border-color:#2b5ea7; box-shadow:0 0 0 2px rgba(43,94,167,.18); }
        .lo-stat.is-current.lo-stat-amber { border-top-color:#f59e0b; }
        .lo-stat.is-current.lo-stat-green { border-top-color:#10b981; }
        .lo-stat.is-current.lo-stat-slate { border-top-color:#94a3b8; }
        .lo-stat.is-current.lo-stat-red { border-top-color:#ef4444; }
        .lo-stat-all { justify-content:center; min-width:120px; border-top-color:#2b5ea7; }
        .lo-stat-all span, .lo-stat-all strong { text-align:center; }
        .lo-hot { display:inline-block; width:8px; height:8px; margin-left:6px; border-radius:50%; background:#f59e0b; vertical-align:middle; animation:lo-pulse 1.8s ease-in-out infinite; }
        @keyframes lo-pulse { 0%,100% { box-shadow:0 0 0 0 rgba(245,158,11,.55); } 50% { box-shadow:0 0 0 5px rgba(245,158,11,0); } }

        .lo-list-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; border-bottom:1px solid #e2e8f0; }
        .lo-list-head h2 { margin:0; font-size:14px; font-weight:800; color:#1e293b; }
        .lo-list-head small { font-size:12px; color:#64748b; }

        .lo-table-scroll { overflow-x:auto; }
        .lo-table { width:100%; border-collapse:separate; border-spacing:0; font-size:12.5px; }
        .lo-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
        .lo-table th { padding:11px 12px; text-align:left; border-bottom:2px solid #cbd5e1; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#334155; white-space:nowrap; }
        .lo-table td { padding:12px; vertical-align:middle; border-bottom:1px solid #eef2f7; line-height:1.4; color:#475569; }
        .lo-table tbody tr:nth-child(even) { background:#f8fafc; }
        .lo-table tbody tr:nth-child(odd) { background:#fff; }
        .lo-table tbody tr:hover { background:#eff6ff; box-shadow:inset 4px 0 0 #2b5ea7; }
        .lo-table tbody tr.is-pending { box-shadow:inset 4px 0 0 #f59e0b; }
        .lo-table tbody tr.is-pending:hover { box-shadow:inset 4px 0 0 #d97706; }
        .lo-num { width:36px; font-weight:700; color:#94a3b8; }

        .lo-group { display:inline-flex; align-items:center; gap:5px; font-size:13.5px; font-weight:800; color:#1d4ed8; text-decoration:none; }
        .lo-group svg { width:13px; height:13px; opacity:.55; }
        .lo-group:hover { text-decoration:underline; }
        .lo-subject { display:block; margin-top:3px; font-weight:600; color:#334155; }
        .lo-chip { display:inline-block; margin-top:5px; padding:1px 8px; border-radius:6px; background:#eef2ff; color:#4338ca; font-size:10.5px; font-weight:700; }

        .lo-date { display:flex; align-items:center; gap:10px; white-space:nowrap; }
        .lo-cal { width:42px; flex:0 0 42px; overflow:hidden; border:1px solid #dbe4ef; border-radius:9px; background:#fff; text-align:center; box-shadow:0 1px 3px rgba(15,23,42,.06); }
        .lo-cal i { display:block; padding:1px 0; background:#2b67ae; color:#fff; font-style:normal; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
        .lo-cal b { display:block; padding:2px 0 3px; font-size:17px; line-height:1.1; font-weight:800; color:#172554; }
        .lo-date-text strong { display:block; font-weight:700; color:#1e293b; }
        .lo-date-text small { font-size:11.5px; color:#94a3b8; }

        .lo-person { display:flex; align-items:center; gap:10px; min-width:210px; }
        .lo-avatar { width:34px; height:34px; flex:0 0 34px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1e40af; font-size:12px; font-weight:800; }
        .lo-person strong { display:block; font-size:12.5px; font-weight:700; color:#1e293b; }
        .lo-person small { display:block; margin-top:1px; font-size:11.5px; color:#94a3b8; }
        .lo-quote { margin-top:7px; padding:6px 9px; border-left:3px solid #93c5fd; border-radius:0 7px 7px 0; background:#f1f5ff; color:#334155; font-size:11.5px; font-style:italic; max-width:280px; }

        .lo-file { display:inline-flex; align-items:center; gap:9px; max-width:240px; padding:6px 10px 6px 6px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; text-decoration:none; transition:border-color .12s, box-shadow .12s; }
        .lo-file:hover { border-color:#93c5fd; box-shadow:0 3px 10px rgba(37,99,235,.12); }
        .lo-file-ext { width:32px; height:32px; flex:0 0 32px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:9.5px; font-weight:800; letter-spacing:.02em; }
        .lo-file-ext.is-pdf { background:#fee2e2; color:#b91c1c; }
        .lo-file-ext.is-img { background:#ede9fe; color:#6d28d9; }
        .lo-file-ext.is-doc { background:#dbeafe; color:#1d4ed8; }
        .lo-file-ext.is-other { background:#f1f5f9; color:#475569; }
        .lo-file-name { overflow:hidden; font-size:12px; font-weight:700; color:#1e293b; text-overflow:ellipsis; white-space:nowrap; }
        .lo-file-dl { display:block; font-size:10.5px; font-weight:600; color:#2563eb; }
        .lo-file-text { min-width:0; }

        .lo-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
        .lo-status::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; }
        .lo-status-pending { background:#fef3c7; color:#b45309; }
        .lo-status-active { background:#d1fae5; color:#047857; }
        .lo-status-expired { background:#e2e8f0; color:#475569; }
        .lo-status-rejected { background:#fee2e2; color:#b91c1c; }
        .lo-meta { display:block; margin-top:5px; font-size:11.5px; color:#64748b; white-space:nowrap; }
        .lo-meta b { font-weight:700; color:#334155; }
        .lo-reason { max-width:260px; margin-top:6px; padding:7px 9px; border:1px solid #fecaca; border-radius:8px; background:#fff1f2; color:#991b1b; font-size:11.5px; line-height:1.45; }
        .lo-reason strong { font-weight:800; }

        .lo-actions { display:flex; gap:7px; white-space:nowrap; }
        .lo-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 13px; border:1px solid transparent; border-radius:9px; font-size:12.5px; font-weight:700; cursor:pointer; transition:background .12s, box-shadow .12s, transform .12s; }
        .lo-btn svg { width:15px; height:15px; }
        .lo-btn:active { transform:translateY(1px); }
        .lo-btn-approve { background:linear-gradient(135deg,#16a34a,#15803d); color:#fff; box-shadow:0 3px 10px rgba(22,163,74,.28); }
        .lo-btn-approve:hover { box-shadow:0 5px 14px rgba(22,163,74,.38); }
        .lo-btn-reject { border-color:#fecaca; background:#fff; color:#b91c1c; }
        .lo-btn-reject:hover { background:#fef2f2; }
        .lo-btn-danger { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; box-shadow:0 3px 10px rgba(220,38,38,.25); }
        .lo-btn-ghost { border-color:#e2e8f0; background:#f8fafc; color:#475569; }
        .lo-btn-ghost:hover { background:#f1f5f9; }

        .lo-empty { padding:48px 16px; text-align:center; }
        .lo-empty-icon { width:56px; height:56px; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; border-radius:16px; background:#ecfdf5; color:#059669; }
        .lo-empty-icon svg { width:28px; height:28px; }
        .lo-empty strong { display:block; font-size:14px; font-weight:800; color:#1e293b; }
        .lo-empty span { display:block; margin-top:4px; font-size:12.5px; color:#64748b; }
        .lo-pager { padding:12px 16px; border-top:1px solid #e2e8f0; }

        .lo-modal { position:fixed; inset:0; z-index:60; display:none; align-items:center; justify-content:center; padding:16px; background:rgba(15,23,42,.55); backdrop-filter:blur(2px); }
        .lo-modal.is-open { display:flex; }
        .lo-modal-box { width:100%; max-width:470px; overflow:hidden; border-radius:14px; background:#fff; box-shadow:0 24px 60px rgba(15,23,42,.3); animation:lo-pop .16s ease-out; }
        @keyframes lo-pop { from { opacity:0; transform:translateY(8px) scale(.98); } to { opacity:1; transform:none; } }
        .lo-modal-head { display:flex; align-items:center; gap:12px; padding:16px 20px; color:#fff; }
        .lo-modal-head.is-green { background:linear-gradient(135deg,#15803d,#16a34a); }
        .lo-modal-head.is-red { background:linear-gradient(135deg,#b91c1c,#dc2626); }
        .lo-modal-head .lo-hero-icon { width:38px; height:38px; flex-basis:38px; }
        .lo-modal-head h3 { margin:0; font-size:16px; font-weight:800; }
        .lo-modal-head p { margin:2px 0 0; font-size:12px; opacity:.9; }
        .lo-modal-body { padding:16px 20px 18px; }
        .lo-summary { display:grid; grid-template-columns:110px 1fr; gap:7px 10px; padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; font-size:12.5px; }
        .lo-summary dt { color:#64748b; }
        .lo-summary dd { margin:0; font-weight:700; color:#1e293b; }
        .lo-callout { display:flex; gap:9px; margin-top:12px; padding:10px 12px; border:1px solid #bbf7d0; border-radius:10px; background:#f0fdf4; color:#166534; font-size:12.5px; line-height:1.5; }
        .lo-callout svg { width:18px; height:18px; flex:0 0 18px; margin-top:1px; }
        .lo-label { display:block; margin:14px 0 6px; font-size:12px; font-weight:700; color:#334155; }
        .lo-reasons { display:flex; flex-wrap:wrap; gap:6px; }
        .lo-reason-chip { padding:5px 10px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#475569; font-size:11.5px; font-weight:600; cursor:pointer; }
        .lo-reason-chip:hover { border-color:#fca5a5; background:#fef2f2; color:#b91c1c; }
        .lo-textarea { width:100%; margin-top:8px; padding:9px 11px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px; color:#1e293b; resize:vertical; }
        .lo-textarea:focus { border-color:#f87171; outline:none; box-shadow:0 0 0 3px rgba(248,113,113,.2); }
        .lo-modal-foot { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }

        @media (max-width:1000px) {
            .lo-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .lo-stat-all { grid-column:1 / -1; min-height:48px; }
        }
        @media (max-width:760px) {
            .lo-hero { align-items:flex-start; flex-direction:column; }
            .lo-table thead { display:none; }
            .lo-table, .lo-table tbody, .lo-table tr, .lo-table td { display:block; width:100%; }
            .lo-table tbody tr { padding:10px 12px; border-bottom:1px solid #e2e8f0; }
            .lo-table td { padding:6px 0; border:0; }
            .lo-table td[data-label]::before { content:attr(data-label); display:block; margin-bottom:3px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; }
            .lo-num { display:none !important; }
            .lo-person { min-width:0; }
            .lo-actions .lo-btn { flex:1; }
        }
    </style>

    <div class="py-4 lo-page">
        <div class="max-w-full mx-auto px-4 lg:px-6">
            @if(session('success'))
                <div class="lo-alert">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error') || $errors->any())
                <div class="lo-alert lo-alert-error">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M10.3 4.3L2.9 17.1A2 2 0 004.6 20h14.8a2 2 0 001.7-2.9L13.7 4.3a2 2 0 00-3.4 0z"/></svg>
                    {{ session('error') ?: $errors->first() }}
                </div>
            @endif

            <section class="lo-panel">
                <header class="lo-hero">
                    <div class="lo-hero-title">
                        <span class="lo-hero-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zm4-5l2 2 4-4"/></svg>
                        </span>
                        <div>
                            <h1>Dars ochish so'rovlari</h1>
                            <p>Registrator o'tkazib yuborilgan darsni ochish uchun asos hujjat bilan so'rov yuboradi</p>
                        </div>
                    </div>
                    <div class="lo-hero-chip">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Tasdiqlangach baho qo'yish muddati: <b>{{ $openingDays }} kun</b>
                    </div>
                </header>

                <div class="lo-stats">
                    @foreach($tiles as $key => [$label, $color, $icon])
                        @php $count = (int) ($counts[$key] ?? 0); @endphp
                        <a href="{{ route('admin.lesson-opening-requests.index', ['status' => $key]) }}"
                           class="lo-stat lo-stat-{{ $color }} {{ $status === $key ? 'is-current' : '' }}">
                            <span class="lo-stat-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                            </span>
                            <div>
                                <span>
                                    {{ $label }}
                                    @if($key === 'pending' && $count > 0)
                                        <i class="lo-hot"></i>
                                    @endif
                                </span>
                                <strong>{{ $count }}</strong>
                            </div>
                        </a>
                    @endforeach
                    <a href="{{ route('admin.lesson-opening-requests.index', ['status' => 'all']) }}"
                       class="lo-stat lo-stat-all {{ $status === 'all' ? 'is-current' : '' }}">
                        <div>
                            <span>Barchasi</span>
                            <strong>{{ (int) $counts->sum() }}</strong>
                        </div>
                    </a>
                </div>
            </section>

            <section class="lo-panel">
                <div class="lo-list-head">
                    <h2>{{ $status === 'all' ? "Barcha so'rovlar" : ($tiles[$status][0] ?? '') }}</h2>
                    @if($openings->total() > 0)
                        <small>{{ $openings->total() }} ta so'rov</small>
                    @endif
                </div>

                @if($openings->isEmpty())
                    <div class="lo-empty">
                        <div class="lo-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        @if($status === 'pending')
                            <strong>Hammasi ko'rib chiqilgan</strong>
                            <span>Hozircha tasdiq kutayotgan so'rov yo'q.</span>
                        @else
                            <strong>So'rov topilmadi</strong>
                            <span>Bu bo'limda hali so'rov yo'q.</span>
                        @endif
                    </div>
                @else
                    <div class="lo-table-scroll">
                        <table class="lo-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Guruh / Fan</th>
                                    <th>Dars sanasi</th>
                                    <th>So'rov yubordi</th>
                                    <th>Asos hujjat</th>
                                    <th>Holat</th>
                                    @if($showActions)
                                        <th>Amal</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($openings as $opening)
                                    @php
                                        $group = $groups[$opening->group_hemis_id] ?? null;
                                        $groupName = $group->name ?? $opening->group_hemis_id;
                                        $subjectName = $subjectNames[$opening->subject_id] ?? ('Fan #' . $opening->subject_id);
                                        $semesterNumber = is_numeric($opening->semester_code) ? ((int) $opening->semester_code - 10) : 0;
                                        [$statusText, $statusClass] = $statusLabels[$opening->status] ?? [$opening->status, 'expired'];
                                        $isPending = $opening->status === 'pending';
                                        $lessonDate = $opening->lesson_date;
                                        $daysAgo = $lessonDate ? (int) abs($lessonDate->copy()->startOfDay()->diffInDays(now()->startOfDay())) : null;
                                        $ext = strtolower(pathinfo((string) $opening->file_original_name, PATHINFO_EXTENSION));
                                        $extClass = match (true) {
                                            $ext === 'pdf' => 'is-pdf',
                                            in_array($ext, ['png', 'jpg', 'jpeg', 'heic', 'webp', 'gif'], true) => 'is-img',
                                            in_array($ext, ['doc', 'docx'], true) => 'is-doc',
                                            default => 'is-other',
                                        };
                                        $months = ['', 'Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
                                    @endphp
                                    <tr class="{{ $isPending ? 'is-pending' : '' }}">
                                        <td class="lo-num">{{ $openings->firstItem() + $loop->index }}</td>
                                        <td data-label="Guruh / Fan">
                                            @if($group)
                                                <a class="lo-group" target="_blank" title="Jurnalni ochish"
                                                   href="{{ route('admin.journal.show', [$group->id, $opening->subject_id, $opening->semester_code]) }}">
                                                    {{ $groupName }}
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @else
                                                <span class="lo-group">{{ $groupName }}</span>
                                            @endif
                                            <span class="lo-subject">{{ $subjectName }}</span>
                                            @if($semesterNumber > 0)
                                                <span class="lo-chip">{{ $semesterNumber }}-semestr</span>
                                            @endif
                                        </td>
                                        <td data-label="Dars sanasi">
                                            @if($lessonDate)
                                                <div class="lo-date">
                                                    <div class="lo-cal"><i>{{ $months[(int) $lessonDate->format('n')] }}</i><b>{{ $lessonDate->format('d') }}</b></div>
                                                    <div class="lo-date-text">
                                                        <strong>{{ $lessonDate->format('d.m.Y') }}</strong>
                                                        <small>{{ $daysAgo === 0 ? 'bugun' : $daysAgo . ' kun oldin' }}</small>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="So'rov yubordi">
                                            <div class="lo-person">
                                                <span class="lo-avatar">{{ $initials($opening->opened_by_name) }}</span>
                                                <div>
                                                    <strong>{{ $opening->opened_by_name }}</strong>
                                                    <small>{{ $opening->created_at?->format('d.m.Y H:i') }}</small>
                                                </div>
                                            </div>
                                            @if($opening->request_note)
                                                <div class="lo-quote">“{{ $opening->request_note }}”</div>
                                            @endif
                                        </td>
                                        <td data-label="Asos hujjat">
                                            @if($opening->file_path)
                                                <a class="lo-file" href="{{ route('admin.journal.download-lesson-file', $opening->id) }}" title="{{ $opening->file_original_name }}">
                                                    <span class="lo-file-ext {{ $extClass }}">{{ strtoupper($ext ?: 'FAYL') }}</span>
                                                    <span class="lo-file-text">
                                                        <span class="lo-file-name">{{ $opening->file_original_name ?: 'Fayl' }}</span>
                                                        <span class="lo-file-dl">Yuklab olish</span>
                                                    </span>
                                                </a>
                                            @else
                                                <span class="lo-meta">Fayl yo'q</span>
                                            @endif
                                        </td>
                                        <td data-label="Holat">
                                            <span class="lo-status lo-status-{{ $statusClass }}">{{ $statusText }}</span>
                                            @if($isPending)
                                                <span class="lo-meta">{{ $ago($opening->created_at) }} kutmoqda</span>
                                            @elseif($opening->status === 'active' && $opening->deadline)
                                                <span class="lo-meta">Baho: <b>{{ $opening->deadline->format('d.m.Y H:i') }}</b> gacha</span>
                                            @endif
                                            @if($opening->reviewed_by_name)
                                                <span class="lo-meta">{{ $opening->reviewed_by_name }} · {{ $opening->reviewed_at?->format('d.m.Y H:i') }}</span>
                                            @endif
                                            @if($opening->status === 'rejected' && $opening->review_comment)
                                                <div class="lo-reason"><strong>Sabab:</strong> {{ $opening->review_comment }}</div>
                                            @endif
                                        </td>
                                        @if($showActions)
                                            <td data-label="{{ $isPending ? 'Amal' : '' }}">
                                                @if($isPending)
                                                    <div class="lo-actions">
                                                        <button type="button" class="lo-btn lo-btn-approve"
                                                                onclick="loOpenModal('approve', this)"
                                                                data-action="{{ route('admin.lesson-opening-requests.approve', $opening->id) }}"
                                                                data-group="{{ $groupName }}"
                                                                data-subject="{{ $subjectName }}"
                                                                data-date="{{ $lessonDate?->format('d.m.Y') }}"
                                                                data-by="{{ $opening->opened_by_name }}">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                            Tasdiqlash
                                                        </button>
                                                        <button type="button" class="lo-btn lo-btn-reject"
                                                                onclick="loOpenModal('reject', this)"
                                                                data-action="{{ route('admin.lesson-opening-requests.reject', $opening->id) }}"
                                                                data-group="{{ $groupName }}"
                                                                data-subject="{{ $subjectName }}"
                                                                data-date="{{ $lessonDate?->format('d.m.Y') }}"
                                                                data-by="{{ $opening->opened_by_name }}">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            Rad etish
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($openings->hasPages())
                        <div class="lo-pager">{{ $openings->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </div>

    @if($canReview)
        {{-- Tasdiqlash oynasi --}}
        <div class="lo-modal" id="loApproveModal" onclick="if (event.target === this) loCloseModals()">
            <div class="lo-modal-box">
                <div class="lo-modal-head is-green">
                    <span class="lo-hero-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <h3>Darsni ochish</h3>
                        <p>So'rovni tasdiqlaysizmi?</p>
                    </div>
                </div>
                <form method="POST" id="loApproveForm" class="lo-modal-body">
                    @csrf
                    <dl class="lo-summary">
                        <dt>Guruh</dt><dd data-fill="group"></dd>
                        <dt>Fan</dt><dd data-fill="subject"></dd>
                        <dt>Dars sanasi</dt><dd data-fill="date"></dd>
                        <dt>So'rov yubordi</dt><dd data-fill="by"></dd>
                    </dl>
                    <div class="lo-callout">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>Dars ochiladi va o'qituvchi <b>{{ $approveDeadline }}</b> gacha ({{ $openingDays }} kun) baho qo'ya oladi. O'qituvchiga Telegram orqali xabar boradi.</div>
                    </div>
                    <div class="lo-modal-foot">
                        <button type="button" class="lo-btn lo-btn-ghost" onclick="loCloseModals()">Bekor qilish</button>
                        <button type="submit" class="lo-btn lo-btn-approve">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            Tasdiqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Rad etish oynasi --}}
        <div class="lo-modal" id="loRejectModal" onclick="if (event.target === this) loCloseModals()">
            <div class="lo-modal-box">
                <div class="lo-modal-head is-red">
                    <span class="lo-hero-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <h3>So'rovni rad etish</h3>
                        <p>Sabab registratorga jurnalda ko'rinadi</p>
                    </div>
                </div>
                <form method="POST" id="loRejectForm" class="lo-modal-body">
                    @csrf
                    <dl class="lo-summary">
                        <dt>Guruh</dt><dd data-fill="group"></dd>
                        <dt>Fan</dt><dd data-fill="subject"></dd>
                        <dt>Dars sanasi</dt><dd data-fill="date"></dd>
                    </dl>
                    <label class="lo-label" for="loRejectComment">Rad etish sababi <span style="color:#dc2626">*</span></label>
                    <div class="lo-reasons">
                        <button type="button" class="lo-reason-chip" onclick="loPickReason(this)">Asos hujjat yetarli emas</button>
                        <button type="button" class="lo-reason-chip" onclick="loPickReason(this)">Fayl o'qilmaydi</button>
                        <button type="button" class="lo-reason-chip" onclick="loPickReason(this)">Sababi ko'rsatilmagan</button>
                        <button type="button" class="lo-reason-chip" onclick="loPickReason(this)">Bildirgi imzolanmagan</button>
                    </div>
                    <textarea name="comment" id="loRejectComment" class="lo-textarea" rows="3" required minlength="3" maxlength="1000"
                              placeholder="Sababni yozing yoki yuqoridan tanlang"></textarea>
                    <div class="lo-modal-foot">
                        <button type="button" class="lo-btn lo-btn-ghost" onclick="loCloseModals()">Bekor qilish</button>
                        <button type="submit" class="lo-btn lo-btn-danger">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            Rad etish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function loOpenModal(kind, btn) {
                loCloseModals();
                var modal = document.getElementById(kind === 'approve' ? 'loApproveModal' : 'loRejectModal');
                var form = modal.querySelector('form');
                form.action = btn.dataset.action;
                modal.querySelectorAll('[data-fill]').forEach(function (el) {
                    el.textContent = btn.dataset[el.dataset.fill] || '—';
                });
                modal.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
                if (kind === 'reject') {
                    document.getElementById('loRejectComment').value = '';
                }
                modal.classList.add('is-open');
                if (kind === 'reject') {
                    setTimeout(function () { document.getElementById('loRejectComment').focus(); }, 50);
                }
            }
            function loCloseModals() {
                document.querySelectorAll('.lo-modal.is-open').forEach(function (m) { m.classList.remove('is-open'); });
            }
            function loPickReason(chip) {
                var box = document.getElementById('loRejectComment');
                box.value = chip.textContent.trim();
                box.focus();
            }
            // Ikki marta bosilib ikki so'rov ketmasin
            document.querySelectorAll('.lo-modal form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') loCloseModals();
            });
        </script>
    @endif
</x-app-layout>
