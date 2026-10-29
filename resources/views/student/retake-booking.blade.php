<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Qayta topshirish vaqti</h2>
    </x-slot>

    {{-- Uslublar oddiy CSS'da: serverda `npm run build` qilinmaydi --}}
    <style>
        .rb-wrap { max-width: 720px; margin: 0 auto; padding: 16px 12px 28px; display: grid; gap: 12px; }
        .rb-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 20px; color: #fff;
            background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%); box-shadow: 0 12px 30px rgba(15, 39, 72, .22); }
        .rb-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.07); }
        .rb-hero small { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .rb-hero h1 { margin: 6px 0 0; font-size: 22px; font-weight: 800; line-height: 1.2; text-wrap: balance; }
        .rb-hero p { margin: 8px 0 0; font-size: 13.5px; line-height: 1.5; color: rgba(255,255,255,.88); position: relative; z-index: 1; }
        .rb-alert { padding: 12px 14px; border-radius: 14px; font-size: 13px; line-height: 1.45; border: 1px solid transparent; }
        .rb-alert.success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .rb-alert.warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .rb-alert.error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .rb-alert.info { background: #eef2ff; color: #3730a3; border-color: #e0e7ff; }
        .rb-card { border-radius: 16px; background: #fff; border: 1px solid #e5eaf2; box-shadow: 0 2px 8px rgba(15, 39, 72, .05); padding: 16px; }
        .rb-card h2 { margin: 0 0 12px; font-size: 15px; font-weight: 800; color: #17233a; }
        .rb-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; margin: 0; }
        .rb-facts dt { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .rb-facts dd { margin: 2px 0 0; font-size: 14px; font-weight: 700; color: #17233a; font-variant-numeric: tabular-nums; }
        .rb-chip { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; background: #f1f5f9; color: #475569; }
        .rb-chip.start, .rb-chip.noquiz { background: #fef3c7; color: #92400e; }
        .rb-chip.booked, .rb-chip.continue { background: #e0e7ff; color: #3730a3; }
        .rb-chip.missed, .rb-chip.expired { background: #fee2e2; color: #991b1b; }
        .rb-chip.done { background: #dcfce7; color: #166534; }
        .rb-slot { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; font-size: 20px; font-weight: 800; color: #1e3a8a; font-variant-numeric: tabular-nums; }
        .rb-slot span { font-size: 13px; font-weight: 600; color: #64748b; }
        .rb-actions { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; }
        .rb-btn { appearance: none; border: 1px solid transparent; border-radius: 12px; padding: 11px 16px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .rb-btn:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; }
        .rb-btn.primary { background: #1e3a8a; color: #fff; }
        .rb-btn.primary:hover { background: #1e40af; }
        .rb-btn.ghost { background: #fff; color: #1e3a8a; border-color: #c7d2fe; }
        .rb-btn.danger { background: #fff; color: #b91c1c; border-color: #fecaca; }
        .rb-btn.block { width: 100%; }
        .rb-form { display: grid; gap: 12px; }
        .rb-form p { margin: 0; font-size: 13.5px; color: #334155; }
        .rb-field label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 700; color: #475569; }
        .rb-field input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 12px; padding: 10px 12px; font-size: 16px; color: #17233a; background: #fff; }
        .rb-field input:focus { outline: none; border-color: #4338ca; box-shadow: 0 0 0 3px rgba(67, 56, 202, .15); }
        .rb-slots-wrap { border: 0; margin: 0; padding: 0; min-width: 0; }
        .rb-slots-wrap legend { padding: 0; margin-bottom: 2px; font-size: 12px; font-weight: 700; color: #475569; }
        .rb-slots { margin-top: 8px; display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px; }
        .rb-slots .rb-slot-opt { position: relative; display: block; margin: 0; cursor: pointer; }
        .rb-slot-opt input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
        .rb-slot-opt span { display: block; padding: 10px 6px; border: 1px solid #cbd5e1; border-radius: 12px; text-align: center;
            font-size: 14px; font-weight: 700; color: #17233a; background: #fff; font-variant-numeric: tabular-nums; transition: background .12s, border-color .12s; }
        .rb-slot-opt:hover span { border-color: #a5b4fc; }
        .rb-slot-opt input:checked + span { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
        .rb-slot-opt input:focus-visible + span { outline: 2px solid #4338ca; outline-offset: 2px; }
        .rb-slot-opt input:disabled + span { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; text-decoration: line-through; cursor: not-allowed; }
        .rb-hint { font-size: 12px; color: #64748b; line-height: 1.45; }
        .rb-error { font-size: 13px; color: #b91c1c; font-weight: 600; }
        .rb-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: #fff; border: 1px solid #e5eaf2;
            box-shadow: 0 2px 8px rgba(15, 39, 72, .05); text-decoration: none; color: inherit; }
        .rb-item:hover { border-color: #a5b4fc; }
        .rb-item:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; }
        .rb-mark { flex: none; min-width: 44px; height: 44px; padding: 0 6px; box-sizing: border-box; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800; background: #fee2e2; color: #b91c1c; }
        .rb-body { flex: 1; min-width: 0; }
        .rb-name { display: block; font-size: 14.5px; font-weight: 700; color: #17233a; line-height: 1.3; }
        .rb-meta { display: flex; flex-wrap: wrap; gap: 4px 8px; margin-top: 4px; font-size: 12px; color: #64748b; align-items: center; }
        .rb-empty { padding: 32px 16px; text-align: center; color: #64748b; font-size: 14px; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1; }
        .rb-back { font-size: 13px; font-weight: 700; color: #4338ca; text-decoration: none; }
        @media (max-width: 380px) { .rb-facts { grid-template-columns: 1fr; } }
    </style>

    <div class="rb-wrap">
        @php $flash = session('booking_flash'); @endphp

        @if($debt)
            @php
                $dateText = $debt['lesson_date']?->format('d.m.Y');
                $deadlineText = $debt['deadline']?->format('d.m.Y');
                // Sababli ariza: oraliq boshi bugundan keyin bo'lishi mumkin
                $minDate = $debt['book_from'] && $debt['book_from']->isFuture() ? $debt['book_from'] : now();
                $showForm = $debt['can_book'];
                // Vaqt tanlangan bo'lsa forma "O'zgartirish" bosilgandagina ochiladi (xato bo'lsa ochiq qoladi)
                $formOpen = !$debt['is_booked'] || $errors->any() || ($flash['type'] ?? '') === 'error';
            @endphp

            <div class="rb-hero">
                <small>Qayta topshirish</small>
                <h1>{{ $debt['subject_name'] }}</h1>
                <p>
                    Sizda {{ $debt['subject_name'] }} fanidan {{ $dateText }} sanada
                    {{ $debt['is_nb'] ? 'NB' : 'past baho (' . $debt['mark'] . ')' }} bor.
                    @if($debt['excused'] && $deadlineText)
                        Sababli ariza bo'yicha qayta topshirish oralig'i:
                        <strong>{{ $debt['book_from']?->format('d.m.Y') }} — {{ $deadlineText }}</strong>.
                    @elseif($deadlineText)
                        Qayta topshirish muddati: <strong>{{ $deadlineText }}</strong> gacha.
                    @endif
                    @if($showForm && !$debt['is_booked']) Qayta topshirish kuningiz va vaqt oralig'ini tanlang. @endif
                </p>
            </div>

            @if($flash)
                <div class="rb-alert {{ $flash['type'] }}" role="status">{{ $flash['message'] }}</div>
            @endif

            @if(($debt['state'] ?? '') === 'missed')
                <div class="rb-alert error">Tanlangan vaqtda kelmadingiz, arizangiz bekor qilindi — qaytadan tanlang.</div>
            @endif

            @if($showForm && !$debt['quiz_uploaded'])
                <div class="rb-alert warning">Bu mavzu bo'yicha test hali yuklanmagan. Vaqtni tanlab qo'yishingiz mumkin, test o'sha vaqtgacha yuklanishi kerak.</div>
            @endif

            <section class="rb-card">
                <dl class="rb-facts">
                    <div><dt>Mavzu</dt><dd>{{ $debt['mavzu'] ? $debt['mavzu'] . '-mavzu' : '—' }}</dd></div>
                    <div><dt>Dars sanasi</dt><dd>{{ $dateText }}</dd></div>
                    <div><dt>{{ $debt['is_nb'] ? 'Sabab' : 'Baho' }}</dt><dd>{{ $debt['mark'] }}</dd></div>
                    <div><dt>Muddat</dt><dd>{{ $deadlineText ? $deadlineText . ' gacha' : '—' }}@if($debt['excused']) <small>(sababli ariza)</small>@endif</dd></div>
                    <div><dt>Qolgan urinishlar</dt><dd>{{ $debt['tries_left'] ?? '—' }}</dd></div>
                    <div><dt>Holat</dt><dd><span class="rb-chip {{ $debt['state'] }}">{{ $debt['state_label'] }}</span></dd></div>
                </dl>
            </section>

            @if($debt['is_booked'] && $debt['slot_start'])
                <section class="rb-card">
                    <h2>Tanlangan vaqt</h2>
                    <div class="rb-slot">
                        {{ $debt['slot_start']->format('d.m.Y') }}, {{ $debt['slot_start']->format('H:i') }}–{{ $debt['slot_end']?->format('H:i') }}
                    </div>
                    <p class="rb-hint" style="margin:8px 0 0;">
                        Shu vaqt oralig'ida test markazida Moodle'ga kirsangiz, qayta topshirish oynasi avtomatik ochiladi. Vaqtida kelmasangiz, bron bekor bo'ladi.
                        @if($debt['sync_pending']) <br><strong>Moodle bilan sinxronlash kutilmoqda.</strong> @endif
                    </p>
                    <div class="rb-actions">
                        @if($showForm)
                            <button type="button" class="rb-btn ghost" id="rb-change" @if($formOpen) hidden @endif>O'zgartirish</button>
                        @endif
                        @if($debt['booking'])
                            <form method="POST" action="{{ route('student.retake-booking.cancel', $debt['booking']->id) }}" onsubmit="return confirm('Tanlangan vaqt bekor qilinsinmi?');">
                                @csrf
                                <button type="submit" class="rb-btn danger">Bekor qilish</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @if($showForm)
                <section class="rb-card" id="rb-form-card" @unless($formOpen) hidden @endunless>
                    <h2>{{ $debt['is_booked'] ? 'Yangi vaqtni tanlang' : 'Kun va vaqtni tanlang' }}</h2>
                    <form method="POST" action="{{ route('student.retake-booking.store') }}" class="rb-form" id="rb-form"
                          data-deadline="{{ $debt['deadline']?->format('Y-m-d') }}" data-from="{{ $minDate->format('Y-m-d') }}">
                        @csrf
                        <input type="hidden" name="subject_id" value="{{ $debt['subject_id'] }}">
                        <input type="hidden" name="lesson_date" value="{{ $debt['lesson_date_raw'] }}">
                        <div class="rb-field">
                            <label for="rb-date">Kun</label>
                            <input type="date" id="rb-date" name="date" required
                                   min="{{ $minDate->format('Y-m-d') }}" @if($debt['deadline']) max="{{ $debt['deadline']->format('Y-m-d') }}" @endif
                                   value="{{ old('date', $debt['is_booked'] ? $debt['slot_start']?->format('Y-m-d') : '') }}">
                        </div>
                        @php
                            $currentSlot = old('slot', $debt['is_booked'] && $debt['slot_start'] && $debt['slot_end']
                                ? $debt['slot_start']->format('H:i') . '-' . $debt['slot_end']->format('H:i') : '');
                        @endphp
                        <fieldset class="rb-field rb-slots-wrap">
                            <legend>Vaqt oralig'i</legend>
                            <p class="rb-hint">Shu oraliqda test markaziga borib qayta topshirasiz.</p>
                            <div class="rb-slots" id="rb-slots">
                                @foreach($slots as $key => [$from, $to])
                                    <label class="rb-slot-opt">
                                        <input type="radio" name="slot" value="{{ $key }}" data-start="{{ $from }}" data-end="{{ $to }}" @checked($currentSlot === $key)>
                                        <span>{{ $from }}–{{ $to }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="rb-hint" id="rb-no-slots" hidden>Bugun uchun bo'sh vaqt oralig'i qolmadi — boshqa kunni tanlang.</p>
                        </fieldset>
                        <div class="rb-error" id="rb-client-error" role="alert" @unless($errors->any()) hidden @endunless>
                            {{ $errors->first('slot') ?: $errors->first() }}
                        </div>
                        <button type="submit" class="rb-btn primary block">Vaqtni saqlash</button>
                    </form>
                </section>
            @elseif(!$debt['is_booked'])
                <div class="rb-alert info">
                    @switch($debt['state'])
                        @case('done') Bu mavzu topshirilgan — qayta topshirish shart emas. @break
                        @case('expired') Qayta topshirish muddati o'tgan. @break
                        @case('continue') Bu mavzu bo'yicha urinishingiz davom etmoqda. @break
                        @default Bu mavzu uchun hozir vaqt tanlab bo'lmaydi.
                    @endswitch
                </div>
            @endif

            <div class="rb-alert info">
                Vaqt tanlash majburiy emas: tanlamasangiz, muddat tugaguncha istalgan vaqtda topshirishingiz mumkin.
                Vaqt tanlasangiz, faqat shu vaqt oralig'ida topshira olasiz.
            </div>

            <a class="rb-back" href="{{ route('student.retake-booking.index') }}">← Barcha qarzlarim</a>

            @if($showForm)
                <script>
                    (function () {
                        var form = document.getElementById('rb-form');
                        var card = document.getElementById('rb-form-card');
                        var change = document.getElementById('rb-change');
                        var errorBox = document.getElementById('rb-client-error');
                        if (change) {
                            change.addEventListener('click', function () {
                                card.hidden = false;
                                change.hidden = true;
                                document.getElementById('rb-date').focus();
                            });
                        }
                        function fail(message) {
                            errorBox.textContent = message;
                            errorBox.hidden = false;
                            return false;
                        }
                        var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="slot"]'));
                        var noSlots = document.getElementById('rb-no-slots');
                        // Tanlangan kun bugun bo'lsa, tugab bo'lgan oraliqlar tanlanmaydi (davom etayotgani ochiq)
                        function refreshSlots() {
                            var date = form.date.value, now = new Date(), free = 0;
                            radios.forEach(function (r) {
                                var past = !!date && new Date(date + 'T' + r.dataset.end) <= now;
                                r.disabled = past;
                                if (past && r.checked) { r.checked = false; }
                                if (!past) { free++; }
                            });
                            noSlots.hidden = !date || free > 0;
                        }
                        form.date.addEventListener('change', function () { errorBox.hidden = true; refreshSlots(); });
                        refreshSlots();
                        form.addEventListener('submit', function (e) {
                            var date = form.date.value, deadline = form.dataset.deadline;
                            var picked = radios.filter(function (r) { return r.checked; })[0];
                            var ok = true;
                            if (!date) {
                                ok = fail("Kunni tanlang.");
                            } else if (form.dataset.from && date < form.dataset.from) {
                                ok = fail("Qayta topshirish oralig'i " + form.dataset.from.split('-').reverse().join('.') + " dan boshlanadi.");
                            } else if (deadline && date > deadline) {
                                ok = fail("Sana qayta topshirish muddatidan kech bo'lmasligi kerak.");
                            } else if (!picked) {
                                ok = fail("Vaqt oralig'ini tanlang.");
                            } else if (new Date(date + 'T' + picked.dataset.end) <= new Date()) {
                                ok = fail("Bu vaqt oralig'i o'tib ketgan — keyingisini tanlang.");
                            }
                            if (!ok) { e.preventDefault(); }
                        });
                    })();
                </script>
            @endif
        @else
            <div class="rb-hero">
                <small>Qayta topshirish</small>
                <h1>Qayta topshirish vaqtini tanlash</h1>
                <p>NB yoki past baho bo'lgan mavzuni tanlang va uni test markazida qayta topshiradigan kun hamda vaqtni belgilang.</p>
            </div>

            @if($flash)
                <div class="rb-alert {{ $flash['type'] }}" role="status">{{ $flash['message'] }}</div>
            @endif
            @if($loadError)
                <div class="rb-alert error">{{ $loadError }}</div>
            @endif
            @if($notFound)
                <div class="rb-alert warning">Havoladagi mavzu bo'yicha qarz topilmadi — u topshirilgan yoki muddati o'tgan bo'lishi mumkin.</div>
            @endif

            @forelse($debts as $item)
                <a class="rb-item" href="{{ $item['url'] }}">
                    <span class="rb-mark">{{ $item['mark'] }}</span>
                    <span class="rb-body">
                        <span class="rb-name">{{ $item['subject_name'] }}</span>
                        <span class="rb-meta">
                            <span>{{ $item['lesson_date']?->format('d.m.Y') }}</span>
                            @if($item['deadline'])<span>· muddat {{ $item['deadline']->format('d.m.Y') }}</span>@endif
                            <span class="rb-chip {{ $item['state'] }}">{{ $item['state_label'] }}</span>
                        </span>
                        @if($item['is_booked'] && $item['slot_start'])
                            <span class="rb-meta">Vaqt: {{ $item['slot_start']->format('d.m.Y H:i') }}–{{ $item['slot_end']?->format('H:i') }}</span>
                        @endif
                    </span>
                    <svg width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @empty
                @unless($loadError)
                    <div class="rb-empty">Vaqt tanlash mumkin bo'lgan qarzingiz yo'q.</div>
                @endunless
            @endforelse
        @endif
    </div>
</x-student-app-layout>
