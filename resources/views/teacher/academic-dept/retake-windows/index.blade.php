<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Qabul oynalari") }}
        </h2>
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
                {{ __("Barcha sessiyalardagi oynalar — yangi oyna ochish uchun sessiyaga kiring") }}
            </p>
            <a href="{{ route('admin.retake-sessions.index') }}"
               class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                {{ __("Sessiyalarga o'tish") }}
            </a>
        </div>

        {{-- Cascading filtrlar (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-windows.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'hiddenFilters' => ['group', 'full_name', 'subject'],
            'extraQueryFields' => array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
            ]),
        ])

        {{-- Holat filtri (qo'shimcha) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4">
            <form method="GET" action="{{ route('admin.retake-windows.index') }}" class="flex items-end gap-2 flex-wrap">
                @foreach(['education_type','department','specialty','level_code','semester_code','per_page'] as $kept)
                    @if(request($kept))
                        <input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">
                    @endif
                @endforeach
                <div class="min-w-[180px]">
                    <label class="block text-xs text-gray-600 mb-1">{{ __("Holat") }}</label>
                    <select name="status" class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded">
                        <option value="all" {{ ($statusFilter ?? 'all') === 'all' ? 'selected' : '' }}>{{ __("Barchasi") }}</option>
                        <option value="upcoming" {{ ($statusFilter ?? '') === 'upcoming' ? 'selected' : '' }}>{{ __("Kelmoqda") }}</option>
                        <option value="active" {{ ($statusFilter ?? '') === 'active' ? 'selected' : '' }}>{{ __("Faol") }}</option>
                        <option value="closed" {{ ($statusFilter ?? '') === 'closed' ? 'selected' : '' }}>{{ __("Yopilgan") }}</option>
                    </select>
                </div>
                <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">{{ __("Qo'llash") }}</button>
            </form>
        </div>

        {{-- Mavjud oynalar jadvali --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($windows->count() === 0)
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Hali oyna yaratilmagan") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fakultet") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yo'nalish") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Kurs") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Semestr") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Sanalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Arizalar") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase"></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($windows as $w)
                            <tr>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $specialtyToFaculty[$w->specialty_id] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-900">{{ $w->specialty_name ?? $w->specialty_id }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $w->level_name ?? $w->level_code }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $w->semester_name }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    {{ $w->start_date->format('Y-m-d') }} → {{ $w->end_date->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @php
                                        $statusColors = [
                                            'upcoming' => 'bg-yellow-100 text-yellow-800',
                                            'active' => 'bg-green-100 text-green-800',
                                            'closed' => 'bg-gray-100 text-gray-700',
                                        ];
                                        $statusLabels = [
                                            'upcoming' => __("Kelmoqda"),
                                            'active' => __("Faol"),
                                            'closed' => __("Yopilgan"),
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
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showCreate = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showCreate = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 z-10">
                    <h3 class="text-base font-bold text-gray-900 mb-4">{{ __("Yangi qabul oynasi") }}</h3>
                    <form method="POST"
                          action="{{ route('admin.retake-windows.store') }}"
                          x-data="windowForm({
                              specialties: @js($specialties->map(fn($s) => ['id' => (string)$s->specialty_hemis_id, 'name' => $s->name, 'department_hemis_id' => (string)($s->department_hemis_id ?? '')])->values()->all()),
                          })"
                          class="space-y-3">
                        @csrf

                        {{-- Fakultet --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Fakultet") }} <span class="text-red-500">*</span></label>
                            <select x-model="departmentId"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                <option value="">— {{ __("Tanlang") }} —</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->department_hemis_id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Yo'nalish (fakultet bo'yicha filtrlanadi) --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Yo'nalish") }} <span class="text-red-500">*</span></label>
                            <select name="specialty_id"
                                    x-model="specialtyId"
                                    @change="onSpecialtyChange($event)"
                                    required
                                    :disabled="!departmentId"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                                <option value="">— {{ __("Avval fakultetni tanlang") }} —</option>
                                <template x-for="sp in filteredSpecialties" :key="sp.id">
                                    <option :value="sp.id" :data-name="sp.name" x-text="sp.name"></option>
                                </template>
                            </select>
                            <input type="hidden" name="specialty_name" :value="specialtyName">
                        </div>

                        {{-- Kurs --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Kurs") }} <span class="text-red-500">*</span></label>
                            <select name="level_code"
                                    x-model="levelCode"
                                    @change="levelName = $event.target.options[$event.target.selectedIndex].dataset.name || ''"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                <option value="">— {{ __("Tanlang") }} —</option>
                                @foreach($levels as $lv)
                                    <option value="{{ $lv['code'] }}" data-name="{{ $lv['name'] }}">{{ $lv['name'] }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="level_name" :value="levelName">
                        </div>

                        {{-- Semestr (12 ta unikal) --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Semestr") }} <span class="text-red-500">*</span></label>
                            <select name="semester_code"
                                    x-model="semesterCode"
                                    @change="semesterName = $event.target.options[$event.target.selectedIndex].dataset.name || ''"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                <option value="">— {{ __("Tanlang") }} —</option>
                                @foreach($semesters as $s)
                                    <option value="{{ $s['code'] }}" data-name="{{ $s['name'] }}">{{ $s['name'] }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="semester_name" :value="semesterName">
                        </div>

                        {{-- Sanalar --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Boshlanish") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Tugash") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>
                        </div>

                        <p class="text-[11px] text-gray-500">
                            ⚠️ {{ __("Yaratilgandan keyin sanalarni faqat super-admin o'zgartira oladi") }}
                        </p>

                        <div class="flex gap-2 pt-3">
                            <button type="button" @click="showCreate = false"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                {{ __("Bekor qilish") }}
                            </button>
                            <button type="submit"
                                    class="flex-1 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                {{ __("Yaratish va ochish") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
            function windowForm({ specialties }) {
                return {
                    allSpecialties: specialties || [],
                    departmentId: '',
                    specialtyId: '',
                    specialtyName: '',
                    levelCode: '',
                    levelName: '',
                    semesterCode: '',
                    semesterName: '',

                    get filteredSpecialties() {
                        if (!this.departmentId) return [];
                        return this.allSpecialties.filter(sp =>
                            String(sp.department_hemis_id) === String(this.departmentId)
                        );
                    },

                    onSpecialtyChange(e) {
                        this.specialtyName = e.target.options[e.target.selectedIndex]?.dataset.name || '';
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
