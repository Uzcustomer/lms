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
         data-key="{{ md5(json_encode(array_column($ynqPending, 'url'))) }}">
        <div class="ynqp-card">
            <div class="ynqp-head">
                <span class="ynqp-icon" aria-hidden="true">!</span>
                <h2 id="ynqp-title">Vedomost tasdig'ingiz kutilmoqda</h2>
            </div>
            <p class="ynqp-sub">Kichik guruhlar bitta umumiy vedomostda. Boshqa guruh o'qituvchisi tasdiqladi — kafedra mudiriga o'tishi uchun sizning tasdig'ingiz ham kerak.</p>
            <ul class="ynqp-list">
                @foreach($ynqPending as $item)
                    <li class="ynqp-item">
                        <div class="ynqp-text">
                            <b>{{ $item['group'] }}</b> · {{ $item['subject'] }}
                            @foreach($item['by'] as $by)
                                <span class="ynqp-by">✓ {{ $by }} — tasdiqladi</span>
                            @endforeach
                            @unless($item['ready'])
                                <span class="ynqp-warn">Avval jurnalda YN yuboring, so'ng qaydnomani tasdiqlaysiz</span>
                            @endunless
                        </div>
                        <a class="ynqp-btn" href="{{ $item['url'] }}" @if($item['ready']) target="_blank" rel="noopener" @endif>
                            {{ $item['ready'] ? 'Tekshirish' : 'Jurnalni ochish' }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="ynqp-foot">
                <button type="button" class="ynqp-later" onclick="ynqpClose()">Keyinroq</button>
            </div>
        </div>
    </div>
    <style>
        .ynqp-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .45); }
        .ynqp-overlay[hidden] { display: none; }
        .ynqp-card { width: 100%; max-width: 560px; max-height: calc(100vh - 32px); overflow-y: auto; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); padding: 22px 22px 16px; font-family: system-ui, sans-serif; }
        .ynqp-head { display: flex; align-items: center; gap: 12px; }
        .ynqp-icon { flex-shrink: 0; width: 34px; height: 34px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #fef3c7; color: #b45309; font-weight: 800; font-size: 18px; }
        .ynqp-head h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .ynqp-sub { margin: 10px 0 14px; font-size: 13.5px; line-height: 1.5; color: #475569; }
        .ynqp-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .ynqp-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; }
        .ynqp-text { font-size: 14px; color: #0f172a; line-height: 1.45; }
        .ynqp-by { display: block; font-size: 12.5px; color: #15803d; }
        .ynqp-warn { display: block; font-size: 12.5px; color: #b45309; }
        .ynqp-btn { flex-shrink: 0; display: inline-flex; align-items: center; height: 40px; padding: 0 18px; border-radius: 10px; background: #16a34a; color: #fff !important; font-weight: 700; font-size: 14px; text-decoration: none !important; }
        .ynqp-btn:hover { background: #15803d; }
        .ynqp-btn:focus-visible, .ynqp-later:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
        .ynqp-foot { display: flex; justify-content: flex-end; margin-top: 14px; }
        .ynqp-later { height: 38px; padding: 0 16px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-weight: 600; font-size: 14px; cursor: pointer; }
        @media (max-width: 480px) { .ynqp-item { flex-direction: column; align-items: stretch; } .ynqp-btn { justify-content: center; } }
    </style>
    <script>
        // "Keyinroq" — shu brauzer seansida shu ro'yxat qayta chiqmaydi (yangi tasdiq bo'lsa — yana chiqadi)
        (function () {
            var el = document.getElementById('ynq-pending');
            var key = 'ynq-pending-' + el.getAttribute('data-key');
            try { if (sessionStorage.getItem(key)) return; } catch (e) {}
            el.hidden = false;
            window.ynqpClose = function () {
                el.hidden = true;
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
            };
            el.addEventListener('click', function (e) { if (e.target === el) window.ynqpClose(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !el.hidden) window.ynqpClose(); });
        })();
    </script>
@endif
