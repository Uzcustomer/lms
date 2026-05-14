<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Talabalar statistikasi
    </h2>
</x-slot>

<style>
    [x-cloak]{display:none !important;}

    .stats-outer-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }
    .stats-outer-btn {
        flex: 1 1 0;
        min-width: 240px;
        padding: 18px 28px;
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        background: #ffffff;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        cursor: pointer;
        transition: all .15s ease;
    }
    .stats-outer-btn:hover {
        border-color: #cbd5e1;
        color: #1e293b;
    }
    .stats-outer-btn.active {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 6px 16px -4px rgba(79, 70, 229, .45);
    }

    .stats-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 18px 22px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .stats-inner-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 22px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
    }
    .stats-inner-btn {
        padding: 10px 18px;
        font-size: 13px;
        font-weight: 600;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer;
        transition: all .15s ease;
    }
    .stats-inner-btn:hover {
        background: #eef2ff;
        color: #4338ca;
        border-color: #c7d2fe;
    }
    .stats-inner-btn.active {
        background: #4f46e5;
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 3px 10px -3px rgba(79, 70, 229, .55);
    }

    .stats-empty {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 56px 24px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
    .stats-empty strong {
        color: #475569;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 18px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 22px 24px;
        box-shadow: 0 4px 14px -4px rgba(15, 23, 42, .12);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .kpi-card:hover {
        transform: translateY(-4px) scale(1.025);
        box-shadow: 0 14px 30px -8px rgba(15, 23, 42, .22);
    }
    /* Yuqori qator: ikonka + sarlavha + qiymat — bitta qatorda */
    .kpi-head {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .kpi-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: #eef2ff;
        color: #4f46e5;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .kpi-headtext { display: flex; flex-direction: column; }
    .kpi-title { font-size: 18px; font-weight: 700; color: #0f172a; line-height: 1.15; }
    .kpi-sub   { font-size: 13px; color: #94a3b8; }
    .kpi-total { font-size: 34px; font-weight: 800; color: #0f172a; line-height: 1; margin-left: auto; }
    .kpi-split { display: flex; gap: 36px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
    .kpi-split .lbl { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 3px; }
    .kpi-split .val { font-size: 22px; font-weight: 700; color: #0f172a; }
    .kpi-split .pct { font-size: 14px; font-weight: 700; margin-left: 5px; }
    .pct-m { color: #059669; }
    .pct-f { color: #dc2626; }

    .pie-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 18px;
        margin-top: 22px;
    }
    .pie-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 22px 24px;
        box-shadow: 0 4px 14px -4px rgba(15, 23, 42, .12);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .pie-card:hover {
        transform: translateY(-4px) scale(1.015);
        box-shadow: 0 14px 30px -8px rgba(15, 23, 42, .22);
    }
    .pie-card h3 { font-size: 19px; font-weight: 700; color: #0f172a; margin-bottom: 16px; }
    /* Chap: chart, o'ng: ko'rsatkichlar */
    .pie-body { display: flex; align-items: center; gap: 44px; }
    .pie-canvas-wrap { position: relative; height: 230px; width: 230px; flex-shrink: 0; }
    .pie-legend { flex: 1; display: flex; flex-direction: column; gap: 22px; }
    .pie-legend-item { display: flex; align-items: center; gap: 14px; }
    .pie-legend-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; flex-shrink: 0;
    }
    .pie-legend-text { display: flex; flex-direction: column; }
    .pie-legend-label { font-size: 14px; color: #64748b; margin-bottom: 2px; }
    .pie-legend-value { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1.05; }
    .pie-legend-value .pie-legend-pct { font-size: 14px; font-weight: 700; color: #94a3b8; margin-left: 8px; }

    /* Kurslar bar chart */
    .course-bar-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 22px 24px;
        box-shadow: 0 4px 14px -4px rgba(15, 23, 42, .12);
        margin-top: 22px;
    }
    .course-bar-card h3 { font-size: 19px; font-weight: 700; color: #0f172a; margin-bottom: 16px; }
    .course-bar-wrap { position: relative; height: 420px; }

    /* Yarim kenglikdagi kartalar uchun 2-ustunli grid */
    .half-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 22px;
    }
    @media (max-width: 900px) {
        .half-grid { grid-template-columns: 1fr; }
    }
    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 22px 24px;
        box-shadow: 0 4px 14px -4px rgba(15, 23, 42, .12);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px) scale(1.015);
        box-shadow: 0 14px 30px -8px rgba(15, 23, 42, .22);
    }
    .stat-card h3 { font-size: 19px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
    .stat-card-kpis { display: flex; gap: 34px; margin-bottom: 8px; }
    .stat-card-kpis .lbl { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 2px; }
    .stat-card-kpis .val { font-size: 30px; font-weight: 800; color: #0f172a; line-height: 1.05; }
    .stat-card-kpis .pct { font-size: 13px; font-weight: 700; color: #94a3b8; }
    .social-bar-wrap { position: relative; height: 300px; margin-top: 10px; }

    .stat-card-empty {
        display: flex; align-items: center; justify-content: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        color: #94a3b8; font-size: 13px;
        min-height: 200px;
    }
</style>

<div class="w-full px-4 py-6"
     x-data="{
        outer: 'talabalar',
        inner: { talabalar: 'umumiy' }
     }"
     x-init="
        $watch('outer', () => $nextTick(() => { window.statsAnimateVisible(); window.statsRenderCharts && window.statsRenderCharts(); }));
        $watch('inner.talabalar', () => $nextTick(() => { window.statsAnimateVisible(); window.statsRenderCharts && window.statsRenderCharts(); }));
        $nextTick(() => window.statsAnimateVisible());
     ">

    {{-- ───── Outer tabs ───── --}}
    <div class="stats-outer-tabs">
        <button type="button" @click="outer = 'talabalar'"
                :class="outer === 'talabalar' ? 'active' : ''"
                class="stats-outer-btn">
            Talabalar
        </button>
        <button type="button" @click="outer = 'oqituvchilar'"
                :class="outer === 'oqituvchilar' ? 'active' : ''"
                class="stats-outer-btn">
            Professor - o'qituvchilar
        </button>
        <button type="button" @click="outer = 'yonalishlar'"
                :class="outer === 'yonalishlar' ? 'active' : ''"
                class="stats-outer-btn">
            Yo'nalishlar bo'yicha jadvallar
        </button>
    </div>

    {{-- ───── Talabalar (outer) ───── --}}
    <div x-show="outer === 'talabalar'" class="stats-card">
        @php
            $innerTabs = [
                'umumiy'          => 'Umumiy',
                'talim_turi'      => "Ta'lim turi",
                'ijtimoiy_toifa'  => 'Ijtimoiy toifa',
                'tolov_shakli'    => "To'lov shakli",
                'fuqaroligi'      => 'Fuqaroligi',
                'kurslar'         => 'Kurslar',
                'yoshi'           => 'Yoshi',
                'yashash_joyi'    => 'Yashash joyi',
                'hududlar'        => 'Hududlar kesimida',
            ];

            // Pie chart qiymatlari — bir nechta tabda ishlatilgani uchun yuqorida
            $younger  = (int) ($ageStats['younger'] ?? 0);
            $older    = (int) ($ageStats['older'] ?? 0);
            $ageTotal = $younger + $older;
            $grant    = (int) ($payStats['grant'] ?? 0);
            $contract = (int) ($payStats['contract'] ?? 0);
            $payTotal = $grant + $contract;

            // Legend ikonkalari
            $legendIcons = [
                'young'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                'old'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                'grant'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m-6-3v-3"/>',
                'contract' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h2m4 0h4M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>',
            ];

            // Ijtimoiy toifalar — gorizontal bar chart uchun
            $socialStats = $socialStats ?? [];
            $socialHasCategory = (int) ($socialHasCategory ?? 0);
            $totalActive = (int) ($stats['total']['total'] ?? 0);
            $socialPct = $totalActive > 0 ? round($socialHasCategory * 100 / $totalActive, 1) : 0;
            $socialColors = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#a855f7', '#ec4899', '#14b8a6', '#f97316'];
            $socialJson = json_encode([
                'labels' => array_keys($socialStats),
                'data'   => array_values($socialStats),
                'colors' => $socialColors,
            ]);
        @endphp

        <div class="stats-inner-tabs">
            @foreach($innerTabs as $key => $label)
                <button type="button" @click="inner.talabalar = '{{ $key }}'"
                        :class="inner.talabalar === '{{ $key }}' ? 'active' : ''"
                        class="stats-inner-btn">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Umumiy --}}
        <div x-show="inner.talabalar === 'umumiy'" x-cloak>
            @php
                $cards = [
                    ['key' => 'bakalavr',   'title' => 'Bakalavr',       'icon' => 'cap'],
                    ['key' => 'magistr',    'title' => 'Magistratura',   'icon' => 'note'],
                    ['key' => 'ordinatura', 'title' => 'Ordinatura',     'icon' => 'bank'],
                    ['key' => 'total',      'title' => 'Jami talabalar', 'icon' => 'columns'],
                ];
                $icons = [
                    'cap'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M22 10v6M2 10l10-5 10 5-10 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 12v5c3 2 9 2 12 0v-5"/>',
                    'note'    => '<rect x="4" y="4" width="16" height="16" rx="2" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M8 10h8M8 14h5"/>',
                    'bank'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10l9-6 9 6M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18"/>',
                    'columns' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 21V8m6 13V4m6 17v-9m4 9V11"/>',
                ];
            @endphp
            <div class="kpi-grid">
                @foreach($cards as $c)
                    @php
                        $st = $stats[$c['key']] ?? ['total' => 0, 'male' => 0, 'female' => 0];
                        $total = (int) $st['total'];
                        $m = (int) $st['male'];
                        $f = (int) $st['female'];
                        $mp = $total > 0 ? round($m * 100 / $total, 2) : 0;
                        $fp = $total > 0 ? round($f * 100 / $total, 2) : 0;
                    @endphp
                    <div class="kpi-card">
                        <div class="kpi-head">
                            <span class="kpi-icon">
                                <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    {!! $icons[$c['icon']] !!}
                                </svg>
                            </span>
                            <div class="kpi-headtext">
                                <span class="kpi-title">{{ $c['title'] }}</span>
                                <span class="kpi-sub">Umumiy</span>
                            </div>
                            <div class="kpi-total" data-count="{{ $total }}">{{ number_format($total, 0, '.', ' ') }}</div>
                        </div>
                        <div class="kpi-split">
                            <div>
                                <span class="lbl">Erkaklar</span>
                                <span class="val" data-count="{{ $m }}">{{ number_format($m, 0, '.', ' ') }}</span>
                                <span class="pct pct-m" data-count="{{ $mp }}" data-count-decimals="2">{{ number_format($mp, 2) }}%</span>
                            </div>
                            <div>
                                <span class="lbl">Ayollar</span>
                                <span class="val" data-count="{{ $f }}">{{ number_format($f, 0, '.', ' ') }}</span>
                                <span class="pct pct-f" data-count="{{ $fp }}" data-count-decimals="2">{{ number_format($fp, 2) }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pie chartlar — Yoshi va To'lov shakli kesimi --}}
            <div class="pie-grid">
                <div class="pie-card">
                    <h3>Yoshi</h3>
                    <div class="pie-body">
                        <div class="pie-canvas-wrap">
                            <canvas id="ageChart"
                                    data-younger="{{ $younger }}"
                                    data-older="{{ $older }}"></canvas>
                        </div>
                        <div class="pie-legend">
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#6366f1">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['young'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">30 yoshdan kichiklar</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $younger }}">{{ number_format($younger, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $ageTotal > 0 ? number_format($younger * 100 / $ageTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#22c55e">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['old'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">30 yoshdan oshganlar</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $older }}">{{ number_format($older, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $ageTotal > 0 ? number_format($older * 100 / $ageTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pie-card">
                    <h3>To'lov shakli</h3>
                    <div class="pie-body">
                        <div class="pie-canvas-wrap">
                            <canvas id="payChart"
                                    data-grant="{{ $grant }}"
                                    data-contract="{{ $contract }}"></canvas>
                        </div>
                        <div class="pie-legend">
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#a855f7">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['grant'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">Davlat granti</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $grant }}">{{ number_format($grant, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $payTotal > 0 ? number_format($grant * 100 / $payTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#f43f5e">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['contract'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">To'lov-kontrakt</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $contract }}">{{ number_format($contract, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $payTotal > 0 ? number_format($contract * 100 / $payTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Yarim kenglikdagi kartalar: Kurslar + Ijtimoiy toifalar --}}
            <div class="half-grid">
                <div class="stat-card">
                    <h3>Kurslar bo'yicha taqsimot</h3>
                    <div class="social-bar-wrap" style="height:340px;">
                        <canvas id="courseChartUmumiy"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Ijtimoiy toifalar</h3>
                    <div class="stat-card-kpis">
                        <div>
                            <span class="lbl">Jami talabalar</span>
                            <span class="val" data-count="{{ $totalActive }}">{{ number_format($totalActive, 0, '.', ' ') }}</span>
                        </div>
                        <div>
                            <span class="lbl">Ijtimoiy toifasi bor (Boshqa'siz)</span>
                            <span class="val" data-count="{{ $socialHasCategory }}">{{ number_format($socialHasCategory, 0, '.', ' ') }}</span>
                            <div class="pct">{{ $socialPct }}%</div>
                        </div>
                    </div>
                    <div class="social-bar-wrap">
                        <canvas id="socialChart"></canvas>
                    </div>
                </div>
            </div>

            <script type="application/json" id="socialChartData">{!! $socialJson !!}</script>
        </div>

        {{-- Yoshi tabi — pie chart --}}
        <div x-show="inner.talabalar === 'yoshi'" x-cloak>
            <div class="pie-grid">
                <div class="pie-card">
                    <h3>Yoshi bo'yicha taqsimot</h3>
                    <div class="pie-body">
                        <div class="pie-canvas-wrap">
                            <canvas id="ageChartTab"
                                    data-younger="{{ $younger }}"
                                    data-older="{{ $older }}"></canvas>
                        </div>
                        <div class="pie-legend">
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#6366f1">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['young'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">30 yoshdan kichiklar</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $younger }}">{{ number_format($younger, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $ageTotal > 0 ? number_format($younger * 100 / $ageTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                            <div class="pie-legend-item">
                                <span class="pie-legend-icon" style="background:#22c55e">
                                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $legendIcons['old'] !!}</svg>
                                </span>
                                <span class="pie-legend-text">
                                    <span class="pie-legend-label">30 yoshdan oshganlar</span>
                                    <span class="pie-legend-value">
                                        <span data-count="{{ $older }}">{{ number_format($older, 0, '.', ' ') }}</span>
                                        <span class="pie-legend-pct">{{ $ageTotal > 0 ? number_format($older * 100 / $ageTotal, 1) : 0 }}%</span>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kurslar tabi — KPI raqamlar + stacked bar chart --}}
        <div x-show="inner.talabalar === 'kurslar'" x-cloak>
            @php
                // KPI kartalar uchun — 1..6 kurs (mavjud bo'lganlari)
                $allLevels = array_keys($courseTotals);
                sort($allLevels);
                // Bar chart datasi: ta'lim turi × kurs
                $eduLabels = ['bakalavr' => 'Bakalavr', 'magistr' => 'Magistratura', 'ordinatura' => 'Ordinatura'];
                $courseColors = [
                    1 => '#3b82f6', 2 => '#ec4899', 3 => '#f59e0b',
                    4 => '#22c55e', 5 => '#60a5fa', 6 => '#f97316',
                    7 => '#a855f7', 8 => '#14b8a6',
                ];
                // chartData[eduKey][level] = full breakdown
                $courseChartData = [];
                foreach ($eduLabels as $eduKey => $_) {
                    foreach ($allLevels as $lvl) {
                        $cs = $courseStats[$eduKey][$lvl] ?? null;
                        $courseChartData[$eduKey][$lvl] = $cs
                            ? ['total' => $cs['total'], 'male' => $cs['male'], 'female' => $cs['female'], 'semesters' => $cs['semesters']]
                            : ['total' => 0, 'male' => 0, 'female' => 0, 'semesters' => []];
                    }
                }

                // Chart konfiguratsiyasini JSON sifatida tayyorlaymiz — @json
                // direktivasi ko'p qatorli massivni xato o'qigani uchun @php da.
                $courseChartJson = json_encode([
                    'levels'    => $allLevels,
                    'eduKeys'   => array_keys($eduLabels),
                    'eduLabels' => array_values($eduLabels),
                    'colors'    => $courseColors,
                    'data'      => $courseChartData,
                ]);
            @endphp

            {{-- KPI raqamlar --}}
            <div class="course-bar-card" style="margin-top:0">
                <h3>Kurslar</h3>
                <div style="display:flex; flex-wrap:wrap; gap:36px;">
                    @foreach($allLevels as $lvl)
                        <div>
                            <div style="font-size:14px; color:#94a3b8; margin-bottom:2px;">{{ $lvl }}-kurs</div>
                            <div style="font-size:32px; font-weight:800; color:#0f172a;"
                                 data-count="{{ (int) $courseTotals[$lvl] }}">{{ number_format($courseTotals[$lvl], 0, '.', ' ') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Stacked bar chart --}}
            <div class="course-bar-card">
                <h3>Ta'lim turi bo'yicha kurslar taqsimoti</h3>
                <div class="course-bar-wrap">
                    <canvas id="courseChart"></canvas>
                </div>
            </div>

            <script type="application/json" id="courseChartData">{!! $courseChartJson !!}</script>
        </div>

        {{-- Ijtimoiy toifa tabi — to'liq kenglikdagi gorizontal bar chart --}}
        <div x-show="inner.talabalar === 'ijtimoiy_toifa'" x-cloak>
            <div class="course-bar-card" style="margin-top:0">
                <h3>Ijtimoiy toifalar</h3>
                <div class="stat-card-kpis">
                    <div>
                        <span class="lbl">Jami talabalar</span>
                        <span class="val" data-count="{{ $totalActive }}">{{ number_format($totalActive, 0, '.', ' ') }}</span>
                    </div>
                    <div>
                        <span class="lbl">Ijtimoiy toifasi bor (Boshqa'siz)</span>
                        <span class="val" data-count="{{ $socialHasCategory }}">{{ number_format($socialHasCategory, 0, '.', ' ') }}</span>
                        <div class="pct">{{ $socialPct }}%</div>
                    </div>
                </div>
                <div style="position:relative; height:420px; margin-top:10px;">
                    <canvas id="socialChartTab"></canvas>
                </div>
            </div>
        </div>

        {{-- Boshqa inner tablar (hozircha bo'sh) --}}
        @foreach($innerTabs as $key => $label)
            @if(in_array($key, ['umumiy', 'yoshi', 'kurslar', 'ijtimoiy_toifa'])) @continue @endif
            <div x-show="inner.talabalar === '{{ $key }}'" x-cloak>
                <div class="stats-empty">
                    <strong>{{ $label }}</strong> — statistika hali tayyor emas.
                </div>
            </div>
        @endforeach
    </div>

    {{-- ───── Professor - o'qituvchilar ───── --}}
    <div x-show="outer === 'oqituvchilar'" x-cloak class="stats-card">
        <div class="stats-empty">
            <strong>Professor - o'qituvchilar</strong> — statistika hali tayyor emas.
        </div>
    </div>

    {{-- ───── Yo'nalishlar bo'yicha jadvallar ───── --}}
    <div x-show="outer === 'yonalishlar'" x-cloak class="stats-card">
        <div class="stats-empty">
            <strong>Yo'nalishlar bo'yicha jadvallar</strong> — statistika hali tayyor emas.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const DURATION = 900;            // ms
    const fmtInt = (n) => Math.round(n).toLocaleString('uz-UZ').replace(/,/g, ' ');
    const fmtFloat = (n, d) => n.toFixed(d).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    function animate(el) {
        const target = parseFloat((el.dataset.count || '0').toString().replace(/\s/g, ''));
        const decimals = parseInt(el.dataset.countDecimals || '0', 10);
        const isPct = el.classList.contains('pct');
        const suffix = isPct ? '%' : '';
        if (!isFinite(target)) return;

        const start = performance.now();
        function tick(now) {
            const p = Math.min(1, (now - start) / DURATION);
            const eased = 1 - Math.pow(1 - p, 3);
            const v = target * eased;
            el.textContent = (decimals > 0 ? fmtFloat(v, decimals) : fmtInt(v)) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    window.statsAnimateVisible = function () {
        document.querySelectorAll('[data-count]').forEach(el => {
            // Faqat hozirda ko'rinayotgan elementlarni animatsiya qilamiz
            // (boshqa tablardagilar offsetParent=null bo'ladi).
            if (el.offsetParent !== null) animate(el);
        });
    };

    // ─── Pie chartlar ──────────────────────────────────────────────────
    const pieOpts = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%',
        // Aylanish + masshtab animatsiyasi — har render qilinganda 0 dan ochiladi
        animation: {
            animateRotate: true,
            animateScale: true,
            duration: 1100,
            easing: 'easeOutCubic',
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const v = ctx.parsed;
                        const pct = total > 0 ? (v * 100 / total).toFixed(2) : 0;
                        return ` ${ctx.label}: ${v.toLocaleString('uz-UZ').replace(/,/g, ' ')} (${pct}%)`;
                    }
                }
            }
        }
    };

    function makePie(canvas, labels, data, colors) {
        if (!canvas) return;
        // Avval render qilingan bo'lsa — eski chartni buzib, qayta animatsiya bilan chizamiz
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
            },
            options: pieOpts
        });
    }

    function renderAge(id) {
        const c = document.getElementById(id);
        if (!c) return;
        makePie(c,
            ["30 yoshdan kichiklar", "30 yoshdan oshganlar"],
            [parseInt(c.dataset.younger || '0', 10), parseInt(c.dataset.older || '0', 10)],
            ['#6366f1', '#22c55e']);
    }
    function renderPay(id) {
        const c = document.getElementById(id);
        if (!c) return;
        makePie(c,
            ["Davlat granti", "To'lov-kontrakt"],
            [parseInt(c.dataset.grant || '0', 10), parseInt(c.dataset.contract || '0', 10)],
            ['#a855f7', '#f43f5e']);
    }

    // ─── Kurslar stacked bar chart ─────────────────────────────────────
    let courseChartCfg = null;
    function getCourseCfg() {
        if (courseChartCfg !== null) return courseChartCfg;
        const el = document.getElementById('courseChartData');
        if (!el) { courseChartCfg = false; return false; }
        try { courseChartCfg = JSON.parse(el.textContent); }
        catch (e) { courseChartCfg = false; }
        return courseChartCfg;
    }

    function renderCourseChart(id) {
        const canvas = document.getElementById(id || 'courseChart');
        if (!canvas) return;
        const cfg = getCourseCfg();
        if (!cfg) return;
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        const fmt = (n) => Number(n).toLocaleString('uz-UZ').replace(/,/g, ' ');

        // Har kurs (level) uchun bitta dataset — stacked
        const datasets = cfg.levels.map(lvl => ({
            label: lvl + '-kurs',
            data: cfg.eduKeys.map(ek => (cfg.data[ek] && cfg.data[ek][lvl] ? cfg.data[ek][lvl].total : 0)),
            backgroundColor: cfg.colors[lvl] || '#94a3b8',
            borderWidth: 1,
            borderColor: '#fff',
            borderRadius: 4,
        }));

        new Chart(canvas, {
            type: 'bar',
            data: { labels: cfg.eduLabels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1000, easing: 'easeOutCubic' },
                interaction: { mode: 'nearest', intersect: true },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => fmt(v) } },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 14, boxWidth: 14 } },
                    tooltip: {
                        // Rasmdagi kabi boy tooltip: kurs -> Erkak/Ayol -> semestrlar
                        callbacks: {
                            title: (items) => items.length ? items[0].label : '',
                            label: (ctx) => {
                                const ek = cfg.eduKeys[ctx.dataIndex];
                                const lvl = cfg.levels[ctx.datasetIndex];
                                const d = cfg.data[ek] && cfg.data[ek][lvl] ? cfg.data[ek][lvl] : null;
                                if (!d || !d.total) return null;
                                const lines = [];
                                lines.push(lvl + '-kurs: ' + fmt(d.total));
                                lines.push('   Erkak: ' + fmt(d.male) + ' · Ayol: ' + fmt(d.female));
                                const sems = d.semesters || {};
                                const semParts = Object.keys(sems)
                                    .sort((a, b) => Number(a) - Number(b))
                                    .map(s => s + '-semestr: ' + fmt(sems[s]));
                                if (semParts.length) {
                                    // 2 tadan bo'lib yangi qatorlarga
                                    for (let i = 0; i < semParts.length; i += 2) {
                                        lines.push('   ' + semParts.slice(i, i + 2).join(' · '));
                                    }
                                }
                                return lines;
                            },
                        }
                    }
                }
            }
        });
    }

    // ─── Ijtimoiy toifalar — gorizontal bar chart ──────────────────────
    let socialCfg = null;
    function getSocialCfg() {
        if (socialCfg !== null) return socialCfg;
        const el = document.getElementById('socialChartData');
        if (!el) { socialCfg = false; return false; }
        try { socialCfg = JSON.parse(el.textContent); }
        catch (e) { socialCfg = false; }
        return socialCfg;
    }

    function renderSocialChart(id) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const cfg = getSocialCfg();
        if (!cfg) return;
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        const fmt = (n) => Number(n).toLocaleString('uz-UZ').replace(/,/g, ' ');
        const colors = cfg.labels.map((_, i) => cfg.colors[i % cfg.colors.length]);

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: cfg.labels,
                datasets: [{
                    label: 'Talabalar soni',
                    data: cfg.data,
                    backgroundColor: colors,
                    borderRadius: 5,
                    borderWidth: 0,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1000, easing: 'easeOutCubic' },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: (v) => fmt(v) }, title: { display: true, text: 'Talabalar soni' } },
                    y: { grid: { display: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + fmt(ctx.parsed.x) + ' talaba',
                        }
                    }
                }
            }
        });
    }

    // Chart.js canvas yashirin (display:none) bo'lsa o'lcham 0 bo'lib chiqadi —
    // shuning uchun tab ko'ringanda render qilamiz.
    window.statsRenderCharts = function () {
        renderAge('ageChart');
        renderPay('payChart');
        renderAge('ageChartTab');
        renderCourseChart('courseChart');
        renderCourseChart('courseChartUmumiy');
        renderSocialChart('socialChart');
        renderSocialChart('socialChartTab');
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Boshlang'ich ko'rinadigan (Umumiy) chartlar
        setTimeout(window.statsRenderCharts, 50);
    });
})();
</script>
</x-app-layout>
