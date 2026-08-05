<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Akademik mobillik arizalari</h1>
                    <p class="mt-1 text-sm text-slate-500">Registrator ofisi tomonidan yuborilgan arizalar ro'yxati</p>
                </div>
                <a href="{{ route('admin.academic-mobility.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m-7 7h18"/>
                    </svg>
                    Talabalar ro'yxati
                </a>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <form method="GET" action="{{ route('admin.academic-mobility.applications') }}"
                      class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-4">
                    <div class="min-w-[280px] flex-1">
                        <label for="application-search" class="mb-1 block text-xs font-semibold uppercase text-slate-500">Qidiruv</label>
                        <input id="application-search" name="search" value="{{ request('search') }}"
                               placeholder="Talaba, HEMIS ID, telefon yoki sabab..."
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Qidirish
                    </button>
                    <a href="{{ route('admin.academic-mobility.applications') }}"
                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                        Tozalash
                    </a>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Talaba</th>
                                <th class="px-4 py-3">O'qish ma'lumoti</th>
                                <th class="px-4 py-3">Telefon</th>
                                <th class="px-4 py-3">Ariza sababi</th>
                                <th class="px-4 py-3">Yuborgan</th>
                                <th class="px-4 py-3">Sana</th>
                                <th class="px-4 py-3">Holat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applications as $application)
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ $application->student?->full_name ?? 'Talaba topilmadi' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            HEMIS: {{ $application->student?->hemis_id ?? '-' }} · ID: {{ $application->student?->student_id_number ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <div>{{ $application->student?->department_name ?? '-' }}</div>
                                        <div class="text-xs">{{ $application->student?->specialty_name ?? '-' }}</div>
                                        <div class="text-xs">{{ $application->student?->level_name ?? '-' }} · {{ $application->student?->group_name ?? '-' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $application->phone }}</td>
                                    <td class="max-w-md whitespace-normal px-4 py-3 text-slate-700">{{ $application->reason }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $application->created_by_name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Yangi</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-14 text-center text-slate-500">Hozircha akademik mobillik arizalari mavjud emas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($applications->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">{{ $applications->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
