@php
    $btnKind = $kind ?? 'age';
    $btnCanvas = $canvas ?? 'ageChart';
@endphp
<button type="button" class="chart-excel-btn"
        data-chart="{{ $btnKind }}"
        data-canvas="{{ $btnCanvas }}"
        title="Excelga yuklab olish">
    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m-9 4.5V18a2 2 0 002 2h10a2 2 0 002-2v-1.5"/></svg>
    Excel
</button>
