<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Arizalar") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{
            selected: [],
            rejectFor: null,
            rejectReason: '',
            bulkRejectOpen: false,
            bulkRejectReason: '',
            toggleAll(ids) {
                if (this.selected.length === ids.length && ids.every(id => this.selected.includes(id))) {
                    this.selected = this.selected.filter(id => !ids.includes(id));
                } else {
                    const set = new Set(this.selected);
                    ids.forEach(id => set.add(id));
                    this.selected = Array.from(set);
                }
            },
         }">

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

        <p class="text-sm text-gray-500 mb-4">
            {{ __("Dekan va registrator tasdiqlagan arizalar — guruhga ajratishdan oldin O'quv bo'limi tasdig'i kerak") }}
        </p>

        {{-- Bosqich tablari --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
            <div class="flex border-b border-gray-100">
                @php
                    $tabs = [
                        'pending' => ['label' => "Tasdiq kutmoqda", 'color' => 'amber', 'icon' => '⏳'],
                        'preapproved' => ['label' => "Tasdiqlangan (guruhsiz)", 'color' => 'blue', 'icon' => '✓'],
                        'rejected' => ['label' => "Rad etilgan", 'color' => 'red', 'icon' => '✕'],
                    ];
                @endphp
                @foreach($tabs as $key => $tab)
                    @php
                        $active = ($stage ?? 'pending') === $key;
                        $activeBg = match($tab['color']) {
                            'amber' => 'bg-amber-50 border-b-2 border-amber-500 text-amber-800',
                            'blue' => 'bg-blue-50 border-b-2 border-blue-500 text-blue-800',
                            'red' => 'bg-red-50 border-b-2 border-red-500 text-red-800',
                            default => 'bg-gray-50 border-b-2 border-gray-500 text-gray-800',
                        };
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['stage' => $key]) }}"
                       class="flex-1 px-4 py-3 text-center text-sm font-medium {{ $active ? $activeBg : 'text-gray-600 hover:bg-gray-50' }}">
                        <span class="text-base mr-1">{{ $tab['icon'] }}</span>
                        {{ __($tab['label']) }}
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $active ? 'bg-white' : 'bg-gray-100' }}">
                            {{ $counters[$key] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Cascading filtrlar (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr → Guruh + F.I.Sh) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-applications.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'extraQueryFields' => array_filter([
                'stage' => $stage !== 'pending' ? $stage : null,
            ]),
        ])

        {{-- Bulk actions panel (faqat tasdiq kutmoqda bosqichida ko'rinadi) --}}
        @if($stage === 'pending')
            <div x-show="selected.length > 0" x-cloak
                 class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 flex items-center justify-between flex-wrap gap-2">
                <div class="text-sm text-blue-800">
                    <strong x-text="selected.length"></strong> {{ __("ta ariza tanlangan") }}
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.retake-applications.bulk-approve') }}" class="inline">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="application_ids[]" :value="id">
                        </template>
                        <button type="submit"
                                class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ __("Tanlanganlarni tasdiqlash") }}
                        </button>
                    </form>
                    <button type="button" @click="bulkRejectOpen = true"
                            class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        {{ __("Tanlanganlarni rad etish") }}
                    </button>
                    <button type="button" @click="selected = []"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        {{ __("Tozalash") }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Arizalar jadvali --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($applications->count() === 0)
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Ushbu bosqichda ariza yo'q") }}
                </div>
            @else
                @php
                    $pageIds = $applications->pluck('id')->toArray();
                    $actionableIds = $stage === 'pending' ? $pageIds : [];
                @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            @if($stage === 'pending')
                                <th class="px-3 py-2 text-center" style="width:40px;">
                                    <input type="checkbox"
                                           @change="toggleAll(@js($actionableIds))"
                                           :checked="@js($actionableIds).length > 0 && @js($actionableIds).every(id => selected.includes(id))"
                                           class="rounded">
                                </th>
                            @endif
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:48px;">{{ __("T/R") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Talaba") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Semestr") }}</th>
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:140px;">{{ __("Dekan") }}</th>
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:140px;">{{ __("Registrator") }}</th>
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:140px;">{{ __("O'quv bo'limi") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Yuborilgan") }}</th>
                            @if($stage === 'pending')
                                <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase" style="width:160px;"></th>
                            @endif
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($applications as $i => $app)
                            @php
                                $student = $app->group?->student;
                            @endphp
                            <tr>
                                @if($stage === 'pending')
                                    <td class="px-3 py-2.5 text-center">
                                        <input type="checkbox" :value="{{ $app->id }}" x-model="selected" class="rounded">
                                    </td>
                                @endif
                                <td class="px-3 py-2.5 text-center text-sm font-bold text-blue-700">
                                    {{ ($applications->currentPage() - 1) * $applications->perPage() + $i + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-sm">
                                    <div class="font-medium text-gray-900">{{ $student?->full_name ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500">
                                        {{ $student?->department_name ?? '—' }} · {{ $student?->level_name ?? '—' }} · {{ $student?->group_name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $app->subject_name }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-600">{{ $app->semester_name }}</td>

                                {{-- Dekan ustun --}}
                                <td class="px-3 py-2.5 text-center">
                                    @include('teacher.academic-dept.retake-applications._stage_badge', [
                                        'status' => $app->dean_status,
                                        'userName' => $app->deanUser?->full_name ?? $app->dean_user_name,
                                        'decisionAt' => $app->dean_decision_at,
                                        'reason' => $app->dean_reason,
                                    ])
                                </td>

                                {{-- Registrator ustun --}}
                                <td class="px-3 py-2.5 text-center">
                                    @include('teacher.academic-dept.retake-applications._stage_badge', [
                                        'status' => $app->registrar_status,
                                        'userName' => $app->registrarUser?->full_name ?? $app->registrar_user_name,
                                        'decisionAt' => $app->registrar_decision_at,
                                        'reason' => $app->registrar_reason,
                                    ])
                                </td>

                                {{-- O'quv bo'limi ustun --}}
                                <td class="px-3 py-2.5 text-center">
                                    @php
                                        // Pre-approved (group hali yo'q) — alohida yorliq
                                        $effectiveStatus = $app->academic_dept_status;
                                        $extraNote = null;
                                        if ($effectiveStatus === 'approved' && empty($app->retake_group_id)) {
                                            $extraNote = __("guruhga kutmoqda");
                                        }
                                    @endphp
                                    @include('teacher.academic-dept.retake-applications._stage_badge', [
                                        'status' => $effectiveStatus,
                                        'userName' => $app->academicDeptUser?->full_name ?? $app->academic_dept_user_name,
                                        'decisionAt' => $app->academic_dept_decision_at,
                                        'reason' => $app->academic_dept_reason,
                                        'extraNote' => $extraNote,
                                    ])
                                </td>

                                <td class="px-3 py-2.5 text-xs text-gray-500 text-right whitespace-nowrap" title="{{ $app->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $app->created_at->diffForHumans() }}
                                </td>

                                @if($stage === 'pending')
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                        <form method="POST" action="{{ route('admin.retake-applications.approve', $app->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" title="{{ __('Tasdiqlash') }}"
                                                    class="text-xs px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                {{ __("Tasdiq") }}
                                            </button>
                                        </form>
                                        <button type="button"
                                                @click="rejectFor = {{ $app->id }}; rejectReason = ''"
                                                title="{{ __('Rad etish') }}"
                                                class="text-xs px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            {{ __("Rad") }}
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-100">
                    {{ $applications->links() }}
                </div>
            @endif
        </div>

        {{-- Yakka rad etish modal --}}
        <div x-show="rejectFor !== null" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             @keydown.escape.window="rejectFor = null">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="rejectFor = null"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
                    <h3 class="text-base font-bold text-gray-900 mb-4">{{ __("Arizani rad etish") }}</h3>
                    <form :action="`{{ url('/admin/retake-applications') }}/${rejectFor}/reject`" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Sabab") }} <span class="text-red-500">*</span></label>
                            <textarea name="reason" x-model="rejectReason" required rows="3"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg"
                                      placeholder="{{ __('Rad etish sababini batafsil yozing...') }}"></textarea>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="rejectFor = null"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __("Bekor qilish") }}</button>
                            <button type="submit"
                                    :disabled="!rejectReason.trim()"
                                    :class="rejectReason.trim() ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-300 cursor-not-allowed'"
                                    class="flex-1 px-3 py-2 text-sm text-white rounded-lg">{{ __("Rad etish") }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Bulk rad etish modal --}}
        <div x-show="bulkRejectOpen" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             @keydown.escape.window="bulkRejectOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="bulkRejectOpen = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
                    <h3 class="text-base font-bold text-gray-900 mb-1">{{ __("Tanlangan arizalarni rad etish") }}</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        <span x-text="selected.length"></span> {{ __("ta ariza rad etiladi") }}
                    </p>
                    <form method="POST" action="{{ route('admin.retake-applications.bulk-reject') }}" class="space-y-3">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="application_ids[]" :value="id">
                        </template>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Umumiy sabab") }} <span class="text-red-500">*</span></label>
                            <textarea name="reason" x-model="bulkRejectReason" required rows="3"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg"></textarea>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="bulkRejectOpen = false"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __("Bekor qilish") }}</button>
                            <button type="submit"
                                    :disabled="!bulkRejectReason.trim()"
                                    :class="bulkRejectReason.trim() ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-300 cursor-not-allowed'"
                                    class="flex-1 px-3 py-2 text-sm text-white rounded-lg">{{ __("Rad etish") }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
