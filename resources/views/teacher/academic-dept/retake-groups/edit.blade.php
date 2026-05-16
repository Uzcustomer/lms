<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Guruhni tahrirlash") }}
            <a href="{{ route('admin.retake-groups.index') }}" class="text-sm text-blue-600 hover:underline ml-2">← {{ __('Orqaga') }}</a>
        </h2>
    </x-slot>

    @include('partials._retake_tom_select')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">{{ $group->name }}</h3>
                @php
                    $colors = [
                        'forming' => 'bg-gray-100 text-gray-700',
                        'scheduled' => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-green-100 text-green-800',
                        'completed' => 'bg-purple-100 text-purple-800',
                    ];
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $colors[$group->status] }}">
                    {{ $group->statusLabel() }}
                </span>
            </div>

            <p class="text-sm text-gray-700 mb-1">
                <span class="text-gray-500">{{ __("Fan") }}:</span> {{ $group->subject_name }}
                <span class="text-gray-400 mx-1">·</span>
                <span class="text-gray-500">{{ __("Semestr") }}:</span> {{ $group->semester_name }}
            </p>

            @php $editable = $group->isEditable() || $canOverride; @endphp

            <form method="POST" action="{{ route('admin.retake-groups.update', $group->id) }}" class="mt-4 space-y-3"
                  x-data="{
                      phones: @js(!empty($group->teacher_phones) ? $group->teacher_phones : ['']),
                      add() { if (this.phones.length < 5) this.phones.push(''); },
                      remove(i) { if (this.phones.length > 1) this.phones.splice(i, 1); }
                  }">
                @csrf @method('PUT')

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Guruh nomi") }}</label>
                    <input type="text" name="name" value="{{ $group->name }}"
                           {{ $editable ? '' : 'disabled' }}
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("O'qituvchi") }}</label>
                    <select name="teacher_id" {{ $editable ? '' : 'disabled' }}
                            class="tom-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ $group->teacher_id == $t->id ? 'selected' : '' }}>
                                {{ $t->full_name }}{{ $t->department ? ' — ' . $t->department : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __("O'qituvchi telefon raqami") }} <span class="text-red-500">*</span>
                        <span class="text-[10px] font-normal text-gray-500">({{ __("bir nechta qo'shish mumkin") }})</span>
                    </label>
                    <div class="space-y-1.5">
                        <template x-for="(phone, idx) in phones" :key="idx">
                            <div class="flex gap-1.5">
                                <input type="tel" name="teacher_phones[]" required
                                       x-model="phones[idx]"
                                       placeholder="+998 90 123 45 67"
                                       {{ $editable ? '' : 'disabled' }}
                                       class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                                <button type="button" @click="remove(idx)" x-show="phones.length > 1 && {{ $editable ? 'true' : 'false' }}"
                                        class="px-2.5 py-1 text-xs bg-red-50 text-red-700 rounded-lg hover:bg-red-100">✕</button>
                            </div>
                        </template>
                        @if($editable)
                            <button type="button" @click="add()" x-show="phones.length < 5"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg">
                                + {{ __("Yana raqam qo'shish") }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Boshlanish") }}</label>
                        <input type="date" name="start_date" value="{{ $group->start_date->format('Y-m-d') }}"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Tugash") }}</label>
                        <input type="date" name="end_date" value="{{ $group->end_date->format('Y-m-d') }}"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Maks. talabalar") }}</label>
                        <input type="number" name="max_students" value="{{ $group->max_students }}" min="1"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                </div>

                @if($editable)
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __("Saqlash") }}
                        </button>
                        @if($group->status === 'forming')
                            <button type="submit" formaction="{{ route('admin.retake-groups.publish', $group->id) }}"
                                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                                {{ __("Tasdiqlash (publish)") }}
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-gray-500">⚠️ {{ __("Tahrirlash imkoniyati mavjud emas") }}</p>
                @endif
            </form>

            @if($canOverride)
                <div class="mt-6 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-2">{{ __("Holatni o'zgartirish") }}</p>
                    <form method="POST" action="{{ route('admin.retake-groups.override-status', $group->id) }}" class="flex gap-2">
                        @csrf
                        <select name="status" class="px-3 py-1.5 text-xs border border-gray-300 rounded">
                            <option value="forming" {{ $group->status === 'forming' ? 'selected' : '' }}>forming</option>
                            <option value="scheduled" {{ $group->status === 'scheduled' ? 'selected' : '' }}>scheduled</option>
                            <option value="in_progress" {{ $group->status === 'in_progress' ? 'selected' : '' }}>in_progress</option>
                            <option value="completed" {{ $group->status === 'completed' ? 'selected' : '' }}>completed</option>
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs bg-orange-600 text-white rounded">{{ __("Override") }}</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Talabalar ro'yxati --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
             x-data="addStudentsModal({
                eligibleUrl: '{{ route('admin.retake-groups.eligible-applications', $group->id) }}',
                addUrl: '{{ route('admin.retake-groups.add-students', $group->id) }}',
                csrf: '{{ csrf_token() }}',
             })">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Talabalar") }} ({{ $group->applications->count() }})</h3>
                @if($group->status !== 'completed')
                    <button type="button" @click="openModal()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __("Talaba qo'shish") }}
                    </button>
                @endif
            </div>
            {{-- Talaba qo'shish modali --}}
            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="closeModal()">
                <div class="flex items-start justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeModal()"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-3xl w-full p-6 z-10 my-8">
                        <h3 class="text-base font-bold text-gray-900 mb-2">{{ __("Guruhga talaba qo'shish") }}</h3>
                        <p class="text-xs text-gray-500 mb-3">
                            {{ __("Hali guruhlanmagan tasdiqlangan arizalardan tanlang") }}
                            <span class="text-gray-400">·</span>
                            {{ __("Tanlangan") }}: <span class="font-bold text-blue-700" x-text="selected.length"></span>
                        </p>

                        <div class="mb-3">
                            <input type="text" x-model="search" placeholder="{{ __('F.I.Sh, HEMIS ID, fan yoki yo\'nalish bo\'yicha qidirish') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                        </div>

                        <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                            <template x-if="loading">
                                <div class="p-6 text-center text-sm text-gray-500">{{ __("Yuklanmoqda...") }}</div>
                            </template>
                            <template x-if="!loading && filteredApps.length === 0">
                                <div class="p-6 text-center text-sm text-gray-500">{{ __("Mos ariza topilmadi") }}</div>
                            </template>
                            <template x-for="app in filteredApps" :key="app.id">
                                <label class="flex items-start px-3 py-2 hover:bg-gray-50 border-b border-gray-100 cursor-pointer">
                                    <input type="checkbox" :value="app.id" x-model="selected" class="mt-0.5 rounded text-blue-600">
                                    <span class="ml-2 flex-1 text-xs">
                                        <span class="font-medium text-gray-900" x-text="app.student_name"></span>
                                        <span class="text-gray-500" x-text="' · ' + app.student_hemis_id"></span>
                                        <span class="ml-2 inline-block px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-medium"
                                              x-text="app.subject_name"></span>
                                        <span class="ml-1 inline-block px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[10px]"
                                              x-text="app.semester_name"></span>
                                        <span class="block text-[11px] text-gray-500 mt-0.5"
                                              x-text="(app.department_name || '') + ' · ' + (app.specialty_name || '') + ' · ' + (app.level_name || '') + ' · ' + (app.group_name || '')"></span>
                                    </span>
                                    <span class="text-xs text-gray-500 mr-2 whitespace-nowrap" x-text="app.credit + ' kr'"></span>
                                </label>
                            </template>
                        </div>

                        <form method="POST" :action="addUrl" class="flex gap-2 pt-4">
                            <input type="hidden" name="_token" :value="csrf">
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="application_ids[]" :value="id">
                            </template>
                            <button type="button" @click="closeModal()"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                {{ __("Bekor qilish") }}
                            </button>
                            <button type="submit"
                                    :disabled="selected.length === 0"
                                    :class="selected.length === 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                    class="flex-1 px-3 py-2 text-sm text-white rounded-lg">
                                {{ __("Guruhga qo'shish") }} (<span x-text="selected.length"></span>)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">F.I.Sh</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">HEMIS</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yo'nalish") }}</th>
                        <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Kredit") }}</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($group->applications as $app)
                        @php $student = $app->group->student ?? null; @endphp
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-900">{{ $student?->full_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ $app->student_hemis_id }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ $student?->specialty_name ?? '' }} · {{ $student?->level_name ?? $student?->level_code }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700 text-right">{{ number_format($app->credit, 1) }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $appColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $appColors[$app->final_status] }}">
                                    {{ $app->final_status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function addStudentsModal({ eligibleUrl, addUrl, csrf }) {
            return {
                eligibleUrl, addUrl, csrf,
                showModal: false,
                loading: false,
                apps: [],
                selected: [],
                search: '',
                async openModal() {
                    this.showModal = true;
                    this.selected = [];
                    this.search = '';
                    this.loading = true;
                    try {
                        const res = await fetch(this.eligibleUrl, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        this.apps = json.applications || [];
                    } catch (e) {
                        this.apps = [];
                    } finally {
                        this.loading = false;
                    }
                },
                closeModal() { this.showModal = false; },
                get filteredApps() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return this.apps;
                    return this.apps.filter(a =>
                        (a.student_name || '').toLowerCase().includes(q)
                        || String(a.student_hemis_id || '').includes(q)
                        || (a.subject_name || '').toLowerCase().includes(q)
                        || (a.specialty_name || '').toLowerCase().includes(q)
                        || (a.department_name || '').toLowerCase().includes(q)
                        || (a.group_name || '').toLowerCase().includes(q)
                    );
                },
            };
        }
    </script>
    @endpush
</x-teacher-app-layout>
