<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            To'garaklar
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 py-4">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Men a'zo bo'lgan to'garaklar --}}
        @if($myMemberships->count() > 0)
        <div class="mb-6">
            <h2 class="text-sm font-bold text-gray-800 mb-3">Men a'zo bo'lgan to'garaklar</h2>
            <div class="space-y-2">
                @foreach($myMemberships as $m)
                    <div class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-3 py-2.5">
                        <div>
                            <div class="font-semibold text-xs text-gray-800">{{ $m->club_name }}</div>
                            <div class="text-[11px] text-gray-500">{{ $m->kafedra_name }}</div>
                        </div>
                        @if($m->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700">Ariza yuborildi</span>
                        @elseif($m->status === 'approved')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">Tasdiqlangan</span>
                        @elseif($m->status === 'rejected')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-red-100 text-red-700" title="{{ $m->reject_reason }}">Rad etilgan</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="text-center mb-5">
            <h1 class="text-xs font-bold text-gray-800 uppercase leading-snug">
                Toshkent Davlat Tibbiyot Universiteti Termiz Filialida<br>
                2025-2026 o'quv yilida tashkil etilgan to'garaklar to'g'risida ma'lumot
            </h1>
        </div>

        @php
            $sections = [
                [
                    'title' => "O'zbek va xorijiy tillar kafedrasi faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => '"Yosh tilshunoslar"', 'place' => '1-o\'quv bino, 412-xona', 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                        ['name' => '"Русское слово"', 'place' => '1-o\'quv bino, 332-xona', 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                        ['name' => '"We learn English"', 'place' => '1-o\'quv bino, 411-xona', 'day' => 'Juma', 'time' => '15:00-16:00'],
                        ['name' => '"English atmosphere"', 'place' => '1-o\'quv bino, 406-xona', 'day' => 'Chorshanba', 'time' => '15:00-16:00'],
                        ['name' => '"Medicus"', 'place' => '1-o\'quv bino, 334-xona', 'day' => 'Juma', 'time' => '15:00-16:00'],
                    ]
                ],
                [
                    'title' => "Travmatologiya-ortopediya, harbiy dala jarrohligi, neyrojarrohllik, anesteziologiya va tex tibbiy yordam kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh Travmatolog-ortoped', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Shanba', 'time' => '14:30-16:30'],
                    ]
                ],
                [
                    'title' => "Otorinolaringologiya, oftasmologiya, onkologiya va tibbiy radiologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh onkologlar', 'place' => 'Viloyat Onkologiya shifoxonasi', 'day' => 'Chorshanba, Shanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Normal va patologik fiziologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Tibbiyot falsafasi', 'place' => 'Asosiy o\'quv bino, 5-qavat', 'day' => 'Chorshanba, Juma', 'time' => '16:30-17:30'],
                        ['name' => 'Yosh fiziologlar', 'place' => 'Asosiy o\'quv bino, 5-qavat', 'day' => 'Chorshanba, Shanba', 'time' => '16:30-17:30'],
                    ]
                ],
                [
                    'title' => "Mikrobiologiya jamoat salomatligi gigiyena va menejment kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => '"Yosh mikrobiologlar"', 'place' => 'Asosiy o\'quv bino, 4-qavat 408-xona', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh gigiyenistlar', 'place' => '1-o\'quv bino, 308-xona', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Ichki kasalliklar, HDT, gematologiya va oilaviy shifokorlikda terapiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Oilaviy hifokorlikda terapiya', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh allergologlar', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh revmatologlar', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Juma', 'time' => '15:00-17:00'],
                        ['name' => 'Harbiy terapevtlar', 'place' => '4-oilaviy poliklinika', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh terapevtlar', 'place' => '4-oilaviy poliklinika', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Kardiologlar avlodi', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh kardiologlar', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Juma', 'time' => '15:00-17:00'],
                        ['name' => 'Tibbiyotda zamonaviy diagnostika', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                        ['name' => '"SMART DOCTOR" ichki kasalliklar klubi', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi, 8-o\'quv xona', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Kardiologiya', 'place' => 'RShTTYoIM Surxondaryo filiali', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Farmakologiya va klinik farmakologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Mediator', 'place' => '1-o\'quv binosi, 333-xona', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh tabobatchi', 'place' => '1-o\'quv binosi, 326-xona', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Bolalar kasalliklari propedevtikasi, bolalar kasalliklari va oilaviy shifokorlikda pediatriya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh pediator', 'place' => 'Viloyat bolalar ko\'p tarmoqli tibbiyot markazi', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                        ['name' => 'Pediatriya bilimdonlari', 'place' => 'Viloyat bolalar ko\'p tarmoqli tibbiyot markazi', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '15:00-17:00'],
                        ['name' => 'Ustoz va shogird', 'place' => 'Viloyat bolalar ko\'p tarmoqli tibbiyot markazi', 'day' => 'Seshanba, Payshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Anatomiya va klinik anatomiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Skalpel', 'place' => '1-o\'quv bino, 224-xona', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                        ['name' => 'Moxir anatomlar', 'place' => '1-o\'quv bino, 217-xona', 'day' => 'Chorshanba, Juma', 'time' => '15:00-17:00'],
                        ['name' => 'MAXAON', 'place' => '1-o\'quv bino, 222-xona', 'day' => 'Seshanba, Payshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Tibbiy biologiya va gistologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => '"Yosh biologlar"', 'place' => '1-o\'quv bino, 115-xona', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                        ['name' => '"Yosh gistologlar"', 'place' => '1-o\'quv bino, 106-xona', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Akusherlik va ginekologiya va Oilaviy shifokorlikda ginekologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => '"Yosh akusher-ginekologlar"', 'place' => 'RIO va BSIATMSF Reproduktiv salomatlik bo\'limi', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Umumiyxirurgiya, bolalar xirurgiyasi, urologiya va bolalar urologiyasi kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Surgeon', 'place' => 'Viloyat bolalar ko\'p tarmoqli tibbiyot markazi', 'day' => 'Dushanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh jarrohlar', 'place' => 'Viloyat bolalar ko\'p tarmoqli tibbiyot markazi', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Nefroclub', 'place' => 'Viloyat ko\'p tarmoqli tibbiyot markazi', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Ijtimoiy-gumanitar fanlar kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh tarixchi', 'place' => '1-o\'quv bino, 416-xona', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                        ['name' => 'Kompyuter bilimdoni', 'place' => 'o\'quv bino, 430-xona', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                        ['name' => 'Yosh biofiziklar', 'place' => '1-o\'quv bino, 424-xona', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                        ['name' => 'Qalb shifokorlari', 'place' => '1-o\'quv bino, 416-xona', 'day' => 'Shanba', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Tibbiy va biologik kimyo kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh kimyogarlar', 'place' => 'Asosiy o\'quv bino, 301-xona', 'day' => 'Dushanba', 'time' => '15:00-16:00'],
                        ['name' => 'Yosh biokimyogarlar', 'place' => 'Asosiy o\'quv bino, 313-xona', 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                    ]
                ],
                [
                    'title' => "Patologik anatomiya, sud tibbiyoti huquqi kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Buyuk patologoanatomlar', 'place' => 'Asosiy o\'quv bino, 2-qavat', 'day' => 'Seshanba, Payshanba', 'time' => '16:30-17:30'],
                        ['name' => 'Adolatli sud-tibbiy ekspertlar', 'place' => 'RSTYIAM Surxondaryo viloyati filiali binosi', 'day' => 'Chorshanba, Juma', 'time' => '16:30-17:30'],
                    ]
                ],
                [
                    'title' => "Tibbiy psixologiya nevrologiya va psixiatriya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh nevrologlar', 'place' => 'Ko\'p tarmoqli markaziy poliklinika, 403-xona', 'day' => 'Seshanba, Payshanba, Shanba', 'time' => '16:00-18:00'],
                        ['name' => 'Yosh Psixiatrlar', 'place' => 'Viloyat Ruhiy asab kasalliklar shifoxonasi, 3-xona', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '16:00-18:00'],
                    ]
                ],
                [
                    'title' => "Yuqumli kasalliklar, dermatovenerologiya, ftiziatriya va pulmonologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Dermatovenerologlar', 'place' => 'Surxondaryo viloyati Teri tanosil kasalliklari dispanseri', 'day' => 'Shanba', 'time' => '16:00-18:00'],
                        ['name' => 'Infeksionistlar', 'place' => 'Viloyat yuqumli kasalliklar shifoxonasi', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '16:00-18:00'],
                        ['name' => 'Ftiziatrlar', 'place' => 'Viloyat Ftiziatriyava Pulmonologiya shifoxonasi', 'day' => 'Seshanba, Payshanba', 'time' => '16:00-18:00'],
                    ]
                ],
                [
                    'title' => "Ichki kasalliklar, propedevtikasi, reabitologiya, xalq tabobati va endokrinologiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Yosh endokrinologlar', 'place' => 'Mashxura klinikasi', 'day' => 'Dushanba, Juma', 'time' => '15:00-17:00'],
                    ]
                ],
                [
                    'title' => "Xirurgik kasalliklar va oilaviy shifokorlikda xirurgiya kafedrasida faoliyat ko'rsatayotgan to'garak mashg'ulotlar jadvali",
                    'clubs' => [
                        ['name' => 'Torakal va yurak-qon tomir xirurgiyasi', 'place' => 'O\'tan polvon DDM, Kafedra xonasi', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Kardioxirurgiyada zamonaviy tekshirish usullari', 'place' => 'O\'tan polvon DDM, Kafedra xonasi', 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Kardioxirurgiyada anesteziologiya, reanimatsiya va perfuziologiya masalalari', 'place' => 'O\'tan polvon DDM, Kafedra xonasi', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                        ['name' => 'Tibbiyotda nemis tili', 'place' => 'O\'tan polvon DDM, Kafedra xonasi', 'day' => 'Seshanba, Payshanba, Shanba', 'time' => '15:00-17:00'],
                    ]
                ],
            ];

            $myClubNames = $myMemberships->pluck('club_name')->toArray();
        @endphp

        @foreach($sections as $section)
            <div class="mb-5">
                <div class="rounded-t-xl px-3 py-2 border border-b-0 border-black" style="background-color:#c2def9;">
                    <h2 class="text-xs font-bold text-gray-800 text-center leading-snug">{{ $section['title'] }}</h2>
                </div>
                <div class="border border-t-0 border-black rounded-b-xl">
                    <div class="grid grid-cols-2 rounded-b-xl overflow-hidden">
                        @foreach($section['clubs'] as $i => $club)
                            <div class="px-2.5 py-2.5 bg-white border-b border-r border-black {{ count($section['clubs']) % 2 !== 0 && $loop->last ? 'col-span-2 border-r-0' : '' }} {{ $loop->iteration % 2 === 0 ? 'border-r-0' : '' }} {{ $loop->last || ($loop->iteration % 2 !== 0 && $loop->iteration === count($section['clubs']) - 1) ? 'border-b-0' : '' }}">
                                <div class="font-semibold text-xs text-gray-800">{{ $i + 1 }}. {{ $club['name'] }}</div>
                                <div class="mt-1 flex flex-col gap-0.5 text-[11px] text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 0115 0z"/></svg>
                                        <span>{{ $club['place'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                        <span>{{ $club['day'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>{{ $club['time'] }}</span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    @if(in_array($club['name'], $myClubNames))
                                        <span class="inline-block text-[10px] font-semibold text-green-600 bg-green-50 border border-green-200 rounded-lg px-2 py-0.5">Ariza yuborilgan</span>
                                    @else
                                        <form method="POST" action="{{ route('student.clubs.join') }}">
                                            @csrf
                                            <input type="hidden" name="club_name" value="{{ $club['name'] }}">
                                            <input type="hidden" name="club_place" value="{{ $club['place'] }}">
                                            <input type="hidden" name="club_day" value="{{ $club['day'] }}">
                                            <input type="hidden" name="club_time" value="{{ $club['time'] }}">
                                            <input type="hidden" name="kafedra_name" value="{{ $section['title'] }}">
                                            <button type="submit" class="text-[10px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg px-2.5 py-1 transition">A'zo bo'lish</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-student-app-layout>
