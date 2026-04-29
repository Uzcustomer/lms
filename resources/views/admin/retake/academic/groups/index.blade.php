<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Qayta o'qish guruhlari</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Navigation --}}
            <div class="mb-4 flex items-center gap-3">
                <a href="{{ route('admin.retake.academic.index') }}"
                   class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    ← Yakuniy bosqichga qaytish
                </a>
            </div>

            {{-- Filtrlar --}}
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 p-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Qidiruv</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Guruh nomi yoki fan"
                           class="rounded-lg border-gray-300 text-sm w-64" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Holat</label>
                    <select name="status" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm w-44">
                        <option value="">Hammasi</option>
                        <option value="forming" {{ ($filters['status'] ?? '') === 'forming' ? 'selected' : '' }}>Shakllantirilmoqda</option>
                        <option value="scheduled" {{ ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' }}>Rejalashtirilgan</option>
                        <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>Davom etmoqda</option>
                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Tugagan</option>
                    </select>
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    Filtrlash
                </button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.retake.academic.groups.index') }}"
                       class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">Tozalash</a>
                @endif
            </form>

            {{-- Guruhlar jadvali --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Guruh nomi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">O'qituvchi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sanalar</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Talabalar</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Holat</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($groups as $group)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800">{{ $group->name }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm text-gray-700">{{ $group->subject_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $group->semester_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $group->teacher?->full_name }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-600">
                                        {{ $group->start_date->format('d.m.Y') }}<br>
                                        → {{ $group->end_date->format('d.m.Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-gray-700">{{ $group->applications_count }}</span>
                                        @if($group->max_students)
                                            <span class="text-xs text-gray-500">/ {{ $group->max_students }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php $status = $group->status?->value; @endphp
                                        <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-medium
                                            @if($status === 'scheduled') bg-blue-100 text-blue-700
                                            @elseif($status === 'in_progress') bg-emerald-100 text-emerald-700
                                            @elseif($status === 'completed') bg-gray-100 text-gray-600
                                            @else bg-amber-100 text-amber-700
                                            @endif">
                                            @if($status === 'scheduled') Rejalashtirilgan
                                            @elseif($status === 'in_progress') Davom etmoqda
                                            @elseif($status === 'completed') Tugagan
                                            @else Shakllantirilmoqda
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.retake.academic.groups.show', $group->id) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg">
                                            Tafsilot
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                        Hozircha guruhlar yaratilmagan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($groups->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $groups->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
