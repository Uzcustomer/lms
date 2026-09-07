@extends('kiosk.fan-testi.layout')

@section('title', 'Test yakunlandi')

@section('styles')
        .c-wrap { text-align: center; padding: 18px 8px 6px; }
        .c-badge {
            display: grid; place-items: center; width: 62px; height: 62px; margin: 0 auto 18px;
            border: 1px solid var(--line); border-radius: 50%; background: #fafcfe;
            color: var(--navy); font-size: 26px;
        }
        .c-wrap h1 {
            margin: 0; color: var(--navy);
            font-family: 'Roboto Slab', Georgia, serif; font-size: 23px; font-weight: 600;
        }
        .c-wrap > p { margin: 9px auto 0; max-width: 430px; color: var(--ink-soft); font-size: 14px; line-height: 1.65; }
        .c-meta {
            display: inline-flex; flex-wrap: wrap; justify-content: center; gap: 22px;
            margin-top: 22px; padding: 14px 24px;
            border: 1px solid var(--line); border-left: 3px solid var(--gold); border-radius: 4px;
            background: #fdfaf0;
        }
        .c-meta div { text-align: left; }
        .c-meta b { display: block; color: var(--navy); font-size: 14px; font-weight: 500; }
        .c-meta span {
            display: block; margin-top: 2px; color: var(--muted);
            font-size: 9.5px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase;
        }
        .c-note { margin-top: 20px; color: var(--muted); font-size: 12.5px; }
@endsection

@section('content')
    <div class="k-card">
        <div class="c-wrap">
            <div class="c-badge">&#10003;</div>
            <h1>Test bajarish yakunlandi</h1>
            <p>Bu test sahifasi o'qituvchi tomonidan yopilgan. Yangi urinish qabul qilinmaydi.</p>

            <div class="c-meta">
                <div>
                    <b>{{ $test->name }}</b>
                    <span>Test to'plami</span>
                </div>
                @if($test->subject?->subject_name)
                    <div>
                        <b>{{ $test->subject->subject_name }}</b>
                        <span>Fan</span>
                    </div>
                @endif
            </div>

            <p class="c-note">Natijangiz o'qituvchida saqlangan — savollaringiz bo'lsa unga murojaat qiling.</p>
        </div>
    </div>
@endsection
