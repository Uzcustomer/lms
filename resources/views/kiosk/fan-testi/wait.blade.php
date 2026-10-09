@extends('kiosk.fan-testi.layout')

@section('title', 'Test kutilmoqda')

@section('styles')
        .w-card { text-align: center; }
        .w-pulse {
            position: relative; width: 96px; height: 96px; margin: 8px auto 22px;
            border-radius: 50%; background: #eaf1fb; display: grid; place-items: center; color: var(--navy);
        }
        .w-pulse::before, .w-pulse::after {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            border: 2px solid rgba(27, 58, 99, .25); animation: w-ring 2.4s ease-out infinite;
        }
        .w-pulse::after { animation-delay: 1.2s; }
        @keyframes w-ring { from { transform: scale(1); opacity: .9; } to { transform: scale(1.7); opacity: 0; } }
        .w-title { margin: 0 0 8px; color: var(--navy); font-size: clamp(24px, 4vw, 34px); font-weight: 800; }
        .w-sub { margin: 0 auto; max-width: 520px; color: var(--ink-soft); font-size: 15.5px; line-height: 1.6; }
        .w-device {
            display: inline-flex; align-items: center; gap: 10px; margin-top: 22px; padding: 10px 18px;
            border: 1px solid var(--line); border-radius: 999px; background: #f7f9fc;
            color: var(--navy); font-size: 15px; font-weight: 700;
        }
        .w-device span { color: var(--muted); font-weight: 600; }
        .w-state { margin-top: 16px; color: var(--muted); font-size: 13px; }
        .w-state.ok::before, .w-state.bad::before {
            content: ''; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; vertical-align: 1px;
        }
        .w-state.ok::before { background: #16a34a; }
        .w-state.bad::before { background: #dc2626; }
        @media (prefers-reduced-motion: reduce) { .w-pulse::before, .w-pulse::after { animation: none; } }
@endsection

@section('content')
    <div class="k-card w-card">
        <div class="k-body" style="padding-top:38px;padding-bottom:38px">
            @if($device)
                <div class="w-pulse" aria-hidden="true">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>
                </div>
                <h1 class="w-title">Test boshlanishini kuting</h1>
                <p class="w-sub">O'qituvchi testni boshlaganda test oynasi shu yerda o'zi ochiladi. Sahifani yopmang.</p>
                <div class="w-device">
                    @if($device->room)<span>{{ $device->room }}-xona</span>·@endif
                    {{ $device->name }}
                </div>
                <div class="w-state ok" id="wState" aria-live="polite">Server bilan aloqa bor</div>
            @else
                <h1 class="w-title">Bu kompyuter ro'yxatdan o'tmagan</h1>
                <p class="w-sub">Test kompyuteri sifatida ishlatish uchun administrator shu kompyuterda <b>/test-kompyuter</b> sahifasi orqali uni ro'yxatdan o'tkazishi kerak.</p>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
@if($device)
<script>
(function () {
    const statusUrl = @json(route('test-devices.status'));
    const state = document.getElementById('wState');

    async function check() {
        try {
            const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!r.ok) throw new Error();
            const d = await r.json();
            if (!d.registered) { window.location.reload(); return; }
            state.className = 'w-state ok';
            state.textContent = 'Server bilan aloqa bor · ' + new Date().toLocaleTimeString().slice(0, 5);
            if (d.test_url) {
                state.textContent = 'Test ochilmoqda...';
                window.location.href = d.test_url;
            }
        } catch (e) {
            state.className = 'w-state bad';
            state.textContent = "Server bilan aloqa yo'q — qayta urinilmoqda";
        }
    }

    check();
    setInterval(check, 4000);
})();
</script>
@endif
@endsection
