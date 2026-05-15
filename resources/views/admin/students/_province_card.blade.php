@php
    $provinceStats = $provinceStats ?? [];
    $provMaleTotal = array_sum(array_column($provinceStats, 'male'));
    $provFemaleTotal = array_sum(array_column($provinceStats, 'female'));
    $cId = $canvasId ?? 'provinceChart';
@endphp
<div class="course-bar-card">
    <h3>Viloyatlar</h3>
    <div class="stat-card-kpis" style="margin-bottom:12px;">
        <div>
            <span class="lbl" style="color:#3b82f6;">Erkaklar</span>
            <span class="val" data-count="{{ (int) $provMaleTotal }}">{{ number_format($provMaleTotal, 0, '.', ' ') }}</span>
        </div>
        <div>
            <span class="lbl" style="color:#ec4899;">Ayollar</span>
            <span class="val" data-count="{{ (int) $provFemaleTotal }}">{{ number_format($provFemaleTotal, 0, '.', ' ') }}</span>
        </div>
    </div>
    <div class="course-bar-wrap" style="height:420px;">
        <canvas id="{{ $cId }}"></canvas>
    </div>
</div>
