{{--
    Ustun sarlavhasidagi ochilma filtr (Excel uslubida).

    Parametrlar:
      key               — JS uchun kalit (colFilter_<key>, col-filter-cb-<key>)
      field             — form maydoni nomi, massiv sifatida yuboriladi: field[]
      items             — [qiymat => yorliq]
      selected          — tanlangan qiymatlar massivi
      emptyLabel        — berilsa, "__empty__" varianti shu yorliq bilan chiqadi
      searchPlaceholder — berilsa, qidiruv maydoni ko'rsatiladi

    Sana ustunlari (reg_end, visa_end) o'z HTMLini saqlab qolgan — ular sanani
    formatlaydi; bu partial matn qiymatlar uchun.
--}}
@php
    $emptyLabel = $emptyLabel ?? null;
    $searchPlaceholder = $searchPlaceholder ?? null;
    $count = count($selected);
@endphp

<button type="button" class="col-filter-btn {{ $count ? 'col-filter-active' : '' }}"
        onclick="toggleColFilter(event, '{{ $key }}')" title="Filtr">
    <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 9v7l-4-2v-5L3 4z"/></svg>
    @if($count) <span class="col-filter-count">{{ $count }}</span>@endif
</button>

<div id="colFilter_{{ $key }}" class="col-filter-popup" onclick="event.stopPropagation();">
    @if($searchPlaceholder)
        <div class="col-filter-head">
            <input type="text" placeholder="{{ $searchPlaceholder }}" class="col-filter-search"
                   oninput="filterColList('{{ $key }}', this.value)">
        </div>
    @endif

    <label class="col-filter-item col-filter-all">
        <input type="checkbox" id="colFilterAll_{{ $key }}" onchange="toggleAllColItems('{{ $key }}', this.checked)">
        <span>Barchasini tanlash</span>
    </label>

    <div class="col-filter-list" id="colFilterList_{{ $key }}">
        @if($emptyLabel)
            <label class="col-filter-item col-filter-empty-opt" data-text="{{ $emptyLabel }} bo'sh empty">
                <input type="checkbox" form="filterForm" name="{{ $field }}[]" value="__empty__"
                       {{ in_array('__empty__', $selected, true) ? 'checked' : '' }}
                       class="col-filter-cb-{{ $key }}">
                <span><em>({{ $emptyLabel }})</em></span>
            </label>
        @endif

        @forelse($items as $value => $label)
            <label class="col-filter-item" data-text="{{ $label }}">
                <input type="checkbox" form="filterForm" name="{{ $field }}[]" value="{{ $value }}"
                       {{ in_array((string) $value, array_map('strval', $selected), true) ? 'checked' : '' }}
                       class="col-filter-cb-{{ $key }}">
                <span>{{ $label }}</span>
            </label>
        @empty
            <div class="col-filter-empty">Qiymat topilmadi</div>
        @endforelse
    </div>

    <div class="col-filter-actions">
        <button type="button" class="col-filter-clear-btn" onclick="clearColFilter('{{ $key }}')">Tozalash</button>
        <button type="submit" form="filterForm" class="col-filter-apply-btn">Qo'llash</button>
    </div>
</div>
