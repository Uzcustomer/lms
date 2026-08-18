<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl shadow-sm border border-blue-100 p-6 text-white"
                 style="background: linear-gradient(135deg, #153e75 0%, #2563eb 62%, #38bdf8 100%);">
                <div>
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-blue-100">Test moduli</div>
                    <h1 class="text-3xl font-extrabold mt-1">Test fanlar</h1>
                    <p class="text-sm text-blue-50 mt-2">{{ $curriculumSubjectYear }} o'quv yilidagi kafedra fanlarini semesterlari bilan ko'ring va ulardan test fan yarating.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex flex-col xl:flex-row xl:items-end gap-4">
                    <form method="GET" action="{{ route('admin.test-subjects.index') }}" class="flex-1 grid grid-cols-1 md:grid-cols-[minmax(260px,520px)_auto_auto] gap-3 items-end">
                        <div>
                            <label for="kafedra_id" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Kafedra</label>
                            <select id="kafedra_id" name="kafedra_id"
                                    class="w-full rounded-xl border-slate-300 bg-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Kafedra tanlang --</option>
                                @foreach($kafedras as $departmentId => $name)
                                    <option value="{{ $departmentId }}" @selected((string) $selectedKafedraId === (string) $departmentId)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-sm">
                            Fanlarni ko'rish
                        </button>
                        @if($selectedKafedraId)
                            <a href="{{ route('admin.test-subjects.index') }}"
                               class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                                Tozalash
                            </a>
                        @endif
                    </form>

                    @if($selectedKafedraId)
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-100 px-5 py-3 text-sm text-emerald-700 min-w-[190px]">
                            <div class="text-xs font-bold uppercase tracking-wide text-emerald-500">Topildi</div>
                            <div class="text-2xl font-extrabold text-emerald-700">{{ $curriculumSubjects->total() }}</div>
                            <div>{{ $curriculumSubjectYear }} fan/semester</div>
                        </div>
                    @endif
                    </div>
                </div>

                <div class="p-5">
                @if(!$selectedKafedraId)
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <div class="text-lg font-bold text-slate-900">Kafedra tanlang</div>
                        <div class="text-sm text-slate-500 mt-1">Tanlangan kafedraga bog'langan fanlar semesterlari bilan shu yerda chiqadi.</div>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-sm">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600 uppercase text-xs tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Fan</th>
                                <th class="px-4 py-3 text-left">Yo'nalish / semester</th>
                                <th class="px-4 py-3 text-left">Kafedra</th>
                                <th class="px-4 py-3 text-center">Yuklama</th>
                                <th class="px-4 py-3 text-right">Amal</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($curriculumSubjects as $curriculumSubject)
                                <tr class="hover:bg-blue-50/40 transition">
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-bold text-slate-900 leading-5">{{ $curriculumSubject->subject_name }}</div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">
                                                {{ $curriculumSubject->subject_code ?: 'Kod yo\'q' }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                                                {{ $curriculumSubjectYear }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-semibold text-slate-800">{{ $curriculumSubject->specialty_name ?: '-' }}</div>
                                        <div class="mt-2">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                                                {{ $curriculumSubject->semester_name ?: $curriculumSubject->semester_code }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                            {{ $curriculumSubject->department_name ?: '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center align-top">
                                        <div class="inline-flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                            <div class="px-3 py-2 border-r border-slate-200">
                                                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Soat</div>
                                                <div class="font-extrabold text-slate-900">{{ rtrim(rtrim((string) $curriculumSubject->total_acload, '0'), '.') ?: '-' }}</div>
                                            </div>
                                            <div class="px-3 py-2">
                                                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Kredit</div>
                                                <div class="font-extrabold text-slate-900">{{ rtrim(rtrim((string) $curriculumSubject->credit, '0'), '.') ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right align-top">
                                        <a href="{{ route('admin.test-subjects.create', [
                                                'name' => $curriculumSubject->subject_name,
                                                'faculty_hemis_id' => $curriculumSubject->department_hemis_id,
                                                'specialty_hemis_id' => $curriculumSubject->specialty_hemis_id,
                                            ]) }}"
                                           class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                                            Test yaratish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                        Bu kafedra bo'yicha {{ $curriculumSubjectYear }} o'quv yilida fan topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $curriculumSubjects->links() }}
                    </div>
                @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-4 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-900">Yaratilgan test fanlar</h2>
                    <p class="text-sm text-slate-500 mt-1">Avval yaratilgan test fanlar ro'yxati.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Fan</th>
                            <th class="px-4 py-3 text-left">O'qituvchi</th>
                            <th class="px-4 py-3 text-left">Kurs</th>
                            <th class="px-4 py-3 text-left">Yo'nalish</th>
                            <th class="px-4 py-3 text-center">Guruhlar</th>
                            <th class="px-4 py-3 text-center">Darslar</th>
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
                                <td class="px-4 py-4 text-slate-700">{{ $subject->teacher_name ?: '-' }}</td>
                                <td class="px-4 py-4 text-slate-700">{{ $subject->level_name ?: '-' }}</td>
                                <td class="px-4 py-4 text-slate-700">{{ $subject->specialty_name ?: '-' }}</td>
                                <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $subject->groups->count() }}</td>
                                <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $subject->lessons->count() }}</td>
                                <td class="px-4 py-4 text-slate-700">
                                    {{ optional($subject->starts_on)->format('d.m.Y') ?: '-' }}
                                    -
                                    {{ optional($subject->ends_on)->format('d.m.Y') ?: '-' }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.test-subjects.show', $subject) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                                        Ko'rish
                                    </a>
                                        <form method="POST" action="{{ route('admin.test-subjects.destroy', $subject) }}"
                                              onsubmit="return confirm('Test fanni o\\'chirishni tasdiqlaysizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                    Hozircha test fan yaratilmagan.
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
