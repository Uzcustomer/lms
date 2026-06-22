<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-slate-500 mb-1">Test fan tafsilotlari</div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ $testSubject->name }}</h1>
                    <p class="text-sm text-slate-500 mt-2">
                        {{ $testSubject->department_name ?: 'Bo‘lim tanlanmagan' }}
                        · {{ $testSubject->specialty_name ?: 'Yo‘nalish tanlanmagan' }}
                        · {{ $testSubject->level_name ?: 'Kurs tanlanmagan' }}
                    </p>
                </div>
                <a href="{{ route('admin.test-subjects.index') }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                    Orqaga
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Asosiy ma’lumotlar</h2>
                    <div class="space-y-3 text-sm">
                        <div><span class="text-slate-500">O‘qituvchi:</span> <span class="font-semibold text-slate-900">{{ $testSubject->teacher_name ?: '—' }}</span></div>
                        <div><span class="text-slate-500">Boshlanish:</span> <span class="font-semibold text-slate-900">{{ optional($testSubject->starts_on)->format('d.m.Y') ?: '—' }}</span></div>
                        <div><span class="text-slate-500">Tugash:</span> <span class="font-semibold text-slate-900">{{ optional($testSubject->ends_on)->format('d.m.Y') ?: '—' }}</span></div>
                        <div><span class="text-slate-500">Holat:</span> <span class="font-semibold {{ $testSubject->is_active ? 'text-emerald-600' : 'text-rose-600' }}">{{ $testSubject->is_active ? 'Faol' : 'Nofaol' }}</span></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:col-span-2">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Biriktirilgan guruhlar</h2>
                    <div class="flex flex-wrap gap-2">
                        @forelse($testSubject->groups as $group)
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-sm font-medium">
                                {{ $group->name }}
                            </span>
                        @empty
                            <div class="text-sm text-slate-500">Guruh biriktirilmagan.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-900">Dars jadvali</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Sana</th>
                                <th class="px-4 py-3 text-left">Vaqt</th>
                                <th class="px-4 py-3 text-left">Mavzu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($testSubject->lessons as $lesson)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $lesson->topic_order }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ optional($lesson->lesson_date)->format('d.m.Y') }}</td>
                                    <td class="px-4 py-4 text-slate-700">
                                        {{ $lesson->starts_at ?: '—' }} - {{ $lesson->ends_at ?: '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-slate-700">{{ $lesson->topic_title ?: 'Mavzu hali kiritilmagan' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500">Dars jadvali kiritilmagan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
