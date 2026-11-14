<x-app-layout>
@include('admin.subject-differences._styles')

<div class="sd-page">
    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">

            <div>
                <h1 class="text-xl font-bold text-slate-900">Fanlar farqi</h1>
                <p class="mt-0.5 text-sm text-slate-600">Ko'chib kelgan talabaning transkriptini o'quv rejamiz bilan solishtirish: qaysi kursga o'tadi va qaysi fanlarni topshiradi.</p>
            </div>

            @if(session('success'))
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm text-rose-900">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm text-rose-900">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Yangi tekshiruv: bitta qator --}}
            <form method="POST" action="{{ route('admin.subject-differences.store') }}" enctype="multipart/form-data"
                  x-data="{ mode: '{{ old('scale_mode', $defaults['mode']) }}', busy: false, scaleOpen: {{ old('scale_mode') && old('scale_mode') !== 'ball' ? 'true' : 'false' }}, extraOpen: false }" @submit="busy = true"
                  class="rounded-xl border border-slate-300 bg-white p-4">
                @csrf
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="block text-sm font-semibold text-slate-900" for="transcript">Yangi tekshiruv: transkript (PDF)</label>
                        <input id="transcript" type="file" name="transcript" accept="application/pdf" required
                               class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-800">
                    </div>
                    <div class="lg:w-80">
                        <div class="text-sm font-semibold text-slate-900">Baho shkalasi</div>
                        <div class="mt-1 flex h-[38px] items-center justify-between rounded-md border border-slate-300 px-3 text-sm">
                            <span class="truncate text-slate-800">
                                <span x-show="mode === 'ball'">5 = 90+, 4 = 70+, 3 = 60+ ball</span>
                                <span x-show="mode === 'grade'" x-cloak>transkriptdagi baho</span>
                            </span>
                            <button type="button" @click="scaleOpen = !scaleOpen" class="ml-2 shrink-0 font-semibold text-blue-700 hover:underline">O'zgartirish</button>
                        </div>
                    </div>
                    <button type="submit" :disabled="busy"
                            class="inline-flex h-[38px] shrink-0 items-center justify-center gap-2 rounded-md bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span x-text="busy ? 'O\'qilmoqda… (1–2 daqiqa)' : 'Yuklash va tekshirish'">Yuklash va tekshirish</span>
                    </button>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                    <span>Faqat transkript sahifalari, pasport va arizasiz. Talaba ismi va OTM transkriptdan olinadi.</span>
                    <button type="button" x-show="!extraOpen" @click="extraOpen = true" class="font-semibold text-blue-700 hover:underline">+ Qo'shimcha ko'chirma</button>
                    @unless($aiConfigured)
                        <span class="font-semibold text-amber-800">AI kaliti sozlanmagan: skaner transkriptlar o'qilmaydi (Vedomost tekshirish → AI sozlamalari).</span>
                    @endunless
                </div>

                <div x-show="extraOpen" x-cloak class="mt-3 border-t border-slate-200 pt-3">
                    <label class="block text-sm font-semibold text-slate-900" for="extra">Qo'shimcha ko'chirma (PDF)</label>
                    <input id="extra" type="file" name="extra[]" accept="application/pdf" multiple
                           class="mt-1 block w-full max-w-xl rounded-md border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-800">
                    <p class="mt-1 text-xs text-slate-600">Transkriptga kirmagan oxirgi semestr baholari bo'lsa ("Reyting daftarchasidan ko'chirma").</p>
                </div>

                <div x-show="scaleOpen" x-cloak class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-slate-200 pt-3 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="scale_mode" value="ball" x-model="mode"> Balldan hisoblansin</label>
                    <div x-show="mode === 'ball'" class="flex items-center gap-2">
                        @foreach(['five' => '5 ≥', 'four' => '4 ≥', 'three' => '3 ≥'] as $f => $lbl)
                            <label class="flex items-center gap-1 text-slate-700">{{ $lbl }}
                                <input type="number" step="0.01" min="0" max="100" name="scale_{{ $f }}" value="{{ old('scale_' . $f, $defaults[$f]) }}"
                                       class="w-16 rounded-md border-slate-300 px-2 py-1 text-sm">
                            </label>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2"><input type="radio" name="scale_mode" value="grade" x-model="mode"> Transkriptda yozilgan baho (5/4/3/2)</label>
                </div>
            </form>

            {{-- Tekshiruvlar ro'yxati --}}
            <div class="rounded-xl border border-slate-300 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-300 px-4 py-3">
                    <h2 class="text-base font-bold text-slate-900">Tekshiruvlar <span class="font-normal text-slate-600">({{ $checks->total() }})</span></h2>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba yoki OTM" aria-label="Qidirish"
                               class="w-64 rounded-md border-slate-300 px-3 py-1.5 text-sm">
                        <button class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">Qidirish</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-left text-slate-800">
                            <th class="w-36 border-b border-slate-300 px-4 py-2 font-semibold">Sana</th>
                            <th class="border-b border-slate-300 px-4 py-2 font-semibold">Talaba</th>
                            <th class="border-b border-slate-300 px-4 py-2 font-semibold">Avvalgi OTM</th>
                            <th class="w-40 border-b border-slate-300 px-4 py-2 font-semibold">Natija</th>
                            <th class="w-24 border-b border-slate-300 px-4 py-2 text-center font-semibold">GPA</th>
                            <th class="w-28 border-b border-slate-300 px-4 py-2 text-center font-semibold">Fanlar farqi</th>
                            <th class="w-56 border-b border-slate-300 px-4 py-2 font-semibold">Kim tekshirdi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($checks as $c)
                            @php $sum = $summaries[$c->id] ?? null; @endphp
                            <tr class="cursor-pointer border-b border-slate-200 hover:bg-slate-50" onclick="window.location='{{ route('admin.subject-differences.show', $c) }}'">
                                <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-700">{{ $c->created_at?->format('d.m.Y H:i') }}</td>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('admin.subject-differences.show', $c) }}" class="font-semibold text-slate-900 hover:text-blue-700 hover:underline">{{ $c->student_name ?: '—' }}</a>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $c->source_institution ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if($c->status === 'error')
                                        <span class="font-semibold text-rose-700">Transkript o'qilmadi</span>
                                    @elseif($sum)
                                        <span class="font-semibold {{ $sum['decision']['passed'] ? 'text-emerald-800' : 'text-rose-700' }}">
                                            {{ $sum['decision']['passed'] ? '✓ ' . $sum['decision']['kurs'] . '-kursga' : '✕ o\'tmadi' }}
                                        </span>
                                    @else
                                        <span class="text-amber-800">O'quv reja tanlanmagan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center font-semibold tabular-nums text-slate-900">{{ $sum ? number_format($sum['gpa'], 2) : '—' }}</td>
                                <td class="px-4 py-2.5 text-center tabular-nums text-slate-900">{{ $sum ? $sum['farq'] : '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $c->created_by_name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-600">Hozircha tekshiruv yo'q. Yuqoridan transkript yuklang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($checks->hasPages())
                    <div class="border-t border-slate-300 px-4 py-3">{{ $checks->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

</x-app-layout>
