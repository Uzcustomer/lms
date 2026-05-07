<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-sessions.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Sessiyalar") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $session->name }}
            </h2>
            @if($session->is_closed)
                <span class="px-2 py-0.5 text-[11px] font-medium bg-gray-200 text-gray-700 rounded-full">{{ __("Yopilgan") }}</span>
            @else
                <span class="px-2 py-0.5 text-[11px] font-medium bg-green-100 text-green-800 rounded-full">{{ __("Ochiq") }}</span>
            @endif
        </div>
    </x-slot>

    @include('partials._retake_tom_select')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{ showCreate: false, overrideId: null, overrideStart: '', overrideEnd: '' }">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            <p class="text-sm text-gray-500">
                {{ __("Sessiya ichida har yo'nalish + kurs + semestr uchun alohida oyna ochiladi") }}
            </p>
            @if(!$session->is_closed)
                <button type="button"
                        @click="showCreate = true"
                        class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    + {{ __("Yangi oyna ochish") }}
                </button>
            @endif
        </div>

        {{-- Cascading filtrlar (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-sessions.show', $session->id),
            'educationTypes' => $educationTypes ?? collect(),
            'hiddenFilters' => ['group', 'full_name', 'subject'],
            'extraQueryFields' => array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
            ]),
        ])

        {{-- Holat filtri (qo'shimcha) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4">
            <form method="GET" action="{{ route('admin.retake-sessions.show', $session->id) }}" class="flex items-end gap-2 flex-wrap">
                @foreach(['education_type','department','specialty','level_code','semester_code','per_page'] as $kept)
                    @if(request($kept))
                        <input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">
                    @endif
                @endforeach
                <div class="min-w-[180px]">
                    <label class="block text-xs text-gray-600 mb-1">{{ __("Holat") }}</label>
                    <select name="status" class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded">
                        <option value="all" {{ ($statusFilter ?? 'all') === 'all' ? 'selected' : '' }}>{{ __("Barchasi") }}</option>
                        <option value="active" {{ ($statusFilter ?? '') === 'active' ? 'selected' : '' }}>{{ __("Ariza qabul ochiq") }}</option>
                        <option value="study" {{ ($statusFilter ?? '') === 'study' ? 'selected' : '' }}>{{ __("O'qish davri") }}</option>
                        <option value="closed" {{ ($statusFilter ?? '') === 'closed' ? 'selected' : '' }}>{{ __("Tugagan") }}</option>
                    </select>
                </div>
                <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">{{ __("Qo'llash") }}</button>
            </form>
        </div>

        {{-- Mavjud oynalar jadvali --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($windows->count() === 0)
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Bu sessiyada hali oyna yo'q. Yuqoridagi tugma orqali oching.") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:48px;">{{ __("T/R") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fakultet") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yo'nalish") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Kurs") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Semestr") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Sanalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Ochilgan vaqti") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Arizalar") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase"></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($windows as $i => $w)
                            <tr>
                                <td class="px-3 py-2.5 text-center text-sm font-bold text-blue-700">
                                    {{ ($windows->currentPage() - 1) * $windows->perPage() + $i + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-sm font-semibold text-gray-900">
                                    @php $rowFaculty = $rowFaculties[$w->id] ?? '—'; @endphp
                                    {{ $rowFaculty }}
                                    @php
                                        $siblings = $w->creation_batch_id ? ($batchFaculties[$w->creation_batch_id] ?? []) : [];
                                        $others = collect($siblings)->reject(fn ($f) => $f === $rowFaculty)->values();
                                    @endphp
                                    @if(count($others) > 0)
                                        <div class="text-[10px] text-gray-500 font-normal mt-0.5"
                                             title="{{ __('Bu oyna birgalikda yaratilgan fakultetlar') }}">
                                            ⛓ {{ __("birgalikda") }}: {{ implode(', ', $others->all()) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $w->specialty_name ?? $w->specialty_id }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $w->level_name ?? $w->level_code }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $w->semester_name }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    {{ $w->start_date->format('Y-m-d') }} → {{ $w->end_date->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-500" title="{{ $w->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $w->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-800',
                                            'study' => 'bg-blue-100 text-blue-800',
                                            'closed' => 'bg-gray-100 text-gray-700',
                                        ];
                                        $statusLabels = [
                                            'active' => __("Ariza qabul ochiq"),
                                            'study' => __("O'qish davri"),
                                            'closed' => __("Tugagan"),
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusColors[$w->status] }}">
                                        {{ $statusLabels[$w->status] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $w->applications_count }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.retake-windows.show', $w->id) }}"
                                       class="text-xs text-blue-600 hover:underline mr-2">{{ __("Ko'rish") }}</a>
                                    @if($canOverride)
                                        <button type="button"
                                                @click="overrideId = {{ $w->id }}; overrideStart = '{{ $w->start_date->format('Y-m-d') }}'; overrideEnd = '{{ $w->end_date->format('Y-m-d') }}'"
                                                class="text-xs text-blue-600 hover:underline mr-2">
                                            {{ __("Override") }}
                                        </button>
                                        @if($w->applications_count === 0)
                                            <form method="POST" action="{{ route('admin.retake-windows.destroy', $w->id) }}" class="inline" onsubmit="return confirm('{{ __('Aniqmi?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:underline">{{ __("O'chirish") }}</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-100">
                    {{ $windows->links() }}
                </div>
            @endif
        </div>

        {{-- Yangi oyna yaratish modal --}}
        @if(!$session->is_closed)
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showCreate = false">
            <div class="flex items-center justify-center min-h-screen p-3">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showCreate = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full p-5 z-10 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">{{ __("Yangi qabul oynasi") }}</h3>
                            <p class="text-[11px] text-gray-500">{{ $session->name }} · {{ __("Har fakultet uchun alohida yo'nalish va kurs tanlanadi") }}</p>
                        </div>
                        <button type="button" @click="showCreate = false"
                                class="text-gray-400 hover:text-gray-700 text-lg leading-none px-1">×</button>
                    </div>

                    <form method="POST"
                          action="{{ route('admin.retake-windows.store') }}"
                          x-data="windowForm({
                              departments: @js($departments->map(fn($d) => ['id' => (string)$d->department_hemis_id, 'name' => $d->name])->values()->all()),
                              specialties: @js($specialties->map(fn($s) => ['pk' => $s->id, 'id' => (string)$s->specialty_hemis_id, 'name' => $s->name, 'department_hemis_id' => (string)($s->department_hemis_id ?? '')])->values()->all()),
                              levels: @js(collect($levels)->map(fn($lv) => ['code' => $lv['code'], 'name' => $lv['name']])->all()),
                              semesters: @js(collect($semesters)->map(fn($s) => ['code' => $s['code'], 'name' => $s['name']])->all()),
                          })"
                          @submit="prepareSubmit($event)"
                          class="space-y-3">
                        @csrf
                        <input type="hidden" name="session_id" value="{{ $session->id }}">

                        {{-- Sanalar (umumiy) --}}
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __("Boshlanish") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" required class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __("Tugash") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date" required class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                            </div>
                        </div>

                        {{-- Fakultet kartochkalari --}}
                        <div class="space-y-2">
                            <template x-for="card in cards" :key="card.fid">
                                <div class="border border-gray-200 rounded-lg p-2.5 bg-gray-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold text-gray-800" x-text="card.name"></span>
                                        <button type="button" @click="removeCard(card.fid)"
                                                class="text-[10px] text-red-600 hover:underline">{{ __("Olib tashlash") }}</button>
                                    </div>

                                    {{-- Yo'nalish va Kurs yonma-yon --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        {{-- Yo'nalish --}}
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="text-[10px] font-medium text-gray-600">{{ __("Yo'nalish") }} <span class="text-red-500">*</span></label>
                                                <button type="button" class="text-[10px] text-blue-600 hover:underline"
                                                        @click="toggleAllSpecialties(card)"
                                                        x-show="specialtiesFor(card.fid).length > 0"
                                                        x-text="card.specialtyPks.length === specialtiesFor(card.fid).length ? '{{ __("Tozalash") }}' : '{{ __("Hammasi") }}'"></button>
                                            </div>
                                            <div class="max-h-28 overflow-y-auto border border-gray-300 rounded p-1.5 space-y-0.5 bg-white">
                                                <p x-show="specialtiesFor(card.fid).length === 0" class="text-[10px] text-gray-400 px-1 py-1">— {{ __("Yo'nalishlar yo'q") }} —</p>
                                                <template x-for="sp in specialtiesFor(card.fid)" :key="sp.pk">
                                                    <label class="flex items-center gap-1.5 text-[11px] text-gray-700 hover:bg-gray-50 px-1 py-0.5 rounded cursor-pointer">
                                                        <input type="checkbox" :value="sp.pk" x-model="card.specialtyPks" class="rounded">
                                                        <span x-text="sp.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Kurs --}}
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="text-[10px] font-medium text-gray-600">{{ __("Kurs") }} <span class="text-red-500">*</span></label>
                                                <button type="button" class="text-[10px] text-blue-600 hover:underline"
                                                        @click="toggleAllLevels(card)"
                                                        x-text="card.levelCodes.length === allLevels.length ? '{{ __("Tozalash") }}' : '{{ __("Hammasi") }}'"></button>
                                            </div>
                                            <div class="grid grid-cols-3 gap-1">
                                                <template x-for="lv in allLevels" :key="lv.code">
                                                    <label class="flex items-center justify-center gap-1 text-[11px] text-gray-700 border border-gray-300 hover:bg-white px-1.5 py-1 rounded cursor-pointer bg-white"
                                                           :class="card.levelCodes.includes(lv.code) ? 'ring-2 ring-blue-500 border-blue-500 bg-blue-50' : ''">
                                                        <input type="checkbox" class="sr-only" :value="lv.code" x-model="card.levelCodes">
                                                        <span x-text="lv.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Fakultet qo'shish --}}
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button" @click="open = !open"
                                        :disabled="availableDepartments.length === 0"
                                        :class="availableDepartments.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-50'"
                                        class="w-full px-3 py-2 text-xs bg-white text-blue-700 border-2 border-dashed border-blue-300 rounded-lg flex items-center justify-center gap-1">
                                    <span>+</span>
                                    <span x-text="cards.length === 0 ? '{{ __('Fakultet tanlash') }}' : '{{ __('Yana fakultet qo\'shish') }}'"></span>
                                </button>
                                <div x-show="open" x-cloak class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <template x-for="d in availableDepartments" :key="d.id">
                                        <button type="button"
                                                @click="addCard(d); open = false"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-blue-50 border-b border-gray-100 last:border-b-0">
                                            <span x-text="d.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Semestr — faqat Xalqaro talim fakulteti tanlanganda (umumiy) --}}
                        <div x-show="hasXalqaroSelected" x-cloak class="border border-amber-200 bg-amber-50 rounded-lg p-2.5">
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-[11px] font-medium text-amber-800">{{ __("Semestr (Xalqaro fakulteti uchun)") }} <span class="text-red-500">*</span></label>
                                <button type="button" class="text-[10px] text-amber-700 hover:underline"
                                        @click="toggleAllSemesters()"
                                        x-text="semesterCodes.length === allSemesters.length ? '{{ __("Tozalash") }}' : '{{ __("Hammasi") }}'"></button>
                            </div>
                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-1">
                                <template x-for="s in allSemesters" :key="s.code">
                                    <label class="flex items-center justify-center text-[11px] text-amber-900 border border-amber-300 hover:bg-white px-1 py-1 rounded cursor-pointer bg-white"
                                           :class="semesterCodes.includes(s.code) ? 'ring-2 ring-amber-500 border-amber-500 bg-amber-100' : ''">
                                        <input type="checkbox" class="sr-only" :value="s.code" x-model="semesterCodes">
                                        <span x-text="s.name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Hidden inputs (form submit) — har bir oyna uchun "fid|spec_pk|level_code" --}}
                        <template x-for="a in assignments" :key="'as-'+a">
                            <input type="hidden" name="assignments[]" :value="a">
                        </template>
                        <template x-for="code in semesterCodes" :key="'sm-'+code">
                            <input type="hidden" name="semester_codes[]" :value="code">
                        </template>

                        {{-- Status / submit --}}
                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-gray-100">
                            <p class="text-[11px]" :class="combinationCount > 0 ? 'text-blue-700' : 'text-gray-400'">
                                <span x-show="combinationCount > 0">ℹ️ <span x-text="combinationCount"></span> {{ __("ta oyna yaratiladi") }}</span>
                                <span x-show="combinationCount === 0">{{ __("Fakultet, yo'nalish va kursni tanlang") }}</span>
                            </p>
                            <div class="flex gap-2">
                                <button type="button" @click="showCreate = false"
                                        class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    {{ __("Bekor qilish") }}
                                </button>
                                <button type="submit"
                                        :disabled="combinationCount === 0"
                                        :class="combinationCount === 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                        class="px-4 py-1.5 text-xs text-white rounded-lg font-medium">
                                    {{ __("Yaratish") }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Override modal --}}
        @if($canOverride)
            <div x-show="overrideId !== null" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="overrideId = null">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-black bg-opacity-50" @click="overrideId = null"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
                        <h3 class="text-base font-bold text-gray-900 mb-1">{{ __("Sanalarni override qilish") }}</h3>
                        <p class="text-xs text-red-600 mb-4">⚠️ {{ __("Faqat istisno holatlarda ishlating") }}</p>
                        <form :action="`{{ url('/admin/retake-windows') }}/${overrideId}/override-dates`" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Boshlanish") }}</label>
                                <input type="date" name="start_date" x-model="overrideStart" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Tugash") }}</label>
                                <input type="date" name="end_date" x-model="overrideEnd" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="overrideId = null"
                                        class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __("Bekor qilish") }}</button>
                                <button type="submit" class="flex-1 px-3 py-2 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700">{{ __("Override") }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            // Per-fakultet card-based form: har bir fakultet kartochkasi
            // o'zining yo'nalish va kurs tanlovini saqlaydi. Sana va semestr
            // (xalqaro uchun) — umumiy.
            function windowForm({ departments, specialties, levels, semesters }) {
                return {
                    allDepartments: departments || [],
                    allSpecialties: specialties || [],
                    allLevels: levels || [],
                    allSemesters: semesters || [],
                    cards: [], // [{fid, name, specialtyPks: [], levelCodes: []}]
                    semesterCodes: [],

                    get availableDepartments() {
                        const taken = new Set(this.cards.map(c => String(c.fid)));
                        return this.allDepartments.filter(d => !taken.has(String(d.id)));
                    },

                    specialtiesFor(fid) {
                        return this.allSpecialties.filter(sp => String(sp.department_hemis_id) === String(fid));
                    },

                    addCard(d) {
                        if (this.cards.some(c => String(c.fid) === String(d.id))) return;
                        this.cards.push({
                            fid: d.id,
                            name: d.name,
                            specialtyPks: [],
                            levelCodes: [],
                        });
                    },

                    removeCard(fid) {
                        this.cards = this.cards.filter(c => String(c.fid) !== String(fid));
                    },

                    get hasXalqaroSelected() {
                        return this.cards.some(c => /xalqaro/i.test(c.name || ''));
                    },

                    // Hidden input uchun "fid|spec_pk|level_code" triplets ro'yxati
                    get assignments() {
                        const out = [];
                        for (const c of this.cards) {
                            for (const pk of c.specialtyPks) {
                                for (const lv of c.levelCodes) {
                                    out.push(`${c.fid}|${pk}|${lv}`);
                                }
                            }
                        }
                        return out;
                    },

                    get combinationCount() {
                        if (this.assignments.length === 0) return 0;
                        if (this.hasXalqaroSelected && this.semesterCodes.length === 0) return 0;
                        // Xalqaro fakultetdagi assignment'lar har semestr uchun alohida oyna
                        let total = 0;
                        for (const c of this.cards) {
                            const isX = /xalqaro/i.test(c.name || '');
                            const sm = isX ? Math.max(this.semesterCodes.length, 0) : 1;
                            total += c.specialtyPks.length * c.levelCodes.length * sm;
                        }
                        return total;
                    },

                    toggleAllSpecialties(card) {
                        const all = this.specialtiesFor(card.fid).map(sp => sp.pk);
                        card.specialtyPks = card.specialtyPks.length === all.length ? [] : all;
                    },
                    toggleAllLevels(card) {
                        card.levelCodes = card.levelCodes.length === this.allLevels.length
                            ? [] : this.allLevels.map(lv => lv.code);
                    },
                    toggleAllSemesters() {
                        this.semesterCodes = this.semesterCodes.length === this.allSemesters.length
                            ? [] : this.allSemesters.map(s => s.code);
                    },

                    prepareSubmit(e) {
                        if (this.assignments.length === 0 ||
                            (this.hasXalqaroSelected && this.semesterCodes.length === 0)) {
                            e.preventDefault();
                            alert("{{ __("Iltimos, har fakultet uchun kamida bittadan yo'nalish va kurs tanlang") }}");
                        }
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
