<x-app-layout>
    <style>
        /* Vedomostlar — sahifaning o'z uslubi (Tailwind build talab qilmaydi; yq- prefiksi) */
        .yq-page { --yq-navy: #1e3a8a; --yq-ink: #0f172a; --yq-text: #334155; --yq-muted: #64748b; --yq-line: #e2e8f0; --yq-soft: #f8fafc;
            --yq-green: #15803d; --yq-green-bg: #f0fdf4; --yq-green-line: #bbf7d0; color: var(--yq-text); font-size: 14px; }
        .yq-page *, .yq-page *::before, .yq-page *::after { box-sizing: border-box; }
        .yq-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .yq-title { margin: 0; font-size: 24px; line-height: 1.25; font-weight: 700; color: var(--yq-ink); }
        .yq-sub { margin: 4px 0 0; font-size: 13px; color: var(--yq-muted); max-width: 70ch; }
        .yq-search { display: flex; gap: 8px; }
        .yq-search input { height: 38px; width: 280px; max-width: 60vw; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: var(--yq-ink); background: #fff; }
        .yq-search input:focus { outline: 2px solid #93c5fd; outline-offset: 0; border-color: #60a5fa; }
        .yq-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: 1px solid var(--yq-line); background: #fff; color: var(--yq-ink) !important; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
        .yq-btn:hover { background: var(--yq-soft); border-color: #cbd5e1; }
        .yq-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .yq-btn--primary { background: var(--yq-navy); border-color: var(--yq-navy); color: #fff !important; height: 38px; }
        .yq-btn--primary:hover { background: #1e40af; }
        .yq-btn--excel { color: var(--yq-green) !important; border-color: var(--yq-green-line); background: var(--yq-green-bg); }
        .yq-btn--excel:hover { background: #dcfce7; border-color: #86efac; }
        .yq-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .yq-card { border-radius: 12px; background: #fff; border: 1px solid var(--yq-line); box-shadow: 0 1px 2px rgba(15,23,42,.05); overflow: hidden; }
        .yq-scroll { overflow-x: auto; }
        .yq-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .yq-table th { padding: 10px 12px; text-align: left; white-space: nowrap; background: var(--yq-soft); border-bottom: 1px solid var(--yq-line);
            font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--yq-muted); }
        .yq-table td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .yq-table tbody tr:hover td { background: #fafcff; }
        .yq-num { color: #94a3b8; font-variant-numeric: tabular-nums; width: 1%; }
        .yq-group { font-weight: 700; color: var(--yq-ink); white-space: nowrap; }
        .yq-small { display: block; font-size: 12px; color: var(--yq-muted); margin-top: 2px; }
        .yq-teacher { display: flex; align-items: center; gap: 6px; font-weight: 600; color: var(--yq-ink); }
        .yq-teacher + .yq-teacher { margin-top: 6px; }
        .yq-check { display: inline-flex; width: 18px; height: 18px; border-radius: 999px; background: var(--yq-green-bg); color: var(--yq-green);
            align-items: center; justify-content: center; font-size: 11px; border: 1px solid var(--yq-green-line); flex-shrink: 0; }
        .yq-date { font-variant-numeric: tabular-nums; }
        .yq-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap;
            background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .yq-badge--ok { background: var(--yq-green-bg); color: var(--yq-green); border-color: var(--yq-green-line); }
        .yq-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
        .yq-actions form { margin: 0; }
        .yq-empty { padding: 48px 16px; text-align: center; color: var(--yq-muted); }
        .yq-empty b { display: block; color: var(--yq-ink); font-size: 15px; margin-bottom: 4px; }
        .yq-pages { padding: 12px 16px; border-top: 1px solid var(--yq-line); }
    </style>

    <div class="py-6 yq-page">
        <div class="max-w-full mx-auto" style="padding-inline: 16px;">
            <div class="yq-head">
                <div>
                    <h1 class="yq-title">Vedomostlar</h1>
                    <p class="yq-sub">
                        Amaliy o'qituvchi baholarni tekshirib tasdiqlagan YN qaydnomalar{{ $isDean ? " — fakultetingiz guruhlari. «Ko'rish» orqali qaydnomani ochib, tasdiqlaysiz" : '' }}.
                        Jami: <b style="color:var(--yq-ink)">{{ $rows->total() }}</b>
                    </p>
                </div>
                <form method="GET" action="{{ route('admin.yn-qaydnomalar.index') }}" class="yq-search">
                    <input type="search" id="yq-q" name="q" value="{{ $search }}" placeholder="Guruh, fan yoki o'qituvchi" aria-label="Qidirish">
                    <button type="submit" class="yq-btn yq-btn--primary">Qidirish</button>
                </form>
            </div>

            <div class="yq-card">
                @if($rows->isEmpty())
                    <div class="yq-empty">
                        <b>{{ $search !== '' ? 'Hech narsa topilmadi' : "Hozircha tasdiqlangan vedomost yo'q" }}</b>
                        {{ $search !== '' ? "Qidiruv so'zini o'zgartirib ko'ring." : "O'qituvchi YN qaydnomani tasdiqlagach, shu yerda paydo bo'ladi." }}
                    </div>
                @else
                    <div class="yq-scroll">
                        <table class="yq-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Guruh</th>
                                    <th>Fan</th>
                                    <th>Tasdiqlagan o'qituvchi</th>
                                    <th>Sana</th>
                                    <th>Dekan</th>
                                    <th style="text-align:right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $i => $row)
                                    <tr>
                                        <td class="yq-num">{{ $rows->firstItem() + $i }}</td>
                                        <td>
                                            <span class="yq-group">{{ $row->group }}</span>
                                            @if($row->faculty && !$isDean)<span class="yq-small">{{ $row->faculty }}</span>@endif
                                        </td>
                                        <td>
                                            {{ $row->subject }}
                                            <span class="yq-small">{{ $row->semester }}</span>
                                        </td>
                                        <td>
                                            @foreach($row->teachers as $t)
                                                <div class="yq-teacher"><span class="yq-check" aria-hidden="true">✓</span>{{ $t->teacher_name }}</div>
                                            @endforeach
                                        </td>
                                        <td class="yq-date">
                                            {{ $row->confirmed_at->format('d.m.Y') }}
                                            <span class="yq-small">{{ $row->confirmed_at->format('H:i') }}</span>
                                        </td>
                                        <td>
                                            @if($row->dean)
                                                <span class="yq-badge yq-badge--ok">✓ Tasdiqlangan</span>
                                                <span class="yq-small yq-date">{{ $row->dean->confirmed_at->format('d.m.Y H:i') }}</span>
                                            @else
                                                <span class="yq-badge">Kutilmoqda</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="yq-actions">
                                                <a class="yq-btn" target="_blank" rel="noopener"
                                                   href="{{ route('admin.journal.view-yn-qaydnoma', $row->params) }}">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    Ko'rish
                                                </a>
                                                <form method="POST" action="{{ route('admin.journal.export-yn-qaydnoma') }}">
                                                    @csrf
                                                    @foreach($row->params as $k => $v)
                                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                                    @endforeach
                                                    <button type="submit" class="yq-btn yq-btn--excel">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        Excel
                                                    </button>
                                                </form>
                                            </div>
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
