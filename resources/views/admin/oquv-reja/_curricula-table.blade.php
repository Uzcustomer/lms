{{-- Yuklangan o'quv rejalar jadvali (saralanadigan).
     $list — ManualCurriculum kolleksiyasi (subjects_count, total_hours, total_credit bilan).
     $emptyText — ro'yxat bo'sh bo'lganda ko'rsatiladigan matn. --}}
<div class="overflow-x-auto">
    <table class="js-sortable-table min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
        <tr>
            <th data-sort-type="number" class="js-sortable cursor-pointer select-none px-4 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">#<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="text" class="js-sortable cursor-pointer select-none px-4 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Nomi<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="text" class="js-sortable cursor-pointer select-none px-4 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Yo'nalish<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="text" class="js-sortable cursor-pointer select-none px-4 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Reja yili<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="number" class="js-sortable cursor-pointer select-none px-4 py-2 text-right font-medium text-gray-600 hover:bg-gray-100">Fan qatorlari<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="number" class="js-sortable cursor-pointer select-none px-4 py-2 text-right font-medium text-gray-600 hover:bg-gray-100">Jami soat<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="number" class="js-sortable cursor-pointer select-none px-4 py-2 text-right font-medium text-gray-600 hover:bg-gray-100">Jami kredit<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th data-sort-type="number" class="js-sortable cursor-pointer select-none px-4 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Yuklangan<span class="js-sort-indicator ml-1 text-gray-400"></span></th>
            <th class="px-4 py-2 text-left font-medium text-gray-600">Amallar</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($list as $curriculum)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2" data-sort-value="{{ $loop->iteration }}">{{ $loop->iteration }}</td>
                <td class="px-4 py-2" data-sort-value="{{ $curriculum->name }}">
                    <a href="{{ route('admin.oquv-reja.show', $curriculum) }}" class="text-blue-600 hover:underline">
                        {{ $curriculum->name }}
                    </a>
                </td>
                <td class="px-4 py-2">{{ trim($curriculum->specialty_code . ' ' . $curriculum->specialty_name) ?: '—' }}</td>
                <td class="px-4 py-2">{{ $curriculum->plan_year ?: '—' }}</td>
                <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->subjects_count }}">{{ $curriculum->subjects_count }}</td>
                <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->total_hours ?? 0 }}">{{ rtrim(rtrim(number_format($curriculum->total_hours ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->total_credit ?? 0 }}">{{ rtrim(rtrim(number_format($curriculum->total_credit ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                <td class="px-4 py-2" data-sort-value="{{ $curriculum->created_at->timestamp }}">{{ $curriculum->created_at->format('d.m.Y H:i') }}</td>
                <td class="px-4 py-2">
                    <form method="POST" action="{{ route('admin.oquv-reja.destroy', $curriculum) }}"
                          onsubmit="return confirm('Ushbu reja va uning barcha fan qatorlari o’chirilsinmi?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-sm">O'chirish</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">
                    {{ $emptyText ?? "Hozircha o'quv reja yuklanmagan." }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
