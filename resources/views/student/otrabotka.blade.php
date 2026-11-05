<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ __('Qayta topshirish (Otrabotka)') }}</h2>
    </x-slot>

    {{-- Uslublar oddiy CSS'da: serverda `npm run build` qilinmaydi --}}
    <style>
        .ot-wrap { max-width: 720px; margin: 0 auto; padding: 8px 8px 20px; display: grid; gap: 8px; }
        .ot-hero { position: relative; overflow: hidden; border-radius: 16px; padding: 12px 14px; color: #fff;
            background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%); box-shadow: 0 6px 18px rgba(15, 39, 72, .18); }
        .ot-hero::after { content: ""; position: absolute; right: -36px; top: -36px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,.07); }
        .ot-hero small { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .ot-hero h1 { margin: 2px 0 0; font-size: 18px; font-weight: 800; line-height: 1.15; }
        .ot-hero p { margin: 3px 0 0; font-size: 12px; color: rgba(255,255,255,.8); position: relative; z-index: 1; line-height: 1.35; }
        .ot-stats { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; position: relative; z-index: 1; }
        .ot-stat { padding: 5px 10px; border-radius: 10px; background: rgba(255,255,255,.12); font-size: 11px; color: rgba(255,255,255,.85); line-height: 1.2; }
        .ot-stat b { display: block; font-size: 15px; color: #fff; font-variant-numeric: tabular-nums; }
        .ot-note { padding: 7px 10px; border-radius: 10px; background: #eef2ff; color: #3730a3; font-size: 11.5px; line-height: 1.4; }
        .ot-card { border-radius: 12px; background: #fff; border: 1px solid #e5eaf2; box-shadow: 0 1px 4px rgba(15, 39, 72, .04); overflow: hidden; }
        .ot-head { padding: 7px 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; border-bottom: 1px solid #eef1f6; background: #fbfcfe; }
        .ot-name { flex: 1 1 100%; font-size: 13px; font-weight: 700; color: #17233a; line-height: 1.25; }
        .ot-chip { padding: 0 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; line-height: 17px; }
        .ot-chip.open { background: #fef3c7; color: #92400e; }
        .ot-chip.booked { background: #dbeafe; color: #1e40af; }
        .ot-chip.expired { background: #fee2e2; color: #991b1b; }
        .ot-chip.done { background: #dcfce7; color: #166534; }
        .ot-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; }
        .ot-row + .ot-row { border-top: 1px solid #f1f4f8; }
        .ot-mark { flex: none; min-width: 34px; height: 34px; padding: 0 5px; box-sizing: border-box; border-radius: 9px; display: flex; align-items: center; justify-content: center;
            font-size: 12.5px; font-weight: 800; font-variant-numeric: tabular-nums; background: #fee2e2; color: #b91c1c; }
        .ot-row.done .ot-mark { background: #f1f5f9; color: #64748b; }
        .ot-body { flex: 1; min-width: 0; }
        .ot-date, .ot-meta, .ot-state span { display: block; }
        .ot-date { font-size: 12.5px; font-weight: 700; color: #17233a; }
        .ot-meta { font-size: 10.5px; color: #64748b; }
        .ot-state { flex: none; text-align: right; font-size: 10.5px; line-height: 1.3; max-width: 55%; }
        .ot-state b { display: block; font-size: 11.5px; }
        .ot-row.open .ot-state b { color: #b45309; }
        .ot-row.open.urgent .ot-state b { color: #dc2626; }
        .ot-row.expired .ot-state b { color: #b91c1c; }
        .ot-row.done .ot-state b { color: #15803d; }
        .ot-state span { color: #64748b; }
        .ot-book { display: inline-block; margin-top: 3px; padding: 2px 9px; border-radius: 999px; background: #1e3a8a; color: #fff !important; font-size: 10.5px; font-weight: 700; text-decoration: none; }
        .ot-book:focus-visible, .ot-slot:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; }
        /* Vaqt tanlangan: tugma o'rniga tanlangan vaqt */
        .ot-slot { display: inline-flex; align-items: center; gap: 4px; margin-top: 3px; padding: 2px 8px; border-radius: 8px; background: #eff6ff;
            border: 1px solid #bfdbfe; color: #1e3a8a !important; font-size: 11px; font-weight: 800; text-decoration: none; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ot-slot.now { background: #ecfdf5; border-color: #6ee7b7; color: #065f46 !important; }
        .ot-state span.ot-try { color: #b91c1c; font-weight: 700; }
        .ot-empty { padding: 20px 12px; text-align: center; color: #64748b; font-size: 13px; background: #fff; border-radius: 12px; border: 1px dashed #cbd5e1; }
        .ot-empty b { display: block; margin-bottom: 2px; font-size: 14px; color: #15803d; }
    </style>

    @php
        $bookedCount = $groups->sum(fn ($g) => $g['items']->filter(fn ($i) => $i['state'] === 'open' && $i['booking'])->count());
    @endphp

    <div class="ot-wrap">
        <div class="ot-hero">
            <small>{{ $semesterName ?: __('Joriy semestr') }}</small>
            <h1>{{ __('Qayta topshirish') }}</h1>
            <p>{{ __("Joriy fanlaringizdagi NB va :limit dan past baholar hamda ularni qayta topshirish muddati.", ['limit' => $lowLimit]) }}</p>
            @if($groups->isNotEmpty())
                <div class="ot-stats">
                    <div class="ot-stat"><b>{{ $openCount }}</b>{{ __('topshirish kerak') }}</div>
                    @if($bookedCount)
                        <div class="ot-stat"><b>{{ $bookedCount }}</b>{{ __('vaqt tanlangan') }}</div>
                    @endif
                    @if($nearestDeadline)
                        <div class="ot-stat"><b>{{ $nearestDeadline->format('d.m.Y') }}</b>{{ __('eng yaqin muddat') }}</div>
                    @endif
                    @if($expiredCount)
                        <div class="ot-stat"><b>{{ $expiredCount }}</b>{{ __("muddati o'tgan") }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div class="ot-note">
            {{ __('Har bir NB yoki past baho dars kunidan boshlab :days kun ichida qayta topshiriladi.', ['days' => $deadlineDays]) }}
            @if($byTestMarkazi && $byTeacher)
                {{ __("Qayta topshirish o'qituvchiga yoki test markazida.") }}
            @elseif($byTestMarkazi)
                {{ __('Qayta topshirish test markazida.') }}
            @elseif($byTeacher)
                {{ __("Qayta topshirish fan o'qituvchisiga.") }}
            @endif
        </div>

        @forelse($groups as $group)
            @php $groupBooked = $group['items']->filter(fn ($i) => $i['state'] === 'open' && $i['booking'])->count(); @endphp
            <section class="ot-card">
                <div class="ot-head">
                    <span class="ot-name">{{ $group['name'] }}</span>
                    @if($group['open'] - $groupBooked > 0)<span class="ot-chip open">{{ $group['open'] - $groupBooked }} {{ __('ta kutilmoqda') }}</span>@endif
                    @if($groupBooked)<span class="ot-chip booked">{{ $groupBooked }} {{ __('ta vaqt tanlangan') }}</span>@endif
                    @if($group['expired'])<span class="ot-chip expired">{{ $group['expired'] }} {{ __("ta muddati o'tgan") }}</span>@endif
                    @if($group['done'])<span class="ot-chip done">{{ $group['done'] }} {{ __('ta topshirilgan') }}</span>@endif
                </div>
                @foreach($group['items'] as $item)
                    @php
                        $url = route('student.retake-booking.index', ['subject_id' => $item['subject_id'], 'lesson_date' => $item['lesson_date']->format('Y-m-d')]);
                        $b = $item['state'] === 'open' ? $item['booking'] : null;
                    @endphp
                    <div class="ot-row {{ $item['state'] }} {{ $item['state'] === 'open' && $item['days_left'] <= 1 ? 'urgent' : '' }}">
                        <span class="ot-mark">{{ $item['mark'] }}</span>
                        <span class="ot-body">
                            <span class="ot-date">{{ $item['lesson_date']->format('d.m.Y') }}</span>
                            <span class="ot-meta">{{ $item['training_type'] }}</span>
                        </span>
                        <span class="ot-state">
                            @if($item['state'] === 'done')
                                <b>{{ __('Topshirildi') }}: {{ $item['retake'] }}</b>
                            @elseif($item['state'] === 'expired')
                                <b>{{ __("Muddati o'tgan") }}</b>
                                <span>{{ $item['deadline']->format('d.m.Y') }}</span>
                            @elseif($b)
                                <b>{{ $item['retry'] ? __('2-urinish: vaqt tanlangan') : __('Vaqt tanlangan') }}</b>
                                @if($item['retry'])<span class="ot-try">{{ __('1-urinish') }}: {{ $item['retake'] }}</span>@endif
                                <a class="ot-slot {{ $b->slot_start->lte(now()) ? 'now' : '' }}" href="{{ $url }}"
                                   title="{{ __('Batafsil') }}">🕒 {{ $b->slot_start->format('d.m') }}, {{ $b->slot_start->format('H:i') }}–{{ $b->slot_end->format('H:i') }}</a>
                            @else
                                <b>
                                    @if($item['days_left'] <= 0)
                                        {{ __('Bugun oxirgi kun') }}
                                    @else
                                        {{ $item['days_left'] }} {{ __('kun qoldi') }}
                                    @endif
                                </b>
                                @if($item['retry'])<span class="ot-try">{{ __('1-urinish') }}: {{ $item['retake'] }}</span>@endif
                                <span>{{ $item['deadline']->format('d.m.Y') }} {{ __('gacha') }}</span>
                                <a class="ot-book" href="{{ $url }}">{{ $item['retry'] ? __('2-urinish: vaqt tanlash') : __('Vaqt tanlash') }}</a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </section>
        @empty
            <div class="ot-empty">
                <b>{{ __("Qarzingiz yo'q") }}</b>
                {{ __("Joriy fanlaringizda NB yoki :limit dan past baho topilmadi.", ['limit' => $lowLimit]) }}
            </div>
        @endforelse
    </div>
</x-student-app-layout>
