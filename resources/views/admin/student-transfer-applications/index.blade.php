<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h9l3 3v13.5H6a1.5 1.5 0 01-1.5-1.5v-13A1.5 1.5 0 016 3.75zM14.5 3.75v3h3M8 11h6m-6 3h6m-6 3h4"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">O'qishni ko'chirish arizalari</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Talabalar yuborgan arizalarni ko'rish</p>
                </div>
            </div>
            <div class="hidden rounded-xl bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 sm:block">Jami: {{ $stats['total'] }}</div>
        </div>
    </x-slot>

    <div class="min-h-full bg-slate-50 px-3 py-5 sm:px-5 lg:px-6">
        <div class="mx-auto max-w-[1600px] space-y-4">
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-[#123d7a] via-[#1d5aa6] to-[#347fc7] p-5 text-white shadow-lg shadow-blue-100">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">Talaba arizalari</p>
                        <h1 class="mt-1 text-xl font-bold">O'qishni ko'chirish bo'yicha nazorat</h1>
                        <p class="mt-1 max-w-xl text-xs leading-5 text-blue-100">Ariza ma'lumotlari va buyruq hujjatlarini yagona oynada ko'ring.</p>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-right backdrop-blur">
                        <p class="text-[11px] text-blue-100">Jami arizalar</p>
                        <p class="mt-0.5 text-2xl font-bold">{{ $stats['total'] }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-semibold text-slate-500">Jami</span><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span></div><p class="mt-2 text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p></div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-semibold text-amber-700">Kutilmoqda</span><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span></div><p class="mt-2 text-2xl font-bold text-amber-800">{{ $stats['pending'] }}</p></div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-semibold text-emerald-700">Qabul qilingan</span><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span></div><p class="mt-2 text-2xl font-bold text-emerald-800">{{ $stats['approved'] }}</p></div>
                <div class="rounded-2xl border border-red-200 bg-red-50/70 p-4 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-semibold text-red-700">Rad etilgan</span><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span></div><p class="mt-2 text-2xl font-bold text-red-800">{{ $stats['rejected'] }}</p></div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <label class="min-w-[260px] flex-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        Qidirish
                        <div class="relative mt-1.5">
                            <svg class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input name="search" value="{{ request('search') }}" placeholder="Ism, HEMIS ID yoki telefon..." class="w-full rounded-xl border-slate-300 py-2.5 pl-9 pr-3 text-sm font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </label>
                    <label class="w-full sm:w-52 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        Holat
                        <select name="status" class="mt-1.5 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            <option value="pending" @selected(request('status') === 'pending')>Kutilmoqda</option>
                            <option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option>
                            <option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option>
                        </select>
                    </label>
                    <button class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Filtrlash
                    </button>
                    @if(request()->filled('search') || request()->filled('status'))
                        <a href="{{ route('admin.student-transfer-applications.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Tozalash</a>
                    @endif
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div><h3 class="text-sm font-bold text-slate-800">Arizalar ro'yxati</h3><p class="mt-0.5 text-xs text-slate-500">Topildi: {{ $applications->total() }} ta</p></div>
                    <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3.75h9l3 3v13.5H6a1.5 1.5 0 01-1.5-1.5v-13A1.5 1.5 0 016 3.75zM14.5 3.75v3h3M8 11h6m-6 3h6m-6 3h4"/></svg>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full text-left text-sm">
                        <thead class="bg-slate-100 text-[10px] uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">№</th><th class="px-4 py-3">Talaba</th><th class="px-4 py-3">O'qish ma'lumoti</th><th class="px-4 py-3">Telefon</th><th class="px-4 py-3">Sabab</th><th class="px-4 py-3">Buyruq</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Vaqt</th>
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
                                    <td class="max-w-xs px-4 py-4 leading-6 text-slate-600">{{ $application->reason }}</td>
                                    <td class="px-4 py-4"><a href="{{ route('admin.student-transfer-applications.document', $application) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/></svg>Ko'rish</a><div class="mt-1 max-w-[160px] truncate text-[11px] text-slate-400" title="{{ $application->order_name }}">{{ $application->order_name }}</div></td>
                                    <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500">Arizalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($applications->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $applications->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
