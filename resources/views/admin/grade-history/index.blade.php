<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Baholar tarixi (jurnal o'zgarishlari)
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            {{-- Statistika --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami yozuvlar</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Otrabotka baholari</div>
                    <div class="text-2xl font-semibold text-orange-600">{{ number_format($stats['retake_count']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Tahrir / o'chirish</div>
                    <div class="text-2xl font-semibold text-blue-600">
                        {{ number_format($stats['updates']) }} / {{ number_format($stats['deletes']) }}
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Filtrlar --}}
                    <form method="GET" action="{{ route('admin.grade-history.index') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Foydalanuvchi</label>
                                <input type="text" name="user_search" value="{{ request('user_search') }}"
                                       placeholder="Ism / familiya"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Talaba</label>
                                <input type="text" name="student_search" value="{{ request('student_search') }}"
                                       placeholder="Talaba ismi yoki HEMIS ID"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fan</label>
                                <input type="text" name="subject_search" value="{{ request('subject_search') }}"
                                       placeholder="Fan nomi"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amal turi</label>
                                <select name="action" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    <option value="create" {{ request('action') == 'create' ? 'selected' : '' }}>Yaratish</option>
                                    <option value="update" {{ request('action') == 'update' ? 'selected' : '' }}>Tahrirlash</option>
                                    <option value="delete" {{ request('action') == 'delete' ? 'selected' : '' }}>O'chirish</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                                <select name="role" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Boshlanish</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tugash</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <label class="inline-flex items-center text-sm">
                                    <input type="checkbox" name="only_retake" value="1"
                                           {{ request('only_retake') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-orange-600 shadow-sm">
                                    <span class="ml-2">Faqat otrabotka</span>
                                </label>
                            </div>
                            <div class="md:col-span-2 flex items-end gap-2">
                                <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">
                                    Filtrlash
                                </button>
                                <a href="{{ route('admin.grade-history.index') }}"
                                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                                    Tozalash
                                </a>
                            </div>
                        </div>
                    </form>

                    {{-- Jadval --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vaqt</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kim</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amal</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fan</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dars sanasi</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">O'qituvchi</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">O'zgarishlar</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($logs as $log)
                                    @php
                                        $grade = $grades[$log->subject_id] ?? null;
                                        $student = $grade ? ($students[$grade->student_hemis_id] ?? null) : null;

                                        $old = $log->old_values ?? [];
                                        $new = $log->new_values ?? [];
                                        $changedKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

                                        $isRetake = array_key_exists('retake_grade', $old) || array_key_exists('retake_grade', $new);
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $isRetake ? 'bg-orange-50' : '' }}">
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">
                                            {{ $log->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $log->user_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-400">{{ $log->role ?? $log->guard }}</div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @php
                                                $colors = [
                                                    'create' => 'bg-green-100 text-green-800',
                                                    'update' => 'bg-blue-100 text-blue-800',
                                                    'delete' => 'bg-red-100 text-red-800',
                                                ];
                                                $colorClass = $colors[$log->action] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colorClass }}">
                                                {{ $log->action_label }}
                                            </span>
                                            @if($isRetake)
                                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                    Otrabotka
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                            {{ $student->full_name ?? ($grade->student_hemis_id ?? '-') }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 max-w-xs truncate" title="{{ $grade->subject_name ?? '' }}">
                                            {{ $grade->subject_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs">
                                            {{ $grade && $grade->lesson_date ? \Carbon\Carbon::parse($grade->lesson_date)->format('d.m.Y') : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 text-xs max-w-xs truncate" title="{{ $grade->employee_name ?? '' }}">
                                            {{ $grade->employee_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @foreach($changedKeys as $key)
                                                @php
                                                    $oldVal = $old[$key] ?? null;
                                                    $newVal = $new[$key] ?? null;
                                                @endphp
                                                <div>
                                                    <span class="font-medium text-gray-600">{{ $key }}:</span>
                                                    <span class="text-red-600">{{ is_scalar($oldVal) || $oldVal === null ? ($oldVal ?? '—') : json_encode($oldVal, JSON_UNESCAPED_UNICODE) }}</span>
                                                    →
                                                    <span class="text-green-700">{{ is_scalar($newVal) || $newVal === null ? ($newVal ?? '—') : json_encode($newVal, JSON_UNESCAPED_UNICODE) }}</span>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <a href="{{ route('admin.activity-log.show', $log) }}"
                                               class="text-indigo-600 hover:text-indigo-900 text-xs">
                                                Batafsil
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                            Hozircha yozuvlar yo'q
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
