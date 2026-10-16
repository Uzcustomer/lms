<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fanlar bo'yicha hisobot</h2>
    </x-slot>

    @php
        $q = array_filter(['kafedra' => $kafedras->isNotEmpty() ? $deptId : null, 'from' => $from, 'to' => $to, 'scope' => $scope ?? null]);
        $fmt = fn ($v) => $v === null || $v === '' ? '—' : (is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') : $v);
        $tone = fn ($v) => !is_numeric($v) ? ($v === 'NB' ? 'nb' : 'none') : ($v >= 90 ? 'a' : ($v >= 70 ? 'b' : ($v >= 60 ? 'c' : 'd')));
        $periodLabel = $from || $to
            ? ($from ? \Carbon\Carbon::parse($from)->format('d.m.Y') : '…') . ' – ' . ($to ? \Carbon\Carbon::parse($to)->format('d.m.Y') : '…')
            : 'Joriy semestr';
    @endphp

    <style>
        .kr { --ink:#0f172a; --muted:#64748b; --line:#e5e9f0; --navy:#1b3a63; --blue:#2b5ea7;
              --a-bg:#e7f6ec; --a-fg:#15803d; --b-bg:#e8efff; --b-fg:#1d4ed8; --c-bg:#fff4dc; --c-fg:#92400e; --d-bg:#fde8e7; --d-fg:#b42318; }
        .kr-card { background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 3px rgba(15,23,42,.06); }
        .kr-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:14px 20px; color:#fff;
                   background:linear-gradient(135deg,var(--navy) 0%,var(--blue) 100%); border-radius:14px 14px 0 0; }
        .kr-head h3 { margin:0; font-size:15px; font-weight:700; }
        .kr-head small { display:block; font-size:12px; opacity:.85; font-weight:500; margin-top:2px; }
        .kr-crumbs { display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:12.5px; }
        .kr-crumbs a { color:#dbe7ff; text-decoration:none; font-weight:600; }
        .kr-crumbs a:hover { color:#fff; text-decoration:underline; }
        .kr-crumbs span { opacity:.6; }
        .kr-filters { display:flex; flex-wrap:wrap; gap:12px 14px; align-items:flex-end; padding:14px 20px; border-bottom:1px solid var(--line); }
        .kr-field { display:flex; flex-direction:column; gap:5px; }
        .kr-field.grow { flex:1; min-width:240px; }
        .kr-field label { font-size:12px; font-weight:600; color:#475569; }
        .kr-field input, .kr-field select { height:38px; box-sizing:border-box; border:1px solid #d5dbe5; border-radius:9px; background:#f8fafc; padding:0 11px; font-size:13px; color:var(--ink); }
        .kr-field select { padding-right:32px; }
        .kr-field input:focus, .kr-field select:focus { outline:none; border-color:#2563eb; background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
        .kr-btn { display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 15px; border-radius:9px; border:0; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; white-space:nowrap; }
        .kr-btn:hover { filter:brightness(.95); }
        .kr-btn svg { width:16px; height:16px; }
        .kr-btn-blue { background:#2563eb; color:#fff; }
        .kr-btn-green { background:#16a34a; color:#fff; }
        .kr-btn-ghost { background:#fff; color:#334155; border:1px solid #d5dbe5; }
        .kr-summary { display:flex; flex-wrap:wrap; gap:10px; padding:12px 20px; border-bottom:1px solid var(--line); background:#f8fafd; font-size:12.5px; color:var(--muted); }
        .kr-summary b { color:var(--ink); font-variant-numeric:tabular-nums; }
        .kr-wrap { overflow-x:auto; }
        .kr-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .kr-table th { position:sticky; top:0; z-index:1; background:#f1f5fb; text-align:left; font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
                       color:var(--muted); padding:9px 12px; border-bottom:1px solid var(--line); white-space:nowrap; }
        .kr-table td { padding:9px 12px; border-bottom:1px solid #f0f3f8; vertical-align:middle; color:#1e293b; }
        .kr-table tbody tr:nth-child(even) td { background:#fbfcfe; }
        .kr-table tbody tr.kr-link { cursor:pointer; }
        .kr-table tbody tr.kr-link:hover td { background:#eef4ff; }
        .kr-c { text-align:center; } .kr-nw { white-space:nowrap; } .kr-mute { color:var(--muted); font-size:12px; }
        .kr-name { font-weight:600; color:var(--ink); } .kr-name a { color:inherit; text-decoration:none; } .kr-name a:hover { color:#1d4ed8; text-decoration:underline; }
        .kr-pill { display:inline-block; min-width:44px; text-align:center; padding:3px 8px; border-radius:7px; font-weight:700; font-variant-numeric:tabular-nums; }
        .kr-pill.a { background:var(--a-bg); color:var(--a-fg); } .kr-pill.b { background:var(--b-bg); color:var(--b-fg); }
        .kr-pill.c { background:var(--c-bg); color:var(--c-fg); } .kr-pill.d { background:var(--d-bg); color:var(--d-fg); }
        .kr-pill.none { background:#f1f5f9; color:var(--muted); }
        .kr-dist { display:flex; height:8px; width:150px; border-radius:99px; overflow:hidden; background:#eef1f6; }
        .kr-dist i { display:block; height:100%; }
        .kr-dist-legend { font-size:11px; color:var(--muted); margin-top:4px; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .kr-legend { display:flex; flex-wrap:wrap; gap:12px; font-size:12px; color:var(--muted); }
        .kr-legend i { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:5px; vertical-align:-1px; }
        /* Jurnal jadvali */
        .kr-grid td.g, .kr-grid th.g { text-align:center; padding:6px 4px; min-width:44px; }
        .kr-grid td.g.a { color:var(--a-fg); } .kr-grid td.g.b { color:var(--b-fg); } .kr-grid td.g.c { color:var(--c-fg); font-weight:700; }
        .kr-grid td.g.d { color:var(--d-fg); font-weight:700; background:#fff7f6 !important; } .kr-grid td.g.nb { color:#92400e; background:#fff7e0 !important; font-weight:600; }
        .kr-grid th.jn, .kr-grid td.jn { background:#f0f9f3 !important; text-align:center; font-weight:700; }
        .kr-grid th.ot { background:#e8efff; text-align:center; }
        .kr-grid td.ot { text-align:center; }
        .kr-grid td.sticky1, .kr-grid th.sticky1 { position:sticky; left:0; z-index:2; background:#fff; }
        .kr-grid th.sticky1 { background:#f1f5fb; z-index:3; }
        .kr-empty { padding:36px 20px; text-align:center; color:var(--muted); }
    </style>

    <div class="kr">
        <div class="kr-card">
            <div class="kr-head">
                <div>
                    <h3>{{ $deptName ?: 'Kafedra' }}</h3>
                    <small>{{ $periodLabel }}</small>
                </div>
                <nav class="kr-crumbs" aria-label="Bo'limlar">
                    <a href="{{ route('admin.kafedra-report.index', $q) }}">Fanlar</a>
                    @if(in_array($mode, ['groups', 'students'], true))
                        <span>›</span><a href="{{ route('admin.kafedra-report.subject', $q + ['unit' => $unit->key]) }}">{{ $unit->subject_name }}</a>
                    @endif
                    @if($mode === 'students')
                        <span>›</span><a href="{{ route('admin.kafedra-report.group', $q + ['unit' => $unit->key, 'group' => $group->group_hemis_id]) }}">{{ $group->name }}</a>
                    @endif
                </nav>
            </div>

            <form method="GET" class="kr-filters">
                @if($mode !== 'subjects')<input type="hidden" name="unit" value="{{ $unit->key }}">@endif
                @if($mode === 'students')<input type="hidden" name="group" value="{{ $group->group_hemis_id }}">@endif
                @if($kafedras->isNotEmpty())
                    <div class="kr-field grow">
                        <label for="kr-kafedra">Kafedra</label>
                        <select id="kr-kafedra" name="kafedra" onchange="this.form.submit()">
                            @foreach($kafedras as $k)
                                <option value="{{ $k->department_hemis_id }}" {{ (int) $deptId === (int) $k->department_hemis_id ? 'selected' : '' }}>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($mode === 'subjects')
                    <div class="kr-field grow">
                        <label for="kr-scope">Fakultet / yo'nalish · kurs</label>
                        <select id="kr-scope" name="scope" onchange="this.form.submit()">
                            <option value="">-- barcha yo'nalishlar --</option>
                            @foreach($scopes as $k => $sc)
                                <option value="{{ $k }}" {{ ($scope ?? null) === $k ? 'selected' : '' }}>{{ $sc['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($scope ?? null)
                    <input type="hidden" name="scope" value="{{ $scope }}">
                @endif
                <div class="kr-field">
                    <label for="kr-from">Davr boshi</label>
                    <input type="date" id="kr-from" name="from" value="{{ $from }}">
                </div>
                <div class="kr-field">
                    <label for="kr-to">Davr oxiri</label>
                    <input type="date" id="kr-to" name="to" value="{{ $to }}">
                </div>
                <button type="submit" class="kr-btn kr-btn-blue">Ko'rsatish</button>
                @if($from || $to)
                    <a class="kr-btn kr-btn-ghost" href="{{ url()->current() . '?' . http_build_query(array_filter(['kafedra' => $q['kafedra'] ?? null, 'unit' => $unit->key ?? null, 'group' => $group->group_hemis_id ?? null, 'scope' => $scope ?? null])) }}">Butun semestr</a>
                @endif
                @if($mode !== 'subjects')
                    <a class="kr-btn kr-btn-green" href="{{ route('admin.kafedra-report.export', $q + ['unit' => $unit->key] + ($mode === 'students' ? ['group' => $group->group_hemis_id] : [])) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Excel{{ $mode === 'groups' ? ' — barcha guruhlar' : '' }}
                    </a>
                @endif
                <div class="kr-legend" style="margin-left:auto">
                    <span><i style="background:#e7f6ec"></i>90+</span><span><i style="background:#e8efff"></i>70–89</span>
                    <span><i style="background:#fff4dc"></i>60–69</span><span><i style="background:#fde8e7"></i>60 dan past</span>
                </div>
            </form>

            {{-- ===== 1. Fanlar ===== --}}
            @if($mode === 'subjects')
                <div class="kr-summary">
                    <span>Fanlar: <b>{{ count($rows) }}</b></span>
                    <span>Guruhlar: <b>{{ collect($rows)->sum('groups') }}</b></span>
                    <span>Talabalar: <b>{{ collect($rows)->sum('students') }}</b></span>
                    <span>{{ ($scope ?? null) ? "Fanni bosing — guruhlar bo'yicha baholar ochiladi." : "O'rtacha JN va taqsimotni ko'rish uchun fakultet / yo'nalish · kursni tanlang." }}</span>
                </div>
                @if($rows)
                    <div class="kr-wrap">
                        <table class="kr-table">
                            <thead><tr>
                                <th>№</th><th>Fan</th><th>Yo'nalish</th><th>Kurs · semestr</th><th class="kr-c">Guruhlar</th>
                                <th class="kr-c">Talabalar</th><th class="kr-c">Baholangan</th><th class="kr-c">O'rtacha JN</th><th>Taqsimot</th>
                            </tr></thead>
                            <tbody>
                                @foreach($rows as $r)
                                    @php $u = $r['unit']; $href = route('admin.kafedra-report.subject', $q + ['unit' => $u->key]); $tot = max(1, (int) $r['graded']); @endphp
                                    <tr class="kr-link" onclick="location.href='{{ $href }}'">
                                        <td class="kr-mute">{{ $loop->iteration }}</td>
                                        <td class="kr-name"><a href="{{ $href }}">{{ $u->subject_name }}</a></td>
                                        <td>{{ $u->specialty_name }}</td>
                                        <td class="kr-nw">{{ $u->level_name }} · {{ $u->semester_name }}</td>
                                        <td class="kr-c">{{ $r['groups'] }}</td>
                                        <td class="kr-c">{{ $r['students'] }}</td>
                                        <td class="kr-c">{{ $r['graded'] ?? '—' }}</td>
                                        <td class="kr-c"><span class="kr-pill {{ $tone($r['avg']) }}">{{ $fmt($r['avg']) }}</span></td>
                                        <td>
                                            @if($r['graded'] === null)
                                                <span class="kr-mute">tanlang</span>
                                            @else
                                            <div class="kr-dist" title="90+: {{ $r['a'] }} · 70–89: {{ $r['b'] }} · 60–69: {{ $r['c'] }} · 60 dan past: {{ $r['d'] }}">
                                                <i style="width:{{ 100 * $r['a'] / $tot }}%;background:#22c55e"></i><i style="width:{{ 100 * $r['b'] / $tot }}%;background:#3b82f6"></i>
                                                <i style="width:{{ 100 * $r['c'] / $tot }}%;background:#f59e0b"></i><i style="width:{{ 100 * $r['d'] / $tot }}%;background:#ef4444"></i>
                                            </div>
                                            <div class="kr-dist-legend">{{ $r['a'] }} · {{ $r['b'] }} · {{ $r['c'] }} · {{ $r['d'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="kr-empty">Joriy semestrda kafedraga biriktirilgan fan topilmadi (HEMIS o'quv rejasi bo'yicha).</div>
                @endif
            @endif

            {{-- ===== 2. Guruhlar ===== --}}
            @if($mode === 'groups')
                <div class="kr-summary">
                    <span><b>{{ $unit->subject_name }}</b></span>
                    <span>{{ $unit->specialty_name }} · {{ $unit->level_name }} · {{ $unit->semester_name }}</span>
                    <span>Guruhlar: <b>{{ count($rows) }}</b></span>
                    <span>Talabalar: <b>{{ collect($rows)->sum('students') }}</b></span>
                </div>
                @if($rows)
                    <div class="kr-wrap">
                        <table class="kr-table">
                            <thead><tr>
                                <th>№</th><th>Guruh</th><th>O'qituvchi (baho qo'ygan)</th><th class="kr-c">Talabalar</th>
                                <th class="kr-c">Baholangan</th><th class="kr-c">O'rtacha JN</th><th>Taqsimot</th>
                            </tr></thead>
                            <tbody>
                                @foreach($rows as $r)
                                    @php $g = $r['group']; $href = route('admin.kafedra-report.group', $q + ['unit' => $unit->key, 'group' => $g->group_hemis_id]); $tot = max(1, $r['graded']); @endphp
                                    <tr class="kr-link" onclick="location.href='{{ $href }}'">
                                        <td class="kr-mute">{{ $loop->iteration }}</td>
                                        <td class="kr-name kr-nw"><a href="{{ $href }}">{{ $g->name }}</a> @if($g->lang)<span class="kr-mute">· {{ $g->lang }}</span>@endif</td>
                                        <td>{{ $r['teacher'] ?: '—' }}</td>
                                        <td class="kr-c">{{ $r['students'] }}</td>
                                        <td class="kr-c">{{ $r['graded'] }}</td>
                                        <td class="kr-c"><span class="kr-pill {{ $tone($r['avg']) }}">{{ $fmt($r['avg']) }}</span></td>
                                        <td>
                                            <div class="kr-dist">
                                                <i style="width:{{ 100 * $r['a'] / $tot }}%;background:#22c55e"></i><i style="width:{{ 100 * $r['b'] / $tot }}%;background:#3b82f6"></i>
                                                <i style="width:{{ 100 * $r['c'] / $tot }}%;background:#f59e0b"></i><i style="width:{{ 100 * $r['d'] / $tot }}%;background:#ef4444"></i>
                                            </div>
                                            <div class="kr-dist-legend">{{ $r['a'] }} · {{ $r['b'] }} · {{ $r['c'] }} · {{ $r['d'] }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="kr-empty">Bu fanni o'qiyotgan faol guruh topilmadi.</div>
                @endif
            @endif

            {{-- ===== 3. Guruh talabalari ===== --}}
            @if($mode === 'students')
                <div class="kr-summary">
                    <span><b>{{ $group->name }}</b> · {{ $unit->subject_name }}</span>
                    <span>O'qituvchi: <b>{{ $teacher ?: '—' }}</b></span>
                    <span>Talabalar: <b>{{ $group->students->count() }}</b></span>
                    <span>O'rtacha JN: <b>{{ $fmt($stats['avg']) }}</b></span>
                    <span>Darslar: <b>{{ count($dates) }}</b></span>
                </div>
                <div class="kr-wrap">
                    <table class="kr-table kr-grid">
                        <thead><tr>
                            <th>№</th><th class="sticky1">F.I.Sh.</th>
                            @foreach($dates as $d)<th class="g">{{ \Carbon\Carbon::parse($d)->format('d.m') }}</th>@endforeach
                            <th class="jn">JN</th>
                            @foreach($otherTypes as $t)<th class="ot">{{ $t }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                            @foreach($group->students as $s)
                                @php $sg = $grades[(int) $s->hemis_id][$unit->subject_id] ?? []; @endphp
                                <tr>
                                    <td class="kr-mute">{{ $loop->iteration }}</td>
                                    <td class="sticky1 kr-nw"><span class="kr-name">{{ $s->full_name }}</span></td>
                                    @foreach($dates as $d)
                                        @php $v = $sg['days'][$d] ?? ''; @endphp
                                        <td class="g {{ $v === '' ? '' : $tone($v) }}">{{ $v === '' ? '' : $fmt($v) }}</td>
                                    @endforeach
                                    <td class="jn"><span class="kr-pill {{ $tone($sg['jn'] ?? null) }}">{{ $fmt($sg['jn'] ?? null) }}</span></td>
                                    @foreach($otherTypes as $t)
                                        <td class="ot">{{ implode(' / ', array_map($fmt, $sg['other'][$t] ?? [])) ?: '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
