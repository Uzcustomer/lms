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
        .yq-btn--sm { height: 32px; padding: 0 11px; font-size: 12.5px; }
        .yq-btn--excel { color: var(--yq-green) !important; border-color: var(--yq-green-line); background: var(--yq-green-bg); }
        .yq-btn--excel:hover { background: #dcfce7; border-color: #86efac; }
        .yq-btn--pdf { color: #fff !important; border-color: #b91c1c; background: #b91c1c; }
        .yq-btn--pdf:hover { background: #991b1b; border-color: #991b1b; }
        .yq-btn--pdf.is-busy { background: #94a3b8; border-color: #94a3b8; pointer-events: none; }
        .yq-btn svg { width: 15px; height: 15px; flex-shrink: 0; }

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
        .yq-btn--warn { color: #fff !important; border-color: #7e22ce; background: #7e22ce; }
        .yq-btn--warn:hover { background: #6b21a8; }
        .yq-badge--dean { background: var(--yq-green-bg); color: var(--yq-green); border-color: var(--yq-green-line); }
        .yq-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
        .yq-actions form { margin: 0; }
        .yq-muted { color: #94a3b8; }
        .yq-empty { padding: 48px 16px; text-align: center; color: var(--yq-muted); }
        .yq-empty b { display: block; color: var(--yq-ink); font-size: 15px; margin-bottom: 4px; }
        .yq-pages { padding: 12px 16px; border-top: 1px solid var(--yq-line); }
    </style>

    @php
        $statusUrl = fn ($s) => request()->fullUrlWithQuery(['status' => $s, 'page' => null]);
        $active = $filters['status'] ?? '';
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
                            Siz amaliy mashg'ulot o'tadigan guruh va fanlar. YN yuborilgach jurnalda «YN qaydnoma yaratish» orqali qaydnomani tekshirib
                            tasdiqlaysiz; kafedra mudiri yoki dekan rad etsa — sababini ko'rib, qayta tasdiqlab yuborasiz.
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

            {{-- Filtrlar — registrator / admin uchun; dekan, kafedra mudiri va o'qituvchida faqat kartochkalar --}}
            @if(!$isDean && !$isHead && !$isTeacher)
            @php
                // Filtr maydoni (select): nomi, yorlig'i, variantlari
                $select = fn (string $name, string $label, $items) => ['name' => $name, 'label' => $label, 'items' => $items];
                $selects = array_filter([
                    $isTeacher ? null : $select('education_type', "Ta'lim turi", $options['educationTypes']),
                    ($isDean || $isTeacher) ? null : $select('faculty', 'Fakultet', $options['faculties']),
                    $isTeacher ? null : $select('specialty', "Yo'nalish", $options['specialties']),
                    $isTeacher ? null : $select('level', 'Kurs', $options['levels']),
                    $select('semester', 'Semestr', $options['semesters']),
                    $select('group', 'Guruh', $options['groups']),
                    $isTeacher ? null : $select('kafedra', 'Kafedra', $options['kafedras']),
                    $select('subject', 'Fan', $options['subjects']),
                ]);
            @endphp
            <form method="GET" action="{{ $listRoute }}" class="yq-filters">
                @foreach($selects as $sel)
                    <div class="yq-field">
                        <label for="yq-{{ $sel['name'] }}">{{ $sel['label'] }}</label>
                        <select id="yq-{{ $sel['name'] }}" name="{{ $sel['name'] }}">
                            <option value="">Barchasi</option>
                            @foreach($sel['items'] as $id => $name)
                                <option value="{{ $id }}" @selected(($filters[$sel['name']] ?? '') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="yq-field" style="flex:0 1 160px;min-width:140px;">
                    <label for="yq-yn-from">YN sanasi (dan)</label>
                    <input type="date" id="yq-yn-from" name="yn_from" value="{{ $filters['yn_from'] ?? '' }}">
                </div>
                <div class="yq-field" style="flex:0 1 160px;min-width:140px;">
                    <label for="yq-yn-to">YN sanasi (gacha)</label>
                    <input type="date" id="yq-yn-to" name="yn_to" value="{{ $filters['yn_to'] ?? '' }}">
                </div>
                <div class="yq-field" style="flex:0 1 200px;">
                    <label for="yq-status">Holat</label>
                    <select id="yq-status" name="status">
                        <option value="">Barchasi</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($active === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="yq-field yq-field--wide">
                    <label for="yq-q">Qidirish</label>
                    <input type="search" id="yq-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Fan, guruh yoki o'qituvchi">
                </div>
                <input type="hidden" name="current" value="0">
                <label class="yq-check"><input type="checkbox" name="current" value="1" @checked(($filters['current'] ?? '1') === '1')> Joriy semestr</label>
                <button type="submit" class="yq-btn yq-btn--primary">Ko'rsatish</button>
                <a href="{{ $listRoute }}" class="yq-btn">Tozalash</a>
            </form>
            @endif

            <div class="yq-card">
                @if($rows->isEmpty())
                    <div class="yq-empty">
                        <b>Mos vedomost topilmadi</b>
                        {{ $isTeacher ? "Joriy dars jadvalingizdagi guruh va fanlar shu yerda ko'rinadi." : "YN imtihonlari tugagan fanlar shu yerda ko'rinadi." }}{{ ($isDean || $isHead || $isTeacher) ? '' : " Filtrlarni o'zgartirib ko'ring." }}
                    </div>
                @else
                    <div class="yq-scroll">
                        <table class="yq-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Guruh</th>
                                    <th>Fan</th>
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
                                    <tr>
                                        <td class="yq-num">{{ $rows->firstItem() + $i }}</td>
                                        <td>
                                            <span class="yq-group">{{ $row->group }}</span>
                                            @if($row->member_names)
                                                <span class="yq-small">Kichik guruhlar: {{ implode(', ', $row->member_names) }}</span>
                                            @endif
                                            <span class="yq-small">{{ collect([$isDean ? null : $row->faculty, $row->specialty, $row->level_name])->filter()->implode(' · ') }}</span>
                                        </td>
                                        <td>
                                            {{ $row->subject }}
                                            <span class="yq-small">{{ $row->semester }}</span>
                                        </td>
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
                                                        <a class="yq-btn yq-btn--sm yq-btn--warn" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">Qayta ko'rib chiqish</a>
                                                    @elseif($row->teachers->isNotEmpty() && $row->params)
                                                        <a class="yq-btn yq-btn--sm" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">Ko'rish</a>
                                                    @elseif(!$row->yn_submitted)
                                                        <span class="yq-muted" style="font-size:12px;">YN hali yuborilmagan</span>
                                                    @endif
                                                    @if($row->group_pk)
                                                        <a class="yq-btn yq-btn--sm {{ $row->status === 'none' && $row->yn_submitted ? 'yq-btn--primary' : '' }}"
                                                           href="{{ route('admin.journal.show', [$row->group_pk, $row->subject_id, $row->semester_code]) }}">Jurnal</a>
                                                    @endif
                                                </div>
                                            @elseif($row->teachers->isNotEmpty() && $row->params)
                                                <div class="yq-actions">
                                                    @if($canPdf && $row->status === 'dean')
                                                        <a class="yq-btn yq-btn--sm yq-btn--pdf" onclick="return yqPdf(this)"
                                                           href="{{ route('admin.vedomost-tekshirish.qaydnoma-pdf', ['subject_id' => $row->subject_id, 'semester_code' => $row->semester_code, 'group_hemis_id' => $row->group_hemis_id]) }}">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                            PDF
                                                        </a>
                                                    @endif
                                                    <a class="yq-btn yq-btn--sm" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
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
                                                    <input type="hidden" name="group_hemis_id" value="{{ $row->group_hemis_id }}">
                                                    <button type="submit" class="yq-btn yq-btn--sm" style="color:#b91c1c !important;border-color:#fecaca;">O'chirish</button>
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
