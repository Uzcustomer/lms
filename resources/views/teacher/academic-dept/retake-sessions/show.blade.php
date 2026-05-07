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
                                        @else
                                            {{-- Superadmin uchun force delete: oyna + barcha arizalar cascade o'chiriladi --}}
                                            <form method="POST" action="{{ route('admin.retake-windows.destroy', $w->id) }}" class="inline"
                                                  onsubmit="return confirm('{{ __('DIQQAT! Bu oynaga') }} {{ $w->applications_count }} {{ __('ta ariza yuborilgan. Hammasi cascade o\'chiriladi. Aniq davom etamizmi?') }}')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="force" value="1">
                                                <button type="submit" class="text-xs text-red-700 hover:underline font-semibold" title="{{ __('Majburiy o\'chirish — barcha arizalar bilan birga') }}">
                                                    ⚠ {{ __("Majburiy o'chirish") }}
                                                </button>
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
                <div class="relative bg-white rounded-xl shadow-xl w-full z-10 overflow-y-auto"
                     style="max-width:1100px; height:1000px; max-height:92vh;">
                    {{-- LMS-style header (sticky, eng yuqori z-index) --}}
                    <div class="px-5 py-3 sticky top-0"
                         style="background:linear-gradient(135deg,#1a3268,#2b5ea7); z-index:100; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-bold text-white">{{ __("Yangi qabul oynasi") }}</h3>
                                <p class="text-[11px] text-white mt-0.5 opacity-90">{{ $session->name }} · {{ __("Har fakultet uchun alohida yo'nalish va kurs tanlanadi") }}</p>
                            </div>
                            <button type="button" @click="showCreate = false"
                                    class="text-white hover:opacity-80 text-2xl leading-none px-1">×</button>
                        </div>
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
                          class="p-5 space-y-4">
                        @csrf
                        <input type="hidden" name="session_id" value="{{ $session->id }}">

                        {{-- Sanalar (umumiy) — input ustiga bosganda calendar ochiladi --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> {{ __("Boshlanish") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" required
                                       onclick="try{this.showPicker()}catch(e){}"
                                       class="filter-input">
                            </div>
                            <div>
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> {{ __("Tugash") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date" required
                                       onclick="try{this.showPicker()}catch(e){}"
                                       class="filter-input">
                            </div>
                        </div>

                        {{-- Fakultetlar bloki: kartochkalar + qo'shish + semester (umumiy konteyner) --}}
                        <div class="rounded-xl border border-gray-200 bg-white p-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide flex items-center gap-1.5">
                                    <svg style="width:14px;height:14px;color:#2b5ea7;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l7-3 7 3z"/></svg>
                                    {{ __("Fakultetlar va yo'nalishlar") }}
                                </h4>
                                <span class="text-[10px] text-gray-500" x-text="cards.length + ' / ' + allDepartments.length + ' fakultet'"></span>
                            </div>

                            {{-- Kartochkalar grid (har qatorda 2 ta) --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <template x-for="card in cards" :key="card.fid">
                                    <div class="rounded-lg p-3" style="background:linear-gradient(135deg,#f0f4f8,#e8edf5);border:1px solid #dbe4ef;">
                                        <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
                                            <div class="flex items-center gap-2">
                                                <div style="width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">
                                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l7-3 7 3z"/></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-800" x-text="card.name"></span>
                                            </div>
                                            <button type="button" @click="removeCard(card.fid)"
                                                    class="text-[10px] text-red-600 hover:text-red-700 font-medium hover:underline">× {{ __("Olib tashlash") }}</button>
                                        </div>

                                        {{-- Yo'nalish --}}
                                        <div class="mb-2">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="text-[10px] font-bold text-gray-700 uppercase tracking-wide">{{ __("Yo'nalish") }} <span class="text-red-500">*</span></label>
                                                <button type="button" class="text-[10px] text-blue-600 hover:text-blue-800 font-medium hover:underline"
                                                        @click="toggleAllSpecialties(card)"
                                                        x-show="specialtiesFor(card.fid).length > 0"
                                                        x-text="card.specialtyPks.length === specialtiesFor(card.fid).length ? '{{ __("Tozalash") }}' : '{{ __("Hammasi") }}'"></button>
                                            </div>
                                            <div class="max-h-32 overflow-y-auto rounded-lg p-1.5 space-y-0.5 bg-white border border-gray-200">
                                                <p x-show="specialtiesFor(card.fid).length === 0" class="text-[10px] text-gray-400 px-1 py-1">— {{ __("Yo'nalishlar yo'q") }} —</p>
                                                <template x-for="sp in specialtiesFor(card.fid)" :key="sp.pk">
                                                    <label class="flex items-center gap-1.5 text-[11px] text-gray-700 hover:bg-blue-50 px-1.5 py-1 rounded cursor-pointer transition">
                                                        <input type="checkbox" :value="sp.pk" x-model="card.specialtyPks" class="rounded text-blue-600 focus:ring-blue-500">
                                                        <span x-text="sp.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Kurs (bitta tanlash — single select dropdown) --}}
                                        <div x-data="{ levelOpen: false }" @click.outside="levelOpen = false" class="relative mb-2">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="text-[10px] font-bold text-gray-700 uppercase tracking-wide">{{ __("Kurs") }} <span class="text-red-500">*</span></label>
                                                <button type="button"
                                                        x-show="card.levelCode"
                                                        @click="card.levelCode = ''"
                                                        class="text-[10px] text-red-600 hover:text-red-800 font-medium hover:underline">{{ __("Tozalash") }}</button>
                                            </div>
                                            <button type="button" @click="levelOpen = !levelOpen"
                                                    class="w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-lg flex items-center justify-between hover:border-blue-500 transition">
                                                <span x-show="!card.levelCode" class="text-gray-400">{{ __("Kurs tanlang...") }}</span>
                                                <span x-show="card.levelCode" class="text-gray-800 font-semibold truncate"
                                                      x-text="allLevels.find(l => l.code === card.levelCode)?.name || ''"></span>
                                                <svg class="w-3.5 h-3.5 text-gray-500 flex-shrink-0 ml-1" :class="levelOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            <div x-show="levelOpen" x-cloak
                                                 class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-56 overflow-y-auto"
                                                 style="z-index:60;">
                                                <template x-for="lv in allLevels" :key="lv.code">
                                                    <button type="button"
                                                            @click="card.levelCode = lv.code; levelOpen = false"
                                                            :class="card.levelCode === lv.code ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700'"
                                                            class="w-full text-left text-xs hover:bg-blue-50 px-3 py-2 border-b border-gray-100 last:border-b-0 transition">
                                                        <span x-text="lv.name"></span>
                                                        <span x-show="card.levelCode === lv.code" class="ml-2 text-blue-600">✓</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Semestr — faqat shu kartochka Xalqaro fakulteti bo'lsa --}}
                                        <div x-show="/xalqaro/i.test(card.name || '')" x-cloak
                                             x-data="{ semOpen: false }" @click.outside="semOpen = false"
                                             class="relative rounded-lg p-2 mt-2"
                                             style="background:#fffbeb;border:1px solid #fde68a;">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="text-[10px] font-bold text-amber-800 uppercase tracking-wide flex items-center gap-1">
                                                    <span class="fl-dot" style="background:#d97706;"></span>
                                                    {{ __("Semestr") }} <span class="text-red-500">*</span>
                                                </label>
                                                <button type="button" class="text-[10px] text-amber-700 hover:text-amber-900 font-medium hover:underline"
                                                        @click="toggleAllSemestersFor(card)"
                                                        x-text="(card.semesterCodes || []).length === allSemesters.length ? '{{ __("Tozalash") }}' : '{{ __("Hammasi") }}'"></button>
                                            </div>
                                            <button type="button" @click="semOpen = !semOpen"
                                                    class="w-full px-3 py-2 text-xs bg-white border border-amber-300 rounded-lg flex items-center justify-between hover:border-amber-500 transition">
                                                <span x-show="(card.semesterCodes || []).length === 0" class="text-amber-700/70">{{ __("Semestr tanlang...") }}</span>
                                                <span x-show="(card.semesterCodes || []).length > 0" class="text-amber-900 font-semibold truncate">
                                                    <span x-text="(card.semesterCodes || []).length"></span> {{ __("ta tanlangan") }}
                                                </span>
                                                <svg class="w-3.5 h-3.5 text-amber-700 flex-shrink-0 ml-1" :class="semOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            <div x-show="semOpen" x-cloak
                                                 class="absolute left-0 right-0 mt-1 bg-white border border-amber-300 rounded-lg shadow-xl max-h-64 overflow-y-auto"
                                                 style="z-index:60;">
                                                <template x-for="s in allSemesters" :key="s.code">
                                                    <label class="flex items-center gap-2 text-xs text-amber-900 hover:bg-amber-50 px-3 py-2 cursor-pointer border-b border-amber-100 last:border-b-0">
                                                        <input type="checkbox" :value="s.code"
                                                               :checked="(card.semesterCodes || []).includes(s.code)"
                                                               @change="toggleSemesterFor(card, s.code)"
                                                               class="rounded text-amber-600 focus:ring-amber-500">
                                                        <span x-text="s.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Fakultet qo'shish (bitta col oladi) --}}
                                <div class="relative" :class="cards.length % 2 === 0 ? 'md:col-span-2' : ''" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button" @click="open = !open"
                                            :disabled="availableDepartments.length === 0"
                                            :class="availableDepartments.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:border-blue-500 hover:bg-blue-50'"
                                            class="w-full px-3 py-3 text-xs font-semibold bg-white text-blue-700 border-2 border-dashed border-blue-300 rounded-lg flex items-center justify-center gap-2 transition">
                                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        <span x-text="cards.length === 0 ? '{{ __('Fakultet tanlash') }}' : '{{ __('Yana fakultet qo\'shish') }}'"></span>
                                    </button>
                                    <div x-show="open" x-cloak class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-72 overflow-y-auto" style="z-index:60;">
                                        <template x-for="d in availableDepartments" :key="d.id">
                                            <button type="button"
                                                    @click="addCard(d); open = false"
                                                    class="w-full text-left px-3 py-2.5 text-xs hover:bg-blue-50 border-b border-gray-100 last:border-b-0 transition">
                                                <span x-text="d.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- Hidden inputs (form submit) — har bir oyna uchun "fid|spec_pk|level_code" --}}
                        <template x-for="a in assignments" :key="'as-'+a">
                            <input type="hidden" name="assignments[]" :value="a">
                        </template>
                        {{-- Xalqaro fakultet kartochkalaridan semester_codes ni yig'amiz --}}
                        <template x-for="code in collectedSemesterCodes" :key="'sm-'+code">
                            <input type="hidden" name="semester_codes[]" :value="code">
                        </template>

                        {{-- Status / submit --}}
                        <div class="flex items-center justify-between gap-2 pt-3 border-t border-gray-100">
                            <p class="text-xs" :class="combinationCount > 0 ? 'text-blue-700 font-semibold' : 'text-gray-400'">
                                <span x-show="combinationCount > 0">ℹ️ <span x-text="combinationCount"></span> {{ __("ta oyna yaratiladi") }}</span>
                                <span x-show="combinationCount === 0">{{ __("Fakultet, yo'nalish va kursni tanlang") }}</span>
                            </p>
                            <div class="flex gap-2">
                                <button type="button" @click="showCreate = false"
                                        class="px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                                    {{ __("Bekor qilish") }}
                                </button>
                                <button type="submit"
                                        :disabled="combinationCount === 0"
                                        :class="combinationCount === 0 ? 'bg-gray-300 cursor-not-allowed' : 'hover:shadow-lg'"
                                        style="background:linear-gradient(135deg,#1a3268,#2b5ea7);"
                                        class="px-5 py-2 text-xs text-white rounded-lg font-bold transition shadow-sm">
                                    ✓ {{ __("Yaratish") }}
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

    @push('styles')
        <style>
            .filter-label { display:flex;align-items:center;gap:5px;margin-bottom:4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#475569; }
            .fl-dot { width:7px;height:7px;border-radius:50%;display:inline-block; }
            .filter-input { height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:13px;color:#1e293b;width:100%;transition:all .15s;cursor:pointer; }
            .filter-input:hover { border-color:#94a3b8; }
            .filter-input:focus { outline:none;border-color:#2b5ea7;box-shadow:0 0 0 3px rgba(43,94,167,0.15); }
            input[type="date"].filter-input::-webkit-calendar-picker-indicator { cursor:pointer;opacity:0.6; }
            input[type="date"].filter-input::-webkit-calendar-picker-indicator:hover { opacity:1; }
        </style>
    @endpush

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
                    cards: [], // [{fid, name, specialtyPks:[], levelCode:'', semesterCodes:[]}]

                    get availableDepartments() {
                        const taken = new Set(this.cards.map(c => String(c.fid)));
                        return this.allDepartments.filter(d => !taken.has(String(d.id)));
                    },

                    specialtiesFor(fid) {
                        return this.allSpecialties.filter(sp => String(sp.department_hemis_id) === String(fid));
                    },

                    isXalqaro(card) {
                        return /xalqaro/i.test(card?.name || '');
                    },

                    addCard(d) {
                        if (this.cards.some(c => String(c.fid) === String(d.id))) return;
                        this.cards.push({
                            fid: d.id,
                            name: d.name,
                            specialtyPks: [],
                            levelCode: '',           // bitta kurs
                            semesterCodes: [],       // faqat Xalqaro uchun ishlatiladi
                        });
                    },

                    removeCard(fid) {
                        this.cards = this.cards.filter(c => String(c.fid) !== String(fid));
                    },

                    get hasXalqaroSelected() {
                        return this.cards.some(c => this.isXalqaro(c));
                    },

                    // "fid|spec_pk|level_code" triplets ro'yxati (kurs endi bitta)
                    get assignments() {
                        const out = [];
                        for (const c of this.cards) {
                            if (!c.levelCode) continue;
                            for (const pk of c.specialtyPks) {
                                out.push(`${c.fid}|${pk}|${c.levelCode}`);
                            }
                        }
                        return out;
                    },

                    // Backend uchun semester_codes — barcha Xalqaro kartochkalardan yig'iladi (unique)
                    get collectedSemesterCodes() {
                        const set = new Set();
                        for (const c of this.cards) {
                            if (this.isXalqaro(c) && Array.isArray(c.semesterCodes)) {
                                for (const code of c.semesterCodes) set.add(code);
                            }
                        }
                        return [...set];
                    },

                    get combinationCount() {
                        if (this.assignments.length === 0) return 0;
                        let total = 0;
                        for (const c of this.cards) {
                            if (!c.levelCode || c.specialtyPks.length === 0) continue;
                            const isX = this.isXalqaro(c);
                            if (isX) {
                                if (!c.semesterCodes || c.semesterCodes.length === 0) return 0;
                                total += c.specialtyPks.length * c.semesterCodes.length;
                            } else {
                                total += c.specialtyPks.length;
                            }
                        }
                        return total;
                    },

                    toggleAllSpecialties(card) {
                        const all = this.specialtiesFor(card.fid).map(sp => sp.pk);
                        card.specialtyPks = card.specialtyPks.length === all.length ? [] : all;
                    },
                    toggleAllSemestersFor(card) {
                        if (!Array.isArray(card.semesterCodes)) card.semesterCodes = [];
                        card.semesterCodes = card.semesterCodes.length === this.allSemesters.length
                            ? [] : this.allSemesters.map(s => s.code);
                    },
                    toggleSemesterFor(card, code) {
                        if (!Array.isArray(card.semesterCodes)) card.semesterCodes = [];
                        const idx = card.semesterCodes.indexOf(code);
                        if (idx === -1) card.semesterCodes.push(code);
                        else card.semesterCodes.splice(idx, 1);
                    },

                    prepareSubmit(e) {
                        if (this.combinationCount === 0) {
                            e.preventDefault();
                            alert("{{ __("Iltimos, har fakultet uchun kamida bittadan yo'nalish va kurs tanlang. Xalqaro fakulteti uchun semestr ham majburiy.") }}");
                        }
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
