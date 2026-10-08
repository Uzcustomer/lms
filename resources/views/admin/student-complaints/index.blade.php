<x-app-layout>
<style>
    .sc { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; color: #1e293b; }
    .sc-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .sc h1 { margin: 0; font-size: 22px; font-weight: 700; color: #0f2748; }
    .sc-lead { margin: 4px 0 0; color: #64748b; font-size: 14px; }
    .sc-alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 14px; }
    .sc-alert.ok { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
    .sc-alert.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .sc-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
    .sc-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
    .sc-tab {
        display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 1px solid #dde5ef; border-radius: 999px;
        background: #fff; color: #475569; font-size: 13px; font-weight: 600; text-decoration: none;
    }
    .sc-tab:hover { background: #f8fafc; color: #0f2748; }
    .sc-tab.on { border-color: #1e3a8a; background: #1e3a8a; color: #fff; }
    .sc-tab b { min-width: 20px; padding: 0 6px; border-radius: 999px; background: rgba(15,39,72,.08); font-size: 12px; text-align: center; }
    .sc-tab.on b { background: rgba(255,255,255,.22); }
    .sc-search { display: flex; gap: 6px; }
    .sc-search input { width: 240px; max-width: 60vw; height: 36px; padding: 0 12px; border: 1px solid #c4d0e0; border-radius: 8px; font-family: inherit; font-size: 13.5px; }
    .sc-search button { height: 36px; padding: 0 14px; border: 0; border-radius: 8px; background: #0f2748; color: #fff; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
    .sc-card { margin-bottom: 14px; border: 1px solid #dde5ef; border-radius: 10px; background: #fff; overflow: hidden; }
    .sc-card.is-new { border-left: 4px solid #f59e0b; }
    .sc-card.is-done { border-left: 4px solid #16a34a; }
    .sc-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid #eef2f8; background: #fbfcfe; }
    .sc-who b { display: block; color: #0f2748; font-size: 15px; }
    .sc-meta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 3px; color: #64748b; font-size: 12.5px; }
    .sc-meta a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
    .sc-chip { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .sc-chip.new { background: #fef3c7; color: #92400e; }
    .sc-chip.done { background: #dcfce7; color: #166534; }
    .sc-body { padding: 14px 16px; }
    .sc-msg { margin: 0; color: #1e293b; font-size: 14px; line-height: 1.6; white-space: pre-line; word-break: break-word; }
    .sc-thumbs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .sc-thumbs button { width: 96px; height: 96px; padding: 0; border: 1px solid #dde5ef; border-radius: 8px; overflow: hidden; background: #f1f5f9; cursor: zoom-in; }
    .sc-thumbs img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .sc-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 16px; border-top: 1px solid #eef2f8; }
    .sc-foot small { color: #94a3b8; font-size: 12px; }
    .sc-actions { display: flex; gap: 8px; }
    .sc-btn { height: 34px; padding: 0 14px; border: 0; border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
    .sc-btn.ok { background: #16a34a; color: #fff; }
    .sc-btn.ok:hover { background: #15803d; }
    .sc-btn.del { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
    .sc-btn.del:hover { background: #fef2f2; }
    .sc-empty { padding: 40px 16px; border: 1px dashed #dde5ef; border-radius: 10px; color: #94a3b8; text-align: center; }
    .sc-view { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15,23,42,.88); cursor: zoom-out; }
    .sc-view.on { display: flex; }
    .sc-view img { max-width: 100%; max-height: 100%; border-radius: 6px; box-shadow: 0 10px 40px rgba(0,0,0,.4); }
</style>

<div class="sc">
    <div class="sc-head">
        <div>
            <h1>Shikoyatlar</h1>
            <p class="sc-lead">Xalqaro ta'lim fakulteti talabalarining shikoyatlari. Muammo hal bo'lgach "Hal etildi" ni bosing.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="sc-alert ok">{{ session('success') }}</div>
    @endif
    @if($migrationPending)
        <div class="sc-alert bad">Jadval hali yaratilmagan — serverda <b>php artisan migrate</b> ni ishga tushiring.</div>
    @endif

    <div class="sc-bar">
        <div class="sc-tabs">
            <a class="sc-tab {{ $status === 'new' ? 'on' : '' }}" href="{{ route('admin.student-complaints.index', ['status' => 'new']) }}">Yangi <b>{{ $counts['new'] }}</b></a>
            <a class="sc-tab {{ $status === 'resolved' ? 'on' : '' }}" href="{{ route('admin.student-complaints.index', ['status' => 'resolved']) }}">Hal etilgan <b>{{ $counts['resolved'] }}</b></a>
            <a class="sc-tab {{ $status === 'all' ? 'on' : '' }}" href="{{ route('admin.student-complaints.index', ['status' => 'all']) }}">Hammasi</a>
        </div>
        <form class="sc-search" method="GET" action="{{ route('admin.student-complaints.index') }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" id="sc-q" value="{{ request('q') }}" placeholder="Ism, ID, guruh yoki telefon">
            <button type="submit">Qidirish</button>
        </form>
    </div>

    @forelse($complaints as $complaint)
        <div class="sc-card {{ $complaint->isResolved() ? 'is-done' : 'is-new' }}">
            <div class="sc-card-head">
                <div class="sc-who">
                    <b>{{ $complaint->student_name }}</b>
                    <div class="sc-meta">
                        <span>#{{ $complaint->id }}</span>
                        @if($complaint->group_name)<span>{{ $complaint->group_name }}</span>@endif
                        @if($complaint->student_id_number)<span>ID: {{ $complaint->student_id_number }}</span>@endif
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $complaint->phone) }}">{{ $complaint->phone }}</a>
                        <span>{{ $complaint->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                </div>
                @if($complaint->isResolved())
                    <span class="sc-chip done">Hal etildi</span>
                @else
                    <span class="sc-chip new">Yangi</span>
                @endif
            </div>

            <div class="sc-body">
                <p class="sc-msg">{{ $complaint->message }}</p>
                @if(!empty($complaint->images))
                    <div class="sc-thumbs">
                        @foreach($complaint->images as $i => $path)
                            @php $src = route('admin.student-complaints.image', [$complaint, $i]); @endphp
                            <button type="button" class="sc-zoom" data-src="{{ $src }}" aria-label="Rasmni kattalashtirish">
                                <img src="{{ $src }}" alt="Shikoyat rasmi {{ $i + 1 }}" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="sc-foot">
                <small>
                    @if($complaint->isResolved())
                        Hal etildi: {{ $complaint->resolved_at?->format('d.m.Y H:i') }}@if($complaint->resolved_by_name) · {{ $complaint->resolved_by_name }}@endif
                    @else
                        {{ count($complaint->images ?? []) }} ta rasm
                    @endif
                </small>
                <div class="sc-actions">
                    @unless($complaint->isResolved())
                        <form method="POST" action="{{ route('admin.student-complaints.resolve', $complaint) }}">
                            @csrf
                            <button type="submit" class="sc-btn ok">Hal etildi</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('admin.student-complaints.destroy', $complaint) }}"
                          onsubmit="return confirm('Shikoyat #{{ $complaint->id }} rasmlari bilan butunlay o\'chirilsinmi? Qaytarib bo\'lmaydi.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sc-btn del">O'chirish</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="sc-empty">
            {{ $status === 'resolved' ? "Hal etilgan shikoyat yo'q." : ($status === 'new' ? "Yangi shikoyat yo'q." : "Shikoyat yo'q.") }}
        </div>
    @endforelse

    @if(method_exists($complaints, 'links'))
        <div style="margin-top:12px">{{ $complaints->links() }}</div>
    @endif
</div>

<div class="sc-view" id="scView" role="dialog" aria-label="Rasm"><img src="" alt="Shikoyat rasmi"></div>
<script>
(function () {
    const view = document.getElementById('scView');
    const img = view.querySelector('img');
    document.querySelectorAll('.sc-zoom').forEach((btn) => {
        btn.addEventListener('click', () => { img.src = btn.dataset.src; view.classList.add('on'); });
    });
    view.addEventListener('click', () => view.classList.remove('on'));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') view.classList.remove('on'); });
})();
</script>
</x-app-layout>
