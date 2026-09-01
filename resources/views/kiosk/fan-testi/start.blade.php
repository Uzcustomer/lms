@extends('kiosk.fan-testi.layout')

@section('title', $test->name)

@section('content')
    <div class="k-card">
        <div class="k-head">
            <h1>{{ $test->name }}</h1>
            <p>{{ $test->subject?->subject_name }}</p>
            <div class="k-meta">
                <span class="k-chip">{{ $test->duration_minutes }} daqiqa</span>
                <span class="k-chip">{{ collect($test->questions ?? [])->filter(fn ($q) => ($q['is_active'] ?? true) !== false)->count() }} ta savol</span>
                @if($test->pass_percent)
                    <span class="k-chip">O'tish: {{ $test->pass_percent }}%</span>
                @endif
            </div>
        </div>

        <div class="k-body">
            @if($errors->any())
                <div class="k-error">{{ $errors->first() }}</div>
            @endif

            @if($test->description)
                <p style="margin:0 0 18px;color:#5b7091;font-size:13px">{{ $test->description }}</p>
            @endif

            <form method="POST" action="{{ route('kiosk.fan-testi.start', $test) }}">
                @csrf
                <label class="k-label" for="student_id_number">Talaba ID raqamingizni kiriting</label>
                <input class="k-input" id="student_id_number" name="student_id_number"
                       value="{{ old('student_id_number') }}" autocomplete="off" autofocus
                       inputmode="numeric" placeholder="Masalan: 361241100123" required>

                <div style="margin-top:18px">
                    <button class="k-btn k-btn-lg" type="submit">Testni boshlash</button>
                </div>
            </form>

            <p class="k-note">
                Test boshlangach {{ $test->duration_minutes }} daqiqa vaqt beriladi.<br>
                Har bir talaba bu testni faqat bir marta topshiradi.
            </p>
        </div>
    </div>
@endsection
