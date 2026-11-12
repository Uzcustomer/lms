{{-- Kichik guruhlar (a/b/c) qaydnomasi: boshqa guruh o'qituvchisi tasdiqlagan — "sizning tasdig'ingiz kutilmoqda" --}}
@php
    $ynqPending = [];
    if (function_exists('is_active_oqituvchi') && is_active_oqituvchi()) {
        $ynqTeacher = auth('teacher')->user();
        if (!$ynqTeacher instanceof \App\Models\Teacher) {
            $ynqHemis = get_teacher_hemis_id();
            $ynqTeacher = $ynqHemis ? \App\Models\Teacher::where('hemis_id', $ynqHemis)->first() : null;
        }
        try {
            $ynqPending = $ynqTeacher ? \App\Services\YnqPending::forTeacher($ynqTeacher) : [];
        } catch (\Throwable $e) {
            report($e);
        }
    }
@endphp
@if($ynqPending)
    <div id="ynq-pending" class="ynqp-overlay" role="dialog" aria-modal="true" aria-labelledby="ynqp-title" hidden
         data-key="{{ md5(json_encode($ynqPending)) }}">
        <div class="ynqp-card">
            <div class="ynqp-head">
                <span class="ynqp-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h2m-4 13h10a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4A2 2 0 0013.6 2H7a2 2 0 00-2 2v15a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <h2 id="ynqp-title">Vedomost tasdig'ingiz kutilmoqda</h2>
                    <p class="ynqp-sub">Kichik guruhlar bitta umumiy vedomostda. Boshqa guruh o'qituvchisi tasdiqladi — kafedra mudiriga o'tishi uchun sizning guruhingiz ham tasdiqlashi kerak.</p>
                </div>
                <button type="button" class="ynqp-x" onclick="ynqpClose()" aria-label="Yopish">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <ul class="ynqp-list">
                @foreach($ynqPending as $item)
                    <li class="ynqp-item">
                        <div class="ynqp-top">
                            <div class="ynqp-subject">{{ $item['subject'] }}</div>
                            <span class="ynqp-chip">{{ $item['group'] }}</span>
                        </div>

                        <div class="ynqp-progress" aria-label="{{ $item['done'] }} / {{ $item['total'] }} kichik guruh tasdiqladi">
                            <div class="ynqp-bars">
                                @for($i = 0; $i < $item['total']; $i++)
                                    <span class="{{ $i < $item['done'] ? 'is-done' : '' }}"></span>
                                @endfor
                            </div>
                            <span class="ynqp-count">{{ $item['done'] }} / {{ $item['total'] }} kichik guruh tasdiqladi</span>
                        </div>

                        <div class="ynqp-people">
                            @foreach($item['by'] as $by)
                                <div class="ynqp-person">
                                    <span class="ynqp-check" aria-hidden="true">✓</span>
                                    <span><b>{{ $by['teacher'] }}</b><span class="ynqp-muted">{{ $by['group'] }}</span></span>
                                </div>
                            @endforeach
                            <div class="ynqp-person ynqp-person--you">
                                <span class="ynqp-wait" aria-hidden="true">…</span>
                                <span><b>Siz</b><span class="ynqp-muted">{{ $item['group'] }} — kutilmoqda</span></span>
                            </div>
                        </div>

                        @unless($item['ready'])
                            <div class="ynqp-warn">Avval jurnalda YN yuboring, so'ng qaydnomani tekshirib tasdiqlaysiz.</div>
                        @endunless
                        <a class="ynqp-btn" href="{{ $item['url'] }}" @if($item['ready']) target="_blank" rel="noopener" @endif>
                            {{ $item['ready'] ? 'Tekshirish va tasdiqlash' : 'Jurnalni ochish' }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="ynqp-foot">
                <button type="button" class="ynqp-later" onclick="ynqpClose()">Keyinroq eslatish</button>
            </div>
        </div>
    </div>
    <style>
        .ynqp-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px;
            background: rgba(15, 23, 42, .5); backdrop-filter: blur(3px); animation: ynqp-fade .18s ease-out; }
        .ynqp-overlay[hidden] { display: none; }
        .ynqp-card { width: 100%; max-width: 540px; max-height: calc(100vh - 32px); overflow-y: auto; background: #fff; border-radius: 20px;
            box-shadow: 0 30px 70px rgba(15, 23, 42, .35); font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; animation: ynqp-rise .22s ease-out; }
        .ynqp-head { position: relative; display: flex; gap: 14px; align-items: flex-start; padding: 22px 52px 18px 22px;
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border-bottom: 1px solid #e2e8f0; border-radius: 20px 20px 0 0; }
        .ynqp-icon { flex-shrink: 0; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            background: #1e3a8a; color: #fff; box-shadow: 0 6px 14px rgba(30, 58, 138, .3); }
        .ynqp-icon svg { width: 24px; height: 24px; }
        .ynqp-head h2 { margin: 2px 0 0; font-size: 19px; font-weight: 750; color: #0f172a; letter-spacing: -.01em; }
        .ynqp-sub { margin: 6px 0 0; font-size: 13.5px; line-height: 1.5; color: #475569; }
        .ynqp-x { position: absolute; top: 14px; right: 14px; width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .ynqp-x:hover { background: rgba(15, 23, 42, .06); color: #0f172a; }
        .ynqp-x svg { width: 18px; height: 18px; }
        .ynqp-list { list-style: none; margin: 0; padding: 18px 22px 4px; display: flex; flex-direction: column; gap: 14px; }
        .ynqp-item { padding: 16px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .ynqp-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .ynqp-subject { font-size: 15.5px; font-weight: 700; color: #0f172a; line-height: 1.35; }
        .ynqp-chip { flex-shrink: 0; padding: 4px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12.5px; font-weight: 700; white-space: nowrap; }
        .ynqp-progress { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
        .ynqp-bars { display: flex; gap: 4px; flex: 0 0 auto; }
        .ynqp-bars span { width: 26px; height: 6px; border-radius: 999px; background: #e2e8f0; }
        .ynqp-bars span.is-done { background: #16a34a; }
        .ynqp-count { font-size: 12.5px; color: #475569; font-variant-numeric: tabular-nums; }
        .ynqp-people { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e2e8f0; }
        .ynqp-person { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: #0f172a; }
        .ynqp-person b { font-weight: 650; }
        .ynqp-muted { display: block; font-size: 12px; color: #64748b; }
        .ynqp-check, .ynqp-wait { flex-shrink: 0; width: 24px; height: 24px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; }
        .ynqp-check { background: #dcfce7; color: #15803d; }
        .ynqp-wait { background: #fef3c7; color: #b45309; }
        .ynqp-warn { margin-top: 12px; padding: 8px 12px; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 12.5px; }
        .ynqp-btn { margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; height: 44px; border-radius: 11px;
            background: #16a34a; color: #fff !important; font-weight: 700; font-size: 14.5px; text-decoration: none !important; box-shadow: 0 6px 14px rgba(22, 163, 74, .28); transition: background .15s, transform .05s; }
        .ynqp-btn svg { width: 18px; height: 18px; }
        .ynqp-btn:hover { background: #15803d; }
        .ynqp-btn:active { transform: translateY(1px); }
        .ynqp-btn:focus-visible, .ynqp-later:focus-visible, .ynqp-x:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
        .ynqp-foot { display: flex; justify-content: center; padding: 10px 22px 18px; }
        .ynqp-later { height: 38px; padding: 0 18px; border: 0; border-radius: 10px; background: transparent; color: #64748b; font-weight: 600; font-size: 13.5px; cursor: pointer; }
        .ynqp-later:hover { background: #f1f5f9; color: #0f172a; }
        @keyframes ynqp-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ynqp-rise { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .ynqp-overlay, .ynqp-card { animation: none; } }
    </style>
    <script>
        // "Keyinroq" — shu brauzer seansida shu holat qayta chiqmaydi (yangi tasdiq bo'lsa — yana chiqadi)
        (function () {
            var el = document.getElementById('ynq-pending');
            var key = 'ynq-pending-' + el.getAttribute('data-key');
            window.ynqpClose = function () {
                el.hidden = true;
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
            };
            try { if (sessionStorage.getItem(key)) return; } catch (e) {}
            el.hidden = false;
            el.addEventListener('click', function (e) { if (e.target === el) window.ynqpClose(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !el.hidden) window.ynqpClose(); });
        })();
    </script>
@endif
