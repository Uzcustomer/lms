<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">5 ga da'vogar talabalar</h2>
            <a href="{{ route('admin.student-ratings.index') }}" class="fc-back">&larr; Talabalar reytingi</a>
        </div>
    </x-slot>

    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
        $tone = fn ($v) => $v === null ? 'none' : ($v >= 90 ? 'good' : ($v >= 80 ? 'mid' : 'low'));
        $allAvgs = collect($averages)->flatten(1);
        $lowSubjects = $allAvgs->filter(fn ($a) => $a['average'] < 90)->count();
        $studentsWithLow = collect($averages)->filter(fn ($subs) => collect($subs)->contains(fn ($a) => $a['average'] < 90))->count();
    @endphp

    <style>
        .fc-wrap { --ink: #0f172a; --muted: #64748b; --line: #e5e9f0; --soft: #f6f8fb; --brand: #1d4ed8;
            --good-bg: #e8f7ee; --good-fg: #166534; --mid-bg: #fff4dc; --mid-fg: #92400e; --low-bg: #fde8e7; --low-fg: #b42318; }
        .fc-back { font-size: 13px; color: #1d4ed8; text-decoration: none; font-weight: 600; }
        .fc-back:hover { text-decoration: underline; }
        .fc-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); }
        .fc-filters { padding: 18px 20px; margin-bottom: 18px; }
        .fc-filter-row { display: flex; flex-wrap: wrap; gap: 14px 16px; align-items: flex-end; }
        .fc-field { display: flex; flex-direction: column; gap: 6px; min-width: 160px; }
        .fc-field.grow { flex: 1; min-width: 220px; }
        .fc-field label { font-size: 12px; font-weight: 600; color: #475569; }
        .fc-field input, .fc-field select { height: 40px; box-sizing: border-box; border: 1px solid #d5dbe5; border-radius: 10px; background-color: #f8fafc;
            padding: 0 12px; font-size: 13px; color: var(--ink); }
        .fc-field select { padding-right: 34px; }
        .fc-field input:focus, .fc-field select:focus { outline: none; border-color: #2563eb; background-color: #fff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }
        .fc-btn { display: inline-flex; align-items: center; gap: 7px; height: 40px; padding: 0 16px; border-radius: 10px; border: 0; font-size: 13px;
            font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: filter .15s, transform .15s; }
        .fc-btn:hover { filter: brightness(.95); transform: translateY(-1px); }
        .fc-btn:focus-visible { outline: 2px solid #1d4ed8; outline-offset: 2px; }
        .fc-btn svg { width: 16px; height: 16px; }
        .fc-btn-primary { background: #2563eb; color: #fff; }
        .fc-btn-excel { background: #16a34a; color: #fff; }
        .fc-hint { margin-top: 12px; font-size: 12px; color: var(--muted); line-height: 1.5; }

        .fc-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .fc-stat { padding: 14px 16px; }
        .fc-stat-label { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
        .fc-stat-value { margin-top: 4px; font-size: 24px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
        .fc-stat-value small { font-size: 12px; font-weight: 500; color: var(--muted); }
        .fc-stat.warn .fc-stat-value { color: var(--low-fg); }

        .fc-section { margin-bottom: 20px; overflow: hidden; }
        .fc-section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 18px;
            background: linear-gradient(180deg, #f8fafd, #f1f5fb); border-bottom: 1px solid var(--line); }
        .fc-section-head h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--ink); }
        .fc-count { font-size: 12px; font-weight: 600; color: var(--brand); background: #e8efff; border-radius: 999px; padding: 3px 10px; }
        .fc-table-wrap { overflow-x: auto; }
        .fc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .fc-table thead th { position: sticky; top: 0; z-index: 1; background: #fff; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .04em;
            text-transform: uppercase; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        .fc-table tbody td { padding: 10px 12px; border-bottom: 1px solid #f0f3f8; vertical-align: top; color: #1e293b; }
        .fc-table tbody tr:nth-child(even) td { background: #fbfcfe; }
        .fc-table tbody tr:hover td { background: #f1f6ff; }
        .fc-table tbody tr:last-child td { border-bottom: 0; }
        .fc-num { color: var(--muted); font-variant-numeric: tabular-nums; width: 36px; }
        .fc-name { font-weight: 600; color: var(--ink); }
        .fc-id { display: block; font-size: 11px; font-weight: 400; color: var(--muted); margin-top: 2px; }
        .fc-nowrap { white-space: nowrap; }
        .fc-center { text-align: center; }
        .fc-pill { display: inline-block; min-width: 48px; text-align: center; padding: 4px 9px; border-radius: 8px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .fc-pill.good { background: var(--good-bg); color: var(--good-fg); }
        .fc-pill.mid { background: var(--mid-bg); color: var(--mid-fg); }
        .fc-pill.low { background: var(--low-bg); color: var(--low-fg); }
        .fc-pill.none { background: var(--soft); color: var(--muted); }
        .fc-subjects { display: flex; flex-wrap: wrap; gap: 6px; }
        .fc-subj { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--line); border-radius: 8px; padding: 3px 4px 3px 9px; font-size: 12px; background: #fff; }
        .fc-subj span { color: #334155; }
        .fc-subj b { border-radius: 6px; padding: 1px 7px; font-variant-numeric: tabular-nums; }
        .fc-subj.good b { background: var(--good-bg); color: var(--good-fg); }
        .fc-subj.mid { border-color: #f7d99b; } .fc-subj.mid b { background: var(--mid-bg); color: var(--mid-fg); }
        .fc-subj.low { border-color: #f5c2bf; } .fc-subj.low b { background: var(--low-bg); color: var(--low-fg); }
        .fc-empty-cell { font-size: 12px; color: var(--muted); font-style: italic; }
        .fc-empty { padding: 40px 20px; text-align: center; color: var(--muted); }
        .fc-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 12px; color: var(--muted); }
        .fc-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
    </style>

    <div class="fc-wrap">
        <div class="fc-card fc-filters">
            <form method="GET" action="{{ route('admin.student-ratings.five-candidates') }}" class="fc-filter-row">
                <div class="fc-field">
                    <label for="fc-from">Davr boshi</label>
                    <input type="date" id="fc-from" name="from" value="{{ $from }}">
                </div>
                <div class="fc-field">
                    <label for="fc-to">Davr oxiri</label>
                    <input type="date" id="fc-to" name="to" value="{{ $to }}">
                </div>
                <div class="fc-field grow">
                    <label for="fc-faculty">Fakultet</label>
                    <select id="fc-faculty" name="faculty">
                        <option value="">Barcha fakultetlar</option>
                        @foreach($faculties as $f)
                            <option value="{{ $f }}" {{ $faculty === $f ? 'selected' : '' }}>{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="fc-btn fc-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"></path></svg>
                    Ko'rsatish
                </button>
                <a href="{{ route('admin.student-ratings.five-candidates.export', array_filter(['from' => $from, 'to' => $to, 'faculty' => $faculty])) }}" class="fc-btn fc-btn-excel">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Excel — kunlik baholar
                </a>
            </form>
            <div class="fc-hint">
                Joriy baho — tanlangan davrdagi kunlik baholarning fan bo'yicha o'rtachasi. Excelda har bir fakultet alohida varaqda,
                har bir baho sanasi va turi (joriy, MT, ON, OSKI, Test) bilan.
            </div>
            <div class="fc-legend">
                <span><i style="background:#e8f7ee;border:1px solid #9ad5b0"></i>90 va yuqori</span>
                <span><i style="background:#fff4dc;border:1px solid #f7d99b"></i>80–89</span>
                <span><i style="background:#fde8e7;border:1px solid #f5c2bf"></i>80 dan past</span>
            </div>
        </div>

        <div class="fc-stats">
            <div class="fc-card fc-stat">
                <div class="fc-stat-label">Da'vogar talabalar</div>
                <div class="fc-stat-value">{{ $total }}</div>
            </div>
            <div class="fc-card fc-stat">
                <div class="fc-stat-label">Fakultetlar</div>
                <div class="fc-stat-value">{{ $byFaculty->count() }}</div>
            </div>
            <div class="fc-card fc-stat {{ $studentsWithLow ? 'warn' : '' }}">
                <div class="fc-stat-label">90 dan past fani bor</div>
                <div class="fc-stat-value">{{ $studentsWithLow }} <small>talaba · {{ $lowSubjects }} fan</small></div>
            </div>
            <div class="fc-card fc-stat">
                <div class="fc-stat-label">Davr</div>
                <div class="fc-stat-value" style="font-size:16px;padding-top:6px;">
                    {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}
                </div>
            </div>
        </div>

        @forelse($byFaculty as $facultyName => $students)
            <section class="fc-card fc-section">
                <div class="fc-section-head">
                    <h3>{{ $facultyName }}</h3>
                    <span class="fc-count">{{ $students->count() }} ta talaba</span>
                </div>
                <div class="fc-table-wrap">
                    <table class="fc-table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>F.I.Sh.</th>
                                <th>Guruh</th>
                                <th>Yo'nalish</th>
                                <th>Semestr</th>
                                <th class="fc-center">JN reyting</th>
                                <th>Joriy baholar (fan bo'yicha)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($students as $s)
                                @php
                                    $subs = $averages[$s->hemis_id] ?? [];
                                    $jn = isset($ratings[$s->hemis_id]) ? (float) $ratings[$s->hemis_id] : null;
                                @endphp
                                <tr>
                                    <td class="fc-num">{{ $loop->iteration }}</td>
                                    <td>
                                        <span class="fc-name">{{ $s->full_name }}</span>
                                        @if($s->student_id_number)<span class="fc-id">{{ $s->student_id_number }}</span>@endif
                                    </td>
                                    <td class="fc-nowrap">{{ $s->group_name }}</td>
                                    <td>{{ $s->specialty_name }}</td>
                                    <td class="fc-nowrap">{{ $s->semester_name }}</td>
                                    <td class="fc-center"><span class="fc-pill {{ $tone($jn) }}">{{ $jn !== null ? $fmt($jn) : '—' }}</span></td>
                                    <td>
                                        @if($subs)
                                            <div class="fc-subjects">
                                                @foreach($subs as $sub)
                                                    <span class="fc-subj {{ $tone($sub['average']) }}" title="{{ $sub['days'] }} kunlik baho">
                                                        <span>{{ $sub['name'] }}</span><b>{{ $fmt($sub['average']) }}</b>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="fc-empty-cell">Davrda baho yo'q</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="fc-card fc-empty">
                "5 ga da'vogar" deb belgilangan talaba yo'q. Talaba sahifasida "5 ga da'vogar" belgisini qo'ying.
            </div>
        @endforelse
    </div>
</x-app-layout>
