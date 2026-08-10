<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800">O'qishni ko'chirish arizalari</h2>
                <p class="mt-1 text-xs text-slate-500">Talabalar yuborgan o'qishni ko'chirish arizalari</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">Jami: {{ $stats['total'] }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 pb-8 sm:px-6 lg:px-8">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Jami</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm"><p class="text-xs text-amber-700">Kutilmoqda</p><p class="mt-1 text-2xl font-bold text-amber-800">{{ $stats['pending'] }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><p class="text-xs text-emerald-700">Qabul qilingan</p><p class="mt-1 text-2xl font-bold text-emerald-800">{{ $stats['approved'] }}</p></div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm"><p class="text-xs text-red-700">Rad etilgan</p><p class="mt-1 text-2xl font-bold text-red-800">{{ $stats['rejected'] }}</p></div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label class="min-w-[260px] flex-1 text-xs font-semibold text-slate-600">
                    Qidirish
                    <input name="search" value="{{ request('search') }}" placeholder="Ism, HEMIS ID yoki telefon..." class="mt-1.5 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="w-full sm:w-52 text-xs font-semibold text-slate-600">
                    Holat
                    <select name="status" class="mt-1.5 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Barchasi</option>
                        <option value="pending" @selected(request('status') === 'pending')>Kutilmoqda</option>
                        <option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option>
                    </select>
                </label>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">Filtrlash</button>
                @if(request()->filled('search') || request()->filled('status'))
                    <a href="{{ route('admin.student-transfer-applications.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Tozalash</a>
                @endif
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-sm">
                    <thead class="bg-slate-100 text-[11px] uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">№</th>
                            <th class="px-4 py-3">Talaba</th>
                            <th class="px-4 py-3">O'qish ma'lumoti</th>
                            <th class="px-4 py-3">Telefon</th>
                            <th class="px-4 py-3">Sabab</th>
                            <th class="px-4 py-3">Buyruq</th>
                            <th class="px-4 py-3">Holat</th>
                            <th class="px-4 py-3">Vaqt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($applications as $application)
                            @php
                                $status = [
                                    'pending' => ['Kutilmoqda', 'bg-amber-100 text-amber-700'],
                                    'approved' => ['Qabul qilingan', 'bg-emerald-100 text-emerald-700'],
                                    'rejected' => ['Rad etilgan', 'bg-red-100 text-red-700'],
                                ][$application->status] ?? [$application->status, 'bg-slate-100 text-slate-700'];
                            @endphp
                            <tr class="align-top transition hover:bg-blue-50/40">
                                <td class="px-4 py-4 font-semibold text-slate-500">{{ ($applications->firstItem() ?? 1) + $loop->index }}</td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-800">{{ $application->student?->full_name ?? '—' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">HEMIS: {{ $application->student?->hemis_id ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <div>{{ $application->student?->department_name ?? '—' }}</div>
                                    <div class="mt-1">{{ $application->student?->specialty_name ?? '—' }}</div>
                                    <div class="mt-1 font-semibold">{{ $application->student?->level_name ?? '—' }} · {{ $application->student?->group_name ?? '—' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $application->phone }}</td>
                                <td class="max-w-xs px-4 py-4 leading-6 text-slate-600">{{ $application->reason }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.student-transfer-applications.document', $application) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                        <span>Ko'rish</span>
                                    </a>
                                    <div class="mt-1 max-w-[150px] truncate text-[11px] text-slate-400" title="{{ $application->order_name }}">{{ $application->order_name }}</div>
                                </td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500">Arizalar topilmadi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($applications->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $applications->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
