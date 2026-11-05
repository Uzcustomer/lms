<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Qayta topshirish bronlari</h2>
    </x-slot>

    <style>
        .rs-wrap { padding: 16px; max-width: 1600px; margin: 0 auto; }
        .rs-hero { position: relative; overflow: hidden; border-radius: 16px; padding: 20px 22px; color: #fff;
            background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%); box-shadow: 0 12px 30px rgba(15, 39, 72, .2); }
        .rs-hero::after { content: ""; position: absolute; right: -50px; top: -50px; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,.07); }
        .rs-hero small { text-transform: uppercase; letter-spacing: .08em; font-size: 11px; font-weight: 700; opacity: .75; }
        .rs-hero h1 { margin: 2px 0 0; font-size: 22px; font-weight: 800; }
        .rs-top { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; position: relative; z-index: 1; }
        .rs-nav { display: flex; align-items: center; gap: 6px; }
        .rs-nav a { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 12px; border-radius: 10px;
            background: rgba(255,255,255,.14); color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid rgba(255,255,255,.18); }
        .rs-nav a:hover { background: rgba(255,255,255,.24); }
        .rs-nav input { height: 36px; border-radius: 10px; border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.95); color: #0f172a; padding: 0 10px; font-size: 13px; }
        .rs-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; position: relative; z-index: 1; }
        .rs-stat { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.16); border-radius: 12px; padding: 8px 14px; min-width: 110px; }
        .rs-stat b { display: block; font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .rs-stat span { font-size: 12px; opacity: .8; }

        .rs-legend { display: flex; flex-wrap: wrap; gap: 14px; margin: 14px 2px; font-size: 12px; color: #475569; }
        .rs-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }

        .rs-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.05); overflow: hidden; }
        .rs-scroll { overflow-x: auto; }
        table.rs-grid { width: 100%; min-width: 980px; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        .rs-grid th, .rs-grid td { border-bottom: 1px solid #eef2f7; border-right: 1px solid #eef2f7; vertical-align: top; }
        .rs-grid th:last-child, .rs-grid td:last-child { border-right: 0; }
        .rs-grid thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; padding: 10px 8px; text-align: left; }
        .rs-grid thead th.rs-time { width: 92px; }
        .rs-day { font-size: 13px; font-weight: 800; color: #0f172a; }
        .rs-date { font-size: 12px; color: #64748b; font-weight: 600; }
        .rs-count { float: right; min-width: 24px; height: 22px; padding: 0 7px; border-radius: 999px; background: #e0e7ff; color: #3730a3;
            font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
        .rs-today { background: #eef2ff !important; box-shadow: inset 0 3px 0 #4338ca; }
        .rs-today .rs-day { color: #3730a3; }
        .rs-past .rs-day, .rs-past .rs-date { opacity: .6; }
        td.rs-time { padding: 10px 8px; font-size: 12.5px; font-weight: 800; color: #334155; background: #fbfdff; white-space: nowrap; font-variant-numeric: tabular-nums; }
        td.rs-cell { padding: 6px; min-height: 64px; height: 64px; }
        td.rs-cell.rs-today-col { background: #fafaff; }
        td.rs-closed { background: repeating-linear-gradient(135deg, #f8fafc, #f8fafc 6px, #f1f5f9 6px, #f1f5f9 12px); }
        tr.rs-break td { background: #fff7ed; color: #9a3412; font-size: 12px; font-weight: 700; text-align: center; padding: 6px; }
        tr.rs-break td.rs-time { color: #9a3412; background: #fff7ed; text-align: left; }

        .rs-b { border-radius: 10px; padding: 6px 8px; margin-bottom: 6px; border: 1px solid; font-size: 12px; line-height: 1.35; }
        .rs-b:last-child { margin-bottom: 0; }
        .rs-b b { display: block; font-size: 12.5px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rs-b .rs-meta { color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rs-b .rs-sub { color: #1e3a8a; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rs-b.booked { background: #eff6ff; border-color: #bfdbfe; }
        .rs-b.now { background: #ecfdf5; border-color: #6ee7b7; box-shadow: 0 0 0 2px rgba(16,185,129,.15); }
        .rs-b.past { background: #f8fafc; border-color: #e2e8f0; opacity: .75; }
        .rs-b.missed { background: #fef2f2; border-color: #fecaca; }
        .rs-b.missed .rs-sub { color: #b91c1c; }
        .rs-tag { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 6px; font-size: 10.5px; font-weight: 800; vertical-align: 1px; }
        .rs-tag.nb { background: #fee2e2; color: #991b1b; }
        .rs-tag.low { background: #fef3c7; color: #92400e; }
        .rs-tag.miss { background: #fecaca; color: #7f1d1d; }
        .rs-tag.live { background: #d1fae5; color: #065f46; }
        .rs-empty { padding: 50px 20px; text-align: center; color: #64748b; font-size: 14px; }
    </style>

    @php
        $fmt = fn ($d) => $d->format('d.m.Y');
    @endphp

    <div class="rs-wrap">
        <div class="rs-hero">
            <div class="rs-top">
                <div>
                    <small>Test markazi · haftalik jadval</small>
                    <h1>{{ $fmt($monday) }} — {{ $fmt($saturday) }}</h1>
                </div>
                <form class="rs-nav" method="GET" action="{{ route('admin.retake-bookings.schedule') }}">
                    <a href="{{ route('admin.retake-bookings.schedule', ['week' => $prevWeek]) }}" title="Oldingi hafta">‹ Oldingi</a>
                    @unless($isCurrentWeek)
                        <a href="{{ route('admin.retake-bookings.schedule') }}">Bu hafta</a>
                    @endunless
                    <a href="{{ route('admin.retake-bookings.schedule', ['week' => $nextWeek]) }}" title="Keyingi hafta">Keyingi ›</a>
                    <input type="date" name="week" value="{{ $monday->format('Y-m-d') }}" onchange="this.form.submit()" aria-label="Hafta">
                </form>
            </div>
            <div class="rs-stats">
                <div class="rs-stat"><b>{{ $total }}</b><span>bron</span></div>
                <div class="rs-stat"><b>{{ $students }}</b><span>talaba</span></div>
                <div class="rs-stat"><b>{{ $missed }}</b><span>kelmagan</span></div>
            </div>
        </div>

        <div class="rs-legend">
            <span><i style="background:#bfdbfe"></i>Kutilmoqda</span>
            <span><i style="background:#6ee7b7"></i>Hozir (vaqti ketyapti)</span>
            <span><i style="background:#e2e8f0"></i>Vaqti o'tgan</span>
            <span><i style="background:#fecaca"></i>Kelmagan</span>
            <span><i style="background:#f1f5f9"></i>Yopiq vaqt (shanba — qisqa kun)</span>
        </div>

        <div class="rs-card">
            @if($total === 0)
                <div class="rs-empty">Bu haftada qayta topshirish bronlari yo'q.</div>
            @else
                <div class="rs-scroll">
                    <table class="rs-grid">
                        <thead>
                            <tr>
                                <th class="rs-time"><span class="rs-date">Vaqt</span></th>
                                @foreach($days as $day)
                                    <th class="{{ $day['is_today'] ? 'rs-today' : '' }} {{ $day['is_past'] ? 'rs-past' : '' }}">
                                        @if($day['count'])<span class="rs-count">{{ $day['count'] }}</span>@endif
                                        <div class="rs-day">{{ $day['name'] }}</div>
                                        <div class="rs-date">{{ $day['date']->format('d.m') }}{{ $day['is_today'] ? ' · bugun' : '' }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php $breakShown = false; @endphp
                            @foreach($slots as $key => [$from, $to])
                                @foreach($breaks as [$bs, $be])
                                    @if(!$breakShown && $from >= $be)
                                        <tr class="rs-break"><td class="rs-time">{{ $bs }}–{{ $be }}</td><td colspan="6">Tushlik</td></tr>
                                        @php $breakShown = true; @endphp
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="rs-time">{{ $from }}–{{ $to }}</td>
                                    @foreach($days as $i => $day)
                                        @php $items = $grid[$i][$key] ?? []; $closed = !isset($day['open'][$key]) && !$items; @endphp
                                        <td class="rs-cell {{ $closed ? 'rs-closed' : '' }} {{ $day['is_today'] ? 'rs-today-col' : '' }}">
                                            @foreach($items as $b)
                                                <div class="rs-b {{ $b['state'] }}" title="{{ $b['name'] }} — {{ $b['group'] }} — {{ $b['subject'] }}{{ $b['mavzu'] ? ', ' . $b['mavzu'] . '-mavzu' : '' }}">
                                                    <b>{{ $b['name'] }}</b>
                                                    <div class="rs-meta">{{ $b['group'] }}
                                                        <span class="rs-tag {{ $b['reason'] === 'NB' ? 'nb' : 'low' }}">{{ $b['reason'] }}</span>
                                                        @if($b['state'] === 'missed')<span class="rs-tag miss">kelmadi</span>@endif
                                                        @if($b['state'] === 'now')<span class="rs-tag live">hozir</span>@endif
                                                    </div>
                                                    <div class="rs-sub">{{ $b['subject'] }}{{ $b['mavzu'] ? ' · ' . $b['mavzu'] . '-mavzu' : '' }}</div>
                                                </div>
                                            @endforeach
                                        </td>
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
