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
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px 20px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .kpi-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        background: #f1f5f9;
        color: #475569;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 14px;
    }
    .kpi-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .kpi-sub   { font-size: 12px; color: #94a3b8; margin-bottom: 10px; }
    .kpi-total { font-size: 30px; font-weight: 800; color: #0f172a; line-height: 1.1; }
    .kpi-split { display: flex; gap: 28px; margin-top: 16px; }
    .kpi-split .lbl { display: block; font-size: 11px; color: #94a3b8; margin-bottom: 2px; }
    .kpi-split .val { font-size: 16px; font-weight: 700; color: #0f172a; }
    .kpi-split .pct { font-size: 12px; font-weight: 700; margin-left: 4px; }
    .pct-m { color: #059669; }
    .pct-f { color: #dc2626; }
</style>

<div class="w-full px-4 py-6"
     x-data="{
        outer: 'talabalar',
        inner: { talabalar: 'umumiy' }
     }">

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
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {!! $icons[$c['icon']] !!}
                            </svg>
                        </span>
                        <div class="kpi-title">{{ $c['title'] }}</div>
                        <div class="kpi-sub">Umumiy</div>
                        <div class="kpi-total">{{ number_format($total, 0, '.', ' ') }}</div>
                        <div class="kpi-split">
                            <div>
                                <span class="lbl">Erkaklar</span>
                                <span class="val">{{ number_format($m, 0, '.', ' ') }}</span>
                                <span class="pct pct-m">{{ number_format($mp, 2) }}%</span>
                            </div>
                            <div>
                                <span class="lbl">Ayollar</span>
                                <span class="val">{{ number_format($f, 0, '.', ' ') }}</span>
                                <span class="pct pct-f">{{ number_format($fp, 2) }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Boshqa inner tablar (hozircha bo'sh) --}}
        @foreach($innerTabs as $key => $label)
            @if($key === 'umumiy') @continue @endif
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
</x-app-layout>
