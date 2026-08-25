<x-app-layout>
    <div class="py-4 sm:py-5">
        <div class="w-full px-3 sm:px-5 lg:px-7 space-y-4">
            <section class="relative overflow-hidden rounded-2xl border border-blue-900/20 px-4 py-4 text-white shadow-lg shadow-blue-950/10 sm:px-5 sm:py-5"
                     style="background: linear-gradient(115deg, #102f5b 0%, #1d4ed8 58%, #0ea5e9 100%);">
                <div class="pointer-events-none absolute -right-10 -top-14 h-36 w-36 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-20 right-24 h-44 w-44 rounded-full border-[18px] border-white/10"></div>
                <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-blue-100">Test moduli</div>
                            <h1 class="mt-0.5 truncate text-2xl font-extrabold tracking-tight sm:text-[28px]">Test fanlar</h1>
                            <p class="mt-1 max-w-2xl text-xs leading-5 text-blue-50">{{ implode(', ', $curriculumSubjectYears) }} o'quv yillaridagi fanlardan tezkor test moduli yarating.</p>
                        </div>
                    </div>
                    <div class="flex w-fit items-center gap-3 rounded-xl border border-white/20 bg-white/10 px-3 py-2 backdrop-blur-sm">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wide text-blue-100">Yaratilgan fanlar</div>
                            <div class="text-2xl font-extrabold leading-6">{{ $subjects->total() }}</div>
                        </div>
                        <svg class="h-7 w-7 text-cyan-100" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 016.5 3H20v15.5A2.5 2.5 0 0117.5 21H6.5A2.5 2.5 0 014 18.5v-13zM4 6h13.5A2.5 2.5 0 0120 8.5M8 10h8m-8 4h5"/>
                        </svg>
                    </div>
                </div>
            </section>

            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 016.5 3H20v15.5A2.5 2.5 0 0117.5 21H6.5A2.5 2.5 0 014 18.5v-13zM4 6h13.5A2.5 2.5 0 0120 8.5M8 10h8m-8 4h5"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900">Fanlar katalogi</h2>
                            <p class="text-[11px] text-slate-500">Kafedrani tanlang va test fanini boshlang</p>
                        </div>
                    </div>
                    @if($selectedKafedraId)
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            {{ $curriculumSubjects->total() }} ta fan topildi
                        </span>
                    @endif
                </div>
                <div class="border-b border-slate-100 bg-slate-50/70 px-3 py-3 sm:px-4">
                    <form method="GET" action="{{ route('admin.test-subjects.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label for="kafedra_id" class="mb-1 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Kafedra</label>
                            <select id="kafedra_id" name="kafedra_id"
                                    class="w-full rounded-lg border-slate-300 bg-white py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Kafedra tanlang --</option>
                                @foreach($kafedras as $departmentId => $name)
                                    <option value="{{ $departmentId }}" @selected((string) $selectedKafedraId === (string) $departmentId)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-4 text-xs font-extrabold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Ko'rish
                        </button>
                        @if($selectedKafedraId)
                            <a href="{{ route('admin.test-subjects.index') }}"
                               class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-100">
                                Tozalash
                            </a>
                        @endif
                    </form>
                </div>

                <div class="p-3 sm:p-4">
                @if(!$selectedKafedraId)
                    <div class="flex items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.3 4.3L2.9 17.1A2 2 0 004.6 20h14.8a2 2 0 001.7-2.9L13.7 4.3a2 2 0 00-3.4 0z"/></svg>
                        </span>
                        <div>
                            <div class="text-sm font-extrabold text-slate-900">Kafedra tanlang</div>
                            <div class="mt-0.5 text-xs text-slate-500">Fanlar va semesterlar tanlangan kafedraga qarab chiqadi.</div>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-100 text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="px-3 py-2.5 text-left">Fan</th>
                                <th class="px-3 py-2.5 text-left">Yo'nalish / semester</th>
                                <th class="px-3 py-2.5 text-left">Kafedra</th>
                                <th class="px-3 py-2.5 text-center">Yuklama</th>
                                <th class="px-3 py-2.5 text-right">Amal</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($curriculumSubjects as $curriculumSubject)
                                <tr class="group transition hover:bg-blue-50/50">
                                    <td class="px-3 py-3 align-top">
                                        <div class="font-extrabold leading-5 text-slate-900">{{ $curriculumSubject->subject_name }}</div>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                                {{ $curriculumSubject->subject_code ?: 'Kod yo\'q' }}
                                            </span>
                                            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                {{ $curriculumSubject->education_year_name ?: implode(', ', $curriculumSubjectYears) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="max-w-[240px] font-semibold leading-5 text-slate-800">{{ $curriculumSubject->specialty_name ?: '-' }}</div>
                                        <div class="mt-1.5">
                                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-extrabold text-indigo-700">
                                                {{ $curriculumSubject->semester_name ?: $curriculumSubject->semester_code }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <span class="inline-flex max-w-[200px] items-center rounded-md bg-amber-50 px-2 py-1 text-[10px] font-bold leading-4 text-amber-700">
                                            {{ $curriculumSubject->department_name ?: '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center align-top">
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2 py-1.5 ring-1 ring-slate-200">
                                            <div class="text-center">
                                                <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Soat</div>
                                                <div class="text-xs font-extrabold text-slate-900">{{ rtrim(rtrim((string) $curriculumSubject->total_acload, '0'), '.') ?: '-' }}</div>
                                            </div>
                                            <span class="h-6 w-px bg-slate-200"></span>
                                            <div class="text-center">
                                                <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Kredit</div>
                                                <div class="text-xs font-extrabold text-slate-900">{{ rtrim(rtrim((string) $curriculumSubject->credit, '0'), '.') ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right align-top">
                                        <a href="{{ route('admin.test-subjects.create', [
                                                'name' => $curriculumSubject->subject_name,
                                                'faculty_hemis_id' => $curriculumSubject->department_hemis_id,
                                                'specialty_hemis_id' => $curriculumSubject->specialty_hemis_id,
                                            ]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-2.5 py-1.5 text-[11px] font-extrabold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
                                            Yaratish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                        Bu kafedra bo'yicha {{ implode(', ', $curriculumSubjectYears) }} o'quv yillarida fan topilmadi.
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

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-900">Yaratilgan test fanlar</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Mavjud test modullarini ko'ring yoki boshqaring.</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-extrabold text-blue-700">
                        {{ $subjects->total() }} ta modul
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2.5 text-left">Fan</th>
                            <th class="px-3 py-2.5 text-left">O'qituvchi</th>
                            <th class="px-3 py-2.5 text-left">Kurs / yo'nalish</th>
                            <th class="px-3 py-2.5 text-center">Guruh</th>
                            <th class="px-3 py-2.5 text-center">Dars</th>
                            <th class="px-3 py-2.5 text-left">Muddat</th>
                            <th class="px-3 py-2.5 text-right">Amal</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($subjects as $subject)
                            <tr class="group transition hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <div class="font-extrabold text-slate-900">{{ $subject->name }}</div>
                                    <div class="mt-1 text-[10px] text-slate-500">{{ $subject->faculty_name ?: 'Fakultet tanlanmagan' }}</div>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $subject->teacher_name ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $subject->level_name ?: '-' }}</div>
                                    <div class="mt-0.5 max-w-[220px] truncate text-[10px] text-slate-500">{{ $subject->specialty_name ?: '-' }}</div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-7 justify-center rounded-md bg-blue-50 px-2 py-1 font-extrabold text-blue-700">{{ $subject->groups->count() }}</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-7 justify-center rounded-md bg-emerald-50 px-2 py-1 font-extrabold text-emerald-700">{{ $subject->lessons->count() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-700">
                                    {{ optional($subject->starts_on)->format('d.m.Y') ?: '-' }}
                                    -
                                    {{ optional($subject->ends_on)->format('d.m.Y') ?: '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.test-subjects.show', $subject) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-bold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        Ko'rish
                                    </a>
                                        <form method="POST" action="{{ route('admin.test-subjects.destroy', $subject) }}"
                                              onsubmit="return confirm('Test fanni o\\'chirishni tasdiqlaysizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-[11px] font-bold text-red-600 transition hover:bg-red-100">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14m-9-3h4l1 3H9l1-3zm-3 3 1 13h8l1-13"/></svg>
                                                O'chirish
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
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
            </section>
        </div>
    </div>
</x-app-layout>
