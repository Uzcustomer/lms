@php
    $accomStats = $accomStats ?? [];
    $accomTotal = array_sum(array_column($accomStats, 'total'));
    $accomColors = ['#ec4899', '#3b82f6', '#f59e0b', '#a855f7', '#06b6d4', '#22c55e', '#ef4444'];
    $cId = $canvasId ?? 'accomChart';
@endphp
<div class="course-bar-card">
    @include('admin.students._excel_btn', ['kind' => 'accom', 'canvas' => $cId])
    <h3>Yashash joyi</h3>
    <div class="stat-card-kpis" style="flex-wrap:wrap; gap:28px; margin-bottom:12px;">
        @php $idx = 0; @endphp
        @foreach($accomStats as $name => $row)
            <div>
                <span class="lbl">{{ $name }}</span>
                <span class="val" data-count="{{ (int) $row['total'] }}">{{ number_format($row['total'], 0, '.', ' ') }}</span>
                <span class="pct" style="color: {{ $accomColors[$idx % count($accomColors)] }}; font-weight:700;">
                    ({{ $accomTotal > 0 ? number_format($row['total'] * 100 / $accomTotal, 1) : 0 }}%)
                </span>
            </div>
            @php $idx++; @endphp
        @endforeach
    </div>
    <div class="course-bar-wrap" style="height:340px;">
        <canvas id="{{ $cId }}"></canvas>
    </div>
</div>
