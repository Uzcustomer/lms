<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-sm font-semibold leading-tight text-gray-800">
            {{ __('Test fanlar') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <style>
                .tsi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
                .tsi-card { background:#fff; border:1px solid #dbe4ef; border-radius:22px; box-shadow:0 12px 30px rgba(15,23,42,.06); overflow:hidden; }
                .tsi-head { padding:16px 18px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom:1px solid #dbe4ef; }
                .tsi-body { padding:18px; }
                .tsi-chip { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid transparent; }
                .tsi-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tsi-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tsi-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tsi-btn { display:inline-flex; align-items:center; justify-content:center; padding:11px 16px; border-radius:14px; font-size:14px; font-weight:800; background:linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; width:100%; }
                .tsi-empty { background:#fff; border:1px dashed #cbd5e1; border-radius:22px; padding:36px 24px; text-align:center; color:#64748b; }
            </style>

            <div class="mb-5">
                <h1 class="text-2xl font-bold text-slate-900">Test fanlar</h1>
                <p class="mt-1 text-sm text-slate-600">Sizga biriktirilgan test fanlar va ularning mavzu testlari shu yerda ko'rinadi.</p>
            </div>

            @if($subjects->isEmpty())
                <div class="tsi-empty">
                    Hozircha sizga biriktirilgan faol test fan topilmadi.
                </div>
            @else
                <div class="tsi-grid">
                    @foreach($subjects as $subject)
                        <div class="tsi-card">
                            <div class="tsi-head">
                                <div class="flex flex-wrap gap-2">
                                    @if($subject['group_name'])
                                        <span class="tsi-chip blue">{{ $subject['group_name'] }}</span>
                                    @endif
                                    @if($subject['starts_on'] || $subject['ends_on'])
                                        <span class="tsi-chip orange">{{ $subject['starts_on'] ?: '--.--.----' }} - {{ $subject['ends_on'] ?: '--.--.----' }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-3 text-xl font-bold text-slate-900">{{ $subject['name'] }}</h3>
                            </div>
                            <div class="tsi-body">
                                <div class="space-y-2 text-sm text-slate-600">
                                    <div><b>O'qituvchi:</b> {{ $subject['teacher_name'] ?: '-' }}</div>
                                    <div><b>Mavzular:</b> {{ $subject['lessons_count'] }}</div>
                                    <div><b>Hozir ochiq:</b> {{ $subject['open_count'] }}</div>
                                </div>
                                <div class="mt-4">
                                    <a href="{{ $subject['subject_route'] }}" class="tsi-btn">Test sahifasini ochish</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-student-app-layout>
