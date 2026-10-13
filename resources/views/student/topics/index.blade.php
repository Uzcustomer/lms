<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ __('Fan mavzulari') }}</h2>
    </x-slot>

    {{-- Uslublar oddiy CSS'da: serverda `npm run build` qilinmaydi --}}
    <style>
        .tp-wrap { max-width: 720px; margin: 0 auto; padding: 16px 12px 28px; display: grid; gap: 12px; }
        .tp-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 20px; color: #fff;
            background: linear-gradient(135deg, #0f2748 0%, #1e3a8a 60%, #4338ca 100%); box-shadow: 0 12px 30px rgba(15, 39, 72, .22); }
        .tp-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.07); }
        .tp-hero small { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .tp-hero h1 { margin: 6px 0 0; font-size: 24px; font-weight: 800; line-height: 1.15; }
        .tp-hero p { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,.8); position: relative; z-index: 1; }
        .tp-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: #fff;
            border: 1px solid #e5eaf2; box-shadow: 0 2px 8px rgba(15, 39, 72, .05); text-decoration: none; color: inherit; transition: border-color .15s, transform .15s; }
        a.tp-item:hover { border-color: #a5b4fc; transform: translateY(-1px); }
        a.tp-item:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; }
        .tp-item.is-empty { opacity: .65; }
        .tp-icon { flex: none; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4338ca; }
        .tp-item.is-empty .tp-icon { background: #f1f5f9; color: #94a3b8; }
        .tp-body { flex: 1; min-width: 0; }
        .tp-name { font-size: 14.5px; font-weight: 700; color: #17233a; line-height: 1.3; }
        .tp-meta { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 5px; font-size: 11.5px; color: #64748b; }
        .tp-chip { padding: 1px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; font-weight: 600; }
        .tp-arrow { flex: none; color: #94a3b8; }
        .tp-empty { padding: 32px 16px; text-align: center; color: #64748b; font-size: 14px; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1; }
    </style>

    <div class="tp-wrap">
        <div class="tp-hero">
            <small>{{ $semesterName ?: __('Joriy semestr') }}</small>
            <h1>{{ __('Fan mavzulari') }}</h1>
            <p>{{ __("Fanni tanlang — ma'ruza va amaliy mashg'ulotlar mavzularini haftalar bo'yicha ko'rasiz va PDF qilib yuklab olasiz.") }}</p>
        </div>

        @forelse($subjects as $subject)
            @if($subject['has_plan'])
                <a class="tp-item" href="{{ route('student.topics.show', $subject['id']) }}">
            @else
                <div class="tp-item is-empty" title="{{ __("Kafedra bu fan uchun KTR ni hali kiritmagan") }}">
            @endif
                <span class="tp-icon" aria-hidden="true">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </span>
                <span class="tp-body">
                    <span class="tp-name">{{ $subject['name'] }}</span>
                    <span class="tp-meta">
                        @if($subject['has_plan'])
                            <span>{{ $subject['topics_count'] }} {{ __('ta mavzu') }}</span>
                            @foreach($subject['types'] as $type)
                                <span class="tp-chip">{{ $type }}</span>
                            @endforeach
                        @else
                            <span>{{ __('Mavzular hali kiritilmagan') }}</span>
                        @endif
                    </span>
                </span>
                @if($subject['has_plan'])
                    <svg class="tp-arrow" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endif
            @if($subject['has_plan'])
                </a>
            @else
                </div>
            @endif
        @empty
            <div class="tp-empty">{{ __('Joriy semestrda fanlar topilmadi.') }}</div>
        @endforelse
    </div>
</x-student-app-layout>
