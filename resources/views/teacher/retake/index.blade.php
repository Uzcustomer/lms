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

            @php $canBulkDecide = in_array($role, ['dean', 'registrar'], true); @endphp

            <div x-data="{
                    selected: [],
                    bulkApps: [],
                    bulkRejectOpen: false,
                    bulkRejectReason: '',
                    get pageGroupIds() { return @js($groups->pluck('id')->values()->all()); },
                    get pageAppIds() { return @js($groups->flatMap(fn($g) => $g->applications->filter(fn($a) => ($role === 'dean' ? $a->dean_status : $a->registrar_status) === 'pending' && $a->final_status === 'pending')->pluck('id'))->values()->all()); },
                    get allGroupsChecked() { return this.pageGroupIds.length > 0 && this.pageGroupIds.every(id => this.selected.includes(id)); },
                    get allAppsChecked() { return this.pageAppIds.length > 0 && this.pageAppIds.every(id => this.bulkApps.includes(id)); },
                    toggleAllGroups(ev) {
                        if (ev.target.checked) {
                            this.pageGroupIds.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
                        } else {
                            this.pageGroupIds.forEach(id => {
                                const idx = this.selected.indexOf(id);
                                if (idx > -1) this.selected.splice(idx, 1);
                            });
                        }
                    },
                    toggleAllApps(ev) {
                        if (ev.target.checked) {
                            this.pageAppIds.forEach(id => { if (!this.bulkApps.includes(id)) this.bulkApps.push(id); });
                        } else {
                            this.pageAppIds.forEach(id => {
                                const idx = this.bulkApps.indexOf(id);
                                if (idx > -1) this.bulkApps.splice(idx, 1);
                            });
                        }
                    },
                    confirmDelete(ev) {
                        if (this.selected.length === 0) { ev.preventDefault(); return; }
                        if (!confirm(this.selected.length + ' ta arizani butunlay o\'chirishni tasdiqlaysizmi? Bu amal qaytarib bo\'lmaydi.')) {
                            ev.preventDefault();
                        }
                    },
                    confirmBulkApprove(ev) {
                        if (this.bulkApps.length === 0) { ev.preventDefault(); return; }
                        if (!confirm(this.bulkApps.length + ' ta arizani tasdiqlashni tasdiqlaysizmi?')) {
                            ev.preventDefault();
                        }
                    }
                }">

                @if($canBulkDecide || $canBulkDelete)
                    <div class="bg-white rounded-xl shadow-sm p-3 mb-3 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-4 flex-wrap">
                            @if($canBulkDecide)
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox"
                                           :checked="allAppsChecked"
                                           @change="toggleAllApps($event)"
                                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>{{ __('Hammasini tanlash (arizalar)') }}</span>
                                    <span class="text-xs text-gray-500" x-show="bulkApps.length > 0">
                                        (<span x-text="bulkApps.length"></span>)
                                    </span>
                                </label>
                            @endif
                            @if($canBulkDelete)
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox"
                                           :checked="allGroupsChecked"
                                           @change="toggleAllGroups($event)"
                                           class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span>{{ __('Hammasini tanlash (guruhlar)') }}</span>
                                    <span class="text-xs text-gray-500" x-show="selected.length > 0">
                                        (<span x-text="selected.length"></span>)
                                    </span>
                                </label>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            @if($canBulkDecide)
                                {{-- Bulk approve — faqat dekan uchun (registratorda baho kerak) --}}
                                @if($role === 'dean')
                                    <form method="POST"
                                          action="{{ route('admin.retake.applications.bulk-decide') }}"
                                          @submit="confirmBulkApprove($event)"
                                          class="inline">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <template x-for="id in bulkApps" :key="'a'+id">
                                            <input type="hidden" name="application_ids[]" :value="id">
                                        </template>
                                        <button type="submit"
                                                :disabled="bulkApps.length === 0"
                                                :class="bulkApps.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-green-600 text-white hover:bg-green-700'"
                                                class="px-4 py-2 text-sm font-medium rounded-lg">
                                            ✓ {{ __("Tanlanganlarni tasdiqlash") }}
                                            <span x-show="bulkApps.length > 0">(<span x-text="bulkApps.length"></span>)</span>
                                        </button>
                                    </form>
                                @endif

                                <button type="button"
                                        @click="if (bulkApps.length > 0) bulkRejectOpen = true"
                                        :disabled="bulkApps.length === 0"
                                        :class="bulkApps.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700'"
                                        class="px-4 py-2 text-sm font-medium rounded-lg">
                                    ✗ {{ __("Tanlanganlarni rad etish") }}
                                    <span x-show="bulkApps.length > 0">(<span x-text="bulkApps.length"></span>)</span>
                                </button>
                            @endif

                            @if($canBulkDelete)
                                <form method="POST"
                                      action="{{ route('admin.retake.bulk-delete') }}"
                                      @submit="confirmDelete($event)"
                                      class="inline">
                                    @csrf
                                    <template x-for="id in selected" :key="'g'+id">
                                        <input type="hidden" name="group_ids[]" :value="id">
                                    </template>
                                    <button type="submit"
                                            :disabled="selected.length === 0"
                                            :class="selected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-700 text-white hover:bg-red-800'"
                                            class="px-4 py-2 text-sm font-medium rounded-lg">
                                        {{ __("Guruhlarni o'chirish") }}
                                        <span x-show="selected.length > 0">(<span x-text="selected.length"></span>)</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach($groups as $group)
                        @include('teacher.retake._group_card', [
                            'group' => $group,
                            'role' => $role,
                            'minReasonLength' => $minReasonLength,
                            'canBulkDelete' => $canBulkDelete,
                            'canBulkDecide' => $canBulkDecide,
                        ])
                    @endforeach
                </div>

                {{-- Bulk reject modal (sabab) --}}
                @if($canBulkDecide)
                    <div x-show="bulkRejectOpen" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         @keydown.escape.window="bulkRejectOpen = false">
                        <div class="fixed inset-0 bg-black bg-opacity-50" @click="bulkRejectOpen = false"></div>
                        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-5 z-10">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">
                                {{ __("Tanlangan arizalarni rad etish") }}
                            </h4>
                            <p class="text-xs text-gray-500 mb-3">
                                <span x-text="bulkApps.length"></span> {{ __("ta ariza uchun bir xil sabab qo'llaniladi") }}
                            </p>
                            <form method="POST" action="{{ route('admin.retake.applications.bulk-decide') }}">
                                @csrf
                                <input type="hidden" name="decision" value="rejected">
                                <template x-for="id in bulkApps" :key="'r'+id">
                                    <input type="hidden" name="application_ids[]" :value="id">
                                </template>
                                <textarea name="reason"
                                          x-model="bulkRejectReason"
                                          rows="4"
                                          required
                                          minlength="{{ $minReasonLength }}"
                                          maxlength="1000"
                                          placeholder="{{ __('Sababni yozing (eng kamida') }} {{ $minReasonLength }} {{ __('belgi)') }}"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"></textarea>
                                <div class="flex gap-2 mt-3">
                                    <button type="button"
                                            @click="bulkRejectOpen = false"
                                            class="flex-1 px-3 py-2 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                        {{ __('Bekor qilish') }}
                                    </button>
                                    <button type="submit"
                                            class="flex-1 px-3 py-2 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700">
                                        {{ __('Rad etish') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-4">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</x-teacher-app-layout>
