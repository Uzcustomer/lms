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
        .yq-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        @media (max-width: 900px) { .yq-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .yq-stat { display: flex; flex-direction: column; gap: 2px; padding: 14px 16px 14px 20px; border-radius: 12px; background: #fff; border: 1px solid var(--yq-line);
            text-decoration: none !important; color: inherit !important; transition: border-color .15s, box-shadow .15s; position: relative; overflow: hidden; }
        .yq-stat::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--yq-c, #94a3b8); }
        .yq-stat:hover { border-color: #cbd5e1; box-shadow: 0 2px 8px rgba(15,23,42,.06); }
        .yq-stat:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .yq-stat.is-active { border-color: var(--yq-c, #94a3b8); box-shadow: 0 0 0 1px var(--yq-c, #94a3b8); }
        .yq-stat-num { font-size: 26px; font-weight: 800; color: var(--yq-ink); font-variant-numeric: tabular-nums; line-height: 1.1; }
        .yq-stat-label { font-size: 12.5px; font-weight: 600; color: var(--yq-muted); }
        .yq-stat--none { --yq-c: #dc2626; } .yq-stat--teacher { --yq-c: #d97706; } .yq-stat--dean { --yq-c: #16a34a; } .yq-stat--all { --yq-c: var(--yq-navy); }

        /* Filtrlar */
        .yq-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 14px 16px; margin-bottom: 16px; border-radius: 12px; background: #fff; border: 1px solid var(--yq-line); }
        .yq-field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; flex: 1 1 150px; }
        .yq-field--wide { flex: 2 1 220px; }
        .yq-field label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--yq-muted); }
        .yq-field select, .yq-field input[type=search] { height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: var(--yq-ink); background: #fff; width: 100%; }
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
        .yq-badge--teacher { background: var(--yq-amber-bg); color: var(--yq-amber); border-color: var(--yq-amber-line); }
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
        $statusLabels = ['none' => 'Topshirilmagan', 'teacher' => 'Dekan tasdig\'i kutilmoqda', 'dean' => 'Dekan tasdiqlagan'];
    @endphp

    <div class="py-6 yq-page">
        <div class="max-w-full mx-auto" style="padding-inline: 16px;">
            <div class="yq-head">
                <div>
                    <h1 class="yq-title">Vedomostlar</h1>
                    <p class="yq-sub">
                        YN imtihonlari (OSKI / test) tugagan fanlar{{ $isDean ? ' — fakultetingiz guruhlari' : '' }}: amaliy o'qituvchi YN qaydnomani
                        tasdiqlab topshirganmi va dekan tasdiqlaganmi.{{ $isDean ? " O'qituvchi topshirgan qaydnomani «Ko'rish» orqali ochib tasdiqlaysiz." : '' }}
                    </p>
                </div>
            </div>

            <div class="yq-stats">
                <a href="{{ $statusUrl(null) }}" class="yq-stat yq-stat--all {{ $active === '' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['all'] }}</span><span class="yq-stat-label">Jami vedomost</span>
                </a>
                <a href="{{ $statusUrl('none') }}" class="yq-stat yq-stat--none {{ $active === 'none' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['none'] }}</span><span class="yq-stat-label">O'qituvchi topshirmagan</span>
                </a>
                <a href="{{ $statusUrl('teacher') }}" class="yq-stat yq-stat--teacher {{ $active === 'teacher' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['teacher'] }}</span><span class="yq-stat-label">Topshirgan, dekan tasdig'i kutilmoqda</span>
                </a>
                <a href="{{ $statusUrl('dean') }}" class="yq-stat yq-stat--dean {{ $active === 'dean' ? 'is-active' : '' }}">
                    <span class="yq-stat-num">{{ $counts['dean'] }}</span><span class="yq-stat-label">Dekan tasdiqlagan</span>
                </a>
            </div>

            <form method="GET" action="{{ route('admin.yn-qaydnomalar.index') }}" class="yq-filters">
                @if(!$isDean)
                    <div class="yq-field">
                        <label for="yq-faculty">Fakultet</label>
                        <select id="yq-faculty" name="faculty">
                            <option value="">Barchasi</option>
                            @foreach($options['faculties'] as $id => $name)
                                <option value="{{ $id }}" @selected(($filters['faculty'] ?? '') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="yq-field">
                    <label for="yq-specialty">Yo'nalish</label>
                    <select id="yq-specialty" name="specialty">
                        <option value="">Barchasi</option>
                        @foreach($options['specialties'] as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['specialty'] ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="yq-field" style="flex:0 1 130px;min-width:120px;">
                    <label for="yq-level">Kurs</label>
                    <select id="yq-level" name="level">
                        <option value="">Barchasi</option>
                        @foreach($options['levels'] as $code => $name)
                            <option value="{{ $code }}" @selected(($filters['level'] ?? '') == $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="yq-field">
                    <label for="yq-group">Guruh</label>
                    <select id="yq-group" name="group">
                        <option value="">Barchasi</option>
                        @foreach($options['groups'] as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['group'] ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
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
                <a href="{{ route('admin.yn-qaydnomalar.index') }}" class="yq-btn">Tozalash</a>
            </form>

            <div class="yq-card">
                @if($rows->isEmpty())
                    <div class="yq-empty">
                        <b>Mos vedomost topilmadi</b>
                        YN imtihonlari tugagan fanlar shu yerda ko'rinadi. Filtrlarni o'zgartirib ko'ring.
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
                                    <th>Amaliy o'qituvchi</th>
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
                                        <td>
                                            @forelse($row->teachers as $t)
                                                <div class="yq-person"><span class="yq-dot yq-dot--ok" aria-hidden="true">✓</span>
                                                    <span>{{ $t->teacher_name }}<span class="yq-small">topshirdi · {{ $t->confirmed_at->format('d.m.Y H:i') }}</span></span></div>
                                            @empty
                                                @forelse($row->pending_teachers as $name)
                                                    <div class="yq-person yq-person--pending"><span class="yq-dot yq-dot--wait" aria-hidden="true">!</span><span>{{ $name }}</span></div>
                                                @empty
                                                    <span class="yq-muted">—</span>
                                                @endforelse
                                            @endforelse
                                        </td>
                                        <td>
                                            @if($row->dean)
                                                <div class="yq-person"><span class="yq-dot yq-dot--ok" aria-hidden="true">✓</span>
                                                    <span>{{ $row->dean->teacher_name }}<span class="yq-small">{{ $row->dean->confirmed_at->format('d.m.Y H:i') }}</span></span></div>
                                            @else
                                                <span class="yq-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="yq-badge yq-badge--{{ $row->status }}">{{ $statusLabels[$row->status] }}</span></td>
                                        <td>
                                            @if($row->params)
                                                <div class="yq-actions">
                                                    <a class="yq-btn yq-btn--sm" target="_blank" rel="noopener" href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        {{ $isDean && $row->status === 'teacher' ? 'Ko\'rish va tasdiqlash' : 'Ko\'rish' }}
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
                                                <div class="yq-actions yq-muted" style="font-size:12px;">O'qituvchi topshirgach</div>
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
</x-app-layout>
