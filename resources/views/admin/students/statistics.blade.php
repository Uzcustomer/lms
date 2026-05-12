<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Talabalar statistikasi
    </h2>
</x-slot>

<div class="container mx-auto px-4 py-6"
     x-data="{
        outer: 'talabalar',
        inner: {
            talabalar: 'talim_turi',
        }
     }">

    {{-- ───── Outer tabs ───── --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" @click="outer = 'talabalar'"
                :class="outer === 'talabalar'
                    ? 'bg-indigo-500 text-white border-indigo-500'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                class="px-5 py-2.5 rounded-lg border text-sm font-semibold transition">
            Talabalar
        </button>
        <button type="button" @click="outer = 'oqituvchilar'"
                :class="outer === 'oqituvchilar'
                    ? 'bg-indigo-500 text-white border-indigo-500'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                class="px-5 py-2.5 rounded-lg border text-sm font-semibold transition">
            Professor - o'qituvchilar
        </button>
        <button type="button" @click="outer = 'yonalishlar'"
                :class="outer === 'yonalishlar'
                    ? 'bg-indigo-500 text-white border-indigo-500'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                class="px-5 py-2.5 rounded-lg border text-sm font-semibold transition">
            Yo'nalishlar bo'yicha jadvallar
        </button>
    </div>

    {{-- ───── Talabalar (outer) ───── --}}
    <div x-show="outer === 'talabalar'" class="bg-white rounded-lg border p-4">

        {{-- Inner tabs --}}
        <div class="flex flex-wrap gap-2 mb-5">
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
            @foreach($innerTabs as $key => $label)
                <button type="button" @click="inner.talabalar = '{{ $key }}'"
                        :class="inner.talabalar === '{{ $key }}'
                            ? 'bg-indigo-500 text-white border-indigo-500'
                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        class="px-3 py-1.5 rounded-md border text-xs font-medium transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Inner content (har biri keyin to'ldiriladi) --}}
        @foreach($innerTabs as $key => $label)
            <div x-show="inner.talabalar === '{{ $key }}'" x-cloak>
                <div class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-400">
                    <span class="font-semibold text-gray-500">{{ $label }}</span> — statistika hali tayyor emas.
                </div>
            </div>
        @endforeach
    </div>

    {{-- ───── Professor - o'qituvchilar ───── --}}
    <div x-show="outer === 'oqituvchilar'" x-cloak class="bg-white rounded-lg border p-4">
        <div class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-400">
            <span class="font-semibold text-gray-500">Professor - o'qituvchilar</span> — statistika hali tayyor emas.
        </div>
    </div>

    {{-- ───── Yo'nalishlar bo'yicha jadvallar ───── --}}
    <div x-show="outer === 'yonalishlar'" x-cloak class="bg-white rounded-lg border p-4">
        <div class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-400">
            <span class="font-semibold text-gray-500">Yo'nalishlar bo'yicha jadvallar</span> — statistika hali tayyor emas.
        </div>
    </div>
</div>

<style>[x-cloak]{display:none !important;}</style>
</x-app-layout>
