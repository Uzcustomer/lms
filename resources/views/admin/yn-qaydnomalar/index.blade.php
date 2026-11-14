<x-app-layout>
    <style>
        /* Vedomostlar — sahifaning o'z uslubi (Tailwind build talab qilmaydi; yq- prefiksi) */
        .yq-page { --yq-navy: #1e3a8a; --yq-ink: #0f172a; --yq-text: #334155; --yq-muted: #64748b; --yq-line: #e2e8f0; --yq-soft: #f8fafc;
            --yq-green: #15803d; --yq-green-bg: #f0fdf4; --yq-green-line: #bbf7d0;
            --yq-amber: #b45309; --yq-amber-bg: #fffbeb; --yq-amber-line: #fde68a;
            --yq-red: #b91c1c; --yq-red-bg: #fef2f2; --yq-red-line: #fecaca;
            color: var(--yq-text); font-size: 14px; }
        .yq-page *, .yq-page *::before, .yq-page *::after { box-sizing: border-box; }
        .yq-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .yq-title { margin: 0; font-size: 24px; line-height: 1.25; font-weight: 700; color: var(--yq-ink); }
        .yq-sub { margin: 4px 0 0; font-size: 13px; color: var(--yq-muted); max-width: 80ch; }

        /* Holat kartalari — bosilsa shu holat bo'yicha filtr */
        .yq-stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        @media (max-width: 1100px) { .yq-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .yq-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .yq-stat { display: flex; flex-direction: column; gap: 2px; padding: 14px 16px 14px 20px; border-radius: 12px; background: #fff; border: 1px solid var(--yq-line);
            text-decoration: none !important; color: inherit !important; transition: border-color .15s, box-shadow .15s; position: relative; overflow: hidden; }
        .yq-stat::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--yq-c, #94a3b8); }
        .yq-stat:hover { border-color: #cbd5e1; box-shadow: 0 2px 8px rgba(15,23,42,.06); }
        .yq-stat:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .yq-stat.is-active { border-color: var(--yq-c, #94a3b8); box-shadow: 0 0 0 1px var(--yq-c, #94a3b8); }
        .yq-stat-num { font-size: 26px; font-weight: 800; color: var(--yq-ink); font-variant-numeric: tabular-nums; line-height: 1.1; }
        .yq-stat-label { font-size: 12.5px; font-weight: 600; color: var(--yq-muted); }
        .yq-stat--rejected { --yq-c: #9333ea; }
        .yq-stat--none { --yq-c: #dc2626; } .yq-stat--teacher { --yq-c: #d97706; } .yq-stat--head { --yq-c: #0284c7; } .yq-stat--dean { --yq-c: #16a34a; } .yq-stat--all { --yq-c: var(--yq-navy); }

        /* Filtrlar */
        .yq-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 14px 16px; margin-bottom: 16px; border-radius: 12px; background: #fff; border: 1px solid var(--yq-line); }
        .yq-field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; flex: 1 1 150px; }
        .yq-field--wide { flex: 2 1 220px; }
        .yq-field label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--yq-muted); }
        .yq-field select, .yq-field input[type=search], .yq-field input[type=date] { height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: var(--yq-ink); background: #fff; width: 100%; }
        .yq-field select:focus, .yq-field input:focus { outline: 2px solid #93c5fd; outline-offset: 0; border-color: #60a5fa; }
        .yq-check { display: inline-flex; align-items: center; gap: 6px; height: 38px; font-size: 13px; font-weight: 600; color: var(--yq-ink); white-space: nowrap; }
        .yq-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: 1px solid var(--yq-line); background: #fff; color: var(--yq-ink) !important; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
        .yq-btn:hover { background: var(--yq-soft); border-color: #cbd5e1; }
        .yq-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .yq-btn--primary { background: var(--yq-navy); border-color: var(--yq-navy); color: #fff !important; }
        .yq-btn--primary:hover { background: #1e40af; }
        /* Jadvaldagi amal tugmalari — har biri o'z rangi va belgisi bilan, bir-biridan aniq ajralib turadi */
        .yq-btn--sm { height: 38px; padding: 0 14px; font-size: 13px; font-weight: 700; border-radius: 9px; gap: 7px;
            transition: background-color .15s, border-color .15s, box-shadow .15s, transform .05s; }
        .yq-btn--sm:active { transform: translateY(1px); }
        .yq-btn--primary { box-shadow: 0 2px 6px rgba(30,58,138,.28); }
        .yq-btn--approve { color: #fff !important; background: #16a34a; border-color: #16a34a; box-shadow: 0 2px 6px rgba(22,163,74,.3); }
        .yq-btn--approve:hover { background: #15803d; border-color: #15803d; }
        .yq-btn--view { color: #1d4ed8 !important; background: #eff6ff; border-color: #bfdbfe; }
        .yq-btn--view:hover { background: #dbeafe; border-color: #93c5fd; }
        .yq-btn--journal { color: #334155 !important; background: #fff; border-color: #cbd5e1; }
        .yq-btn--journal:hover { background: #f1f5f9; border-color: #94a3b8; }
        .yq-btn--excel { color: var(--yq-green) !important; border-color: #86efac; background: var(--yq-green-bg); }
        .yq-btn--excel:hover { background: #dcfce7; border-color: #4ade80; }
        .yq-btn--pdf { color: #fff !important; border-color: #b91c1c; background: #b91c1c; box-shadow: 0 2px 6px rgba(185,28,28,.28); }
        .yq-btn--pdf:hover { background: #991b1b; border-color: #991b1b; }
        .yq-btn--pdf.is-busy { background: #94a3b8; border-color: #94a3b8; pointer-events: none; box-shadow: none; }
        .yq-btn--danger { color: #b91c1c !important; background: #fff; border-color: #fca5a5; }
        .yq-btn--danger:hover { background: #fef2f2; border-color: #f87171; }
        .yq-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* Jadval */
        .yq-card { border-radius: 12px; background: #fff; border: 1px solid var(--yq-line); box-shadow: 0 1px 2px rgba(15,23,42,.05); overflow: hidden; }
        .yq-scroll { overflow-x: auto; }
        .yq-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .yq-table th { padding: 10px 12px; text-align: left; white-space: nowrap; background: var(--yq-soft); border-bottom: 1px solid var(--yq-line);
            font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--yq-muted); }
        .yq-table td { padding: 11px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .yq-table tbody tr:hover td { background: #fafcff; }
        .yq-num { color: #94a3b8; font-variant-numeric: tabular-nums; width: 1%; }
        .yq-group { font-weight: 700; color: var(--yq-ink); white-space: nowrap; }
        .yq-small { display: block; font-size: 12px; color: var(--yq-muted); margin-top: 2px; font-weight: 400; }
        /* Qaydnoma shakli (12-shakl qo'shimchasi, 12a, …) — alohida vedomost */
        .yq-mine-list { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-top: 4px; }
        .yq-mine-label { font-size: 12px; color: var(--yq-muted); }
        .yq-mine { display: inline-block; padding: 1px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; white-space: nowrap;
            color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; text-decoration: none; }
        a.yq-mine:hover { background: #dbeafe; }
        a.yq-mine:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
        .yq-mine.is-done { color: var(--yq-green); background: var(--yq-green-bg); border-color: var(--yq-green-line); }
        .yq-form--main { color: #334155 !important; background: #f1f5f9 !important; border-color: #e2e8f0 !important; }
        .yq-form { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;
            color: #6d28d9; background: #f5f3ff; border: 1px solid #ddd6fe; white-space: nowrap; vertical-align: 1px; }
        /* O'qituvchi: fan tugagan, siz hali tasdiqlamagan qaydnoma — qator chap chetida sariq chiziq */
        .yq-table tr.yq-row--todo > td { background: #fffdf5; }
        .yq-table tr.yq-row--todo > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .yq-dates { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: 12.5px; }
        .yq-dates b { display: inline-block; min-width: 40px; color: var(--yq-muted); font-weight: 600; font-size: 11px; }
        .yq-person { display: flex; align-items: flex-start; gap: 6px; font-weight: 600; color: var(--yq-ink); }
        .yq-person + .yq-person { margin-top: 5px; }
        .yq-person--pending { color: var(--yq-muted); font-weight: 500; }
        .yq-dot { flex-shrink: 0; display: inline-flex; width: 18px; height: 18px; margin-top: 1px; border-radius: 999px; align-items: center; justify-content: center; font-size: 11px; border: 1px solid; }
        .yq-dot--ok { background: var(--yq-green-bg); color: var(--yq-green); border-color: var(--yq-green-line); }
        .yq-dot--wait { background: var(--yq-red-bg); color: var(--yq-red); border-color: var(--yq-red-line); }
        .yq-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; border: 1px solid; }
        .yq-badge--none { background: var(--yq-red-bg); color: var(--yq-red); border-color: var(--yq-red-line); }
        .yq-badge--partial { background: var(--yq-amber-bg); color: var(--yq-amber); border-color: var(--yq-amber-line); border-style: dashed; }
        .yq-badge--teacher { background: var(--yq-amber-bg); color: var(--yq-amber); border-color: var(--yq-amber-line); }
        .yq-badge--head { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
        .yq-badge--rejected { background: #faf5ff; color: #7e22ce; border-color: #e9d5ff; }
        .yq-reason { margin-top: 4px; padding: 6px 9px; border-radius: 8px; background: #faf5ff; border: 1px solid #e9d5ff; color: #6b21a8; font-size: 12px; max-width: 34ch; }
        .yq-btn--warn { color: #fff !important; border-color: #7e22ce; background: #7e22ce; box-shadow: 0 2px 6px rgba(126,34,206,.28); }
        .yq-btn--warn:hover { background: #6b21a8; }
        .yq-badge--dean { background: var(--yq-green-bg); color: var(--yq-green); border-color: var(--yq-green-line); }
        .yq-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center; }
        .yq-actions form { margin: 0; }
        .yq-muted { color: #94a3b8; }
        .yq-empty { padding: 48px 16px; text-align: center; color: var(--yq-muted); }
        .yq-empty b { display: block; color: var(--yq-ink); font-size: 15px; margin-bottom: 4px; }
        .yq-pages { padding: 12px 16px; border-top: 1px solid var(--yq-line); }
    </style>

    @php
        $statusUrl = fn ($s) => request()->fullUrlWithQuery(['status' => $s, 'page' => null]);
        $active = $filters['status'] ?? '';
        $svg = fn (string $d) => '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
        $ico = [
            'create' => $svg('M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'),
            'view' => $svg('M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'),
            'approve' => $svg('M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'),
            'redo' => $svg('M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'),
            'journal' => $svg('M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'),
            'trash' => $svg('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'),
        ];
        $statusLabels = ['none' => 'Topshirilmagan', 'partial' => 'Qisman topshirilgan', 'teacher' => 'Kafedra tasdig\'i kutilmoqda', 'head' => 'Dekan tasdig\'i kutilmoqda',
            'rejected' => 'Rad etilgan', 'dean' => 'Dekan tasdiqlagan'];
        $isHead = $isHead ?? false;
        // Shu foydalanuvchi tasdiqlashi kutilayotgan holat
        $myTurn = $isHead ? 'teacher' : ($isDean ? 'head' : null);
        $listRoute = $isTeacher ? route('admin.yn-qaydnomalar.mine') : route('admin.yn-qaydnomalar.index');
    @endphp

    <div class="py-6 yq-page">
        <div class="max-w-full mx-auto" style="padding-inline: 16px;">
            <div class="yq-head">
                <div>
                    @if($canPdf)
                        <a href="{{ route('admin.vedomost-tekshirish.index') }}" class="yq-small" style="text-decoration:none;margin:0 0 4px;">← Vedomost tekshirish</a>
                    @endif
                    <h1 class="yq-title">{{ $isTeacher ? 'Vedomostlarim' : 'Vedomostlar' }}</h1>
                    <p class="yq-sub">
                        @if($isTeacher)
                            Siz amaliy mashg'ulot o'tadigan barcha guruh va fanlar. Fan tugagach (imtihonlar o'tgach, imtihonsiz fanda — oxirgi dars o'tgach)
                            «YN qaydnoma» tugmasi bilan qaydnomani ochib, tekshirib tasdiqlaysiz; tasdiqlashingiz kerak bo'lganlar ro'yxat boshida, sariq chiziq bilan.
                            Kafedra mudiri yoki dekan rad etsa — sababini ko'rib, qayta tasdiqlab yuborasiz.
                        @else
                            YN imtihonlari (OSKI / test) tugagan fanlar{{ $isDean ? ' — fakultetingiz guruhlari' : ($isHead ? ' — kafedrangiz fanlari' : '') }}:
                            amaliy o'qituvchi YN qaydnomani topshirganmi, kafedra mudiri va dekan tasdiqlaganmi.
                            @if($isHead) O'qituvchi topshirgan qaydnomani «Ko'rish» orqali ochib tasdiqlaysiz yoki rad etasiz; keyin dekan tasdiqlaydi.
                            @elseif($isDean) Kafedra mudiri tasdiqlagan qaydnomani «Ko'rish» orqali ochib tasdiqlaysiz yoki rad etasiz.
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            {{-- Holat kartochkalari (dekan, kafedra mudiri, o'qituvchi) --}}
            <div class="yq-stats">
                <a href="{{ $statusUrl(null) }}" class="yq-stat yq-stat--all {{ $active === '' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['all'] }}</span><span class="yq-stat-label">{{ $isTeacher ? 'Jami' : 'Jami vedomost' }}</span>
                </a>
                <a href="{{ $statusUrl('none') }}" class="yq-stat yq-stat--none {{ $active === 'none' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['none'] }}</span><span class="yq-stat-label">{{ $isTeacher ? 'Topshirilmagan' : 'O\'qituvchi topshirmagan' }}</span>
                </a>
                <a href="{{ $statusUrl('teacher') }}" class="yq-stat yq-stat--teacher {{ $active === 'teacher' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['teacher'] }}</span><span class="yq-stat-label">Kafedra tasdig'i kutilmoqda</span>
                </a>
                <a href="{{ $statusUrl('head') }}" class="yq-stat yq-stat--head {{ $active === 'head' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['head'] }}</span><span class="yq-stat-label">Dekan tasdig'i kutilmoqda</span>
                </a>
                <a href="{{ $statusUrl('rejected') }}" class="yq-stat yq-stat--rejected {{ $active === 'rejected' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['rejected'] }}</span><span class="yq-stat-label">Rad etilgan</span>
                </a>
                <a href="{{ $statusUrl('dean') }}" class="yq-stat yq-stat--dean {{ $active === 'dean' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['dean'] }}</span><span class="yq-stat-label">Dekan tasdiqlagan</span>
                </a>
            </div>



            <div class="yq-card">
                @if($rows->isEmpty())
                    <div class="yq-empty">
                        <b>Mos vedomost topilmadi</b>
                        {{ $isTeacher ? "Joriy dars jadvalingizdagi guruh va fanlar shu yerda ko'rinadi." : "YN imtihonlari tugagan fanlar shu yerda ko'rinadi." }}
                    </div>
                @else
                    <div class="yq-scroll">
                        <table class="yq-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Guruh</th>
                                    <th>Fan</th>
                                    <th>Shakl</th>
                                    <th>YN sanasi</th>
                                    @unless($isTeacher)<th>Amaliy o'qituvchi</th>@endunless
                                    <th>Kafedra mudiri</th>
                                    <th>Dekan</th>
                                    <th>Holat</th>
                                    <th style="text-align:right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $i => $row)
                                    <tr class="{{ $isTeacher && $row->needs_action ? 'yq-row--todo' : '' }}">
                                        <td class="yq-num">{{ $rows->firstItem() + $i }}</td>
                                        <td>
                                            <span class="yq-group">{{ $row->group }}</span>
                                            @if($row->member_names)
                                                <span class="yq-small">Kichik guruhlar: {{ implode(', ', $row->member_names) }}</span>
                                            @endif
                                            @if($isTeacher)@include('admin.yn-qaydnomalar.partials.my-groups')@endif
                                            <span class="yq-small">{{ collect([$isDean ? null : $row->faculty, $row->specialty, $row->level_name])->filter()->implode(' · ') }}</span>
                                        </td>
                                        <td>
                                            {{ $row->subject }}
                                            <span class="yq-small">{{ $row->semester }}</span>
                                        </td>
                                        <td><span class="yq-form {{ $row->form === '12' ? 'yq-form--main' : '' }}" style="margin-left:0">{{ $row->form_label }}</span></td>
                                        <td class="yq-dates">
                                            @if($row->oski_date)<div><b>OSKI</b> {{ \Carbon\Carbon::parse($row->oski_date)->format('d.m.Y') }}</div>@endif
                                            @if($row->test_date)<div><b>Test</b> {{ \Carbon\Carbon::parse($row->test_date)->format('d.m.Y') }}</div>@endif
                                        </td>
                                        @unless($isTeacher)
                                        <td>
                                            @foreach($row->teachers as $t)
                                                <div class="yq-person"><span class="yq-dot yq-dot--ok" aria-hidden="true">✓</span>
                                                    <span>{{ $t->teacher_name }}<span class="yq-small">topshirdi · {{ $t->confirmed_at->format('d.m.Y H:i') }}</span></span></div>
                                            @endforeach
                                            {{-- Hali topshirmagan (kichik) guruhlar o'qituvchilari --}}
                                            @foreach($row->pending_teachers as $name)
                                                <div class="yq-person yq-person--pending"><span class="yq-dot yq-dot--wait" aria-hidden="true">!</span><span>{{ $name }}</span></div>
                                            @endforeach
                                            @if($row->teachers->isEmpty() && $row->pending_teachers->isEmpty())
                                                <span class="yq-muted">—</span>
                                            @endif
                                        </td>
                                        @endunless
                                        <td>
                                            @if($row->head)
                                                <div class="yq-person"><span class="yq-dot yq-dot--ok" aria-hidden="true">✓</span>
                                                    <span>{{ $row->head->teacher_name }}<span class="yq-small">{{ $row->head->confirmed_at->format('d.m.Y H:i') }}</span></span></div>
                                            @else
                                                <span class="yq-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->dean)
                                                <div class="yq-person"><span class="yq-dot yq-dot--ok" aria-hidden="true">✓</span>
                                                    <span>{{ $row->dean->teacher_name }}<span class="yq-small">{{ $row->dean->confirmed_at->format('d.m.Y H:i') }}</span></span></div>
                                            @elseif($row->rejection)
                                                <div class="yq-person"><span class="yq-dot" style="background:#faf5ff;color:#7e22ce;border-color:#e9d5ff;" aria-hidden="true">✕</span>
                                                    <span>{{ $row->rejection->dean_name }}<span class="yq-small">rad etdi · {{ $row->rejection->rejected_at->format('d.m.Y H:i') }}</span></span></div>
                                                <div class="yq-reason">{{ $row->rejection->reason }}</div>
                                            @else
                                                <span class="yq-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="yq-badge yq-badge--{{ $row->status }}">{{ $statusLabels[$row->status] }}</span></td>
                                        <td>
                                            @if($isTeacher)
                                                <div class="yq-actions">
                                                    @if($row->status === 'rejected' && $row->params)
                                                        <a class="yq-btn yq-btn--sm yq-btn--warn" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">{!! $ico['redo'] !!}Qayta ko'rib chiqish</a>
                                                    @elseif($row->create_params)
                                                        {{-- Fan tugagan — qaydnoma darhol tasdiqlash sahifasida (jurnalsiz) --}}
                                                        <a class="yq-btn yq-btn--sm yq-btn--primary" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->create_params) }}">{!! $ico['create'] !!}YN qaydnoma</a>
                                                    @elseif($row->teachers->isNotEmpty() && $row->params)
                                                        <a class="yq-btn yq-btn--sm yq-btn--view" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">{!! $ico['view'] !!}Ko'rish</a>
                                                    @elseif(!$row->exams_finished)
                                                        {{-- Fan tugamaguncha qaydnoma yaratilmaydi --}}
                                                        {{-- 12a / 12b — sabab o'zi ("2-urinish hali topshirilmagan"), asosiy shaklda — "Fan tugamagan: …" --}}
                                                        <span class="yq-muted" style="font-size:12px;">{{ in_array($row->form, ['12a', '12aq', '12b', '12bq'], true) ? ucfirst((string) $row->finish_reason) : 'Fan tugamagan' . ($row->finish_reason ? ': ' . $row->finish_reason : '') }}</span>
                                                    @elseif(!$row->yn_submitted)
                                                        <span class="yq-muted" style="font-size:12px;">Avval jurnalda YN ga yuboring</span>
                                                    @endif
                                                    @if($row->group_pk)
                                                        <a class="yq-btn yq-btn--sm yq-btn--journal"
                                                           href="{{ route('admin.journal.show', [collect($row->my_groups)->first(fn ($g) => !$g->confirmed && $g->pk)?->pk ?? $row->group_pk, $row->subject_id, $row->semester_code] + ($row->form !== '12' ? ['ynq_form' => $row->form] : [])) }}">{!! $ico['journal'] !!}Jurnal</a>
                                                    @endif
                                                </div>
                                            @elseif($row->teachers->isNotEmpty() && $row->params)
                                                <div class="yq-actions">
                                                    @if($canPdf && $row->status === 'dean')
                                                        <a class="yq-btn yq-btn--sm yq-btn--pdf" onclick="return yqPdf(this)"
                                                           href="{{ route('admin.vedomost-tekshirish.qaydnoma-pdf', ['subject_id' => $row->subject_id, 'semester_code' => $row->semester_code, 'group_hemis_id' => $row->group_hemis_id, 'form' => $row->form]) }}">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                            PDF
                                                        </a>
                                                    @endif
                                                    {{-- Dekan tasdiqlagan — "Ko'rish" mavjud PDF ni ochadi (PDF huquqi borlarga) --}}
                                                    <a class="yq-btn yq-btn--sm {{ $myTurn && $row->status === $myTurn ? 'yq-btn--approve' : 'yq-btn--view' }}" target="_blank" rel="noopener"
                                                       href="{{ $canPdf && $row->status === 'dean'
                                                           ? route('admin.vedomost-tekshirish.qaydnoma-pdf', ['subject_id' => $row->subject_id, 'semester_code' => $row->semester_code, 'group_hemis_id' => $row->group_hemis_id, 'form' => $row->form, 'inline' => 1, 'v' => substr(md5((string) ($row->dean->pdf_path ?? '')), 0, 8)])
                                                           : route('admin.journal.view-yn-qaydnoma', $row->params) }}">
                                                        {!! $myTurn && $row->status === $myTurn ? $ico['approve'] : $ico['view'] !!}
                                                        {{ $myTurn && $row->status === $myTurn ? 'Ko\'rish va tasdiqlash' : 'Ko\'rish' }}
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.journal.export-yn-qaydnoma') }}">
                                                        @csrf
                                                        @foreach($row->params as $k => $v)
                                                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                                        @endforeach
                                                        <button type="submit" class="yq-btn yq-btn--sm yq-btn--excel">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Excel
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <div class="yq-actions yq-muted" style="font-size:12px;">{{ $row->status === 'rejected' ? 'Qayta topshirilishi kutilmoqda' : 'O\'qituvchi topshirgach' }}</div>
                                            @endif
                                            @if($canDelete && ($row->teachers->isNotEmpty() || $row->rejection))
                                                <form method="POST" action="{{ route('admin.journal.delete-yn-qaydnoma') }}" class="yq-actions" style="margin-top:6px;"
                                                      onsubmit="return confirm('Qaydnoma butunlay o\'chirilsinmi? Tasdiqlar, QR kodlar va fayllar o\'chadi; o\'qituvchi qaytadan shakllantiradi.')">
                                                    @csrf
                                                    <input type="hidden" name="subject_id" value="{{ $row->subject_id }}">
                                                    <input type="hidden" name="semester_code" value="{{ $row->semester_code }}">
                                                    <input type="hidden" name="group_hemis_id" value="{{ $row->group_hemis_id }}"><input type="hidden" name="form" value="{{ $row->form }}">
                                                    <button type="submit" class="yq-btn yq-btn--sm yq-btn--danger">{!! $ico['trash'] !!}O'chirish</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($rows->hasPages())
                        <div class="yq-pages">{{ $rows->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <script>
        // PDF bir necha soniyada tayyorlanadi — takroriy bosishning oldini olish
        function yqPdf(a) {
            if (a.classList.contains('is-busy')) return false;
            var label = a.lastChild;
            a.classList.add('is-busy'); label.textContent = ' Tayyorlanmoqda…';
            setTimeout(function () { a.classList.remove('is-busy'); label.textContent = ' PDF'; }, 15000);
            return true;
        }
    </script>
</x-app-layout>
