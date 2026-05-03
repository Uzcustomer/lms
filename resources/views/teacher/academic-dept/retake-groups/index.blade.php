<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish guruhlari") }}
        </h2>
    </x-slot>

    @include('partials._retake_tom_select')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="groupFormation({
             lookupUrl: '{{ route('admin.retake-groups.lookup') }}',
             storeUrl: '{{ route('admin.retake-groups.store') }}',
             rejectUrlBase: '{{ url('/admin/retake-groups/applications') }}',
             csrf: '{{ csrf_token() }}',
             minReasonLength: {{ \App\Models\RetakeSetting::rejectReasonMinLength() }},
         })">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Tasdiqlanishi kutilayotgan to'plamlar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ __("Guruh shakllantirish kutilmoqda") }}
                    <span class="text-xs font-normal text-gray-500">
                        ({{ __("dekan + registrator tasdiqlagan, fan + semestr bo'yicha to'plangan") }})
                    </span>
                </h3>
            </div>

            @if($aggregations->count() === 0)
                <div class="p-8 text-center text-gray-500 text-sm">
                    {{ __("Hozircha tasdiqlanishi kutilayotgan ariza yo'q") }}
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($aggregations as $agg)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $agg['subject_name'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $agg['semester_name'] }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-700">
                                    <span class="font-bold text-blue-700">{{ $agg['count'] }}</span>
                                    {{ __("ta talaba") }}
                                </span>
                                <button type="button"
                                        @click="openFormation({{ json_encode([
                                            'subject_id' => $agg['subject_id'],
                                            'subject_name' => $agg['subject_name'],
                                            'semester_id' => $agg['semester_id'],
                                            'semester_name' => $agg['semester_name'],
                                        ]) }})"
                                        class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    {{ __("Guruh shakllantirish") }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Cascading filtrlar (talaba ma'lumotlari + Fan bo'yicha) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-groups.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'extraQueryFields' => array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
                'search' => $search ?: null,
            ]),
        ])

        {{-- Mavjud guruhlar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Mavjud guruhlar") }}</h3>
                <form method="GET" action="{{ route('admin.retake-groups.index') }}" class="flex gap-2 items-center flex-wrap">
                    @foreach(['education_type','department','specialty','level_code','semester_code','group','subject','per_page'] as $kept)
                        @if(request($kept))
                            <input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">
                        @endif
                    @endforeach
                    <input type="text" name="search" value="{{ $search ?? '' }}"
                           placeholder="{{ __('Nom, fan yoki o\'qituvchi') }}"
                           class="px-3 py-1.5 text-xs border border-gray-300 rounded w-56">
                    <select name="status" class="px-3 py-1.5 text-xs border border-gray-300 rounded">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>{{ __("Barchasi") }}</option>
                        <option value="forming" {{ $statusFilter === 'forming' ? 'selected' : '' }}>{{ __("Shakllantirilmoqda") }}</option>
                        <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>{{ __("Tasdiqlangan") }}</option>
                        <option value="in_progress" {{ $statusFilter === 'in_progress' ? 'selected' : '' }}>{{ __("Borayotgan") }}</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>{{ __("Tugagan") }}</option>
                    </select>
                    <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">{{ __("Filtrlash") }}</button>
                    @if(($search ?? '') !== '' || $statusFilter !== 'all')
                        <a href="{{ route('admin.retake-groups.index') }}" class="text-xs text-gray-500 hover:underline">{{ __("Tozalash") }}</a>
                    @endif
                </form>
            </div>

            @if($groups->count() === 0)
                <div class="p-8 text-center text-gray-500 text-sm">{{ __("Guruh topilmadi") }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Nom") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Sanalar") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($groups as $g)
                            <tr>
                                <td class="px-3 py-2.5 text-sm text-gray-900">{{ $g->name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ $g->subject_name }}
                                    <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    {{ $g->start_date->format('Y-m-d') }} → {{ $g->end_date->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $g->students_count }}</td>
                                <td class="px-3 py-2.5">
                                    @php
                                        $colors = [
                                            'forming' => 'bg-gray-100 text-gray-700',
                                            'scheduled' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-purple-100 text-purple-800',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $colors[$g->status] }}">
                                        {{ $g->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    @if($g->status === 'forming')
                                        <form method="POST" action="{{ route('admin.retake-groups.publish', $g->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-green-600 hover:underline mr-2">{{ __("Tasdiqlash") }}</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.retake-groups.edit', $g->id) }}"
                                       class="text-xs text-blue-600 hover:underline">{{ __("Tahrirlash") }}</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-gray-100">{{ $groups->links() }}</div>
            @endif
        </div>

        {{-- Guruh shakllantirish modal --}}
        <div x-show="showFormation" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="closeFormation()">
            <div class="flex items-start justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeFormation()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-3xl w-full p-6 z-10 my-8">
                    <h3 class="text-base font-bold text-gray-900 mb-1">{{ __("Guruh shakllantirish") }}</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        <span x-text="formData.subject_name"></span> · <span x-text="formData.semester_name"></span>
                    </p>

                    <form method="POST" action="{{ route('admin.retake-groups.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="subject_id" :value="formData.subject_id">
                        <input type="hidden" name="subject_name" :value="formData.subject_name">
                        <input type="hidden" name="semester_id" :value="formData.semester_id">
                        <input type="hidden" name="semester_name" :value="formData.semester_name">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Guruh nomi") }} <span class="text-red-500">*</span></label>
                                <input type="text" name="name" x-model="formData.name" required
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("O'qituvchi") }} <span class="text-red-500">*</span></label>
                                <select name="teacher_id" x-model="formData.teacher_id" required
                                        x-ref="teacherSelect"
                                        class="tom-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                    <option value="">— {{ __("Tanlang") }} —</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Maks. talabalar (ixtiyoriy)") }}</label>
                                <input type="number" name="max_students" min="1"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Boshlanish") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Tugash") }} <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>
                        </div>

                        {{-- Talabalar checkbox ro'yxati --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-medium text-gray-700">{{ __("Talabalar") }} <span class="text-red-500">*</span></label>
                                <span class="text-xs text-gray-500">
                                    {{ __("Tanlangan") }}: <span class="font-bold text-blue-700" x-text="selectedCount"></span> / <span x-text="applications.length"></span>
                                </span>
                            </div>

                            <div class="border border-gray-200 rounded-lg max-h-72 overflow-y-auto">
                                <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                                    <input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" class="rounded">
                                    <span class="text-xs font-medium text-gray-700">{{ __("Barchasini tanlash") }}</span>
                                </div>
                                <template x-for="app in applications" :key="app.id">
                                    <div class="flex items-start px-3 py-2 hover:bg-gray-50 border-b border-gray-100">
                                        <label class="flex items-start flex-1 cursor-pointer">
                                            <input type="checkbox" name="application_ids[]" :value="app.id" x-model="selected" class="mt-0.5 rounded">
                                            <span class="ml-2 flex-1 text-xs">
                                                <span class="font-medium text-gray-900" x-text="app.student_name"></span>
                                                <span class="text-gray-500" x-text="' · ' + app.student_hemis_id"></span>
                                                <span class="block text-[11px] text-gray-500"
                                                      x-text="(app.department_name || '') + ' · ' + (app.specialty_name || '') + ' · ' + (app.level_name || '') + ' · ' + (app.group_name || '')"></span>
                                            </span>
                                            <span class="text-xs text-gray-500 mr-2" x-text="app.credit + ' kr'"></span>
                                        </label>
                                        <button type="button" @click="rejectApp(app)"
                                                class="ml-2 text-[11px] text-red-600 hover:underline whitespace-nowrap">
                                            {{ __("Rad etish") }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="closeFormation()"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __("Bekor qilish") }}</button>
                            <button type="submit" name="action" value="save"
                                    class="flex-1 px-3 py-2 text-sm bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                                {{ __("Saqlash (draft)") }}
                            </button>
                            <button type="submit" name="action" value="publish"
                                    class="flex-1 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                {{ __("Tasdiqlash") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function groupFormation({ lookupUrl, storeUrl, csrf, rejectUrlBase, minReasonLength }) {
                return {
                    lookupUrl, storeUrl, csrf, rejectUrlBase, minReasonLength,
                    showFormation: false,
                    formData: { subject_id: '', subject_name: '', semester_id: '', semester_name: '', name: '', teacher_id: '' },
                    applications: [],
                    teachers: [],
                    selected: [],

                    get selectedCount() { return this.selected.length; },
                    get allSelected() { return this.applications.length > 0 && this.selected.length === this.applications.length; },

                    async openFormation(data) {
                        this.formData = {
                            subject_id: data.subject_id,
                            subject_name: data.subject_name,
                            semester_id: data.semester_id,
                            semester_name: data.semester_name,
                            name: data.subject_name + ' — qayta o\'qish',
                            teacher_id: '',
                        };
                        this.applications = [];
                        this.teachers = [];
                        this.selected = [];
                        this.showFormation = true;

                        const url = `${this.lookupUrl}?subject_id=${encodeURIComponent(data.subject_id)}&semester_id=${encodeURIComponent(data.semester_id)}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        this.applications = json.applications || [];
                        this.teachers = json.teachers || [];
                        this.selected = this.applications.map(a => a.id);

                        this.$nextTick(() => this.syncTeacherOptions());
                    },

                    syncTeacherOptions() {
                        const sel = this.$refs.teacherSelect;
                        if (!sel) return;
                        // TomSelect endi init bo'lmagan bo'lsa, init qilamiz
                        if (!sel.tomselect && window.initTomSelects) {
                            window.initTomSelects(sel.parentElement);
                        }
                        const ts = sel.tomselect;
                        if (!ts) return;
                        ts.clear();
                        ts.clearOptions();
                        ts.addOption({ value: '', text: '— {{ __("Tanlang") }} —' });
                        this.teachers.forEach(t => {
                            ts.addOption({
                                value: String(t.id),
                                text: t.full_name + (t.department ? ' — ' + t.department : ''),
                            });
                        });
                        ts.refreshOptions(false);
                    },

                    closeFormation() { this.showFormation = false; },

                    toggleAll(checked) {
                        this.selected = checked ? this.applications.map(a => a.id) : [];
                    },

                    rejectApp(app) {
                        const promptMsg = "{{ __('Rad etish sababi (kamida') }} " + this.minReasonLength + " {{ __('belgi):') }}\n\n" + app.student_name;
                        const reason = window.prompt(promptMsg, '');
                        if (reason === null) return;
                        const trimmed = reason.trim();
                        if (trimmed.length < this.minReasonLength) {
                            alert("{{ __('Sabab juda qisqa. Kamida') }} " + this.minReasonLength + " {{ __('belgi bo\'lishi kerak.') }}");
                            return;
                        }

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `${this.rejectUrlBase}/${app.id}/reject`;

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = this.csrf;
                        form.appendChild(csrfInput);

                        const reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'reason';
                        reasonInput.value = trimmed;
                        form.appendChild(reasonInput);

                        document.body.appendChild(form);
                        form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
