<x-app-layout>

    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">
            <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%) !important;">
                <h1 class="text-2xl font-bold">Shikoyatlar</h1>
                <p class="mt-1 text-sm text-white/80">Xalqaro ta'lim fakulteti talabalarining shikoyatlarini ko'rish va ko'rib chiqish oynasi</p>
            </div>

            @if(session('success'))
                <div class="rounded-xl border px-4 py-3 text-sm font-semibold" style="border-color:#86efac;background:#ecfdf5;color:#166534;">{{ session('success') }}</div>
            @endif
            @if($migrationPending)
                <div class="rounded-xl border px-4 py-3 text-sm font-semibold" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">Jadval hali yaratilmagan — serverda <b>php artisan migrate</b> ni ishga tushiring.</div>
            @endif

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #0ea5e9 !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-blue-700">Jami</span><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-amber-700">Yangi</span><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-amber-800">{{ $stats['new'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-teal-700">Hal etilgan</span><span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-teal-800">{{ $stats['resolved'] }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div><h3 class="text-sm font-bold text-slate-800">Shikoyatlar ro'yxati</h3><p class="mt-0.5 text-xs text-slate-500">Topildi: {{ $complaints->total() }} ta</p></div>
                    <div class="flex items-center gap-2">
                        @if(request()->filled('search') || request()->filled('status'))
                            <a href="{{ route('admin.student-complaints.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Tozalash</a>
                        @endif
                        <button type="submit" form="complaint-filters" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Filtrlash
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <form id="complaint-filters" method="GET"></form>
                    <table class="min-w-[1180px] w-full text-left text-sm">
                        <thead class="bg-[#e8eff7] text-[10px] uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">№</th><th class="px-4 py-3">Talaba</th><th class="px-4 py-3">O'qish ma'lumoti</th><th class="px-4 py-3">Telefon</th><th class="px-4 py-3">Shikoyat</th><th class="px-4 py-3">Rasmlar</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Vaqt</th><th class="px-4 py-3">Amallar</th>
                            </tr>
                            <tr class="border-t border-slate-200 bg-white">
                                <th class="px-4 py-2"></th>
                                <th class="px-4 py-2"><label class="sr-only" for="complaint-search">Qidirish</label><input id="complaint-search" form="complaint-filters" name="search" value="{{ request('search') }}" placeholder="Ism, ID, guruh yoki telefon..." class="w-full min-w-[210px] rounded-lg border-slate-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500"></th>
                                <th class="px-4 py-2"></th><th class="px-4 py-2"></th><th class="px-4 py-2"></th><th class="px-4 py-2"></th>
                                <th class="px-4 py-2"><label class="sr-only" for="complaint-status">Holat</label><select id="complaint-status" form="complaint-filters" name="status" class="w-full min-w-[150px] rounded-lg border-slate-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500"><option value="">Barchasi</option><option value="new" @selected(request('status') === 'new')>Yangi</option><option value="resolved" @selected(request('status') === 'resolved')>Hal etilgan</option></select></th>
                                <th class="px-4 py-2"></th><th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($complaints as $complaint)
                                @php
                                    $status = $complaint->isResolved()
                                        ? ['Hal etilgan', 'bg-emerald-100 text-emerald-700']
                                        : ['Yangi', 'bg-amber-100 text-amber-700'];
                                @endphp
                                <tr class="align-top transition hover:bg-blue-50/50">
                                    <td class="px-4 py-4 font-semibold text-slate-400">{{ ($complaints->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-4"><div class="font-bold text-slate-800">{{ $complaint->student_name }}</div><div class="mt-1 text-xs text-slate-500">ID: {{ $complaint->student_id_number ?? '—' }}</div></td>
                                    <td class="px-4 py-4 text-xs text-slate-600"><div class="font-semibold text-slate-700">{{ $complaint->faculty_name ?? '—' }}</div><div class="mt-1 font-semibold">{{ $complaint->group_name ?? '—' }}</div></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-700"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $complaint->phone) }}" class="font-semibold text-blue-700 hover:underline">{{ $complaint->phone }}</a></td>
                                    <td class="px-4 py-4 leading-6 text-slate-600" style="min-width:260px;max-width:420px;white-space:pre-line;word-break:break-word;">{{ $complaint->message }}</td>
                                    <td class="px-4 py-4">
                                        @if(!empty($complaint->images))
                                            <div class="flex flex-wrap gap-2" style="max-width:220px;">
                                                @foreach($complaint->images as $i => $path)
                                                    @php $src = route('admin.student-complaints.image', [$complaint, $i]); @endphp
                                                    <button type="button" class="sc-zoom rounded-lg border border-slate-200" data-src="{{ $src }}"
                                                            aria-label="Rasmni kattalashtirish" style="width:64px;height:64px;padding:0;overflow:hidden;background:#f1f5f9;cursor:zoom-in;">
                                                        <img src="{{ $src }}" alt="Shikoyat rasmi {{ $i + 1 }}" loading="lazy" style="display:block;width:100%;height:100%;object-fit:cover;">
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
                                        @if($complaint->isResolved())
                                            <div class="mt-1 text-xs text-slate-500">{{ $complaint->resolved_at?->format('d.m.Y H:i') }}@if($complaint->resolved_by_name)<br>{{ $complaint->resolved_by_name }}@endif</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $complaint->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col items-start gap-2">
                                            @unless($complaint->isResolved())
                                                <form method="POST" action="{{ route('admin.student-complaints.resolve', $complaint) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        Hal etildi
                                                    </button>
                                                </form>
                                            @endunless
                                            <form method="POST" action="{{ route('admin.student-complaints.destroy', $complaint) }}"
                                                  onsubmit="return confirm('Shikoyat rasmlari bilan butunlay o\'chirilsinmi? Qaytarib bo\'lmaydi.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition" style="border:1px solid #fecaca;background:#fff;color:#b91c1c;">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 0l1 12a1 1 0 001 1h4a1 1 0 001-1l1-12"/></svg>
                                                    O'chirish
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-14 text-center text-sm text-slate-500">Shikoyatlar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($complaints->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $complaints->links() }}</div>@endif
            </div>
        </div>
    </div>

    {{-- Rasmni kattalashtirib ko'rish --}}
    <div id="scView" role="dialog" aria-label="Rasm"
         style="position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.88);cursor:zoom-out;">
        <img src="" alt="Shikoyat rasmi" style="max-width:100%;max-height:100%;border-radius:6px;box-shadow:0 10px 40px rgba(0,0,0,.4);">
    </div>
    <script>
    (function () {
        const view = document.getElementById('scView');
        const img = view.querySelector('img');
        const close = () => { view.style.display = 'none'; };
        document.querySelectorAll('.sc-zoom').forEach((btn) => {
            btn.addEventListener('click', () => { img.src = btn.dataset.src; view.style.display = 'flex'; });
        });
        view.addEventListener('click', close);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();
    </script>
</x-app-layout>
