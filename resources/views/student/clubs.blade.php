<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            To'garaklar
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 py-4">
        <div class="text-center mb-5">
            <h1 class="text-base font-bold text-gray-800 uppercase leading-snug">
                ToshDTU Termiz Filiali<br>
                2025-2026 o'quv yili to'garaklar jadvali
            </h1>
        </div>

        @php
            $sections = [
                [
                    'title' => "O'zbek va xorijiy tillar kafedrasi",
                    'color' => 'indigo',
                    'clubs' => [
                        ['name' => '"Yosh tilshunoslar"', 'place' => '1-o\'quv bino, 412-xona', 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                        ['name' => '"Русское слово"', 'place' => '1-o\'quv bino, 332-xona', 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                        ['name' => '"We learn English"', 'place' => '1-o\'quv bino, 411-xona', 'day' => 'Juma', 'time' => '15:00-16:00'],
                        ['name' => '"English atmosphere"', 'place' => '1-o\'quv bino, 406-xona', 'day' => 'Chorshanba', 'time' => '15:00-16:00'],
                        ['name' => '"Medicus"', 'place' => '1-o\'quv bino, 334-xona', 'day' => 'Juma', 'time' => '15:00-16:00'],
                    ]
                ],
                [
                    'title' => 'Travmatologiya-ortopediya, harbiy dala jarrohligi, neyrojarrohllik, anesteziologiya va tex tibbiy yordam kafedrasi',
                    'color' => 'red',
                    'clubs' => [
                        ['name' => 'Yosh Travmatolog-ortoped', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Shanba', 'time' => '14:30-16:30'],
                    ]
                ],
                [
                    'title' => 'Otorinolaringologiya, oftasmologiya, onkologiya va tibbiy radiologiya kafedrasi',
                    'color' => 'purple',
                    'clubs' => [
                        ['name' => 'Yosh onkologlar', 'place' => 'Viloyat Onkologiya shifoxonasi', 'day' => 'Chorshanba, Shanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => 'Normal va patologik fiziologiya kafedrasi',
                    'color' => 'teal',
                    'clubs' => [
                        ['name' => 'Tibbiyot falsafasi', 'place' => 'Asosiy o\'quv bino, 5-qavat', 'day' => 'Chorshanba, Juma', 'time' => '16:30-17:30'],
                        ['name' => 'Yosh fiziologlar', 'place' => 'Asosiy o\'quv bino, 5-qavat', 'day' => 'Chorshanba, Shanba', 'time' => '16:30-17:30'],
                    ]
                ],
            ];
        @endphp

        @foreach($sections as $section)
            <div class="mb-5">
                <div class="bg-{{ $section['color'] }}-50 rounded-t-xl px-3 py-2 border border-b-0 border-{{ $section['color'] }}-200">
                    <h2 class="text-xs font-bold text-{{ $section['color'] }}-800 text-center leading-snug">{{ $section['title'] }}</h2>
                </div>
                <div class="border border-t-0 border-gray-200 rounded-b-xl divide-y divide-gray-100">
                    @foreach($section['clubs'] as $i => $club)
                        <div class="px-3 py-3 {{ $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} {{ $loop->last ? 'rounded-b-xl' : '' }}">
                            <div class="font-semibold text-sm text-gray-800">{{ $i + 1 }}. {{ $club['name'] }}</div>
                            <div class="mt-1.5 flex flex-col gap-1 text-xs text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 0115 0z"/></svg>
                                    <span>{{ $club['place'] }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                    <span>{{ $club['day'] }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ $club['time'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-student-app-layout>
