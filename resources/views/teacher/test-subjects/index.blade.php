<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h1 class="text-2xl font-bold text-slate-900">Test fanlar</h1>
                <p class="text-sm text-slate-500 mt-1">Sizga biriktirilgan test fanlar va ular bo'yicha mavzu-jadval.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Fan</th>
                            <th class="px-4 py-3 text-left">Kurs</th>
                            <th class="px-4 py-3 text-left">Yo'nalish</th>
                            <th class="px-4 py-3 text-center">Guruhlar</th>
                            <th class="px-4 py-3 text-center">Mavzular</th>
                            <th class="px-4 py-3 text-center">Testlar</th>
                            <th class="px-4 py-3 text-left">Muddat</th>
                            <th class="px-4 py-3 text-right">Amal</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($subjects as $subject)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900">{{ $subject->name }}</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ $subject->faculty_name ?: 'Fakultet tanlanmagan' }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-700">{{ $subject->level_name ?: '-' }}</td>
                                <td class="px-4 py-4 text-slate-700">{{ $subject->specialty_name ?: '-' }}</td>
                                <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $subject->groups->count() }}</td>
                                <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $subject->lessons->count() }}</td>
                                <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $subject->lessons->filter(fn($lesson) => $lesson->lessonTest)->count() }}</td>
                                <td class="px-4 py-4 text-slate-700">
                                    {{ optional($subject->starts_on)->format('d.m.Y') ?: '-' }}
                                    -
                                    {{ optional($subject->ends_on)->format('d.m.Y') ?: '-' }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('teacher.test-subjects.show', $subject) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                        Ochish
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                    Sizga hali test fan biriktirilmagan.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-4 border-t border-slate-100">
                    {{ $subjects->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
