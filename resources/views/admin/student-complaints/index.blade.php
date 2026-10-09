<x-app-layout>
<style>
    /* Shikoyatlar — LMS ranglari; Tailwind yig'ilmasa ham to'g'ri chiqishi uchun o'z uslubi */
    .scx { --navy: #102a56; --blue: #1e5da8; --ink: #0f1f38; --ink-2: #44546c; --muted: #8494aa;
           --line: #e3eaf3; --line-2: #eef3f9; --bg: #eef3f9; --card: #ffffff;
           --amber: #d97706; --amber-bg: #fff6e5; --green: #059669; --green-bg: #e7f8f1; --sky: #0284c7; --sky-bg: #e6f4fc; --red: #c0262d; }
    .scx { min-height: 100%; padding: 22px 24px 40px; background: var(--bg); color: var(--ink); }
    .scx * { box-sizing: border-box; }
    .scx-wrap { width: 100%; display: flex; flex-direction: column; gap: 16px; }

    /* Sarlavha */
    .scx-hero {
        display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        padding: 20px 26px; border-radius: 18px; color: #fff;
        background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%);
        box-shadow: 0 10px 30px rgba(16, 42, 86, .18);
    }
    .scx-hero-l { display: flex; align-items: center; gap: 16px; }
    .scx-hero-ic { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: rgba(255,255,255,.14); }
    .scx-hero h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.01em; }
    .scx-hero p { margin: 3px 0 0; font-size: 13.5px; color: rgba(255,255,255,.78); }
    .scx-hero-badge { padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 13px; font-weight: 700; white-space: nowrap; }
    .scx-hero-badge b { color: #fde68a; }

    .scx-alert { padding: 11px 16px; border-radius: 12px; font-size: 14px; font-weight: 600; }
    .scx-alert.ok { background: var(--green-bg); color: #065f46; border: 1px solid #a7f3d0; }
    .scx-alert.bad { background: #fdecec; color: #991b1b; border: 1px solid #fecaca; }

    /* Statistika */
    .scx-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .scx-stat {
        display: flex; align-items: center; gap: 14px; padding: 16px 18px;
        border: 1px solid var(--line); border-radius: 16px; background: var(--card);
        color: inherit; text-decoration: none; transition: transform .15s, box-shadow .15s, border-color .15s;
    }
    .scx-stat:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(16,42,86,.08); }
    .scx-stat.on { border-color: currentColor; box-shadow: 0 0 0 3px rgba(30,93,168,.08); }
    .scx-stat-ic { display: grid; place-items: center; flex: none; width: 46px; height: 46px; border-radius: 13px; }
    .scx-stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .scx-stat b { display: block; margin-top: 2px; color: var(--ink); font-size: 26px; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .scx-stat.sky { color: var(--sky); } .scx-stat.sky .scx-stat-ic { background: var(--sky-bg); color: var(--sky); }
    .scx-stat.amber { color: var(--amber); } .scx-stat.amber .scx-stat-ic { background: var(--amber-bg); color: var(--amber); }
    .scx-stat.green { color: var(--green); } .scx-stat.green .scx-stat-ic { background: var(--green-bg); color: var(--green); }

    /* Ro'yxat paneli */
    .scx-panel { border: 1px solid var(--line); border-radius: 18px; background: var(--card); overflow: hidden; box-shadow: 0 2px 10px rgba(16,42,86,.04); }
    .scx-tools { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--line); background: #f8fafd; }
    .scx-tools h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--ink); }
    .scx-tools small { color: var(--muted); font-size: 12.5px; }
    .scx-filter { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .scx-seg { display: inline-flex; padding: 3px; border-radius: 11px; background: #e9eff7; }
    .scx-seg a { padding: 6px 13px; border-radius: 8px; color: var(--ink-2); font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .scx-seg a.on { background: #fff; color: var(--navy); box-shadow: 0 1px 3px rgba(16,42,86,.12); }
    .scx-search { position: relative; }
    .scx-search svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
    .scx-search input {
        width: 260px; max-width: 70vw; height: 38px; padding: 0 12px 0 34px;
        border: 1px solid #cfd9e6; border-radius: 10px; background: #fff; color: var(--ink);
        font-family: inherit; font-size: 13.5px; outline: none;
    }
    .scx-search input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(30,93,168,.12); }
    .scx-clear { color: var(--muted); font-size: 12.5px; font-weight: 600; text-decoration: none; }
    .scx-clear:hover { color: var(--blue); }

    /* Jadval */
    .scx-scroll { overflow-x: auto; }
    .scx-table { width: 100%; min-width: 1160px; table-layout: auto; border-collapse: collapse; font-size: 13.5px; }
    .scx-table th {
        padding: 11px 16px; border-bottom: 1px solid var(--line); background: #f3f7fc;
        color: #5b6b82; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-align: left; text-transform: uppercase; white-space: nowrap;
    }
    .scx-table td { padding: 16px; border-bottom: 1px solid var(--line-2); vertical-align: top; }
    .scx-table tr:last-child td { border-bottom: 0; }
    .scx-table tbody tr { transition: background .12s; }
    .scx-table tbody tr:hover { background: #f8fbff; }
    .scx-table tr.is-new td:first-child { box-shadow: inset 3px 0 0 var(--amber); }
    .scx-num { color: #a3b1c4; font-weight: 700; font-variant-numeric: tabular-nums; }

    .scx-who { display: flex; align-items: center; gap: 11px; min-width: 210px; }
    .scx-avatar { display: grid; place-items: center; flex: none; width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #dbe8f8, #c3d7f0); color: var(--navy); font-size: 13px; font-weight: 800; }
    .scx-who b { display: block; color: var(--ink); font-size: 14px; font-weight: 800; }
    .scx-who small { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }
    .scx-study { color: var(--ink-2); font-size: 12.5px; line-height: 1.5; min-width: 150px; }
    .scx-tag { display: inline-block; margin-top: 4px; padding: 2px 9px; border-radius: 6px; background: #eef3fa; color: var(--navy); font-size: 12px; font-weight: 700; }
    .scx-phone { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 9px; background: #eef5fe; color: var(--blue); font-weight: 700; text-decoration: none; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .scx-phone:hover { background: #e0edfd; }

    .scx-msg { min-width: 260px; max-width: 640px; }
    .scx-msg p { margin: 0; color: var(--ink); font-size: 14px; line-height: 1.55; white-space: pre-line; word-break: break-word; }
    .scx-msg p.clamp { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .scx-more { margin-top: 4px; padding: 0; border: 0; background: none; color: var(--blue); font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }

    .scx-thumbs { display: flex; gap: 6px; }
    .scx-thumb { position: relative; width: 58px; height: 58px; padding: 0; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: #f1f5fa; cursor: zoom-in; }
    .scx-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .scx-thumb em { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(15,31,56,.6); color: #fff; font-size: 14px; font-style: normal; font-weight: 800; }
    .scx-none { color: #b3bfcf; }

    .scx-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; font-size: 12.5px; font-weight: 800; white-space: nowrap; }
    .scx-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .scx-pill.new { background: var(--amber-bg); color: var(--amber); }
    .scx-pill.done { background: var(--green-bg); color: var(--green); }
    .scx-meta { margin-top: 6px; color: var(--muted); font-size: 12px; line-height: 1.4; }
    .scx-time b { display: block; color: var(--ink-2); font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .scx-time small { color: var(--muted); font-size: 12px; }

    .scx-acts { display: flex; flex-direction: column; align-items: stretch; gap: 7px; min-width: 128px; }
    .scx-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; height: 34px; padding: 0 12px; border-radius: 9px; font-family: inherit; font-size: 13px; font-weight: 800; cursor: pointer; transition: background .15s, border-color .15s; }
    .scx-btn.ok { border: 0; background: var(--green); color: #fff; }
    .scx-btn.ok:hover { background: #047857; }
    .scx-btn.del { border: 1px solid #f5c2c4; background: #fff; color: var(--red); }
    .scx-btn.del:hover { background: #fdf0f0; border-color: #eba3a6; }
    .scx-btn:focus-visible, .scx-seg a:focus-visible, .scx-stat:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }

    .scx-empty { padding: 56px 16px; text-align: center; }
    .scx-empty-ic { display: inline-grid; place-items: center; width: 64px; height: 64px; margin-bottom: 12px; border-radius: 50%; background: var(--green-bg); color: var(--green); }
    .scx-empty b { display: block; color: var(--ink); font-size: 16px; font-weight: 800; }
    .scx-empty span { color: var(--muted); font-size: 13.5px; }
    .scx-pages { padding: 12px 18px; border-top: 1px solid var(--line); }

    .scx-view { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 24px; background: rgba(10,20,38,.88); cursor: zoom-out; }
    .scx-view.on { display: flex; }
    .scx-view img { max-width: 100%; max-height: 100%; border-radius: 10px; box-shadow: 0 16px 50px rgba(0,0,0,.45); }

    @media (max-width: 900px) {
        .scx { padding: 16px 16px 32px; }
        .scx-stats { grid-template-columns: 1fr; }
    }
    @media (prefers-reduced-motion: reduce) { .scx-stat, .scx-table tbody tr { transition: none; } }
</style>

@php
    $currentStatus = request('status');
    $statusUrl = fn ($s) => route('admin.student-complaints.index', array_filter(['status' => $s, 'search' => request('search')]));
    $initials = function ($name) {
        $parts = preg_split('/\s+/u', trim((string) $name));
        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    };
@endphp

<div class="scx">
    <div class="scx-wrap">
        <div class="scx-hero">
            <div class="scx-hero-l">
                <div class="scx-hero-ic" aria-hidden="true">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l3.2-3.2A2 2 0 018.6 16H19a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14z"/></svg>
                </div>
                <div>
                    <h1>Shikoyatlar</h1>
                    <p>Xalqaro ta'lim fakulteti talabalarining shikoyatlari — ko'rib chiqing va hal etilgach belgilang</p>
                </div>
            </div>
            @if($stats['new'] > 0)
                <div class="scx-hero-badge"><b>{{ $stats['new'] }}</b> ta yangi shikoyat kutmoqda</div>
            @else
                <div class="scx-hero-badge">Yangi shikoyat yo'q</div>
            @endif
        </div>

        @if(session('success'))
            <div class="scx-alert ok">{{ session('success') }}</div>
        @endif
        @if($migrationPending)
            <div class="scx-alert bad">Jadval hali yaratilmagan — serverda <b>php artisan migrate</b> ni ishga tushiring.</div>
        @endif

        <div class="scx-stats">
            <a class="scx-stat sky {{ !$currentStatus ? 'on' : '' }}" href="{{ $statusUrl(null) }}">
                <div class="scx-stat-ic" aria-hidden="true"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg></div>
                <div><span>Jami</span><b>{{ $stats['total'] }}</b></div>
            </a>
            <a class="scx-stat amber {{ $currentStatus === 'new' ? 'on' : '' }}" href="{{ $statusUrl('new') }}">
                <div class="scx-stat-ic" aria-hidden="true"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div><span>Yangi</span><b>{{ $stats['new'] }}</b></div>
            </a>
            <a class="scx-stat green {{ $currentStatus === 'resolved' ? 'on' : '' }}" href="{{ $statusUrl('resolved') }}">
                <div class="scx-stat-ic" aria-hidden="true"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div><span>Hal etilgan</span><b>{{ $stats['resolved'] }}</b></div>
            </a>
        </div>

        <div class="scx-panel">
            <div class="scx-tools">
                <div>
                    <h3>Shikoyatlar ro'yxati</h3>
                    <small>Topildi: {{ $complaints->total() }} ta</small>
                </div>
                <form class="scx-filter" method="GET" action="{{ route('admin.student-complaints.index') }}">
                    <div class="scx-seg" role="tablist" aria-label="Holat">
                        <a href="{{ $statusUrl(null) }}" class="{{ !$currentStatus ? 'on' : '' }}">Barchasi</a>
                        <a href="{{ $statusUrl('new') }}" class="{{ $currentStatus === 'new' ? 'on' : '' }}">Yangi</a>
                        <a href="{{ $statusUrl('resolved') }}" class="{{ $currentStatus === 'resolved' ? 'on' : '' }}">Hal etilgan</a>
                    </div>
                    @if($currentStatus)
                        <input type="hidden" name="status" value="{{ $currentStatus }}">
                    @endif
                    <label class="scx-search" for="complaint-search">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                        <input id="complaint-search" type="search" name="search" value="{{ request('search') }}" placeholder="Ism, ID, guruh yoki telefon" aria-label="Qidirish">
                    </label>
                    @if(request()->filled('search') || $currentStatus)
                        <a class="scx-clear" href="{{ route('admin.student-complaints.index') }}">Tozalash</a>
                    @endif
                </form>
            </div>

            @if($complaints->isEmpty())
                <div class="scx-empty">
                    <div class="scx-empty-ic" aria-hidden="true"><svg width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <b>{{ request()->filled('search') ? 'Hech narsa topilmadi' : ($currentStatus === 'resolved' ? "Hal etilgan shikoyat yo'q" : "Shikoyat yo'q") }}</b>
                    <span>{{ request()->filled('search') ? "Qidiruv so'zini o'zgartirib ko'ring." : "Yangi shikoyat kelganda shu yerda va Telegram guruhda ko'rinadi." }}</span>
                </div>
            @else
                <div class="scx-scroll">
                    <table class="scx-table">
                        <thead>
                            <tr>
                                <th>№</th><th>Talaba</th><th>O'qish ma'lumoti</th><th>Telefon</th><th>Shikoyat</th><th>Rasmlar</th><th>Holat</th><th>Vaqt</th><th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($complaints as $complaint)
                            @php $images = array_values($complaint->images ?? []); @endphp
                            <tr class="{{ $complaint->isResolved() ? '' : 'is-new' }}">
                                <td class="scx-num">{{ ($complaints->firstItem() ?? 1) + $loop->index }}</td>
                                <td>
                                    <div class="scx-who">
                                        <div class="scx-avatar" aria-hidden="true">{{ $initials($complaint->student_name) }}</div>
                                        <div><b>{{ $complaint->student_name }}</b><small>ID: {{ $complaint->student_id_number ?? '—' }}</small></div>
                                    </div>
                                </td>
                                <td class="scx-study">
                                    {{ $complaint->faculty_name ?? '—' }}
                                    @if($complaint->group_name)<br><span class="scx-tag">{{ $complaint->group_name }}</span>@endif
                                </td>
                                <td>
                                    <a class="scx-phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $complaint->phone) }}">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.28 6.72 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.37c0-.52-.35-.97-.85-1.09l-4.42-1.1a1.13 1.13 0 00-1.17.42l-.97 1.29a1.13 1.13 0 01-1.21.38 12.04 12.04 0 01-7.14-7.14 1.13 1.13 0 01.38-1.21l1.29-.97c.36-.27.52-.73.42-1.17l-1.1-4.42a1.13 1.13 0 00-1.09-.85H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                        {{ $complaint->phone }}
                                    </a>
                                </td>
                                <td class="scx-msg">
                                    <p class="{{ mb_strlen($complaint->message) > 160 ? 'clamp' : '' }}">{{ $complaint->message }}</p>
                                    @if(mb_strlen($complaint->message) > 160)
                                        <button type="button" class="scx-more">Ko'proq</button>
                                    @endif
                                </td>
                                <td>
                                    @if(count($images))
                                        <div class="scx-thumbs">
                                            @foreach($images as $i => $path)
                                                @php $src = route('admin.student-complaints.image', [$complaint, $i]); @endphp
                                                @if($i < 3)
                                                    <button type="button" class="scx-thumb" data-src="{{ $src }}" data-group="c{{ $complaint->id }}" aria-label="Rasm {{ $i + 1 }} ni kattalashtirish">
                                                        <img src="{{ $src }}" alt="Shikoyat rasmi {{ $i + 1 }}" loading="lazy">
                                                        @if($i === 2 && count($images) > 3)<em>+{{ count($images) - 3 }}</em>@endif
                                                    </button>
                                                @else
                                                    <span hidden class="scx-thumb-extra" data-src="{{ $src }}" data-group="c{{ $complaint->id }}"></span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="scx-none">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($complaint->isResolved())
                                        <span class="scx-pill done">Hal etilgan</span>
                                        <div class="scx-meta">{{ $complaint->resolved_at?->format('d.m.Y H:i') }}@if($complaint->resolved_by_name)<br>{{ $complaint->resolved_by_name }}@endif</div>
                                    @else
                                        <span class="scx-pill new">Yangi</span>
                                    @endif
                                </td>
                                <td class="scx-time">
                                    <b>{{ $complaint->created_at?->format('d.m.Y H:i') }}</b>
                                    <small>{{ $complaint->created_at?->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <div class="scx-acts">
                                        @unless($complaint->isResolved())
                                            <form method="POST" action="{{ route('admin.student-complaints.resolve', $complaint) }}">
                                                @csrf
                                                <button type="submit" class="scx-btn ok">
                                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Hal etildi
                                                </button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('admin.student-complaints.destroy', $complaint) }}"
                                              onsubmit="return confirm('Shikoyat rasmlari bilan butunlay o\'chirilsinmi? Qaytarib bo\'lmaydi.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="scx-btn del">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 0l1 12a1 1 0 001 1h4a1 1 0 001-1l1-12"/></svg>
                                                O'chirish
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($complaints->hasPages())<div class="scx-pages">{{ $complaints->links() }}</div>@endif
            @endif
        </div>
    </div>
</div>

{{-- Rasmni kattalashtirish: chap/o'ng tugmalar bilan shu shikoyatning rasmlari bo'ylab yuriladi --}}
<div class="scx-view" id="scxView" role="dialog" aria-label="Rasm"><img src="" alt="Shikoyat rasmi"></div>
<script>
(function () {
    const view = document.getElementById('scxView');
    const img = view.querySelector('img');
    let list = [], pos = 0;
    const show = () => { img.src = list[pos]; view.classList.add('on'); };
    const close = () => view.classList.remove('on');

    document.querySelectorAll('.scx-thumb').forEach((btn) => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.group;
            list = Array.from(document.querySelectorAll('[data-group="' + group + '"]')).map((el) => el.dataset.src);
            pos = Math.max(0, list.indexOf(btn.dataset.src));
            show();
        });
    });
    view.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (!view.classList.contains('on')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight' && list.length) { pos = (pos + 1) % list.length; show(); }
        if (e.key === 'ArrowLeft' && list.length) { pos = (pos - 1 + list.length) % list.length; show(); }
    });

    // Uzun shikoyat matnini ochish/yopish
    document.querySelectorAll('.scx-more').forEach((btn) => {
        btn.addEventListener('click', () => {
            const p = btn.previousElementSibling;
            const open = p.classList.toggle('clamp') === false;
            btn.textContent = open ? 'Yopish' : "Ko'proq";
        });
    });
})();
</script>
</x-app-layout>
