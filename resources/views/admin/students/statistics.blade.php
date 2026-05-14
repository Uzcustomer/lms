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
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 18px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px 26px 26px;
        box-shadow: 0 4px 14px -4px rgba(15, 23, 42, .12);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .kpi-card:hover {
        transform: translateY(-4px) scale(1.025);
        box-shadow: 0 14px 30px -8px rgba(15, 23, 42, .22);
    }
    .kpi-icon {
        width: 56px; height: 56px;
        border-radius: 14px;
        background: #eef2ff;
        color: #4f46e5;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 18px;
    }
    .kpi-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .kpi-sub   { font-size: 14px; color: #94a3b8; margin-bottom: 12px; }
    .kpi-total { font-size: 44px; font-weight: 800; color: #0f172a; line-height: 1.05; }
    .kpi-split { display: flex; gap: 36px; margin-top: 20px; }
    .kpi-split .lbl { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 3px; }
    .kpi-split .val { font-size: 24px; font-weight: 700; color: #0f172a; }
    .kpi-split .pct { font-size: 14px; font-weight: 700; margin-left: 5px; }
    .pct-m { color: #059669; }
    .pct-f { color: #dc2626; }

    .pie-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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
    .pie-canvas-wrap { position: relative; height: 260px; }
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
                        <span class="kpi-icon">
                            <svg width="30" height="30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {!! $icons[$c['icon']] !!}
                            </svg>
                        </span>
                        <div class="kpi-title">{{ $c['title'] }}</div>
                        <div class="kpi-sub">Umumiy</div>
                        <div class="kpi-total" data-count="{{ $total }}">{{ number_format($total, 0, '.', ' ') }}</div>
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
            @php
                $younger = (int) ($ageStats['younger'] ?? 0);
                $older   = (int) ($ageStats['older'] ?? 0);
                $grant   = (int) ($payStats['grant'] ?? 0);
                $contract = (int) ($payStats['contract'] ?? 0);
            @endphp
            <div class="pie-grid">
                <div class="pie-card">
                    <h3>Yoshi</h3>
                    <div class="pie-canvas-wrap">
                        <canvas id="ageChart"
                                data-younger="{{ $younger }}"
                                data-older="{{ $older }}"></canvas>
                    </div>
                </div>
                <div class="pie-card">
                    <h3>To'lov shakli</h3>
                    <div class="pie-canvas-wrap">
                        <canvas id="payChart"
                                data-grant="{{ $grant }}"
                                data-contract="{{ $contract }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Yoshi tabi — pie chart --}}
        <div x-show="inner.talabalar === 'yoshi'" x-cloak>
            <div class="pie-grid">
                <div class="pie-card">
                    <h3>Yoshi bo'yicha taqsimot</h3>
                    <div class="pie-canvas-wrap">
                        <canvas id="ageChartTab"
                                data-younger="{{ (int) ($ageStats['younger'] ?? 0) }}"
                                data-older="{{ (int) ($ageStats['older'] ?? 0) }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Boshqa inner tablar (hozircha bo'sh) --}}
        @foreach($innerTabs as $key => $label)
            @if(in_array($key, ['umumiy', 'yoshi'])) @continue @endif
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
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 13 }, padding: 16 } },
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
        if (!canvas || canvas.dataset.rendered === '1') return;
        canvas.dataset.rendered = '1';
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
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

    // Chart.js canvas yashirin (display:none) bo'lsa o'lcham 0 bo'lib chiqadi —
    // shuning uchun tab ko'ringanda render qilamiz.
    window.statsRenderCharts = function () {
        renderAge('ageChart');
        renderPay('payChart');
        renderAge('ageChartTab');
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Boshlang'ich ko'rinadigan (Umumiy) chartlar
        setTimeout(window.statsRenderCharts, 50);
    });
})();
</script>
</x-app-layout>
