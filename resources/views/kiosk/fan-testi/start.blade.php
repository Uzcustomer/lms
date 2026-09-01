@extends('kiosk.fan-testi.layout')

@section('title', $test->name)

@section('styles')
        .s-desc {
            margin: 0 0 22px; padding: 14px 17px;
            border-left: 3px solid var(--gold); border-radius: 0 4px 4px 0;
            background: #fdfaf0; color: var(--ink-soft); font-size: 13.5px; line-height: 1.65;
        }
        .s-rules { margin: 0 0 24px; padding: 0; list-style: none; }
        .s-rules li {
            display: flex; gap: 11px; padding: 9px 0;
            border-bottom: 1px dashed var(--line-soft);
            color: var(--ink-soft); font-size: 13.5px;
        }
        .s-rules li:last-child { border-bottom: 0; }
        .s-rules b { flex: none; color: var(--navy); font-weight: 500; }
        .s-num {
            flex: none; display: grid; place-items: center;
            width: 21px; height: 21px; margin-top: 1px; border-radius: 50%;
            background: #eaeff7; color: var(--navy);
            font-size: 11px; font-weight: 700;
        }
@endsection

@section('content')
    <div class="k-card">
        <div class="k-head">
            <div class="k-eyebrow">Nazorat testi</div>
            <h1>{{ $test->name }}</h1>
            <p>{{ $test->subject?->subject_name }}@if($test->subject?->semester_name) · {{ $test->subject->semester_name }}@endif</p>

            @php
                $activeCount = collect($test->questions ?? [])
                    ->filter(fn ($q) => ($q['is_active'] ?? true) !== false)
                    ->count();
            @endphp
            <div class="k-meta">
                <div class="k-meta-item"><b>{{ $activeCount }}</b><span>Savol</span></div>
                <div class="k-meta-item"><b>{{ $test->duration_minutes }}</b><span>Daqiqa</span></div>
                @if($test->pass_percent)
                    <div class="k-meta-item"><b>{{ $test->pass_percent }}%</b><span>O'tish chegarasi</span></div>
                @endif
            </div>
        </div>

        <div class="k-body">
            @if($errors->any())
                <div class="k-error"><span>&#9888;</span><span>{{ $errors->first() }}</span></div>
            @endif

            @if($test->description)
                <p class="s-desc">{{ $test->description }}</p>
            @endif

            <ul class="s-rules">
                <li><span class="s-num">1</span><span>Test <b>{{ $test->duration_minutes }} daqiqa</b> davom etadi. Vaqt tugashiga bir daqiqa qolganda ogohlantirish beriladi.</span></li>
                <li><span class="s-num">2</span><span>Vaqt tugaganda javoblaringiz <b>avtomatik topshiriladi</b>.</span></li>
                <li><span class="s-num">3</span><span>Har bir talaba bu testni <b>faqat bir marta</b> topshiradi.</span></li>
            </ul>

            <form method="POST" action="{{ route('kiosk.fan-testi.start', $test) }}">
                @csrf
                <label class="k-label" for="student_id_number">Talaba ID raqamingizni kiriting</label>
                <input class="k-input" id="student_id_number" name="student_id_number"
                       value="{{ old('student_id_number') }}" autocomplete="off" autofocus
                       inputmode="numeric" placeholder="000000000000" required>

                <div style="margin-top:20px">
                    <button class="k-btn k-btn-lg" type="submit">Testni boshlash</button>
                </div>
            </form>

            <p class="k-note">
                ID raqamingizni talaba guvohnomangizdan yoki HEMIS tizimidan topishingiz mumkin.<br>
                Muammo yuzaga kelsa o'qituvchiga murojaat qiling.
            </p>
        </div>
    </div>
@endsection
