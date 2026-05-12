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
        border-color: #4f46e5;
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
        border-color: #4338ca;
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
</style>

<div class="container mx-auto px-4 py-6"
     x-data="{
        outer: 'talabalar',
        inner: { talabalar: 'talim_turi' }
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

        @foreach($innerTabs as $key => $label)
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
