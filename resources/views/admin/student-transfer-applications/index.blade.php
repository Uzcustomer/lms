<x-app-layout>

    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">
            <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%) !important;">
                <h1 class="text-2xl font-bold">O'qishni ko'chirish arizalari</h1>
                <p class="mt-1 text-sm text-white/80">Talabalar yuborgan arizalarni ko'rish, saralash va ko'rib chiqish oynasi</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #0ea5e9 !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-blue-700">Jami</span><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-amber-700">Kutilmoqda</span><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-amber-800">{{ $stats['pending'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-teal-700">Qabul qilingan</span><span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-teal-800">{{ $stats['approved'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="border-left: 4px solid #ef4444 !important;">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold text-rose-700">Rad etilgan</span><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span></div>
                    <p class="mt-2 text-2xl font-bold text-rose-800">{{ $stats['rejected'] }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div><h3 class="text-sm font-bold text-slate-800">Arizalar ro'yxati</h3><p class="mt-0.5 text-xs text-slate-500">Topildi: {{ $applications->total() }} ta</p></div>
                    <div class="flex items-center gap-2">
                        @if(request()->filled('search') || request()->filled('status'))
                            <a href="{{ route('admin.student-transfer-applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Tozalash</a>
                        @endif
                        <button type="submit" form="transfer-filters" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Filtrlash
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <form id="transfer-filters" method="GET">
                        <table class="min-w-[1180px] w-full text-left text-sm">
                            <thead class="bg-[#e8eff7] text-[10px] uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">№</th><th class="px-4 py-3">Talaba</th><th class="px-4 py-3">O'qish ma'lumoti</th><th class="px-4 py-3">Telefon</th><th class="px-4 py-3">Ko'chiriladigan ta'lim tashkiloti</th><th class="px-4 py-3">Sabab</th><th class="px-4 py-3">Hujjatlar</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Vaqt</th>
                                </tr>
                                <tr class="border-t border-slate-200 bg-white">
                                    <th class="px-4 py-2"></th>
                                    <th class="px-4 py-2"><label class="sr-only" for="transfer-search">Qidirish</label><input id="transfer-search" name="search" value="{{ request('search') }}" placeholder="Ism, HEMIS ID yoki telefon..." class="w-full min-w-[210px] rounded-lg border-slate-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500"></th>
                                    <th class="px-4 py-2"></th><th class="px-4 py-2"></th><th class="px-4 py-2"></th><th class="px-4 py-2"></th><th class="px-4 py-2"></th>
                                    <th class="px-4 py-2"><label class="sr-only" for="transfer-status">Holat</label><select id="transfer-status" name="status" class="w-full min-w-[150px] rounded-lg border-slate-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500"><option value="">Barchasi</option><option value="pending" @selected(request('status') === 'pending')>Kutilmoqda</option><option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option><option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option></select></th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applications as $application)
                                @php
                                    $status = [
                                        'pending' => ["Kutilmoqda", 'bg-amber-100 text-amber-700'],
                                        'approved' => ["Qabul qilingan", 'bg-emerald-100 text-emerald-700'],
                                        'rejected' => ["Rad etilgan", 'bg-red-100 text-red-700'],
                                    ][$application->status] ?? [$application->status, 'bg-slate-100 text-slate-700'];
                                @endphp
                                <tr class="align-top transition hover:bg-blue-50/50">
                                    <td class="px-4 py-4 font-semibold text-slate-400">{{ ($applications->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-4"><div class="font-bold text-slate-800">{{ $application->student?->full_name ?? '—' }}</div><div class="mt-1 text-xs text-slate-500">HEMIS: {{ $application->student?->hemis_id ?? '—' }}</div></td>
                                    <td class="px-4 py-4 text-xs text-slate-600"><div class="font-semibold text-slate-700">{{ $application->student?->department_name ?? '—' }}</div><div class="mt-1">{{ $application->student?->specialty_name ?? '—' }}</div><div class="mt-1 font-semibold">{{ $application->student?->level_name ?? '—' }} · {{ $application->student?->group_name ?? '—' }}</div></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $application->phone }}</td>
                                    <td class="max-w-xs px-4 py-4 text-slate-600">{{ $application->target_institution ?: '—' }}</td>
                                    <td class="max-w-xs px-4 py-4 leading-6 text-slate-600">{{ $application->reason }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col items-start gap-2">
                                            <a href="{{ route('admin.student-transfer-applications.document', $application) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/></svg>
                                                Transfer arizasi
                                            </a>
                                            @if($application->basis_document_path && $application->basis_document_path !== 'pending')
                                                <a href="{{ route('admin.student-transfer-applications.basis-document', $application) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h7l3 3v13.5H7a1.5 1.5 0 01-1.5-1.5V5.25A1.5 1.5 0 017 3.75zM13.5 3.75v3.5H17M8.5 12h6m-6 3h6"/></svg>
                                                    Asos hujjati
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-14 text-center text-sm text-slate-500">Arizalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                        </table>
                    </form>
                </div>
                @if($applications->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $applications->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
