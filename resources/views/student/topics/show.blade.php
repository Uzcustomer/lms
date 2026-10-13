<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ __('Fan mavzulari') }}</h2>
    </x-slot>

    @php
        // Mashg'ulot turi rangi: ma'ruza — ko'k, amaliy — yashil, boshqalar — binafsha
        $typeTone = function (string $name) {
            $n = mb_strtolower($name);
            if (str_contains($n, 'ma\'ruza') || str_contains($n, 'maruza') || str_contains($n, 'ma’ruza') || str_contains($n, 'maʼruza')) return 'lec';
            if (str_contains($n, 'amaliy')) return 'prc';
            return 'oth';
        };
    @endphp

    <style>
        .ts-wrap { max-width: 760px; margin: 0 auto; padding: 16px 12px 28px; display: grid; gap: 12px; }
        .ts-back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; font-weight: 600; color: #4338ca; text-decoration: none; }
        .ts-hero { border-radius: 22px; padding: 18px 20px; color: #fff; background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%);
            box-shadow: 0 12px 30px rgba(15, 39, 72, .22); display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; }
        .ts-hero small { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .ts-hero h1 { margin: 6px 0 0; font-size: 21px; font-weight: 800; line-height: 1.2; }
        .ts-pdf { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 12px; background: #fff; color: #1e3a8a;
            font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .ts-pdf:hover { background: #e0e7ff; }
        .ts-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
        .ts-tab { padding: 6px 12px; border-radius: 999px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-size: 12.5px; font-weight: 600; cursor: pointer; }
        .ts-tab[aria-pressed="true"] { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
        .ts-week { background: #fff; border: 1px solid #e5eaf2; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 39, 72, .05); }
        .ts-week-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
        .ts-week-head b { font-size: 13.5px; color: #17233a; }
        .ts-week-head span { font-size: 12px; color: #64748b; font-variant-numeric: tabular-nums; }
        .ts-row { display: flex; gap: 10px; padding: 11px 16px; border-top: 1px solid #f1f5f9; }
        .ts-row:first-of-type { border-top: 0; }
        .ts-badge { flex: none; align-self: flex-start; min-width: 74px; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; text-align: center; }
        .ts-badge.lec { background: #dbeafe; color: #1e40af; }
        .ts-badge.prc { background: #dcfce7; color: #166534; }
        .ts-badge.oth { background: #ede9fe; color: #5b21b6; }
        .ts-topic { flex: 1; min-width: 0; font-size: 14px; color: #1e293b; line-height: 1.45; }
        .ts-topic.is-none { color: #94a3b8; font-style: italic; }
        .ts-hours { flex: none; font-size: 12px; color: #64748b; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ts-empty { padding: 32px 16px; text-align: center; color: #64748b; font-size: 14px; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1; }
    </style>

    <div class="ts-wrap">
        <a class="ts-back" href="{{ route('student.topics.index') }}">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Fanlar') }}
        </a>

        <div class="ts-hero">
            <div>
                <small>{{ $subject->semester_name }}</small>
                <h1>{{ $subject->subject_name }}</h1>
            </div>
            @if($topics['has_plan'])
                <a class="ts-pdf" href="{{ route('student.topics.pdf', $subject->id) }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                    {{ __('PDF yuklab olish') }}
                </a>
            @endif
        </div>

        @if(!$topics['has_plan'] || empty($topics['weeks']))
            <div class="ts-empty">{{ __('Bu fan uchun mavzular hali kiritilmagan.') }}</div>
        @else
            @if(count($topics['types']) > 1)
                <div class="ts-tabs" role="group" aria-label="{{ __("Mashg'ulot turi") }}">
                    <button type="button" class="ts-tab" data-type="" aria-pressed="true">{{ __('Hammasi') }}</button>
                    @foreach($topics['types'] as $code => $name)
                        <button type="button" class="ts-tab" data-type="{{ $code }}" aria-pressed="false">{{ $name }}</button>
                    @endforeach
                </div>
            @endif

            @foreach($topics['weeks'] as $week)
                <section class="ts-week">
                    <div class="ts-week-head">
                        <b>{{ $week['week'] }}-{{ __('hafta') }}</b>
                        @if($week['dates'])<span>{{ $week['dates'] }}</span>@endif
                    </div>
                    @foreach($week['items'] as $item)
                        <div class="ts-row" data-type="{{ $item['code'] }}">
                            <span class="ts-badge {{ $typeTone($item['type']) }}">{{ $item['type'] }}</span>
                            <span class="ts-topic {{ $item['topic'] === '' ? 'is-none' : '' }}">{{ $item['topic'] !== '' ? $item['topic'] : __('Mavzu kiritilmagan') }}</span>
                            @if($item['hours'] > 0)<span class="ts-hours">{{ $item['hours'] }} {{ __('soat') }}</span>@endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        @endif
    </div>

    <script>
        // Mashg'ulot turi bo'yicha filtr (ma'ruza / amaliy / ...)
        document.querySelectorAll('.ts-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const type = tab.dataset.type;
                document.querySelectorAll('.ts-tab').forEach((t) => t.setAttribute('aria-pressed', String(t === tab)));
                document.querySelectorAll('.ts-week').forEach((week) => {
                    let visible = 0;
                    week.querySelectorAll('.ts-row').forEach((row) => {
                        const show = !type || row.dataset.type === type;
                        row.hidden = !show;
                        if (show) visible++;
                    });
                    week.hidden = visible === 0;
                });
            });
        });
    </script>
</x-student-app-layout>
