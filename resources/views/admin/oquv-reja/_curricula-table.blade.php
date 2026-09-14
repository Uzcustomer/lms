{{-- Yuklangan o'quv rejalar jadvali (saralanadigan; bir nechtasini belgilab o'chirish mumkin).
     $list - ManualCurriculum kolleksiyasi (subjects_count, total_hours, total_credit bilan).
     $emptyText - ro'yxat bo'sh bo'lganda ko'rsatiladigan matn.
     $tableKey - (ixtiyoriy) partial bir sahifada bir necha marta qo'shilganda
                 checkbox'lar va bulk forma aralashmasligi uchun noyob kalit. --}}
@php
    $bulkId = 'curricula-bulk-' . ($tableKey ?? uniqid());
    $bulkFormId = $bulkId . '-form';
@endphp
<div id="{{ $bulkId }}">
    @if($list->isNotEmpty())
        {{-- Belgilanganlarni o'chirish paneli. Checkbox'lar jadval ichida, forma esa
             tashqarida — qatorlardagi bitta-bitta o'chirish formalari bilan ichma-ich
             tushmasligi uchun; ular form="..." atributi orqali bog'langan. --}}
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm text-gray-600">
                <span class="js-bulk-hint">Bir nechta rejani o'chirish uchun qatorlarni belgilang.</span>
                <span class="js-bulk-count font-medium text-gray-800 hidden"></span>
            </div>
            <form id="{{ $bulkFormId }}" method="POST" action="{{ route('admin.oquv-reja.bulk-delete') }}" class="js-bulk-form">
                @csrf
                <button type="submit" disabled
                        class="js-bulk-submit px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 text-gray-400 cursor-not-allowed">
                    Tanlanganlarni o'chirish
                </button>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="js-sortable-table min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 w-8 text-center">
                    @if($list->isNotEmpty())
                        <input type="checkbox" autocomplete="off" title="Barchasini tanlash" aria-label="Barchasini tanlash"
                               class="js-bulk-check-all w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    @endif
                </th>
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
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" name="ids[]" value="{{ $curriculum->id }}" form="{{ $bulkFormId }}" autocomplete="off"
                               aria-label="Tanlash: {{ $curriculum->name }}"
                               class="js-bulk-check w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    </td>
                    <td class="px-4 py-2" data-sort-value="{{ $loop->iteration }}">{{ $loop->iteration }}</td>
                    <td class="px-4 py-2" data-sort-value="{{ $curriculum->name }}">
                        <a href="{{ route('admin.oquv-reja.show', $curriculum) }}" class="text-blue-600 hover:underline">
                            {{ $curriculum->name }}
                        </a>
                        @if($curriculum->isPlanned())
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800" title="HEMIS'ga bog'lanmagan rejalashtirilgan reja">&#9203; Rejalashtirilgan</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        {{ trim($curriculum->specialty_code . ' ' . $curriculum->specialty_name) ?: '—' }}
                        @if($curriculum->isPlanned())
                            <div class="text-[11px] text-amber-600">HEMIS'ga bog'lanmagan</div>
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $curriculum->plan_year ?: '—' }}</td>
                    <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->subjects_count }}">{{ $curriculum->subjects_count }}</td>
                    <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->total_hours ?? 0 }}">{{ rtrim(rtrim(number_format($curriculum->total_hours ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                    <td class="px-4 py-2 text-right" data-sort-value="{{ $curriculum->total_credit ?? 0 }}">{{ rtrim(rtrim(number_format($curriculum->total_credit ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                    <td class="px-4 py-2" data-sort-value="{{ $curriculum->created_at->timestamp }}">{{ $curriculum->created_at->format('d.m.Y H:i') }}</td>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('admin.oquv-reja.destroy', $curriculum) }}"
                              onsubmit="return confirmAndPreserveOquvRejaState('Ushbu reja va uning barcha fan qatorlari o\\'chirilsinmi?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm">O'chirish</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-6 text-center text-gray-500">
                        {{ $emptyText ?? "Hozircha o'quv reja yuklanmagan." }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($list->isNotEmpty())
<script>
    (function () {
        // ===== Bir nechtasini belgilab o'chirish (faqat shu jadval doirasida) =====
        const root = document.getElementById(@json($bulkId));
        if (!root) return;
        const form = root.querySelector('.js-bulk-form');
        const submitBtn = root.querySelector('.js-bulk-submit');
        const hintEl = root.querySelector('.js-bulk-hint');
        const countEl = root.querySelector('.js-bulk-count');
        const checkAll = root.querySelector('.js-bulk-check-all');
        if (!form || !submitBtn) return;

        const rowBoxes = () => Array.from(root.querySelectorAll('.js-bulk-check'));
        const ENABLED = ['bg-red-600', 'text-white', 'hover:bg-red-700'];
        const DISABLED = ['bg-gray-200', 'text-gray-400', 'cursor-not-allowed'];

        function refresh() {
            const boxes = rowBoxes();
            const checked = boxes.filter(b => b.checked).length;

            submitBtn.disabled = checked === 0;
            ENABLED.forEach(c => submitBtn.classList.toggle(c, checked > 0));
            DISABLED.forEach(c => submitBtn.classList.toggle(c, checked === 0));
            submitBtn.textContent = checked > 0
                ? "Tanlanganlarni o'chirish (" + checked + ")"
                : "Tanlanganlarni o'chirish";

            if (hintEl) hintEl.classList.toggle('hidden', checked > 0);
            if (countEl) {
                countEl.textContent = checked + ' ta reja tanlandi';
                countEl.classList.toggle('hidden', checked === 0);
            }
            if (checkAll) {
                checkAll.checked = boxes.length > 0 && checked === boxes.length;
                checkAll.indeterminate = checked > 0 && checked < boxes.length;
            }
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                rowBoxes().forEach(b => { b.checked = checkAll.checked; });
                refresh();
            });
        }
        root.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('js-bulk-check')) refresh();
        });

        form.addEventListener('submit', function (e) {
            const checked = rowBoxes().filter(b => b.checked).length;
            if (checked === 0) { e.preventDefault(); return; }
            const msg = 'Tanlangan ' + checked + " ta reja va ularning barcha fan qatorlari o'chirilsinmi?";
            // Sahifa qayta yuklanganda ochiq vkladka va scroll holati saqlanib qolsin
            // (bitta-bitta o'chirish bilan bir xil). Helper index.blade.php da aniqlangan.
            const ok = typeof window.confirmAndPreserveOquvRejaState === 'function'
                ? window.confirmAndPreserveOquvRejaState(msg)
                : window.confirm(msg);
            if (!ok) e.preventDefault();
        });

        refresh();
    })();
</script>
@endif
