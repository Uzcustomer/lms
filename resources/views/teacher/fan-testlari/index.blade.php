<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="rounded-[24px] bg-gradient-to-r from-slate-950 via-blue-900 to-cyan-700 px-6 py-7 text-white shadow-xl sm:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-cyan-200">
                            <span class="rounded-full border border-cyan-300/40 px-2 py-1">Test moduli</span>
                            <span>{{ $collections->total() }} ta to'plam</span>
                        </div>
                        <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Test yaratish</h1>
                        <p class="mt-2 max-w-2xl text-sm text-blue-100">Dars jadvali qo'yilishidan oldin fanlar bo'yicha test to'plamlarini tayyorlab qo'ying.</p>
                    </div>
                    <a href="{{ route('teacher.fan-testlari.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-blue-900 shadow-lg transition hover:-translate-y-0.5 hover:bg-cyan-50">
                        <span class="text-lg leading-none">+</span>
                        Yangi test to'plami
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <form method="GET" class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="To'plam yoki fan nomi..."
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <select name="subject_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Barcha fanlar</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected((int) request('subject_id') === (int) $subject->id)>
                                {{ $subject->subject_name }} @if($subject->semester_name) - {{ $subject->semester_name }} @endif
                            </option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">Filtrlash</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="font-black text-slate-900">Yaratilgan test to'plamlari</h2>
                        <p class="mt-1 text-xs text-slate-500">Keyinchalik bu to'plamlardan dars testiga biriktiriladi.</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $collections->count() }} ta ko'rsatildi</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Test to'plami</th>
                            <th class="px-5 py-4">Fan</th>
                            <th class="px-5 py-4 text-center">Savollar</th>
                            <th class="px-5 py-4 text-center">Vaqt</th>
                            <th class="px-5 py-4">Holat</th>
                            <th class="px-5 py-4 text-right">Amal</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($collections as $collection)
                            <tr class="transition hover:bg-blue-50/40">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-900">{{ $collection->name }}</div>
                                    @if($collection->description)
                                        <div class="mt-1 max-w-sm truncate text-xs text-slate-500">{{ $collection->description }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-800">{{ $collection->subject?->subject_name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $collection->subject?->semester_name ?? $collection->subject?->subject_code ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-4 text-center font-black text-slate-800">{{ $collection->questionCount() }}</td>
                                <td class="px-5 py-4 text-center text-slate-700">{{ $collection->duration_minutes }} daqiqa</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $collection->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $collection->is_active ? 'Faol' : 'Nofaol' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('teacher.fan-testlari.edit', $collection) }}" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100">Tahrirlash</a>
                                        <form method="POST" action="{{ route('teacher.fan-testlari.destroy', $collection) }}" onsubmit="return confirm('Bu test to\'plami va savollarini o\'chirishni tasdiqlaysizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100">O'chirish</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl text-blue-600">?</div>
                                    <h3 class="mt-4 font-black text-slate-800">Hali test to'plami yaratilmagan</h3>
                                    <p class="mt-1 text-sm text-slate-500">Birinchi test to'plamingizni yaratib, savollarni oldindan tayyorlang.</p>
                                    <a href="{{ route('teacher.fan-testlari.create') }}" class="mt-5 inline-flex rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Test yaratishni boshlash</a>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($collections->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">{{ $collections->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
