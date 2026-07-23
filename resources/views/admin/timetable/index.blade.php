<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dars jadvali tuzish</h2>
    </x-slot>

    {{-- Butun sahifa ekran balandligiga sig'adi (page-scroll yo'q): yuqori
         boshqaruv qatorlari va pastdagi panel doim ko'rinadi, faqat oradagi
         panjara ichida skroll bo'ladi. --}}
    <div class="max-w-full mx-auto px-2 lg:px-4" style="height: calc(100vh - 130px); display: flex; flex-direction: column; overflow: hidden;">

            {{-- Yuqori boshqaruv qatorlari (flex-shrink-0 — doim ko'rinadi) --}}
            <div style="flex: 0 0 auto;">

            {{-- Doska tanlash + boshqaruv paneli — bitta ixcham qatorda --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-2">
                <div class="p-2 flex flex-wrap items-center gap-1.5">
                    <select id="boardSel" class="rounded-md border-gray-300 shadow-sm text-xs py-1.5 min-w-[220px] max-w-[300px]">
                        <option value="">— Tanlang yoki yangi yarating —</option>
                        @foreach($boards as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->cards_count }} karta)</option>
                        @endforeach
                    </select>
                    <button type="button" id="newBoardBtn" class="asc-tool">+ Yangi doska</button>
                    <button type="button" id="genBtn" class="hidden asc-tool">⚙ Kartochkalar</button>
                    <button type="button" id="refreshNamesBtn" class="hidden asc-tool" title="Ishchi rejadagi joriy fan nomlarini kartochkalarga ko'chiradi (joylashuvlar saqlanadi)">🔄 Fan nomlari</button>
                    <button type="button" id="delBoardBtn" class="hidden asc-tool" style="color:#dc2626">🗑 O'chirish</button>
                    <span id="boardMsg" class="text-xs"></span>

                    {{-- aSc Timetables uslubidagi boshqaruv tugmalari — doska yuklanganda ko'rinadi --}}
                    <span data-asc-toolbar class="hidden mx-1 h-6 w-px bg-gray-200"></span>
                    <button type="button" id="settingsBtn" data-asc-toolbar class="hidden asc-tool">⚙️ Sozlamalar</button>
                    <button type="button" id="managerBtn" data-asc-toolbar class="hidden asc-tool" data-dialog="subjects">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4.5h10a2 2 0 0 1 2 2V19H7a2 2 0 0 0-2 2V4.5Z"/><path d="M7 21h12V6.5a2 2 0 0 0-2-2H7"/><path d="M9 8h5M9 12h5"/></svg>
                        Ma'lumotlar
                    </button>
                    <button type="button" id="assignBtn" data-asc-toolbar class="hidden asc-tool">🔗 Biriktirish</button>
                    <span data-asc-toolbar class="hidden mx-1 h-6 w-px bg-gray-200"></span>
                    <button type="button" id="excelViewBtn" data-asc-toolbar class="hidden asc-tool">⬇ Excelga yuklash</button>
                    <button type="button" id="checkBtn" data-asc-toolbar class="hidden asc-tool">🔍 Tekshiruv <span id="checkBadge" class="hidden ml-1 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold"></span></button>
                </div>

                {{-- Yangi doska formasi --}}
                <div id="newBoardForm" class="hidden border-t border-gray-100 p-3 grid grid-cols-2 md:grid-cols-7 gap-3 items-end bg-gray-50">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">O'quv yili</label>
                        <select id="nbYear" class="w-full rounded-md border-gray-300 text-sm">
                            @foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Semestr</label>
                        <select id="nbParity" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="kuzgi">Kuzgi (toq)</option>
                            <option value="bahorgi">Bahorgi (juft)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Oqim manbai</label>
                        <select id="nbKind" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="plan">Reja (kelasi yil)</option>
                            <option value="real">Real (joriy)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fakultet</label>
                        <select id="nbFaculty" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Barcha fakultetlar</option>
                            @foreach($faculties as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kunlar (sukut)</label>
                        <input type="number" id="nbDays" value="6" min="1" max="7" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kuniga para (sukut)</label>
                        <input type="number" id="nbPairs" value="6" min="1" max="10" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hafta soni (sukut)</label>
                        <input type="number" id="nbWeeks" value="15" min="1" max="30" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-7">
                        <button type="button" id="createBoardBtn" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Yaratish</button>
                        <span class="ml-2 text-xs text-gray-500">Bu sukut sozlamalar — har yo'nalish+kurs uchun keyin alohida o'zgartiriladi. Doska yaratilgach "Kartochkalarni yaratish" bosiladi.</span>
                    </div>
                </div>
            </div>

            {{-- Yo'nalish tanlash + statistika + shu yo'nalish uchun panjara sozlamasi --}}
            <div id="specBar" class="hidden bg-white shadow-sm sm:rounded-lg mb-3 p-2" title="Kartani bosing → yashil katakni bosing. Joylashgan kartani bosib olib tashlash/ko'chirish/o'qituvchi-xona biriktirish mumkin. Avtomatik joylash — guruh/o'qituvchi to'qnashuvisiz, oynasiz, fanni hafta bo'ylab teng taqsimlab qo'yadi.">
                <div class="flex flex-wrap items-center gap-1.5">
                    <select id="facSel" class="rounded-md border-gray-300 shadow-sm text-xs py-1.5 min-w-[140px]"></select>
                    <select id="dirSel" class="rounded-md border-gray-300 shadow-sm text-xs py-1.5 min-w-[140px]"></select>
                    <span class="text-[11px] text-gray-500">Kurs:</span>
                    <span id="courseChecks" class="flex items-center gap-1.5 flex-wrap rounded-md border border-gray-300 px-2 py-1 bg-white"></span>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600 whitespace-nowrap" title="Shu yo'nalish+kursdagi barcha fakultetlar ketma-ket ko'rsatiladi">
                        <input type="checkbox" id="allFacChk" class="rounded border-gray-300"> Barcha fak.
                    </label>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600 whitespace-nowrap" title="Shu kursdagi barcha fakultet va yo'nalishlar ketma-ket ko'rsatiladi">
                        <input type="checkbox" id="allSpecChk" class="rounded border-gray-300"> Barcha fak.+yo'n.
                    </label>

                    <span class="h-6 w-px bg-gray-200"></span>
                    <div class="flex items-center gap-1 rounded-md border border-indigo-100 bg-indigo-50 px-1.5 py-1" title="Faqat shu yo'nalish+kursga. Hafta soni o'zgarsa kartalar qayta yaratiladi.">
                        <input type="number" id="gsDays" min="1" max="7" class="w-10 rounded border-gray-300 text-xs" title="Kunlar">
                        <input type="number" id="gsPairs" min="1" max="10" class="w-10 rounded border-gray-300 text-xs" title="Kuniga para">
                        <input type="number" id="gsWeeks" min="1" max="30" class="w-10 rounded border-gray-300 text-xs" title="Hafta soni">
                        <button type="button" id="gsSave" class="asc-tool">Saqlash</button>
                    </div>

                    <span class="h-6 w-px bg-gray-200"></span>
                    <div class="flex rounded-md overflow-hidden border border-gray-300 text-[11px]">
                        <button type="button" class="tt-type active px-2 py-1" data-type="all">Hammasi</button>
                        <button type="button" class="tt-type px-2 py-1 border-l border-gray-300" data-type="lecture">Ma'ruza</button>
                        <button type="button" class="tt-type px-2 py-1 border-l border-gray-300" data-type="practice">Amaliy</button>
                    </div>
                    <select id="weekSel" class="rounded-md border-gray-300 text-[11px] py-1"></select>
                    <span id="weekHint" class="hidden text-[10px] text-amber-600 font-medium">individual</span>
                    <label class="flex items-center gap-1 text-[11px] text-gray-500">Kesim:
                        <select id="viewMode" class="rounded-md border-gray-300 text-[11px] py-1">
                            <option value="group">Guruh</option>
                            <option value="teacher">O'qituvchi</option>
                            <option value="room">Auditoriya</option>
                            <option value="subject">Fan</option>
                        </select>
                    </label>

                    <span class="h-6 w-px bg-gray-200"></span>
                    <button type="button" id="autoBtn" class="px-2.5 py-1 text-xs bg-emerald-600 text-white rounded-md hover:bg-emerald-700">⚡ Avtomatik joylash</button>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600" title="Butun doska (barcha yo'nalishlar)"><input type="checkbox" id="autoScope" class="rounded border-gray-300"> Butun doska</label>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600" title="Qaytadan joylash (mavjudini bo'shatib)"><input type="checkbox" id="autoReset" class="rounded border-gray-300"> Qaytadan joylash</label>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600" title="Auditoriya biriktirilsin (sig'im bo'yicha)"><input type="checkbox" id="autoRooms" class="rounded border-gray-300"> Auditoriya</label>
                    <label class="flex items-center gap-1 text-[11px] text-gray-600" title="Ma'ruzalarga faqat ma'ruza xonalarini to'qnashuvsiz biriktirish"><input type="checkbox" id="autoLecRooms" class="rounded border-gray-300"> Ma'ruza xonasi</label>
                    <button type="button" id="unplaceBtn" class="px-2.5 py-1 text-xs bg-amber-50 text-amber-700 rounded-md hover:bg-amber-100" title="Ko'rinayotgan qamrovdagi barcha joylashuvlarni bo'shatib, kartochkalarni panelga qaytaradi">🗑 Bo'shatish</button>
                    <span id="autoMsg" class="text-[11px] text-emerald-700 font-medium"></span>

                    <div id="statChips" class="flex flex-wrap gap-1.5 text-[11px] ml-auto"></div>
                </div>
            </div>

            </div>{{-- /yuqori boshqaruv qatorlari --}}

            {{-- Asosiy maydon: panjara (flex bilan qolgan balandlikni to'ldiradi,
                 faqat shu ichida skroll) + pastda joylashmagan kartalar --}}
            <div id="mainArea" class="hidden" style="flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;">
                {{-- Panjara --}}
                <div class="bg-white shadow-sm sm:rounded-lg overflow-auto" style="flex: 1 1 auto; min-height: 0; max-width: 100%;">
                    <table id="grid" class="border-collapse text-[11px]"></table>
                </div>

                {{-- Joylashtirilmagan kartochkalar — pastda gorizontal panel (flex-shrink-0 — doim ko'rinadi) --}}
                <div class="bg-white shadow-sm sm:rounded-lg mt-2" style="flex: 0 0 auto; box-shadow: 0 -6px 12px -4px rgba(0,0,0,.15);">
                    <div class="px-3 py-1 border-b border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700">Joylashmagan kartalar</span>
                        <span id="unplacedCount" class="text-xs font-bold text-amber-600"></span>
                    </div>
                    <div id="cardPanel" class="p-2 flex flex-wrap gap-1.5 overflow-y-auto bg-white" style="max-height: 120px;"></div>
                </div>
            </div>

            {{-- Kartochka rekvizitlari modali --}}
            <div id="cardModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="tt-modal-win bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="flex items-center justify-between px-5 py-3 border-b">
                            <div>
                                <div id="cmTitle" class="font-semibold text-gray-800 text-sm"></div>
                                <div id="cmSub" class="text-xs text-gray-500"></div>
                            </div>
                            <button type="button" id="cmClose" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="px-5 py-4 space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">O'qituvchi (kafedra bo'yicha)</label>
                                <input id="cmTeacherSearch" placeholder="Qidirish..." class="w-full rounded-md border-gray-300 text-sm mb-1">
                                <select id="cmTeacher" size="5" class="w-full rounded-md border-gray-300 text-sm"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Auditoriya <span id="cmCap" class="text-gray-400"></span></label>
                                <select id="cmAud" class="w-full rounded-md border-gray-300 text-sm"></select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Dars uzunligi</label>
                                    <select id="cmLen" class="w-full rounded-md border-gray-300 text-sm">
                                        <option value="1">0.5 para (1 soat)</option>
                                        <option value="2">1 para (2 soat)</option>
                                        <option value="3">1.5 para (3 soat)</option>
                                        <option value="4">2 para (4 soat)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Boshlanishi</label>
                                    <select id="cmStartHalf" class="w-full rounded-md border-gray-300 text-sm">
                                        <option value="0">Para boshidan</option>
                                        <option value="1">Para o'rtasidan (yarmidan)</option>
                                    </select>
                                </div>
                                <div id="cmTimeHint" class="col-span-2 text-[11px] text-indigo-600"></div>
                            </div>
                            <div id="cmMsg" class="hidden text-sm rounded px-3 py-2"></div>
                        </div>
                        <div class="flex justify-between gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-lg">
                            <div class="flex gap-2">
                                <button type="button" id="cmUnplace" class="px-3 py-1.5 text-sm bg-amber-50 text-amber-700 rounded-md hover:bg-amber-100">↩ Jadvaldan olish</button>
                                <button type="button" id="cmResetWeek" class="hidden px-3 py-1.5 text-sm bg-sky-50 text-sky-700 rounded-md hover:bg-sky-100">↺ Shablonga qaytarish</button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" id="cmCancel" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700">Yopish</button>
                                <button type="button" id="cmSave" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Saqlash</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ aSc Timetables uslubidagi boshqaruv dialogi (Fanlar/Guruhlar/Auditoriyalar/O'qituvchilar) ═══ --}}
            <div id="ascModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-none flex flex-col" style="width: calc(100vw - 200px); max-width: none; height: calc(100vh - 100px); max-height: calc(100vh - 100px);">
                        {{-- Sarlavha satri --}}
                        <div class="asc-titlebar flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="flex items-center gap-3 text-base font-semibold text-white">
                                <span id="ascIcon"></span><span id="ascTitle"></span>
                            </div>
                            <button type="button" id="ascClose" class="text-white/80 hover:text-white text-xl leading-none px-1">&times;</button>
                        </div>
                        {{-- aSc uslubidagi chap navigatsiya + ishchi panel --}}
                        <div class="flex gap-3 p-4 overflow-hidden" style="min-height: 600px;">
                            <nav class="w-60 shrink-0 flex flex-col gap-2 rounded-xl border border-blue-100 bg-gradient-to-b from-slate-50 to-white p-3 shadow-sm" aria-label="Jadval ma'lumotlari">
                                <div class="px-3 py-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Boshqaruv</div>
                                <button type="button" class="asc-nav-btn active" data-asc-type="subjects" aria-selected="true"><span class="asc-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4.5h10a2 2 0 0 1 2 2V19H7a2 2 0 0 0-2 2V4.5Z"/><path d="M7 21h12V6.5a2 2 0 0 0-2-2H7"/><path d="M9 8h5M9 12h5"/></svg></span><span>Darslar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="groups" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="8" r="3"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 15h1a3 3 0 0 1 3 3v2"/></svg></span><span>Guruhlar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="auditoriums" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16"/><path d="M2 21h20M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M11 21v-4h2v4"/></svg></span><span>Auditoriyalar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="teachers" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 9 8-4 8 4-8 4-8-4Z"/><path d="M7 11.5V16c2 1.5 8 1.5 10 0v-4.5M20 9v5"/></svg></span><span>O'qituvchilar</span></button>
                            </nav>
                            <div id="ascPanel" class="flex-1 flex gap-3 min-w-0">
                                <div class="flex-1 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden min-w-0 shadow-sm">
                                    <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-blue-50">
                                        <span id="ascListLabel" class="text-sm font-bold text-slate-700">Darslar ro'yxati:</span>
                                        <input id="ascSearch" placeholder="Qidirish..." class="ml-auto w-64 rounded-lg border-slate-300 text-sm py-2 shadow-sm">
                                        <select id="ascFilter" class="hidden rounded-lg border-slate-300 text-sm py-2 shadow-sm"></select>
                                        <span id="ascCount" class="text-xs text-gray-400"></span>
                                    </div>
                                    <div class="overflow-auto" style="max-height: 64vh;">
                                        <table id="ascTable" class="w-full text-sm asc-table"></table>
                                    </div>
                                </div>
                                {{-- Tanlangan bo'lim uchun amallar --}}
                                <div id="ascButtons" class="w-48 shrink-0 flex flex-col gap-2"></div>
                            </div>
                        </div>
                        {{-- Pastki panel --}}
                        <div class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                            <div id="ascFootMsg" class="text-sm text-slate-500"></div>
                            <button type="button" id="ascCloseBtn" class="asc-btn px-5 py-2">Yopish</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Auditoriya tahrirlash mini-formasi --}}
            <div id="audEditModal" class="hidden tt-modal tt-modal-top">
                <div class="tt-modal-body">
                    <div class="tt-modal-win bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="flex items-center justify-between px-5 py-3 border-b">
                            <div id="aeTitle" class="font-semibold text-gray-800 text-sm">Auditoriya</div>
                            <button type="button" id="aeClose" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="px-5 py-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Kod *</label>
                                <input id="aeCode" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sig'im *</label>
                                <input id="aeVolume" type="number" min="0" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nomi *</label>
                                <input id="aeName" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bino</label>
                                <input id="aeBuilding" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Turi</label>
                                <input id="aeType" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <label class="col-span-2 flex items-center gap-2 text-sm text-gray-600">
                                <input id="aeActive" type="checkbox" class="rounded border-gray-300" checked> Faol
                            </label>
                            <div id="aeMsg" class="col-span-2 hidden text-sm rounded px-3 py-2"></div>
                        </div>
                        <div class="flex justify-end gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-lg">
                            <button type="button" id="aeCancel" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700">Bekor</button>
                            <button type="button" id="aeSave" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Saqlash</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Import uchun yashirin fayl input --}}
            <input type="file" id="audImportFile" accept=".xlsx,.xls,.csv" class="hidden">

            {{-- ═══ Excel ko'rinishidagi jadval (kunlar/paralar qatorda, guruhlar ustunda) ═══ --}}
            <div id="excelModal" class="hidden fixed inset-0 z-50 bg-black/50">
                <div class="flex min-h-full items-start justify-center p-3">
                    <div class="bg-white rounded shadow-2xl w-full max-w-[1500px] flex flex-col" style="max-height: 94vh;">
                        <div class="flex items-center justify-between px-4 py-2 border-b bg-gray-50 rounded-t">
                            <div class="font-semibold text-gray-800 text-sm">📊 Dars jadvali — Excel ko'rinish</div>
                            <div class="flex items-center gap-2">
                                <div class="flex rounded-md overflow-hidden border border-gray-300 text-xs">
                                    <button type="button" class="ex-mode active px-2.5 py-1" data-mode="group">Guruh bo'yicha</button>
                                    <button type="button" class="ex-mode px-2.5 py-1 border-l border-gray-300" data-mode="teacher">O'qituvchi bo'yicha</button>
                                    <button type="button" class="ex-mode px-2.5 py-1 border-l border-gray-300" data-mode="room">Auditoriya bo'yicha</button>
                                </div>
                                <button type="button" id="excelDownload" class="asc-btn">⬇ Excelga yuklab olish</button>
                                <button type="button" id="excelPrint" class="asc-btn">🖨 Chop / PDF</button>
                                <button type="button" id="excelClose" class="text-gray-400 hover:text-gray-600 text-2xl leading-none px-1">&times;</button>
                            </div>
                        </div>
                        <div id="excelBody" class="overflow-auto p-3" style="max-height: 86vh;"></div>
                    </div>
                </div>
            </div>

            {{-- ═══ Umumiy sozlamalar (aSc "Установки" uslubida) ═══ --}}
            <div id="setModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-3xl flex flex-col" style="max-height: 92vh;">
                        <div class="asc-titlebar flex items-center justify-between px-3 py-1.5 rounded-t">
                            <div class="flex items-center gap-2 text-sm font-semibold text-white">⚙️ Umumiy sozlamalar</div>
                            <button type="button" id="setClose" class="text-white/80 hover:text-white text-xl leading-none px-1">&times;</button>
                        </div>
                        {{-- Tablar --}}
                        <div class="flex gap-1 px-2 pt-2 bg-[#f0f0f0]">
                            <button type="button" class="set-tab active" data-tab="basic">Asosiy ma'lumotlar</button>
                            <button type="button" class="set-tab" data-tab="bells">Qo'ng'iroqlar (juftliklar vaqti)</button>
                            <button type="button" class="set-tab" data-tab="days">Kunlar</button>
                        </div>
                        <div class="bg-white border border-gray-300 mx-2 mb-2 rounded-b p-4 overflow-auto" style="max-height: 66vh;">
                            {{-- Asosiy --}}
                            <div id="setBasic" class="set-pane grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Muassasa nomi (chop etishda)</label>
                                    <input id="stInst" class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">O'quv yili</label>
                                    <input id="stYear" disabled class="w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Haftada kunlar</label>
                                    <select id="stDays" class="w-full rounded-md border-gray-300 text-sm">
                                        @for($i=1;$i<=7;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Kuniga para (qo'ng'iroqlardan)</label>
                                    <input id="stPairs" disabled class="w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Dam olish kuni</label>
                                    <input id="stDayOff" placeholder="Yakshanba" class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div class="col-span-2 flex flex-col gap-2 pt-1">
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input id="stAllowZero" type="checkbox" class="rounded border-gray-300"> Nol para (0-para)ga ruxsat berish
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input id="stShowNum" type="checkbox" class="rounded border-gray-300"> Kun nomi o'rniga raqamini ko'rsatish
                                    </label>
                                    <div class="mt-1 pt-2 border-t border-gray-100">
                                        <div class="text-xs font-semibold text-gray-500 mb-1">Avtomatik joylash qoidalari (bir fanning haftalik paralari):</div>
                                        <label class="flex items-center gap-2 text-sm text-gray-600">
                                            <input id="stSameDay" type="checkbox" class="rounded border-gray-300"> Bitta fanning paralarini bir kunga qo'yish
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-gray-600">
                                            <input id="stConsec" type="checkbox" class="rounded border-gray-300"> Ketma-ket (yonma-yon) paralarga qo'yish
                                        </label>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Masalan 4 soatlik dars — ikki para bir kunda, ketma-ket (2+2 alohida kunga bo'linmaydi).</p>
                                        <label class="flex items-center gap-2 text-sm text-gray-600 mt-2">
                                            Auditoriya sig'imi toleransi:
                                            <input id="stRoomTol" type="number" min="0" max="30" class="w-16 rounded border-gray-300 text-sm"> %
                                        </label>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Oqim xona sig'imidan shu %gача katta bo'lsa ham joylanadi (mas. 120 o'rinli xona — 125 oqim). Katta farq baribir rad etiladi.</p>
                                    </div>
                                </div>
                                <p class="col-span-2 text-xs text-gray-400">O'quv yili va semestr doska yaratilganda belgilangan — o'zgartirish uchun yangi doska yarating.</p>
                            </div>
                            {{-- Qo'ng'iroqlar --}}
                            <div id="setBells" class="set-pane hidden">
                                <div class="flex items-center gap-2 mb-2">
                                    <button type="button" id="stAddPair" class="asc-btn primary">➕ Para qo'shish</button>
                                    <button type="button" id="stAddBreak" class="asc-btn">➕ Tanaffus qo'shish</button>
                                    <span class="mx-1 h-6 w-px bg-gray-300"></span>
                                    <button type="button" id="stMoveUp" class="asc-btn" title="Belgilangan qatorni yuqoriga ko'chirish" disabled>▲ Yuqoriga</button>
                                    <button type="button" id="stMoveDown" class="asc-btn" title="Belgilangan qatorni pastga ko'chirish" disabled>▼ Pastga</button>
                                    <button type="button" id="stResetBells" class="asc-btn ml-auto">↺ Standart jadval</button>
                                </div>
                                <table class="w-full text-xs asc-table" id="stBellTable"></table>
                                <p class="text-xs text-gray-400 mt-2">Juftliklar (para) tartib bilan raqamlanadi va panjaradagi para sonini belgilaydi. Tanaffuslar faqat chop/Excel ko'rinishida ko'rinadi. Vaqt formati <b>SS:DD</b> (masalan 08:30).</p>
                            </div>
                            {{-- Kunlar --}}
                            <div id="setDays" class="set-pane hidden">
                                <p class="text-xs text-gray-500 mb-2">Kun nomlarini o'zgartirishingiz mumkin (chop etishda ishlatiladi).</p>
                                <div id="stDayNames" class="grid grid-cols-2 gap-2"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-gray-300 bg-[#f0f0f0] rounded-b">
                            <div id="setMsg" class="text-xs text-gray-500"></div>
                            <div class="flex gap-2">
                                <button type="button" id="setCancel" class="asc-btn">Bekor</button>
                                <button type="button" id="setSave" class="asc-btn primary">Saqlash</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Qo'ng'iroq qatorini tahrirlash mini-modali --}}
            <div id="bellEditModal" class="hidden tt-modal tt-modal-top">
                <div class="tt-modal-body">
                    <div class="tt-modal-win bg-white rounded-lg shadow-xl w-full max-w-sm">
                        <div class="flex items-center justify-between px-5 py-3 border-b">
                            <div id="beTitle" class="font-semibold text-gray-800 text-sm">Qatorni tahrirlash</div>
                            <button type="button" id="beClose" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="px-5 py-4 grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nomi</label>
                                <input id="beName" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Qisqartma</label>
                                <input id="beAbbr" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bosib chiqarish</label>
                                <select id="bePrint" class="w-full rounded-md border-gray-300 text-sm"><option value="1">Ha</option><option value="0">Yo'q</option></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Boshlanishi (SS:DD)</label>
                                <input id="beStart" placeholder="08:30" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Tugashi (SS:DD)</label>
                                <input id="beEnd" placeholder="09:50" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-lg">
                            <button type="button" id="beCancel" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700">Bekor</button>
                            <button type="button" id="beSave" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">OK</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ O'qituvchi biriktirish matritsasi ═══ --}}
            <div id="assignModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-6xl flex flex-col" style="max-height: 92vh;">
                        <div class="asc-titlebar flex items-center justify-between px-3 py-1.5 rounded-t">
                            <div class="flex items-center gap-2 text-sm font-semibold text-white">🔗 O'qituvchi biriktirish</div>
                            <button type="button" id="asgClose" class="text-white/80 hover:text-white text-xl leading-none px-1">&times;</button>
                        </div>
                        <div class="flex gap-2 p-2 overflow-hidden" style="min-height: 400px;">
                            {{-- Chap: dars birliklari --}}
                            <div class="flex-1 flex flex-col bg-white border border-gray-300 rounded overflow-hidden">
                                <div class="flex items-center gap-2 px-2 py-1.5 border-b border-gray-200 bg-gray-50">
                                    <span class="text-xs font-semibold text-gray-600">Dars birliklari:</span>
                                    <select id="asgFilter" class="rounded border-gray-300 text-xs py-1"></select>
                                    <label class="flex items-center gap-1 text-xs text-gray-600 ml-1"><input type="checkbox" id="asgOnlyEmpty" class="rounded border-gray-300"> faqat biriktirilmagan</label>
                                    <input id="asgSearch" placeholder="Fan qidirish..." class="ml-auto w-44 rounded border-gray-300 text-xs py-1">
                                    <span id="asgCount" class="text-xs text-gray-400"></span>
                                </div>
                                <div class="overflow-auto" style="max-height: 64vh;">
                                    <table id="asgTable" class="w-full text-xs asc-table"></table>
                                </div>
                            </div>
                            {{-- O'ng: o'qituvchi tanlash --}}
                            <div class="w-72 shrink-0 flex flex-col bg-white border border-gray-300 rounded overflow-hidden">
                                <div class="px-2 py-1.5 border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">O'qituvchi</div>
                                <div class="p-2 space-y-2 flex-1 flex flex-col overflow-hidden">
                                    <div id="asgUnitInfo" class="text-xs text-gray-500 min-h-[32px]">← Chapdan dars birligini tanlang</div>
                                    <input id="asgTeacherSearch" placeholder="Qidirish..." class="w-full rounded-md border-gray-300 text-xs" disabled>
                                    <label class="flex items-center gap-1 text-[11px] text-gray-500"><input type="checkbox" id="asgKafedraOnly" class="rounded border-gray-300" checked> shu kafedra bo'yicha</label>
                                    <select id="asgTeacher" size="10" class="w-full rounded-md border-gray-300 text-xs flex-1" disabled></select>
                                    <div class="flex gap-1">
                                        <button type="button" id="asgApply" class="asc-btn primary flex-1" disabled>Biriktirish</button>
                                        <button type="button" id="asgClear" class="asc-btn" disabled title="Biriktirishni olib tashlash">✖</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-gray-300 bg-[#f0f0f0] rounded-b">
                            <div id="asgMsg" class="text-xs text-gray-500"></div>
                            <button type="button" id="asgCloseBtn" class="asc-btn">Yopish</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Tekshiruv (konflikt / oyna) hisoboti ═══ --}}
            <div id="checkModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-3xl flex flex-col" style="max-height: 90vh;">
                        <div class="asc-titlebar flex items-center justify-between px-3 py-1.5 rounded-t">
                            <div class="flex items-center gap-2 text-sm font-semibold text-white">🔍 Jadval tekshiruvi</div>
                            <button type="button" id="chkClose" class="text-white/80 hover:text-white text-xl leading-none px-1">&times;</button>
                        </div>
                        <div id="chkBody" class="bg-white border border-gray-300 mx-2 my-2 rounded p-3 overflow-auto" style="max-height: 76vh;"></div>
                    </div>
                </div>
            </div>

        </div>

    <style>
        /* Jadval chiziqlari — qora, eniga va bo'yiga (barcha katak chegaralari) */
        #grid th, #grid td { border: 1px solid #000; }
        #grid td.tt-cell { width: 52px; min-width: 52px; max-width: 52px; height: 34px; vertical-align: middle; text-align: center; cursor: default; padding: 1px; overflow: hidden; }
        #grid td.tt-ok { background: #dcfce7; cursor: pointer; }
        #grid td.tt-bad { background: #fee2e2; }
        /* Drag-and-drop: sudralayotgan katak ustidan o'tganda */
        #grid td.drag-ok { outline: 3px solid #16a34a; outline-offset: -3px; }
        #grid td.drag-bad { outline: 3px solid #ef4444; outline-offset: -3px; }
        #grid [data-chip] { cursor: grab; }
        .pn-card { cursor: grab; }
        /* Faol katak — sichqoncha ustidan o'tganda / strelkalar bilan */
        #grid td.tt-active { outline: 2px solid #2563eb; outline-offset: -2px; box-shadow: inset 0 0 0 2px rgba(37,99,235,.25); }
        /* Transpoze panjara: chapdagi kun/para sarlavhalari — qalin (jiringlagan) yozuv */
        #grid th.tt-corner { background: #eef1f5; color: #475569; position: sticky; left: 0; z-index: 6; font-weight: 800; }
        #grid td.tt-day { background: #f1f5f9; font-weight: 900; color: #1e293b; font-size: 15px; writing-mode: vertical-rl; transform: rotate(180deg);
            text-align: center; white-space: nowrap; letter-spacing: .3px; position: sticky; left: 0; z-index: 4;
            width: 26px; min-width: 26px; max-width: 26px; padding: 2px 0; border-bottom: 4px solid #000 !important; }
        #grid td.tt-para { background: #f8fafc; font-weight: 700; color: #334155; text-align: center; position: sticky; left: 26px; z-index: 4; min-width: 40px; width: 40px; padding: 2px; }
        #grid td.tt-para .tt-para-name { font-size: 11px; font-weight: 900; color: #1e293b; line-height: 1.1; white-space: nowrap; }
        #grid td.tt-para .tt-para-time { font-size: 8px; font-weight: 700; color: #64748b; line-height: 1.1; margin-top: 1px; }
        #grid thead th { position: sticky; top: 0; z-index: 5; }
        #grid th.tt-fac { background: #c7d2fe; color: #1e1b4b; font-weight: 900; text-align: center; text-transform: uppercase; font-size: 11px; letter-spacing: .2px; }
        #grid th.tt-oqim { background: #e0e7ff; color: #3730a3; font-weight: 900; text-align: center; font-size: 11px; }
        /* Guruh sarlavhasi — o'ralib chiqadi (ustunni kengaytirmaydi), ustun ingichka bo'ladi */
        #grid th.tt-grp { background: #eef1f5; color: #1e293b; font-weight: 800; white-space: normal; word-break: break-word;
            text-align: center; font-size: 9px; line-height: 1.05; width: 52px; min-width: 52px; max-width: 52px; padding: 2px 1px; }
        /* Oqimlar orasi — qo'sh chiziq; asos guruhlar (a/b) orasi — qalin chiziq */
        #grid td.sep-oqim, #grid th.sep-oqim { border-left: 3px double #000; }
        #grid td.sep-base, #grid th.sep-base { border-left: 2px solid #000; }
        /* Para/kun ajratuvchi chiziqlar — 3 daraja:
           1) sukut (1px qora) — bitta butun paraning ikki yarmi orasida (masalan 0.5↔1);
           2) .tt-paraend (qalinroq) — ikki xil butun para orasida (masalan 1↔1.5);
           3) .tt-dayend (eng qalin) — kundan-kunga o'tganda (masalan dushanba↔seshanba). */
        #grid td.tt-paraend { border-bottom: 3px solid #000; }
        #grid td.tt-dayend { border-bottom: 5px solid #000; }
        .tt-chip { border-radius: 5px; padding: 2px 4px; margin: 1px 0; font-size: 10px; line-height: 1.2; cursor: pointer; }
        /* Ma'ruza — butun katak bitta sariq (chip'ning alohida foni yo'q); amaliy — fan rangi (inline) */
        .tt-chip.lec { background: transparent; border-left: none; color: #713f12; font-weight: 700; }
        .tt-chip.prc { border-left: 3px dotted #94a3b8; color: #1f2937; font-weight: 500; }
        #grid td.tt-lec { background: #fde68a; }   /* butun oqimga tegishli ma'ruza katagi — bir xil sariq */
        .tt-chip.sel { outline: 2px solid #ef4444; }
        .tt-merge-badge { display: inline-block; margin-left: 4px; padding: 0 4px; font-size: 8px; font-weight: 700;
            background: rgba(0,0,0,.12); border-radius: 6px; color: #334155; vertical-align: middle; }
        .pn-card { display: inline-block; width: 170px; vertical-align: top; border-radius: 6px; padding: 4px 6px;
            font-size: 11px; cursor: pointer; border: 1px solid #e2e8f0; }
        .pn-card.lec { background: #fefce8; border-color: #fde68a; }
        .pn-card.prc { background: #faf5ff; }
        .pn-card.sel { outline: 2px solid #f59e0b; }
        .lang-rus { box-shadow: inset 0 0 0 1px #fca5a5; }
        .lang-ing { box-shadow: inset 0 0 0 1px #86efac; }

        /* ── aSc uslubidagi toolbar va dialoglar ── */
        .asc-tool { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 13px;
            background: linear-gradient(#fff,#eef1f5); border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; }
        .asc-tool:hover { background: linear-gradient(#fff,#e2e8f0); border-color: #94a3b8; }
        .asc-tool .asc-ic { font-size: 16px; }
        .asc-titlebar { background: linear-gradient(#3b6fb5,#2c5896); }
        /* ── Modal oynalar (Tailwind kompilyatsiyasiga bog'liq bo'lmasin — inline) ── */
        .tt-modal { position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,.55); overflow-y: auto; }
        .tt-modal.hidden { display: none; }
        .tt-modal.tt-modal-top { z-index: 70; }   /* boshqa modal ustidagi ichki dialog */
        .tt-modal-body { min-height: 100%; display: flex; align-items: center; justify-content: center;
            padding: 2.5vh 16px; box-sizing: border-box; }
        .tt-modal-win { width: 100%; max-width: min(1200px, 96vw); background: #eef2f7;
            border-radius: 12px; box-shadow: 0 28px 80px rgba(2,6,23,.55); border: 1px solid #cbd5e1;
            display: flex; flex-direction: column; max-height: 95vh; overflow: hidden; }
        .tt-modal .asc-titlebar { box-shadow: 0 1px 0 rgba(255,255,255,.15) inset; }
        .asc-btn { padding: 6px 14px; font-size: 13px; background: linear-gradient(#fff,#e8edf3);
            border: 1px solid #b6c2d1; border-radius: 5px; color: #2c3e50; }
        .asc-btn:hover:not(:disabled) { background: linear-gradient(#fff,#dbe3ec); border-color: #8ea3ba; }
        .asc-btn:disabled { opacity: .45; cursor: not-allowed; }
        .asc-btn.primary { background: linear-gradient(#4a90d9,#2c6bb3); border-color: #2c6bb3; color: #fff; }
        .asc-btn.primary:hover:not(:disabled) { background: linear-gradient(#3f82c8,#255d9c); }
        .asc-btn.danger:hover:not(:disabled) { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
        .asc-btn.block { display: block; width: 100%; text-align: left; }
        .asc-table th { position: sticky; top: 0; background: #eef1f5; border: 1px solid #d5dbe3;
            padding: 4px 8px; text-align: left; font-weight: 600; color: #475569; white-space: nowrap; z-index: 1; }
        .asc-table td { border: 1px solid #edf0f3; padding: 3px 8px; color: #334155; white-space: nowrap; }
        .asc-table tr.sel td { background: #dbeafe; }
        .asc-table tr:hover td { background: #f1f5f9; }
        .asc-table tr.sel:hover td { background: #cfe0fb; }
        .asc-row-head td { background: #f8fafc; font-weight: 700; color: #1e40af; }
        .set-tab { padding: 6px 14px; font-size: 13px; border: 1px solid #cbd5e1; border-bottom: none;
            border-radius: 6px 6px 0 0; background: #e2e8f0; color: #475569; }
        .set-tab.active { background: #fff; color: #1e40af; font-weight: 600; }
        #stBellTable td { padding: 3px 6px; }
        #stBellTable tr.is-break td { background: #f0fdf4; color: #15803d; }
        #stBellTable tbody tr { cursor: pointer; }
        #stBellTable tr.bell-sel td { background: #dbeafe !important; box-shadow: inset 0 0 0 9999px rgba(59,130,246,.12); }
        #stBellTable tr.bell-sel td:first-child { box-shadow: inset 3px 0 0 #2563eb; }
        .asc-mini { padding: 1px 5px; margin-left: 2px; font-size: 12px; border: 1px solid #cbd5e1;
            border-radius: 4px; background: #f8fafc; color: #475569; }
        .asc-mini:hover { background: #e2e8f0; }
        .ex-mode { background: #fff; color: #475569; }
        .ex-mode.active { background: #2c5896; color: #fff; font-weight: 600; }
        .tt-type { background: #fff; color: #475569; }
        .tt-type.active { background: #059669; color: #fff; font-weight: 600; }
        /* ── Excel ko'rinish ── */
        #excelBody table { border-collapse: collapse; font-size: 11px; }
        #excelBody th, #excelBody td { border: 1px solid #9aa7b4; padding: 2px 4px; vertical-align: middle; }
        #excelBody .ex-title { text-align: center; font-weight: 700; font-size: 14px; border: none; padding: 6px; }
        #excelBody .ex-fac { text-align: center; font-weight: 700; background: #dbeafe; }
        #excelBody .ex-fac { text-align: center; font-weight: 800; background: #dbeafe; text-transform: uppercase; }
        #excelBody .ex-spec { text-align: center; font-weight: 700; background: #eef2ff; }
        #excelBody .ex-grp { text-align: center; font-weight: 600; background: #f8fafc; }
        #excelBody .ex-day { writing-mode: vertical-rl; transform: rotate(180deg); font-weight: 700; background: #f1f5f9; text-align: center; }
        #excelBody .ex-para { text-align: center; background: #f8fafc; font-weight: 600; }
        #excelBody .ex-time { text-align: center; background: #fbfcfe; color: #64748b; white-space: nowrap; }
        #excelBody .ex-cell { min-width: 92px; height: 30px; }
        #excelBody .ex-lec { background: #fde68a; }
        #excelBody .ex-prc { background: #faf5ff; }
        @media print {
            body * { visibility: hidden; }
            #excelBody, #excelBody * { visibility: visible; }
            #excelBody { position: absolute; left: 0; top: 0; }
        }
            .asc-nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 9px 10px;
            border: 1px solid transparent;
            border-radius: 6px;
            color: #475569;
            background: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            text-align: left;
            transition: all 0.15s ease;
        }
        .asc-nav-btn:hover {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .asc-nav-btn.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-color: #1d4ed8;
            box-shadow: 0 2px 7px rgba(37, 99, 235, 0.25);
        }
        .dark .asc-nav-btn {
            color: #cbd5e1;
            background: #1e293b;
        }
        .dark .asc-nav-btn:hover {
            background: #1e3a8a;
            color: #dbeafe;
            border-color: #3b82f6;
        }
        .dark .asc-nav-btn.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
        }

        .asc-panel-enter {
            animation: asc-panel-enter 220ms cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes asc-panel-enter {
            from { opacity: 0.72; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .asc-nav-btn {
            min-height: 54px;
            padding: 12px 14px;
            gap: 12px;
            border-radius: 10px;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .asc-nav-btn::first-letter {
            font-size: 1.35rem;
        }
        .asc-nav-btn:hover {
            transform: translateX(2px);
        }
        .asc-nav-btn.active {
            transform: translateX(3px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        @media (max-width: 760px) {
            .asc-win {
                width: calc(100vw - 24px) !important;
                height: calc(100vh - 24px) !important;
                max-height: calc(100vh - 24px) !important;
            }
            .asc-nav-btn {
                min-height: 46px;
                padding: 9px 10px;
                font-size: 0.82rem;
            }
        }

        #ascIcon {
            font-size: 1.5rem;
            line-height: 1;
            filter: drop-shadow(0 2px 4px rgba(15, 23, 42, 0.25));
        }
        #ascTitle {
            font-size: 1.05rem;
            letter-spacing: 0.01em;
        }
        #ascPanel {
            transition: opacity 180ms ease, transform 180ms ease;
        }
        .asc-win {
            border: 1px solid rgba(148, 163, 184, 0.45);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.35);
        }

        .asc-nav-icon {
            width: 1.45rem;
            height: 1.45rem;
            flex: 0 0 1.45rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .asc-nav-icon svg {
            width: 100%;
            height: 100%;
        }
        #ascTable.asc-auditorium-table {
            width: 730px;
            min-width: 730px;
            table-layout: fixed;
        }
        #ascTable.asc-auditorium-table th,
        #ascTable.asc-auditorium-table td {
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #ascTable.asc-auditorium-table .tt-aud-code { width: 58px; }
        #ascTable.asc-auditorium-table .tt-aud-name { width: 150px; }
        #ascTable.asc-auditorium-table .tt-aud-volume { width: 70px; }
        #ascTable.asc-auditorium-table .tt-aud-building { width: 190px; }
        #ascTable.asc-auditorium-table .tt-aud-type { width: 165px; }
        #ascTable.asc-auditorium-table .tt-aud-status { width: 97px; }
        .asc-action-btn { display: flex; align-items: center; gap: 8px; }
        .asc-action-icon { width: 16px; height: 16px; flex: 0 0 16px; display: inline-flex; }
        .asc-action-icon svg { width: 100%; height: 100%; }
        .asc-action-btn.primary .asc-action-icon { color: #fff; }
        .asc-action-btn.danger .asc-action-icon { color: #b91c1c; }
        .asc-action-btn:disabled .asc-action-icon { color: #94a3b8; }

</style>

    <script>
        (function () {
            const BOARDS_STORE = @json(route('admin.timetable.boards.store'));
            const BASE = @json(url('admin/dars-jadvali-tuzish'));
            const TEACHERS_URL = @json(route('admin.timetable.teachers'));
            const AUDS_URL = @json(route('admin.timetable.auditoriums'));
            const CSRF = @json(csrf_token());
            const DAY_NAMES = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];

            let board = null;      // {id, days, pairs_per_day, ...}
            let cards = [];        // barcha kartochkalar
            let grids = {};        // "specialty|course" => {days, pairs_per_day, weeks}
            let specList = [];     // [{key, specialty_name, course}]
            let curSpec = null;    // tanlangan {specialty_name, course} (asosiy/primary)
            let selectedCourses = new Set();   // tanlangan kurslar (checkbox — bir nechta)
            let groupRows = [];    // [{oqim_label, lang, group}]
            let selected = null;   // tanlangan karta (obyekt)
            let audCache = null;
            let modalCard = null;
            let overrides = {};    // "cardId|week" => {day, pair, cancelled} (hafta bo'yicha istisnolar)
            let curWeek = 0;       // 0 = barcha haftalar (shablon); 1..N = alohida hafta

            const $ = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

            // ===== Fan rangi (aSc Timetables uslubida) =====
            // FAQAT amaliy (praktik) darslar fan bo'yicha o'ziga xos rangda
            // bo'yaladi; ma'ruza ([M]) o'zining sariq rangida qoladi. Ranglar
            // doskaning barcha fanlari alfavit tartibida oltin burchak (137.5°)
            // bo'yicha teng taqsimlanadi — qo'shni fanlar bir-biridan ajraladi
            // va bir fan hamma joyda (panel, panjara, Excel) bir xil rangda.
            // HSL emas, HEX ishlatiladi — Excel (.xls) HTML importi hsl() ni
            // tushunmaydi, hex esa brauzerda ham, Excelda ham bir xil chiqadi.
            let subjectColors = {};
            function hslToHex(h, s, l) {
                s /= 100; l /= 100;
                const k = n => (n + h / 30) % 12;
                const a = s * Math.min(l, 1 - l);
                const f = n => {
                    const c = l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
                    return Math.round(255 * c).toString(16).padStart(2, '0');
                };
                return '#' + f(0) + f(8) + f(4);
            }
            function buildSubjectColors() {
                const names = [...new Set(cards.map(c => c.subject_name).filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, 'uz'));
                subjectColors = {};
                const GOLDEN = 137.508;
                names.forEach((n, i) => {
                    const h = Math.round((i * GOLDEN) % 360);
                    subjectColors[n] = { bg: hslToHex(h, 70, 88), border: hslToHex(h, 62, 45) };
                });
            }
            const subjColor = name => subjectColors[name] || { bg: '#f1f5f9', border: '#94a3b8' };
            // Ma'ruza — bir xil sariq (class'dagi tt-lec/.lec fonida qoladi,
            // inline rang bermaymiz). Amaliy — har fan o'z rangida.
            const subjStyle = c => {
                if (c.training_type === 'lecture') return '';
                const s = subjColor(c.subject_name);
                return 'background-color:' + s.bg + ';border-left-color:' + s.border + ';';
            };

            async function api(url, method = 'GET', body = null) {
                const opt = { method, headers: { 'Accept': 'application/json' } };
                if (body) {
                    const fd = new FormData();
                    fd.append('_token', CSRF);
                    Object.entries(body).forEach(([k, v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
                    opt.body = fd;
                }
                const r = await fetch(url, opt);
                const j = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(j.error || j.message || ('HTTP ' + r.status));
                return j;
            }

            // ===== Doska =====
            $('newBoardBtn').onclick = () => $('newBoardForm').classList.toggle('hidden');
            $('createBoardBtn').onclick = async function () {
                this.disabled = true;
                try {
                    const j = await api(BOARDS_STORE, 'POST', {
                        academic_year: $('nbYear').value, semester_parity: $('nbParity').value,
                        kind: $('nbKind').value, faculty_id: $('nbFaculty').value || '',
                        days: $('nbDays').value, pairs_per_day: $('nbPairs').value, weeks: $('nbWeeks').value,
                    });
                    location.href = location.pathname + '?board=' + j.board_id;
                } catch (e) { alert('Xatolik: ' + e.message); this.disabled = false; }
            };
            $('boardSel').onchange = function () {
                if (this.value) loadBoard(this.value); else hideBoard();
            };
            $('delBoardBtn').onclick = async () => {
                if (!board || !confirm('Doska va barcha kartochkalari o\'chirilsinmi?')) return;
                await fetch(BASE + '/boards/' + board.id, { method: 'POST', headers: {'Accept':'application/json'},
                    body: (() => { const f = new FormData(); f.append('_token', CSRF); f.append('_method', 'DELETE'); return f; })() });
                location.href = location.pathname;
            };
            $('genBtn').onclick = async function () {
                if (!board) return;
                if (cards.length && !confirm('Mavjud kartochkalar (joylashuvlari bilan) o\'chirilib QAYTA yaratiladi. Davom etamizmi?')) return;
                this.disabled = true; $('boardMsg').textContent = 'Yaratilmoqda...';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/generate', 'POST', {});
                    $('boardMsg').textContent = j.created + ' ta kartochka yaratildi';
                    await loadBoard(board.id);
                } catch (e) { $('boardMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };
            // Fan nomlarini ishchi rejadagi joriy nomga yangilash (qayta yaratmasdan)
            $('refreshNamesBtn').onclick = async function () {
                if (!board) return;
                this.disabled = true; $('boardMsg').textContent = 'Fan nomlari yangilanmoqda...';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/refresh-names', 'POST', {});
                    $('boardMsg').textContent = (j.updated || 0) + ' ta kartochka nomi yangilandi';
                    await loadBoard(board.id);
                } catch (e) { $('boardMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };

            // aSc uslubidagi boshqaruv tugmalari (doska tanlash qatorida) — bitta guruh sifatida ko'rsatish/yashirish
            function toggleAscToolbar(show) {
                document.querySelectorAll('[data-asc-toolbar]').forEach(el => el.classList.toggle('hidden', !show));
            }

            function hideBoard() {
                board = null;
                $('genBtn').classList.add('hidden'); $('delBoardBtn').classList.add('hidden');
                $('refreshNamesBtn').classList.add('hidden');
                toggleAscToolbar(false);
                $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
            }

            async function loadBoard(id) {
                // Boshqa doskaga o'tayotgan bo'lsak — eski doskaga oid holatni tozalaymiz
                const switching = !board || String(board.id) !== String(id);
                const j = await api(BASE + '/boards/' + id + '/data');
                board = j.board; cards = j.cards;
                buildSubjectColors();
                grids = {};
                (j.grids || []).forEach(g => { grids[g.specialty_name + '|' + g.course] = g; });
                // Hafta bo'yicha istisnolar
                overrides = {};
                (j.overrides || []).forEach(o => { overrides[o.card_id + '|' + o.week] = { day: o.day, pair: o.pair, cancelled: o.cancelled }; });
                // Eski kartaga ishora qiluvchi tanlovlarni bekor qilamiz (eski doskaga yozib
                // yubormaslik uchun); doska almashsa yo'nalish tanlovini ham qayta tanlaymiz
                selected = null; modalCard = null;
                $('cardModal').classList.add('hidden');
                if (switching) curSpec = null;
                $('boardSel').value = String(board.id);
                $('genBtn').classList.remove('hidden');
                $('refreshNamesBtn').classList.remove('hidden');
                $('delBoardBtn').classList.remove('hidden');
                toggleAscToolbar(true);
                buildSpecList();
                if (!cards.length) {
                    $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
                    $('boardMsg').textContent = 'Kartochkalar hali yaratilmagan — "Kartochkalarni yaratish"ni bosing.';
                    return;
                }
                $('boardMsg').textContent = '';
                $('specBar').classList.remove('hidden'); $('mainArea').classList.remove('hidden');
                if ((!curSpec || !specList.find(s => s.key === curSpec.key)) && specList.length) curSpec = specList[0];
                fillSpecControls();
                fillGridInputs();
                renderAll();
            }

            function buildSpecList() {
                const seen = {};
                specList = [];
                cards.forEach(c => {
                    // Kalitga fakultetni ham qo'shamiz — bir yo'nalish (mas.
                    // "davolash ishi") bir nechta fakultetда bo'lса ham
                    // ular alohida qolsin (aks holda biri ikkinchisini yutadi).
                    const fac = c.faculty_name || '';
                    const k = fac + '|' + c.specialty_name + '|' + c.course;
                    if (!seen[k]) { seen[k] = 1; specList.push({ key: k, specialty_name: c.specialty_name, course: c.course, faculty: fac }); }
                });
                specList.sort((a, b) =>
                    (a.faculty + '|' + a.specialty_name + a.course).localeCompare(b.faculty + '|' + b.specialty_name + b.course, 'uz'));
            }

            // ===== Kaskadli tanlov: Fakultet → Yo'nalish → Kurs =====
            const facLabel = f => f || '— (fakultetsiz)';
            const facultiesList = () => [...new Set(specList.map(s => s.faculty))].sort((a, b) => a.localeCompare(b, 'uz'));
            // allFac — barcha fakultetlar (bir yo'nalish); allSpec — barcha
            // fakultet + barcha yo'nalish (shu kursda). Ro'yxatlar shunga qarab
            // fakultet/yo'nalishга bog'lanmaydi.
            const facMatch = (s, fac) => allFac || allSpec || s.faculty === fac;
            const dirsOf = fac => [...new Set(specList.filter(s => facMatch(s, fac)).map(s => s.specialty_name))]
                .sort((a, b) => a.localeCompare(b, 'uz'));
            const coursesOf = (fac, dir) => [...new Set(specList
                .filter(s => allSpec || (facMatch(s, fac) && s.specialty_name === dir)).map(s => s.course))].sort((a, b) => a - b);

            // curSpec ni (fac, dir, course) bo'yicha aniqlaymiz. course berilmasa
            // shu yo'nalishning birinchi kursi olinadi.
            function setCurSpec(fac, dir, course) {
                const match = s => facMatch(s, fac) && (allSpec || s.specialty_name === dir);
                let found = specList.find(s => match(s) && s.course === course);
                if (!found) found = specList.find(match);
                if (found) curSpec = found;
                return found;
            }
            // Uch selektorni curSpec holatiga qarab (yoki birinchi mavjudga) to'ldiradi.
            function fillSpecControls() {
                const facs = facultiesList();
                const curFac = (curSpec && facs.includes(curSpec.faculty)) ? curSpec.faculty : (facs[0] ?? '');
                $('facSel').innerHTML = facs.map(f => '<option value="' + esc(f) + '">' + esc(facLabel(f)) + '</option>').join('');
                $('facSel').value = curFac;
                fillDirControls(curFac);
            }
            function fillDirControls(fac) {
                const dirs = dirsOf(fac);
                const curDir = (curSpec && (allFac || curSpec.faculty === fac) && dirs.includes(curSpec.specialty_name)) ? curSpec.specialty_name : (dirs[0] ?? '');
                $('dirSel').innerHTML = dirs.map(d => '<option value="' + esc(d) + '">' + esc(d) + '</option>').join('');
                $('dirSel').value = curDir;
                fillCourseControls(fac, curDir);
            }
            function fillCourseControls(fac, dir) {
                const courses = coursesOf(fac, dir);
                // Avvalgi tanlangan kurslardan mavjudlarini saqlaymiz; bo'lmasa primary/birinchi
                let sel = new Set([...selectedCourses].filter(c => courses.includes(c)));
                if (!sel.size) {
                    const primary = (curSpec && courses.includes(curSpec.course)) ? curSpec.course : (courses[0] ?? null);
                    if (primary != null) sel = new Set([primary]);
                }
                selectedCourses = sel;
                $('courseChecks').innerHTML = courses.map(c =>
                    '<label class="flex items-center gap-0.5 text-xs text-gray-700 cursor-pointer"><input type="checkbox" class="course-chk rounded border-gray-300" value="' + c + '"' +
                    (sel.has(c) ? ' checked' : '') + '>' + c + '</label>').join('') || '<span class="text-xs text-gray-400">—</span>';
                const primary = [...sel].sort((a, b) => a - b)[0] ?? (courses[0] ?? null);
                setCurSpec(fac, dir, primary);
            }
            // Kurs checkboxlari — bir nechta kursni tanlash
            $('courseChecks').addEventListener('change', ev => {
                if (!ev.target.classList || !ev.target.classList.contains('course-chk')) return;
                const c = +ev.target.value;
                if (ev.target.checked) selectedCourses.add(c); else selectedCourses.delete(c);
                if (!selectedCourses.size) { selectedCourses.add(c); ev.target.checked = true; } // kamida bitta
                const primary = [...selectedCourses].sort((a, b) => a - b)[0];
                setCurSpec($('facSel').value, $('dirSel').value, primary);
                selected = null; fillGridInputs(); renderAll();
            });

            $('facSel').onchange = function () {
                fillDirControls(this.value);   // yo'nalish + kurs + curSpec yangilanadi
                selected = null; fillGridInputs(); renderAll();
            };
            $('dirSel').onchange = function () {
                fillCourseControls($('facSel').value, this.value);
                selected = null; fillGridInputs(); renderAll();
            };
            $('allFacChk').onchange = function () {
                allFac = this.checked;
                // Ketma-ket rejimda fakultet selektori ta'sir qilmaydi; yo'nalish/kurs
                // ro'yxatlari barcha fakultetlar bo'yicha qayta to'ldiriladi.
                $('facSel').disabled = allFac || allSpec;
                selected = null;
                fillDirControls($('facSel').value);
                fillGridInputs(); renderAll();
            };
            $('allSpecChk').onchange = function () {
                allSpec = this.checked;
                // Barcha fakultet + yo'nalish: fakultet/yo'nalish selektorlari va
                // "Barcha fakultetlar" checkbox'i o'chiriladi; faqat kurs qoladi.
                $('facSel').disabled = allSpec || allFac;
                $('dirSel').disabled = allSpec;
                $('allFacChk').disabled = allSpec;
                selected = null;
                fillDirControls($('facSel').value);
                fillGridInputs(); renderAll();
            };

            // ===== Panjara sozlamasi (yo'nalish+kurs bo'yicha) =====
            function curGrid() {
                const g = curSpec && grids[curSpec.specialty_name + '|' + curSpec.course];
                return g || { days: board.days, pairs_per_day: board.pairs_per_day, weeks: board.weeks };
            }
            function fillGridInputs() {
                const g = curGrid();
                $('gsDays').value = g.days; $('gsPairs').value = g.pairs_per_day; $('gsWeeks').value = g.weeks;
                fillWeekSel();
            }

            // ===== Hafta tanlash (individual haftalar) =====
            function fillWeekSel() {
                const w = +curGrid().weeks || +board.weeks || 15;
                if (curWeek > w) curWeek = 0;
                let opts = '<option value="0">Barcha haftalar (shablon)</option>';
                for (let i = 1; i <= w; i++) opts += '<option value="' + i + '">' + i + '-hafta</option>';
                $('weekSel').innerHTML = opts;
                $('weekSel').value = String(curWeek);
            }
            $('weekSel').onchange = function () {
                curWeek = +this.value || 0;
                selected = null;
                renderAll();
            };
            $('viewMode').onchange = function () {
                viewMode = this.value;
                selected = null;
                renderGrid();
            };
            // Kartaning tanlangan haftadagi (yoki shablon) effektiv joylashuvi: {day,pair} yoki null
            function effPlace(c) {
                if (!curWeek) return c.day ? { day: c.day, pair: c.pair } : null;
                const ov = overrides[c.id + '|' + curWeek];
                if (ov) return ov.cancelled ? null : { day: ov.day, pair: ov.pair };
                return c.day ? { day: c.day, pair: c.pair } : null;
            }
            // Karta shu haftada shablondan farq qiladimi (individual)?
            const hasWeekOverride = c => curWeek && !!overrides[c.id + '|' + curWeek];
            $('gsSave').onclick = async function () {
                if (!curSpec) return;
                this.disabled = true;
                const weeksBefore = curGrid().weeks;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/grid', 'POST', {
                        specialty_name: curSpec.specialty_name, course: curSpec.course,
                        days: $('gsDays').value, pairs_per_day: $('gsPairs').value, weeks: $('gsWeeks').value,
                    });
                    if (j.regenerated || +$('gsWeeks').value !== +weeksBefore) {
                        await loadBoard(board.id);   // kartalar qayta yaratildi — to'liq yangilash
                    } else {
                        grids[curSpec.specialty_name + '|' + curSpec.course] = {
                            specialty_name: curSpec.specialty_name, course: curSpec.course,
                            days: +$('gsDays').value, pairs_per_day: +$('gsPairs').value, weeks: +$('gsWeeks').value,
                        };
                        // Doskadan kelgan bo'shatilgan joylashuvlarni yangilash uchun qayta yuklaymiz
                        await loadBoard(board.id);
                    }
                } catch (e) { alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };

            // ===== Avtomatik (optimal) joylashtirish =====
            async function doAutoPlace() {
                if (!board || !curSpec) return;
                const whole = $('autoScope').checked;
                const typeLbl = { all: '', lecture: ' · faqat ma\'ruza', practice: ' · faqat amaliy' }[typeFilter];
                // Qamrov: butun doska / shu kursning barcha yo'nalishlari (allSpec) /
                // shu yo'nalish+kurs (barcha fakultetlar bilan yoki bittasi).
                const scopeLabel = (whole ? 'Butun doska'
                    : allSpec ? ('Barcha yo\'nalishlar · ' + curSpec.course + '-kurs')
                    : (curSpec.specialty_name + ' · ' + curSpec.course + '-kurs')) + typeLbl;
                if ($('autoReset').checked &&
                    !confirm(scopeLabel + ' bo\'yicha mavjud joylashuvlar bo\'shatilib qaytadan joylanadi. Davom etamizmi?')) return;
                $('autoBtn').disabled = true; $('autoMsg').textContent = 'Joylashtirilmoqda...';
                try {
                    const body = { reset: $('autoReset').checked ? 1 : 0, assign_rooms: $('autoRooms').checked ? 1 : 0,
                        lecture_rooms: $('autoLecRooms').checked ? 1 : 0 };
                    if (whole) { /* butun doska — qamrov yubormaymiz */ }
                    else if (allSpec) { body.course = curSpec.course; }
                    else { body.specialty_name = curSpec.specialty_name; body.course = curSpec.course; }
                    if (typeFilter !== 'all') body.training_type = typeFilter;
                    const j = await api(BASE + '/boards/' + board.id + '/auto-place', 'POST', body);
                    await loadBoard(board.id);
                    $('autoMsg').textContent = 'Joylandi: ' + j.placed +
                        (j.unplaced ? (' · joy topilmadi: ' + j.unplaced) : '') +
                        (j.rooms_assigned ? (' · xona biriktirildi: ' + j.rooms_assigned) : '');
                    // Hammasi allaqachon joylashgan va reset belgilanmagan — yangi
                    // sozlama bo'yicha qayta taqsimlash uchun yo'l ko'rsatamiz.
                    if (!$('autoReset').checked && !j.placed && !j.unplaced) {
                        $('autoMsg').textContent = 'Hammasi joylashgan. Yangi sozlama bo\'yicha qayta joylash kerak.';
                        if (confirm('Barcha kartalar allaqachon joylashgan.\nYangi sozlama (bir kunga / ketma-ket) bo\'yicha mavjud joylashuvlarni bo\'shatib QAYTA joylaymizmi?')) {
                            $('autoReset').checked = true;
                            $('autoBtn').disabled = false;
                            return doAutoPlace();
                        }
                    }
                } catch (e) { $('autoMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                $('autoBtn').disabled = false;
            }
            $('autoBtn').onclick = doAutoPlace;

            // Ko'rinayotgan qamrovdagi barcha joylashuvlarni bo'shatish (panelga qaytarish)
            $('unplaceBtn').onclick = async function () {
                if (!board || !curSpec) return;
                const whole = $('autoScope').checked;
                const scopeLabel = whole ? 'Butun doska'
                    : allSpec ? ('Barcha yo\'nalishlar · ' + curSpec.course + '-kurs')
                    : (curSpec.specialty_name + ' · ' + curSpec.course + '-kurs');
                if (!confirm(scopeLabel + ' bo\'yicha barcha joylashuvlar bo\'shatilib, kartochkalar panelga qaytariladi. Davom etamizmi?')) return;
                this.disabled = true; $('autoMsg').textContent = 'Bo\'shatilmoqda...';
                try {
                    const body = {};
                    if (whole) { /* butun doska */ }
                    else if (allSpec) { body.course = curSpec.course; }
                    else { body.specialty_name = curSpec.specialty_name; body.course = curSpec.course; }
                    if (typeFilter !== 'all') body.training_type = typeFilter;
                    const j = await api(BASE + '/boards/' + board.id + '/unplace', 'POST', body);
                    await loadBoard(board.id);
                    $('autoMsg').textContent = (j.unplaced || 0) + ' ta joylashuv bo\'shatildi';
                } catch (e) { $('autoMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };

            // ===== Yordamchilar =====
            // Fakultet cheklovi: allFac — shu yo'nalish+kursдаги barcha
            // fakultetlar; allSpec — shu kursдаги barcha fakultet + barcha
            // yo'nalish (ketma-ket).
            let allFac = false;
            let allSpec = false;
            let viewMode = 'group';   // group | teacher | room | subject (jadval kesimi)
            // Kurs mosligi — tanlangan kurslar (checkbox); bo'sh bo'lsa primary curSpec.course
            const inCourses = c => selectedCourses.size ? selectedCourses.has(c.course) : (curSpec && c.course === curSpec.course);
            const specCards = () => cards.filter(c => {
                if (!curSpec || !inCourses(c)) return false;
                if (allSpec) return true;
                if (c.specialty_name !== curSpec.specialty_name) return false;
                return allFac || (c.faculty_name || '') === curSpec.faculty;
            });
            const cardGroups = c => c.training_type === 'lecture' ? (c.group_names || []) : (c.group_name ? [c.group_name] : []);
            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy) — panel, panjara, stat va avtomatik joylashga ta'sir qiladi
            let typeFilter = 'all';
            const typeVisible = c => typeFilter === 'all' || c.training_type === typeFilter;
            const visibleSpecCards = () => specCards().filter(typeVisible);

            function buildGroupRows() {
                groupRows = [];
                const seen = {};
                specCards().forEach(c => {
                    cardGroups(c).forEach(g => {
                        if (!seen[g]) { seen[g] = 1; groupRows.push({ oqim_label: c.oqim_label || '', lang: c.lang || 'uz', group: g, faculty: c.faculty_name || '', specialty: c.specialty_name || '', course: c.course }); }
                    });
                });
                // Fakultet → yo'nalish → kurs → oqim → guruh: bir blok ketma-ket tursin.
                const sk = x => x.faculty + '|' + x.specialty + '|' + x.course + '|' + x.oqim_label + x.group;
                groupRows.sort((a, b) => sk(a).localeCompare(sk(b), undefined, { numeric: true }));
            }

            // Karta uzunligi yarim-slot birligida (1=0.5 para, 2=1 para ...). Sukut 2.
            const cardLen = c => Math.max(1, parseInt(c.len_half) || 2);
            // Konflikt: karta (day,pair) ga qo'yilsa — sabablar ro'yxati (tanlangan hafta effektiv joylashuvi bo'yicha).
            // `pair` — yarim-slot indeksi; dars cardLen(card) ta yarim-slotni egallaydi,
            // shu oraliq [pair, pair+len) boshqa kartaning oralig'i bilan kesishsa — band.
            function conflictsAt(card, day, pair) {
                const my = cardGroups(card);
                const errs = [];
                const a0 = pair, a1 = pair + cardLen(card);   // [a0, a1)
                cards.forEach(o => {
                    if (o.id === card.id) return;
                    const pl = effPlace(o);
                    if (!pl || pl.day !== day) return;
                    const b0 = pl.pair, b1 = pl.pair + cardLen(o);   // [b0, b1)
                    if (a0 >= b1 || b0 >= a1) return;   // yarim-slot oraliqlari kesishmasa — konflikt yo'q
                    if (o.specialty_name === card.specialty_name && o.course === card.course) {
                        const ov = cardGroups(o).filter(g => my.includes(g));
                        if (ov.length) errs.push('Guruh band: ' + ov.join(','));
                    }
                    if (card.teacher_id && o.teacher_id === card.teacher_id) errs.push("O'qituvchi band: " + o.teacher_name);
                    if (card.auditorium_code && o.auditorium_code === card.auditorium_code) errs.push('Auditoriya band: ' + o.auditorium_name);
                });
                return errs;
            }

            // ===== Joylash (bosish yoki drag-and-drop uchun umumiy) =====
            async function placeOneCard(card, d, p) {
                if (!curWeek) {
                    await api(BASE + '/cards/' + card.id + '/place', 'POST', { day: d, pair: p });
                    card.day = d; card.pair = p;
                } else {
                    await api(BASE + '/cards/' + card.id + '/week-override', 'POST',
                        { week: curWeek, action: 'move', day: d, pair: p });
                    overrides[card.id + '|' + curWeek] = { day: d, pair: p, cancelled: false };
                }
            }
            async function placeCardAt(card, d, p) {
                try { await placeOneCard(card, d, p); selected = null; renderAll(); }
                catch (e) { alert('Konflikt: ' + e.message); }
            }
            // Birlashtirilgan (ketma-ket) blok — kartalarni ketma-ket paralarga joylaymiz
            async function placeBlockAt(ids, d, p) {
                try {
                    for (let k = 0; k < ids.length; k++) {
                        const card = cards.find(x => x.id === ids[k]);
                        if (card) await placeOneCard(card, d, p + k);
                    }
                    selected = null; renderAll();
                } catch (e) { alert('Konflikt: ' + e.message); }
            }
            // ===== Drag-and-drop holati (blok = bir yoki bir necha ketma-ket karta) =====
            let dragCardIds = null;
            function startDrag(ids, ev) {
                dragCardIds = Array.isArray(ids) ? ids.slice() : [ids];
                if (ev && ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    try { ev.dataTransfer.setData('text/plain', dragCardIds.join(',')); } catch (e) { /* ba'zi brauzerlar */ }
                }
            }
            // Sudrash tugagach holatni tozalash (tashlanmagan bo'lsa ham)
            document.addEventListener('dragend', () => {
                dragCardIds = null;
                document.querySelectorAll('.drag-ok, .drag-bad').forEach(el => el.classList.remove('drag-ok', 'drag-bad'));
            });

            // ===== Faol katak: sichqoncha ustidan o'tganda belgilash + strelkalar bilan yurish =====
            let activeCell = null;
            function setActiveCell(td) {
                if (activeCell === td) return;
                if (activeCell) activeCell.classList.remove('tt-active');
                activeCell = td || null;
                if (activeCell) activeCell.classList.add('tt-active');
            }
            // Sichqoncha katak ustidan o'tganda faollashtiramiz (delegatsiya — qayta render'da ham ishlaydi)
            $('grid').addEventListener('mouseover', ev => {
                const td = ev.target.closest && ev.target.closest('#grid td[data-day]');
                if (td) setActiveCell(td);
            });
            // Strelkalar — faol katakni yo'nalish bo'yicha eng yaqin katakka ko'chiramiz
            document.addEventListener('keydown', ev => {
                if (!activeCell || !activeCell.isConnected) return;
                const dir = { ArrowRight: 'r', ArrowLeft: 'l', ArrowDown: 'd', ArrowUp: 'u' }[ev.key];
                if (!dir) return;
                const t = ev.target;
                if (t && (t.tagName === 'INPUT' || t.tagName === 'SELECT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
                ev.preventDefault();
                const cells = [...document.querySelectorAll('#grid td[data-day]')];
                const r = activeCell.getBoundingClientRect();
                const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                let best = null, bestScore = Infinity;
                for (const td of cells) {
                    if (td === activeCell) continue;
                    const rr = td.getBoundingClientRect();
                    const x = rr.left + rr.width / 2, y = rr.top + rr.height / 2;
                    const dx = x - cx, dy = y - cy;
                    let ok, score;
                    if (dir === 'r') { ok = dx > 3; score = dx + Math.abs(dy) * 3; }
                    else if (dir === 'l') { ok = dx < -3; score = -dx + Math.abs(dy) * 3; }
                    else if (dir === 'd') { ok = dy > 3; score = dy + Math.abs(dx) * 3; }
                    else { ok = dy < -3; score = -dy + Math.abs(dx) * 3; }
                    if (ok && score < bestScore) { bestScore = score; best = td; }
                }
                if (best) { setActiveCell(best); best.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
            });

            // ===== Render =====
            function renderAll() { buildGroupRows(); renderPanel(); renderGrid(); renderStats(); updateCheckBadge(); }

            function renderStats() {
                const sc = visibleSpecCards();
                const placed = sc.filter(c => effPlace(c)).length;
                const totPlaced = cards.filter(c => effPlace(c)).length;
                const typeLbl = { all: '', lecture: ' · faqat ma\'ruza', practice: ' · faqat amaliy' }[typeFilter];
                const weekLbl = curWeek ? ' · ' + curWeek + '-hafta' : '';
                $('statChips').innerHTML =
                    '<span class="rounded-md px-2 py-1 bg-green-50 text-green-700">Joylashgan: <b>' + placed + '/' + sc.length + '</b>' + typeLbl + weekLbl + '</span>' +
                    '<span class="rounded-md px-2 py-1 bg-gray-100 text-gray-600">Doska bo\'yicha: <b>' + totPlaced + '/' + cards.length + '</b></span>';
                $('unplacedCount').textContent = (sc.length - placed) + ' ta';
                $('weekHint').classList.toggle('hidden', !curWeek);
            }

            function cardLabel(c, short) {
                const t = c.training_type === 'lecture' ? 'M' : 'A';
                const name = short && c.subject_name.length > 26 ? c.subject_name.slice(0, 26) + '…' : c.subject_name;
                return '<b>[' + t + ']</b> ' + esc(name);
            }

            // Joylashmagan kartalar — pastda gorizontal panel (aSc uslubida): tekis
            // ravishda ketma-ket chiqadi (fan bo'yicha saralangan, guruhlash chizig'i yo'q).
            function renderPanel() {
                const un = visibleSpecCards().filter(c => !effPlace(c))
                    .sort((a, b) => a.subject_name.localeCompare(b.subject_name, 'uz'));
                $('cardPanel').innerHTML = un.map(c =>
                    '<div class="pn-card ' + (c.training_type === 'lecture' ? 'lec' : 'prc') + (selected && selected.id === c.id ? ' sel' : '') +
                    ' lang-' + (c.lang || 'uz') + '" draggable="true" style="' + subjStyle(c) + 'border-left-width:3px;" data-id="' + c.id + '" title="' + esc(c.subject_name) + '">' +
                    cardLabel(c, true) +
                    '<div class="text-[9px] text-gray-500">' +
                    (c.training_type === 'lecture'
                        ? esc(c.oqim_label || 'oqim') + ' · ' + (c.group_names || []).length + ' guruh · ' + c.students + ' t.'
                        : esc(c.group_name || '') + ' · ' + c.students + ' t.') +
                    (c.teacher_name ? ' · 👤' : '') + (c.auditorium_name ? ' · 🚪' : '') +
                    '</div></div>'
                ).join('') || '<div class="text-xs text-gray-400 p-1">Hammasi joylashgan 🎉</div>';

                document.querySelectorAll('.pn-card').forEach(el => {
                    el.onclick = () => {
                        const c = cards.find(x => x.id === +el.dataset.id);
                        selected = (selected && selected.id === c.id) ? null : c;
                        renderPanel(); renderGrid();
                    };
                    el.addEventListener('dragstart', ev => startDrag(+el.dataset.id, ev));
                });
            }

            // Jadval kesimi (faqat ko'rish): o'qituvchi / auditoriya / fan ustunlari
            function renderGridCross(mode) {
                const placed = visibleSpecCards().filter(c => effPlace(c));
                const keyOf = c => mode === 'teacher' ? (c.teacher_name || '— (biriktirilmagan)')
                    : mode === 'room' ? (c.auditorium_name || '— (xona yo\'q)')
                    : c.subject_name;
                const cols = [...new Set(placed.map(keyOf))].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
                let D = board.days;
                Object.values(grids).forEach(gg => { D = Math.max(D, gg.days); });
                const sched = boardSchedule().filter(it => it.type === 'pair');
                const P = sched.length || board.pairs_per_day;
                const dayNames = boardDayNames();
                const pairTime = {};
                sched.forEach((it, i) => { pairTime[it.no || (i + 1)] = it; });

                const startIdx = {}, consumed = {};
                placed.forEach(c => {
                    const pl = effPlace(c);
                    const k = keyOf(c) + '|' + pl.day + '|' + pl.pair;
                    (startIdx[k] = startIdx[k] || []).push(c);
                });

                if (!cols.length) {
                    $('grid').innerHTML = '<div class="p-4 text-sm text-gray-400">Bu kesimda joylashgan darslar yo\'q.</div>';
                    activeCell = null; return;
                }
                const rowEndCls = p => p === P ? ' tt-dayend' : (p % 2 === 0 ? ' tt-paraend' : '');
                let h = '<thead><tr><th class="tt-corner px-1 py-1">Kun</th><th class="tt-corner px-1 py-1" style="left:28px">Para</th>';
                cols.forEach(c => h += '<th class="tt-grp px-2 py-1">' + esc(c) + '</th>');
                h += '</tr></thead><tbody>';
                for (let d = 1; d <= D; d++) {
                    for (let p = 1; p <= P; p++) {
                        h += '<tr>';
                        if (p === 1) h += '<td class="tt-day" rowspan="' + P + '">' + esc(dayNames[d - 1] || ('Kun ' + d)) + '</td>';
                        const pt = pairTime[p];
                        h += '<td class="tt-para' + rowEndCls(p) + '"><div class="tt-para-name">' + esc(pt ? (pt.name || pt.abbr || p) : p) + '</div>' +
                            (pt && (pt.start || pt.end) ? '<div class="tt-para-time">' + esc(pt.start) + '<br>' + esc(pt.end) + '</div>' : '') + '</td>';
                        cols.forEach(col => {
                            const key = col + '|' + d + '|' + p;
                            if (consumed[key]) return;
                            const list = startIdx[key] || [];
                            if (!list.length) { h += '<td class="tt-cell' + rowEndCls(p) + '" data-day="' + d + '" data-pair="' + p + '"></td>'; return; }
                            const vs = Math.max(...list.map(cardLen));
                            for (let k = 1; k < vs; k++) consumed[col + '|' + d + '|' + (p + k)] = 1;
                            const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                            const inner = list.map(c => {
                                const extra = mode === 'teacher' ? [c.group_name || c.oqim_label, c.auditorium_name]
                                    : mode === 'room' ? [c.group_name || c.oqim_label, c.teacher_name]
                                    : [c.group_name || c.oqim_label, c.teacher_name];
                                const sub = extra.filter(Boolean).join(' · ');
                                const isLec = c.training_type === 'lecture';
                                const st = isLec ? 'background:#fde68a;' : ('background-color:' + subjColor(c.subject_name).bg + ';');
                                return '<div class="tt-chip ' + (isLec ? 'lec' : 'prc') + '" style="' + st + '">' + cardLabel(c, true) +
                                    (sub ? '<div class="text-[9px] text-gray-600">' + esc(sub) + '</div>' : '') + '</div>';
                            }).join('');
                            // Bir katakda bir nechta (o'qituvchi/xona kesimida) — to'qnashuv
                            const conflict = (list.length > 1 && mode !== 'subject') ? ' style="outline:2px solid #ef4444;outline-offset:-2px"' : '';
                            h += '<td class="tt-cell' + rowEndCls(p + vs - 1) + '"' + rs + ' data-day="' + d + '" data-pair="' + p + '"' + conflict + '>' + inner + '</td>';
                        });
                        h += '</tr>';
                    }
                }
                h += '</tbody>';
                $('grid').innerHTML = h;
                activeCell = null;
            }

            function renderGrid() {
                if (viewMode !== 'group') { renderGridCross(viewMode); return; }
                const g = curGrid();
                let D = g.days;
                // Yarim-slot (qator) soni — doska qo'ng'iroq jadvalidagi "pair" elementlar
                // soni (bir "pair" = bir yarim-slot). Yo'nalish bo'yicha faqat kun soni farq
                // qilishi mumkin, para soni butun doska uchun bir xil.
                let P = boardSchedule().filter(it => it.type === 'pair').length || g.pairs_per_day;
                // allSpec — turli yo'nalishlarning kun soni har xil bo'lishi mumkin; eng
                // kattasini olamiz (barcha kunlar sig'sin).
                if (allSpec) {
                    [...new Set(specCards().map(c => c.specialty_name + '|' + c.course))].forEach(k => {
                        const gg = grids[k]; if (gg) { D = Math.max(D, gg.days); }
                    });
                }
                const dayNames = boardDayNames();

                // Ustunlar: guruhlar oqim bo'yicha guruhlangan (groupRows tartibida)
                const oqimCols = [];
                let curO = null;
                groupRows.forEach(gr => {
                    const lab = gr.oqim_label || '';
                    const fac = gr.faculty || '';
                    const spec = gr.specialty || '';
                    const crs = gr.course;
                    // Fakultet / yo'nalish / kurs o'zgarsa ham yangi oqim bloki (turli
                    // fakultet/yo'nalish/kursning bir xil nomli oqimlari birlashib ketmasin).
                    if (!curO || curO.label !== lab || curO.faculty !== fac || curO.specialty !== spec || curO.course !== crs) {
                        curO = { label: lab, faculty: fac, specialty: spec, course: crs, groups: [] }; oqimCols.push(curO);
                    }
                    curO.groups.push(gr.group);
                });

                // Para → nomi/qisqartma va boshlanish-tugash vaqti (sozlangan qo'ng'iroq jadvalidan).
                // Panjaradagi p-chi para = jadvaldagi p-chi "pair" element (tartib bo'yicha).
                const pairTime = {};
                boardSchedule().filter(it => it.type === 'pair').forEach((it, i) => {
                    pairTime[i + 1] = { name: it.name || '', abbr: it.abbr || '', start: it.start || '', end: it.end || '' };
                });

                // Joylashgan kartalar indeksi: group|day|pair → karta. `pair` — yarim-slot
                // indeksi; dars cardLen(c) ta yarim-slotni egallaydi, shuning uchun karta
                // qamragan HAR bir yarim-slotga yoziladi (band/konflikt/rowspan tekshiruvi uchun).
                const placedIdx = {};
                visibleSpecCards().forEach(c => {
                    const pl = effPlace(c);
                    if (!pl) return;
                    const len = cardLen(c);
                    cardGroups(c).forEach(gg => {
                        for (let k = 0; k < len; k++) placedIdx[gg + '|' + pl.day + '|' + (pl.pair + k)] = c;
                    });
                });

                // Vertikal qamrov: karta o'zining cardLen(c) yarim-slotini to'ldiradi; qo'shimcha
                // ravishda ketma-ket bir xil fan kartalari (masalan "para qo'shish" bilan)
                // bitta katakka (rowspan) birlashtiriladi. vConsumed — yuqoridagi rowspan
                // qamragan (chiqarilmaydigan) kataklar. vChain — jami yarim-slot uzunligi
                // va tarkibidagi karta id'lari (badge uchun).
                const vConsumed = {};
                const vChain = (grp, d, p, c) => {
                    const ids = [];
                    let span = 0, cur = p;
                    while (true) {
                        const n = placedIdx[grp + '|' + d + '|' + cur];
                        if (!n || n.training_type !== c.training_type || n.subject_name !== c.subject_name) break;
                        ids.push(n.id);
                        span += cardLen(n);
                        cur += cardLen(n);
                    }
                    return { span: span || cardLen(c), ids: ids.length ? ids : [c.id] };
                };

                const chipHtml = (c, ids) => {
                    const merged = ids && ids.length > 1;
                    const mids = merged ? ' data-merge-ids="' + ids.join(',') + '"' : '';
                    const badge = merged ? '<span class="tt-merge-badge">' + ids.length + ' para</span>' : '';
                    return '<div class="tt-chip ' + (c.training_type === 'lecture' ? 'lec' : 'prc') +
                        (selected && selected.id === c.id ? ' sel' : '') + '" style="' + subjStyle(c) +
                        '" data-chip="' + c.id + '"' + mids + ' title="' +
                        esc(c.subject_name + (c.teacher_name ? ' · ' + c.teacher_name : '') + (c.auditorium_name ? ' · ' + c.auditorium_name : '')) + '">' +
                        cardLabel(c, true) + badge +
                        (c.teacher_name ? '<div class="text-[9px] text-gray-600">' + esc(c.teacher_name) + '</div>' : '') +
                        (c.auditorium_name ? '<div class="text-[9px] text-gray-500">' + esc(c.auditorium_name) + '</div>' : '') +
                        '</div>';
                };

                // Asos guruh kaliti: til qo'shimchasi "(o'z)" va a/b pastki guruh harfini olib tashlab
                // (masalan "1K-01a (o'z)" → "1K-01"). Bir asos guruh = a va b pastki guruhlari.
                const baseKey = gn => String(gn).replace(/\s*\([^)]*\)\s*$/, '').replace(/[a-z]$/i, '');
                // Ustun chap chegara sinfi: oqim boshi (qo'sh chiziq) yoki asos guruh boshi (qalin chiziq)
                const colBorder = (oqimIdx, gi, groups) => {
                    if (gi === 0) return oqimIdx > 0 ? ' sep-oqim' : '';
                    return baseKey(groups[gi]) !== baseKey(groups[gi - 1]) ? ' sep-base' : '';
                };

                // Fakultet sarlavhasi (Excel dars jadvali kabi): guruh → fakultet xaritasidan
                // qo'shni bir xil fakultet ustunlari bitta blokka birlashtiriladi.
                const facOf = {};
                groupRows.forEach(gr => { facOf[gr.group] = gr.faculty || ''; });
                const facRuns = [];
                oqimCols.forEach(o => o.groups.forEach(gr => {
                    const f = facOf[gr] || '';
                    const last = facRuns[facRuns.length - 1];
                    if (last && last.faculty === f) last.span++; else facRuns.push({ faculty: f, span: 1 });
                }));
                const showFac = facRuns.some(r => r.faculty);
                const corSpan = showFac ? 3 : 2;

                // Sarlavha: Kun | Para | [fakultet] | oqim | guruhlar
                let h = '<thead><tr>' +
                    '<th class="tt-corner px-1 py-1" rowspan="' + corSpan + '" style="width:26px;min-width:26px;max-width:26px">Kun</th>' +
                    '<th class="tt-corner px-1 py-1" rowspan="' + corSpan + '" style="left:26px">Para</th>';
                if (showFac) {
                    facRuns.forEach((r, ri) => h += '<th class="tt-fac px-2 py-1' + (ri > 0 ? ' sep-oqim' : '') + '" colspan="' + r.span + '">' + esc(r.faculty || '—') + '</th>');
                    h += '</tr><tr>';
                }
                const multiCourse = selectedCourses.size > 1;
                oqimCols.forEach((o, oi) => h += '<th class="tt-oqim px-2 py-1' + (oi > 0 ? ' sep-oqim' : '') + '" colspan="' + o.groups.length + '">' +
                    esc((allSpec && o.specialty ? o.specialty + ' · ' : '') + (multiCourse ? o.course + '-kurs · ' : '') + (o.label || '—')) + '</th>');
                h += '</tr><tr>';
                oqimCols.forEach((o, oi) => o.groups.forEach((gr, gi) => h += '<th class="tt-grp px-2 py-1' + colBorder(oi, gi, o.groups) + '">' + esc(gr) + '</th>'));
                h += '</tr></thead><tbody>';

                // Para ajratuvchi chiziq darajasi: shu qatorda TUGAYDIGAN katak uchun
                // (endP = boshlanish parasi + rowspan - 1). Kun oxiri (endP===P) — eng
                // qalin; juft endP — ikki butun para orasi (qalinroq); toq endP — bitta
                // butun paraning ikki yarmi orasi (sukut, yupqa — qo'shimcha sinf kerak emas).
                const rowEndCls = endP => endP === P ? ' tt-dayend' : (endP % 2 === 0 ? ' tt-paraend' : '');

                for (let d = 1; d <= D; d++) {
                    for (let p = 1; p <= P; p++) {
                        h += '<tr>';
                        if (p === 1) h += '<td class="tt-day" rowspan="' + P + '">' + esc(dayNames[d - 1] || ('Kun ' + d)) + '</td>';
                        const pt = pairTime[p];
                        const paraLabel = pt ? (pt.name || pt.abbr || p) : p;
                        h += '<td class="tt-para' + rowEndCls(p) + '"><div class="tt-para-name">' + esc(paraLabel) + '</div>' +
                            (pt && (pt.start || pt.end) ? '<div class="tt-para-time">' + esc(pt.start) + '<br>' + esc(pt.end) + '</div>' : '') + '</td>';
                        oqimCols.forEach((o, oi) => {
                            // Har katakka kun/para — drag-and-drop tashlash nishoni uchun
                            const dp = ' data-day="' + d + '" data-pair="' + p + '"';
                            // Tanlangan ma'ruza — butun oqimga bitta birlashtirilgan nishon (colspan).
                            // allFac/allSpec rejimida faqat o'z fakulteti+yo'nalishi oqimi yonadi.
                            if (selected && selected.training_type === 'lecture' && (selected.oqim_label || '') === o.label
                                && (selected.faculty_name || '') === o.faculty && (selected.specialty_name || '') === o.specialty
                                && selected.course === o.course) {
                                const occupied = o.groups.some(gr => placedIdx[gr + '|' + d + '|' + p]);
                                if (!occupied) {
                                    const bad = conflictsAt(selected, d, p).length > 0;
                                    h += '<td class="tt-cell ' + (bad ? 'tt-bad' : 'tt-ok') + colBorder(oi, 0, o.groups) + rowEndCls(p) + '" colspan="' + o.groups.length + '"' + dp +
                                        (bad ? '' : ' data-place="' + d + '-' + p + '"') + '></td>';
                                    return;
                                }
                            }
                            // Oddiy yurish: ma'ruzani oqim guruhlari bo'ylab birlashtirish, amaliy/bo'sh — alohida
                            let gi = 0;
                            while (gi < o.groups.length) {
                                const grp = o.groups[gi];
                                // Yuqoridagi vertikal birlashma (rowspan) qamragan bo'lsa — katak chiqarmaymiz
                                if (vConsumed[grp + '|' + d + '|' + p]) { gi++; continue; }
                                const bord = colBorder(oi, gi, o.groups);
                                const c = placedIdx[grp + '|' + d + '|' + p];
                                if (c && c.training_type === 'lecture') {
                                    let span = 1;
                                    while (gi + span < o.groups.length) {
                                        const c2 = placedIdx[o.groups[gi + span] + '|' + d + '|' + p];
                                        if (c2 && c2.id === c.id) span++; else break;
                                    }
                                    // Vertikal: karta o'z uzunligini (yarim-slotlar) egallaydi;
                                    // ketma-ket bir xil fan ma'ruzalari ham birlashtiriladi.
                                    const chain = vChain(o.groups[gi], d, p, c);
                                    const vs = chain.span, ids = chain.ids;
                                    for (let k = 1; k < vs; k++)
                                        for (let gg = gi; gg < gi + span; gg++)
                                            vConsumed[o.groups[gg] + '|' + d + '|' + (p + k)] = 1;
                                    const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                                    h += '<td class="tt-cell tt-lec' + bord + rowEndCls(p + vs - 1) + '" colspan="' + span + '"' + rs + dp + ' style="' + subjStyle(c) + '">' + chipHtml(c, ids) + '</td>';
                                    gi += span;
                                } else if (c) {
                                    // Vertikal: karta o'z uzunligini (yarim-slotlar) egallaydi;
                                    // ketma-ket bir xil fan amaliyotlari ham bitta katakka birlashtiriladi.
                                    const chain = vChain(grp, d, p, c);
                                    const vs = chain.span, ids = chain.ids;
                                    for (let k = 1; k < vs; k++) vConsumed[grp + '|' + d + '|' + (p + k)] = 1;
                                    const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                                    h += '<td class="tt-cell' + bord + rowEndCls(p + vs - 1) + '"' + rs + dp + ' style="' + subjStyle(c) + '">' + chipHtml(c, ids) + '</td>';
                                    gi++;
                                } else {
                                    // Bo'sh katak — tanlangan amaliy uchun nishon bo'lishi mumkin
                                    let cls = 'tt-cell' + bord + rowEndCls(p), clickable = '';
                                    if (selected && selected.training_type === 'practice' && cardGroups(selected).includes(grp)) {
                                        if (conflictsAt(selected, d, p).length) cls += ' tt-bad';
                                        else { cls += ' tt-ok'; clickable = ' data-place="' + d + '-' + p + '"'; }
                                    }
                                    h += '<td class="' + cls + '"' + dp + clickable + '></td>';
                                    gi++;
                                }
                            }
                        });
                        h += '</tr>';
                    }
                }
                h += '</tbody>';
                $('grid').innerHTML = h;
                activeCell = null;   // qayta render — eski faol katak eskirdi

                // Yashil katakni bosish — joylash (shablon yoki tanlangan hafta)
                document.querySelectorAll('[data-place]').forEach(td => td.onclick = () => {
                    if (!selected) return;
                    const [d, p] = td.dataset.place.split('-').map(Number);
                    placeCardAt(selected, d, p);
                });

                // Joylashgan chipni bosish — tanlash + modal
                document.querySelectorAll('[data-chip]').forEach(el => el.onclick = (ev) => {
                    ev.stopPropagation();
                    const c = cards.find(x => x.id === +el.dataset.chip);
                    selected = c;
                    openModal(c);
                    renderPanel(); renderGrid();
                });

                // ===== Drag-and-drop (aSc Timetables uslubida) =====
                // Joylashgan chiplar ham sudraladi. Birlashtirilgan (ketma-ket)
                // chip — butun blok bo'lib ko'chadi (data-merge-ids).
                document.querySelectorAll('#grid [data-chip]').forEach(el => {
                    el.setAttribute('draggable', 'true');
                    el.addEventListener('dragstart', ev => {
                        ev.stopPropagation();
                        const ids = el.dataset.mergeIds ? el.dataset.mergeIds.split(',').map(Number) : [+el.dataset.chip];
                        startDrag(ids, ev);
                    });
                });
                // Kataklarni tashlash nishoni qilamiz
                document.querySelectorAll('#grid td[data-day]').forEach(td => {
                    td.addEventListener('dragover', ev => {
                        if (!dragCardIds) return;
                        ev.preventDefault();
                        ev.dataTransfer.dropEffect = 'move';
                        const card = cards.find(x => x.id === dragCardIds[0]);
                        const d = +td.dataset.day, p = +td.dataset.pair;
                        td.classList.add(card && conflictsAt(card, d, p).length ? 'drag-bad' : 'drag-ok');
                    });
                    td.addEventListener('dragleave', () => td.classList.remove('drag-ok', 'drag-bad'));
                    td.addEventListener('drop', ev => {
                        ev.preventDefault();
                        td.classList.remove('drag-ok', 'drag-bad');
                        if (!dragCardIds) return;
                        const ids = dragCardIds;
                        const d = +td.dataset.day, p = +td.dataset.pair;
                        dragCardIds = null;
                        placeBlockAt(ids, d, p);
                    });
                });
            }

            // ===== Kartochka modali (o'qituvchi/auditoriya) =====
            async function openModal(c) {
                modalCard = c;
                $('cmTitle').textContent = c.subject_name;
                $('cmSub').textContent = (c.training_type === 'lecture' ? "Ma'ruza · " + (c.oqim_label || '') : 'Amaliy · ' + (c.group_name || '')) +
                    ' · ' + c.students + ' talaba' + (c.kafedra_name ? ' · ' + c.kafedra_name : '') +
                    (curWeek ? ' · ' + curWeek + '-hafta' + (hasWeekOverride(c) ? ' (individual)' : '') : '');
                $('cmCap').textContent = '(kamida ' + c.students + ' o\'rin)';
                // Hafta rejimida: "olib tashlash" shu haftada bekor qilish; override bo'lsa shablonga qaytarish
                $('cmUnplace').textContent = curWeek ? '✖ Shu haftada bekor qilish' : '↩ Jadvaldan olish';
                $('cmResetWeek').classList.toggle('hidden', !(curWeek && hasWeekOverride(c)));
                // Dars uzunligi va boshlanish yarmi
                $('cmLen').value = String(c.len_half || 2);
                $('cmStartHalf').value = String(c.start_half || 0);
                $('cmStartHalf').disabled = !(c.day && c.pair);
                updateCmTimeHint();
                $('cmMsg').classList.add('hidden');
                $('cardModal').classList.remove('hidden');
                await Promise.all([loadTeachers(''), loadAuds()]);
            }
            // Modal: tanlangan uzunlik/boshlanish bo'yicha dars vaqti oralig'ini ko'rsatish
            function updateCmTimeHint() {
                const el = $('cmTimeHint');
                if (!modalCard || !modalCard.day || !modalCard.pair) { el.textContent = 'Joylashtirilmagan — uzunlik saqlanadi.'; return; }
                const sched = boardSchedule().filter(it => it.type === 'pair');
                const len = +$('cmLen').value;
                // Bu modelda `pair` — yarim-slot indeksi; har qo'ng'iroq elementi bitta
                // yarim-slot (o'z start/end vaqti bilan). Dars pair'dan boshlab len ta
                // yarim-slotni egallaydi.
                const startEntry = sched[modalCard.pair - 1];
                const endEntry = sched[modalCard.pair - 1 + len - 1] || startEntry;
                const startT = startEntry ? (startEntry.start || '') : '';
                const endT = endEntry ? (endEntry.end || '') : '';
                const label = { 1: '0.5 para', 2: '1 para', 3: '1.5 para', 4: '2 para' }[len] || len;
                el.textContent = '⏱ ' + label + (startT && endT ? ' · ' + startT + '–' + endT : '');
            }
            // Ikki vaqt oralig'ining o'rtasi (HH:MM)
            function midTime(s, e) {
                const toMin = t => { const [h, m] = String(t).split(':').map(Number); return (h || 0) * 60 + (m || 0); };
                if (!s || !e) return '';
                const m = Math.round((toMin(s) + toMin(e)) / 2);
                return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
            }
            $('cmLen').onchange = $('cmStartHalf').onchange = updateCmTimeHint;
            async function loadTeachers(search) {
                const p = new URLSearchParams();
                if (modalCard.kafedra_name && !search) p.set('kafedra', modalCard.kafedra_name.split(' ')[0]);
                if (search) p.set('search', search);
                const list = await api(TEACHERS_URL + '?' + p);
                $('cmTeacher').innerHTML = '<option value="">— biriktirilmagan —</option>' + list.map(t =>
                    '<option value="' + t.id + '"' + (modalCard.teacher_id === t.id ? ' selected' : '') + '>' +
                    esc(t.full_name) + (t.lavozim ? ' · ' + esc(t.lavozim) : '') + '</option>').join('');
            }
            async function loadAuds() {
                if (!audCache) audCache = await api(AUDS_URL);
                $('cmAud').innerHTML = '<option value="">— tanlanmagan —</option>' + audCache.map(a =>
                    '<option value="' + esc(a.code) + '"' + (modalCard.auditorium_code === a.code ? ' selected' : '') +
                    ((a.volume && a.volume < modalCard.students) ? ' style="color:#dc2626"' : '') + '>' +
                    esc(a.name) + (a.volume ? ' (' + a.volume + ')' : '') + (a.building_name ? ' · ' + esc(a.building_name) : '') + '</option>').join('');
            }
            let tSearchTimer = null;
            $('cmTeacherSearch').oninput = function () {
                clearTimeout(tSearchTimer);
                tSearchTimer = setTimeout(() => loadTeachers(this.value.trim()), 300);
            };
            $('cmClose').onclick = $('cmCancel').onclick = () => { $('cardModal').classList.add('hidden'); modalCard = null; selected = null; renderPanel(); renderGrid(); };
            $('cmSave').onclick = async function () {
                if (!modalCard) return;
                this.disabled = true;
                try {
                    const j = await api(BASE + '/cards/' + modalCard.id + '/update', 'POST', {
                        teacher_id: $('cmTeacher').value || '',
                        auditorium_code: $('cmAud').value || '',
                        len_half: $('cmLen').value,
                        start_half: $('cmStartHalf').value,
                    });
                    modalCard.teacher_id = $('cmTeacher').value ? +$('cmTeacher').value : null;
                    modalCard.teacher_name = j.teacher_name;
                    modalCard.auditorium_code = j.auditorium_code;
                    modalCard.auditorium_name = j.auditorium_name;
                    modalCard.len_half = +$('cmLen').value;
                    if (modalCard.day && modalCard.pair) modalCard.start_half = +$('cmStartHalf').value;
                    $('cardModal').classList.add('hidden'); modalCard = null; selected = null;
                    renderAll();
                } catch (e) {
                    const m = $('cmMsg');
                    m.className = 'text-sm rounded px-3 py-2 bg-red-50 text-red-700';
                    m.textContent = e.message; m.classList.remove('hidden');
                }
                this.disabled = false;
            };
            $('cmUnplace').onclick = async () => {
                if (!modalCard) return;
                try {
                    if (!curWeek) {
                        // Shablondan olib tashlash (barcha haftalardan)
                        await api(BASE + '/cards/' + modalCard.id + '/place', 'POST', {});
                        modalCard.day = null; modalCard.pair = null;
                    } else {
                        // Faqat shu haftada bekor qilish
                        await api(BASE + '/cards/' + modalCard.id + '/week-override', 'POST', { week: curWeek, action: 'cancel' });
                        overrides[modalCard.id + '|' + curWeek] = { day: null, pair: null, cancelled: true };
                    }
                } catch (e) { alert('Xatolik: ' + e.message); return; }
                $('cardModal').classList.add('hidden'); modalCard = null; selected = null;
                renderAll();
            };
            $('cmResetWeek').onclick = async () => {
                if (!modalCard || !curWeek) return;
                try {
                    await api(BASE + '/cards/' + modalCard.id + '/week-override', 'POST', { week: curWeek, action: 'reset' });
                    delete overrides[modalCard.id + '|' + curWeek];
                } catch (e) { alert('Xatolik: ' + e.message); return; }
                $('cardModal').classList.add('hidden'); modalCard = null; selected = null;
                renderAll();
            };

            // ══════════════════════════════════════════════════════════════
            //  aSc uslubidagi boshqaruv dialoglari
            // ══════════════════════════════════════════════════════════════
            const PAIR_TIMES = ['08:30-09:50','10:00-11:20','12:00-13:20','13:30-14:50','15:00-16:20','16:30-17:50','17:00-18:20'];
            const ROMAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X'];
            const LANG_LABEL = { uz: "o'zbek", rus: 'rus', ru: 'rus', ing: 'ingliz', en: 'ingliz' };
            const managerIcons = {
                subjects: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4.5h10a2 2 0 0 1 2 2V19H7a2 2 0 0 0-2 2V4.5Z"/><path d="M7 21h12V6.5a2 2 0 0 0-2-2H7"/><path d="M9 8h5M9 12h5"/></svg>',
                groups: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="8" r="3"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 15h1a3 3 0 0 1 3 3v2"/></svg>',
                auditoriums: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16"/><path d="M2 21h20M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M11 21v-4h2v4"/></svg>',
                teachers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 9 8-4 8 4-8 4-8-4Z"/><path d="M7 11.5V16c2 1.5 8 1.5 10 0v-4.5M20 9v5"/></svg>',
            };

            let ascType = null;       // joriy dialog turi
            let ascData = [];         // dialog ma'lumotlari (xom)
            let ascSelId = null;      // tanlangan qator id/kalit
            const ascCache = {};      // tablar orasida qaytishda jadvalni qayta yuklamaslik uchun

            const dialogMeta = {
                subjects:    { icon: managerIcons.subjects, title: 'Darslar',      listLabel: 'Darslar ro\'yxati:', filter: 'spec' },
                groups:      { icon: managerIcons.groups, title: 'Guruhlar',     listLabel: 'Guruhlar ro\'yxati:', filter: 'spec' },
                auditoriums: { icon: managerIcons.auditoriums, title: 'Auditoriyalar', listLabel: 'Auditoriyalar:',     filter: null   },
                teachers:    { icon: managerIcons.teachers, title: "O'qituvchilar", listLabel: 'O\'qituvchilar:',   filter: null   },
            };

            document.querySelectorAll('.asc-tool[data-dialog]').forEach(btn =>
                btn.onclick = () => openDialog(btn.dataset.dialog));
            document.querySelectorAll('.asc-nav-btn').forEach(btn =>
                btn.onclick = () => openDialog(btn.dataset.ascType));

            function updateAscNav(type) {
                document.querySelectorAll('.asc-nav-btn').forEach(btn => {
                    const active = btn.dataset.ascType === type;
                    btn.classList.toggle('active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }

            async function openDialog(type) {
                if (!board) return;

                const modalIsOpen = !$('ascModal').classList.contains('hidden');
                if (modalIsOpen && ascType === type) return;

                ascType = type;
                ascSelId = null;
                updateAscNav(type);

                const m = dialogMeta[type];
                $('ascIcon').innerHTML = m.icon;
                $('ascTitle').textContent = m.title;
                $('ascListLabel').textContent = m.listLabel;
                $('ascSearch').value = '';
                $('ascFilter').value = '';
                $('ascFootMsg').textContent = '';

                if (!modalIsOpen) {
                    $('ascModal').classList.remove('hidden');
                    $('ascTable').innerHTML = '<tbody><tr><td class="p-5 text-slate-400">Yuklanmoqda...</td></tr></tbody>';
                }
                renderAscButtons();

                if (ascCache[type]) {
                    ascData = ascCache[type].data;
                    $('ascFootMsg').textContent = ascCache[type].foot || '';
                    buildAscFilter();
                    renderAscTable();
                    animateAscPanel();
                    return;
                }

                $('ascFootMsg').textContent = 'Yuklanmoqda...';
                try {
                    if (type === 'subjects') {
                        const j = await api(BASE + '/boards/' + board.id + '/subjects');
                        ascData = j.subjects || [];
                        $('ascFootMsg').textContent = 'Manba: ishchi o\'quv rejalar · hafta soni: ' + j.weeks;
                    } else if (type === 'groups') {
                        const j = await api(BASE + '/boards/' + board.id + '/groups');
                        ascData = j.groups || [];
                    } else if (type === 'auditoriums') {
                        ascData = await api(AUDS_URL);
                    } else if (type === 'teachers') {
                        ascData = await api(TEACHERS_URL);
                    }
                    if (ascType !== type) return;
                    ascCache[type] = { data: ascData, foot: $('ascFootMsg').textContent };
                } catch (e) {
                    if (ascType !== type) return;
                    ascData = [];
                    $('ascFootMsg').textContent = 'Xatolik: ' + e.message;
                }
                buildAscFilter();
                renderAscTable();
                animateAscPanel();
            }

            function animateAscPanel() {
                const panel = $('ascPanel');
                if (!panel) return;
                panel.classList.remove('asc-panel-enter');
                void panel.offsetWidth;
                panel.classList.add('asc-panel-enter');
            }

            function buildAscFilter() {
                const f = $('ascFilter');
                const meta = dialogMeta[ascType];
                if (meta.filter === 'spec') {
                    const specs = [...new Set(ascData.map(r => r.specialty_name + ' · ' + r.course + '-kurs'))].sort();
                    f.innerHTML = '<option value="">— barcha yo\'nalishlar —</option>' +
                        specs.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('');
                    f.classList.remove('hidden');
                } else { f.classList.add('hidden'); }
            }

            function filteredAsc() {
                const q = ($('ascSearch').value || '').toLowerCase().trim();
                const fv = $('ascFilter').value;
                return ascData.filter(r => {
                    if (fv && (r.specialty_name + ' · ' + r.course + '-kurs') !== fv) return false;
                    if (!q) return true;
                    return JSON.stringify(r).toLowerCase().includes(q);
                });
            }

            function renderAscTable() {
                $('ascTable').classList.toggle('asc-auditorium-table', ascType === 'auditoriums');
                const rows = filteredAsc();
                $('ascCount').textContent = rows.length + ' ta';
                let h = '';
                if (ascType === 'subjects') {
                    h = '<thead><tr><th>Fan</th><th>Yo\'nalish · kurs</th><th>Kafedra</th><th>Ma\'ruza s.</th><th>Amaliy s.</th><th>M/hafta</th><th>A/hafta</th></tr></thead><tbody>';
                    let lastSpec = null;
                    rows.forEach((r, i) => {
                        const sk = r.specialty_name + '·' + r.course;
                        if (sk !== lastSpec) {
                            h += '<tr class="asc-row-head"><td colspan="7">' + esc(r.specialty_name) + ' · ' + r.course + '-kurs</td></tr>';
                            lastSpec = sk;
                        }
                        h += rowTag(i) + '<td>' + esc(r.subject_name) + '</td><td>' + esc(r.specialty_name) + ' · ' + r.course + '</td>' +
                            '<td>' + esc(r.kafedra_name || '—') + '</td><td>' + fmt(r.lecture) + '</td><td>' + fmt(r.practice + r.laboratory + r.seminar) +
                            '</td><td>' + r.lec_pairs + '</td><td>' + r.prc_pairs + '</td></tr>';
                    });
                } else if (ascType === 'groups') {
                    h = '<thead><tr><th>Guruh</th><th>Yo\'nalish · kurs</th><th>Oqim</th><th>Til</th><th>Talaba</th></tr></thead><tbody>';
                    rows.forEach((r, i) => {
                        h += rowTag(i) + '<td class="font-semibold">' + esc(r.group_name) + '</td><td>' + esc(r.specialty_name) + ' · ' + r.course + '-kurs</td>' +
                            '<td>' + esc(r.oqim_label || '—') + '</td><td>' + esc(LANG_LABEL[r.lang] || r.lang || '—') + '</td><td>' + r.students + '</td></tr>';
                    });
                } else if (ascType === 'auditoriums') {
                    h = '<colgroup><col class="tt-aud-code"><col class="tt-aud-name"><col class="tt-aud-volume"><col class="tt-aud-building"><col class="tt-aud-type"><col class="tt-aud-status"></colgroup><thead><tr><th>Kod</th><th>Nomi</th><th>Sig\'im</th><th>Bino</th><th>Turi</th><th>Holat</th></tr></thead><tbody>';
                    rows.forEach((r, i) => {
                        h += rowTag(i, r.id) + '<td class="font-semibold">' + esc(r.code) + '</td><td>' + esc(r.name) + '</td>' +
                            '<td>' + (r.volume || 0) + '</td><td>' + esc(r.building_name || '—') + '</td><td>' + esc(r.auditorium_type_name || '—') + '</td>' +
                            '<td>' + (r.active ? '<span class="text-green-600">faol</span>' : '<span class="text-gray-400">nofaol</span>') + '</td></tr>';
                    });
                } else if (ascType === 'teachers') {
                    h = '<thead><tr><th>F.I.O.</th><th>Qisqa</th><th>Kafedra</th><th>Lavozim</th></tr></thead><tbody>';
                    rows.forEach((r, i) => {
                        h += rowTag(i, r.id) + '<td>' + esc(r.full_name) + '</td><td>' + esc(r.short_name || '—') + '</td>' +
                            '<td>' + esc(r.department || '—') + '</td><td>' + esc(r.lavozim || '—') + '</td></tr>';
                    });
                }
                h += '</tbody>';
                $('ascTable').innerHTML = h;
                document.querySelectorAll('#ascTable tbody tr[data-idx]').forEach(tr => tr.onclick = () => {
                    ascSelId = tr.dataset.id || tr.dataset.idx;
                    document.querySelectorAll('#ascTable tbody tr').forEach(x => x.classList.remove('sel'));
                    tr.classList.add('sel');
                    renderAscButtons();
                });
            }
            const rowTag = (i, id) => '<tr data-idx="' + i + '"' + (id != null ? ' data-id="' + id + '"' : '') + '>';
            const fmt = v => { v = +v || 0; return Number.isInteger(v) ? v : v.toFixed(1); };

            const actionIcons = {
                plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>',
                edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5a2.1 2.1 0 0 0-3-3L4 16.5Z"/><path d="m14.5 7.5 2 2"/></svg>',
                trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M10 11v6M14 11v6M6.5 7l1 13h9l1-13M9 7V4h6v3"/></svg>',
                import: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 20h14"/></svg>',
                template: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
                export: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4v11M7 10l5 5 5-5"/><path d="M5 20h14"/></svg>',
            };
            const actionIcon = name => '<span class="asc-action-icon" aria-hidden="true">' + actionIcons[name] + '</span>';

            function renderAscButtons() {
                const b = $('ascButtons');
                const hasSel = ascSelId !== null;
                if (ascType === 'auditoriums') {
                    b.innerHTML =
                        '<button class="asc-btn primary block asc-action-btn" id="aBtnNew">' + actionIcon('plus') + 'Yangi</button>' +
                        '<button class="asc-btn block asc-action-btn" id="aBtnEdit"' + (hasSel ? '' : ' disabled') + '>' + actionIcon('edit') + 'Tahrirlash</button>' +
                        '<button class="asc-btn danger block asc-action-btn" id="aBtnDel"' + (hasSel ? '' : ' disabled') + '>' + actionIcon('trash') + 'O\'chirish</button>' +
                        '<div class="my-1 border-t border-gray-300"></div>' +
                        '<button class="asc-btn block asc-action-btn" id="aBtnImport">' + actionIcon('import') + 'Import (Excel)</button>' +
                        '<button class="asc-btn block asc-action-btn" id="aBtnTemplate">' + actionIcon('template') + 'Namuna shabloni</button>';
                    $('aBtnNew').onclick = () => openAudEdit(null);
                    $('aBtnEdit').onclick = () => hasSel && openAudEdit(ascData.find(x => String(x.id) === String(ascSelId)));
                    $('aBtnDel').onclick = () => hasSel && deleteAud();
                    $('aBtnImport').onclick = () => $('audImportFile').click();
                    $('aBtnTemplate').onclick = downloadAudTemplate;
                } else {
                    // Faqat o'qish (manba HEMIS/o'quv reja) — eksport imkoniyati
                    b.innerHTML =
                        '<button class="asc-btn block asc-action-btn" id="aBtnCsv">' + actionIcon('export') + 'CSV ga eksport</button>' +
                        '<div class="text-[11px] text-gray-500 leading-snug mt-1 px-1">' +
                        (ascType === 'subjects'
                            ? 'Fanlar ishchi o\'quv rejalardan olinadi. Soatlar reja tahririda o\'zgartiriladi.'
                            : ascType === 'groups'
                            ? 'Guruhlar tasdiqlangan oqim tuzilishidan olinadi.'
                            : 'O\'qituvchilar HEMIS sinxronizatsiyasidan olinadi.') + '</div>';
                    $('aBtnCsv').onclick = exportAscCsv;
                }
            }

            $('ascSearch').oninput = () => renderAscTable();
            $('ascFilter').onchange = () => { ascSelId = null; renderAscTable(); renderAscButtons(); };
            $('ascClose').onclick = $('ascCloseBtn').onclick = () => $('ascModal').classList.add('hidden');

            // ── CSV eksport (faqat o'qiladigan dialoglar) ──
            function exportAscCsv() {
                const rows = filteredAsc();
                if (!rows.length) return;
                const cols = Object.keys(rows[0]);
                const csv = [cols.join(',')].concat(rows.map(r =>
                    cols.map(c => '"' + String(r[c] ?? '').replace(/"/g, '""') + '"').join(','))).join('\n');
                dl('﻿' + csv, ascType + '.csv', 'text/csv');
            }
            function downloadAudTemplate() {
                dl('﻿kod,nomi,sigim,bino,turi\n101,"1-bino №101",30,"1-bino","Amaliy xona"\n', 'auditoriya-namuna.csv', 'text/csv');
            }
            function dl(content, name, type) {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([content], { type }));
                a.download = name; a.click(); URL.revokeObjectURL(a.href);
            }

            // ── Auditoriya CRUD ──
            let audEditId = null;
            function openAudEdit(a) {
                audEditId = a ? a.id : null;
                $('aeTitle').textContent = a ? 'Auditoriyani tahrirlash' : 'Yangi auditoriya';
                $('aeCode').value = a ? a.code : '';
                $('aeName').value = a ? a.name : '';
                $('aeVolume').value = a ? (a.volume || 0) : 30;
                $('aeBuilding').value = a ? (a.building_name || '') : '';
                $('aeType').value = a ? (a.auditorium_type_name || '') : '';
                $('aeActive').checked = a ? !!a.active : true;
                $('aeMsg').classList.add('hidden');
                $('audEditModal').classList.remove('hidden');
            }
            $('aeClose').onclick = $('aeCancel').onclick = () => $('audEditModal').classList.add('hidden');
            $('aeSave').onclick = async function () {
                this.disabled = true;
                const body = {
                    code: $('aeCode').value.trim(), name: $('aeName').value.trim(),
                    volume: $('aeVolume').value || 0, building_name: $('aeBuilding').value.trim(),
                    auditorium_type_name: $('aeType').value.trim(), active: $('aeActive').checked ? 1 : 0,
                };
                const url = BASE + '/auditoriums' + (audEditId ? '/' + audEditId : '');
                try {
                    await api(url, 'POST', body);
                    $('audEditModal').classList.add('hidden');
                    audCache = null;                       // rekvizit modalidagi kesh eskirdi
                    ascCache.auditoriums = null;
                    ascData = await api(AUDS_URL); ascSelId = null;
                    renderAscTable(); renderAscButtons();
                } catch (e) {
                    const m = $('aeMsg'); m.className = 'col-span-2 text-sm rounded px-3 py-2 bg-red-50 text-red-700';
                    m.textContent = e.message; m.classList.remove('hidden');
                }
                this.disabled = false;
            };
            async function deleteAud() {
                const a = ascData.find(x => String(x.id) === String(ascSelId));
                if (!a || !confirm('«' + a.name + '» auditoriyasi o\'chirilsinmi?')) return;
                try {
                    const f = new FormData(); f.append('_token', CSRF); f.append('_method', 'DELETE');
                    const r = await fetch(BASE + '/auditoriums/' + a.id, { method: 'POST', headers: { 'Accept': 'application/json' }, body: f });
                    const j = await r.json();
                    $('ascFootMsg').textContent = j.deactivated
                        ? 'Auditoriya jadvalda ishlatilgani uchun nofaol qilindi.' : 'O\'chirildi.';
                    audCache = null;
                    ascCache.auditoriums = null;
                    ascData = await api(AUDS_URL); ascSelId = null;
                    renderAscTable(); renderAscButtons();
                } catch (e) { alert('Xatolik: ' + e.message); }
            }
            $('audImportFile').onchange = async function () {
                if (!this.files.length) return;
                const f = new FormData(); f.append('_token', CSRF); f.append('file', this.files[0]);
                $('ascFootMsg').textContent = 'Import qilinmoqda...';
                try {
                    const r = await fetch(BASE + '/auditoriums/import', { method: 'POST', headers: { 'Accept': 'application/json' }, body: f });
                    const j = await r.json();
                    if (!r.ok) throw new Error(j.error || j.message || 'Xatolik');
                    $('ascFootMsg').textContent = 'Import: ' + j.imported + ' qo\'shildi, ' + j.updated + ' yangilandi' +
                        (j.errors && j.errors.length ? ' · ' + j.errors.length + ' xato' : '');
                    audCache = null;
                    ascData = await api(AUDS_URL); ascSelId = null;
                    renderAscTable(); renderAscButtons();
                } catch (e) { $('ascFootMsg').textContent = 'Xatolik: ' + e.message; }
                this.value = '';
            };

            // ══════════════════════════════════════════════════════════════
            //  Excel ko'rinishidagi jadval (kunlar/paralar qatorda, guruhlar ustunda)
            // ══════════════════════════════════════════════════════════════
            let excelMode = 'group';   // group | teacher | room
            // "Excelga yuklash" — ekrandagi HAQIQIY panjarani (chiziqlar, ranglar,
            // birlashgan kataklar bilan) aynan o'zini Excel'ga chiqaradi.
            $('excelViewBtn').onclick = () => downloadExcelXls();
            $('excelClose').onclick = () => $('excelModal').classList.add('hidden');
            $('excelPrint').onclick = () => window.print();
            $('excelDownload').onclick = () => downloadExcelXls();

            // ── Ekrandagi panjarani inline-uslubli HTML jadval sifatida tayyorlash ──
            const rgbToHex = c => {
                const m = String(c).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (!m) return null;
                return '#' + [1, 2, 3].map(i => (+m[i]).toString(16).padStart(2, '0')).join('');
            };
            const cssBorder = (cs, side) => {
                const w = parseFloat(cs['border' + side + 'Width']) || 0;
                if (w <= 0) return 'none';
                return Math.round(w) + 'px solid ' + (rgbToHex(cs['border' + side + 'Color']) || '#000');
            };
            // #grid jadvalini nusxalab, har katakning hisoblangan (computed) fon/chegara/
            // shrift uslublarini inline qo'yamiz — Excel aynan ekrandagidek ko'rsatadi.
            function gridExportHtml() {
                const grid = document.getElementById('grid');
                if (!grid || !grid.querySelector('tbody tr')) return null;
                const clone = grid.cloneNode(true);
                const origCells = grid.querySelectorAll('th, td');
                const cloneCells = clone.querySelectorAll('th, td');
                for (let i = 0; i < origCells.length; i++) {
                    const cs = getComputedStyle(origCells[i]);
                    const el = cloneCells[i];
                    const st = [];
                    const bg = rgbToHex(cs.backgroundColor);
                    if (bg && cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent') st.push('background-color:' + bg);
                    ['Top', 'Right', 'Bottom', 'Left'].forEach(s => st.push('border-' + s.toLowerCase() + ':' + cssBorder(cs, s)));
                    st.push('font-weight:' + cs.fontWeight);
                    st.push('font-size:' + cs.fontSize);
                    st.push('text-align:' + cs.textAlign);
                    st.push('vertical-align:middle');
                    st.push('color:' + (rgbToHex(cs.color) || '#000'));
                    if (cs.writingMode && cs.writingMode.indexOf('vertical') === 0) st.push('mso-rotate:90');
                    el.setAttribute('style', st.join(';'));
                    ['data-day', 'data-pair', 'data-place', 'data-chip', 'data-merge-ids', 'title', 'class'].forEach(a => el.removeAttribute(a));
                }
                clone.querySelectorAll('[draggable]').forEach(x => x.removeAttribute('draggable'));
                clone.querySelectorAll('[data-chip],[data-merge-ids],[data-place]').forEach(x => {
                    ['data-chip', 'data-merge-ids', 'data-place', 'title'].forEach(a => x.removeAttribute(a));
                });
                return '<table style="border-collapse:collapse">' + clone.innerHTML + '</table>';
            }
            // Jadvalni HAQIQIY .xlsx fayl sifatida yuklab olish (serverda PhpSpreadsheet
            // orqali) — Excel "format kengaytmага mos emas" ogohlantirishi chiqmaydi.
            // Xato bo'lsa — eski HTML .xls ga qaytamiz (ogohlantirish bilan bo'lса ham ishlaydi).
            async function downloadExcelXls() {
                const tableHtml = gridExportHtml();
                if (!tableHtml) { alert('Yuklab olish uchun panjara yo\'q.'); return; }
                // Kataklar uslublari inline (fon/chegara/shrift) — Excel aynan ekrandagidek chiqaradi.
                const title = (board.institution_name ? esc(board.institution_name) + ' — ' : '') + esc(board.name || 'Dars jadvali') +
                    (curWeek ? ' · ' + curWeek + '-hafta' : '');
                const titleRow = '<div style="font-weight:700;font-size:14px;padding:6px 2px">' + title + '</div>';
                const html = '<html xmlns="http://www.w3.org/TR/REC-html40">' +
                    '<head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{padding:2px 4px}</style></head><body>' +
                    titleRow + tableHtml + '</body></html>';
                const base = (board.name || 'dars-jadvali').replace(/[^\w\-]+/g, '_') +
                    (curWeek ? '_' + curWeek + '-hafta' : '');
                try {
                    const fd = new FormData();
                    fd.append('_token', CSRF);
                    fd.append('html', html);
                    fd.append('filename', base);
                    const r = await fetch(BASE + '/boards/' + board.id + '/excel-export', { method: 'POST', body: fd });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const blob = await r.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url; a.download = base + '.xlsx';
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 5000);
                } catch (e) {
                    // Zaxira: HTML .xls (Excel ogohlantirish berishi mumkin, lekin ochiladi)
                    dl('﻿' + html, base + '.xls', 'application/vnd.ms-excel');
                }
            }
            document.querySelectorAll('.ex-mode').forEach(b => b.onclick = () => {
                excelMode = b.dataset.mode;
                document.querySelectorAll('.ex-mode').forEach(x => x.classList.toggle('active', x === b));
                buildExcelView();
            });

            // Doska sozlamalari yordamchilari (default fallback bilan)
            function boardSchedule() {
                if (board && board.bell_schedule && board.bell_schedule.length) return board.bell_schedule;
                return PAIR_TIMES.slice(0, board ? board.pairs_per_day : 6).map((t, i) => {
                    const [start, end] = t.split('-');
                    return { type: 'pair', no: i + 1, name: (ROMAN[i] || (i + 1)) + '-para', abbr: ROMAN[i] || String(i + 1), start, end, print: true };
                });
            }
            function boardDayNames() {
                return (board && board.day_names && board.day_names.length) ? board.day_names : DAY_NAMES;
            }

            function buildExcelView() {
                // Ekranда ko'rinaётган qamrov (bitta yo'nalish / barcha fakultet /
                // barcha yo'nalish — allFac/allSpec) o'zini yuklaymiz.
                const src = specCards();
                // Dars turi filtri + tanlangan hafta Excel ko'rinishga ham qo'llanadi
                const placed = src.filter(c => effPlace(c) && typeVisible(c));
                // Ustun tuzilishi rejimga qarab: guruh / o'qituvchi / auditoriya.
                // headGroups: [{title, span, cols:[{key,label}]}]; idx: "colKey|day|pair" → karta(lar)
                let headGroups = [], idx = {};
                const push = (key, d, p, c) => { const k = key + '|' + d + '|' + p; (idx[k] = idx[k] || []).push(c); };

                if (excelMode === 'group') {
                    const specMap = {};
                    src.forEach(c => {
                        const sk = c.specialty_name + '|' + c.course;
                        (specMap[sk] = specMap[sk] || { name: c.specialty_name, course: c.course, faculty: c.faculty_name || '', groups: new Set() });
                        cardGroups(c).forEach(g => specMap[sk].groups.add(g));
                    });
                    // Ustunlar fakultet bo'yicha guruhlanadi — bir fakultet
                    // ustunlari ketma-ket (davomida) kelib, tepasida fakultet
                    // super-sarlavhasi ustma-ust turadi.
                    Object.values(specMap)
                        .map(s => ({ ...s, groups: [...s.groups].sort((a, b) => a.localeCompare(b, undefined, { numeric: true })) }))
                        .sort((a, b) => (a.faculty + '|' + a.name + a.course).localeCompare(b.faculty + '|' + b.name + b.course, 'uz'))
                        .forEach(s => headGroups.push({ title: s.name + ' · ' + s.course + '-kurs', cols: s.groups.map(g => ({ key: g, label: g })) }));
                    placed.forEach(c => { const pl = effPlace(c); cardGroups(c).forEach(g => push(g, pl.day, pl.pair, c)); });
                } else if (excelMode === 'teacher') {
                    const names = [...new Set(placed.filter(c => c.teacher_name).map(c => c.teacher_name))]
                        .sort((a, b) => a.localeCompare(b));
                    headGroups.push({ title: "O'qituvchilar", cols: names.map(n => ({ key: n, label: n })) });
                    placed.forEach(c => { if (c.teacher_name) { const pl = effPlace(c); push(c.teacher_name, pl.day, pl.pair, c); } });
                } else { // room
                    const names = [...new Set(placed.filter(c => c.auditorium_name).map(c => c.auditorium_name))]
                        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
                    headGroups.push({ title: 'Auditoriyalar', cols: names.map(n => ({ key: n, label: n })) });
                    placed.forEach(c => { if (c.auditorium_name) { const pl = effPlace(c); push(c.auditorium_name, pl.day, pl.pair, c); } });
                }

                const cols = headGroups.flatMap(hg => hg.cols);
                if (!cols.length) {
                    $('excelBody').innerHTML = '<div class="p-4 text-gray-500">' +
                        (excelMode === 'group' ? 'Joylashgan darslar yo\'q.' :
                         excelMode === 'teacher' ? 'Biriktirilgan va joylashgan darslar yo\'q.' :
                         'Auditoriya biriktirilgan darslar yo\'q.') + '</div>';
                    return;
                }

                // Fakultet super-sarlavhasi (faqat guruh rejimida): guruh → fakultet
                let facHead = null;
                if (excelMode === 'group') {
                    const gFac = {};
                    cards.forEach(c => cardGroups(c).forEach(g => { if (!(g in gFac)) gFac[g] = c.faculty_name || ''; }));
                    if (cols.some(col => gFac[col.key])) {
                        facHead = [];
                        cols.forEach(col => {
                            const f = gFac[col.key] || '';
                            const last = facHead[facHead.length - 1];
                            if (last && last.faculty === f) last.span++; else facHead.push({ faculty: f, span: 1 });
                        });
                    }
                }

                // Kunlar soni — faqat ko'rsatilaётган yo'nalishlar grid'idan (eng kattasi)
                let D = board.days;
                [...new Set(src.map(c => c.specialty_name + '|' + c.course))].forEach(k => {
                    const g = grids[k]; if (g) D = Math.max(D, g.days);
                });
                const dayNames = boardDayNames();
                const sched = boardSchedule().filter(it => it.print !== false || it.type === 'pair');

                // Katak matni rejimga qarab. span — ma'ruzani oqim ustunlari
                // bo'ylab birlashtirish (colspan) uchun.
                const cellHtml = (c, span) => {
                    const isLec = c.training_type === 'lecture';
                    const cls = isLec ? 'ex-lec' : 'ex-prc';
                    let extra;
                    if (excelMode === 'group') extra = [c.teacher_name, c.auditorium_name];
                    else if (excelMode === 'teacher') extra = [c.group_name || (c.oqim_label ? c.oqim_label : ''), c.auditorium_name];
                    else extra = [c.group_name || c.oqim_label, c.teacher_name];
                    const sub = extra.filter(Boolean).join(' · ');
                    // Ma'ruza — bir xil sariq; amaliy — har fan o'z rangida (nuqtali chegara).
                    // Ranglar inline (hex) — Excelга eksport qilinganда ham saqlanadi.
                    const s = subjColor(c.subject_name);
                    const st = isLec ? 'background-color:#fde68a;'
                        : 'background-color:' + s.bg + ';border-left:3px dotted ' + s.border + ';';
                    const cs = (span && span > 1) ? ' colspan="' + span + '"' : '';
                    const tag = '[' + (isLec ? 'M' : 'A') + '] ';
                    return '<td class="ex-cell ' + cls + '"' + cs + ' style="' + st + '">' +
                        '<div><b>' + tag + '</b>' + esc(c.subject_name) + '</div>' +
                        (sub ? '<div style="color:#64748b;font-size:9px">' + esc(sub) + '</div>' : '') + '</td>';
                };

                const modeLabel = { group: 'guruh', teacher: "o'qituvchi", room: 'auditoriya' }[excelMode];
                const title = (board.institution_name ? board.institution_name + ' — ' : '') + (board.name || 'Dars jadvali') + ' (' + modeLabel + ' kesimida)';
                let h = '<table><thead>';
                h += '<tr><td class="ex-title" colspan="' + (cols.length + 3) + '">' + esc(title) + '</td></tr>';
                const kpSpan = facHead ? 3 : 2;
                h += '<tr><th rowspan="' + kpSpan + '" class="ex-para">Kun</th><th rowspan="' + kpSpan + '" class="ex-para">Para</th><th rowspan="' + kpSpan + '" class="ex-para">Soati</th>';
                if (facHead) {
                    facHead.forEach(r => h += '<th class="ex-fac" colspan="' + r.span + '">' + esc(r.faculty || '—') + '</th>');
                    h += '</tr><tr>';
                }
                headGroups.forEach(hg => h += '<th class="ex-spec" colspan="' + hg.cols.length + '">' + esc(hg.title) + '</th>');
                h += '</tr><tr>';
                cols.forEach(col => h += '<th class="ex-grp">' + esc(col.label) + '</th>');
                h += '</tr></thead><tbody>';

                for (let d = 1; d <= D; d++) {
                    sched.forEach((it, si) => {
                        h += '<tr>';
                        if (si === 0) h += '<td class="ex-day" rowspan="' + sched.length + '">' + esc(dayNames[d - 1] || ('Kun ' + d)) + '</td>';
                        const timeStr = (it.start || '') + (it.end ? '-' + it.end : '');
                        if (it.type === 'break') {
                            h += '<td class="ex-para" colspan="2">' + esc(it.name || 'Tanaffus') + '</td>';
                            h += '<td class="ex-time" colspan="' + cols.length + '" style="text-align:center;color:#15803d;background:#f0fdf4">' + esc(timeStr) + '</td></tr>';
                            return;
                        }
                        h += '<td class="ex-para">' + esc(it.abbr || it.no) + '</td><td class="ex-time">' + esc(timeStr) + '</td>';
                        const cellAt = ci => idx[cols[ci].key + '|' + d + '|' + it.no] || [];
                        let ci = 0;
                        while (ci < cols.length) {
                            const list = cellAt(ci);
                            if (!list.length) { h += '<td class="ex-cell"></td>'; ci++; continue; }
                            if (list.length === 1) {
                                const c = list[0];
                                // Ma'ruza — bir xil karta ketma-ket ustunlarda kelsa
                                // (oqim guruhlari) bitta birlashtirilgan katakka jamlanadi.
                                let span = 1;
                                if (c.training_type === 'lecture') {
                                    while (ci + span < cols.length) {
                                        const nx = cellAt(ci + span);
                                        if (nx.length === 1 && nx[0].id === c.id) span++; else break;
                                    }
                                }
                                h += cellHtml(c, span);
                                ci += span;
                                continue;
                            }
                            // Bir katakda bir nechta (konflikt) — qizil ramka bilan
                            h += '<td class="ex-cell" style="outline:2px solid #ef4444;outline-offset:-2px">' +
                                list.map(c => '<div><b>[' + (c.training_type === 'lecture' ? 'M' : 'A') + ']</b> ' +
                                    esc(c.subject_name) + '<span style="color:#64748b;font-size:9px"> · ' +
                                    esc(excelMode === 'teacher' ? (c.group_name || c.oqim_label || '') : (c.teacher_name || '')) + '</span></div>').join('') + '</td>';
                            ci++;
                        }
                        h += '</tr>';
                    });
                }
                h += '</tbody></table>';
                $('excelBody').innerHTML = h;
            }

            // ══════════════════════════════════════════════════════════════
            //  Umumiy sozlamalar dialogi (qo'ng'iroqlar / juftliklar vaqti)
            // ══════════════════════════════════════════════════════════════
            let bellDraft = [];       // tahrirlanayotgan qo'ng'iroq jadvali
            let dayDraft = [];        // tahrirlanayotgan kun nomlari
            let bellEditIdx = null;
            let bellSel = null;       // belgilangan (highlight) qator indeksi — ▲/▼ bilan ko'chirish uchun

            const SETTINGS_URL = id => BASE + '/boards/' + id + '/settings';

            $('settingsBtn').onclick = async () => {
                if (!board) return;
                $('setMsg').textContent = '';
                setTab('basic');
                $('setModal').classList.remove('hidden');
                try {
                    const s = await api(SETTINGS_URL(board.id));
                    $('stInst').value = s.institution_name || '';
                    $('stYear').value = s.academic_year || '';
                    $('stDays').value = s.days;
                    $('stPairs').value = s.pairs_per_day;
                    const set = s.settings || {};
                    $('stDayOff').value = (set.days_off || []).join(', ');
                    $('stAllowZero').checked = !!set.allow_zero;
                    $('stShowNum').checked = !!set.show_day_number;
                    $('stSameDay').checked = !!set.pair_same_day;
                    $('stConsec').checked = !!set.pair_consecutive;
                    $('stRoomTol').value = (set.room_tolerance_pct != null ? set.room_tolerance_pct : 5);
                    bellDraft = (s.bell_schedule || []).map(x => ({ ...x }));
                    dayDraft = (s.day_names || []).slice();
                    renderBellTable(); renderDayNames();
                } catch (e) { $('setMsg').textContent = 'Xatolik: ' + e.message; }
            };

            document.querySelectorAll('.set-tab').forEach(t => t.onclick = () => setTab(t.dataset.tab));
            function setTab(name) {
                document.querySelectorAll('.set-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
                $('setBasic').classList.toggle('hidden', name !== 'basic');
                $('setBells').classList.toggle('hidden', name !== 'bells');
                $('setDays').classList.toggle('hidden', name !== 'days');
            }
            $('setClose').onclick = $('setCancel').onclick = () => $('setModal').classList.add('hidden');

            // Kun soni o'zgarsa — kun nomlari maydonini moslash
            $('stDays').onchange = function () {
                const n = +this.value;
                while (dayDraft.length < n) dayDraft.push(DAY_NAMES[dayDraft.length] || ('Kun ' + (dayDraft.length + 1)));
                dayDraft = dayDraft.slice(0, n);
                renderDayNames();
            };

            function renderBellTable() {
                let pn = 0;
                let h = '<thead><tr><th>#</th><th>Nomi</th><th>Qisqartma</th><th>Boshi</th><th>Oxiri</th><th>Chop</th><th></th></tr></thead><tbody>';
                bellDraft.forEach((it, i) => {
                    const isBreak = it.type === 'break';
                    const label = isBreak ? '<span class="text-green-600">tanaffus</span>' : (++pn);
                    const selCls = (i === bellSel) ? ' bell-sel' : '';
                    h += '<tr class="' + (isBreak ? 'is-break' : '') + selCls + '" data-row="' + i + '">' +
                        '<td class="text-center">' + label + '</td>' +
                        '<td>' + esc(it.name || '') + '</td><td>' + esc(it.abbr || '') + '</td>' +
                        '<td>' + esc(it.start || '') + '</td><td>' + esc(it.end || '') + '</td>' +
                        '<td class="text-center">' + (it.print === false ? '—' : 'Ha') + '</td>' +
                        '<td class="whitespace-nowrap text-right">' +
                            '<button class="asc-mini" data-edit="' + i + '" title="Tahrirlash">✏️</button>' +
                            '<button class="asc-mini" data-del="' + i + '" title="O\'chirish">🗑</button>' +
                        '</td></tr>';
                });
                h += '</tbody>';
                $('stBellTable').innerHTML = h;
                const T = $('stBellTable');
                // Qatorni bosib belgilash — umumiy ▲/▼ tugmalari shu belgilangan qatorni ko'chiradi
                T.querySelectorAll('tr[data-row]').forEach(tr => tr.onclick = ev => {
                    if (ev.target.closest('button')) return;   // tugma bosilsa — belgilamaymiz
                    bellSel = (bellSel === +tr.dataset.row) ? null : +tr.dataset.row;
                    renderBellTable();
                });
                T.querySelectorAll('[data-edit]').forEach(b => b.onclick = () => openBellEdit(+b.dataset.edit));
                T.querySelectorAll('[data-del]').forEach(b => b.onclick = () => {
                    const d = +b.dataset.del;
                    bellDraft.splice(d, 1);
                    if (bellSel === d) bellSel = null; else if (bellSel > d) bellSel--;
                    renderBellTable();
                });
                // Belgilangan qatorni ko'rinishga suramiz
                const selTr = T.querySelector('tr.bell-sel');
                if (selTr) selTr.scrollIntoView({ block: 'nearest' });
                $('stMoveUp').disabled = !(bellSel !== null && bellSel > 0);
                $('stMoveDown').disabled = !(bellSel !== null && bellSel < bellDraft.length - 1);
            }
            // Umumiy ▲/▼ tugmalari — belgilangan qatorni ko'chiradi va belgini
            // shu qatorda saqlab qoladi, shunda qayta-qayta bosib yuqoriga/pastga
            // chiqarib borish mumkin.
            function moveBell(dir) {
                if (bellSel === null) return;
                const i = bellSel, j = i + dir;
                if (j < 0 || j >= bellDraft.length) return;
                [bellDraft[i], bellDraft[j]] = [bellDraft[j], bellDraft[i]];
                bellSel = j;
                renderBellTable();
            }
            $('stMoveUp').onclick = () => moveBell(-1);
            $('stMoveDown').onclick = () => moveBell(1);
            $('stAddPair').onclick = () => {
                const pairs = bellDraft.filter(x => x.type === 'pair').length;
                bellDraft.push({ type: 'pair', name: (pairs + 1) + '-para', abbr: ROMAN[pairs] || String(pairs + 1),
                    start: '', end: '', print: true });
                renderBellTable();
            };
            $('stAddBreak').onclick = () => {
                bellDraft.push({ type: 'break', name: 'Tanaffus', abbr: '—', start: '', end: '', print: false });
                renderBellTable();
            };
            $('stResetBells').onclick = () => {
                if (!confirm('Qo\'ng\'iroqlar jadvali standart holatga qaytarilsinmi?')) return;
                const n = +$('stPairs').value || 6;
                bellDraft = [];
                for (let i = 0; i < n; i++) {
                    const [s, e] = (PAIR_TIMES[i] || '-').split('-');
                    bellDraft.push({ type: 'pair', name: (ROMAN[i] || (i + 1)) + '-para', abbr: ROMAN[i] || String(i + 1), start: s, end: e, print: true });
                    if (i < n - 1) bellDraft.push({ type: 'break', name: 'Tanaffus', abbr: '—', start: e, end: (PAIR_TIMES[i + 1] || '').split('-')[0] || '', print: false });
                }
                renderBellTable();
            };

            // Qo'ng'iroq qatorini tahrirlash
            function openBellEdit(i) {
                bellEditIdx = i; const it = bellDraft[i];
                $('beTitle').textContent = it.type === 'break' ? 'Tanaffus' : 'Para (juftlik)';
                $('beName').value = it.name || ''; $('beAbbr').value = it.abbr || '';
                $('beStart').value = it.start || ''; $('beEnd').value = it.end || '';
                $('bePrint').value = it.print === false ? '0' : '1';
                $('bellEditModal').classList.remove('hidden');
            }
            $('beClose').onclick = $('beCancel').onclick = () => $('bellEditModal').classList.add('hidden');
            $('beSave').onclick = () => {
                const it = bellDraft[bellEditIdx]; if (!it) return;
                it.name = $('beName').value.trim(); it.abbr = $('beAbbr').value.trim();
                it.start = $('beStart').value.trim(); it.end = $('beEnd').value.trim();
                it.print = $('bePrint').value === '1';
                $('bellEditModal').classList.add('hidden');
                renderBellTable();
            };

            function renderDayNames() {
                $('stDayNames').innerHTML = dayDraft.map((d, i) =>
                    '<div class="flex items-center gap-2"><span class="text-xs text-gray-400 w-4">' + (i + 1) + '</span>' +
                    '<input class="flex-1 rounded-md border-gray-300 text-sm dn-inp" data-i="' + i + '" value="' + esc(d) + '"></div>').join('');
                $('stDayNames').querySelectorAll('.dn-inp').forEach(inp =>
                    inp.oninput = () => { dayDraft[+inp.dataset.i] = inp.value; });
            }

            $('setSave').onclick = async function () {
                if (!board) return;
                const pairs = bellDraft.filter(x => x.type === 'pair').length;
                if (!pairs) { $('setMsg').textContent = 'Kamida bitta para bo\'lishi kerak.'; return; }
                this.disabled = true; $('setMsg').textContent = 'Saqlanmoqda...';
                const dayOff = $('stDayOff').value.split(',').map(s => s.trim()).filter(Boolean);
                const body = {
                    institution_name: $('stInst').value.trim(),
                    days: $('stDays').value,
                };
                // Massivlarni FormData ga qo'lda joylash uchun maxsus yuborish
                const fd = new FormData();
                fd.append('_token', CSRF);
                fd.append('institution_name', body.institution_name);
                fd.append('days', body.days);
                dayDraft.slice(0, +body.days).forEach((d, i) => fd.append('day_names[' + i + ']', d || ''));
                bellDraft.forEach((it, i) => {
                    fd.append('bell_schedule[' + i + '][type]', it.type);
                    fd.append('bell_schedule[' + i + '][name]', it.name || '');
                    fd.append('bell_schedule[' + i + '][abbr]', it.abbr || '');
                    fd.append('bell_schedule[' + i + '][start]', it.start || '');
                    fd.append('bell_schedule[' + i + '][end]', it.end || '');
                    fd.append('bell_schedule[' + i + '][print]', it.print === false ? 0 : 1);
                });
                dayOff.forEach((d, i) => fd.append('settings[days_off][' + i + ']', d));
                fd.append('settings[allow_zero]', $('stAllowZero').checked ? 1 : 0);
                fd.append('settings[show_day_number]', $('stShowNum').checked ? 1 : 0);
                fd.append('settings[pair_same_day]', $('stSameDay').checked ? 1 : 0);
                fd.append('settings[pair_consecutive]', $('stConsec').checked ? 1 : 0);
                fd.append('settings[room_tolerance_pct]', $('stRoomTol').value || 5);
                try {
                    const r = await fetch(SETTINGS_URL(board.id), { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
                    const j = await r.json();
                    if (!r.ok) throw new Error(j.error || j.message || 'Xatolik');
                    $('setModal').classList.add('hidden');
                    await loadBoard(board.id);   // yangi o'lcham/vaqtlar bilan qayta yuklash
                } catch (e) { $('setMsg').textContent = 'Xatolik: ' + e.message; }
                this.disabled = false;
            };

            // ══════════════════════════════════════════════════════════════
            //  O'qituvchi biriktirish matritsasi
            // ══════════════════════════════════════════════════════════════
            let asgUnits = [];        // dars birliklari
            let asgSel = null;        // tanlangan birlik
            let asgTeacherTimer = null;

            $('assignBtn').onclick = async () => {
                if (!board) return;
                asgSel = null; setAsgTeacherPanel(null);
                $('assignModal').classList.remove('hidden');
                $('asgMsg').textContent = '';
                $('asgTable').innerHTML = '<tbody><tr><td class="p-3 text-gray-400">Yuklanmoqda...</td></tr></tbody>';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/teacher-units');
                    asgUnits = j.units || [];
                } catch (e) { asgUnits = []; $('asgMsg').textContent = 'Xatolik: ' + e.message; }
                const specs = [...new Set(asgUnits.map(u => u.specialty_name + ' · ' + u.course + '-kurs'))].sort();
                $('asgFilter').innerHTML = '<option value="">— barcha yo\'nalishlar —</option>' +
                    specs.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('');
                renderAsgTable();
            };
            $('asgClose').onclick = $('asgCloseBtn').onclick = () => {
                $('assignModal').classList.add('hidden');
                renderAll();   // grid/panel o'qituvchi o'zgarishlarini aks ettirsin
            };
            $('asgFilter').onchange = $('asgSearch').oninput = $('asgOnlyEmpty').onchange = () => renderAsgTable();

            function asgFiltered() {
                const q = ($('asgSearch').value || '').toLowerCase().trim();
                const fv = $('asgFilter').value;
                const onlyEmpty = $('asgOnlyEmpty').checked;
                return asgUnits.filter(u => {
                    if (fv && (u.specialty_name + ' · ' + u.course + '-kurs') !== fv) return false;
                    if (onlyEmpty && u.teacher_id) return false;
                    if (q && !(u.subject_name.toLowerCase().includes(q) || (u.kafedra_name || '').toLowerCase().includes(q))) return false;
                    return true;
                });
            }

            function renderAsgTable() {
                const rows = asgFiltered();
                $('asgCount').textContent = rows.length + ' ta';
                let h = '<thead><tr><th>Fan</th><th>Tur</th><th>Oqim/Guruh</th><th>Kafedra</th><th>Karta</th><th>O\'qituvchi</th></tr></thead><tbody>';
                let lastSpec = null;
                rows.forEach((u, i) => {
                    const sk = u.specialty_name + '·' + u.course;
                    if (sk !== lastSpec) {
                        h += '<tr class="asc-row-head"><td colspan="6">' + esc(u.specialty_name) + ' · ' + u.course + '-kurs</td></tr>';
                        lastSpec = sk;
                    }
                    const scope = u.training_type === 'lecture' ? (u.oqim_label || 'oqim') : (u.group_name || '');
                    const tt = u.training_type === 'lecture'
                        ? '<span class="text-blue-600 font-semibold">M</span>' : '<span class="text-purple-600 font-semibold">A</span>';
                    const teacher = u.teacher_mixed
                        ? '<span class="text-amber-600">⚠ turlicha</span>'
                        : (u.teacher_name ? esc(u.teacher_name) : '<span class="text-gray-400">— biriktirilmagan —</span>');
                    h += '<tr data-i="' + i + '"' + (asgSel === rows[i] ? ' class="sel"' : '') + '>' +
                        '<td>' + esc(u.subject_name) + '</td><td class="text-center">' + tt + '</td>' +
                        '<td>' + esc(scope) + '</td><td>' + esc(u.kafedra_name || '—') + '</td>' +
                        '<td class="text-center">' + u.cards + (u.placed ? ' <span class="text-green-600">(' + u.placed + '⚑)</span>' : '') + '</td>' +
                        '<td>' + teacher + '</td></tr>';
                });
                h += '</tbody>';
                $('asgTable').innerHTML = h;
                const rowsRef = rows;
                $('asgTable').querySelectorAll('tbody tr[data-i]').forEach(tr => tr.onclick = () => {
                    asgSel = rowsRef[+tr.dataset.i];
                    $('asgTable').querySelectorAll('tbody tr').forEach(x => x.classList.remove('sel'));
                    tr.classList.add('sel');
                    selectAsgUnit();
                });
            }

            async function selectAsgUnit() {
                const u = asgSel;
                setAsgTeacherPanel(u);
                $('asgUnitInfo').innerHTML = '<b>' + esc(u.subject_name) + '</b><br>' +
                    (u.training_type === 'lecture' ? "Ma'ruza · " + esc(u.oqim_label || '') : 'Amaliy · ' + esc(u.group_name || '')) +
                    ' · ' + u.cards + ' karta' + (u.kafedra_name ? '<br><span class="text-gray-400">' + esc(u.kafedra_name) + '</span>' : '');
                await loadAsgTeachers('');
            }

            function setAsgTeacherPanel(u) {
                const on = !!u;
                ['asgTeacherSearch', 'asgTeacher', 'asgApply', 'asgClear', 'asgKafedraOnly'].forEach(id => $(id).disabled = !on);
                if (!on) { $('asgUnitInfo').textContent = '← Chapdan dars birligini tanlang'; $('asgTeacher').innerHTML = ''; $('asgTeacherSearch').value = ''; }
            }

            async function loadAsgTeachers(search) {
                if (!asgSel) return;
                const p = new URLSearchParams();
                if ($('asgKafedraOnly').checked && asgSel.kafedra_name && !search) p.set('kafedra', asgSel.kafedra_name.split(' ')[0]);
                if (search) p.set('search', search);
                try {
                    const list = await api(TEACHERS_URL + '?' + p);
                    $('asgTeacher').innerHTML = list.map(t =>
                        '<option value="' + t.id + '"' + (asgSel.teacher_id === t.id ? ' selected' : '') + '>' +
                        esc(t.short_name || t.full_name) + (t.lavozim ? ' · ' + esc(t.lavozim) : '') + '</option>').join('')
                        || '<option disabled>topilmadi</option>';
                } catch (e) { $('asgTeacher').innerHTML = '<option disabled>xato</option>'; }
            }
            $('asgTeacherSearch').oninput = function () {
                clearTimeout(asgTeacherTimer);
                asgTeacherTimer = setTimeout(() => loadAsgTeachers(this.value.trim()), 300);
            };
            $('asgKafedraOnly').onchange = () => loadAsgTeachers($('asgTeacherSearch').value.trim());

            $('asgApply').onclick = () => applyAsg($('asgTeacher').value || null);
            $('asgClear').onclick = () => applyAsg(null);

            async function applyAsg(teacherId) {
                if (!asgSel) return;
                $('asgApply').disabled = $('asgClear').disabled = true;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/assign-teacher', 'POST', {
                        specialty_name: asgSel.specialty_name, course: asgSel.course,
                        subject_name: asgSel.subject_name, training_type: asgSel.training_type,
                        oqim_label: asgSel.oqim_label || '', group_name: asgSel.group_name || '',
                        teacher_id: teacherId || '',
                    });
                    // Mahalliy holatni yangilaymiz (birlik + tegishli kartalar)
                    asgSel.teacher_id = teacherId ? +teacherId : null;
                    asgSel.teacher_name = j.teacher_name;
                    asgSel.teacher_mixed = false;
                    cards.forEach(c => {
                        const sameScope = asgSel.training_type === 'lecture'
                            ? (c.oqim_label === asgSel.oqim_label) : (c.group_name === asgSel.group_name);
                        if (c.specialty_name === asgSel.specialty_name && c.course === asgSel.course &&
                            c.subject_name === asgSel.subject_name && c.training_type === asgSel.training_type && sameScope) {
                            c.teacher_id = asgSel.teacher_id; c.teacher_name = j.teacher_name;
                        }
                    });
                    $('asgMsg').textContent = (j.teacher_name ? '«' + j.teacher_name + '» biriktirildi' : 'Biriktirish olib tashlandi') +
                        ' · ' + j.affected + ' karta';
                    renderAsgTable();
                } catch (e) { $('asgMsg').textContent = 'Xatolik: ' + e.message; }
                $('asgApply').disabled = $('asgClear').disabled = false;
            }

            // ══════════════════════════════════════════════════════════════
            //  Tekshiruv (konflikt / oyna) hisoboti — client-side
            // ══════════════════════════════════════════════════════════════
            function computeDiagnostics() {
                const placed = cards.filter(c => c.day && c.pair);
                const dayName = i => (boardDayNames()[i - 1] || ('Kun ' + i));

                // 1) Joylashmagan kartalar — yo'nalish bo'yicha
                const unplacedBySpec = {};
                cards.filter(c => !c.day).forEach(c => {
                    const k = c.specialty_name + ' · ' + c.course + '-kurs';
                    unplacedBySpec[k] = (unplacedBySpec[k] || 0) + 1;
                });

                // 2/3) O'qituvchi va auditoriya konfliktlari (day|pair bo'yicha)
                const bySlot = {};
                placed.forEach(c => { (bySlot[c.day + '|' + c.pair] = bySlot[c.day + '|' + c.pair] || []).push(c); });
                const teacherConf = [], roomConf = [];
                Object.entries(bySlot).forEach(([slot, list]) => {
                    const [d, p] = slot.split('|').map(Number);
                    const byT = {}, byR = {};
                    list.forEach(c => {
                        if (c.teacher_id) (byT[c.teacher_id] = byT[c.teacher_id] || []).push(c);
                        if (c.auditorium_code) (byR[c.auditorium_code] = byR[c.auditorium_code] || []).push(c);
                    });
                    Object.values(byT).forEach(g => { if (g.length > 1) teacherConf.push({ d, p, name: g[0].teacher_name, subs: g.map(x => x.subject_name) }); });
                    Object.values(byR).forEach(g => { if (g.length > 1) roomConf.push({ d, p, name: g[0].auditorium_name, subs: g.map(x => x.subject_name) }); });
                });

                // 4) Guruh oynalari (oyna): guruh × kun ichida bo'sh paralar
                const gday = {};   // group|day => Set(pairs)
                placed.forEach(c => cardGroups(c).forEach(g => {
                    const k = g + '|' + c.day; (gday[k] = gday[k] || new Set()).add(c.pair);
                }));
                const gaps = [];
                Object.entries(gday).forEach(([k, set]) => {
                    const [g, d] = k.split('|');
                    const arr = [...set].sort((a, b) => a - b);
                    const hole = (arr[arr.length - 1] - arr[0] + 1) - arr.length;
                    if (hole > 0) gaps.push({ group: g, day: +d, holes: hole, pairs: arr });
                });
                gaps.sort((a, b) => b.holes - a.holes);

                // 5) O'qituvchisiz dars birliklari
                const unitTeacher = {};
                cards.forEach(c => {
                    const scope = c.training_type === 'lecture' ? ('L|' + c.oqim_label) : ('P|' + c.group_name);
                    const k = [c.specialty_name, c.course, c.subject_name, c.training_type, scope].join('¦');
                    if (!(k in unitTeacher)) unitTeacher[k] = { has: false, sub: c.subject_name, spec: c.specialty_name, course: c.course };
                    if (c.teacher_id) unitTeacher[k].has = true;
                });
                const noTeacher = Object.values(unitTeacher).filter(u => !u.has);

                const totalUnplaced = Object.values(unplacedBySpec).reduce((a, b) => a + b, 0);
                const issues = teacherConf.length + roomConf.length + gaps.length;
                return { unplacedBySpec, totalUnplaced, teacherConf, roomConf, gaps, noTeacher, issues, dayName };
            }

            function updateCheckBadge() {
                if (!board || !cards.length) { $('checkBadge').classList.add('hidden'); return; }
                const d = computeDiagnostics();
                const n = d.issues;
                if (n > 0) { $('checkBadge').textContent = n; $('checkBadge').classList.remove('hidden'); }
                else { $('checkBadge').classList.add('hidden'); }
            }

            $('checkBtn').onclick = () => { renderCheck(); $('checkModal').classList.remove('hidden'); };
            $('chkClose').onclick = () => $('checkModal').classList.add('hidden');

            function renderCheck() {
                const d = computeDiagnostics();
                const sec = (icon, title, count, ok, body) =>
                    '<div class="mb-3 border border-gray-200 rounded">' +
                    '<div class="px-3 py-2 font-semibold text-sm flex items-center gap-2 ' + (count ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700') + '">' +
                    icon + ' ' + title + ' <span class="ml-auto text-xs font-bold">' + (count ? count + ' ta' : '✓ ' + ok) + '</span></div>' +
                    (count ? '<div class="px-3 py-2 text-xs text-gray-700 space-y-1">' + body + '</div>' : '') + '</div>';

                let h = '';
                // Joylashmagan
                h += sec('📌', 'Joylashmagan darslar', d.totalUnplaced, 'hammasi joyida',
                    Object.entries(d.unplacedBySpec).map(([k, v]) => '<div>' + esc(k) + ' — <b>' + v + '</b> karta</div>').join(''));
                // O'qituvchi konflikti
                h += sec('🧑‍🏫', "O'qituvchi to'qnashuvlari", d.teacherConf.length, 'to\'qnashuv yo\'q',
                    d.teacherConf.map(c => '<div>' + esc(d.dayName(c.d)) + ', ' + c.p + '-para — <b>' + esc(c.name || '') + '</b>: ' + esc(c.subs.join(' / ')) + '</div>').join(''));
                // Auditoriya konflikti
                h += sec('🚪', 'Auditoriya to\'qnashuvlari', d.roomConf.length, 'to\'qnashuv yo\'q',
                    d.roomConf.map(c => '<div>' + esc(d.dayName(c.d)) + ', ' + c.p + '-para — <b>' + esc(c.name || '') + '</b>: ' + esc(c.subs.join(' / ')) + '</div>').join(''));
                // Oynalar
                h += sec('🕳', 'Guruh oynalari (bo\'sh para)', d.gaps.length, 'oyna yo\'q',
                    d.gaps.slice(0, 40).map(g => '<div>' + esc(g.group) + ' · ' + esc(d.dayName(g.day)) + ' — <b>' + g.holes + '</b> oyna (paralar: ' + g.pairs.join(',') + ')</div>').join('') +
                    (d.gaps.length > 40 ? '<div class="text-gray-400">... yana ' + (d.gaps.length - 40) + '</div>' : ''));
                // O'qituvchisiz
                h += sec('❓', 'O\'qituvchisi biriktirilmagan birliklar', d.noTeacher.length, 'hammasiga biriktirilgan',
                    d.noTeacher.slice(0, 40).map(u => '<div>' + esc(u.spec) + ' · ' + u.course + '-kurs — ' + esc(u.sub) + '</div>').join('') +
                    (d.noTeacher.length > 40 ? '<div class="text-gray-400">... yana ' + (d.noTeacher.length - 40) + '</div>' : ''));

                const okAll = !d.totalUnplaced && !d.issues && !d.noTeacher.length;
                $('chkBody').innerHTML = (okAll
                    ? '<div class="mb-3 p-3 rounded bg-green-50 text-green-700 text-sm font-semibold">✓ Jadval to\'liq va konfliktsiz.</div>' : '') + h;
            }

            // ===== Dars turi filtri (Hammasi / Ma'ruza / Amaliy) =====
            document.querySelectorAll('.tt-type').forEach(b => b.onclick = () => {
                typeFilter = b.dataset.type;
                document.querySelectorAll('.tt-type').forEach(x => x.classList.toggle('active', x === b));
                if (selected && !typeVisible(selected)) selected = null;   // filtrga mos kelmasa tanlovni bekor qilamiz
                renderAll();
            });

            // URLdan doska ochish
            const urlBoard = new URLSearchParams(location.search).get('board');
            if (urlBoard) loadBoard(urlBoard);
        })();
    </script>
</x-app-layout>
