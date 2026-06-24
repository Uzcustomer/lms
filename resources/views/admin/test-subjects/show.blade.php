<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">{{ $testSubject->name }}</h1>
                        <p class="text-sm text-slate-500 mt-1">Admin bosqichi uchun yaratilgan test fan tafsilotlari.</p>
                    </div>
                    <a href="{{ route('admin.test-subjects.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                        Orqaga
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Asosiy ma'lumotlar</h2>
                    <div class="space-y-3 text-sm text-slate-700">
                        <div><span class="font-medium text-slate-900">Fakultet:</span> {{ $testSubject->faculty_name ?: '-' }}</div>
                        <div><span class="font-medium text-slate-900">Yo'nalish:</span> {{ $testSubject->specialty_name ?: '-' }}</div>
                        <div><span class="font-medium text-slate-900">Kurs:</span> {{ $testSubject->level_name ?: '-' }}</div>
                        <div><span class="font-medium text-slate-900">O'qituvchi:</span> {{ $testSubject->teacher_name ?: '-' }}</div>
                        <div><span class="font-medium text-slate-900">Muddat:</span> {{ optional($testSubject->starts_on)->format('d.m.Y') ?: '-' }} - {{ optional($testSubject->ends_on)->format('d.m.Y') ?: '-' }}</div>
                        <div><span class="font-medium text-slate-900">Holat:</span> {{ $testSubject->is_active ? 'Faol' : 'Nofaol' }}</div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 xl:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Biriktirilgan guruhlar</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @forelse($testSubject->groups as $group)
                            <div class="rounded-xl border border-slate-200 px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $group->group_name }}</div>
                                <div class="text-xs text-slate-500 mt-1">HEMIS ID: {{ $group->group_hemis_id ?: '-' }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Guruh biriktirilmagan.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-lg font-semibold text-slate-900">Dars jadvali / mavzular</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
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
                                <td class="px-4 py-4 text-slate-700">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</td>
                                <td class="px-4 py-4 text-slate-700">
                                    {{ $lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--' }}
                                    -
                                    {{ $lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--' }}
                                </td>
                                <td class="px-4 py-4 text-slate-700">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</td>
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
