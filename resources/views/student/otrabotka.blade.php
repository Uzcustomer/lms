<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ __('Qayta topshirish (Otrabotka)') }}</h2>
    </x-slot>

    {{-- Uslublar oddiy CSS'da: serverda `npm run build` qilinmaydi --}}
    <style>
        .ot-wrap { max-width: 720px; margin: 0 auto; padding: 16px 12px 28px; display: grid; gap: 12px; }
        .ot-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 20px; color: #fff;
            background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%); box-shadow: 0 12px 30px rgba(15, 39, 72, .22); }
        .ot-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.07); }
        .ot-hero small { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .ot-hero h1 { margin: 6px 0 0; font-size: 24px; font-weight: 800; line-height: 1.15; }
        .ot-hero p { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,.82); position: relative; z-index: 1; }
        .ot-stats { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; position: relative; z-index: 1; }
        .ot-stat { padding: 8px 12px; border-radius: 12px; background: rgba(255,255,255,.12); font-size: 12px; color: rgba(255,255,255,.85); }
        .ot-stat b { display: block; font-size: 18px; color: #fff; font-variant-numeric: tabular-nums; }
        .ot-note { padding: 12px 14px; border-radius: 14px; background: #eef2ff; color: #3730a3; font-size: 12.5px; line-height: 1.45; }
        .ot-card { border-radius: 16px; background: #fff; border: 1px solid #e5eaf2; box-shadow: 0 2px 8px rgba(15, 39, 72, .05); overflow: hidden; }
        .ot-head { padding: 12px 16px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; border-bottom: 1px solid #eef1f6; }
        .ot-name { flex: 1 1 100%; font-size: 14.5px; font-weight: 700; color: #17233a; line-height: 1.3; }
        .ot-chip { padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .ot-chip.open { background: #fef3c7; color: #92400e; }
        .ot-chip.expired { background: #fee2e2; color: #991b1b; }
        .ot-chip.done { background: #dcfce7; color: #166534; }
        .ot-row { display: flex; align-items: center; gap: 12px; padding: 11px 16px; }
        .ot-row + .ot-row { border-top: 1px solid #f1f4f8; }
        .ot-mark { flex: none; min-width: 44px; height: 44px; padding: 0 6px; box-sizing: border-box; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 800; font-variant-numeric: tabular-nums; background: #fee2e2; color: #b91c1c; }
        .ot-row.done .ot-mark { background: #f1f5f9; color: #64748b; }
        .ot-body { flex: 1; min-width: 0; }
        .ot-date, .ot-meta, .ot-state span { display: block; }
        .ot-date { font-size: 13.5px; font-weight: 700; color: #17233a; }
        .ot-meta { margin-top: 2px; font-size: 11.5px; color: #64748b; }
        .ot-state { flex: none; text-align: right; font-size: 11.5px; line-height: 1.35; max-width: 46%; }
        .ot-state b { display: block; font-size: 12.5px; }
        .ot-row.open .ot-state b { color: #b45309; }
        .ot-row.open.urgent .ot-state b { color: #dc2626; }
        .ot-row.expired .ot-state b { color: #b91c1c; }
        .ot-row.done .ot-state b { color: #15803d; }
        .ot-state span { color: #64748b; }
        .ot-book { display: inline-block; margin-top: 5px; padding: 4px 10px; border-radius: 999px; background: #1e3a8a; color: #fff !important; font-size: 11.5px; font-weight: 700; text-decoration: none; }
        .ot-book:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; }
        .ot-empty { padding: 32px 16px; text-align: center; color: #64748b; font-size: 14px; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1; }
        .ot-empty b { display: block; margin-bottom: 4px; font-size: 16px; color: #15803d; }
    </style>

    <div class="ot-wrap">
        <div class="ot-hero">
            <small>{{ $semesterName ?: __('Joriy semestr') }}</small>
            <h1>{{ __('Qayta topshirish') }}</h1>
            <p>{{ __("Joriy fanlaringizdagi NB va 60 dan past baholar hamda ularni qayta topshirish muddati.") }}</p>
            @if($groups->isNotEmpty())
                <div class="ot-stats">
                    <div class="ot-stat"><b>{{ $openCount }}</b>{{ __('topshirish kerak') }}</div>
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
            <section class="ot-card">
                <div class="ot-head">
                    <span class="ot-name">{{ $group['name'] }}</span>
                    @if($group['open'])<span class="ot-chip open">{{ $group['open'] }} {{ __('ta kutilmoqda') }}</span>@endif
                    @if($group['expired'])<span class="ot-chip expired">{{ $group['expired'] }} {{ __("ta muddati o'tgan") }}</span>@endif
                    @if($group['done'])<span class="ot-chip done">{{ $group['done'] }} {{ __('ta topshirilgan') }}</span>@endif
                </div>
                @foreach($group['items'] as $item)
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
                            @else
                                <b>
                                    @if($item['days_left'] <= 0)
                                        {{ __('Bugun oxirgi kun') }}
                                    @else
                                        {{ $item['days_left'] }} {{ __('kun qoldi') }}
                                    @endif
                                </b>
                                <span>{{ $item['deadline']->format('d.m.Y') }} {{ __('gacha') }}</span>
                                <a class="ot-book" href="{{ route('student.retake-booking.index', ['subject_id' => $item['subject_id'], 'lesson_date' => $item['lesson_date']->format('Y-m-d')]) }}">{{ __('Vaqt tanlash') }}</a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </section>
        @empty
            <div class="ot-empty">
                <b>{{ __("Qarzingiz yo'q") }}</b>
                {{ __("Joriy fanlaringizda NB yoki 60 dan past baho topilmadi.") }}
            </div>
        @endforelse
    </div>
</x-student-app-layout>
