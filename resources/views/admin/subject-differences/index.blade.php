<x-app-layout>

    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">
            <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%) !important;">
                <h1 class="text-2xl font-bold">Fanlar farqi</h1>
                <p class="mt-1 text-sm text-white/80">Ko'chib kelgan talabaning transkripti bizning o'quv reja (akkreditatsiyadagi fanlar) bilan solishtiriladi: baho transkriptdan, fan va kredit bizning rejadan. GPA ≥ 2.4 bo'lsa shu kursga qabul.</p>
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
                      x-data="{ mode: '{{ old('scale_mode', $defaults['mode']) }}', busy: false }" @submit="busy = true"
                      class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
                    @csrf
                    <h3 class="text-sm font-bold text-slate-800">Yangi tekshiruv</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Faqat transkript yuklanadi (pasport, ariza va boshqa hujjatlarsiz).</p>

                    @unless($aiConfigured)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Gemini API kaliti sozlanmagan — skaner (rasm) transkriptlar o'qilmaydi, faqat matnli PDF'lar qoida bilan o'qiladi.
                            Kalit: <b>Vedomost tekshirish → AI sozlamalari</b>.
                        </div>
                    @endunless

                    <label class="mt-4 block text-xs font-semibold text-slate-600">Transkript (PDF) <span class="text-rose-500">*</span></label>
                    <input type="file" name="transcript" accept="application/pdf" required
                           class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700">

                    <label class="mt-3 block text-xs font-semibold text-slate-600">Qo'shimcha fayl (ixtiyoriy)</label>
                    <input type="file" name="extra[]" accept="application/pdf" multiple
                           class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                    <p class="mt-1 text-[11px] text-slate-500">Masalan, transkriptga kirmagan oxirgi semestr baholari uchun "Reyting daftarchasidan ko'chirma".</p>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Talaba F.I.Sh.</label>
                            <input type="text" name="student_name" value="{{ old('student_name') }}" placeholder="Bo'sh qolsa transkriptdan olinadi"
                                   class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Avvalgi OTM</label>
                            <input type="text" name="source_institution" value="{{ old('source_institution') }}" placeholder="Bo'sh qolsa transkriptdan olinadi"
                                   class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        </div>
                    </div>

                    <fieldset class="mt-4 rounded-xl border border-slate-200 p-3">
                        <legend class="px-1 text-xs font-semibold text-slate-600">Baho shkalasi</legend>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="scale_mode" value="ball" x-model="mode" class="mt-0.5">
                            <span>Balldan hisoblansin (100 ballik)</span>
                        </label>
                        <div x-show="mode === 'ball'" class="mt-2 grid grid-cols-3 gap-2 pl-6">
                            @foreach(['five' => '5 baho ≥', 'four' => '4 baho ≥', 'three' => '3 baho ≥'] as $f => $lbl)
                                <label class="text-[11px] text-slate-500">{{ $lbl }}
                                    <input type="number" step="0.01" min="0" max="100" name="scale_{{ $f }}" value="{{ old('scale_' . $f, $defaults[$f]) }}"
                                           class="mt-0.5 w-full rounded-lg border-slate-300 px-2 py-1 text-sm">
                                </label>
                            @endforeach
                        </div>
                        <label class="mt-2 flex items-start gap-2 text-sm">
                            <input type="radio" name="scale_mode" value="grade" x-model="mode" class="mt-0.5">
                            <span>Transkriptda yozilgan baho (5/4/3/2) olinsin</span>
                        </label>
                        <p class="mt-2 text-[11px] text-slate-500">Transkript pastidagi "Baholash tizimi"ga qarab belgilang. Ball va yozilgan baho zid kelsa, qatorda ogohlantirish chiqadi.</p>
                    </fieldset>

                    <button type="submit" :disabled="busy"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60">
                        <svg x-show="busy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span x-text="busy ? 'Transkript o\'qilmoqda… (1–2 daqiqa)' : 'Yuklash va o\'qish'">Yuklash va o'qish</span>
                    </button>
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
                                                    <span class="whitespace-nowrap text-slate-600">· {{ $sum['farq'] }} farq</span>
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
