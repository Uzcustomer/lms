<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish arizalari") }}
            <small class="text-muted text-sm font-normal">
                — {{ $role === 'dean' ? __('Dekan paneli') : __('Registrator paneli') }}
            </small>
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">

        {{-- Statistika kartlari --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Kutilmoqda') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['pending'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Tasdiqlangan') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['approved'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Rad etilgan') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['rejected'] }}</p>
            </div>
        </div>

        {{-- Cascading filtrlar (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr → Guruh + Fan) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'extraQueryFields' => array_filter([
                'filter' => $filter ?? null,
            ]),
        ])

        {{-- Holat filtri (qo'shimcha) --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
            <form method="GET" action="{{ route('admin.retake.index') }}" class="flex items-end gap-3 flex-wrap">
                {{-- Cascading filterdan keladigan qiymatlarni saqlab qolamiz --}}
                @foreach(['education_type','department','specialty','level_code','semester_code','group','subject','search','per_page'] as $kept)
                    @if(request($kept))
                        <input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">
                    @endif
                @endforeach
                <div class="min-w-[200px]">
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Holat') }}</label>
                    <select name="filter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                        <option value="pending_mine" {{ $filter === 'pending_mine' ? 'selected' : '' }}>{{ __('Kutilmoqda') }}</option>
                        @if($role === 'registrar')
                            <option value="payment_to_verify" {{ $filter === 'payment_to_verify' ? 'selected' : '' }}>
                                {{ __("To'lov cheki tekshirilishi kutilmoqda") }}
                                @if(($paymentToVerifyCount ?? 0) > 0) ({{ $paymentToVerifyCount }}) @endif
                            </option>
                        @endif
                        <option value="approved" {{ $filter === 'approved' ? 'selected' : '' }}>{{ __('Tasdiqlangan') }}</option>
                        <option value="rejected" {{ $filter === 'rejected' ? 'selected' : '' }}>{{ __('Rad etilgan') }}</option>
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>{{ __('Barchasi') }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        {{ __('Qo\'llash') }}
                    </button>
                    <a href="{{ route('admin.retake.index') }}" class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        {{ __('Tozalash') }}
                    </a>
                </div>
            </form>
        </div>

        {{-- Xabarlar --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Arizalar ro'yxati --}}
        @if($groups->count() === 0)
            <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                <p class="text-gray-500">{{ __('Tanlangan filtr bo\'yicha arizalar topilmadi') }}</p>
            </div>
        @else
            @php $canBulkDelete = $role === 'registrar'; @endphp

            @if($canBulkDelete)
                <div x-data="{
                        selected: [],
                        get pageIds() { return @js($groups->pluck('id')->values()->all()); },
                        get allChecked() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)); },
                        toggleAll(ev) {
                            if (ev.target.checked) {
                                this.pageIds.forEach(id => {
                                    if (!this.selected.includes(id)) this.selected.push(id);
                                });
                            } else {
                                this.pageIds.forEach(id => {
                                    const idx = this.selected.indexOf(id);
                                    if (idx > -1) this.selected.splice(idx, 1);
                                });
                            }
                        },
                        confirmDelete(ev) {
                            if (this.selected.length === 0) {
                                ev.preventDefault();
                                return;
                            }
                            if (!confirm(this.selected.length + ' ta arizani butunlay o\'chirishni tasdiqlaysizmi? Bu amal qaytarib bo\'lmaydi.')) {
                                ev.preventDefault();
                            }
                        }
                    }">
                    {{-- Bulk panel --}}
                    <div class="bg-white rounded-xl shadow-sm p-3 mb-3 flex items-center justify-between flex-wrap gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox"
                                   :checked="allChecked"
                                   @change="toggleAll($event)"
                                   class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>{{ __('Sahifadagi barchasini tanlash') }}</span>
                            <span class="text-xs text-gray-500" x-show="selected.length > 0">
                                (<span x-text="selected.length"></span> {{ __('ta tanlangan') }})
                            </span>
                        </label>

                        <form method="POST"
                              action="{{ route('admin.retake.bulk-delete') }}"
                              @submit="confirmDelete($event)">
                            @csrf
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="group_ids[]" :value="id">
                            </template>
                            <button type="submit"
                                    :disabled="selected.length === 0"
                                    :class="selected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700'"
                                    class="px-4 py-2 text-sm font-medium rounded-lg">
                                {{ __('Tanlanganlarni o\'chirish') }}
                                <span x-show="selected.length > 0">(<span x-text="selected.length"></span>)</span>
                            </button>
                        </form>
                    </div>

                    <div class="space-y-3">
                        @foreach($groups as $group)
                            @include('teacher.retake._group_card', [
                                'group' => $group,
                                'role' => $role,
                                'minReasonLength' => $minReasonLength,
                                'canBulkDelete' => true,
                            ])
                        @endforeach
                    </div>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($groups as $group)
                        @include('teacher.retake._group_card', [
                            'group' => $group,
                            'role' => $role,
                            'minReasonLength' => $minReasonLength,
                            'canBulkDelete' => false,
                        ])
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</x-teacher-app-layout>
