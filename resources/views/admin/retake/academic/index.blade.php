<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Qayta o'qish — yakuniy bosqich</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Statistika --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="bg-white rounded-xl border-2 border-amber-200 p-4">
                    <div class="text-xs font-medium text-gray-600 uppercase">Kutilayotgan to'plamlar</div>
                    <div class="text-2xl font-bold text-amber-700 mt-1">{{ $stats['pending_groups'] }}</div>
                </div>
                <div class="bg-white rounded-xl border-2 border-yellow-200 p-4">
                    <div class="text-xs font-medium text-gray-600 uppercase">Kutilayotgan arizalar</div>
                    <div class="text-2xl font-bold text-yellow-700 mt-1">{{ $stats['pending_applications'] }}</div>
                </div>
                <a href="{{ route('admin.retake.academic.groups.index') }}?status=scheduled"
                   class="block bg-white rounded-xl border-2 border-emerald-200 p-4 hover:border-emerald-400 transition">
                    <div class="text-xs font-medium text-gray-600 uppercase">Faol guruhlar</div>
                    <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $stats['active_groups'] }}</div>
                </a>
                <a href="{{ route('admin.retake.academic.groups.index') }}?status=completed"
                   class="block bg-white rounded-xl border-2 border-gray-200 p-4 hover:border-gray-400 transition">
                    <div class="text-xs font-medium text-gray-600 uppercase">Tugagan guruhlar</div>
                    <div class="text-2xl font-bold text-gray-700 mt-1">{{ $stats['completed_groups'] }}</div>
                </a>
            </div>

            {{-- Filtrlar --}}
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 p-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Qidiruv (fan/talaba)</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           class="rounded-lg border-gray-300 text-sm w-64" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fakultet</label>
                    <select name="department_id" class="rounded-lg border-gray-300 text-sm w-56">
                        <option value="">Hammasi</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->department_hemis_id }}"
                                {{ ($filters['department_id'] ?? '') == $dept->department_hemis_id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    Filtrlash
                </button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.retake.academic.index') }}"
                       class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">Tozalash</a>
                @endif
            </form>

            <h3 class="text-base font-semibold text-gray-800 mb-3">
                Kutilayotgan arizalar (fan + semestr bo'yicha gruhlangan)
            </h3>

            @if($grouped->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                    </svg>
                    <p class="text-gray-500">Hozircha tasdiqlash kutayotgan arizalar yo'q.</p>
                </div>
            @else
                <div class="space-y-3" x-data="{ openModal: null, openReject: null }">
                    @foreach($grouped as $group)
                        @php $key = $group['subject_id'] . '-' . $group['semester_id']; @endphp
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="p-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-800 truncate">{{ $group['subject_name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $group['semester_name'] }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-gray-800">{{ $group['count'] }}</div>
                                        <div class="text-xs text-gray-500">talaba</div>
                                    </div>
                                    <button type="button"
                                            @click="openModal = '{{ $key }}'"
                                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg">
                                        Guruh shakllantirish
                                    </button>
                                    <button type="button"
                                            x-data="{ expanded: false }"
                                            @click="expanded = !expanded; $el.parentElement.parentElement.parentElement.querySelector('[data-list]').classList.toggle('hidden')"
                                            class="px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                                        Talabalar
                                    </button>
                                </div>
                            </div>

                            {{-- Talabalar ro'yxati (kollaps) --}}
                            <div data-list class="hidden border-t border-gray-100 bg-gray-50">
                                <div class="divide-y divide-gray-200">
                                    @foreach($group['applications'] as $app)
                                        <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-sm">
                                            <div>
                                                <span class="font-medium text-gray-800">{{ $app->student?->full_name }}</span>
                                                <span class="text-xs text-gray-500 ml-2">{{ $app->student?->group_name }} — {{ $app->student?->department_name }}</span>
                                            </div>
                                            <button type="button"
                                                    @click="openReject = {{ $app->id }}"
                                                    class="text-xs text-red-600 hover:text-red-800 font-medium">
                                                Yakka rad etish
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Guruh shakllantirish modali --}}
                            <div x-show="openModal === '{{ $key }}'" x-cloak
                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
                                 @keydown.escape.window="openModal = null">
                                <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl max-h-[90vh] overflow-hidden flex flex-col"
                                     @click.outside="openModal = null">
                                    <form method="POST" action="{{ route('admin.retake.academic.groups.store') }}" class="flex flex-col flex-1 overflow-hidden">
                                        @csrf
                                        <input type="hidden" name="subject_id" value="{{ $group['subject_id'] }}">
                                        <input type="hidden" name="subject_name" value="{{ $group['subject_name'] }}">
                                        <input type="hidden" name="semester_id" value="{{ $group['semester_id'] }}">
                                        <input type="hidden" name="semester_name" value="{{ $group['semester_name'] }}">

                                        <div class="p-5 border-b border-gray-100">
                                            <h3 class="text-lg font-semibold text-gray-800">Guruh shakllantirish — {{ $group['subject_name'] }}</h3>
                                            <p class="text-xs text-gray-500 mt-1">{{ $group['semester_name'] }}</p>
                                        </div>

                                        <div class="p-5 overflow-y-auto flex-1 space-y-4"
                                             x-data="groupForm{{ $loop->index }}({{ $group['count'] }})">

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Guruh nomi <span class="text-red-500">*</span></label>
                                                <input type="text" name="name" required maxlength="255"
                                                       value="{{ $group['subject_name'] }} — qayta o'qish {{ now()->year }}"
                                                       class="w-full rounded-lg border-gray-300 text-sm" />
                                            </div>

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Boshlanish sanasi <span class="text-red-500">*</span></label>
                                                    <input type="date" name="start_date" required
                                                           class="w-full rounded-lg border-gray-300 text-sm" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tugash sanasi <span class="text-red-500">*</span></label>
                                                    <input type="date" name="end_date" required
                                                           class="w-full rounded-lg border-gray-300 text-sm" />
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">O'qituvchi <span class="text-red-500">*</span></label>
                                                <select name="teacher_id" required
                                                        class="w-full rounded-lg border-gray-300 text-sm">
                                                    <option value="">— Tanlang —</option>
                                                    @foreach($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Maks. talabalar (ixtiyoriy)</label>
                                                <input type="number" name="max_students" min="1" max="1000"
                                                       value="{{ $group['count'] }}"
                                                       class="w-full rounded-lg border-gray-300 text-sm" />
                                            </div>

                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Talabalar (<span x-text="selectedCount">{{ $group['count'] }}</span>/{{ $group['count'] }})
                                                    </label>
                                                    <div class="flex gap-2">
                                                        <button type="button" @click="selectAll()"
                                                                class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                                            Hammasi
                                                        </button>
                                                        <span class="text-gray-300">|</span>
                                                        <button type="button" @click="clearAll()"
                                                                class="text-xs text-gray-600 hover:text-gray-800 font-medium">
                                                            Hech biri
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto bg-gray-50">
                                                    @foreach($group['applications'] as $app)
                                                        <label class="flex items-center gap-3 px-3 py-2 hover:bg-white cursor-pointer border-b border-gray-100 last:border-b-0">
                                                            <input type="checkbox" name="application_ids[]"
                                                                   value="{{ $app->id }}" checked
                                                                   @change="updateCount()"
                                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-sm font-medium text-gray-800 truncate">{{ $app->student?->full_name }}</div>
                                                                <div class="text-xs text-gray-500">{{ $app->student?->group_name }} — {{ $app->student?->department_name }}</div>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Belgilanmaganlar pending holatda qoladi va keyingi guruhga qo'shilishi mumkin.</p>
                                            </div>
                                        </div>

                                        <div class="p-5 border-t border-gray-100 flex justify-end gap-2">
                                            <button type="button" @click="openModal = null"
                                                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                                Bekor qilish
                                            </button>
                                            <button type="submit"
                                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg">
                                                Saqlash va tasdiqlash
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            {{-- Yakka rad etish modali --}}
                            @foreach($group['applications'] as $app)
                                <div x-show="openReject === {{ $app->id }}" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                                     @keydown.escape.window="openReject = null">
                                    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl"
                                         @click.outside="openReject = null">
                                        <form method="POST" action="{{ route('admin.retake.academic.application.reject', $app->id) }}"
                                              x-data="{ reason: '' }">
                                            @csrf
                                            <div class="p-5 border-b border-gray-100">
                                                <h3 class="text-base font-semibold text-gray-800">Arizani rad etish</h3>
                                                <p class="text-xs text-gray-500 mt-1">{{ $app->student?->full_name }}</p>
                                            </div>
                                            <div class="p-5 space-y-2">
                                                <textarea name="rejection_reason" x-model="reason"
                                                          minlength="10" maxlength="500" required rows="4"
                                                          class="w-full text-sm rounded-lg border-gray-300"
                                                          placeholder="Sabab (10-500 belgi)"></textarea>
                                                <div class="text-right text-xs text-gray-500">
                                                    <span x-text="reason.length"></span>/500
                                                </div>
                                            </div>
                                            <div class="p-5 border-t border-gray-100 flex justify-end gap-2">
                                                <button type="button" @click="openReject = null"
                                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg">
                                                    Bekor
                                                </button>
                                                <button type="submit"
                                                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg">
                                                    Rad etish
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    @push('styles')
        <style>[x-cloak] { display: none !important; }</style>
    @endpush

    <script>
        // Har "Guruh shakllantirish" modal'i uchun alohida x-data instance
        @foreach($grouped as $idx => $group)
        function groupForm{{ $idx }}(initialCount) {
            return {
                selectedCount: initialCount,
                updateCount() {
                    this.selectedCount = this.$el.querySelectorAll('input[name="application_ids[]"]:checked').length;
                },
                selectAll() {
                    this.$el.querySelectorAll('input[name="application_ids[]"]').forEach(cb => cb.checked = true);
                    this.updateCount();
                },
                clearAll() {
                    this.$el.querySelectorAll('input[name="application_ids[]"]').forEach(cb => cb.checked = false);
                    this.updateCount();
                },
            };
        }
        @endforeach
    </script>
</x-app-layout>
