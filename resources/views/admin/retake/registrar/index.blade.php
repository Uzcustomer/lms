<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Qayta o'qish arizalari (Registrator)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Statistika kartochkalari --}}
            @include('admin.retake._partials.stats-cards', [
                'stats' => $stats,
                'statusKey' => 'registrar_status',
                'current' => $filters['registrar_status'] ?? 'pending',
            ])

            {{-- Filtrlar --}}
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 p-3">
                <input type="hidden" name="registrar_status" value="{{ $filters['registrar_status'] ?? 'pending' }}">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Talaba qidiruv</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="F.I.Sh."
                           class="rounded-lg border-gray-300 text-sm w-56" />
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

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dekan holati</label>
                    <select name="dean_status" class="rounded-lg border-gray-300 text-sm w-40">
                        <option value="">Hammasi</option>
                        <option value="pending" {{ ($filters['dean_status'] ?? '') === 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                        <option value="approved" {{ ($filters['dean_status'] ?? '') === 'approved' ? 'selected' : '' }}>Tasdiqlagan</option>
                        <option value="rejected" {{ ($filters['dean_status'] ?? '') === 'rejected' ? 'selected' : '' }}>Rad etgan</option>
                    </select>
                </div>

                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    Filtrlash
                </button>

                @if(array_filter($filters))
                    <a href="{{ route('admin.retake.registrar.index') }}"
                       class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">Tozalash</a>
                @endif
            </form>

            {{-- Arizalar jadvali (fakultet ustuni bilan) --}}
            @include('admin.retake._partials.applications-table', [
                'applications' => $applications,
                'showRoute' => 'admin.retake.registrar.show',
                'showDepartment' => true,
            ])

        </div>
    </div>
</x-app-layout>
