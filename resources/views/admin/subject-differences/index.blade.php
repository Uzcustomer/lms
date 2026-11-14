<x-app-layout>

    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">
            <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%) !important;">
                <h1 class="text-2xl font-bold">Fanlar farqi</h1>
                <p class="mt-1 text-sm text-white/80">Ko'chib kelgan talabaning transkriptini o'quv rejamiz bilan solishtirish: qaysi kursga o'tadi va qaysi fanlarni topshiradi.</p>
            </div>

            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                {{-- Yuklash --}}
                <form method="POST" action="{{ route('admin.subject-differences.store') }}" enctype="multipart/form-data"
                      x-data="{ mode: '{{ old('scale_mode', $defaults['mode']) }}', busy: false, scaleOpen: {{ old('scale_mode') && old('scale_mode') !== 'ball' ? 'true' : 'false' }}, extraOpen: false }" @submit="busy = true"
                      class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
                    @csrf
                    <h3 class="text-base font-bold text-slate-900">Yangi tekshiruv</h3>
                    <p class="mt-0.5 text-sm text-slate-600">Talabaning transkriptini yuklang — fanlar va baholar avtomatik o'qiladi.</p>

                    @unless($aiConfigured)
                        <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            AI kaliti sozlanmagan: skaner qilingan transkriptlar o'qilmaydi. Kalit — <b>Vedomost tekshirish → AI sozlamalari</b>.
                        </div>
                    @endunless

                    <label class="mt-4 block text-sm font-semibold text-slate-800" for="transcript">Transkript (PDF)</label>
                    <input id="transcript" type="file" name="transcript" accept="application/pdf" required
                           class="mt-1 block w-full rounded-lg border border-dashed border-slate-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">
                    <p class="mt-1 text-xs text-slate-600">Faqat transkript sahifalari — pasport va arizasiz.</p>

                    <button type="button" x-show="!extraOpen" @click="extraOpen = true" class="mt-2 text-sm font-semibold text-blue-700 hover:underline">+ Qo'shimcha ko'chirma qo'shish</button>
                    <div x-show="extraOpen" x-cloak class="mt-3">
                        <label class="block text-sm font-semibold text-slate-800" for="extra">Qo'shimcha ko'chirma (PDF)</label>
                        <input id="extra" type="file" name="extra[]" accept="application/pdf" multiple
                               class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-800">
                        <p class="mt-1 text-xs text-slate-600">Transkriptga kirmagan oxirgi semestr baholari bo'lsa ("Reyting daftarchasidan ko'chirma").</p>
                    </div>

                    <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-700">Baho:
                                <b x-show="mode === 'ball'">5 = 90+, 4 = 70+, 3 = 60+ ball</b>
                                <b x-show="mode === 'grade'" x-cloak>transkriptda yozilgani</b>
                            </span>
                            <button type="button" @click="scaleOpen = !scaleOpen" class="text-sm font-semibold text-blue-700 hover:underline" x-text="scaleOpen ? 'Yopish' : 'O\'zgartirish'">O'zgartirish</button>
                        </div>
                        <div x-show="scaleOpen" x-cloak class="mt-2 space-y-2 border-t border-slate-200 pt-2">
                            <label class="flex items-center gap-2"><input type="radio" name="scale_mode" value="ball" x-model="mode"> Balldan hisoblansin</label>
                            <div x-show="mode === 'ball'" class="grid grid-cols-3 gap-2 pl-6">
                                @foreach(['five' => '5 baho ≥', 'four' => '4 baho ≥', 'three' => '3 baho ≥'] as $f => $lbl)
                                    <label class="text-xs text-slate-600">{{ $lbl }}
                                        <input type="number" step="0.01" min="0" max="100" name="scale_{{ $f }}" value="{{ old('scale_' . $f, $defaults[$f]) }}"
                                               class="mt-0.5 w-full rounded-md border-slate-300 px-2 py-1 text-sm">
                                    </label>
                                @endforeach
                            </div>
                            <label class="flex items-center gap-2"><input type="radio" name="scale_mode" value="grade" x-model="mode"> Transkriptda yozilgan baho (5/4/3/2)</label>
                            <p class="text-xs text-slate-600">Transkript pastidagi "Baholash tizimi"ga qarang.</p>
                        </div>
                    </div>

                    <button type="submit" :disabled="busy"
                            class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span x-text="busy ? 'Transkript o\'qilmoqda… (1–2 daqiqa)' : 'Yuklash va o\'qish'">Yuklash va o'qish</span>
                    </button>
                    <p class="mt-2 text-xs text-slate-600">Talaba ismi va OTM nomi transkriptdan olinadi, keyin o'zgartirish mumkin.</p>
                </form>

                {{-- Ro'yxat --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div><h3 class="text-sm font-bold text-slate-800">Tekshiruvlar</h3><p class="mt-0.5 text-xs text-slate-500">Jami: {{ $checks->total() }} ta</p></div>
                        <form method="GET" class="flex items-center gap-2">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba yoki OTM" class="rounded-lg border-slate-300 px-3 py-1.5 text-sm">
                            <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Qidirish</button>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-2">Sana</th>
                                    <th class="px-4 py-2">Talaba</th>
                                    <th class="px-4 py-2">Avvalgi OTM</th>
                                    <th class="px-4 py-2">Natija</th>
                                    <th class="px-4 py-2">Kim</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($checks as $c)
                                    @php
                                        $lastLevel = collect($c->levels ?? [])->last();
                                    @endphp
                                    <tr class="cursor-pointer hover:bg-blue-50/50" onclick="window.location='{{ route('admin.subject-differences.show', $c) }}'">
                                        <td class="whitespace-nowrap px-4 py-2 text-xs text-slate-600">{{ $c->created_at?->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 font-semibold text-slate-800">
                                            <a href="{{ route('admin.subject-differences.show', $c) }}" class="hover:text-blue-700">{{ $c->student_name ?: '—' }}</a>
                                        </td>
                                        <td class="px-4 py-2 text-slate-600">{{ $c->source_institution ?: '—' }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @php $sum = $summaries[$c->id] ?? null; @endphp
                                            @if($c->status === 'error')
                                                <span class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-800">Transkript o'qilmadi</span>
                                            @elseif($sum)
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded-full px-2 py-0.5 font-semibold {{ $sum['decision']['passed'] ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                        {{ $sum['decision']['passed'] ? '✓' : '✕' }} {{ $sum['decision']['kurs'] }}-kurs
                                                    </span>
                                                    <span class="font-semibold tabular-nums text-slate-800">GPA {{ number_format($sum['gpa'], 2) }}</span>
                                                    <span class="whitespace-nowrap text-slate-600">{{ $sum['farq'] }} farq</span>
                                                </div>
                                                <div class="mt-0.5 text-[11px] text-slate-600">{{ $sum['levels'] }} bosqich · 1–{{ $sum['upto'] }}-semestr fanlari bo'yicha</div>
                                            @else
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">O'quv reja tanlanmagan</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-xs text-slate-600">{{ $c->created_by_name }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Hozircha tekshiruv yo'q.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $checks->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
