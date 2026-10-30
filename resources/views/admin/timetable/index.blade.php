@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
@endpush

<x-app-layout>
@php
    $timetableUser = request()->user() ?? auth()->guard('teacher')->user() ?? auth()->guard('web')->user();
    $timetableDepartmentName = trim((string) ($timetableUser->department ?? ''));
    $timetableRoles = $timetableUser->getRoleNames()->toArray();
    $timetableActiveRole = session('active_role', $timetableRoles[0] ?? '');
    if (!in_array($timetableActiveRole, $timetableRoles, true) && $timetableRoles) {
        $timetableActiveRole = $timetableRoles[0];
    }
    $timetableAuditoriumAssignmentOnly = in_array($timetableActiveRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true);
    $timetableDepartmentHead = $timetableActiveRole === 'kafedra_mudiri';
    // O'qituvchi: jadvalni bevosita tahrirlamaydi, o'z darsiga vaqt taklifini yuboradi.
    $timetableReadOnly = in_array($timetableActiveRole, ['oqituvchi', 'fan_masuli'], true);
    // O'qituvchi identifikatori controllerda ikkala guard uchun bir xil aniqlanadi.
    // Fan mas'uli: "Mas'ul fanlarim" ko'rinishi (o'zi mas'ul bo'lgan fanlar)
    $timetableHasSubjects = $timetableActiveRole === 'fan_masuli';
    try {
        $timetableHasSubjects = $timetableHasSubjects || ($timetableTeacherId && \Illuminate\Support\Facades\Schema::hasTable('teacher_responsible_subjects')
            && \Illuminate\Support\Facades\DB::table('teacher_responsible_subjects')->where('teacher_id', $timetableTeacherId)->exists());
    } catch (\Throwable $e) { /* Biriktirishsiz fan mas'uli uchun ham bo'sh filtr saqlanadi. */ }
    $timetableAssignmentOnly = $timetableAuditoriumAssignmentOnly || $timetableDepartmentHead || $timetableReadOnly;
    $timetableCanPlace = in_array($timetableActiveRole, ['oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri'], true);
    // Fanlar menejeri subjects/subject-setting.save ni chaqiradi — o'qituvchiga yopiq.
    $timetableCanUseManager = $timetableAssignmentOnly && !$timetableReadOnly;
    // Jadval o'zgarishlarini tasdiqlovchi (registrator ofisi), so'rov yuboruvchi (kafedra mudiri)
    // va o'zgarishlar tarixini ko'ruvchilar (o'quv bo'limi, kichik admin)
    $timetableCanApprove = in_array($timetableActiveRole, ['superadmin', 'admin', 'registrator_ofisi'], true);
    // Butun doska bo'yicha joylanmagan kartalar hisoboti (Excel) — serverdagi ruxsat bilan bir xil rollar.
    $timetableCanBoardReport = in_array($timetableActiveRole, ['superadmin', 'admin', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi'], true);
    $timetableUsesChangeRequests = $timetableCanApprove
        || in_array($timetableActiveRole, ['oqituvchi', 'kafedra_mudiri', 'kichik_admin', 'oquv_bolimi', 'oquv_bolimi_boshligi'], true);
@endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dars jadvali tuzish</h2>
    </x-slot>

    {{-- Butun sahifa ekran balandligiga sig'adi (page-scroll yo'q): yuqori
         boshqaruv qatorlari va pastdagi panel doim ko'rinadi, faqat oradagi
         panjara ichida skroll bo'ladi. --}}
    <div class="max-w-full mx-auto px-2 lg:px-4" style="height: calc(100vh - 130px); display: flex; flex-direction: column; overflow: hidden;">

            {{-- Yuqori boshqaruv qatorlari (flex-shrink-0 — doim ko'rinadi); pastki chegarasini sichqoncha bilan surib kichraytirish mumkin --}}
            <div id="ttTopArea" style="flex: 0 0 auto;">

            {{-- Doska tanlash + boshqaruv paneli --}}
            <div class="tt-control-panel tt-top-panel mb-1">
                <div class="tt-top-toolbar">
                    <div class="tt-board-select">
                        <span class="tt-board-icon" aria-hidden="true"><img src="{{ asset('image/tahrirlash.png') }}" alt="" aria-hidden="true"></span>
                        <select id="boardSel" autocomplete="off">
                            <option value="">— Tanlang yoki yangi yarating —</option>
                            @foreach($boards as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->cards_count }} karta)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tt-toolbar-actions">
                        <button type="button" id="reloadBoardBtn" class="hidden asc-tool toolbar-action" title="Doska ma'lumotlarini qayta yuklash — sahifadan chiqmasdan, tanlovlar (fakultet, kurs, hafta...) saqlanadi"><span class="toolbar-icon" aria-hidden="true"><i class="bi bi-arrow-clockwise"></i></span>Yangilash</button>
                        <button type="button" id="newBoardBtn" class="asc-tool toolbar-action"><span class="toolbar-icon tt-icon-success" aria-hidden="true"><img src="{{ asset('image/05_doska.png') }}" alt="" aria-hidden="true"></span>Yangi doska</button>
                        <button type="button" id="genBtn" class="hidden asc-tool toolbar-action"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/02_cards.png') }}" alt="" aria-hidden="true"></span>Kartochkalar</button>
                        <button type="button" id="refreshNamesBtn" class="hidden asc-tool toolbar-action" title="Ishchi rejadagi joriy fan nomlarini kartochkalarga ko'chiradi (joylashuvlar saqlanadi)"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/06_subjects_book.png') }}" alt="" aria-hidden="true"></span>Fan nomlari</button>
                        @if(in_array($timetableActiveRole, ['admin', 'superadmin'], true))
                            {{-- Doska versiyalari: kunlik va xavfli amallardan oldingi nusxalar, joylashuvni tiklash --}}
                            <button type="button" id="snapshotsBtn" class="hidden asc-tool toolbar-action" title="Doska versiyalari (nusxalar) va joylashuvni tiklash"><span class="toolbar-icon" aria-hidden="true">🕘</span>Versiyalar</button>
                        @endif
                        <button type="button" id="delBoardBtn" class="hidden asc-tool toolbar-action tt-danger-btn"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/11_delete.png') }}" alt="" aria-hidden="true"></span>O'chirish</button>
                        <button type="button" id="settingsBtn" data-asc-toolbar class="hidden asc-tool toolbar-action"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/04_settings.png') }}" alt="" aria-hidden="true"></span>Sozlamalar</button>
                        <button type="button" id="managerBtn" data-asc-toolbar class="hidden asc-tool toolbar-action" data-dialog="subjects"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/06_subjects_book.png') }}" alt="" aria-hidden="true"></span>Ma'lumotlar</button>

                        <button type="button" id="excelViewBtn" data-asc-toolbar class="hidden asc-tool toolbar-action"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/05_print_preview.png') }}" alt="" aria-hidden="true"></span>Excelga yuklash</button>
                        <button type="button" id="checkBtn" data-asc-toolbar class="hidden asc-tool toolbar-action tt-check-btn"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/03_tekshirish.png') }}" alt="" aria-hidden="true"></span>Tekshiruv <span id="checkBadge" class="hidden"></span></button>
                        <button type="button" id="rulesBtn" data-asc-toolbar class="hidden asc-tool toolbar-action" title="Jadval qoidalari (bir kunda bo'lmasin, ketma-ket kelsin va h.k.)"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/04_settings.png') }}" alt="" aria-hidden="true"></span>Qoidalar <span id="rulesBadge" class="hidden"></span></button>
                        <button type="button" id="assignBtn" data-asc-toolbar class="hidden asc-tool toolbar-action"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/01_biriktirish_hujjatlari.png') }}" alt="" aria-hidden="true"></span>Biriktirish</button>
                        @if($timetableUsesChangeRequests)
                        <button type="button" id="changeReqBtn" class="hidden asc-tool toolbar-action" title="Jadval o'zgarishlari: tarix va registrator ofisi tasdig'i"><span class="toolbar-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>O'zgarishlar <span id="changeReqBadge" class="hidden tt-req-badge"></span></button>
                        @endif
                        <span id="boardMsg" class="text-xs"></span>
                    </div>
                </div>
                <div id="cardSyncProgress" class="hidden px-3 pb-2" role="status" aria-live="polite">
                    <div class="flex items-center justify-between gap-3 text-xs font-semibold text-blue-800">
                        <span id="cardSyncProgressText">Tasdiqlangan oqim va o‘quv reja fanlari tekshirilmoqda…</span>
                        <span id="cardSyncProgressElapsed" class="whitespace-nowrap tabular-nums">0 soniya</span>
                    </div>
                    <div class="tt-card-sync-track mt-1" aria-hidden="true"><span></span></div>
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
                    @if($isBoardAdmin ?? false)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Fanlar manbai</label>
                            <select id="nbSource" class="w-full rounded-md border-gray-300 text-sm" title="HEMIS — KTR sahifasidagi o'quv reja fanlari va soatlari (faqat Real doska)">
                                <option value="ishchi">Ishchi reja (O'quv reja to'g'riligi)</option>
                                <option value="hemis">HEMIS o'quv rejasi (KTR)</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 pb-2">
                            <input type="checkbox" id="nbAdminOnly" class="rounded border-gray-300">
                            <label for="nbAdminOnly" class="text-xs font-medium text-gray-600">Sinov doskasi (faqat admin ko'radi)</label>
                        </div>
                    @endif
                    <div class="md:col-span-7">
                        <button type="button" id="createBoardBtn" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Yaratish</button>
                        <span class="ml-2 text-xs text-gray-500">Bu sukut sozlamalar — har yo'nalish+kurs uchun keyin alohida o'zgartiriladi. Doska yaratilgach "Kartochkalarni yaratish" bosiladi.</span>
                    </div>
                </div>
            </div>

            {{-- Yo'nalish tanlash + statistika + shu yo'nalish uchun panjara sozlamasi --}}
            <div id="specBar" class="hidden tt-control-panel tt-work-panel mb-3" title="Kartani bosing → yashil katakni bosing. Joylashgan kartani bosib olib tashlash/ko'chirish/o'qituvchi-xona biriktirish mumkin.">
                <div class="tt-filters-row">
                    <div class="tt-field">
                        <label>Fakultet</label>
                        <div class="tt-dd"><button type="button" class="tt-dd-btn" id="facBtn" title="Fakultet(lar)ni tanlang"></button><div class="tt-dd-menu" id="facMenu"></div></div>
                    </div>
                    <div class="tt-field">
                        <label>Yo'nalish</label>
                        <div class="tt-dd"><button type="button" class="tt-dd-btn" id="dirBtn" title="Yo'nalish(lar)ni tanlang"></button><div class="tt-dd-menu" id="dirMenu"></div></div>
                    </div>
                    <div class="tt-field tt-course-field">
                        <label>Kurs</label>
                        <div class="tt-dd"><button type="button" class="tt-dd-btn" id="crsBtn" title="Kurs(lar)ni tanlang"></button><div class="tt-dd-menu" id="crsMenu"></div></div>
                    </div>

                    <div class="tt-field">
                        <label>Dars turi</label>
                        <div class="tt-lesson-tabs">
                            <button type="button" class="tt-type active" data-type="all">Hammasi</button>
                            <button type="button" class="tt-type" data-type="lecture">Ma'ruza</button>
                            <button type="button" class="tt-type" data-type="practice">Amaliy</button>
                        </div>
                    </div>
                    <div class="tt-field tt-view-field">
                        <label>Kesim</label>
                        <select id="viewMode">
                            <option value="group">Guruh</option>
                            <option value="teacher">O'qituvchi</option>
                            <option value="room">Auditoriya</option>
                            <option value="subject">Fan</option>
                            <option value="cycle">Sikl (4-6 kurs)</option>
                        </select>
                    </div>

                    <div class="tt-main-actions">
                        <button type="button" id="unplaceBtn" class="toolbar-action tt-danger-btn" title="Ko'rinayotgan qamrovdagi barcha joylashuvlarni bo'shatib, kartochkalarni panelga qaytaradi"><span class="toolbar-icon" aria-hidden="true"><img src="{{ asset('image/11_delete.png') }}" alt="" aria-hidden="true"></span>Bo'shatish</button>
                    </div>
                </div>

                <div class="tt-bottom-row">
                    <div class="tt-toggle-group">
                        <label class="tt-toggle-chip"><input type="checkbox" id="autoScope"><span class="tt-toggle-icon" aria-hidden="true"><img src="{{ asset('image/07_classes.png') }}" alt="" aria-hidden="true"></span>Butun doska</label>
                        @if($timetableTeacherId)
                        <span class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-[11px]" title="Butun doska yoki faqat sizga biriktirilgan darslar">
                            <button type="button" id="mineAll" class="px-2 py-1 bg-blue-600 font-semibold text-white">Butun doska</button>
                            @if($timetableDepartmentHead)
                            <button type="button" id="mineKafedra" class="px-2 py-1 bg-white text-gray-500" title="Faqat o'z kafedrangiz fanlari">Kafedraniki</button>
                            @endif
                            @if($timetableHasSubjects)
                            <button type="button" id="mineSubjects" class="px-2 py-1 bg-white text-gray-500" title="Faqat siz mas'ul bo'lgan fanlar">Mas'ul fanlarim</button>
                            @endif
                            <button type="button" id="mineOnly" class="px-2 py-1 bg-white text-gray-500">Faqat meniki</button>
                        </span>
                        @endif
                        <span id="weeklyLayerBox" class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-[11px]" title="Haftalik jadval qatlami: tashqi — o'quv bo'limi (umumiy, o'qituvchi/xonasiz), ichki — kafedra (haqiqiy jadval)">
                            <button type="button" id="wkLayerExternal" class="px-2 py-1 bg-white text-gray-500">Tashqi</button>
                            <button type="button" id="wkLayerInternal" class="px-2 py-1 bg-white text-gray-500">Ichki</button>
                        </span>
                        <div class="tt-week-field tt-week-inline">
                            <span class="tt-week-nav">
                                <button type="button" id="weekPrev" title="Oldingi hafta" aria-label="Oldingi hafta"><svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M12.5 4.5 7 10l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                <select id="weekSel"></select>
                                <button type="button" id="weekNext" title="Keyingi hafta" aria-label="Keyingi hafta"><svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M7.5 4.5 13 10l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            </span>
                            <span id="weekHint" class="hidden text-[10px] text-amber-600 font-medium">individual</span>
                            <button type="button" id="weeklyExcel" class="tt-weekly-excel" title="Ekrandagi haftalik jadvalni (tanlangan hafta, qatlam va ko'rinish qamrovi bilan) Excelga yuklash"><i class="bi bi-file-earmark-excel-fill" aria-hidden="true"></i> Excel</button>
                            @unless($timetableAssignmentOnly)
                            <button type="button" id="compactWeekBtn" class="tt-ktr-weeks-btn tt-compact-btn hidden" title="Ichki jadval: shu haftada o'tilmaydigan dars (masalan ma'ruza) bo'shatgan joyga keyingi darslarni yuqoriga suradi, joylashmagan kartalarni blok bo'lib (bir fanning kartalari ketma-ket, bo'linmasdan) tashqi joyiga yoki o'sha kunning ichidagi eng yaqin bo'sh joyga qo'yadi (o'qituvchi/xona/guruh bandligi server tomonidan tekshiriladi)">Haftani zichlash</button>
                            <button type="button" id="suggestWeekBtn" class="tt-ktr-weeks-btn tt-compact-btn hidden" title="Shu haftada joylanmagan kartalarni sig'dirish takliflari: bo'sh vaqt, boshqa xona, to'sib turgan darsni surish, boshqa kun, boshqa hafta va eng oxirida boshqa o'qituvchi. Hech narsa o'zi yozilmaydi — har bir taklif «Qo'llash» bilan bajariladi.">Takliflar</button>
                            <button type="button" id="ktrWeeksBtn" class="tt-ktr-weeks-btn" title="Tanlangan fakultet/yo'nalish/kursdagi haftalik fanlar: qaysi hafta ma'ruza va necha soat amaliy bo'lishini KTR'dan belgilaydi">KTR bo'yicha haftalar</button>
                            @endunless
                            @if($timetableCanApprove)
                            <button type="button" id="hemisSyncBtn" class="tt-ktr-weeks-btn" title="Tanlangan qamrov: LMS ichki jadvali va KTR ↔ HEMIS dars jadvali. Bugungacha o'tilgan darslar balansi (soat va soni), ertadan boshlab tuzatish takliflari va HEMIS'da qilinadigan amallar">HEMIS solishtirish</button>
                            @endif
                            @if(in_array($timetableActiveRole, ['superadmin', 'admin', 'registrator_ofisi', 'kafedra_mudiri', 'oqituvchi'], true))
                            <button type="button" id="blockTicketsBtn" class="tt-ktr-weeks-btn" title="HEMIS to'siqlari bo'yicha so'rovlar: javob berish va (kafedra mudiri) hal qilish">To'siq so'rovlari</button>
                            @endif
                        </div>
                    </div>
                    <span id="autoMsg" class="tt-auto-msg text-[11px] text-emerald-700 font-medium"></span>
                    <div id="statChips" class="tt-statistics"></div>
                </div>
            </div>

            </div>{{-- /yuqori boshqaruv qatorlari --}}
            <div id="ttResizeTop" class="tt-resizer" title="Surib menyuni kichraytiring yoki kengaytiring (ikki marta bosish — asl holiga)"></div>

            {{-- Asosiy maydon: panjara (flex bilan qolgan balandlikni to'ldiradi,
                 faqat shu ichida skroll) + pastda joylashmagan kartalar --}}
            <div id="mainArea" class="hidden" style="flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;">
                {{-- Panjara --}}
                <div id="gridWrap" class="bg-white shadow-sm sm:rounded-lg overflow-auto" style="flex: 1 1 auto; min-height: 0; max-width: 100%;">
                    <table id="grid" class="border-collapse text-[11px]"></table>
                </div>
                {{-- Sikl (4-6 kurs) kalendar ko'rinishi — sana × guruh bloklari --}}
                <div id="cycleArea" class="hidden bg-white shadow-sm sm:rounded-lg overflow-auto" style="flex: 1 1 auto; min-height: 0; max-width: 100%;">
                    <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-gray-100 sticky top-0 bg-white z-10">
                        <span class="text-xs font-semibold text-gray-700">Sikl jadvali (4-6 kurs)</span>
                        <label class="text-[11px] text-gray-500 flex items-center gap-1">Semestr boshlanishi:
                            <input type="date" id="cycleStart" class="rounded border-gray-300 text-xs py-0.5"></label>
                        <label class="text-[11px] text-gray-500 flex items-center gap-1" title="Bayram/dam olish kunlari — sikl bloklari bu kunlarga tushmaydi">Bayram:
                            <input type="date" id="cycleHolAdd" class="rounded border-gray-300 text-xs py-0.5"></label>
                        <button type="button" id="cycleHolAddBtn" class="asc-tool text-xs py-1">+ qo'shish</button>
                        <button type="button" id="cycleRefresh" class="asc-tool text-xs py-1">Yangilash</button>
                        <button type="button" id="cycleExcel" class="asc-tool text-xs py-1 cyc-excel-btn" title="Ekrandagi sikl jadvalini (tashqi yoki ichki) Excelga yuklash"><i class="bi bi-file-earmark-excel-fill" aria-hidden="true"></i> Excel</button>
                        <label class="text-[11px] text-gray-500 flex items-center gap-1" title="Yakshanba va bayram ustunlarini jadvaldan yashiradi (ma'lumot o'zgarmaydi)">
                            <input type="checkbox" id="cycleHideOff" class="rounded border-gray-300"> Dam olish kunlarini yashirish</label>
                        <label class="text-[11px] text-gray-500 flex items-center gap-1" title="Sig'masa, bir fanning turli oqimlardagi sikllari ko'rsatilgan o'quv kunigacha kesishishi mumkin">
                            <input type="checkbox" id="cycleAllowOverlap" class="rounded border-gray-300"> Kesishishga ruxsat
                            <input type="number" id="cycleOverlapDays" min="1" max="60" value="1" class="w-14 rounded border-gray-300 text-xs py-0.5" disabled> kun</label>
                        <label class="text-[11px] text-gray-500 flex items-center gap-1" title="Sikllar orasida 2 o'quv kuni bo'sh qolishi talab qilinmaydi (rasmiy jadvalda sikllar ketma-ket bo'lsa)">
                            <input type="checkbox" id="cycleIgnoreGap" class="rounded border-gray-300"> Oraliqsiz</label>
                        <span class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-[11px]" title="Tashqi — o'quv bo'limining umumiy (tavsiyaviy) jadvali; Ichki — kafedraning KTR bo'yicha ishchi jadvali">
                            <button type="button" id="cycLayerExternal" class="px-2 py-1 bg-white text-gray-500">Tashqi</button>
                            <button type="button" id="cycLayerInternal" class="px-2 py-1 bg-white text-gray-500">Ichki</button>
                        </span>
                        <span id="cycLayerRo" class="hidden text-[11px] text-amber-700 bg-amber-50 rounded px-1.5 py-0.5">faqat ko'rish</span>
                        <span class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-[11px]" title="Ko'rinish rejimi">
                            <button type="button" id="cycViewFlow" class="px-2 py-1 bg-blue-600 font-semibold text-white">Oqim bo'yicha</button>
                            <button type="button" id="cycViewGroup" class="px-2 py-1 bg-white text-gray-500">Guruh bo'yicha</button>
                        </span>
                        <span id="cycleMsg" class="text-[11px] text-gray-500"></span>
                    </div>
                    <div id="cycleHolBar" class="hidden flex flex-wrap items-center gap-1.5 px-3 py-1.5 border-b border-gray-100 bg-amber-50">
                        <span class="text-[11px] text-amber-700 font-medium">Bayram kunlari:</span>
                        <span id="cycleHolList" class="flex flex-wrap items-center gap-1"></span>
                    </div>
                    <div id="cyclePendingBar" class="hidden"></div>
                    <div id="cycleGridWrap" class="overflow-auto"><table id="cycleGrid" class="border-collapse text-[11px]"></table><div id="cycleOverlay"></div></div>
                </div>

                <div id="ttResizeBottom" class="tt-resizer" title="Surib joylashmagan kartalar panelini kichraytiring yoki kengaytiring (ikki marta bosish — asl holiga)"></div>
                {{-- Joylashtirilmagan kartochkalar — pastda gorizontal panel (flex-shrink-0 — doim ko'rinadi) --}}
                <div id="ttBottomPanel" class="bg-white shadow-sm sm:rounded-lg" style="flex: 0 0 auto; box-shadow: 0 -6px 12px -4px rgba(0,0,0,.15);">
                    <div class="px-3 py-1 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span id="cardPanelTitle" class="text-xs font-semibold text-gray-700">Joylashmagan kartalar</span>
                            <span id="cardPanelHint" class="hidden text-[10px] text-slate-500"></span>
                            {{-- Hafta ko'rinishida shu haftada o'tilmaydigan kartalar panelga
                                 tushmaydi; kerak bo'lsa shu tugma orqali ko'rsatiladi. --}}
                            <button type="button" id="skipToggle"
                                class="hidden text-[11px] rounded px-1.5 py-0.5 border border-gray-200 text-gray-500 hover:bg-gray-50"></button>
                            {{-- Paneldagi kartalarni fan, guruh va dars turi bo'yicha saralash --}}
                            <span id="pnFilters" class="flex items-center gap-1.5">
                                <select id="pnSubject" class="pn-f" aria-label="Fan bo'yicha filtr"><option value="">Barcha fanlar</option></select>
                                <select id="pnOqim" class="pn-f" aria-label="Oqim bo'yicha filtr"><option value="">Barcha oqimlar</option></select>
                                <div class="tt-dd tt-dd-up"><button type="button" class="tt-dd-btn pn-dd-btn" id="pnGroupBtn" aria-label="Guruh bo'yicha filtr"></button><div class="tt-dd-menu" id="pnGroupMenu"></div></div>
                                <select id="pnType" class="pn-f" aria-label="Dars turi bo'yicha filtr">
                                    <option value="">Barcha turlar</option>
                                    <option value="lecture">Ma'ruza</option>
                                    <option value="practice">Amaliy</option>
                                </select>
                                <button type="button" id="pnReset" class="hidden text-[11px] rounded px-1.5 py-0.5 border border-gray-200 text-gray-500 hover:bg-gray-50" title="Filtrlarni tozalash">&times;</button>
                                <span id="pnFilterCount" class="hidden text-[10px] text-slate-500"></span>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="cycleGroupFilter" class="hidden text-[11px] rounded border-gray-300 py-1 pl-2 pr-7 text-gray-600" aria-label="Oqim bo'yicha filtr"><option value="">Barcha oqimlar</option></select>
                            <button type="button" id="subjectColorsBtn"
                                class="hidden text-[11px] rounded px-2 py-1 border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                title="Har bir fan uchun rang tanlash">
                                <i class="bi bi-palette" aria-hidden="true"></i> Ranglar
                            </button>
                            <span id="unplacedCount" class="text-xs font-bold text-amber-600"></span>
                            <button type="button" id="unplacedExportBtn"
                                class="hidden text-[11px] rounded px-2 py-1 border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                                title="Joylanmagan kartalar va sabablarini Excelga yuklash">
                                <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Excel (sabablar)
                            </button>
                            @if ($timetableCanBoardReport)
                            <button type="button" id="unplacedReportBtn"
                                class="text-[11px] rounded px-2 py-1 border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                                title="Butun doska bo'yicha joylanmagan kartalar: fakultet, yo'nalish, kurs va hafta kesimida (tanlangan fakultetga qaramasdan, joriy qatlam bo'yicha)">
                                <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Butun doska (Excel)
                            </button>
                            @endif
                        </div>
                    </div>
                    <div class="cyc-assign-backdrop" id="cycAssignModal">
                <div class="cyc-assign-panel">
                    <div class="cyc-assign-head">
                        <b id="cycAssignTitle">Sikl bloki</b>
                        <span id="cycAssignMeta"></span>
                    </div>
                    <div class="cyc-assign-body">
                        <div>
                            <label id="cycAssignKafedra">O'qituvchi</label>
                            <select id="cycAssignTeacher"><option value="">— Tanlanmagan —</option></select>
                        </div>
                        <div>
                            <label>Xona (bo'sh xonalar)</label>
                            <select id="cycAssignRoom"><option value="">— Tanlanmagan —</option></select>
                        </div>
                    </div>
                    <div class="cyc-assign-foot">
                        <button type="button" id="cycAssignReset" title="O'qituvchi, vaqt va xonani olib tashlash">Tozalash</button>
                        <span class="cyc-assign-right">
                            <button type="button" id="cycAssignClose">Bekor</button>
                            <button type="button" id="cycAssignSave">Saqlash</button>
                        </span>
                    </div>
                </div>
            </div>

            <div id="cardPanel" class="p-2 flex flex-wrap gap-1.5 overflow-y-auto bg-white" style="max-height: 120px;"></div>
                </div>
            </div>

            {{-- Fan ranglari modali --}}
            <div id="subjectColorsModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="tt-modal-win asc-small-modal bg-white rounded-xl shadow-xl w-full max-w-lg">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-palette"></i></span>
                                <div>
                                    <div>Fan ranglari</div>
                                    <div class="text-xs text-white/70">Rangni tanlang — sikl jadvalida shu rang ishlatiladi</div>
                                </div>
                            </div>
                            <button type="button" id="scClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="scList" class="p-4 overflow-y-auto" style="max-height: 60vh;"></div>
                        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                            <button type="button" id="scReset"
                                class="text-[11px] rounded px-2 py-1 border border-gray-200 text-gray-600 hover:bg-gray-50">
                                Hammasini avtomatik rangga qaytarish
                            </button>
                            <button type="button" id="scSave"
                                class="text-xs rounded px-3 py-1.5 bg-indigo-600 text-white hover:bg-indigo-700">Saqlash</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kartochka rekvizitlari modali --}}
            <div id="cardModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="tt-modal-win asc-small-modal bg-white rounded-xl shadow-xl w-full max-w-md">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-card-text"></i></span>
                                <div>
                                    <div id="cmTitle"></div>
                                    <div id="cmSub" class="text-xs text-white/70"></div>
                                </div>
                            </div>
                            <button type="button" id="cmClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
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
                                <div class="hidden">
                                    <select id="cmStartHalf" class="w-full rounded-md border-gray-300 text-sm">
                                        <option value="0">Para boshidan</option>
                                        <option value="1">Para o'rtasidan (yarmidan)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Boshlanish vaqti</label>
                                    <select id="cmStartSlot" class="w-full rounded-md border-gray-300 text-sm"></select>
                                </div>
                                <div id="cmTimeHint" class="col-span-2 text-[11px] text-indigo-600"></div>
                            </div>
                            <div id="cmMsg" class="hidden text-sm rounded px-3 py-2"></div>
                        </div>
                        <div class="flex justify-between gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-lg">
                            <div class="flex gap-2">
                                <button type="button" id="cmUnplace" class="px-3 py-1.5 text-sm bg-amber-50 text-amber-700 rounded-md hover:bg-amber-100"><i class="bi bi-arrow-90deg-left" aria-hidden="true"></i> Jadvaldan olish</button>
                                <button type="button" id="cmResetWeek" class="hidden px-3 py-1.5 text-sm bg-sky-50 text-sky-700 rounded-md hover:bg-sky-100"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Shablonga qaytarish</button>
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
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-none flex flex-col" style="width: calc(100vw - 120px); max-width: none; height: calc(100vh - 100px); max-height: calc(100vh - 100px);">
                        {{-- Sarlavha satri --}}
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span id="ascIcon" class="asc-header-icon" aria-hidden="true"></span>
                                <span id="ascTitle"></span>
                            </div>
                            <button type="button" id="ascClose" class="asc-close-btn" aria-label="Yopish" title="Yopish">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                        {{-- aSc uslubidagi chap navigatsiya + ishchi panel --}}
                        <div class="flex gap-3 p-4 overflow-visible" style="min-height: 600px;">
                            <nav class="asc-data-nav w-20 shrink-0 self-start h-fit flex flex-col items-center gap-2 rounded-xl bg-white p-2" style="height: fit-content; align-self: flex-start;" aria-label="Jadval ma'lumotlari">
                                                                <button type="button" class="asc-nav-btn active" data-asc-type="subjects" data-tooltip="Darslar" aria-label="Darslar" aria-selected="true"><span class="asc-nav-icon" aria-hidden="true"><img src="{{ asset('image/06_subjects_book.png') }}" alt="" aria-hidden="true"></span><span>Darslar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="groups" data-tooltip="Guruhlar" aria-label="Guruhlar" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><img src="{{ asset('image/07_classes.png') }}" alt="" aria-hidden="true"></span><span>Guruhlar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="auditoriums" data-tooltip="Auditoriyalar" aria-label="Auditoriyalar" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><img src="{{ asset('image/08_classrooms.png') }}" alt="" aria-hidden="true"></span><span>Auditoriyalar</span></button>
                                <button type="button" class="asc-nav-btn" data-asc-type="teachers" data-tooltip="O'qituvchilar" aria-label="O'qituvchilar" aria-selected="false"><span class="asc-nav-icon" aria-hidden="true"><img src="{{ asset('image/09_teachers_cap.png') }}" alt="" aria-hidden="true"></span><span>O'qituvchilar</span></button>
                            </nav>
                            <div id="ascPanel" class="flex-1 flex gap-3 min-w-0">
                                <div class="flex-1 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden min-w-0 shadow-sm">
                                    <div class="asc-list-toolbar flex flex-wrap items-center gap-3 px-4 py-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-blue-50">
                                        <span id="ascListLabel" class="text-sm font-bold text-slate-700">Darslar ro'yxati:</span>
                                        <div id="ascButtons" class="flex items-center flex-wrap gap-2 ml-auto"></div>
                                        <input id="ascSearch" placeholder="Qidirish..." class="w-64 rounded-lg border-slate-300 text-sm py-2 shadow-sm">
                                        <select id="ascFilter" class="hidden rounded-lg border-slate-300 text-sm py-2 shadow-sm"></select>
                                        <span id="ascCount" class="text-xs text-gray-400"></span>
                                    </div>
                                    <div class="overflow-y-auto overflow-x-hidden asc-table-scroll asc-subject-table-scroll" style="max-height: 64vh;">
                                        <table id="ascTable" class="w-full text-sm asc-table"></table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Pastki panel --}}
                        <div class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                            <div id="ascFootMsg" class="text-sm text-slate-500"></div>
                            <button type="button" id="ascCloseBtn" class="asc-btn primary px-5 py-2">Yopish</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Auditoriya tahrirlash mini-formasi --}}
            <div id="audEditModal" class="hidden tt-modal tt-modal-top">
                <div class="tt-modal-body">
                    <div class="tt-modal-win asc-small-modal bg-white rounded-xl shadow-xl w-full max-w-md">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                                <span id="aeTitle">Auditoriya</span>
                            </div>
                            <button type="button" id="aeClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
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
            <div id="excelModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="tt-modal-win asc-small-modal bg-white rounded-xl shadow-2xl w-full max-w-[1500px]" style="max-height: 94vh;">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                                <span>Dars jadvali — Excel ko'rinish</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex rounded-md overflow-hidden border border-gray-300 text-xs">
                                    <button type="button" class="ex-mode active px-2.5 py-1" data-mode="group">Guruh bo'yicha</button>
                                    <button type="button" class="ex-mode px-2.5 py-1 border-l border-gray-300" data-mode="teacher">O'qituvchi bo'yicha</button>
                                    <button type="button" class="ex-mode px-2.5 py-1 border-l border-gray-300" data-mode="room">Auditoriya bo'yicha</button>
                                </div>
                                <button type="button" id="excelDownload" class="asc-btn"><i class="bi bi-download" aria-hidden="true"></i> Excelga yuklab olish</button>
                                <button type="button" id="excelPrint" class="asc-btn"><i class="bi bi-printer" aria-hidden="true"></i> Chop / PDF</button>
                                <button type="button" id="excelClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div id="excelBody" class="overflow-auto p-3" style="max-height: 86vh;"></div>
                    </div>
                </div>
            </div>

            {{-- ═══ Umumiy sozlamalar (aSc "Установки" uslubida) ═══ --}}
            <div id="setModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win tt-settings-modal bg-[#f0f0f0] rounded shadow-2xl w-full flex flex-col">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-gear"></i></span>
                                <span>Umumiy sozlamalar</span>
                            </div>
                            <button type="button" id="setClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                        </div>
                        {{-- Tablar --}}
                        <div class="flex gap-1 px-2 pt-2 bg-[#f0f0f0]">
                            <button type="button" class="set-tab active" data-tab="basic">Asosiy ma'lumotlar</button>
                            <button type="button" class="set-tab" data-tab="bells">Qo'ng'iroqlar (juftliklar vaqti)</button>
                            <button type="button" class="set-tab" data-tab="days">Kunlar</button>
                            <button type="button" class="set-tab" data-tab="grid">Dars kunlari va paralar</button>
                        </div>
                        <div class="set-content bg-white border border-gray-300 mx-2 mb-2 rounded-b p-4 overflow-y-auto">
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
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1" title="Shu oraliqqa tushgan haftalar o'quv haftasi hisoblanmaydi (hafta raqami ta'tildan keyin davom etadi)">Ta'til oralig'i (haftalik jadval, masalan qishki ta'til)</label>
                                    <div class="flex items-center gap-2">
                                        <input id="stBreakFrom" type="date" class="rounded-md border-gray-300 text-sm"> —
                                        <input id="stBreakTo" type="date" class="rounded-md border-gray-300 text-sm">
                                    </div>
                                </div>
                                <p class="col-span-2 text-xs text-gray-400">O'quv yili va semestr doska yaratilganda belgilangan — o'zgartirish uchun yangi doska yarating.</p>
                            </div>
                            {{-- Qo'ng'iroqlar --}}
                            <div id="setBells" class="set-pane hidden">
                                <div class="flex items-center gap-2 mb-2">
                                    <button type="button" id="stAddPair" class="asc-btn primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Para qo'shish</button>
                                    <button type="button" id="stAddBreak" class="asc-btn"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tanaffus qo'shish</button>
                                    <span class="mx-1 h-6 w-px bg-gray-300"></span>
                                    <button type="button" id="stMoveUp" class="asc-btn" title="Belgilangan qatorni yuqoriga ko'chirish" disabled><i class="bi bi-chevron-up" aria-hidden="true"></i> Yuqoriga</button>
                                    <button type="button" id="stMoveDown" class="asc-btn" title="Belgilangan qatorni pastga ko'chirish" disabled><i class="bi bi-chevron-down" aria-hidden="true"></i> Pastga</button>
                                    <button type="button" id="stResetBells" class="asc-btn ml-auto"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Standart jadval</button>
                                </div>
                                <table class="w-full text-xs asc-table" id="stBellTable"></table>
                                <p class="text-xs text-gray-400 mt-2">Juftliklar (para) tartib bilan raqamlanadi va panjaradagi para sonini belgilaydi. Tanaffuslar faqat chop/Excel ko'rinishida ko'rinadi. Vaqt formati <b>SS:DD</b> (masalan 08:30).</p>
                            </div>
                            {{-- Kunlar --}}
                            <div id="setDays" class="set-pane hidden">
                                <p class="text-xs text-gray-500 mb-2">Kun nomlarini o'zgartirishingiz mumkin (chop etishda ishlatiladi).</p>
                                <div id="stDayNames" class="grid grid-cols-2 gap-2"></div>
                            </div>
                            <div id="setGrid" class="set-pane hidden">
                                <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700">Dars kunlari va paralar</div>
                                        <p class="text-xs text-slate-500">Har bir HEMIS o‘quv rejasining kalendarini alohida saqlang. Kartochkalar va joylashuvlar saqlanadi.</p>
                                    </div>
                                    <div class="flex flex-wrap items-end gap-2">
                                        <label class="text-xs text-slate-500">Fakultet
                                            <select id="stGridFacultyFilter" class="grid-setting-filter">
                                                <option value="">Barcha fakultetlar</option>
                                            </select>
                                        </label>
                                        <label class="text-xs text-slate-500">Yo'nalish
                                            <select id="stGridSpecialtyFilter" class="grid-setting-filter">
                                                <option value="">Barcha yo'nalishlar</option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                                <div id="stGridRows" class="space-y-2"></div>
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
                    <div class="tt-modal-win asc-small-modal bg-white rounded-xl shadow-xl w-full max-w-sm">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                                <span id="beTitle">Qatorni tahrirlash</span>
                            </div>
                            <button type="button" id="beClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
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
            {{-- Qoidalar ro'yxati (aSc "Связь между уроками" uslubida) --}}
            <div id="rulesModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full flex flex-col" style="width: min(1100px, 96vw); max-width: none; height: min(640px, 88vh);">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
                                <span>Qoidalar — darslar orasidagi bog'liqlik</span>
                            </div>
                            <button type="button" id="rulesClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="flex-1 overflow-auto p-3" style="min-height:0;">
                            <table class="w-full text-xs border-collapse tt-rules-table">
                                <thead>
                                    <tr>
                                        <th style="width:34px">№</th>
                                        <th style="width:44px">Faol</th>
                                        <th>Fanlar</th>
                                        <th>Qamrov</th>
                                        <th>Tavsif</th>
                                        <th style="width:90px">Og'irlik</th>
                                        <th>Izoh</th>
                                    </tr>
                                </thead>
                                <tbody id="rulesBody"></tbody>
                            </table>
                            <div id="rulesEmpty" class="hidden text-sm text-gray-400 py-10 text-center">
                                Qoidalar yo'q. Pastdagi <b>+ Qo'shish</b> tugmasi bilan qoida qo'shing.
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2 border-t border-gray-300 bg-[#f0f0f0] rounded-b">
                            <button type="button" id="ruleAddBtn" class="asc-tool text-xs">+ Qo'shish</button>
                            <button type="button" id="ruleEditBtn" class="asc-tool text-xs">Tahrirlash</button>
                            <button type="button" id="ruleDelBtn" class="asc-tool text-xs tt-danger-btn">O'chirish</button>
                            <span class="h-5 w-px bg-gray-300 mx-1"></span>
                            <button type="button" id="ruleToggleBtn" class="asc-tool text-xs">Faol / nofaol</button>
                            <button type="button" id="ruleUpBtn" class="asc-tool text-xs" title="Yuqoriga">↑</button>
                            <button type="button" id="ruleDownBtn" class="asc-tool text-xs" title="Pastga">↓</button>
                            <span id="rulesMsg" class="text-[11px] text-gray-500 ml-1"></span>
                            <button type="button" id="rulesOkBtn" class="asc-tool text-xs ml-auto">OK</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Yangi/tahrir qoida — shart tanlash (aSc "Новая взаимосвязь") --}}
            <div id="ruleEditModal" class="hidden tt-modal tt-modal-top">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full flex flex-col" style="width: min(1000px, 96vw); max-width: none; height: min(620px, 90vh);">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-node-plus"></i></span>
                                <span id="ruleEditTitle">Yangi qoida</span>
                            </div>
                            <button type="button" id="ruleEditClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="flex-1 overflow-hidden p-3" style="min-height:0;">
                            <div class="flex gap-3 h-full" style="min-height:0;">
                                {{-- Chap ustun: fanlar + qamrov + og'irlik --}}
                                <div class="flex flex-col gap-3" style="width:330px; min-height:0;">
                                    <div class="bg-white border border-gray-300 rounded p-2 flex flex-col" style="min-height:0; flex:1 1 50%;">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-blue-700">1. Fanlar</span>
                                            <label class="text-[11px] text-gray-600 flex items-center gap-1">
                                                <input type="checkbox" id="ruleAllSubjects" class="rounded border-gray-300"> Barchasi
                                            </label>
                                        </div>
                                        <input id="ruleSubjSearch" placeholder="Fan qidirish..." class="w-full rounded border-gray-300 text-xs mb-1 py-1">
                                        <div id="ruleSubjList" class="flex-1 overflow-auto border border-gray-200 rounded p-1" style="min-height:0;"></div>
                                    </div>
                                    <div class="bg-white border border-gray-300 rounded p-2 flex flex-col" style="min-height:0; flex:1 1 40%;">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-blue-700">2. Yo'nalish · kurs</span>
                                            <label class="text-[11px] text-gray-600 flex items-center gap-1">
                                                <input type="checkbox" id="ruleAllScopes" class="rounded border-gray-300"> Barchasi
                                            </label>
                                        </div>
                                        <div id="ruleScopeList" class="flex-1 overflow-auto border border-gray-200 rounded p-1" style="min-height:0;"></div>
                                    </div>
                                    <div class="bg-white border border-gray-300 rounded p-2">
                                        <label class="block text-[11px] text-gray-600 mb-1">Qoida og'irligi</label>
                                        <select id="ruleWeight" class="w-full rounded border-gray-300 text-xs mb-2">
                                            <option value="majburiy">Majburiy (buzilmasin)</option>
                                            <option value="normal" selected>Normal</option>
                                            <option value="yengil">Yengil (imkon qadar)</option>
                                        </select>
                                        <label class="block text-[11px] text-gray-600 mb-1">Izoh</label>
                                        <input id="ruleNote" class="w-full rounded border-gray-300 text-xs" placeholder="ixtiyoriy">
                                    </div>
                                </div>
                                {{-- O'ng ustun: shartlar ro'yxati --}}
                                <div class="flex-1 bg-white border border-gray-300 rounded p-2 flex flex-col" style="min-height:0;">
                                    <span class="text-xs font-bold text-blue-700 mb-1">3. Shart</span>
                                    <div id="ruleCondList" class="flex-1 overflow-auto pr-1" style="min-height:0;"></div>
                                    <div id="ruleLectureWeekOptions" class="hidden mt-2 rounded border border-amber-200 bg-amber-50 p-3">
                                        <label class="block text-xs font-bold text-amber-800 mb-1">Ma'ruza haftalari</label>
                                        <select id="ruleLectureWeekMode" class="w-full rounded border-amber-300 bg-white text-xs">
                                            <option value="auto">Avtomatik (kam: toq/juft, ko'p: ketma-ket)</option>
                                            <option value="spread">Teng taqsimlash</option>
                                            <option value="odd">Toq haftalar (1, 3, 5, ...)</option>
                                            <option value="even">Juft haftalar (2, 4, 6, ...)</option>
                                        </select>
                                        <div class="mt-2 text-[11px] leading-4 text-amber-700">
                                            Avtomatikda ma'ruzalar semestr haftalarining yarmidan kam bo'lsa, fanlar navbat bilan
                                            toq va juft haftalarga beriladi; yarim yoki ko'p bo'lsa 1-haftadan ketma-ket joylashadi.
                                            Fan tanlanmasa — barcha fanlarga umumiy qoida. Fan tanlansa — shu fan uchun istisno.
                                            Qo'llash uchun kartochkalarni qayta yarating.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2 border-t border-gray-300 bg-[#f0f0f0] rounded-b">
                            <label class="text-[11px] text-gray-600 flex items-center gap-1">
                                <input type="checkbox" id="ruleActive" class="rounded border-gray-300" checked> Faol
                            </label>
                            <span id="ruleEditMsg" class="text-[11px] text-red-600 ml-2"></span>
                            <button type="button" id="ruleCancelBtn" class="asc-tool text-xs ml-auto">Bekor qilish</button>
                            <button type="button" id="ruleSaveBtn" class="asc-tool text-xs tt-success-btn">Saqlash</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="asgRoomModal" class="hidden tt-modal tt-modal-top" role="dialog" aria-modal="true" aria-labelledby="asgRoomTitle">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl flex flex-col" style="max-width: 640px;">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true"><i class="bi bi-door-open"></i></span>
                                <span id="asgRoomTitle">Xona tanlash</span>
                            </div>
                            <button type="button" id="asgRoomModalClose" class="asc-close-btn" aria-label="Yopish" title="Yopish"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="p-4 space-y-3 bg-white">
                            <div id="asgRoomFor" class="text-xs text-slate-500"></div>
                            <input id="asgRoomSearch" placeholder="Xona qidirish (raqam, nom yoki bino)..." class="w-full rounded-md border-slate-300 text-sm">
                            <div id="asgRoomList" class="rm-list"></div>
                            <div class="flex justify-end">
                                <button type="button" id="asgRoomCancel" class="asc-btn px-5 py-2">Bekor qilish</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="assignModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-none flex flex-col" style="width: calc(100vw - 200px); max-width: none; height: calc(100vh - 100px); max-height: calc(100vh - 100px);">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true">
                                    <i class="bi bi-person-plus"></i>
                                </span>
                                <span id="asgTitle">O'qituvchi biriktirish</span>
                            </div>
                            <button type="button" id="asgClose" class="asc-close-btn" aria-label="Yopish" title="Yopish">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="asg-tabs flex items-center gap-1 px-4 pt-3 border-b border-slate-200 bg-white">
                            @if(!$timetableAuditoriumAssignmentOnly)
                            <button type="button" class="asg-tab-button active" data-asg-tab="teachers">
                                <i class="bi bi-person-plus" aria-hidden="true"></i> Fanlarni biriktirish
                            </button>
                            @endif
                            <button type="button" class="asg-tab-button {{ $timetableAuditoriumAssignmentOnly ? 'active' : '' }}" data-asg-tab="auditoriums">
                                <i class="bi bi-door-open" aria-hidden="true"></i> Auditoriyalarni biriktirish
                            </button>
                        </div>
                        <div id="asgTeachersPanel" data-asg-panel="teachers" class="{{ $timetableAuditoriumAssignmentOnly ? 'hidden' : '' }} assign-modal-content flex gap-3 p-4 overflow-hidden" style="min-height: 0;">
                            {{-- Chap: dars birliklari --}}
                            <div class="assign-pane flex-1 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="assign-toolbar flex flex-wrap items-center gap-2 px-3 py-3 border-b border-slate-200 bg-slate-50">
                                    <span class="text-sm font-semibold text-slate-700">Dars birliklari</span>
                                    <select id="asgFilter" class="rounded-md border-slate-300 text-xs py-1.5"></select>
                                    <select id="asgTypeFilter" class="rounded-md border-slate-300 text-xs py-1.5">
                                        <option value="">— barcha turlar —</option>
                                        <option value="Amaliy">Amaliy</option>
                                        <option value="Ma'ruza">Ma'ruza</option>
                                    </select>
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600 ml-1"><input type="checkbox" id="asgOnlyEmpty" class="rounded border-slate-300"> faqat biriktirilmagan</label>
                                    <input id="asgSearch" placeholder="Fan qidirish..." class="ml-auto w-48 rounded-md border-slate-300 text-xs py-1.5">
                                    <span id="asgCount" class="text-xs text-slate-400"></span>
                                    @if(in_array($timetableActiveRole, ['admin', 'superadmin', 'registrator_ofisi'], true))
                                        {{-- Kafedralar kesimida o'qituvchi va xona biriktirish hisoboti (admin, registrator ofisi) --}}
                                        <button type="button" id="asgReportBtn" class="asc-btn text-xs" style="background:#059669;color:#fff;border-color:#059669"
                                                title="Har bir kafedra bo'yicha o'qituvchi va xona biriktirilgan / biriktirilmagan darslar">
                                            <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel hisobot
                                        </button>
                                    @endif
                                </div>
                                <div class="overflow-y-auto overflow-x-hidden asc-table-scroll" style="max-height: none; flex: 1 1 auto;" data-drag-scroll>
                                    <table id="asgTable" class="w-full text-xs asc-table"></table>
                                </div>
                            </div>
                            {{-- O'ng: o'qituvchi tanlash --}}
                            <div class="assign-teacher-pane w-64 shrink-0 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="assign-pane-title px-4 py-3 border-b border-slate-200 bg-slate-50 text-sm font-semibold text-slate-700">O'qituvchi</div>
                                <div class="p-4 space-y-3 flex-1 flex flex-col overflow-hidden">
                                    <div id="asgUnitInfo" class="assign-unit-info text-xs text-slate-500 min-h-[42px] rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2">Chapdan dars birligini tanlang</div>
                                    <input id="asgTeacherSearch" placeholder="Qidirish..." class="w-full rounded-md border-slate-300 text-sm" disabled>
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" id="asgKafedraOnly" class="rounded border-slate-300" checked> shu kafedra bo'yicha</label>
                                    <select id="asgTeacher" size="8" class="w-full rounded-md border-slate-300 text-sm flex-1" disabled></select>
                                    <div class="flex gap-2 pt-1">
                                        <button type="button" id="asgApply" class="asc-btn primary flex-1" disabled>Biriktirish</button>
                                        <button type="button" id="asgClear" class="asc-btn" disabled title="Biriktirishni olib tashlash"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                    </div>
                                    {{-- Xona: dars birligining barcha kartalariga (ro'yxat alohida oynada) --}}
                                    <div class="pt-3 mt-1 border-t border-slate-200 space-y-2">
                                        <div class="text-xs font-semibold text-slate-600">Xona (auditoriya)</div>
                                        <div id="asgRoomCurrent" class="text-sm text-slate-500">—</div>
                                        <div class="flex gap-2">
                                            <button type="button" id="asgRoomOpen" class="asc-btn primary flex-1" disabled><i class="bi bi-door-open" aria-hidden="true"></i> Xonani biriktirish</button>
                                            <button type="button" id="asgRoomClear" class="asc-btn" disabled title="Xonani olib tashlash"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="asgAuditoriumsPanel" class="{{ $timetableAuditoriumAssignmentOnly ? '' : 'hidden' }} flex-1 gap-3 p-4 overflow-hidden" data-asg-panel="auditoriums">
                            <div class="assign-pane flex-1 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="assign-toolbar flex flex-wrap items-center gap-2 px-3 py-3 border-b border-slate-200 bg-slate-50">
                                    <span class="text-sm font-semibold text-slate-700">Auditoriyalar</span>
                                    
                                    <input id="asgAudSearch" placeholder="Xona qidirish..." class="ml-auto w-52 rounded-md border-slate-300 text-xs py-1.5">
                                    <span id="asgAudCount" class="text-xs text-slate-400"></span>
                                </div>
                                
                                <div class="overflow-auto asc-table-scroll" style="max-height: none; flex: 1 1 auto;" data-drag-scroll>
                                    <table id="asgAudTable" class="w-full text-xs asc-table"></table>
                                </div>
                            </div>
                            <div class="assign-teacher-pane w-64 shrink-0 flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="assign-pane-title px-4 py-3 border-b border-slate-200 bg-slate-50 text-sm font-semibold text-slate-700">Auditoriya uchun o'qituvchi</div>
                                <div class="p-4 space-y-3 flex-1 flex flex-col overflow-hidden">
                                    <div id="asgAudInfo" class="assign-unit-info text-xs text-slate-500 min-h-[42px] rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2">Chapdan auditoriyani tanlang</div>
                                    <select id="asgAudDepartment" class="w-full rounded-md border-slate-300 text-xs" disabled><option value="">— barcha kafedralar —</option></select>
                                    <input id="asgAudTeacherSearch" placeholder="O'qituvchi qidirish..." class="w-full rounded-md border-slate-300 text-sm" disabled>
                                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" id="asgAudGeneral" class="rounded border-slate-300" disabled> Umumiy xona</label>
                                    <select id="asgAudTeacher" size="10" class="w-full rounded-md border-slate-300 text-sm flex-1" disabled></select>
                                    <div class="flex gap-2 pt-1">
                                        <button type="button" id="asgAudApply" class="asc-btn primary flex-1" disabled>Saqlash</button>
                                        <button type="button" id="asgAudClear" class="asc-btn" disabled>Umumiy qilish</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                            <div id="asgMsg" class="text-sm text-slate-500"></div>
                            <button type="button" id="asgCloseBtn" class="asc-btn px-5 py-2">Yopish</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Tekshiruv (konflikt / oyna) hisoboti ═══ --}}
            <div id="checkModal" class="hidden tt-modal">
                <div class="tt-modal-body">
                    <div class="asc-win tt-modal-win bg-[#f0f0f0] rounded shadow-2xl w-full max-w-3xl flex flex-col" style="max-height: 90vh;">
                        <div class="asc-titlebar asc-modal-header flex items-center justify-between px-5 py-3 rounded-t">
                            <div class="asc-header-main flex items-center gap-3 text-base font-semibold text-white">
                                <span class="asc-header-icon" aria-hidden="true">
                                    <i class="bi bi-search"></i>
                                </span>
                                <span id="chkTitle">Jadval tekshiruvi</span>
                            </div>
                            <button type="button" id="chkClose" class="asc-close-btn" aria-label="Yopish" title="Yopish">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="chkBody" class="check-report-body overflow-auto" style="max-height: 76vh;"></div>
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
        /* Joylashgan kartani panelga qaytarish uchun tashlash nishoni */
        #cardPanel.pn-drop { outline: 3px dashed #16a34a; outline-offset: -3px; background: #f0fdf4; }
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
        #grid .tt-grp-count { margin-top: 2px; line-height: 1; }
        .tt-stu-count { display: inline-block; min-width: 18px; padding: 0 5px; border-radius: 9px; background: #059669;
            color: #fff; font-size: 10px; font-weight: 800; font-style: normal; line-height: 16px; text-align: center; }
        #grid td.tt-para.tt-para-roman { min-width: 28px; width: 28px; max-width: 28px; padding: 1px; background: #e8eef6; line-height: 1; }
        #grid td.tt-para.tt-para-roman b { display: block; color: #1e293b; font-size: 12px; font-weight: 900; }
        #grid td.tt-para.tt-para-roman i { display: block; font-style: normal; color: #64748b; font-size: 7px; font-weight: 700; margin-top: 2px; }
        #grid td.tt-ptime { background: #f8fafc; color: #475569; font-size: 8.5px; font-weight: 700; line-height: 1.15; text-align: center;
            white-space: nowrap; position: sticky; left: 54px; z-index: 4; min-width: 40px; width: 40px; padding: 1px 2px; }
        #grid td.tt-para .tt-para-name { font-size: 11px; font-weight: 900; color: #1e293b; line-height: 1.1; white-space: nowrap; }
        #grid td.tt-para .tt-para-time { font-size: 8px; font-weight: 700; color: #64748b; line-height: 1.1; margin-top: 1px; }
        #grid thead th { position: sticky; top: 0; z-index: 5; }
        #grid th.tt-fac { background: #c7d2fe; color: #1e1b4b; font-weight: 900; text-align: center; text-transform: uppercase; font-size: 11px; letter-spacing: .2px; }
        #grid th.tt-oqim { background: #e0e7ff; color: #3730a3; font-weight: 900; text-align: center; font-size: 11px; }
        #grid th.tt-oqim .tt-oqim-count { color: #16a34a; font-size: 10px; font-weight: 800; margin-top: 2px; }
        #grid .tt-course-1 { background-color: #eef6ff; }
        #grid .tt-course-2 { background-color: #f0fdf4; }
        #grid .tt-course-3 { background-color: #fff7ed; }
        #grid .tt-course-4 { background-color: #f5f3ff; }
        #grid .tt-course-5 { background-color: #ecfeff; }
        #grid .tt-course-6 { background-color: #fdf2f8; }
        #grid th.tt-course-1 { background-color: #dbeafe; }
        #grid th.tt-course-2 { background-color: #dcfce7; }
        #grid th.tt-course-3 { background-color: #ffedd5; }
        #grid th.tt-course-4 { background-color: #ede9fe; }
        #grid th.tt-course-5 { background-color: #cffafe; }
        #grid th.tt-course-6 { background-color: #fce7f3; }
        #grid th[class*="tt-course-"] {
            box-shadow: inset 0 3px 0 rgba(37,99,235,.28), inset 0 -1px 0 rgba(15,23,42,.08);
        }
        /* Guruh sarlavhasi — o'ralib chiqadi (ustunni kengaytirmaydi), ustun ingichka bo'ladi */
        #grid th.tt-grp { background: #eef1f5; color: #1e293b; font-weight: 800; white-space: normal; word-break: break-word;
            text-align: center; font-size: 9px; line-height: 1.05; width: 52px; min-width: 52px; max-width: 52px; padding: 2px 1px; }
        #grid th.tt-grp.tt-course-1 { background-color: #dbeafe; }
        #grid th.tt-grp.tt-course-2 { background-color: #dcfce7; }
        #grid th.tt-grp.tt-course-3 { background-color: #ffedd5; }
        #grid th.tt-grp.tt-course-4 { background-color: #ede9fe; }
        #grid th.tt-grp.tt-course-5 { background-color: #cffafe; }
        #grid th.tt-grp.tt-course-6 { background-color: #fce7f3; }
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
        /* Bitta oddiy katakda ketma-ket turgan fanlar qora chiziq bilan ajralsin. */
        #grid td.tt-cell:not(.tt-split) > .tt-chip { margin-top: 0; margin-bottom: 0; }
        #grid td.tt-cell:not(.tt-split) > .tt-chip + .tt-chip { border-top: 1px solid #000; border-radius: 0; }
        /* Auditoriya/o'qituvchi/fan kesimida to'liq karta ma'lumoti o'qiladigan bo'lsin. */
        #grid.tt-cross-details td.tt-cell { width: 118px; min-width: 118px; max-width: 118px; height: auto; padding: 0; }
        #grid.tt-cross-details th.tt-grp { width: 118px; min-width: 118px; max-width: 118px; }
        #grid.tt-cross-details .tt-chip { padding: 4px 5px; font-size: 9px; line-height: 1.2; white-space: normal; overflow-wrap: anywhere; }
        #grid.tt-cross-details .tt-cross-fac { font-size: 8px; font-weight: 700; color: #334155; }
        #grid.tt-cross-details .tt-cross-dir { font-size: 8px; color: #475569; }
        #grid.tt-cross-details .tt-cross-subject { margin-top: 2px; font-weight: 800; color: #713f12; }
        #grid.tt-cross-details .tt-cross-flow { font-size: 8px; font-weight: 700; color: #334155; }
        #grid.tt-cross-details .tt-cross-weeks { font-size: 8px; font-weight: 700; color: #1d4ed8; }
        /* Ma'ruza — butun katak bitta sariq (chip'ning alohida foni yo'q); amaliy — fan rangi (inline) */
        .tt-chip.lec { background: transparent; border-left: none; color: #713f12; font-weight: 700; }
        .tt-chip.prc { border-left: 3px dotted #94a3b8; color: #1f2937; font-weight: 500; }
        #grid td.tt-lec { background: #fde68a; }   /* butun oqimga tegishli ma'ruza katagi — bir xil sariq */
        .tt-chip.sel { outline: 2px solid #ef4444; }
        .tt-merge-badge { display: inline-block; margin-left: 4px; padding: 0 4px; font-size: 8px; font-weight: 700;
            background: rgba(0,0,0,.12); border-radius: 6px; color: #334155; vertical-align: middle; }
        .tt-weeks { font-size: 9px; font-weight: 700; color: #1d4ed8; white-space: nowrap; }
        /* Almashinuvchi karta (har hafta emas) — shablon ko'rinishida ajralib tursin:
           yengil yo'l-yo'l fon + chapda ⇄ belgisi. Aniq hafta tanlanganda ko'rinmaydi. */
        #grid .tt-chip.tt-alt {
            background-image: repeating-linear-gradient(135deg,
                rgba(255,255,255,.55) 0 5px, rgba(0,0,0,0) 5px 10px);
            border-left-width: 3px; border-left-style: dashed;
        }
        #grid .tt-alt-mark {
            display: inline-block; margin-right: 3px; font-size: 10px; font-weight: 900;
            color: #b45309; vertical-align: 1px;
        }
        /* ── Bitta katakda bir necha fan almashib keladi (hafta bo'yicha) ──
           Katak chapdan o'ngga, pastdan tepaga diagonal bilan IKKI UCHBURCHAKKA
           bo'linadi. Har uchburchak to'lig'icha o'z fanining rangida bo'ladi —
           kartochka ramkasi yo'q, ichida faqat fan nomi turadi. */
        #grid td.tt-cell.tt-split { position: relative; padding: 0; overflow: hidden; }
        #grid td.tt-bolak-cell { padding: 2px 3px; vertical-align: top; position: relative; }
        #grid td.tt-bolak-cell > .tt-bolak-head, #grid td.tt-bolak-cell > .tt-bolak-list { position: relative; z-index: 1; }
        .tt-bolak-drops { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
        .tt-bolak-drop { position: absolute; left: 0; right: 0; border-top: 1px dashed rgba(100,116,139,.35); }
        .tt-bolak-drop:first-child { border-top: 0; }
        .tt-bolak-drop i { position: absolute; right: 2px; bottom: 1px; font-size: 8px; font-style: normal; color: #64748b; opacity: 0; }
        #grid.tt-dragging .tt-bolak-drops { pointer-events: auto; z-index: 2; }
        #grid.tt-dragging .tt-bolak-drop { background: rgba(255,255,255,.35); }
        #grid.tt-dragging .tt-bolak-drop i { opacity: 1; font-weight: 700; }
        .tt-bolak-drop.drag-ok { background: rgba(34,197,94,.35) !important; outline: 2px solid #16a34a; }
        .tt-bolak-drop.drag-bad { background: rgba(239,68,68,.3) !important; outline: 2px solid #dc2626; }
        .tt-bolak-time { font-size: 8.5px; color: #334155; background: rgba(255,255,255,.7); border-radius: 3px; padding: 0 3px; white-space: nowrap; }
        .tt-bolak-item.tt-bolak-late { border-left: 3px solid #f59e0b; }
        .tt-bolak-item.tt-bolak-late .tt-bolak-time { color: #b45309; font-weight: 700; }
        .tt-bolak-head { font-size: 10px; font-weight: 700; color: #1e293b; line-height: 1.2; cursor: grab;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 1px dashed rgba(15,23,42,.18); padding-bottom: 2px; margin-bottom: 2px; }
        .tt-bolak-list { display: flex; flex-direction: column; gap: 2px; }
        .tt-bolak-item { display: flex; align-items: center; gap: 4px; font-size: 9.5px; line-height: 1.25; padding: 1px 4px; border-radius: 4px;
            background: rgba(255,255,255,.65); border: 1px solid rgba(15,23,42,.10); cursor: grab; white-space: nowrap; overflow: hidden; }
        .tt-bolak-item:hover { background: #fff; border-color: rgba(37,99,235,.45); }
        .tt-bolak-item.sel { outline: 2px solid #f59e0b; }
        .tt-bolak-item .tt-bolak-tag { display: inline-flex; align-items: center; justify-content: center; min-width: 14px; height: 14px; border-radius: 7px;
            background: #7c2d12; color: #fff; font-size: 9px; }
        .tt-bolak-n { margin-left: auto; color: #475569; font-weight: 700; }
        .tt-bolak-t { color: #64748b; max-width: 60px; overflow: hidden; text-overflow: ellipsis; }
        #grid td.tt-split .tt-chip {
            position: absolute; inset: 0; width: auto; margin: 0; padding: 3px 5px;
            border: 0; border-radius: 0; box-shadow: none; overflow: hidden;
            display: flex; font-size: 10px; line-height: 1.15;
        }
        /* Yuqori-chapdagi uchburchak */
        #grid td.tt-split .tt-chip.tt-tri-a {
            clip-path: polygon(0 0, 100% 0, 0 100%);
            align-items: flex-start; justify-content: flex-start; text-align: left;
        }
        /* Quyi-o'ngdagi uchburchak */
        #grid td.tt-split .tt-chip.tt-tri-b {
            clip-path: polygon(100% 0, 100% 100%, 0 100%);
            align-items: flex-end; justify-content: flex-end; text-align: right;
        }
        /* Nom va haftalar uchburchakdan chiqib ketmasin */
        #grid td.tt-split .tt-tri-name { max-width: 62%; }
        #grid td.tt-split .tt-tri-name .tt-weeks { display: block; font-size: 8px; }
        #grid td.tt-split .tt-tri-name .tt-room { font-size: 9px; }
        /* Bo'lakli fan harfi — "(a)", "(c)" */
        .tt-bolak-tag { color: #7c2d12; font-weight: 800; white-space: nowrap; }
        /* Ikki rang tutashgan joydagi nozik ajratgich */
        #grid td.tt-cell.tt-split::after {
            content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 2;
            background: linear-gradient(to bottom right,
                rgba(0,0,0,0) calc(50% - 1px), rgba(0,0,0,.9) 50%, rgba(0,0,0,0) calc(50% + 1px));
        }
        /* Diagonal chizilgan katakda yo'l-yo'l fon kerak emas — diagonalning
           o'zi "har hafta emas" ekanini bildiradi. */
        #grid td.tt-split .tt-chip.tt-alt { background-image: none; }
        #grid td.tt-split .tt-chip.sel { outline: none; box-shadow: inset 0 0 0 2px #f59e0b; }
        /* Ikki ma'ruza bir xil sariq bo'lsa ajralib tursin */
        #grid .tt-chip.tt-shade-a { filter: brightness(1.07) saturate(.95); }
        #grid .tt-chip.tt-shade-b { filter: brightness(.90) saturate(1.1); }
        .tt-room { font-size: 10px; font-weight: 700; color: #b45309; white-space: nowrap; }
        .tt-room-vol { font-weight: 800; color: #16a34a; opacity: 1; }\n        #grid th.tt-room-head .tt-room-name { display:block; font-size:10px; font-weight:500; color:#64748b; margin-top:2px; }
        .pn-card { display: inline-block; width: 170px; vertical-align: top; border-radius: 6px; padding: 4px 6px;
            font-size: 11px; cursor: pointer; border: 1px solid #e2e8f0; }
        .pn-card img { width: 12px; height: 12px; display: inline-block; vertical-align: -2px; }
        .pn-card .bi { font-size: 10px; }
        .pn-card.lec { background: #fefce8; border-color: #fde68a; }
        .pn-card.prc { background: #faf5ff; }
        .pn-card.sel { outline: 2px solid #f59e0b; }
        /* Shu haftada o'tilmaydigan karta — odatda yashirin, "ko'rsatish" bosilganda so'nik chiqadi */
        .pn-card.skip { opacity: .5; border-style: dashed; filter: grayscale(.5); }
        .lang-rus { box-shadow: inset 0 0 0 1px #fca5a5; }
        .lang-ing { box-shadow: inset 0 0 0 1px #86efac; }

        /* ── aSc uslubidagi toolbar va dialoglar ── */
        .asc-tool { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 13px;
            background: linear-gradient(#fff,#eef1f5); border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; }
        .asc-tool:hover { background: linear-gradient(#fff,#e2e8f0); border-color: #94a3b8; }
        .asc-tool .asc-ic { font-size: 16px; }
        .asc-titlebar { background: linear-gradient(#3b6fb5,#2c5896); }
        /* ── Ko'p tanlovli dropdown (Fakultet / Yo'nalish / Kurs) — inline CSS ── */
        .tt-dd { position: relative; display: inline-block; }
        .tt-dd-btn { display: inline-flex; align-items: center; gap: 6px; max-width: 260px; padding: 6px 10px;
            font-size: 12px; line-height: 1.1; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px;
            color: #334155; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tt-dd-btn:hover { border-color: #94a3b8; }
        .tt-dd-btn .tt-dd-ttl { color: #64748b; font-weight: 600; }
        .tt-dd-btn .tt-dd-caret { margin-left: auto; color: #94a3b8; font-size: 10px; }
        .tt-dd-menu { display: none; position: absolute; z-index: 70; top: calc(100% + 4px); left: 0; min-width: 180px;
            max-width: 320px; max-height: 320px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1;
            border-radius: 8px; box-shadow: 0 10px 24px rgba(15,23,42,.18); padding: 4px; }
        .tt-dd-menu.open { display: block; }
        .tt-dd-item { display: flex; align-items: center; gap: 8px; padding: 5px 8px; font-size: 12px; color: #334155;
            border-radius: 5px; cursor: pointer; white-space: nowrap; }
        .tt-dd-item:hover { background: #eff6ff; }
        .tt-dd-item input { flex: none; }
        .tt-dd-tools { display: flex; gap: 6px; padding: 4px 6px 6px; border-bottom: 1px solid #eef1f5; margin-bottom: 4px; }
        .tt-dd-tools button { font-size: 11px; color: #2563eb; background: none; border: none; cursor: pointer; padding: 2px 4px; }
        .tt-dd-tools button:hover { text-decoration: underline; }
        .tt-dd-empty { padding: 6px 8px; font-size: 12px; color: #94a3b8; }
        .tt-dd-up .tt-dd-menu { top: auto; bottom: calc(100% + 4px); }
        .pn-dd-btn { padding: 4px 8px; font-size: 11px; max-width: 200px; color: #4b5563; }
        /* Joylashmagan kartalar filtrlari — bir xil o'lcham */
        #pnFilters .pn-f, #pnFilters .pn-dd-btn {
            height: 28px; width: 180px; max-width: 180px; box-sizing: border-box;
            font-size: 11px; line-height: 1; color: #4b5563; background-color: #fff;
            border: 1px solid #d1d5db; border-radius: 6px;
        }
        #pnFilters select.pn-f { padding: 0 26px 0 8px; }
        #pnFilters .pn-dd-btn { display: inline-flex; align-items: center; justify-content: space-between; gap: 6px; padding: 0 8px; }
        #pnFilters .pn-dd-btn .tt-dd-sum { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* ── Sikl (4-6 kurs) kalendar ko'rinishi ── */
        /* Sana qatori doim ko'rinib tursin: vertikal scroll wrap ichida
           bo'ladi (sticky top overflow konteynerga nisbatan ishlaydi). */
        /* Sikl maydoni: jadval o'z oynasida suriladi — sanalar (sarlavha) tepada, guruh/para
           ustunlari chapda qotib turadi. Avval tashqi #cycleArea surilardi va sarlavha ketib qolardi. */
        #cycleArea:not(.hidden) { display: flex; flex-direction: column; overflow: hidden; }
        #cycleArea > :not(#cycleGridWrap) { flex: none; }
        #cycleGridWrap { flex: 1 1 auto; min-height: 0; max-height: none; }
        #cycleGrid thead th { position: sticky; top: 0; z-index: 5; }
        #cycleGrid thead .cyc-gcol { z-index: 7; }
        #cycleGrid { --cycle-group-width: 170px; border-collapse: collapse; table-layout: fixed; width: max-content; min-width: 100%; }
        #cycleGrid th, #cycleGrid td { border: 1px solid #d1d5db; }
        #cycleGrid .cyc-gcol { position: sticky; left: 0; z-index: 4; box-sizing: border-box; overflow: hidden;
            width: var(--cycle-group-width); min-width: var(--cycle-group-width); max-width: var(--cycle-group-width);
            padding: 2px 6px; background: #eff6ff; text-align: left; font-size: 10px; line-height: 1.15; }
        #cycleGrid thead .cyc-gcol { background: #dbeafe; }
        #cycleGrid .cyc-members { display: grid; gap: 2px; padding: 1px 0; }
        #cycleGrid .cyc-flow-head { display: flex; align-items: center; justify-content: space-between; gap: 4px;
            overflow: hidden; padding: 3px 6px; border: 1px solid #1d4ed8; border-radius: 3px;
            background: #1d4ed8; color: #fff; font-size: 10px; font-weight: 700;
            text-overflow: ellipsis; white-space: nowrap; }
        #cycleGrid .cyc-flow-head span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #cycleGrid .cyc-flow-head i { flex: none; font-style: normal; font-weight: 500; font-size: 9px; opacity: .85; }
        #cycleGrid .cyc-member-row { display: grid; grid-template-columns: minmax(0, 1fr) 48px; align-items: stretch;
            overflow: hidden; border: 1px solid #93c5fd; border-radius: 3px; background: #eff6ff; }
        #cycleGrid .cyc-member-row span { overflow: hidden; padding: 4px 6px; color: #1e3a8a; font-size: 10px;
            font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        #cycleGrid .cyc-member-single { grid-template-columns: minmax(0, 1fr); }
        #cycleGrid .cyc-stu-count { display: inline-block; min-width: 18px; padding: 0 5px; border-radius: 9px; background: #059669;
            color: #fff; font-size: 10px; font-weight: 800; font-style: normal; line-height: 16px; text-align: center; vertical-align: middle; }
        #cycleGrid .cyc-stu-line { display: flex; align-items: center; gap: 4px; padding: 1px 2px; color: #065f46; font-size: 10px; font-weight: 700; }
        #cycleGrid .cyc-member-row b { display: flex; align-items: center; box-sizing: border-box; width: 48px; min-width: 48px; padding: 4px 6px;
            border-left: 1px solid #60a5fa; background: #0ea5e9; color: #fff; font-size: 10px; justify-content: center; }
        #cycleGrid .cyc-dcol { width: 30px; min-width: 30px; max-width: 30px; font-size: 9.5px; writing-mode: vertical-rl; text-orientation: mixed;
            padding: 3px 0; background: #dbeafe; white-space: nowrap; color: #334155; }
        #cycleGrid .cyc-cell { width: 30px; min-width: 30px; max-width: 30px; height: 28px;
            border: 1px solid #e2e8f0; }
        /* Bo'sh kataklar colspan bilan birlashtirilgan (drag tezligi uchun) —
           kun chegaralari katak ichida gradient bilan chiziladi. Ustun eni
           qat'iy 30px bo'lgani uchun chiziq aniq joyiga tushadi. */
        #cycleGrid td.cyc-cell:not(.cyc-block) {
            background-image: repeating-linear-gradient(
                to right,
                transparent 0 29px,
                #e2e8f0 29px 30px
            );
            background-position: -1px 0;
        }
        /* Dam olish kunlari: katak ichidagi ustunlar ranglanadi */
        #cycleGrid td.cyc-cell.has-off { background-repeat: no-repeat, repeat; }
        /* ── Kafedra mudiri: blok ichida ma'ruza soatlarini belgilash ── */
        #cycleGrid tr.cyc-markrow td { height: calc(var(--mk-rows, 6) * 22px + 12px); vertical-align: top; }
        /* Fan nomi to'r ichida, paralar ustida (kataklarni bosishga xalaqit bermaydi) */
        #cycleGrid .cyc-mk-grid { position: relative; }
        /* Fan nomi sukutda M/A darslar egallagan maydon markazida (--mk-x/--mk-y);
           ushlab blok ichida siljitiladi, ikki marta bosilsa markazga qaytadi. */
        #cycleGrid .cyc-mk-over { position: absolute; left: var(--mk-x, 50%); top: var(--mk-y, 50%); transform: translate(-50%, -50%); z-index: 2;
            width: max-content; max-width: calc(100% - 8px);
            display: flex; flex-direction: column; align-items: center; gap: 2px; pointer-events: none; text-align: center; }
        #cycleGrid .cyc-mk-over .cyc-mk-title { touch-action: none; }
        #cycleGrid .cyc-mk-over.is-dragging .cyc-mk-title { cursor: grabbing; box-shadow: 0 0 0 2px rgba(37, 99, 235, .7), 0 4px 10px rgba(15, 23, 42, .25); }
        #cycleGrid .cyc-mk-over .cyc-mk-title { max-width: 100%; padding: 2px 7px; border-radius: 6px; background: rgba(255, 255, 255, .6);
            box-shadow: 0 1px 3px rgba(15, 23, 42, .14); pointer-events: auto; cursor: pointer; font-size: 12px; font-weight: 800; line-height: 1.2; color: #0f172a;
            white-space: normal; overflow-wrap: anywhere;
            display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
        /* Tor blok (≤ 6 kun): faqat fan nomi va soat — o'qituvchi/xona sichqoncha ustiga borganda */
        #cycleGrid .cyc-mk-over.is-compact { gap: 1px; max-width: calc(100% - 4px); }
        #cycleGrid .cyc-mk-over.is-compact .cyc-mk-title { padding: 1px 4px; font-size: 10.5px; }
        /* O'qituvchi biriktirilmagan fan nomi — kulrang, qizil uzuq hoshiya, belgili */
        #cycleGrid .cyc-mk-over .cyc-mk-title.is-noteacher { background: rgba(229, 231, 235, .92); border: 1.5px dashed #dc2626; color: #475569; }
        #cycleGrid .cyc-mk-over .cyc-mk-title.is-noteacher i { color: #dc2626; font-size: 1.05em; }
        #cycleGrid .cyc-mk-over .cyc-mk-title:hover { box-shadow: 0 0 0 2px rgba(37, 99, 235, .55); }
        #cycleGrid .cyc-mk-over .cyc-mk-sub { max-width: 100%; padding: 1px 6px; border-radius: 5px; background: rgba(255, 255, 255, .6);
            font-size: 10px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #cycleGrid .cyc-mk-gear { position: absolute; top: 2px; right: 2px; z-index: 3; }
        #cycleGrid .cyc-block.is-mark { padding: 0; overflow: visible; }
        #cycleGrid .cyc-mk {
            position: absolute; inset: 0; display: flex; flex-direction: column;
        }
        #cycleGrid .cyc-mk-head {
            flex: none; overflow: hidden; height: 46px; padding: 4px 8px 0;
        }
        #cycleGrid .cyc-mk-line {
            display: flex; align-items: center; gap: 7px;
            height: 21px; overflow: hidden; white-space: nowrap;
        }
        #cycleGrid .cyc-mk-name {
            overflow: hidden; text-overflow: ellipsis;
            font-size: 12px; font-weight: 800; letter-spacing: -.01em;
        }
        #cycleGrid .cyc-mk-head i {
            overflow: hidden; font-style: normal; font-weight: 500;
            font-size: 10.5px; opacity: .75; text-overflow: ellipsis;
        }
        #cycleGrid .cyc-mk-count {
            flex: none; padding: 1px 8px; border-radius: 3px;
            background: rgba(255, 255, 255, .82); color: #3730a3;
            font-size: 11px; font-weight: 800;
        }
        #cycleGrid .cyc-mk-count.is-over { background: #fee2e2; color: #b91c1c; }
        #cycleGrid .cyc-mk-count.is-done { background: #dcfce7; color: #15803d; }
        #cycleGrid .cyc-mk-grid {
            flex: 1 1 auto; display: grid; min-height: 0;
            border-top: 1px solid rgba(15, 39, 72, .22);
            user-select: none;
        }
        #cycleGrid .cyc-mk-cell {
            display: grid; place-items: center; padding: 0; border: 0;
            border-right: 1px solid rgba(15, 39, 72, .14);
            border-bottom: 1px solid rgba(15, 39, 72, .14);
            background: transparent; cursor: pointer; transition: background .1s;
            font-family: inherit; font-size: 10px; font-weight: 800; line-height: 1;
            color: rgba(15, 39, 72, .38);
        }
        #cycleGrid .cyc-mk-cell::after { content: ''; }
        /* Haftalik jadval: Excel tugmasi (sikldagidek yashil) */
        .tt-weekly-excel { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 8px; font-size: 11px;
            background: #1d6f42; border: 1px solid #185c37; color: #fff; font-weight: 600; }
        .tt-weekly-excel:hover:not(:disabled) { background: #185c37; }
        .tt-weekly-excel:disabled { opacity: .7; cursor: wait; }
        /* Sikl paneli: Excel tugmasi yashil — boshqa tugmalardan ajralib tursin */
        #cycleArea .cyc-excel-btn { display: inline-flex; align-items: center; gap: 4px;
            background: #1d6f42; border-color: #185c37; color: #fff; font-weight: 600; }
        #cycleArea .cyc-excel-btn:hover:not(:disabled) { background: #185c37; color: #fff; }
        #cycleArea .cyc-excel-btn i { font-size: 13px; line-height: 1; }
        #cycleArea .cyc-excel-btn:disabled { opacity: .7; cursor: wait; }
        /* Bo'lakli fan qatori (Fan (a)/(b)/(c)) yorlig'i */
        /* Guruhcha qatorida bo'lak sikli ishorasi (dars bo'lak qatorida) */
        /* Bo'sh katak kun-chiziqlari (td.cyc-cell:not(.cyc-block)) ustidan yozilishi uchun !important;
           ishora tugmasi dars soatlari (yuqori qism) ro'parasida turadi. */
        #cycleGrid td.cyc-ghost { padding: 64px 4px 4px; vertical-align: top !important;
            background: repeating-linear-gradient(135deg, rgba(167, 139, 250, .28) 0 8px, rgba(245, 243, 255, .85) 8px 16px) !important;
            border: 2px dashed #8b5cf6; }
        #cycleGrid .cyc-ghost-btn { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; margin: 0 auto; padding: 4px 9px;
            border-radius: 8px; background: #fff; border: 1px solid #c4b5fd; box-shadow: 0 1px 3px rgba(15, 23, 42, .15); cursor: pointer; text-align: left; }
        #cycleGrid .cyc-ghost-btn:hover { box-shadow: 0 0 0 2px rgba(124, 58, 237, .45); }
        #cycleGrid .cyc-ghost-txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
        #cycleGrid .cyc-ghost-txt b { color: #1e293b; font-size: 11px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #cycleGrid .cyc-ghost-txt i { color: #6d28d9; font-size: 10px; font-style: normal; font-weight: 700; white-space: nowrap; }
        #cycleGrid td.cyc-ghost { text-align: center; }
        #cycleGrid .cyc-vrow { display: flex; flex-direction: column; gap: 3px; padding: 5px 6px; border-radius: 6px;
            background: #f5f3ff; border: 1px solid #ddd6fe; border-left: 4px solid #7c3aed; text-align: left; }
        #cycleGrid .cyc-vrow-head { display: flex; align-items: center; gap: 5px; }
        #cycleGrid .cyc-vrow-tag { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%;
            background: #7c3aed; color: #fff; font-size: 12px; font-weight: 800; line-height: 1; }
        #cycleGrid .cyc-vrow-kind { color: #5b21b6; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
        #cycleGrid .cyc-vrow-flow { margin-left: auto; color: #475569; font-size: 10px; font-weight: 700; white-space: nowrap; }
        #cycleGrid .cyc-vrow-subj { color: #1e293b; font-size: 10.5px; font-weight: 700; line-height: 1.2;
            display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
        .tt-week-nav { display: inline-flex; align-items: stretch; height: 30px; border: 1px solid #bfdbfe; border-radius: 9px;
            background: #fff; box-shadow: 0 1px 2px rgba(30,64,175,.08); overflow: hidden; }
        .tt-week-nav:focus-within { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.25); }
        .tt-week-nav button { display: inline-flex; align-items: center; justify-content: center; width: 28px; border: 0; padding: 0;
            background: linear-gradient(180deg, #eff6ff, #dbeafe); color: #1d4ed8; cursor: pointer; transition: background .15s, color .15s; }
        .tt-week-nav button#weekPrev { border-right: 1px solid #bfdbfe; }
        .tt-week-nav button#weekNext { border-left: 1px solid #bfdbfe; }
        .tt-week-nav button:hover:not(:disabled) { background: #2563eb; color: #fff; }
        .tt-week-nav button:active:not(:disabled) { background: #1d4ed8; color: #fff; }
        .tt-week-nav button:disabled { background: #f8fafc; color: #cbd5e1; cursor: default; }
        .tt-week-nav select#weekSel { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; height: 100%;
            background-color: #fff; font-weight: 600; color: #1e3a8a; padding-top: 0; padding-bottom: 0; outline: none;
            flex: 1 1 auto; }   /* tor ekranda maydon 100% bo'lganda bo'sh joy qolmasin — select cho'ziladi */
        .tt-ktr-weeks-btn { margin-left: 6px; padding: 3px 8px; border-radius: 6px; border: 1px solid #a7f3d0; background: #ecfdf5;
            color: #047857; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .tt-ktr-weeks-btn:disabled { opacity: .5; }
        .tt-compact-btn { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
        .tt-req-badge { display: inline-grid; place-items: center; min-width: 16px; height: 16px; padding: 0 4px; margin-left: 3px; border-radius: 8px;
            background: #f59e0b; color: #fff; font-size: 10px; font-weight: 800; }
        .tt-req-badge.hidden { display: none; }
        .tt-pending-chg { outline: 2px dashed #f59e0b !important; outline-offset: -2px; position: relative; }
        .tt-pending-chg::after { content: '\23F3'; position: absolute; right: 2px; top: 1px; font-size: 10px; }
        #cyclePendingBar { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; padding: 5px 12px; font-size: 11px;
            background: #fffbeb; border-bottom: 1px solid #fde68a; color: #78350f; }
        #cyclePendingBar.hidden { display: none; }
        #cyclePendingBar span { padding: 1px 6px; border-radius: 4px; background: #fef3c7; }
        #cyclePendingBar button { margin-left: auto; padding: 2px 10px; border-radius: 5px; border: 1px solid #f59e0b; background: #fff; color: #92400e; font-weight: 700; }
        #cycDayPop .dp-pending { margin: 4px 0; padding: 4px 6px; border-radius: 5px; background: #fef3c7; color: #92400e; font-size: 11px; }
        #ttToastBox { position: fixed; right: 16px; bottom: 16px; z-index: 10050; display: flex; flex-direction: column; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
        .tt-toast { padding: 10px 12px; border-radius: 8px; background: #1e293b; color: #fff; font-size: 12px; line-height: 1.35;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .25); transition: opacity .35s, transform .35s; }
        .tt-toast.warn { background: #92400e; }
        .tt-toast.ok { background: #047857; }
        .tt-toast.out { opacity: 0; transform: translateY(8px); }
        .tt-toast .tt-toast-sub { margin-top: 3px; opacity: .85; font-size: 11px; }
        #crModal { position: fixed; inset: 0; z-index: 10040; display: none; align-items: flex-start; justify-content: center;
            padding: 40px 16px; background: rgba(15, 23, 42, .45); }
        #crModal.open { display: flex; }
        #crModal .cr-box { width: min(1080px, 100%); max-height: calc(100vh - 80px); display: flex; flex-direction: column; overflow: hidden;
            border-radius: 12px; background: #fff; box-shadow: 0 20px 50px rgba(15, 23, 42, .3); }
        #crModal .cr-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        #crModal .cr-x { font-size: 22px; line-height: 1; color: #64748b; }
        #crModal .cr-body { padding: 12px 16px; overflow: auto; font-size: 12px; }
        #crModal .cr-mode { display: flex; align-items: center; gap: 8px; padding: 8px 10px; margin-bottom: 10px; border-radius: 8px; background: #eff6ff; font-weight: 600; color: #1e3a8a; }
        #crModal .cr-mode-info { padding: 8px 10px; margin-bottom: 10px; border-radius: 8px; background: #f1f5f9; color: #334155; }
        #crModal .cr-tabs { display: flex; gap: 6px; margin-bottom: 8px; }
        #crModal .cr-tabs button { padding: 4px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #475569; }
        #crModal .cr-tabs button.on { background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 700; }
        #crModal .cr-table { width: 100%; border-collapse: collapse; }
        #crModal .cr-table th { position: sticky; top: 0; background: #f8fafc; text-align: left; padding: 6px 8px; font-size: 11px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        #crModal .cr-table td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        #crModal .cr-when { white-space: nowrap; color: #64748b; }
        #crModal .cr-who { font-weight: 700; color: #1e293b; }
        #crModal .cr-sub { color: #64748b; font-size: 11px; }
        #crModal .cr-err { margin-top: 3px; color: #b91c1c; }
        #crModal .cr-note { margin-top: 3px; color: #475569; font-style: italic; }
        #crModal .cr-st { display: inline-block; padding: 1px 8px; border-radius: 10px; font-weight: 700; font-size: 11px; white-space: nowrap; }
        #crModal .cr-acts { white-space: nowrap; text-align: right; }
        #crModal .cr-acts button { padding: 3px 10px; margin-left: 4px; border-radius: 6px; font-weight: 700; font-size: 11px; }
        #crModal .cr-ok { background: #059669; color: #fff; }
        #crModal .cr-no { background: #fff; color: #b91c1c; border: 1px solid #fca5a5; }
        #crModal .cr-empty { padding: 18px; text-align: center; color: #94a3b8; }
        #crModal .cr-more { padding: 10px 0 2px; text-align: center; }
        #crModal .cr-more button { padding: 4px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-weight: 600; }
        #cycleGrid .cyc-just-changed { animation: cycJustChanged 2.4s ease-out; }
        @keyframes cycJustChanged { 0%, 35% { box-shadow: inset 0 0 0 2px #f59e0b; background-color: #fef3c7; } 100% { box-shadow: inset 0 0 0 0 transparent; } }
        #cycleGrid .cyc-vrow-stu { margin-top: 2px; font-size: 10px; color: #334155; }
        #cycleGrid .cyc-vrow-stu.is-empty { color: #b91c1c; font-weight: 700; }
        #cycleGrid .cyc-vrow-stu summary { cursor: pointer; font-weight: 800; color: #5b21b6; list-style: revert; }
        #cycleGrid .cyc-vrow-stu summary i { font-style: normal; font-weight: 600; color: #475569; }
        #cycleGrid .cyc-vrow-stu ol { margin: 3px 0 0; padding: 4px 4px 4px 22px; max-height: 150px; overflow-y: auto;
            background: #fff; border: 1px solid #ddd6fe; border-radius: 5px; line-height: 1.35; }
        #cycleGrid .cyc-vrow-stu li { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #cycleGrid .cyc-vrow-stu li b { color: #7c3aed; font-weight: 700; margin-left: 2px; }
        #cycleGrid .cyc-vrow-members { display: flex; flex-wrap: wrap; gap: 3px; }
        #cycleGrid .cyc-vrow-members span { padding: 1px 5px; border-radius: 4px; background: #fff; border: 1px solid #c4b5fd;
            color: #4c1d95; font-size: 9.5px; font-weight: 700; white-space: nowrap; }
        #cycleGrid .cyc-mk-cell { background: rgba(255, 255, 255, .82); }
        #cycleGrid .cyc-mk-cell.is-practice { background: rgba(13, 148, 136, .22); color: #0f766e; }
        #cycleGrid .cyc-mk-cell.is-practice::after { content: 'A'; }
        #cycleGrid .cyc-mk-cell.is-lecture { background: #facc15; color: #713f12; }
        #cycleGrid .cyc-mk-cell.is-lecture::after { content: 'M'; }
        #cycleGrid .cyc-mk-cell:hover { outline: 2px solid rgba(37, 99, 235, .45); outline-offset: -2px; }
        /* Blok ichidagi dam olish/bayram kuni: ustun yuqoridan pastgacha chiziqli — soat emas */
        #cycleGrid .cyc-mk-cell.is-off { cursor: default; color: #64748b; font-size: 8px; font-weight: 700;
            background: repeating-linear-gradient(135deg, rgba(148, 163, 184, .38) 0 4px, rgba(241, 245, 249, .9) 4px 8px); }
        #cycleGrid .cyc-mk-cell.is-off.is-holiday {
            background: repeating-linear-gradient(135deg, rgba(248, 113, 113, .35) 0 4px, rgba(254, 242, 242, .9) 4px 8px); }
        #cycleGrid .cyc-mk-cell.is-off:hover { outline: 0; }
        #cycleGrid .cyc-mk-cell.is-off[data-off-label]::after { content: attr(data-off-label); }
        /* Tanlangan kun ustuni */
        #cycleGrid .cyc-mk-cell.is-day-sel { box-shadow: inset 2px 0 0 #f59e0b, inset -2px 0 0 #f59e0b; }
        #cycleGrid .cyc-mk-cell.is-day-sel.is-day-top { box-shadow: inset 2px 0 0 #f59e0b, inset -2px 0 0 #f59e0b, inset 0 2px 0 #f59e0b; }
        #cycleGrid .cyc-mk-cell.is-day-sel.is-day-bottom { box-shadow: inset 2px 0 0 #f59e0b, inset -2px 0 0 #f59e0b, inset 0 -2px 0 #f59e0b; }
        #cycDayPop { position: fixed; z-index: 60; width: 280px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .18); padding: 10px 12px; font-size: 12px; color: #1e293b; }
        #cycDayPop .dp-head { display: flex; align-items: flex-start; gap: 6px; margin-bottom: 6px; }
        #cycDayPop .dp-title { font-weight: 800; line-height: 1.25; }
        #cycDayPop .dp-sub { color: #64748b; font-size: 11px; font-weight: 600; }
        #cycDayPop .dp-close { margin-left: auto; border: 0; background: transparent; font-size: 16px; line-height: 1; cursor: pointer; color: #64748b; }
        #cycDayPop .dp-row { display: flex; gap: 6px; align-items: baseline; margin: 4px 0; }
        #cycDayPop .dp-tag { flex: none; display: inline-grid; place-items: center; width: 16px; height: 16px; border-radius: 4px;
            font-size: 10px; font-weight: 800; color: #fff; }
        #cycDayPop .dp-tag.l { background: #eab308; color: #422006; }
        #cycDayPop .dp-tag.p { background: #0d9488; }
        #cycDayPop .dp-topic { color: #475569; font-size: 11px; }
        #cycDayPop .dp-note { margin-top: 6px; color: #b45309; font-size: 11px; }
        #cycDayPop .dp-actions { display: flex; gap: 6px; margin-top: 8px; align-items: center; }
        #cycDayPop .dp-actions button { border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; padding: 3px 9px;
            font-weight: 800; cursor: pointer; }
        #cycDayPop .dp-actions button:disabled { opacity: .4; cursor: not-allowed; }
        /* Har 2-soatdan keyin (para chegarasi) to'qroq chiziq */
        #cycleGrid .cyc-mk-cell.is-pair-end { border-bottom-color: rgba(15, 39, 72, .36); }

        /* Yonbosh yorliqlar: karta ichidagi soat qatorlari bilan bir tekis */
        #cycleGrid td.cyc-mk-side {
            width: 84px; min-width: 84px; max-width: 84px; padding: 0;
            background: #f1f5f9; border: 1px solid #e2e8f0;
        }
        /* O'ngga surilganda guruh va para (vaqt) ustunlari joyida qotib turadi */
        #cycleGrid td.cyc-mk-side, #cycleGrid th.cyc-mk-side {
            position: sticky; left: var(--cycle-group-width); z-index: 4; box-sizing: border-box;
        }
        #cycleGrid thead th.cyc-mk-side { z-index: 7; background: #f1f5f9; }
        #cycleGrid .cyc-mk-side-box { display: flex; flex-direction: column; height: 100%; }
        #cycleGrid .cyc-mk-side-gap { display: none; }
        #cycleGrid .cyc-mk-side-rows { flex: 1 1 auto; display: grid; min-height: 0; grid-template-columns: 26px 1fr; }
        /* Para nomi ikkala yarim para qatorini birga qamraydi (chapda), vaqtlar — o'ngda har qatorda */
        #cycleGrid .cyc-mk-side-para { display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-right: 1px solid rgba(15, 39, 72, .14); border-bottom: 1px solid rgba(15, 39, 72, .32); background: #e8eef6; line-height: 1; }
        #cycleGrid .cyc-mk-side-para b { color: #1e293b; font-size: 12px; font-weight: 900; }
        #cycleGrid .cyc-mk-side-para i { font-style: normal; color: #64748b; font-size: 7px; font-weight: 700; margin-top: 2px; }
        #cycleGrid .cyc-mk-side-row {
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            border-bottom: 1px solid rgba(15, 39, 72, .12); line-height: 1.15;
        }
        #cycleGrid .cyc-mk-side-row.is-pair-end { border-bottom-color: rgba(15, 39, 72, .32); }
        #cycleGrid .cyc-mk-side-row b { color: #1e293b; font-size: 9.5px; font-weight: 800; white-space: nowrap; }
        #cycleGrid .cyc-mk-side-row span { color: #475569; font-size: 8.5px; font-weight: 700; white-space: nowrap; }
        #cycleGrid .cyc-addrow td { height: 18px; }
        #cycleGrid .cyc-addcell { background: #f8fafc; }
        #cycleGrid .cyc-addpair { width: 100%; height: 16px; padding: 0; border: 0; background: transparent;
            color: #2563eb; font-size: 13px; font-weight: 800; line-height: 1; cursor: pointer; }
        #cycleGrid .cyc-addpair:hover { color: #1d4ed8; background: #dbeafe; }
        #cycleGrid .cyc-group-col { width: var(--cycle-group-width); min-width: var(--cycle-group-width); }
        #cycleGrid .cyc-date-col { width: 30px; }
        /* "Dam olish kunlarini yashirish": ustun indekslari o'zgarmaydi, faqat ko'rinmaydi */
        #cycleGrid col.cyc-col-hidden { visibility: collapse; }
        #cycleGrid th.cyc-col-hidden { padding: 0; border: 0; font-size: 0; }
        #cycleGrid .cyc-wend, #cycleGrid .cyc-off { background: #eef2f7; }
        #cycleGrid .cyc-block { text-align: center; font-size: 11.5px; line-height: 1.25; overflow: hidden; white-space: normal;
            overflow-wrap: anywhere; vertical-align: middle; color: #1e293b; }
        #cycleGrid .cyc-block { position: relative; }
        #cycleGrid .cyc-shift-actions { position: absolute; top: 2px; left: 3px; right: 3px; z-index: 3; display: flex;
            justify-content: space-between; opacity: 0; pointer-events: none; transition: opacity .12s; }
        #cycleGrid .cyc-block:hover .cyc-shift-actions, #cycleGrid .cyc-block:focus-within .cyc-shift-actions { opacity: 1; pointer-events: auto; }
        #cycleGrid .cyc-shift-btn { width: 19px; height: 19px; padding: 0; border: 1px solid rgba(30,64,175,.35); border-radius: 5px;
            background: rgba(255,255,255,.94); color: #1d4ed8; font-size: 15px; font-weight: 800; line-height: 16px; cursor: pointer; }
        #cycleGrid .cyc-shift-btn:hover { background: #dbeafe; border-color: #2563eb; }
        #cycleGrid .cyc-weekend { background: #fdba74; color: #7c2d12; }
        #cycleGrid .cyc-holiday { background: #fca5a5 !important; color: #7f1d1d; }

        /* Kun ustunlari: bo'sh kataklar colspan bilan birlashtirilgani uchun
           chiziq va dam olish rangi jadval fonida chiziladi — DOM o'smaydi.
           --cyc-col: ustun eni, --cyc-x0: birinchi kun ustuni boshlanishi. */
        #cycleGridWrap { position: relative; }
        #cycleGrid tbody { position: relative; }
        /* Kun chiziqlari va dam olish ranglari jadval ustidagi qatlamda
           chiziladi: bo'sh kataklar colspan bilan birlashtirilgani uchun
           ularga alohida chegara berib bo'lmaydi. Qatlam sichqonchani
           o'tkazib yuboradi, shuning uchun drag & drop ishlayveradi. */
        #cycleGridWrap { position: relative; }
        #cycleOverlay {
            position: absolute; z-index: 3; top: 0; left: 0;
            pointer-events: none; overflow: hidden;
        }
        #cycleOverlay .cyc-off-band { position: absolute; top: 0; bottom: 0; }
        #cycleOverlay .cyc-off-band.is-sunday { background: rgba(251, 146, 60, .22); }
        #cycleOverlay .cyc-off-band.is-holiday { background: rgba(248, 113, 113, .26); }
        /* Sudrash nishoni katak ichida chiziladi: birlashtirilgan katakda
           ham aynan sichqoncha turgan kun ustuni yoritiladi. Koordinata
           katakka nisbatan bo'lgani uchun skroll ta'sir qilmaydi. */
        #cycleGrid td.cyc-cell { position: relative; }
        #cycleGrid .cyc-drop-mark {
            position: absolute; top: 0; bottom: 0; z-index: 4; border-radius: 3px;
            pointer-events: none;
            box-shadow: inset 0 0 0 2px #0284c7; background: rgba(14, 165, 233, .22);
        }
        #cycleGrid .cyc-drop-mark.is-bad {
            box-shadow: inset 0 0 0 2px #dc2626; background: rgba(239, 68, 68, .24);
        }
        #cycleGrid tbody td { position: relative; }
        #cycleGrid .cyc-lbl { display: inline-block; max-width: 100%; padding: 2px 4px; font-weight: 500; }
        #cycleGrid .cyc-subj { font-weight: 800; }
        #cycleGrid .cyc-days { font-weight: 600; color: #334155; white-space: nowrap; }
        #cycleGrid .cyc-pcol { width: 52px; min-width: 52px; padding: 1px 3px; background: #f1f5f9; color: #64748b;
            font-size: 9px; font-weight: 700; text-align: center; border: 1px solid #e2e8f0; }
        #cycleGrid .cyc-pname { font-size: 9.5px; font-weight: 900; color: #1e293b; line-height: 1.1; white-space: nowrap; }
        #cycleGrid .cyc-ptime { font-size: 8px; font-weight: 700; color: #64748b; line-height: 1.1; margin-top: 1px; }
        #cycleGrid .cyc-type { display: inline-grid; place-items: center; width: 14px; height: 14px; margin-right: 3px;
            border-radius: 4px; font-size: 8px; font-weight: 800; color: #fff; vertical-align: middle; }
        #cycleGrid .cyc-type.lecture { background: #4f46e5; }
        #cycleGrid .cyc-type.practice { background: #0d9488; }
        #cycleGrid .cyc-hrs { display: inline-block; margin-left: 3px; padding: 0 4px; border-radius: 4px; font-style: normal;
            font-weight: 800; font-size: 10px; line-height: 15px; white-space: nowrap; color: #b45309; background: rgba(255, 247, 237, .95);
            border: 1px solid rgba(217, 119, 6, .35); }
        .cycle-pn-type { display: inline-block; margin-right: 4px; padding: 1px 5px; border-radius: 4px;
            font-size: 8.5px; font-weight: 800; color: #fff; }
        .cycle-pn-type.lecture { background: #4f46e5; }
        .cycle-pn-type.practice { background: #0d9488; }
        #cycleGrid .cyc-req { display: block; padding: 0 4px 1px; font-size: 9px; font-weight: 500; color: #334155;
            opacity: .9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #cycleGrid .cyc-gear { width: 19px; height: 19px; padding: 0; border: 1px solid rgba(30,64,175,.35); border-radius: 5px;
            background: #fff; color: #1e40af; font-size: 11px; line-height: 1; cursor: pointer; }
        #cycleGrid .cyc-gear:hover { background: #dbeafe; border-color: #2563eb; }
        .cyc-assign-backdrop { position: fixed; inset: 0; z-index: 140; display: none; align-items: center; justify-content: center;
            padding: 16px; background: rgba(15,23,42,.55); }
        .cyc-assign-backdrop.open { display: flex; }
        .cyc-assign-panel { width: min(430px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; border-radius: 12px;
            background: #fff; box-shadow: 0 24px 64px rgba(15,23,42,.35); }
        .cyc-assign-head { padding: 14px 18px; background: #1e3a5f; color: #fff; border-radius: 12px 12px 0 0; }
        .cyc-assign-head b { display: block; font-size: 14px; }
        .cyc-assign-head span { display: block; margin-top: 3px; font-size: 11px; color: rgba(255,255,255,.7); }
        .cyc-assign-body { display: grid; gap: 11px; padding: 15px 18px; }
        .cyc-assign-body label { display: block; margin-bottom: 4px; font-size: 11px; font-weight: 700; color: #475569; }
        .cyc-assign-body select, .cyc-assign-body input { width: 100%; height: 36px; padding: 0 9px; border: 1px solid #cbd5e1;
            border-radius: 7px; font-size: 13px; outline: none; background: #fff; }
        .cyc-assign-body select:focus, .cyc-assign-body input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .cyc-assign-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 12px 18px; border-top: 1px solid #e2e8f0; }
        .cyc-assign-foot .cyc-assign-right { display: flex; gap: 8px; }
        #cycAssignReset { border: 1px solid #fca5a5; background: #fef2f2; color: #b91c1c; }
        #cycAssignReset:hover { background: #fee2e2; }
        .cyc-assign-foot button { height: 36px; padding: 0 16px; border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; }
        #cycAssignSave { border: 0; background: #1e3a5f; color: #fff; }
        #cycAssignSave:hover { background: #162c4a; }
        #cycAssignClose { border: 1px solid #cbd5e1; background: #fff; color: #334155; }
        #cycleGrid [data-cycle-from] { transition: box-shadow .12s, background .12s; }
        #cycleGrid [data-cycle-from].cycle-drop-target { background: rgba(14, 165, 233, .07); }
        /* Karta tanlanganda uning oqim qatori ajralib turadi */
        #cycleGrid .cyc-cell.cycle-row-target:not(.cyc-block) { background: #ecfeff; box-shadow: inset 0 -2px 0 #06b6d4; cursor: copy; }
        #cycleGrid .cyc-cell.cycle-row-muted { opacity: .45; }
        .cycle-pn-card.cycle-pn-selected { outline: 2px solid #0ea5e9; outline-offset: 1px;
            box-shadow: 0 6px 16px rgba(14,165,233,.28); }
        .cr-proposal-overlay { position: fixed; inset: 0; z-index: 10000; background: #0f172a99; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .cr-proposal-form { width: min(100%, 520px); max-height: 90vh; overflow: auto; background: white; color: #0f172a; border-radius: 12px; padding: 24px; display: grid; gap: 14px; box-shadow: 0 20px 60px #0004; }
        .cr-proposal-form label { display: grid; gap: 5px; }
        .cr-proposal-form select, .cr-proposal-form textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; background: white; color: #0f172a; }
        #cycleGrid .cyc-block[draggable="true"] { cursor: grab; }
        #cycleGrid .cyc-block[draggable="true"]:active { cursor: grabbing; }
        .cycle-pn-card { width: 220px; min-height: 54px; padding: 8px 10px; border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb; border-radius: 9px; background: linear-gradient(135deg,#eff6ff,#f8fafc);
            cursor: grab; box-shadow: 0 2px 5px rgba(30,64,175,.08); }
        .cycle-pn-card:hover { border-color: #60a5fa; box-shadow: 0 5px 12px rgba(30,64,175,.14); transform: translateY(-1px); }
        .cycle-pn-card:active { cursor: grabbing; }
        .cycle-pn-card .cycle-pn-subject { display: block; color: #1e3a8a; font-size: 11px; font-weight: 700; line-height: 1.25; }
        .cycle-pn-card .cycle-pn-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 5px; color: #64748b; font-size: 10px; }
        .cycle-pn-card .cycle-pn-days { color: #0369a1; font-weight: 800; white-space: nowrap; }
        /* ── Qoidalar (aSc "Взаимосвязи") ── */
        .tt-rules-table th { background: #e8eef7; border: 1px solid #c7d2e0; padding: 4px 6px; text-align: left;
            font-weight: 700; color: #1e3a5f; position: sticky; top: 0; z-index: 1; }
        .tt-rules-table td { border: 1px solid #dbe3ec; padding: 3px 6px; background: #fff; vertical-align: top; }
        .tt-rules-table tr.sel td { background: #dbeafe; }
        .tt-rules-table tr { cursor: pointer; }
        .tt-rules-table tr.off td { color: #9ca3af; font-style: italic; }
        .tt-rule-w { display: inline-block; padding: 0 5px; border-radius: 3px; font-size: 10px; font-weight: 700; }
        .tt-rule-w.majburiy { background: #fee2e2; color: #991b1b; }
        .tt-rule-w.normal   { background: #e0e7ff; color: #3730a3; }
        .tt-rule-w.yengil   { background: #f1f5f9; color: #475569; }
        .tt-cond-item { display: block; padding: 5px 7px; border-radius: 5px; font-size: 12px; color: #1f2937; cursor: pointer; }
        .tt-cond-item:hover { background: #eff6ff; }
        .tt-cond-item.sel { background: #dbeafe; outline: 1px solid #93c5fd; }
        .tt-cond-item input { margin-right: 6px; }
        .tt-pick-item { display: flex; align-items: center; gap: 6px; padding: 2px 4px; font-size: 11px;
            color: #334155; border-radius: 4px; cursor: pointer; }
        .tt-pick-item:hover { background: #f1f5f9; }
        #rulesBadge:not(.hidden) { display: inline-block; min-width: 16px; padding: 0 4px; margin-left: 4px;
            background: #2563eb; color: #fff; border-radius: 8px; font-size: 10px; font-weight: 700; text-align: center; }
        /* ── Modal oynalar (Tailwind kompilyatsiyasiga bog'liq bo'lmasin — inline) ── */
        .tt-modal { position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,.55); overflow-y: auto; }
        .tt-modal.hidden { display: none; }
        .tt-modal.tt-modal-top { z-index: 70; }   /* boshqa modal ustidagi ichki dialog */
        /* O'qituvchi biriktirish jadvali: kafedra ustuni tor (gorizontal scroll bo'lmasin) */
        #asgTable td.asg-kaf { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* Xona tanlash oynasi */
        #asgRoomModal .rm-list { max-height: 55vh; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        #asgRoomModal .rm-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px; border: 0; border-bottom: 1px solid #f1f5f9;
            background: #fff; text-align: left; cursor: pointer; font-size: 13px; color: #1e293b; }
        #asgRoomModal .rm-row:hover { background: #eff6ff; }
        #asgRoomModal .rm-row.is-current { background: #ecfdf5; }
        #asgRoomModal .rm-row b { min-width: 120px; }
        #asgRoomModal .rm-row span { color: #64748b; font-size: 12px; }
        #asgRoomModal .rm-row .rm-vol { margin-left: auto; font-variant-numeric: tabular-nums; }
        .tt-modal-body { min-height: 100%; display: flex; align-items: center; justify-content: center;
            padding: 2.5vh 16px; box-sizing: border-box; }
        .tt-modal-win { width: 100%; max-width: min(1200px, 96vw); background: #eef2f7;
            border-radius: 12px; box-shadow: 0 28px 80px rgba(2,6,23,.55); border: 1px solid #cbd5e1;
            display: flex; flex-direction: column; max-height: 95vh; overflow: hidden; }
        .tt-settings-modal { width: 94vw; max-width: 1400px; height: 82vh; max-height: 82vh; }
        .tt-settings-modal .set-content { flex: 1 1 auto; min-height: 0; max-height: none !important; }
        @media (max-width: 640px) { .tt-settings-modal { width: 96vw; height: 88vh; max-height: 88vh; } }
        .tt-modal .asc-titlebar { box-shadow: 0 1px 0 rgba(255,255,255,.15) inset; }
        .tt-modal-win.asc-small-modal { border-radius: 12px; overflow: hidden; }
        .asc-small-modal .modal-panel { background: #fff; }
        .asc-small-modal .modal-footer { border-top: 1px solid #e2e8f0; background: #f8fafc; }
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
        .asc-list-toolbar { min-height: 58px; align-items: center; }
        #ascButtons { flex: 0 1 auto; min-width: 0; }
        #ascButtons .asc-action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 5px; height: 36px; width: auto; white-space: nowrap; }
        #ascButtons .asc-action-btn.block { display: inline-flex; width: auto; }
        #ascButtons .my-1.border-t { display: none; }
        #ascSearch { flex: 1 1 220px; width: 220px; max-width: 280px; height: 36px; }
        #ascFilter { height: 36px; }
        #ascButtons .asc-action-btn { white-space: nowrap; min-height: 34px; padding: 5px 10px; }
        .asc-column-filter-row th { position: sticky; top: 29px; z-index: 2; padding: 3px 5px; background: #f8fafc; }
        .asc-column-filter-row input,
        .asc-column-filter-row select { width: 100%; min-width: 0; height: 27px; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; color: #475569; font-size: 10px; font-weight: 400; }
        .asc-column-filter-row input:focus,
        .asc-column-filter-row select:focus { border-color: #60a5fa; outline: 2px solid rgba(96,165,250,.18); }
        .asc-subj-mode-cell { min-width: 0; white-space: nowrap !important; display: table-cell; vertical-align: middle; }
        .asc-subj-season-cell { min-width: 164px; white-space: nowrap !important; overflow-wrap: normal !important; word-break: normal !important; vertical-align: middle; }
        .asc-subj-season-wrap { display: block; min-height: 28px; }
        .asc-subj-season { display: block; width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; padding: 4px 7px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; color: #334155; font-size: 11px; text-align: left; text-align-last: left; white-space: nowrap; }
        .asc-subj-mode { display: inline-block; vertical-align: middle; width: 45%; min-width: 120px; margin-right: 12px; padding: 4px 7px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; color: #334155; font-size: 11px; text-align: left; text-align-last: left; }
        .asc-subj-mode option { text-align: left; }
        .asc-subj-mode-cell .asc-subj-params { display: inline-flex; vertical-align: middle; width: calc(45% - 8px); min-width: 0; margin-top: 0; flex-wrap: nowrap; }
        .asc-subj-params { display: flex; flex-wrap: wrap; gap: 5px 8px; margin-top: 5px; color: #64748b; font-size: 10px; }
        .asc-subj-param { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
        .asc-subj-param input { width: 58px; padding: 2px 4px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; }
        .asc-subj-param:first-child input { width: 68px; }
        .asc-subj-status { display: inline-block; margin-left: 5px; font-size: 11px; font-weight: 700; }
        .asc-row-head td { background: #f8fafc; font-weight: 700; color: #1e40af; }
        .set-tab { padding: 6px 14px; font-size: 13px; border: 1px solid #cbd5e1; border-bottom: none;
            border-radius: 6px 6px 0 0; background: #e2e8f0; color: #475569; }
        .set-tab.active { background: #fff; color: #1e40af; font-weight: 600; }
        .grid-setting-filter { min-width: 190px; height: 34px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; background: #fff; font-size: 12px; }
        .grid-setting-table-wrap { overflow-x: auto; border: 1px solid #dbe3ec; border-radius: 8px; background: #fff; }
        .grid-setting-table { width: 100%; min-width: 1100px; border-collapse: collapse; font-size: 11px; }
        .grid-setting-table th, .grid-setting-table td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: middle; }
        .grid-setting-table th { background: #eef2f7; color: #475569; font-weight: 700; text-align: center; white-space: nowrap; }
        .grid-setting-table td.grid-setting-name { min-width: 150px; max-width: 220px; color: #334155; text-align: left; vertical-align: middle; }
        .grid-setting-table td.grid-setting-name span { display: block; font-size: 11px; line-height: 1.25; word-break: break-word; }
        .grid-setting-table td.grid-setting-empty { text-align: center; color: #94a3b8; vertical-align: middle; }
        .grid-setting-cell { min-width: 145px; position: relative; display: grid; grid-template-columns: minmax(0, 1fr) 30px; gap: 5px; align-items: center; }
        .grid-setting-values { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px; align-items: center; }
        .grid-setting-values label { color: #64748b; font-size: 9px; cursor: help; }
        .grid-setting-values input { display: block; width: 100%; margin-top: 2px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px; font-size: 11px; background: #fff; }
        .grid-setting-save { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 27px; border: 1px solid #2563eb; border-radius: 5px; background: #2563eb; color: #fff; }
        .grid-setting-save img { width: 18px; height: 18px; object-fit: contain; }
        .grid-setting-save:hover { background: #1d4ed8; }
        .grid-setting-save:disabled { opacity: .55; cursor: wait; }
        .grid-setting-status { grid-column: 1 / -1; margin-left: 5px; color: #059669; font-size: 10px; }
        #stBellTable td { padding: 3px 6px; }
        #stBellTable tr.is-break td { background: #f0fdf4; color: #15803d; }
        #stBellTable tbody tr { cursor: pointer; }
        #stBellTable tr.bell-sel td { background: #dbeafe !important; box-shadow: inset 0 0 0 9999px rgba(59,130,246,.12); }
        #stBellTable tr.bell-sel td:first-child { box-shadow: inset 3px 0 0 #2563eb; }
        .asc-mini { padding: 1px 5px; margin-left: 2px; font-size: 12px; border: 1px solid #cbd5e1;
            border-radius: 4px; background: #f8fafc; color: #475569; }
        .asc-mini:hover { background: #e2e8f0; }
        .asc-mini img { width: 18px; height: 18px; object-fit: contain; display: block; }
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
            position: relative;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            width: 70px;
            min-height: 70px;
            padding: 8px;
            border: 0;
            border-radius: 10px;
            color: #475569;
            background: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            text-align: left;
            transition: transform 0.22s cubic-bezier(.2,.8,.2,1), background-color 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
            outline: none;
        }
        .asc-nav-btn > span:not(.asc-nav-icon) { display: none; }
        .asc-nav-btn .asc-nav-icon { width: 54px; height: 54px; flex: 0 0 54px; }
        .asc-nav-btn { background: transparent; box-shadow: none; }
        .asc-nav-btn:focus,
        .asc-nav-btn:focus-visible { outline: none; border: 0; }
        .asc-nav-btn.active:focus,
        .asc-nav-btn.active:focus-visible { border: 2px solid #2563eb; }
        .asc-nav-btn:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: calc(100% + 8px);
            top: 50%;
            z-index: 9999;
            transform: translateY(-50%);
            padding: 7px 10px;
            border-radius: 6px;
            background: #0f172a;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 8px 20px rgba(15,23,42,.22);
            pointer-events: none;
        }
        .asc-nav-btn:hover {
            z-index: 60;
            transform: scale(1.08);
            background: transparent;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .asc-nav-btn.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: 2px solid #2563eb;
            border-color: #2563eb;
            outline: none;
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
        .asc-nav-icon .bi {
            font-size: 1.25rem;
            line-height: 1;
        }
        .asc-nav-icon img, .asc-header-icon img { width: 100%; height: 100%; object-fit: contain; }
        .asc-header-icon .bi {
            font-size: 1.6rem;
            line-height: 1;
        }
        .asc-close-btn .bi {
            font-size: 1rem;
            line-height: 1;
        }
        #ascTable.asc-auditorium-table {
            width: 100%;
            min-width: 100%;
            table-layout: auto;
        }
        #ascTable.asc-auditorium-table th,
        #ascTable.asc-auditorium-table td {
            min-width: 0;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: top;
        }
        #ascTable.asc-auditorium-table .tt-aud-code { width: 7%; }
        #ascTable.asc-auditorium-table .tt-aud-name { width: 18%; }
        #ascTable.asc-auditorium-table .tt-aud-volume { width: 8%; }
        #ascTable.asc-auditorium-table .tt-aud-building { width: 35%; }
        #ascTable.asc-auditorium-table .tt-aud-type { width: 22%; }
        #ascTable.asc-auditorium-table .tt-aud-status { width: 10%; }
        .asc-modal-header {
            min-height: 58px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(120deg, #1e4f91 0%, #2f6fba 55%, #3c82cc 100%);
            border-bottom: 1px solid rgba(255,255,255,.22);
        }
        .asc-modal-header::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            right: 10%;
            top: -220px;
            border-radius: 50%;
            background: rgba(255,255,255,.09);
            pointer-events: none;
        }
        .asc-header-main, .asc-close-btn { position: relative; z-index: 1; }
        .asc-header-icon {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 3px 5px rgba(15,23,42,.3));
        }
        .asc-header-icon svg { width: 100%; height: 100%; }
        .asc-close-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 999px;
            color: rgba(255,255,255,.9);
            background: rgba(15,23,42,.14);
            transition: background .18s ease, transform .18s ease, border-color .18s ease;
        }
        .asc-close-btn svg { width: 16px; height: 16px; }
        .asc-close-btn:hover {
            color: #fff;
            background: rgba(220,38,38,.85);
            border-color: rgba(255,255,255,.7);
            transform: rotate(90deg);
        }
        .asc-close-btn:focus-visible {
            outline: 3px solid rgba(191,219,254,.8);
            outline-offset: 2px;
        }
        #ascCloseBtn {
            transition: background .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
        }
        #ascCloseBtn:hover { color: #1d4ed8; border-color: #60a5fa; transform: translateY(-1px); }
        .asc-table-scroll {
            cursor: grab;
            overscroll-behavior: contain;
            scrollbar-color: #94a3b8 #e2e8f0;
        }
        .asc-subject-table-scroll { cursor: default; overflow-x: hidden; }
        .asc-subject-table-scroll.is-dragging { cursor: default; }
        #ascTable.asc-subject-table { width: 100%; min-width: 100%; table-layout: fixed; }
        .asc-subject-table th, .asc-subject-table td { white-space: normal; overflow-wrap: anywhere; word-break: break-word; vertical-align: top; }
        .asc-subject-table th:nth-child(1), .asc-subject-table td:nth-child(1) { width: 16%; }
        .asc-subject-table th:nth-child(2), .asc-subject-table td:nth-child(2) { width: 10%; }
        .asc-subject-table th:nth-child(3), .asc-subject-table td:nth-child(3) { width: 10%; }
        .asc-subject-table th:nth-child(4), .asc-subject-table td:nth-child(4) { width: 22%; }
        .asc-subject-table th:nth-child(5), .asc-subject-table td:nth-child(5),
        .asc-subject-table th:nth-child(6), .asc-subject-table td:nth-child(6),
        .asc-subject-table th:nth-child(7), .asc-subject-table td:nth-child(7) { width: 4%; }
        .asc-subject-table th:nth-child(8), .asc-subject-table td:nth-child(8) { width: 10%; }
        .asc-subject-table th:nth-child(9), .asc-subject-table td:nth-child(9) { width: 20%; }
        .asc-subject-path { line-height: 1.2; }
        .asc-subject-faculty { color: #334155; font-size: 11px; font-weight: 600; line-height: 1.25; }
        .asc-subject-specialty { color: #64748b; font-size: 10px; line-height: 1.25; }
        .asc-semester-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            padding: 3px 7px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.15;
            white-space: nowrap;
        }
        .asc-semester-kuzgi { color: #92400e; background: #fef3c7; border: 1px solid #fde68a; }
        .asc-semester-bahorgi { color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; }
        .asc-table-scroll.is-dragging {
            cursor: grabbing;
            user-select: none;
        }
        .asc-btn.asc-action-btn { display: flex; align-items: center; gap: 5px; justify-content: flex-start; }
        .asc-action-icon { width: 25px; height: 25px; flex: 0 0 25px; display: inline-flex; }
        .asc-action-icon svg { width: 100%; height: 100%; }
        .asc-action-icon .bi { font-size: 25px; line-height: 1; }
        .asc-action-icon img { width: 100%; height: 100%; object-fit: contain; }
        .asc-action-btn.primary .asc-action-icon { color: #fff; }
        .asc-action-btn.danger .asc-action-icon { color: #b91c1c; }
        .asc-action-btn:disabled .asc-action-icon { color: #94a3b8; }
        .toolbar-row { align-items: flex-end; gap: 8px; }
        .tt-control-panel {
            overflow: visible;
            border: 1px solid rgba(219,228,240,.94);
            border-radius: 5px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 8px 25px rgba(15,23,42,.06), 0 2px 7px rgba(15,23,42,.04);
        }
        .tt-top-toolbar {
            display: flex;
            align-items: center;
            gap: 0;
            width: 100%;
            padding: 14px;
        }
        .tt-board-select {
            position: relative;
            flex: 0 1 360px;
            min-width: 250px;
            max-width: 360px;
        }
        .tt-board-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            z-index: 2;
            width: 25px;
            height: 25px;
            color: #2563eb;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .tt-board-icon svg { width: 100%; height: 100%; }
        .tt-board-icon .bi { font-size: 25px; line-height: 1; }
            .tt-board-icon img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
        .tt-board-select select {
            width: 100%;
            height: 42px;
            padding: 0 36px 0 38px;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: #172033;
            font-size: 12px;
            cursor: pointer;
        }
        .tt-toolbar-actions {
            display: flex;
            flex: 2 1 850px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 6px;
        }
        .tt-toolbar-actions .asc-tool, .tt-grid-field .asc-tool {
            min-height: 42px;
            padding: 0 10px;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            background: linear-gradient(180deg,#fff,#f8fafc);
            color: #334155;
            font-weight: 600;
            box-shadow: none;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        .tt-toolbar-actions .asc-tool:hover, .tt-grid-field .asc-tool:hover {
            border-color: #b9c8dd;
            background: #f8fafc;
            box-shadow: 0 5px 14px rgba(15,23,42,.08);
            transform: translateY(-1px);
        }
        .tt-icon-success { color: #059669; }
        /* Toolbar ikonlari amal turini rang bilan tez ajratib turadi. */
        #newBoardBtn .toolbar-icon { color: #059669; }
        #genBtn .toolbar-icon { color: #7c3aed; }
        #refreshNamesBtn .toolbar-icon { color: #d97706; }
        #reloadBoardBtn .toolbar-icon { color: #0284c7; }
        @keyframes tt-spin { to { transform: rotate(360deg); } }
        #reloadBoardBtn.is-loading .toolbar-icon .bi { display: inline-block; animation: tt-spin .8s linear infinite; }
        #delBoardBtn .toolbar-icon { color: #dc2626; }
        #settingsBtn .toolbar-icon { color: #475569; }
        #managerBtn .toolbar-icon { color: #2563eb; }
        #assignBtn .toolbar-icon { color: #db2777; }
        #excelViewBtn .toolbar-icon { color: #0f766e; }
        #checkBtn .toolbar-icon { color: #1d4ed8; }
        #gsSave .toolbar-icon { color: #2563eb; }
        #unplaceBtn .toolbar-icon { color: #dc2626; }
        .tt-toggle-icon { width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; }
        #autoScope + .tt-toggle-icon { color: #2563eb; }
        .tt-control-panel .toolbar-icon .bi,
        .tt-control-panel .tt-toggle-icon .bi { line-height: 1; }
        .tt-danger-btn {
            min-height: 42px;
            padding: 0 14px;
            border: 1px solid #fecaca !important;
            border-radius: 9px;
            background: linear-gradient(180deg,#fff,#fef2f2) !important;
            color: #dc2626 !important;
            font-weight: 600;
        }
        .tt-check-btn {
            position: relative;
            border-color: #bfdbfe !important;
            background: linear-gradient(180deg,#fff,#eff6ff) !important;
            color: #1d4ed8 !important;
        }
        #checkBadge {
            position: absolute;
            top: -10px;
            right: -9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 24px;
            padding: 0 6px;
            border: 2px solid #fff;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            box-shadow: 0 3px 8px rgba(220,38,38,.25);
        }
        #checkBadge.hidden { display: none; }

        .tt-work-panel { padding: 0; }
        .tt-filters-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            padding: 14px;
            flex-wrap: wrap;
        }
        .tt-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .tt-field > label {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .tt-field .tt-dd-btn, .tt-field > select {
            min-width: 145px;
            height: 42px;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            background: #fff;
            color: #172033;
            font-size: 12px;
        }
        .tt-course-field .tt-dd-btn { min-width: 104px; }
        .tt-field .tt-dd-btn:hover, .tt-field > select:hover {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(37,99,235,.08);
        }
        .tt-grid-field {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        .tt-grid-inputs { display: flex; gap: 5px; }
        .tt-grid-inputs input {
            width: 38px;
            height: 42px;
            padding: 3px;
            border: 1px solid #dbe4f0;
            border-radius: 7px;
            outline: none;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
        }
        .tt-grid-inputs input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .tt-lesson-tabs {
            display: inline-flex;
            min-height: 42px;
            overflow: hidden;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            background: #fff;
        }
        .tt-lesson-tabs .tt-type {
            min-width: 66px;
            padding: 0 12px;
            border: 0;
            border-right: 1px solid #edf2f7;
            background: transparent;
            color: #475569;
            font-size: 11px;
            font-weight: 600;
        }
        .tt-lesson-tabs .tt-type:last-child { border-right: 0; }
        .tt-lesson-tabs .tt-type.active {
            background: linear-gradient(135deg,#10b981,#059669);
            color: #fff;
        }
        .tt-week-field select { min-width: 170px; }
        /* Hafta navigatsiyasi Tashqi/Ichki tugmalaridan keyin, bir qatorda */
        .tt-week-inline { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .tt-week-inline .tt-ktr-weeks-btn { margin-left: 0; height: 30px; }
        .tt-view-field select { min-width: 100px; }
        .tt-main-actions {
            display: flex;
            align-items: flex-end;
            gap: 9px;
            margin-left: auto;
        }
        .tt-success-btn {
            min-height: 42px;
            padding: 0 15px;
            border: 1px solid #059669;
            border-radius: 9px;
            background: linear-gradient(135deg,#10b981,#059669);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 6px 15px rgba(5,150,105,.2);
        }
        .tt-bottom-row {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 64px;
            padding: 12px 14px;
            border-top: 1px solid #edf2f7;
            border-radius: 0 0 15px 15px;
            background: linear-gradient(90deg,rgba(248,250,252,.72),rgba(255,255,255,.94));
            flex-wrap: wrap;
        }
        .tt-toggle-group { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
        .tt-toggle-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid #dbe4f0;
            border-radius: 999px;
            background: #fff;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: .2s ease;
        }
        .tt-toggle-chip input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
        .tt-toggle-icon { display: inline-flex; width: 18px; height: 18px; color: #2563eb; }
        .tt-toggle-icon svg { width: 100%; height: 100%; }
        .tt-toggle-icon .bi { font-size: 18px; line-height: 1; }
        .tt-toggle-chip:hover { border-color: #93c5fd; background: #eff6ff; }
        .tt-toggle-chip:has(input:checked) {
            border-color: #047857;
            background: linear-gradient(135deg, #10b981, #047857);
            color: #fff;
            box-shadow: 0 6px 16px rgba(5,150,105,.28);
            transform: translateY(-1px);
        }
        .tt-toggle-chip:has(input:checked) .tt-toggle-icon { color: #fff; }
        .tt-toggle-chip:has(input:checked):hover {
            border-color: #065f46;
            background: linear-gradient(135deg, #059669, #065f46);
            color: #fff;
        }
        .tt-auto-msg { min-width: 100px; }
        .tt-statistics {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-left: auto;
        }
        .tt-statistics > span {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 15px;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
        }
        .tt-statistics > span:first-child {
            border-color: #d1fae5;
            background: linear-gradient(135deg,#f0fdf4,#ecfdf5);
            color: #047857;
        }
        .tt-statistics > span:last-child {
            border-color: #dbeafe;
            background: linear-gradient(135deg,#eff6ff,#f8fbff);
        }
        .tt-statistics b { margin-left: 4px; color: #1d4ed8; font-size: 13px; }
        .tt-statistics > span:first-child b { color: #047857; }

        @media (max-width: 1450px) {
            .tt-top-toolbar { align-items: flex-start; flex-wrap: wrap; }
            .tt-toolbar-actions { justify-content: flex-start; }
            .tt-main-actions { margin-left: 0; }
        }
        @media (max-width: 900px) {
            .tt-board-select, .tt-toolbar-actions { flex-basis: 100%; max-width: none; }
            .tt-toolbar-actions .asc-tool { flex: 1 1 145px; }
            .tt-filters-row { align-items: stretch; }
            .tt-field, .tt-field .tt-dd, .tt-field .tt-dd-btn, .tt-field > select { width: 100%; }
            .tt-grid-field { width: 100%; flex-wrap: wrap; }
            .tt-grid-inputs { flex: 1; }
            .tt-grid-inputs input { flex: 1; width: auto; }
            .tt-main-actions { width: 100%; }
            .tt-main-actions > button { flex: 1; }
            .tt-statistics { width: 100%; margin-left: 0; }
            .tt-statistics > span { flex: 1; justify-content: center; }
        }

        .toolbar-action { display: inline-flex; align-items: center; justify-content: center; gap: 5px; white-space: nowrap; }
        .toolbar-icon { width: 25px; height: 25px; display: inline-flex; flex: 0 0 25px; align-items: center; justify-content: center; }
        .toolbar-icon svg { width: 100%; height: 100%; }
        .toolbar-icon .bi { font-size: 25px; line-height: 1; }
        .toolbar-icon img, .tt-toggle-icon img { width: 100%; height: 100%; object-fit: contain; }
        .tt-custom-icon { width: 100%; height: 100%; object-fit: contain; }
        #specBar .toolbar-row { row-gap: 8px; }
        #specBar .toolbar-row > .h-6 { margin-inline: 2px; }
        .assign-modal-content { flex: 1 1 auto; }
        .assign-pane, .assign-teacher-pane { min-height: 0; }
        .assign-toolbar select, .assign-toolbar input:not([type="checkbox"]) { min-height: 34px; }
        .assign-toolbar input[type="checkbox"] { width: 16px; height: 16px; min-height: 16px; margin: 0; }
        .assign-pane-title { letter-spacing: .01em; }
        .assign-unit-info { line-height: 1.45; }
        .asg-tabs { min-height: 58px; padding: 10px 16px 0; gap: 8px; background: #fff; border-bottom: 1px solid #dbe4ef; }
         .asg-tab-button {
             display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px;
             border: 1px solid #dbe4ef; border-bottom: 0; border-radius: 10px 10px 0 0;
             background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;
             cursor: pointer; transition: color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
         }
         .asg-tab-button i { font-size: 15px; }
         .asg-tab-button:hover { color: #1d4ed8; background: #eff6ff; transform: translateY(-1px); }
         .asg-tab-button:focus { outline: none; }
         .asg-tab-button.active {
             color: #fff; border-color: #2563eb; background: linear-gradient(135deg, #2563eb, #1d4ed8);
             box-shadow: 0 -2px 10px rgba(37, 99, 235, .18);
         }
         #asgAuditoriumsPanel { min-height:0; }
         #asgAuditoriumsPanel .assign-pane { min-width: 0; }
         #asgAuditoriumsPanel .asc-table-scroll { overflow-x: hidden; }
         #asgAudTable { width: 100%; table-layout: fixed; }
         #asgAudTable th, #asgAudTable td {
             white-space: normal;
             overflow-wrap: anywhere;
             word-break: break-word;
             padding: 6px 7px;
         }
         #asgAudTable tr { cursor: pointer; }
         #asgAudTable tr:hover td { background: #eff6ff; }
         #asgAudTable tr.sel td { background: #dbeafe; box-shadow: inset 3px 0 0 #2563eb; }
         #asgAudTable th, #asgAudTable td { padding: 8px 10px; vertical-align: middle; }
         #asgAudTable .aud-general { color: #059669; font-weight: 700; }
         #asgAudTable .aud-teacher { color: #1d4ed8; font-weight: 600; }
         #asgAudTeacher:disabled, #asgAudDepartment:disabled { background: #f8fafc; }
         #asgTable tbody tr { cursor: pointer; }
        #asgTable tbody tr:hover td { background: #eff6ff; }
        #asgTable tbody tr.sel td { background: #dbeafe; box-shadow: inset 3px 0 0 #2563eb; }
        #asgTable thead th { cursor: default; }
        /* Ustun filtrlari qatori */
        #asgTable thead tr.asg-frow th { background: #f8fafc; padding: 3px 4px; }
        #asgTable thead tr.asg-frow input, #asgTable thead tr.asg-frow select {
            width: 100%; height: 24px; box-sizing: border-box; font-size: 11px; font-weight: 400;
            padding: 0 6px; border: 1px solid #d1d5db; border-radius: 5px; background: #fff; color: #334155;
        }
        #asgTable thead tr.asg-frow select { padding-right: 18px; }
        #asgTable thead tr.asg-frow input.is-active, #asgTable thead tr.asg-frow select.is-active { border-color: #2563eb; background: #eff6ff; }
        .asg-sort-btn {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            font-weight: 700;
            text-align: left;
        }
        .asg-sort-btn.asg-sort-btn-center {
            align-items: center;
            text-align: center;
        }
        .asg-sort-btn:hover,
        .asg-sort-btn.is-active {
            color: #1d4ed8;
        }
        .asg-sort-indicator {
            max-width: 100%;
            color: #2563eb;
            font-size: 10px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #asgTeacher:disabled { background: #f8fafc; }
        .check-report-body {
            margin: 12px;
            padding: 12px;
            border: 1px solid #dbe4ef;
            border-radius: 14px;
            background: linear-gradient(145deg, #f8fafc, #eef4fa);
            scrollbar-color: #94a3b8 #e2e8f0;
        }
        .check-section {
            overflow: hidden;
            margin-bottom: 10px;
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(15,23,42,.05);
        }
        .check-section:last-child { margin-bottom: 0; }
        .check-section-head {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 42px;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .check-section.is-danger .check-section-head {
            color: #b42318;
            background: linear-gradient(90deg, #fff1f2, #fff8f8);
            border-bottom: 1px solid #ffe0e3;
        }
        .check-section.is-ok .check-section-head {
            color: #047857;
            background: linear-gradient(90deg, #ecfdf5, #f7fffb);
            border-bottom: 1px solid #d1fae5;
        }
        .check-section-icon {
            width: 25px;
            height: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 25px;
            font-size: 16px;
            border-radius: 8px;
            background: rgba(255,255,255,.75);
        }
        .check-section-title { letter-spacing: .01em; }
        .check-section-count {
            margin-left: auto;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }
        .check-section-body {
            display: grid;
            gap: 5px;
            padding: 11px 14px 12px 46px;
            color: #475569;
            font-size: 12px;
            line-height: 1.45;
        }
        .check-section-body > div {
            padding: 5px 8px;
            border-radius: 6px;
            background: #f8fafc;
        }
        .check-section-empty {
            padding: 10px 14px 11px 46px;
            color: #64748b;
            font-size: 12px;
        }


        /* ── Ixcham boshqaruv paneli: jadval uchun ko'proq joy ── */
        .tt-top-toolbar { padding: 6px 8px; gap: 8px; flex-wrap: nowrap; align-items: center; }
        .tt-board-select { flex: 0 1 300px; min-width: 200px; }
        .tt-board-select select { height: 32px; font-size: 11.5px; padding-left: 34px; }
        .tt-board-icon { width: 20px; height: 20px; left: 9px; }
        .tt-toolbar-actions { flex: 1 1 auto; flex-wrap: nowrap; gap: 4px; justify-content: flex-end; min-width: 0; }
        .tt-toolbar-actions .asc-tool, .tt-grid-field .asc-tool { min-height: 32px; padding: 0 7px; font-size: 11.5px; white-space: nowrap; }
        .tt-toolbar-actions .asc-tool:hover { transform: none; }
        /* Tor ekranda tugmalar faqat belgi (nomi — sichqoncha yo'rig'ida) */
        @media (max-width: 1900px) {
            .tt-toolbar-actions .asc-tool.toolbar-action { font-size: 0; gap: 0; padding: 0 6px; }
            .tt-toolbar-actions .asc-tool.toolbar-action .toolbar-icon { margin: 0; }
            .tt-toolbar-actions .asc-tool.toolbar-action #checkBadge,
            .tt-toolbar-actions .asc-tool.toolbar-action #rulesBadge { font-size: 10px; }
        }
        .tt-work-panel { display: flex; align-items: center; flex-wrap: wrap; }
        .tt-work-panel.mb-3 { margin-bottom: 4px; }
        .tt-top-panel.mb-1 { margin-bottom: 4px; }
        .tt-filters-row { padding: 6px 8px; gap: 6px; flex: 0 1 auto; align-items: center; }
        .tt-work-panel .tt-bottom-row { flex: 1 1 auto; }
        .tt-field > select { padding-top: 0; padding-bottom: 0; line-height: 30px; }
        .tt-field > label { display: none; }
        .tt-field .tt-dd-btn, .tt-field > select { height: 32px; min-width: 120px; font-size: 11.5px; }
        .tt-bottom-row { min-height: 0; padding: 6px 8px; border-top: 0; flex-wrap: nowrap; gap: 6px; background: transparent; }
        .tt-toggle-chip { min-height: 32px; padding: 0 9px; }
        /* Tor ekranda yuqoridagi nowrap avvalgi moslashuvchan joylashuvni bosib ketmasin:
           aks holda flex-end + nowrap tugmalarni chap chetdan ekran tashqarisiga chiqarardi. */
        @media (max-width: 1450px) {
            .tt-top-toolbar, .tt-bottom-row { flex-wrap: wrap; }
            .tt-toolbar-actions { flex-wrap: wrap; justify-content: flex-start; }
        }
        @media (max-width: 900px) {
            .tt-board-select, .tt-toolbar-actions { flex: 1 1 100%; max-width: none; }
            /* Tugmalar faqat belgi — eski 145px'lik keng tugma qoidasi ularni ko'p qatorga yoyardi */
            .tt-toolbar-actions .asc-tool { flex: 0 0 auto; }
        }
        /* Sikl paneli: kichik oraliqlar */
        #cycleArea > div:first-child { gap: 8px; padding-top: 4px; padding-bottom: 4px; }
        #cycleHolBar { padding-top: 2px; padding-bottom: 2px; }
        /* Guruh filtri ("Guruh" sarlavhasi) */
        #cycleGrid th.cyc-gfilter-th { padding: 2px 4px; }
        .cyc-gfilter-btn { max-width: calc(100% - 18px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid #cbd5e1;
            border-radius: 6px; background: #fff; padding: 2px 6px; font-size: 11px; font-weight: 700; color: #1e293b; cursor: pointer; }
        .cyc-gfilter-btn.is-on { background: #dbeafe; border-color: #60a5fa; color: #1d4ed8; }
        .cyc-gfilter-x { margin-left: 2px; border: 0; background: transparent; font-size: 15px; line-height: 1; color: #64748b; cursor: pointer; }
        #cycRowPop { position: fixed; z-index: 60; width: 230px; max-height: 360px; display: flex; flex-direction: column; background: #fff;
            border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 30px rgba(15,23,42,.18); padding: 6px; }
        #cycRowPop .cyc-rp-q { width: 100%; height: 30px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; font-size: 12px; margin-bottom: 4px; }
        #cycRowPop .cyc-rp-list { overflow-y: auto; display: flex; flex-direction: column; }
        #cycRowPop .cyc-rp-list button { text-align: left; border: 0; background: transparent; padding: 5px 8px; border-radius: 6px; font-size: 12px; cursor: pointer; }
        #cycRowPop .cyc-rp-list button:hover { background: #f1f5f9; }
        #cycRowPop .cyc-rp-list button.is-on { background: #dbeafe; color: #1d4ed8; font-weight: 700; }
        #cycRowPop .cyc-rp-empty { padding: 6px 8px; color: #94a3b8; font-size: 12px; }
        /* Menyu / panjara / joylashmagan kartalar orasidagi suriladigan chegaralar */
        .tt-resizer { flex: 0 0 auto; height: 7px; margin: 1px 0; cursor: row-resize; border-radius: 4px;
            background: linear-gradient(#e2e8f0, #e2e8f0) center / 48px 3px no-repeat; touch-action: none; }
        .tt-resizer:hover, .tt-resizer.dragging { background: linear-gradient(#64748b, #64748b) center / 64px 3px no-repeat, #e0e7ff; }
        body.tt-resizing, body.tt-resizing * { cursor: row-resize !important; user-select: none !important; }
        /* Saqlangan yoki surilgan balandlik oynaga sig'masa ham panel konteynerdan chiqib ketmaydi (pastki kartalari
           ko'rinmay qolmaydi): panjaraga kamida 60px qoladi, kartalar panel ichida skroll bo'ladi. */
        #ttBottomPanel { display: flex; flex-direction: column; max-height: calc(100% - 60px); }
        #ttBottomPanel > #cardPanel { min-height: 0; }
        .tt-card-sync-track { position: relative; height: 6px; overflow: hidden; border-radius: 9999px; background: #dbeafe; }
        .tt-card-sync-track span { position: absolute; inset-block: 0; width: 34%; border-radius: inherit; background: #2563eb;
            animation: tt-card-sync-slide 1.15s ease-in-out infinite; }
        @keyframes tt-card-sync-slide { from { left: -34%; } to { left: 100%; } }
</style>

    <script>
        (function () {
            const BOARDS_STORE = @json(route('admin.timetable.boards.store'));
            const BASE = @json(url('admin/dars-jadvali-tuzish'));
            const TEACHERS_URL = @json(route('admin.timetable.teachers'));
            const TEACHER_DEPARTMENTS_URL = @json(route('admin.timetable.teachers.departments'));
            const AUDS_URL = @json(route('admin.timetable.auditoriums'));
            const CSRF = @json(csrf_token());
            // HEMIS'ga yozish xatcho'pi (bookmarklet) yuklaydigan skript.
            const HS_WRITER_URL = @json(asset('js/hemis-writer.js'));
            const TIMETABLE_ASSIGNMENT_ONLY = @json($timetableAssignmentOnly);
            const TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY = @json($timetableAuditoriumAssignmentOnly);
            const TIMETABLE_CAN_PLACE = @json($timetableCanPlace);
            const TIMETABLE_READ_ONLY = @json($timetableReadOnly);
            const TIMETABLE_CAN_APPROVE = @json($timetableCanApprove);
            const TIMETABLE_ROLE = @json($timetableActiveRole);
            // Haftalik jadval qatlami (sikl kabi): tashqi — o'quv bo'limi, ichki — kafedra mudiri.
            const WEEKLY_ADMIN = ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'].includes(TIMETABLE_ROLE);
            const WEEKLY_OQUV = ['oquv_bolimi', 'oquv_bolimi_boshligi'].includes(TIMETABLE_ROLE);
            let weeklyLayer = WEEKLY_OQUV ? 'external' : 'internal';
            try { const v = localStorage.getItem('lms:timetable-weekly-layer'); if (v === 'external' || v === 'internal') weeklyLayer = v; } catch (e) { }
            const weeklyCanEdit = () => !TIMETABLE_READ_ONLY && (WEEKLY_ADMIN || (weeklyLayer === 'external' ? WEEKLY_OQUV : TIMETABLE_ROLE === 'kafedra_mudiri'));
            // Kartani tanlangan qatlamga moslaydi: tashqida joylashuv ext_day/ext_pair, o'qituvchi va xona yashiriladi.
            function applyWeeklyLayer(list) {
                list.forEach(c => {
                    if (!c._int) c._int = { day: c.day, pair: c.pair, teacher_id: c.teacher_id, teacher_name: c.teacher_name,
                        auditorium_code: c.auditorium_code, auditorium_name: c.auditorium_name, auditorium_volume: c.auditorium_volume };
                    if (weeklyLayer === 'external') {
                        Object.assign(c, { day: c.ext_day || null, pair: c.ext_pair || null, teacher_id: null, teacher_name: null,
                            auditorium_code: null, auditorium_name: null, auditorium_volume: null });
                    } else {
                        Object.assign(c, c._int);
                    }
                });
            }
            const TIMETABLE_TEACHER_ID = @json($timetableTeacherId);
            const TIMETABLE_DEPARTMENT_HEAD = @json($timetableDepartmentHead);
            const TIMETABLE_HAS_SUBJECTS = @json((bool) $timetableHasSubjects);
            let mySubjectCardIds = new Set();   // "Mas'ul fanlarim": serverdan (data.my_subject_card_ids)
            // "Faqat meniki": faqat o'qituvchiga biriktirilgan kartalar/sikl bloklari ko'rinadi.
            // Ko'rinish qamrovi: 'all' — butun doska, 'kafedra' — kafedra mudirining o'z kafedrasi
            // fanlari, 'mine' — faqat o'ziga biriktirilganlar. mineOnly = qamrov toraytirilgan.
            let scopeMode = TIMETABLE_HAS_SUBJECTS && TIMETABLE_ROLE === 'fan_masuli' ? 'subjects' : 'all';
            try {
                const saved = localStorage.getItem('lms:timetable-mine');
                if (TIMETABLE_TEACHER_ID && saved === '1') scopeMode = 'mine';
                else if (TIMETABLE_DEPARTMENT_HEAD && saved === 'kafedra') scopeMode = 'kafedra';
                else if (TIMETABLE_HAS_SUBJECTS && saved === 'subjects') scopeMode = 'subjects';
                else if (saved === '0') scopeMode = 'all';
            } catch (e) { }
            let mineOnly = scopeMode !== 'all';
            let myKafedras = [];
            const kafedraNorm = v => String(v || '').toLowerCase().trim().replace(/\s*kafedra(si)?\s*$/u, '').replace(/[^\p{L}\p{N}]+/gu, '');
            function scopeModeLabel() {
                return { all: 'Butun doska', kafedra: 'Kafedraniki', subjects: "Mas'ul fanlarim", mine: 'Faqat meniki' }[scopeMode] || '';
            }
            function scopeCards(list) {
                if (scopeMode === 'mine') return list.filter(c => +c.teacher_id === +TIMETABLE_TEACHER_ID);
                if (scopeMode === 'subjects') return list.filter(c => mySubjectCardIds.has(+c.id));
                if (scopeMode === 'kafedra') {
                    const keys = new Set(myKafedras.map(kafedraNorm).filter(Boolean));
                    return list.filter(c => keys.has(kafedraNorm(c.kafedra_name)));
                }
                return list;
            }
            let allCards = [];
            let groupStudents = {};   // "fakultet|guruh" => faol talabalar soni (sikl jadvalidagi kabi)
            const TIMETABLE_CAN_USE_MANAGER = @json($timetableCanUseManager);
            const DAY_NAMES = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];

            let board = null;      // {id, days, pairs_per_day, ...}
            let cards = [];        // barcha kartochkalar
            let grids = {};        // "faculty|specialty|course" => {days, pairs_per_day, weeks}
            let specList = [];     // [{key, specialty_name, course}]
            let curSpec = null;    // tanlangan {specialty_name, course} (asosiy/primary)
            // Ko'p tanlovli qamrov (dropdown checkboxlari) — bir nechta fakultet/yo'nalish/kurs
            let selectedFaculties = new Set();
            let selectedDirs = new Set();
            let selectedCourses = new Set();
            let groupRows = [];    // [{oqim_label, lang, group}]
            let flowStudents = {}; // fakultet|yo'nalish|kurs|oqim => talabalar soni
            let selected = null;   // tanlangan karta (obyekt)
            let audCache = null;
            let modalCard = null;
            let overrides = {};    // "cardId|week" => {day, pair, cancelled, auditorium_*}
            let missingGroups = []; // rejada fani bor, lekin guruh proyeksiyasi yo'q yo'nalish+kurslar
            let subjectSettings = {};  // "spec|course|subject" => {mode, season, rotation_group, occurrences, cycle_days}
            let autoCycleBackup = null;
            let autoCycleApplied = false;
            let cycleGroupFilter = '';
            const SUBJ_MODE_LABELS = { normal: 'Har hafta', alternate: 'Hafta almashinuvi', cycle: 'Sikl (blok)' };
            const SUBJ_SEASON_LABELS = { kuzgi: 'Kuzgi', bahorgi: 'Bahorgi' };
            // Fan-rejim kaliti — katta-kichik harf/bo'shliqqa befarq (reja nomi
            // "Davolash ishi", karta nomi "davolash ishi" bo'lishi mumkin).
            const gridKey = (faculty, spec, course, curriculum = 0) =>
                String(faculty || '').trim() + '|' + String(spec || '').trim() + '|' + course + '|' + (+curriculum || 0);
            const gridForCard = c => grids[gridKey(c.faculty_name || c.faculty || '', c.specialty_name || c.specialty, c.course, c.curriculum_hemis_id)]
                || grids[gridKey(c.faculty_name || c.faculty || '', c.specialty_name || c.specialty, c.course)]
                || grids[gridKey('', c.specialty_name || c.specialty, c.course)]
                || { days: board.days, pairs_per_day: board.pairs_per_day, weeks: board.weeks };
            const subjModeKey = (spec, course, subject) =>
                String(spec || '').trim().toLowerCase() + '|' + course + '|' + String(subject || '').trim().toLowerCase();
            let curWeek = 0;       // 0 = barcha haftalar (shablon); 1..N = alohida hafta
            // Doska va ko'rinish tanlovlari (fakultet/yo'nalish/kurs, dars turi, hafta, kesim)
            // URLda saqlanadi — F5 bosilganda yoki havola ochilganda aynan shu doska va
            // shu ko'rinishga qaytamiz (doskadan chiqib ketmaymiz).
            let pendingView = parseViewFromUrl();

            const $ = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            const subjectSettingOf = row => subjectSettings[subjModeKey(row.specialty_name, row.course, row.subject_name)] || {};
            const subjectSeasonOf = row => subjectSettingOf(row).season || row.season || (board && board.semester_parity) || 'kuzgi';
            const subjectSeasonLabel = season => SUBJ_SEASON_LABELS[season] || season || 'Kuzgi';
            const subjectSemesterLabel = row => row.semester_label || (row.semester ? (row.semester + '-semestr') : 'вЂ”');
            const subjectSeasonOptionLabel = (row, season) => subjectSemesterLabel(row) + ' (' + subjectSeasonLabel(season) + ')';
            const subjectSeasonOptionsHtml = (row, season) => Object.keys(SUBJ_SEASON_LABELS).map(value =>
                '<option value="' + value + '"' + (season === value ? ' selected' : '') + '>' + esc(subjectSeasonOptionLabel(row, value)) + '</option>').join('');
            const subjectSeasonBadgeText = (row, season = subjectSeasonOf(row)) => {
                const semester = row.semester_label || (row.semester ? (row.semester + '-semestr') : '—');
                return semester + ' (' + subjectSeasonLabel(season) + ')';
            };
            const repaintSubjectSeason = (tr, row, season) => {
                const select = tr.querySelector('.asc-subj-season');
                if (select) {
                    syncSubjectSeasonSelect(tr, row, season);
                    select.value = season || '';
                }
            };
            function subjectSeasonSemesterLabel(row, season = subjectSeasonOf(row)) {
                const base = Number.parseInt(row.semester, 10);
                if (!Number.isFinite(base) || base <= 0) return row.semester_label || '-';
                const boardSeason = (board && board.semester_parity) || 'kuzgi';
                if (season === boardSeason) return base + '-semestr';
                return (boardSeason === 'kuzgi' ? (base + 1) : Math.max(1, base - 1)) + '-semestr';
            }
            function syncSubjectSeasonSelect(tr, row, season = subjectSeasonOf(row)) {
                const select = tr.querySelector('.asc-subj-season');
                if (!select) return;
                select.innerHTML = Object.keys(SUBJ_SEASON_LABELS).map(value =>
                    '<option value="' + value + '"' + (season === value ? ' selected' : '') + '>'
                    + esc(subjectSeasonSemesterLabel(row, value) + ' (' + subjectSeasonLabel(value) + ')')
                    + '</option>').join('');
            }

            function applyTimetableAccess() {
                if (!TIMETABLE_ASSIGNMENT_ONLY) return;

                const restrictedIds = [
                    'newBoardBtn', 'genBtn', 'refreshNamesBtn', 'delBoardBtn',
                    'settingsBtn', 'managerBtn', 'excelViewBtn', 'checkBtn', 'rulesBtn',
                    'unplaceBtn', 'gsSave', 'cycleHolAddBtn',
                    'cycleRefresh', 'cmSave', 'cmUnplace', 'cmResetWeek'
                ];
                restrictedIds.forEach(id => {
                    if (TIMETABLE_CAN_PLACE && ['unplaceBtn', 'cycleHolAddBtn', 'cycleRefresh'].includes(id)) return;
                    if (id === 'managerBtn' && TIMETABLE_CAN_USE_MANAGER) return;
                    const el = $(id);
                    if (!el) return;
                    el.disabled = true;
                    el.classList.add('hidden');
                    el.title = 'Bu amal faqat jadval administratoriga ochiq';
                });

                if (TIMETABLE_READ_ONLY) {
                    ['assignBtn', 'unplacedExportBtn', 'subjectColorsBtn', 'cycleAllowOverlap', 'cycleIgnoreGap'].forEach(id => {
                        const el = $(id);
                        if (!el) return;
                        el.disabled = true;
                        (el.closest('label') || el).classList.add('hidden');
                    });
                    return;
                }
                const assignButton = $('assignBtn');
                if (assignButton) {
                    assignButton.disabled = false;
                    assignButton.title = TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY
                        ? 'Auditoriyalarni biriktirish'
                        : 'Fanlarni o\'qituvchilarga biriktirish';
                }
            }
            function applyAscRoleAccess() {
                if (!TIMETABLE_CAN_USE_MANAGER) return;
                document.querySelectorAll('.asc-nav-btn').forEach(btn => {
                    const allowed = btn.dataset.ascType === 'subjects';
                    btn.classList.toggle('hidden', !allowed);
                    btn.disabled = !allowed;
                    btn.tabIndex = allowed ? 0 : -1;
                });
            }

            applyTimetableAccess();
            applyAscRoleAccess();
            // Ixcham panelda tugma nomi yashiriladi — u sichqoncha yo'rig'ida ko'rinsin.
            document.querySelectorAll('.tt-toolbar-actions .toolbar-action').forEach(btn => {
                const label = btn.textContent.replace(/\s+/g, ' ').trim();
                if (!label) return;
                if (!btn.title) btn.title = label;
                else if (!btn.title.startsWith(label)) btn.title = label + ' — ' + btn.title;
                btn.setAttribute('aria-label', label);
            });

            // Jadvallarni sichqoncha bilan ushlab chap-o'ngga siljitish.
            document.querySelectorAll('[data-drag-scroll]').forEach(el => {
                let startX = 0;
                let startScroll = 0;
                let moved = false;

                el.addEventListener('pointerdown', e => {
                    if (e.button !== 0 || ascType === 'auditoriums') return;
                    if (e.target.closest?.('#asgTable tbody tr, #asgAudTable tbody tr, #asgTable thead, #asgAudTable thead, button, input, select, textarea, a, label')) return;
                    startX = e.clientX;
                    startScroll = el.scrollLeft;
                    moved = false;
                    el.classList.add('is-dragging');
                    el.setPointerCapture?.(e.pointerId);
                });
                el.addEventListener('pointermove', e => {
                    if (!el.classList.contains('is-dragging')) return;
                    const dx = e.clientX - startX;
                    if (Math.abs(dx) > 4) moved = true;
                    el.scrollLeft = startScroll - dx;
                    if (moved) e.preventDefault();
                });
                const stopDrag = () => {
                    el.classList.remove('is-dragging');
                    if (moved) {
                        el.dataset.dragged = '1';
                        setTimeout(() => { el.dataset.dragged = '0'; }, 0);
                    }
                };
                el.addEventListener('pointerup', stopDrag);
                el.addEventListener('pointercancel', stopDrag);
                el.addEventListener('click', e => {
                    if (el.dataset.dragged === '1') {
                        e.preventDefault();
                        e.stopPropagation();
                        el.dataset.dragged = '0';
                    }
                }, true);
            });

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
            // Rang fanning asosiy nomiga beriladi ("Fan (a)/(b)/(c)" — bitta rang) va avvalo
            // EKRANDAGI qamrov fanlari orasida taqsimlanadi: butun doskada yuzlab fan bo'lgani uchun
            // ranglar bir-biriga yaqinlashib qolardi (masalan Patologik anatomiya va Umumiy xirurgiya).
            // Qamrovdagi 10–20 fanga oltin burchak bilan tarqatilgan rang + navbatlanuvchi yorqinlik.
            function buildSubjectColors(scopeCards) {
                const bolakM = n => /^(.*?)\s*\((\p{L})\)\s*$/u.exec(String(n).trim());
                const baseOf = n => { const m = bolakM(n); return (m ? m[1] : String(n)).trim().toLowerCase(); };
                const allNames = [...new Set(cards.map(c => c.subject_name).filter(Boolean))];
                const scopeNames = [...new Set((scopeCards || cards).map(c => c.subject_name).filter(Boolean))];
                const order = [];
                const pushBases = names => [...new Set(names.map(baseOf))].sort((a, b) => a.localeCompare(b, 'uz'))
                    .forEach(b => { if (!order.includes(b)) order.push(b); });
                pushBases(scopeNames);
                pushBases(allNames);
                // Birinchi 11 fan — bir-biridan aniq farq qiladigan tayyor ranglar (qo'shnilari
                // ataylab uzoq: qizil, havorang, yashil, binafsha...). Sariq yo'q — u ma'ruzaniki.
                const PALETTE = [
                    ['#fecaca', '#dc2626'], ['#a5f3fc', '#0891b2'], ['#d9f99d', '#65a30d'], ['#ddd6fe', '#7c3aed'],
                    ['#fed7aa', '#ea580c'], ['#bfdbfe', '#2563eb'], ['#fbcfe8', '#db2777'], ['#a7f3d0', '#059669'],
                    ['#e7e5e4', '#57534e'], ['#c7d2fe', '#4338ca'], ['#99f6e4', '#0d9488'],
                ];
                const GOLDEN = 137.508;
                subjectColors = {};
                const colorOf = {};
                order.forEach((b, i) => {
                    colorOf[b] = i < PALETTE.length
                        ? { bg: PALETTE[i][0], border: PALETTE[i][1] }
                        : (h => ({ bg: hslToHex(h, [74, 60, 82][i % 3], [85, 90, 81][i % 3]), border: hslToHex(h, 64, 40) }))(Math.round((i * GOLDEN + 18) % 360));
                });
                [...new Set([...allNames, ...scopeNames])].forEach(n => { subjectColors[n] = { ...colorOf[baseOf(n)] }; });
            }
            // Rang qamrovi — ekranda ko'rinib turganlar: sikl ko'rinishida sikl bloklari (specCards ularni
            // chiqarib tashlaydi), aks holda tanlangan yo'nalish/kurs kartalari.
            function colorScopeCards() {
                if (viewMode === 'cycle' && cyclePlanData && Array.isArray(cyclePlanData.cycle_cards)) {
                    return cyclePlanData.cycle_cards.map(c => ({ subject_name: c.subject }));
                }
                return specCards();
            }
            // Foydalanuvchi tanlagan ranglar doska sozlamalarida saqlanadi va
            // avtomatik (golden-angle) rangdan ustun turadi.
            let subjectColorOverrides = {};
            function loadSubjectColorOverrides() {
                const stored = (board && board.settings && board.settings.subject_colors) || {};
                subjectColorOverrides = (stored && typeof stored === 'object') ? { ...stored } : {};
            }
            async function saveSubjectColorOverrides() {
                if (!board) return;
                const settings = { ...(board.settings || {}), subject_colors: subjectColorOverrides };
                await api(BASE + '/boards/' + board.id + '/subject-colors', 'POST', {
                    subject_colors: JSON.stringify(subjectColorOverrides),
                });
                board.settings = settings;
            }
            // Fon rangidan mos chegara rangi: bir xil tusda, to'yingan va qoraroq.
            function borderFromBg(hex) {
                const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || ''));
                if (!m) return '#94a3b8';
                const n = parseInt(m[1], 16);
                const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(v => v / 255);
                const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
                let h = 0;
                if (d) {
                    if (max === r) h = ((g - b) / d + (g < b ? 6 : 0));
                    else if (max === g) h = (b - r) / d + 2;
                    else h = (r - g) / d + 4;
                    h *= 60;
                }
                return hslToHex(Math.round(h), 62, 45);
            }
            const subjColor = name => {
                const custom = subjectColorOverrides[name];
                if (custom) return { bg: custom, border: borderFromBg(custom) };
                const color = subjectColors[name] || { bg: '#f1f5f9', border: '#94a3b8' };
                return { ...color };
            };
            // Ma'ruza — bir xil sariq (class'dagi tt-lec/.lec fonida qoladi,
            // inline rang bermaymiz). Amaliy — har fan o'z rangida.
            const subjStyle = c => {
                if (c.training_type === 'lecture') return '';
                const s = subjColor(c.subject_name);
                return 'background-color:' + s.bg + ';border-left-color:' + s.border + ';';
            };

            async function api(url, method = 'GET', body = null) {
                // Doska ma'lumoti tez o'zgaradi — brauzer eski GET javobini keshdan bermasin.
                const opt = { method, cache: 'no-store', headers: { 'Accept': 'application/json' } };
                if (body) {
                    const fd = new FormData();
                    fd.append('_token', CSRF);
                    Object.entries(body).forEach(([k, v]) => {
                        if (v === undefined || v === null) return;
                        // Massivlarni PHP uslubida (key[]=...) yuboramiz — aks holda
                        // vergul bilan qo'shilib bitta satr bo'lib qoladi (nullable|array 422).
                        if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
                        else fd.append(k, v);
                    });
                    opt.body = fd;
                }
                const r = await fetch(url, opt);
                const j = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(j.error || (j.errors ? Object.values(j.errors).flat().join(' · ') : j.message) || ('HTTP ' + r.status));
                if (j && j.pending_approval) onPendingApproval(j);
                // Server ogohlantirishi (masalan o'qituvchi/xona shu sanada sikl darsida band) — joylash bajarildi.
                if (j && Array.isArray(j.warnings) && j.warnings.length) {
                    ttToast('<b>&#9888; Ogohlantirish</b>' + j.warnings.map(w => '<div>' + esc(w) + '</div>').join(''), 'warn');
                }
                return j;
            }

            // ===== Jadval o'zgarishlari: tarix va registrator ofisi tasdig'i =====
            // Kafedra mudiri o'zgartirganda server o'zgarishni qo'llamaydi, so'rov qilib saqlaydi
            // (pending_approval). Ekrandagi "oldindan" ko'chirilgan holat serverdan qayta yuklanadi.
            // Tasdiqsiz qo'llangan o'zgarishlar ham serverda yoziladi — "Tarix" bo'limi o'chmaydi.
            let changeReqs = { requests: [], approval_required: false, can_approve: false };
            let changeHistory = { rows: [], more: false, loading: false };
            let changeReqFilter = 'pending';
            let pendingReloadTimer = null;
            const CR_STATUS = { pending: ['Kutilmoqda', '#b45309', '#fef3c7'], approved: ['Tasdiqlandi', '#047857', '#d1fae5'],
                rejected: ['Rad etildi', '#b91c1c', '#fee2e2'], failed: ['Qo\'llanmadi', '#b91c1c', '#fee2e2'], cancelled: ['Bekor qilindi', '#475569', '#e2e8f0'],
                applied: ['Kiritildi', '#1d4ed8', '#dbeafe'] };
            function ttToast(html, tone) {
                let box = document.getElementById('ttToastBox');
                if (!box) { box = document.createElement('div'); box.id = 'ttToastBox'; document.body.appendChild(box); }
                const t = document.createElement('div');
                t.className = 'tt-toast ' + (tone || '');
                t.innerHTML = html;
                box.appendChild(t);
                setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 6500);
            }
            function onPendingApproval(j) {
                ttToast('<b>&#9203; Tasdiqqa yuborildi</b><div>' + esc(j.summary || '') + '</div><div class="tt-toast-sub">' + esc(j.message || '') + '</div>', 'warn');
                clearTimeout(pendingReloadTimer);
                pendingReloadTimer = setTimeout(async () => {
                    if (!board) return;
                    await loadChangeRequests();
                    try {
                        if (viewMode === 'cycle') await loadCyclePlan();
                        else await loadBoard(board.id);
                    } catch (e) { }
                }, 300);
            }
            function pendingReqs() { return (changeReqs.requests || []).filter(r => r.status === 'pending'); }
            async function loadChangeRequests() {
                if (!board || !$('changeReqBtn')) return;
                try { changeReqs = await api(BASE + '/boards/' + board.id + '/change-requests?status=pending&_=' + Date.now()); }
                catch (e) { return; }
                const n = pendingReqs().length;
                const badge = $('changeReqBadge');
                badge.textContent = n; badge.classList.toggle('hidden', !n);
                markPendingChanges();
                renderCyclePendingBar();
                if (document.getElementById('crModal') && document.getElementById('crModal').classList.contains('open')) renderChangeReqModal();
            }
            // Tarix sahifalab yuklanadi (eng yangisidan): reset — boshidan.
            async function loadChangeHistory(reset) {
                if (!board || changeHistory.loading) return;
                const last = reset ? null : changeHistory.rows[changeHistory.rows.length - 1];
                changeHistory.loading = true;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/change-requests?status=history' + (last ? '&before_id=' + last.id : '') + '&_=' + Date.now());
                    changeHistory.rows = (reset ? [] : changeHistory.rows).concat(j.requests || []);
                    changeHistory.more = Boolean(j.has_more);
                } catch (e) { }
                changeHistory.loading = false;
                renderChangeReqModal();
            }
            function markPendingChanges() {
                const ids = new Set();
                pendingReqs().forEach(r => { if (/\.cards\./.test(r.route)) (r.card_ids || []).forEach(id => ids.add(+id)); });
                document.querySelectorAll('[data-chip], .pn-card[data-id]').forEach(el => {
                    const on = ids.has(+(el.dataset.chip || el.dataset.id));
                    el.classList.toggle('tt-pending-chg', on);
                    if (on && !/Tasdiq kutilmoqda/.test(el.title)) el.title = '⏳ Tasdiq kutilmoqda · ' + el.title;
                });
            }
            function renderCyclePendingBar() {
                const bar = $('cyclePendingBar');
                if (!bar) return;
                const list = pendingReqs().filter(r => !/\.cards\./.test(r.route));
                bar.classList.toggle('hidden', !list.length);
                if (!list.length) { bar.innerHTML = ''; return; }
                bar.innerHTML = '<b>&#9203; Tasdiq kutilmoqda (' + list.length + '):</b> ' +
                    list.slice(0, 3).map(r => '<span>' + esc(r.summary) + '</span>').join('') +
                    (list.length > 3 ? '<span>… yana ' + (list.length - 3) + ' ta</span>' : '') +
                    ' <button type="button" data-cr-open>Ko\'rish</button>';
                bar.querySelector('[data-cr-open]').onclick = openChangeReqModal;
            }
            // Sikl kun oynasi: shu kun bo'yicha kutilayotgan soat so'rovi (bo'lsa).
            function pendingDaySlot(card, dayIndex) {
                if (!card) return null;
                return pendingReqs().find(r => r.route === 'admin.timetable.cycle-lecture-slots' && r.payload
                    && r.payload.subject_name === card.subject && r.payload.group_name === card.group
                    && String(r.payload.day_index) === String(dayIndex) && r.payload.start_hour) || null;
            }
            function openChangeReqModal() {
                let m = document.getElementById('crModal');
                if (!m) {
                    m = document.createElement('div');
                    m.id = 'crModal';
                    m.innerHTML = '<div class="cr-box"><div class="cr-head"><b>Jadval o\'zgarishlari</b><button type="button" class="cr-x" aria-label="Yopish">&times;</button></div><div class="cr-body"></div></div>';
                    document.body.appendChild(m);
                    m.querySelector('.cr-x').onclick = () => m.classList.remove('open');
                    m.addEventListener('click', ev => { if (ev.target === m) m.classList.remove('open'); });
                }
                m.classList.add('open');
                renderChangeReqModal();
                loadChangeRequests();
                if (changeReqFilter === 'history') loadChangeHistory(true);
            }
            function renderChangeReqModal() {
                const m = document.getElementById('crModal');
                if (!m) return;
                const body = m.querySelector('.cr-body');
                const pending = pendingReqs();
                const history = changeReqFilter === 'history';
                const list = history ? changeHistory.rows : pending;
                const approver = Boolean(changeReqs.can_approve);
                const modeHtml = approver
                    ? '<label class="cr-mode"><input type="checkbox" id="crModeChk"' + (changeReqs.approval_required ? ' checked' : '') + '> ' +
                      'Kafedra mudirlari o\'zgarishlari registrator ofisi tasdig\'idan o\'tsin</label>'
                    : '<div class="cr-mode-info">' + (TIMETABLE_ROLE === 'oqituvchi'
                        ? 'Taklifingiz avval kafedra mudiri, keyin registrator ofisi tasdiqlagach jadvalga tushadi.'
                        : TIMETABLE_DEPARTMENT_HEAD
                        ? (changeReqs.approval_required
                            ? 'Bu doskada o\'zgarishlaringiz registrator ofisi tasdiqlagandan keyin jadvalga tushadi. Bo\'sh darsga birinchi marta o\'qituvchi yoki xona biriktirish — darhol.'
                            : 'Hozir bu doskada o\'zgarishlar tasdiqsiz, darhol qo\'llanadi.')
                        : (changeReqs.approval_required
                            ? 'Bu doskada kafedra mudirlari o\'zgarishlari registrator ofisi tasdig\'idan o\'tadi (bo\'sh darsga birinchi marta o\'qituvchi yoki xona biriktirish bundan mustasno).'
                            : 'Bu doskada o\'zgarishlar tasdiqsiz, darhol qo\'llanadi.')) + '</div>';
                const tabs = '<div class="cr-tabs">' + [['pending', 'Kutilayotgan (' + pending.length + ')'], ['history', 'Tarix']]
                    .map(([k, l]) => '<button type="button" data-cr-tab="' + k + '" class="' + (changeReqFilter === k ? 'on' : '') + '">' + l + '</button>').join('') + '</div>';
                const rows = list.length ? list.map(r => {
                    const st = r.status === 'pending' && r.approval_stage
                        ? [r.approval_stage === 'department' ? 'Kafedra mudiri tasdig‘ida' : 'Registrator ofisi tasdig‘ida', '#b45309', '#fef3c7']
                        : CR_STATUS[r.status] || [r.status, '#334155', '#e2e8f0'];
                    const acts = r.status !== 'pending' ? '' : r.can_review
                        ? '<button type="button" class="cr-ok" data-cr-approve="' + r.id + '">Tasdiqlash</button><button type="button" class="cr-no" data-cr-reject="' + r.id + '">Rad etish</button>'
                        : r.mine ? '<button type="button" class="cr-no" data-cr-cancel="' + r.id + '">Bekor qilish</button>' : '';
                    const note = r.error ? '<div class="cr-err">' + esc(r.error) + '</div>' : (r.review_note && r.status !== 'pending' ? '<div class="cr-note">' + esc(r.review_note) + '</div>' : '');
                    return '<tr><td class="cr-when">' + esc(r.created_at || '') + '</td>' +
                        '<td><div class="cr-who">' + esc(r.kafedra_name || r.requester_role || '') + '</div><div class="cr-sub">' + esc(r.requester_name || '') +
                        (r.requester_role && r.kafedra_name ? ' · ' + esc(r.requester_role) : '') + '</div></td>' +
                        '<td>' + esc(r.summary) + (r.head_reviewer_name ? '<div class="cr-sub">Kafedra mudiri tasdiqladi: ' + esc(r.head_reviewer_name) + '</div>' : '') + (r.groups && r.groups.length ? '<div class="cr-sub">Guruhlar: ' + esc(r.groups.slice(0, 8).join(', ')) + (r.groups.length > 8 ? '…' : '') + '</div>' : '') + note + '</td>' +
                        '<td><span class="cr-st" style="color:' + st[1] + ';background:' + st[2] + '">' + st[0] + '</span>' +
                        (r.reviewer_name && r.status !== 'pending' ? '<div class="cr-sub">' + esc(r.reviewer_name) + (r.reviewed_at ? ' · ' + esc(r.reviewed_at) : '') + '</div>' : '') + '</td>' +
                        '<td class="cr-acts">' + acts + '</td></tr>';
                }).join('') : '<tr><td colspan="5" class="cr-empty">' + (history
                    ? (changeHistory.loading ? 'Yuklanmoqda...' : 'O\'zgarishlar tarixi bo\'sh.')
                    : 'Kutilayotgan so\'rovlar yo\'q.') + '</td></tr>';
                const more = history && changeHistory.more
                    ? '<div class="cr-more"><button type="button" data-cr-more' + (changeHistory.loading ? ' disabled' : '') + '>Yana yuklash</button></div>' : '';
                body.innerHTML = modeHtml + tabs + '<div class="cr-table-wrap"><table class="cr-table"><thead><tr><th>Sana</th><th>Kim</th><th>O\'zgarish</th><th>Holat</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>' + more;
                body.querySelectorAll('[data-cr-tab]').forEach(b => b.onclick = () => {
                    changeReqFilter = b.dataset.crTab;
                    renderChangeReqModal();
                    if (changeReqFilter === 'history') loadChangeHistory(true);
                });
                const moreBtn = body.querySelector('[data-cr-more]');
                if (moreBtn) moreBtn.onclick = () => loadChangeHistory(false);
                const chk = body.querySelector('#crModeChk');
                if (chk) chk.onchange = async () => {
                    try {
                        await api(BASE + '/boards/' + board.id + '/change-requests/mode', 'POST', { approval_required: chk.checked ? 1 : 0 });
                        ttToast(chk.checked ? 'Tasdiq rejimi yoqildi: kafedra o\'zgarishlari endi so\'rov bo\'lib keladi.' : 'Tasdiq rejimi o\'chirildi.', 'ok');
                        await loadChangeRequests();
                    } catch (e) { chk.checked = !chk.checked; alert('Xatolik: ' + e.message); }
                };
                const act = async (btn, url, body, okMsg) => {
                    btn.disabled = true;
                    try {
                        const result = await api(url, 'POST', body);
                        ttToast(result.message || okMsg, 'ok');
                        await loadChangeRequests();
                        if (changeReqFilter === 'history') await loadChangeHistory(true);
                        if (viewMode === 'cycle') await loadCyclePlan(); else await loadBoard(board.id);
                    } catch (e) { alert('Xatolik: ' + e.message); btn.disabled = false; await loadChangeRequests(); }
                };
                body.querySelectorAll('[data-cr-approve]').forEach(b => b.onclick = () =>
                    act(b, BASE + '/change-requests/' + b.dataset.crApprove + '/approve', {}, 'Tasdiqlandi — jadvalga qo\'llandi, tegishlilarga xabar yuborilmoqda.'));
                body.querySelectorAll('[data-cr-reject]').forEach(b => b.onclick = () => {
                    const note = prompt('Rad etish sababi (taklif yuboruvchiga boradi):', '');
                    if (note === null) return;
                    act(b, BASE + '/change-requests/' + b.dataset.crReject + '/reject', { note }, 'Rad etildi.');
                });
                body.querySelectorAll('[data-cr-cancel]').forEach(b => b.onclick = () => {
                    if (!confirm('So\'rov bekor qilinsinmi?')) return;
                    act(b, BASE + '/change-requests/' + b.dataset.crCancel + '/cancel', {}, 'So\'rov bekor qilindi.');
                });
            }
            if ($('changeReqBtn')) $('changeReqBtn').onclick = openChangeReqModal;

            // ===== Doska =====
            $('newBoardBtn').onclick = () => $('newBoardForm').classList.toggle('hidden');
            $('createBoardBtn').onclick = async function () {
                this.disabled = true;
                try {
                    const j = await api(BOARDS_STORE, 'POST', {
                        academic_year: $('nbYear').value, semester_parity: $('nbParity').value,
                        kind: $('nbKind').value, faculty_id: $('nbFaculty').value || '',
                        days: $('nbDays').value, pairs_per_day: $('nbPairs').value, weeks: $('nbWeeks').value,
                        subject_source: $('nbSource') ? $('nbSource').value : 'ishchi',
                        admin_only: $('nbAdminOnly') && $('nbAdminOnly').checked ? 1 : 0,
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
            // Kartochkalar: odatda SINXRONLASH (joylashuvlar saqlanadi, faqat noaktual kartalar
            // o'chadi, yangilari taqsimlanmagan holda qo'shiladi). Shift+bosish — to'liq qayta yaratish.
            let cardSyncTimer = null;
            let cardSyncElapsed = 0;
            function startCardSyncProgress(full) {
                const box = $('cardSyncProgress');
                cardSyncElapsed = 0;
                $('cardSyncProgressText').textContent = full
                    ? 'Barcha kartochkalar qayta yaratilmoqda…'
                    : 'Tasdiqlangan oqim va o‘quv reja fanlari sinxronlanmoqda…';
                $('cardSyncProgressElapsed').textContent = '0 soniya';
                box.classList.remove('hidden');
                if (cardSyncTimer) clearInterval(cardSyncTimer);
                cardSyncTimer = setInterval(() => {
                    cardSyncElapsed++;
                    $('cardSyncProgressElapsed').textContent = cardSyncElapsed + ' soniya';
                }, 1000);
            }
            function setCardSyncProgressText(text) {
                $('cardSyncProgressText').textContent = text;
            }
            function stopCardSyncProgress() {
                if (cardSyncTimer) clearInterval(cardSyncTimer);
                cardSyncTimer = null;
                $('cardSyncProgress').classList.add('hidden');
            }
            $('genBtn').title = 'Tasdiqlangan oqim bilan sinxronlash. Shift+bosish — hammasini qayta yaratish';
            $('genBtn').onclick = async function (ev) {
                if (!board) return;
                const full = !!(ev && ev.shiftKey) && cards.length > 0;
                if (full) {
                    if (!confirm('TO\'LIQ QAYTA YARATISH: mavjud kartochkalar (joylashuvlari bilan) o\'chirilib qayta yaratiladi. O\'qituvchi va xona biriktirishlari saqlanib qoladi. Davom etamizmi?')) return;
                } else if (cards.length && !confirm('Kartochkalar tasdiqlangan oqim bilan sinxronlanadi:\n'
                    + '• mavjud kartochkalar va ularning joylashuvi saqlanadi;\n'
                    + '• rejada endi yo\'q kartochkalar o\'chiriladi;\n'
                    + '• yangilari pastda (taqsimlanmagan) qo\'shiladi.\nDavom etamizmi?')) return;
                this.disabled = true;
                $('boardMsg').textContent = full ? 'Qayta yaratilmoqda...' : 'Sinxronlanmoqda...';
                startCardSyncProgress(full);
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/generate', 'POST', { mode: full ? 'full' : 'sync' });
                    const parts = [];
                    if (j.mode === 'sync') {
                        parts.push((j.kept || 0) + ' ta saqlandi', (j.created || 0) + ' ta yangi qo\'shildi',
                            (j.removed || 0) + ' ta o\'chirildi' + (j.removed_placed ? ' (' + j.removed_placed + ' tasi joylangan edi)' : ''));
                        if (j.updated) parts.push(j.updated + ' ta yangilandi');
                        if (j.renamed) parts.push(j.renamed + ' ta fan nomi yangilandi');
                        if (j.kept_placement) parts.push(j.kept_placement + ' ta qayta yaratilgan karta eski joyini oldi');
                        if (j.kept_placement_conflicts) parts.push(j.kept_placement_conflicts + ' ta eski joy band — karta joylanmagan qoldi');
                    } else parts.push((j.created || 0) + ' ta kartochka yaratildi');
                    if (j.restored_units) parts.push(j.restored_units + ' ta dars birligining o\'qituvchi/xona biriktirishi tiklandi');
                    if (j.lost_units) parts.push(j.lost_units + ' ta birlik endi rejada yo\'q');
                    const summary = parts.join(' · ');
                    setCardSyncProgressText('Doska yangi kartochkalar bilan qayta yuklanmoqda…');
                    await loadBoard(board.id);
                    $('boardMsg').textContent = summary;
                } catch (e) {
                    $('boardMsg').textContent = 'Sinxronlash xatosi: ' + e.message;
                    alert('Xatolik: ' + e.message);
                } finally {
                    stopCardSyncProgress();
                    this.disabled = false;
                }
            };
            // ===== Doska versiyalari (nusxalar): ro'yxat, qo'lda nusxa, joylashuvni tiklash (avval sinov) =====
            if ($('snapshotsBtn')) $('snapshotsBtn').onclick = async function () {
                if (!board) return;
                let box = $('snapBox');
                if (!box) {
                    box = document.createElement('div');
                    box.id = 'snapBox';
                    box.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.45);display:flex;align-items:flex-start;justify-content:center;padding:40px 16px;overflow:auto';
                    document.body.appendChild(box);
                }
                const reasonName = { daily: 'Kunlik', manual: 'Qo\'lda', before_generate: '«Kartochkalar» oldidan', before_unplace: '«Bo\'shatish» oldidan',
                    before_layer_copy: 'Qatlamga ko\'chirish oldidan', before_restore: 'Tiklash oldidan', imported_backup: 'Zaxira bazadan',
                    before_add_groups: 'Yangi guruhlar oldidan', before_separate_practice: 'Alohida amaliy oldidan', before_split_practice: 'Amaliyni bo\'lish oldidan',
                    before_split_variant: 'Bo\'lakni bo\'lish oldidan', before_merge_lectures: 'Ma\'ruzalarni birlashtirish oldidan' };
                const render = async (msg) => {
                    let list = [];
                    try { list = (await api(BASE + '/boards/' + board.id + '/snapshots')).snapshots || []; } catch (e) { msg = 'Xatolik: ' + e.message; }
                    box.innerHTML = '<div style="background:#fff;border-radius:12px;max-width:820px;width:100%;padding:16px;font-size:13px">'
                        + '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><b style="flex:1;font-size:15px">Doska versiyalari</b>'
                        + '<button type="button" id="snapTake" class="tt-ktr-weeks-btn">Hozir nusxa olish</button>'
                        + '<button type="button" id="snapClose" class="tt-ktr-weeks-btn">Yopish</button></div>'
                        + '<div style="color:#64748b;margin-bottom:8px">Har kecha va «Kartochkalar», «Bo\'shatish» kabi amallardan oldin avtomatik nusxa olinadi (30 kun saqlanadi). '
                        + '«Tiklash» — nusxadagi joylashuvni hozir <b>joylanmagan</b> kartalarga qaytaradi (karta qayta yaratilgan bo\'lsa ham, fan + guruh bo\'yicha); joylashganlariga tegilmaydi. Avval «Sinov».</div>'
                        + (msg ? '<pre style="white-space:pre-wrap;background:#f1f5f9;padding:8px;border-radius:8px;margin-bottom:8px">' + esc(msg) + '</pre>' : '')
                        + '<table style="width:100%;border-collapse:collapse"><thead><tr style="background:#f8fafc"><th style="text-align:left;padding:4px">Sana</th><th style="text-align:left">Turi</th><th>Kartalar</th><th>Joylashgan</th><th></th></tr></thead><tbody>'
                        + (list.length ? list.map(x => '<tr style="border-top:1px solid #e2e8f0"><td style="padding:4px">' + esc(x.created_at) + '</td><td>' + esc(reasonName[x.reason] || x.reason)
                            + (x.created_by_name ? ' <span style="color:#94a3b8">· ' + esc(x.created_by_name) + '</span>' : '') + '</td><td style="text-align:center">' + x.cards_count
                            + '</td><td style="text-align:center">' + x.placed_count + '</td><td style="text-align:right;white-space:nowrap">'
                            + '<button type="button" class="tt-ktr-weeks-btn" data-snap-dry="' + x.id + '">Sinov</button> '
                            + '<button type="button" class="tt-ktr-weeks-btn" data-snap-apply="' + x.id + '">Tiklash</button></td></tr>').join('')
                            : '<tr><td colspan="5" style="padding:8px;color:#94a3b8">Hali nusxa yo\'q</td></tr>')
                        + '</tbody></table></div>';
                    $('snapClose').onclick = () => box.remove();
                    $('snapTake').onclick = async () => { try { await api(BASE + '/boards/' + board.id + '/snapshots', 'POST', {}); render('Nusxa olindi.'); } catch (e) { render('Xatolik: ' + e.message); } };
                    const run = async (id, dry) => {
                        if (!dry && !confirm('Nusxadagi joylashuv hozir joylanmagan kartalarga yoziladi (oldin hozirgi holatdan nusxa olinadi). Davom etamizmi?')) return;
                        render('Hisoblanmoqda...');
                        try {
                            const r = await api(BASE + '/boards/' + board.id + '/snapshots/' + id + '/restore', 'POST', { dry_run: dry ? 1 : 0 });
                            const txt = (r.dry_run ? 'SINOV (yozilmadi): ' : 'YOZILDI: ') + r.cards + ' karta (' + r.units + ' dars birligi) — ichki ' + r.internal + ', tashqi ' + r.external
                                + ', haftalik ko\'chirish ' + r.week_moves + ', o\'qituvchi/xona ' + r.teacher_room + '\nNusxada topilmadi: ' + r.no_source + ' · nusxada ham joylanmagan: ' + r.source_unplaced
                                + (r.conflicts ? ' · joyi band (to\'qnashuv) — yozilmadi: ' + r.conflicts : '')
                                + (r.examples && r.examples.length ? '\n\n' + r.examples.join('\n') : '')
                                + (r.conflict_examples && r.conflict_examples.length ? '\n\nTo\'qnashuvlar:\n' + r.conflict_examples.join('\n') : '');
                            await render(txt);
                            if (!r.dry_run) await loadBoard(board.id);
                        } catch (e) { render('Xatolik: ' + e.message); }
                    };
                    box.querySelectorAll('[data-snap-dry]').forEach(b => b.onclick = () => run(b.dataset.snapDry, true));
                    box.querySelectorAll('[data-snap-apply]').forEach(b => b.onclick = () => run(b.dataset.snapApply, false));
                };
                render('');
            };
            // Fan nomlarini ishchi rejadagi joriy nomga yangilash (qayta yaratmasdan)
            $('refreshNamesBtn').onclick = async function () {
                if (!board) return;
                this.disabled = true; $('boardMsg').textContent = 'Fan nomlari yangilanmoqda...';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/refresh-names', 'POST', {});
                    $('boardMsg').textContent = (j.updated || 0) + ' ta kartochka nomi yangilandi'
                        + ' · ' + (j.kafedra_updated || 0) + ' ta kartochka kafedrasi yangilandi';
                    await loadBoard(board.id);
                } catch (e) { $('boardMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };
            // Doskani sahifadan chiqmasdan qayta yuklash (F5 o'rniga): tanlovlar
            // (fakultet/yo'nalish/kurs, dars turi, hafta, kesim) va skroll saqlanadi.
            async function reloadBoard() {
                if (!board) return;
                const btn = $('reloadBoardBtn'), wrap = $('gridWrap');
                const scroll = { top: wrap.scrollTop, left: wrap.scrollLeft };
                btn.disabled = true; btn.classList.add('is-loading');
                $('boardMsg').textContent = 'Yangilanmoqda...';
                try {
                    await loadBoard(board.id);
                    wrap.scrollTop = scroll.top; wrap.scrollLeft = scroll.left;
                    if (cards.length) {
                        $('boardMsg').textContent = 'Yangilandi ' +
                            new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                } catch (e) { $('boardMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                btn.disabled = false; btn.classList.remove('is-loading');
            }
            $('reloadBoardBtn').onclick = reloadBoard;

            // aSc uslubidagi boshqaruv tugmalari (doska tanlash qatorida) — bitta guruh sifatida ko'rsatish/yashirish

            function toggleAscToolbar(show) {
                document.querySelectorAll('[data-asc-toolbar]').forEach(el => {
                    const allowedForRole = !TIMETABLE_ASSIGNMENT_ONLY
                        || el.id === 'assignBtn'
                        || (TIMETABLE_CAN_USE_MANAGER && el.id === 'managerBtn');
                    el.classList.toggle('hidden', !show || !allowedForRole);
                });
                applyTimetableAccess();
            }

            // ===== Holatni URLda saqlash (F5 / havola) =====
            // ?board=ID&fac=..&dir=..&crs=..&type=..&week=..&view=..  (fac/dir/crs takrorlanishi mumkin)
            function parseViewFromUrl() {
                const p = new URLSearchParams(location.search);
                const id = p.get('board');
                if (!id) return null;
                // Hafta — faqat musbat butun son (week=-1, 1.5, abc → shablon); yuqori
                // chegara (panjaraning hafta soni) fillWeekSel'da tekshiriladi.
                const week = Number(p.get('week'));
                return {
                    board: id,
                    fac: p.getAll('fac'),
                    dir: p.getAll('dir'),
                    crs: p.getAll('crs').map(Number).filter(Number.isInteger),
                    type: p.get('type') || 'all',
                    week: Number.isInteger(week) && week > 0 ? week : 0,
                    view: p.get('view') || 'group',
                };
            }
            // Joriy holatni URLga yozamiz (tarixga yangi yozuv qo'shmasdan). Faqat
            // o'zgargan bo'lsa yoziladi — renderAll'dan tez-tez chaqirilsa ham arzon.
            function syncUrl() {
                const p = new URLSearchParams();
                if (board) {
                    p.set('board', board.id);
                    if (cards.length) {
                        selectedFaculties.forEach(f => p.append('fac', f));
                        selectedDirs.forEach(d => p.append('dir', d));
                        selectedCourses.forEach(c => p.append('crs', c));
                        if (typeFilter !== 'all') p.set('type', typeFilter);
                        if (curWeek) p.set('week', curWeek);
                        if (viewMode !== 'group') p.set('view', viewMode);
                    }
                }
                const qs = p.toString();
                const next = location.pathname + (qs ? '?' + qs : '') + location.hash;
                if (next !== location.pathname + location.search + location.hash) {
                    history.replaceState(history.state, '', next);
                }
            }
            // URLdan o'qilgan tanlovlarni doska yuklanganda qo'llaymiz (faqat o'sha doskaga).
            function applyPendingView() {
                const v = pendingView;
                pendingView = null;
                if (!v || !board || String(v.board) !== String(board.id)) return;
                selectedFaculties = new Set(v.fac);
                selectedDirs = new Set(v.dir);
                selectedCourses = new Set(v.crs);
                if (['all', 'lecture', 'practice'].includes(v.type)) setTypeFilter(v.type);
                curWeek = v.week;
                if ([...$('viewMode').options].some(o => o.value === v.view)) {
                    viewMode = v.view;
                    $('viewMode').value = v.view;
                }
            }
            function setTypeFilter(t) {
                typeFilter = t;
                document.querySelectorAll('.tt-type').forEach(x => x.classList.toggle('active', x.dataset.type === t));
            }

            function hideBoard() {
                board = null;
                $('genBtn').classList.add('hidden'); $('delBoardBtn').classList.add('hidden');
                $('refreshNamesBtn').classList.add('hidden'); $('reloadBoardBtn').classList.add('hidden');
                if ($('snapshotsBtn')) $('snapshotsBtn').classList.add('hidden');
                toggleAscToolbar(false);
                $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
                syncUrl();
            }

            async function loadBoard(id) {
                // Boshqa doskaga o'tayotgan bo'lsak — eski doskaga oid holatni tozalaymiz
                const switching = !board || String(board.id) !== String(id);
                // URLga noyob parametr — har qanday kesh (brauzer/proxy) eski kartalarni
                // qaytarmasin (yangi kurs/kartalar darrov ko'rinsin).
                const j = await api(BASE + '/boards/' + id + '/data?_=' + Date.now());
                board = j.board; allCards = j.cards || [];
                applyWeeklyLayer(allCards);
                groupStudents = j.group_students || {};
                myKafedras = Array.isArray(j.my_kafedras) ? j.my_kafedras : [];
                mySubjectCardIds = new Set((j.my_subject_card_ids || []).map(Number));
                cards = scopeCards(allCards);
                missingGroups = j.missing_groups || [];
                buildSubjectColors();
                loadSubjectColorOverrides();
                grids = {};
                (j.grids || []).forEach(g => { grids[gridKey(g.faculty_name, g.specialty_name, g.course, g.curriculum_hemis_id)] = g; });
                // Hafta bo'yicha istisnolar
                overrides = {};
                (j.overrides || []).forEach(o => {
                    overrides[o.card_id + '|' + o.week] = {
                        day: o.day,
                        pair: o.pair,
                        cancelled: o.cancelled,
                        auditorium_code: o.auditorium_code || null,
                        auditorium_name: o.auditorium_name || null,
                        auditorium_volume: o.auditorium_volume || null,
                    };
                });
                // Fan rejimi (hafta almashinuvi / sikl): "spec|course|subject" => {mode, rotation_group, occurrences, cycle_days}
                subjectSettings = {};
                (j.subject_settings || []).forEach(s => { subjectSettings[subjModeKey(s.specialty_name, s.course, s.subject_name)] = s; });
                // Eski kartaga ishora qiluvchi tanlovlarni bekor qilamiz (eski doskaga yozib
                // yubormaslik uchun); doska almashsa yo'nalish tanlovini ham qayta tanlaymiz
                selected = null; modalCard = null;
                $('cardModal').classList.add('hidden');
                if (switching) {
                    curSpec = null; selectedFaculties.clear(); selectedDirs.clear(); selectedCourses.clear();
                    // Sikl sanasi/bayramlari doskaga xos — almashganda tozalaymiz (boshqa
                    // doska qiymatini uning ustiga yozib yubormaslik uchun).
                    if ($('cycleStart')) $('cycleStart').value = '';
                    cyclePlanData = null; cycleHolidays = []; cyclePairsShown = null;
                    if ($('cycleHolBar')) $('cycleHolBar').classList.add('hidden');
                }
                applyPendingView();
                $('boardSel').value = String(board.id);
                $('genBtn').classList.remove('hidden');
                if ($('changeReqBtn')) { $('changeReqBtn').classList.remove('hidden'); loadChangeRequests(); }
                $('refreshNamesBtn').classList.remove('hidden');
                $('reloadBoardBtn').classList.remove('hidden');
                if ($('snapshotsBtn')) $('snapshotsBtn').classList.remove('hidden');
                $('delBoardBtn').classList.remove('hidden');
                toggleAscToolbar(true);
                buildSpecList();
                if (!cards.length) {
                    $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
                    $('boardMsg').textContent = 'Kartochkalar hali yaratilmagan — "Kartochkalarni yaratish"ni bosing.';
                    syncUrl();
                    return;
                }
                $('boardMsg').textContent = '';
                $('specBar').classList.remove('hidden'); $('mainArea').classList.remove('hidden');
                if ((!curSpec || !specList.find(s => s.key === curSpec.key)) && specList.length) curSpec = specList[0];
                fillSpecControls();
                fillGridInputs();
                renderAll();
                // Qoidalar sonini (badge) fonda yangilaymiz — doska yuklanishini kutdirmaydi
                loadRules().catch(() => {});
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

            // ===== Ko'p tanlovli tanlov: Fakultet → Yo'nalish → Kurs (dropdown) =====
            // Uch o'lcham ham checkbox dropdown'idan tanlanadi (bir nechtasini
            // birga ko'rsatish mumkin). selectedFaculties/selectedDirs/selectedCourses
            // to'plamlari ko'rinishni (specCards) va bo'shatish qamrovini boshqaradi.
            const facLabel = f => f || '— (fakultetsiz)';
            const facultiesList = () => [...new Set(specList.map(s => s.faculty))].sort((a, b) => a.localeCompare(b, 'uz'));
            // Tanlangan fakultet(lar)ga tegishli yo'nalishlar; fakultet(lar)+yo'nalish(lar)ga tegishli kurslar.
            const availDirs = () => [...new Set(specList.filter(s => selectedFaculties.has(s.faculty)).map(s => s.specialty_name))]
                .sort((a, b) => a.localeCompare(b, 'uz'));
            const availCourses = () => [...new Set(specList
                .filter(s => selectedFaculties.has(s.faculty) && selectedDirs.has(s.specialty_name)).map(s => s.course))]
                .sort((a, b) => a - b);

            // Asosiy (primary) curSpec — panjara sozlamalari paneli va sozlama saqlash uchun.
            function setPrimarySpec() {
                const fac = [...selectedFaculties].sort((a, b) => a.localeCompare(b, 'uz'))[0];
                const dir = [...selectedDirs].sort((a, b) => a.localeCompare(b, 'uz'))[0];
                const crs = [...selectedCourses].sort((a, b) => a - b)[0];
                curSpec = specList.find(s => s.faculty === fac && s.specialty_name === dir && s.course === crs)
                    || specList.find(s => s.faculty === fac && s.specialty_name === dir)
                    || specList.find(s => s.faculty === fac)
                    || specList[0] || null;
            }
            // Tanlovlarni mavjud variantlarga moslaymiz (kamida bittasi tanlangan bo'ladi).
            // seed=true bo'lsa — curSpec (yoki birinchi variant)dan boshlab to'ldiramiz.
            function reconcileSelection(seed) {
                const facs = facultiesList();
                if (seed) selectedFaculties = new Set(curSpec && facs.includes(curSpec.faculty) ? [curSpec.faculty] : (facs.length ? [facs[0]] : []));
                selectedFaculties = new Set([...selectedFaculties].filter(f => facs.includes(f)));
                if (!selectedFaculties.size && facs.length) selectedFaculties.add(facs[0]);

                const dirs = availDirs();
                if (seed) selectedDirs = new Set(curSpec && dirs.includes(curSpec.specialty_name) ? [curSpec.specialty_name] : (dirs.length ? [dirs[0]] : []));
                selectedDirs = new Set([...selectedDirs].filter(d => dirs.includes(d)));
                if (!selectedDirs.size && dirs.length) selectedDirs.add(dirs[0]);

                const courses = availCourses();
                if (seed) selectedCourses = new Set(curSpec && courses.includes(curSpec.course) ? [curSpec.course] : (courses.length ? [courses[0]] : []));
                selectedCourses = new Set([...selectedCourses].filter(c => courses.includes(c)));
                if (!selectedCourses.size && courses.length) selectedCourses.add(courses[0]);

                setPrimarySpec();
            }

            // Bitta dropdown'ni chizadi: menyu ichiga checkboxlar + tugma yozuvi (xulosa).
            function renderDD(id, title, items) {
                const menu = $(id + 'Menu');
                const chosen = items.filter(it => it.checked);
                const tools = items.length > 1
                    ? '<div class="tt-dd-tools"><button type="button" data-dd-all="' + id + '">Barchasi</button>' +
                      '<button type="button" data-dd-one="' + id + '">Faqat asosiy</button></div>' : '';
                menu.innerHTML = tools + (items.map(it =>
                    '<label class="tt-dd-item"><input type="checkbox" value="' + esc(it.v) + '"' + (it.checked ? ' checked' : '') + '>' +
                    esc(it.label) + '</label>').join('') || '<div class="tt-dd-empty">—</div>');
                let sum;
                if (!items.length) sum = '—';
                else if (chosen.length === items.length && items.length > 1) sum = 'Barchasi (' + items.length + ')';
                else if (chosen.length <= 1) sum = chosen.length ? chosen[0].label : '—';
                else sum = chosen.length + ' ta tanlandi';
                $(id + 'Btn').innerHTML = '<span class="tt-dd-ttl">' + title + ':</span> ' +
                    '<span class="tt-dd-sum">' + esc(sum) + '</span> <span class="tt-dd-caret">▾</span>';
            }
            // Uchala dropdown'ni joriy tanlov holatiga qarab qayta chizadi.
            function fillSelectors() {
                renderDD('fac', 'Fakultet', facultiesList().map(f => ({ v: f, label: facLabel(f), checked: selectedFaculties.has(f) })));
                renderDD('dir', 'Yo\'nalish', availDirs().map(d => ({ v: d, label: d, checked: selectedDirs.has(d) })));
                renderDD('crs', 'Kurs', availCourses().map(c => ({ v: c, label: c + '-kurs', checked: selectedCourses.has(c) })));
            }
            // Boshlang'ich to'ldirish (loadBoard'dan). Tanlov bo'sh bo'lsa (yangi doska)
            // curSpec'dan urug'lantiramiz; aks holda mavjud tanlovni saqlab, mavjud
            // variantlarga moslaymiz (bo'shatishdan keyingi qayta yuklashda
            // ko'p tanlov yo'qolib qolmasin).
            function fillSpecControls() {
                const empty = !selectedFaculties.size && !selectedDirs.size && !selectedCourses.size;
                reconcileSelection(empty);
                fillSelectors();
            }

            // Checkbox o'zgarganda: tegishli to'plamni yangilaymiz, tanlovni moslaymiz, qayta chizamiz.
            function onDDChange(which, target) {
                const set = which === 'fac' ? selectedFaculties : which === 'dir' ? selectedDirs : selectedCourses;
                const val = which === 'crs' ? +target.value : target.value;
                if (target.checked) set.add(val); else set.delete(val);
                if (!set.size) { set.add(val); target.checked = true; }   // kamida bitta
                reconcileSelection(false);
                fillSelectors();
                selected = null; fillGridInputs(); renderAll();
            }
            [['fac', 'facMenu'], ['dir', 'dirMenu'], ['crs', 'crsMenu']].forEach(([which, menuId]) => {
                $(menuId).addEventListener('change', ev => {
                    if (ev.target && ev.target.matches('input[type=checkbox]')) onDDChange(which, ev.target);
                });
                // "Barchasi" / "Faqat asosiy" tugmalari
                $(menuId).addEventListener('click', ev => {
                    const set = which === 'fac' ? selectedFaculties : which === 'dir' ? selectedDirs : selectedCourses;
                    const items = which === 'fac' ? facultiesList() : which === 'dir' ? availDirs() : availCourses();
                    if (ev.target.dataset.ddAll !== undefined && ev.target.dataset.ddAll) {
                        items.forEach(v => set.add(v));
                    } else if (ev.target.dataset.ddOne !== undefined && ev.target.dataset.ddOne) {
                        const keep = [...set].sort((a, b) => which === 'crs' ? a - b : String(a).localeCompare(String(b), 'uz'))[0];
                        set.clear(); if (keep != null) set.add(keep);
                    } else { return; }
                    reconcileSelection(false);
                    fillSelectors();
                    selected = null; fillGridInputs(); renderAll();
                });
            });
            // Tugmani bosish — menyuni ochish/yopish; tashqariga bosilsa yopiladi.
            document.addEventListener('click', ev => {
                const btn = ev.target.closest('.tt-dd-btn');
                const insideMenu = ev.target.closest('.tt-dd-menu');
                document.querySelectorAll('.tt-dd').forEach(dd => {
                    const menu = dd.querySelector('.tt-dd-menu');
                    if (btn && dd.contains(btn)) menu.classList.toggle('open');
                    else if (menu !== insideMenu) menu.classList.remove('open');
                });
            });

            // ===== Panjara sozlamasi (yo'nalish+kurs bo'yicha) =====
            function curGrid() {
                const visible = cards.filter(c => selectedFaculties.has(c.faculty_name || '') && selectedDirs.has(c.specialty_name) && selectedCourses.has(c.course));
                const calendars = visible.map(gridForCard);
                const base = calendars[0] || { days: board.days, pairs_per_day: board.pairs_per_day, weeks: board.weeks };
                return Object.assign({}, base, { weeks: Math.max(+base.weeks || 1, ...calendars.map(g => +g.weeks || 1)) });
            }
            function fillGridInputs() {
                // Panjara qiymatlari endi Sozlamalar modalidan boshqariladi.
                fillWeekSel();
            }

            // ===== Hafta tanlash (individual haftalar) =====
            // Semestr boshlanishi (doska sozlamasi / sikl jadvalidagi sana) — hafta sanalari uchun.
            function semesterStartDate() {
                const v = (board && board.settings && board.settings.semester_start) || ($('cycleStart') && $('cycleStart').value) || '';
                const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v);
                if (!m) return null;
                const d = new Date(+m[1], +m[2] - 1, +m[3]);
                d.setDate(d.getDate() - ((d.getDay() + 6) % 7));   // o'sha haftaning dushanbasi
                return d;
            }
            // Yo'nalish+kurs kalendari: panjaraning o'z boshlanishi (bo'lmasa doska semestr boshi),
            // ta'til oralig'iga (settings.weekly_breaks) tushgan haftalar o'quv haftasi hisoblanmaydi.
            function gridStartDate(g) {
                const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String((g && g.start_date) || ''));
                if (!m) return semesterStartDate();
                const d = new Date(+m[1], +m[2] - 1, +m[3]);
                d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
                return d;
            }
            function weekBreaks(g) {
                const toD = v => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v || '')); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; };
                return ((g && g.weekly_breaks) ?? ((board && board.settings && board.settings.weekly_breaks) || []))
                    .map(b => [toD(b && b.from), toD(b && b.to)]).filter(b => b[0] && b[1]);
            }
            // n-o'quv haftasining dushanbasi (tanlangan yo'nalish+kurs kalendari bo'yicha)
            function weekMonday(n, g) {
                const st = gridStartDate(g || curGrid());
                if (!st) return null;
                const br = weekBreaks(g || curGrid());
                const d = new Date(st);
                for (let k = 0, guard = 0; guard < 200; guard++, d.setDate(d.getDate() + 7)) {
                    if (br.some(([f, t]) => d >= f && d <= t)) continue;
                    if (++k >= n) return d;
                }
                return d;
            }
            function weekDates(n) {
                const a = weekMonday(n);
                if (!a) return '';
                const b = new Date(a); b.setDate(b.getDate() + Math.max(0, (board.days || 6) - 1));
                const f = d => String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0');
                return f(a) + '–' + f(b);
            }
            // Bugun semestrning nechanchi haftasi (chegaradan chiqsa — 1 yoki oxirgi hafta).
            function currentWeekNo(w) {
                if (!gridStartDate(curGrid())) return 1;
                const today = new Date(); today.setHours(0, 0, 0, 0);
                for (let i = w; i >= 1; i--) { const m = weekMonday(i); if (m && m <= today) return i; }
                return 1;
            }
            // Ichki (haqiqiy) jadval faqat haftama-hafta ko'riladi — "shablon" yo'q.
            const weeklyOnly = () => typeof weeklyLayer !== 'undefined' && weeklyLayer === 'internal' && viewMode !== 'cycle';
            function fillWeekSel() {
                const w = +curGrid().weeks || +board.weeks || 15;
                // curWeek faqat 0 (shablon) yoki 1..w oralig'idagi butun son bo'lishi mumkin —
                // aks holda (URLdan kelgan noto'g'ri qiymat, qisqargan panjara) shablonga qaytamiz,
                // yo'qsa select'da mos variant bo'lmaydi va hafta istisnosi 422 bilan qaytadi.
                if (!Number.isInteger(curWeek) || curWeek < 0 || curWeek > w) curWeek = 0;
                const only = weeklyOnly();
                const now = currentWeekNo(w);
                if (only && !curWeek) curWeek = now;   // ichki jadval — joriy hafta bilan ochiladi
                let opts = only ? '' : '<option value="0">Barcha haftalar (shablon)</option>';
                for (let i = 1; i <= w; i++) {
                    const dates = weekDates(i);
                    opts += '<option value="' + i + '">' + i + '-hafta' + (dates ? ' (' + dates + ')' : '') + (i === now && semesterStartDate() ? ' • joriy' : '') + '</option>';
                }
                $('weekSel').innerHTML = opts;
                $('weekSel').value = String(curWeek);
                if ($('weekPrev')) $('weekPrev').disabled = curWeek <= (only ? 1 : 0);
                if ($('weekNext')) $('weekNext').disabled = curWeek >= w;
            }
            function stepWeek(delta) {
                const w = +curGrid().weeks || +board.weeks || 15;
                const next = Math.min(w, Math.max(weeklyOnly() ? 1 : 0, (curWeek || 0) + delta));
                if (next === curWeek) return;
                $('weekSel').value = String(next);
                $('weekSel').onchange.call($('weekSel'));
            }
            if ($('weekPrev')) $('weekPrev').onclick = () => stepWeek(-1);
            if ($('weekNext')) $('weekNext').onclick = () => stepWeek(1);
            // Haftalik fanlar: har hafta qaysi kartochka o'tilishi — KTR'dan (ma'ruza haftalari, amaliy soati).
            if ($('ktrWeeksBtn')) $('ktrWeeksBtn').onclick = async function () {
                if (!board) return;
                const body = scopeBody();
                this.disabled = true;
                try {
                    const dry = await api(BASE + '/boards/' + board.id + '/ktr-weeks', 'POST', Object.assign({ dry_run: 1 }, body));
                    const msg = 'Qamrov: ' + scopeLabelText() + '\n\n' +
                        dry.cards + ' ta kartochka KTR bo\'yicha haftalarga taqsimlanadi\n' +
                        '(' + dry.weeks_on + ' ta hafta yoqiladi, ' + dry.weeks_off + ' ta o\'chiriladi).' +
                        (dry.no_ktr.length ? '\n\nKTR topilmagan fanlar (o\'zgarmaydi): ' + dry.no_ktr.slice(0, 8).join('; ') + (dry.no_ktr.length > 8 ? '…' : '') : '') +
                        (dry.mismatch_count ? '\n\nDiqqat: ' + dry.mismatch_count + ' ta haftada KTR soatiga kartochkalar yetmaydi (masalan: ' + dry.mismatch[0] + ').' : '') +
                        '\n\nDavom etamizmi?';
                    if (!confirm(msg)) return;
                    const res = await api(BASE + '/boards/' + board.id + '/ktr-weeks', 'POST', body);
                    $('boardMsg').textContent = 'KTR bo\'yicha: ' + res.cards + ' ta kartochka haftalari yangilandi';
                    await loadBoard(board.id);
                } catch (e) { alert('Xatolik: ' + e.message); }
                finally { this.disabled = false; }
            };
            // ===== Registrator: HEMIS bilan solishtirish (faqat o'qiydi, taklif beradi) =====
            let hemisSyncLast = null;
            let hemisSyncUntil = null;
            let hemisSyncPeriod = null;  // {from, to} — hisob davri (haftalar); null — 1-hafta…joriy hafta
            let hemisSyncFilter = { onlyDiff: true, periodMatch: true, finished: false, ops: { create: true, update: true, delete: true, blocked: true }, blk: { room: true, teacher: true, other: true }, subjects: null, groups: null };   // subjects / groups: null — barcha fanlar / guruhlar   // null — joriy hafta oxirigacha (sukut), 0 — semestr oxirigacha
            function hemisSyncModal(html) {
                let m = $('hemisSyncModal');
                if (!m) {
                    m = document.createElement('div');
                    m.id = 'hemisSyncModal';
                    m.className = 'fixed inset-0 z-[9999] bg-black/40 flex items-start justify-center p-4 overflow-auto';
                    m.onclick = e => { if (e.target === m) m.remove(); };
                    document.body.appendChild(m);
                }
                m.innerHTML = '<div class="bg-white rounded-xl shadow-xl w-full max-w-6xl p-4 text-[12px]">' + html + '</div>';
                return m;
            }
            // Ochiladigan ro'yxatlar (Fanlar / Guruhlar): tashqariga bosilsa yoki Esc bosilsa — yopiladi.
            // Ro'yxat ichidagi tugma oynani qayta chizsa (bosilgan element DOM'dan chiqadi) — yopilmaydi: apply() uni qayta ochadi.
            document.addEventListener('click', e => {
                if (!e.target.isConnected) return;
                document.querySelectorAll('#hemisSyncModal details.hs-pick[open]').forEach(d => { if (!d.contains(e.target)) d.open = false; });
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') document.querySelectorAll('#hemisSyncModal details.hs-pick[open]').forEach(d => { d.open = false; });
            });
            const hsEsc = v => String(v == null ? '' : v).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
            const hsSigned = n => (n > 0 ? '+' : '') + n;
            function hsUnitMatches(u) {
                if ((u.actions || []).length || u.actions_later || (u.notes || []).length) return false;
                for (const k of ['hours_diff', 'count_diff']) if (Object.values(u.before[k] || {}).some(v => +v)) return false;
                const p = u.period;
                if (p) {
                    if (Object.keys(u.code_names).some(c => (+p.ktr_hours[c] || 0) !== (+p.hemis_hours[c] || 0))) return false;
                    if (['L', 'A'].some(x => Object.values(u.codes).includes(x) && (+p.ktr_count[x] || 0) !== (+p.hemis_count[x] || 0))) return false;
                }
                return true;
            }
            // Qismlar: ma'ruza va "amaliy qism" (ma'ruzadan boshqa hamma tur) — har biri jurnalda kunma-kun ustun.
            const HS_PART = { L: 'Ma\'ruza', A: 'Amaliy qism' };
            const HS_A_TITLE = ' title="Amaliy qism — ma\'ruzadan boshqa barcha turlar: amaliy, seminar, laboratoriya, klinik mashg\'ulot. Bir kunda qaysi biri bo\'lsa ham — jurnalda bitta ustun."';
            // Fan filtri: null — hammasi; aks holda tanlangan fan nomlari.
            const hsSubjOk = u => !hemisSyncFilter.subjects || hemisSyncFilter.subjects.includes(u.subject_name);
            // Guruh filtri: null — hammasi; aks holda tanlangan guruhlar (rep.groups[].group). Fan filtri bilan birga (ikkalasi ham).
            const hsGroupOk = g => !hemisSyncFilter.groups || hemisSyncFilter.groups.includes(g.group);
            const hsGroupNames = rep => [...new Set(rep.groups.map(g => g.group))].sort((a, b) => a.localeCompare(b));
            // Filtr panelidagi ochiladigan ro'yxat: checkbox'lar, tepada "Hammasi / Hech biri".
            const hsPickBox = (key, label, all, picked) => {
                const sel = picked ? picked.filter(x => all.includes(x)) : all;
                return '<details id="hs' + key + 'Box" class="hs-pick relative"><summary class="cursor-pointer border rounded px-2 py-0.5 bg-white">' + label + ': ' +
                    (sel.length === all.length ? 'hammasi' : sel.length + ' / ' + all.length) + '</summary>' +
                    '<div class="absolute z-10 mt-1 bg-white border rounded shadow p-2 max-h-72 overflow-auto w-96">' +
                    '<div class="mb-1"><button type="button" id="hs' + key + 'All" class="underline mr-2">Hammasi</button><button type="button" id="hs' + key + 'None" class="underline">Hech biri</button></div>' +
                    all.map((n, i) => '<label class="flex items-center gap-1"><input type="checkbox" class="hs-pick-' + key + '" data-i="' + i + '"' + (sel.includes(n) ? ' checked' : '') + '> ' + hsEsc(n) + '</label>').join('') +
                    '</div></details>';
            };
            // Fan filtri ro'yxati: solishtirilgan va o'tkazilgan (skipped) fanlar — o'tkazilganning sababini ham tanlab ko'rish mumkin.
            const hsSubjectNames = rep => [...new Set(rep.groups.flatMap(g => [...g.units, ...(g.skipped || [])].map(x => x.subject_name)))]
                .sort((a, b) => a.localeCompare(b));
            // Joylanmagan kartalar (HEMIS'ga yozilmaydi): guruh filtri bo'yicha; bitta karta bir nechta guruhda bo'lsa — bir marta.
            const hsUnplaced = rep => {
                const u = rep.unplaced || {};
                const weekly = ((u.weekly && u.weekly.items) || []).filter(hsGroupOk);
                const cycle = ((u.cycle && u.cycle.items) || []).filter(hsGroupOk);
                const cycleCards = new Set(cycle.map(i => i.key)).size;
                return { weekly, cycle, cycleCards, cardWeeks: new Set(weekly.flatMap(i => i.weeks.map(w => i.card_id + '|' + w))).size,
                    cards: new Set(weekly.map(i => i.card_id)).size + cycleCards };
            };
            let hsUnplacedOpen = false;   // "Ro'yxat" ochiqmi (qayta chizishda saqlanadi)
            const hsWeekRanges = ws => ws.reduce((acc, w) => {
                const last = acc[acc.length - 1];
                if (last && w === last[1] + 1) last[1] = w; else acc.push([w, w]);
                return acc;
            }, []).map(([a, b]) => a === b ? String(a) : a + '–' + b).join(', ');
            // Guruh bo'yicha ro'yxat (natija oynasidagi banner va oldindan ogohlantirish oynasi uchun bir xil).
            function hsUnplacedListHtml(un) {
                const TYPE = { lecture: 'ma\'ruza', practice: 'amaliy' };
                const byGroup = {};
                un.weekly.forEach(i => (byGroup[i.group] = byGroup[i.group] || []).push([i.subject, hsEsc(i.subject) + ' (' + TYPE[i.type] + '): ' + hsWeekRanges(i.weeks) + '-hafta']));
                un.cycle.forEach(i => (byGroup[i.group] = byGroup[i.group] || []).push([i.subject, hsEsc(i.subject) + ' (' + TYPE[i.type] + ', sikl): ' + i.days + ' kun']));
                return Object.keys(byGroup).sort((a, b) => a.localeCompare(b)).map(g => '<div><b>' + hsEsc(g) + '</b>: ' +
                    byGroup[g].sort((a, b) => a[0].localeCompare(b[0])).map(x => x[1]).join('; ') + '</div>').join('');
            }
            function hsUnplacedBanner(rep) {
                const un = hsUnplaced(rep);
                if (!un.cardWeeks && !un.cycleCards) return '';
                const list = hsUnplacedListHtml(un);
                return '<div id="hsUnplacedBanner" class="mb-2 p-2 rounded border border-red-400 bg-red-50 text-red-800">⚠ Hali joylanmagan kartalar bor: ' +
                    [un.cardWeeks ? 'haftalik — ' + un.cardWeeks + ' ta karta-hafta' : '', un.cycleCards ? 'sikl — ' + un.cycleCards + ' ta karta' : ''].filter(Boolean).join(', ') +
                    '. Ular HEMIS\'ga yozilmaydi. <button type="button" id="hsUnplacedToggle" class="underline ml-1">Ro\'yxat</button>' +
                    '<div id="hsUnplacedList" class="mt-1' + (hsUnplacedOpen ? '' : ' hidden') + '">' + list + '</div></div>';
            }
            // "Bu davrda mos": tanlangan davrgacha amali yo'q, keyingi haftalarda bor ("Bu davrda moslar" belgisi olinsa yashiriladi).
            const hsPeriodMatch = u => !u.actions.length && (u.actions_later || 0) > 0;
            // Amal qatori: qisqa xulosa (HEMIS → LMS), to'siq belgilari (xona / o'qituvchi band — alohida rangda),
            // pastda batafsil: kim, qachon, qaysi guruh va fan band qilgan; so'ng sabab.
            const HS_BLOCK = {
                room: { label: 'Xona band', cls: 'bg-orange-100 text-orange-800 border-orange-300' },
                teacher: { label: 'O\'qituvchi band', cls: 'bg-purple-100 text-purple-800 border-purple-300' },
            };
            const hsChangeShort = c => {
                const m = /^([^:]+):\s*(.*?)\s*→\s*(.*)$/.exec(String(c));
                if (!m) return hsEsc(c);
                const what = m[1].charAt(0).toUpperCase() + m[1].slice(1);
                return '<b>' + hsEsc(what) + ':</b> HEMIS\'da <span class="line-through">' + hsEsc(m[2] || '—') + '</span> → LMS\'da <b>' + hsEsc(m[3]) + '</b>';
            };
            function hsActionHtml(a, u) {
                const opName = { create: 'Qo\'shish', update: 'O\'zgartirish', delete: 'O\'chirish' };
                const opCls = { create: 'text-emerald-700', update: 'text-amber-700', delete: 'text-red-700' };
                const blocks = a.blocks || [];
                // Tuzilgan tafsiloti yo'q to'siqlar (para vaqti yo'q, o'qituvchi/xona topilmadi) — alohida, matni bilan.
                const covered = new Set(blocks.map(b => b.text));
                const other = (a.blocked || []).filter(m => !covered.has(m));
                const types = [...new Set(blocks.map(b => b.type))];
                const chips = types.map(t => '<span class="ml-1 px-1.5 rounded border text-[10px] font-semibold ' + HS_BLOCK[t].cls + '">⛔ ' + HS_BLOCK[t].label + '</span>').join('')
                    + (other.length ? '<span class="ml-1 px-1.5 rounded border text-[10px] font-semibold bg-red-100 text-red-700 border-red-300">⛔ To\'siq</span>' : '')
                    // Bartaraf etilgan to'siq: band o'qituvchi/xona o'rniga kafedra mudiri / mudir xonasi qo'yilgan.
                    + (a.resolved || []).map(b => '<span class="ml-1 px-1.5 rounded border text-[10px] font-semibold bg-emerald-50 text-emerald-700 border-emerald-300" title="' + hsEsc((b.source || '') + ' · ' + (b.group || '') + ' · «' + (b.subject || '') + '» ' + (b.time || '')) + '">✔ ' + (b.type === 'room' ? 'Xona band → mudir xonasi' : 'O\'qituvchi band → kafedra mudiri') + '</span>').join('');
                const head = '<div><span class="' + opCls[a.op] + ' font-semibold">' + opName[a.op] + '</span> · ' + hsEsc(a.date) + ' ' + hsEsc(a.start) + '–' + hsEsc(a.end)
                    + ' · ' + hsEsc((u && u.code_names[a.code]) || a.code) + ' · ' + hsEsc(a.employee_name || '—') + ' · ' + hsEsc(a.auditorium_name || '—') + chips + '</div>';
                const summary = (a.changes && a.changes.length) ? '<div class="ml-3 text-gray-700">' + a.changes.map(hsChangeShort).join(' · ') + '</div>' : '';
                const details = blocks.map(b => '<div class="ml-3 mt-0.5 px-1.5 py-0.5 rounded border ' + HS_BLOCK[b.type].cls + '">' +
                    (b.type === 'room'
                        ? '<b>' + hsEsc(b.room || '') + '</b>-xona ' + hsEsc(b.time) + ' da band'
                        : '<b>' + hsEsc(b.teacher || 'O\'qituvchi') + '</b> ' + hsEsc(b.time) + ' da boshqa darsda')
                    + ': ' + hsEsc(b.source || '') + ' · ' + hsEsc(b.group || '') + ' · «' + hsEsc(b.subject || '') + '»'
                    + (b.type === 'room' && b.teacher ? ' · o\'qituvchi: ' + hsEsc(b.teacher) : '')
                    + (b.type === 'teacher' && b.room ? ' · xona: ' + hsEsc(b.room) : '') + '</div>').join('')
                    + (other.length ? '<div class="ml-3 mt-0.5 text-red-700">' + hsEsc(other.join('; ')) + '</div>' : '');
                const flags = (a.flags && a.flags.length) ? ' <span class="text-indigo-600 text-[10px]">[' + hsEsc(a.flags.join(', ')) + ']</span>' : '';
                const reason = a.reason ? '<div class="ml-3 text-gray-500 italic text-[10px]">Sabab: ' + hsEsc(a.reason) + flags + '</div>' : (flags ? '<div class="ml-3">' + flags + '</div>' : '');
                return '<div class="mb-1.5 pb-1 border-b border-dashed border-gray-200">' + head + summary + details + reason + '</div>';
            }
            // To'siq turi: xona band / o'qituvchi band / boshqa (o'qituvchi yoki xona topilmadi, para yo'q...).
            const hsBlockKinds = a => {
                const k = new Set((a.blocks || []).map(b => b.type));
                if (!k.size || (a.blocked || []).length > (a.blocks || []).length) k.add('other');
                return [...k];
            };
            const hsOpOk = a => (a.blocked && a.blocked.length)
                ? hemisSyncFilter.ops.blocked && hsBlockKinds(a).some(k => hemisSyncFilter.blk[k])
                : !!hemisSyncFilter.ops[a.op];
            function hemisSyncRender(rep) {
                const F = hemisSyncFilter;
                let tot = { create: 0, update: 0, delete: 0, blocked: 0, later: 0, hidden: 0, opHidden: 0, periodHidden: 0, finishedHidden: 0 };
                // Amal turi yoki to'siq turi bo'yicha filtr tanlangan bo'lsa — shu filtrga tushadigan amali yo'q fanlar ko'rsatilmaydi.
                const opsNarrowed = !(F.ops.create && F.ops.update && F.ops.delete && F.ops.blocked && F.blk.room && F.blk.teacher && F.blk.other);
                let body = '';
                const pf = rep.period_from, pt = rep.period_to;
                for (const g of rep.groups) {
                    if (!hsGroupOk(g)) continue;
                    // Fan filtrida tanlanmaganlar "mos" emas — yashirilgan mos fanlar soniga qo'shilmaydi.
                    const picked = g.units.filter(hsSubjOk);
                    const shown = picked.filter(u => !(F.onlyDiff && hsUnitMatches(u)));
                    tot.hidden += picked.length - shown.length;
                    // Faqat ko'rinish: bu fanlarning tanlangan davrda amali yo'q — Excel / JSON / to'siq xabarlari o'zgarmaydi.
                    const inPeriod = F.periodMatch ? shown : shown.filter(u => !hsPeriodMatch(u));
                    tot.periodHidden += shown.length - inPeriod.length;
                    const units = opsNarrowed ? inPeriod.filter(u => u.actions.some(hsOpOk)) : inPeriod;
                    tot.opHidden += inPeriod.length - units.length;
                    shown.filter(u => !units.includes(u)).forEach(u => { tot.later += u.actions_later || 0; });
                    // Mos fanlar yashirilsa ham guruhning ogohlantirishlari (o'tkazilgan fanlar, HEMIS rejasida topilmagan kartochkalar) qoladi.
                    // Amal/to'siq turi filtri tanlanganda — faqat shu turdagi amali bor fanlar; o'tkazilgan fanlar va
                    // HEMIS rejasida topilmagan kartochkalar (amal emas, ma'lumot) ko'rsatilmaydi.
                    const skippedPicked = opsNarrowed ? [] : g.skipped.filter(x => !hemisSyncFilter.subjects || hemisSyncFilter.subjects.includes(x.subject_name));
                    // Darslari tugagan fanlar (bugun va o'tgan kunlar HEMIS'da tahrirlanmaydi — qiladigan ish yo'q) sukutda yashirin;
                    // "joylanmagan" va "kartochka yo'q" — haqiqiy muammo, doim ko'rinadi.
                    const skippedShown = F.finished ? skippedPicked : skippedPicked.filter(x => x.kind !== 'finished');
                    tot.finishedHidden += skippedPicked.length - skippedShown.length;
                    const noSubjCards = opsNarrowed ? [] : (g.cards_without_subject || []);
                    if (!units.length && !skippedShown.length && !noSubjCards.length) continue;
                    body += '<h4 class="mt-4 mb-1 font-semibold text-[13px]">' + hsEsc(g.group) +
                        ' <span class="text-gray-400 font-normal">· HEMIS import: ' + hsEsc(g.imported_at || '—') + '</span></h4>';
                    body += '<table class="w-full border text-[11px]"><thead class="bg-gray-50"><tr>' +
                        '<th class="border px-1 text-left">Fan</th>' +
                        '<th class="border px-1" title="Tanlangan hisob davridagi haftalar: KTR bo\'yicha kerak, HEMIS\'da qo\'yilgan va LMS ichki jadvalida bor">Davr ' + (g.period_from || pf) + '–' + (g.period_to || pt) + '-hafta<br><span class="font-normal text-gray-500">soat va dars kunlari</span></th>' +
                        '<th class="border px-1" title="Butun semestr: (HEMIS bugungacha + LMS ertadan) − KTR jami. Musbat — ortiqcha, manfiy — kam; o\'qdan keyin — takliflar qo\'llangandan keyin">Semestr: soat farqi<br><span class="font-normal text-gray-500">(hozir → takliflardan keyin)</span></th>' +
                        '<th class="border px-1" title="Butun semestr bo\'yicha dars kunlari (jurnal ustunlari) farqi">Semestr: dars kunlari farqi</th><th class="border px-1 text-left">Tuzatish takliflari</th>' +
                        '<th class="border px-1">HEMIS amallari</th></tr></thead><tbody>';
                    for (const u of units) {
                        const p = u.period;
                        const per = p ? Object.keys(u.code_names).map(c => {
                            const k = +p.ktr_hours[c] || 0, h = +p.hemis_hours[c] || 0;
                            return '<div class="' + (k !== h ? 'text-red-600' : 'text-gray-500') + '"><b>' + hsEsc(u.code_names[c]) + '</b> soati — KTR ' + k + ' · HEMIS ' + h + '</div>';
                        }).join('') + ['L', 'A'].filter(x => Object.values(u.codes).includes(x)).map(x => {
                            const k = +p.ktr_count[x] || 0, h = +p.hemis_count[x] || 0, l = +p.lms_count[x] || 0;
                            return '<div class="' + (k !== h ? 'text-red-600' : 'text-gray-500') + '"' + (x === 'A' ? HS_A_TITLE : '') + '><b>' + HS_PART[x] + '</b> kunlari — KTR ' + k + ' · HEMIS ' + h + ' · LMS ' + l + '</div>';
                        }).join('') : '';
                        const hd = Object.keys(u.code_names).map(c => {
                            const b = u.before.hours_diff[c] || 0, a = u.after.hours_diff[c] || 0;
                            return '<div class="' + (a ? 'text-red-600' : (b ? 'text-amber-700' : 'text-gray-400')) + '">' + hsEsc(u.code_names[c]) + ' soati: ' +
                                (b || a ? hsSigned(-b) + ' → ' + hsSigned(-a) : '0') + '</div>';
                        }).join('');
                        const cd = ['L', 'A'].filter(x => Object.values(u.codes).includes(x)).map(x => {
                            const b = u.before.count_diff[x] || 0, a = u.after.count_diff[x] || 0;
                            return '<div class="' + (a ? 'text-red-600' : (b ? 'text-amber-700' : 'text-gray-400')) + '"' + (x === 'A' ? HS_A_TITLE : '') + '>' + HS_PART[x] + ' kunlari: ' +
                                (b || a ? hsSigned(-b) + ' → ' + hsSigned(-a) : '0') + '</div>';
                        }).join('');
                        // "HEMIS'ga yozish" chegarasidan keyingi takliflar — kulrang, hozir yozilmaydi.
                        const fx = u.fixes.map(f => {
                            const later = g.until && f.date && f.date > g.until;
                            const wk = later ? (rep.weeks || []).find(w => f.date >= w.from && f.date <= w.to) : null;
                            return later
                                ? '<div class="text-gray-400">• ' + hsEsc(f.text) + ' <i>(' + (wk ? wk.week + '-hafta' : 'keyingi hafta') + ' — hozir yozilmaydi)</i></div>'
                                : '<div>• ' + hsEsc(f.text) + '</div>';
                        }).join('') +
                            u.notes.map(n => '<div class="text-red-600">⚠ ' + hsEsc(n) + '</div>').join('');
                        const acts = u.actions.filter(hsOpOk);
                        const cnt = { create: 0, update: 0, delete: 0 };
                        tot.later += u.actions_later || 0;
                        acts.forEach(a => { if (a.blocked && a.blocked.length) tot.blocked++; else { cnt[a.op]++; tot[a.op]++; } });
                        const list = acts.map(a => hsActionHtml(a, u)).join('');
                        const nBlk = acts.filter(a => a.blocked && a.blocked.length).length;
                        body += '<tr class="align-top"><td class="border px-1">' + hsEsc(u.subject_name)
                            + (u.cycle ? ' <span class="px-1 rounded bg-sky-100 text-sky-700 text-[10px]" title="Sikl fani: HEMIS LMS ichki sikl jadvaliga aynan moslashtiriladi (KTR balansi — faqat ma\'lumot)">sikl</span>' : '') + '<div class="text-gray-400">' + hsEsc(u.kafedra || '') + '</div></td>' +
                            '<td class="border px-1 whitespace-nowrap">' + per + '</td>' +
                            '<td class="border px-1 whitespace-nowrap">' + hd + '</td><td class="border px-1 whitespace-nowrap">' + cd + '</td>' +
                            '<td class="border px-1">' + (fx || '<span class="text-gray-400">—</span>') + '</td>' +
                            '<td class="border px-1">' + (acts.length
                                ? '<details><summary class="cursor-pointer">+' + cnt.create + ' · ~' + cnt.update + ' · −' + cnt.delete + (nBlk ? ' · ⛔' + nBlk : '') + '</summary>' + list + '</details>'
                                : (u.actions.length ? '<span class="text-gray-400">filtrda yashirin</span>'
                                    // Tanlangan haftagacha amal yo'q, lekin keyingi haftalarda bor — "mos" emas (filtrda ham yashirilmaydi).
                                    : u.actions_later ? '<span class="text-emerald-600">bu davrda mos</span><div class="text-amber-700">keyingi haftalarda ' + u.actions_later + ' ta amal</div>'
                                    : '<span class="text-emerald-600">mos</span>')) + '</td></tr>';
                    }
                    // Fan filtri tanlangan bo'lsa — o'tkazilgan fanlar ham faqat tanlanganlari.
                    for (const sk of skippedShown) {
                        body += '<tr class="text-gray-500"><td class="border px-1">' + hsEsc(sk.subject_name) + '</td><td class="border px-1" colspan="5">' + hsEsc(sk.reason) + '</td></tr>';
                    }
                    body += '</tbody></table>';
                    if (noSubjCards.length) {
                        body += '<div class="text-amber-700 mt-1">HEMIS o\'quv rejasida topilmagan kartochkalar: ' + hsEsc(noSubjCards.join('; ')) + '</div>';
                    }
                }
                const wkOpt = (sel, label) => (rep.weeks || []).map(w => '<option value="' + w.week + '"' + (w.week === sel ? ' selected' : '') + '>' + label(w) + (w.week === rep.current_week ? ' • joriy' : '') + '</option>').join('');
                const chk = (id, on, text) => '<label class="inline-flex items-center gap-1 mr-2"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '> ' + text + '</label>';
                const head = '<div class="flex flex-wrap items-center gap-2 mb-2"><h3 class="font-semibold text-[14px] flex-1">HEMIS solishtirish · ' + hsEsc(scopeLabelText()) +
                    ' · bugun ' + hsEsc(rep.today) + '</h3>' +
                    '<button type="button" id="hsExcel" class="tt-weekly-excel">Excel</button>' +
                    '<button type="button" id="hsJson" class="tt-ktr-weeks-btn">JSON (HEMIS\'ga yozish uchun)</button>' +
                    '<button type="button" id="hsTickets" class="tt-ktr-weeks-btn" title="Filtrdagi to\'siqlar bo\'yicha o\'qituvchi va kafedra mudiriga Telegram orqali so\'rov">📨 To\'siqlar bo\'yicha xabar</button>' +
                    '<button type="button" id="hsTicketList" class="tt-ktr-weeks-btn">So\'rovlar</button>' +
                    // Xatcho'p: HEMIS sahifasida bosilganda mark'dagi yozish oynasini ochadi (JSON fayl tanlanadi).
                    '<a href="' + hsEsc("javascript:(function(){var s=document.createElement('script');s.charset='utf-8';s.src='" + HS_WRITER_URL + "?v='+Date.now();document.body.appendChild(s);})();") + '"' +
                    ' onclick="alert(\'Bu xatcho\\\'p: uni sichqoncha bilan brauzerning xatcho\\\'plar paneliga torting. Keyin HEMIS sahifasida (kirib turgan holda) shu xatcho\\\'pni bosing va yuklab olingan JSON faylni tanlang.\'); return false;"' +
                    ' title="Sichqoncha bilan xatcho\'plar paneliga torting, so\'ng HEMIS sahifasida bosing" class="px-2 py-1 rounded border border-indigo-400 bg-indigo-50 text-indigo-700 cursor-move">⭐ HEMIS\'ga yozish (xatcho\'p)</a>' +
                    '<button type="button" id="hsClose" class="px-2 py-1 border rounded">Yopish</button></div>' +
                    hsUnplacedBanner(rep) +
                    '<div class="flex flex-wrap items-center gap-3 mb-2 p-2 bg-gray-50 rounded border">' +
                    '<label class="text-gray-700">Hisob davri: <select id="hsPFrom" class="border rounded px-1 py-0.5 text-[12px]">' + wkOpt(pf, w => w.week + '-hafta (' + hsEsc(w.from) + ')') + '</select>' +
                    ' – <select id="hsPTo" class="border rounded px-1 py-0.5 text-[12px]">' + wkOpt(pt, w => w.week + '-hafta oxiri (' + hsEsc(w.to) + ')') + '</select></label>' +
                    '<label class="text-gray-700">HEMIS\'ga yozish: <select id="hsUntil" class="border rounded px-1 py-0.5 text-[12px]">' +
                    wkOpt(rep.until_week, w => w.week + '-hafta oxirigacha (' + hsEsc(w.to) + ')') +
                    '<option value="0"' + (!rep.until_week ? ' selected' : '') + '>Semestr oxirigacha</option></select></label>' +
                    '<span class="text-gray-700">' + chk('hsOnlyDiff', F.onlyDiff, 'Faqat farqi borlar') +
                    chk('hsPeriodMatch', F.periodMatch, '<span title="Tanlangan davrgacha amali yo\'q, keyingi haftalarda amali bor fanlar">Bu davrda moslar</span>') +
                    chk('hsFinished', F.finished, '<span title="LMS ichki jadvalida ertadan keyin darsi yo\'q fanlar — bugun va o\'tgan kunlar HEMIS\'da tahrirlanmaydi">Tugagan fanlar</span>') +
                    chk('hsOpC', F.ops.create, 'Qo\'shish') + chk('hsOpU', F.ops.update, 'O\'zgartirish') + chk('hsOpD', F.ops.delete, 'O\'chirish') + chk('hsOpB', F.ops.blocked, 'To\'siqli') +
                    '<span class="ml-1 pl-2 border-l border-gray-300' + (F.ops.blocked ? '' : ' opacity-40') + '">To\'siq turi: ' +
                    chk('hsBlkRoom', F.blk.room, '<span class="px-1 rounded bg-orange-100 text-orange-800">Xona band</span>') +
                    chk('hsBlkTeacher', F.blk.teacher, '<span class="px-1 rounded bg-purple-100 text-purple-800">O\'qituvchi band</span>') +
                    chk('hsBlkOther', F.blk.other, '<span class="px-1 rounded bg-red-100 text-red-700">Boshqa</span>') +
                    '<button type="button" id="hsOnlyBlocked" class="ml-1 underline text-[11px]">faqat to\'siqlilar</button></span>' + '</span>' +
                    hsPickBox('Subj', 'Fanlar', hsSubjectNames(rep), F.subjects) +
                    hsPickBox('Group', 'Guruhlar', hsGroupNames(rep), F.groups) + '</div>' +
                    '<div class="text-gray-600 mb-1">Tuzatish takliflari butun semestr bo\'yicha: KTR jami = HEMIS (bugungacha o\'tilgan) + LMS ichki jadvali (ertadan semestr oxirigacha). ' +
                    '«Hisob davri» faqat shu haftalardagi KTR / HEMIS / LMS yig\'indilarini ko\'rsatadi, «HEMIS\'ga yozish» esa qaysi haftagacha amal chiqishini belgilaydi. Bugun va undan oldingi kunlarga tegilmaydi.</div>' +
                    '<div class="text-gray-500 mb-1 text-[11px]"><b>Soati</b> — akademik soat (1 para = 2 soat). <b>Kunlari</b> — necha kunda o\'tilgani (jurnaldagi ustunlar soni). ' +
                    '<b>Amaliy qism</b> — ma\'ruzadan boshqa barcha turlar (amaliy, seminar, laboratoriya, klinik): bir kunda qaysi biri bo\'lsa ham bitta ustun. ' +
                    '<b>KTR</b> — reja bo\'yicha kerak, <b>HEMIS</b> — HEMIS\'da qo\'yilgan, <b>LMS</b> — mark ichki jadvalida. Teng bo\'lmasa — qizil.</div>' +
                    '<div class="text-gray-600 mb-1">Ko\'rsatilgan amallar: <b class="text-emerald-700">+' + tot.create + '</b> qo\'shish, <b class="text-amber-700">~' + tot.update + '</b> o\'zgartirish, ' +
                    '<b class="text-red-700">−' + tot.delete + '</b> o\'chirish' + (tot.blocked ? ', <b class="text-red-600">' + tot.blocked + ' ta to\'siqli</b>' : '') +
                    (tot.later ? ' · tanlangan haftadan keyin yana ' + tot.later + ' ta amal (hozir yozilmaydi)' : '') +
                    (tot.hidden ? ' · ' + tot.hidden + ' ta mos fan yashirildi' : '') +
                    (tot.opHidden ? ' · ' + tot.opHidden + ' ta fanda tanlangan turdagi amal yo\'q (yashirildi)' : '') +
                    (tot.periodHidden ? ' · ' + tot.periodHidden + ' ta fan bu davrda mos (yashirildi)' : '') +
                    (tot.finishedHidden ? ' · ' + tot.finishedHidden + ' ta tugagan fan (yashirildi)' : '') + '.' +
                    (rep.fallback_room ? '' : ' <b class="text-red-600">"Kafedra mudiri xonasi" auditoriyasi topilmadi — HEMIS\'da ochib, auditoriyalarni import qiling.</b>') +
                    (rep.groups_not_in_hemis.length ? '<div class="text-red-600">HEMIS\'da topilmagan guruhlar: ' + hsEsc(rep.groups_not_in_hemis.join(', ')) + '</div>' : '') + '</div>';
                hemisSyncModal(head + body);
                $('hsClose').onclick = () => $('hemisSyncModal').remove();
                if ($('hsUnplacedToggle')) $('hsUnplacedToggle').onclick = () => {
                    hsUnplacedOpen = !hsUnplacedOpen;
                    $('hsUnplacedList').classList.toggle('hidden', !hsUnplacedOpen);
                };
                $('hsTickets').onclick = () => hsTicketsSend(rep);
                $('hsTicketList').onclick = () => hsTicketsList();
                $('hsUntil').onchange = function () { hemisSyncUntil = +this.value; hemisSyncRun(); };
                const perChange = () => {
                    let a = +$('hsPFrom').value, b = +$('hsPTo').value;
                    if (b < a) [a, b] = [b, a];
                    hemisSyncPeriod = { from: a, to: b };
                    hemisSyncRun();
                };
                $('hsPFrom').onchange = perChange;
                $('hsPTo').onchange = perChange;
                const reFilter = () => {
                    F.onlyDiff = $('hsOnlyDiff').checked;
                    F.periodMatch = $('hsPeriodMatch').checked;
                    F.finished = $('hsFinished').checked;
                    F.ops = { create: $('hsOpC').checked, update: $('hsOpU').checked, delete: $('hsOpD').checked, blocked: $('hsOpB').checked };
                    F.blk = { room: $('hsBlkRoom').checked, teacher: $('hsBlkTeacher').checked, other: $('hsBlkOther').checked };
                    hemisSyncRender(rep);
                };
                ['hsOnlyDiff', 'hsPeriodMatch', 'hsFinished', 'hsOpC', 'hsOpU', 'hsOpD', 'hsOpB', 'hsBlkRoom', 'hsBlkTeacher', 'hsBlkOther'].forEach(id => { $(id).onchange = reFilter; });
                $('hsOnlyBlocked').onclick = () => {
                    F.ops = { create: false, update: false, delete: false, blocked: true };
                    hemisSyncRender(rep);
                };
                // Ochiladigan ro'yxat: belgilash — filtr (hammasi belgilansa null), qayta chizilgandan keyin ro'yxat ochiq qoladi.
                for (const [key, all, field] of [['Subj', hsSubjectNames(rep), 'subjects'], ['Group', hsGroupNames(rep), 'groups']]) {
                    const boxes = () => document.querySelectorAll('#hemisSyncModal .hs-pick-' + key);
                    const apply = () => {
                        const picked = [...boxes()].filter(c => c.checked).map(c => all[+c.dataset.i]);
                        F[field] = picked.length === all.length ? null : picked;
                        hemisSyncRender(rep);
                        if ($('hs' + key + 'Box')) $('hs' + key + 'Box').open = true;
                    };
                    boxes().forEach(c => { c.onchange = apply; });
                    $('hs' + key + 'All').onclick = () => { boxes().forEach(c => { c.checked = true; }); apply(); };
                    $('hs' + key + 'None').onclick = () => { boxes().forEach(c => { c.checked = false; }); apply(); };
                }
                $('hsJson').onclick = () => {
                    const un = hsUnplaced(rep);
                    if (un.cards && !confirm(un.cards + ' ta joylanmagan karta bor — ular HEMIS\'ga yozilmaydi. Baribir davom etamizmi?')) return;
                    // Yozish uchun JSON — faqat filtrda tanlangan guruh, fan va amallar (to'siqlilar baribir yozilmaydi).
                    const out = JSON.parse(JSON.stringify(rep));
                    out.groups = out.groups.filter(hsGroupOk);
                    out.groups.forEach(g => g.units.forEach(u => { u.actions = hsSubjOk(u) ? u.actions.filter(hsOpOk) : []; }));
                    const blob = new Blob([JSON.stringify(out, null, 1)], { type: 'application/json' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob); a.download = 'hemis_amallar_' + rep.today + '.json';
                    document.body.appendChild(a); a.click(); a.remove();
                };
                $('hsExcel').onclick = async function () {
                    const fd = new FormData();
                    const add = (k, v) => fd.append(k, v);
                    add('_token', CSRF);
                    const sb = scopeBody();
                    Object.keys(sb).forEach(k => sb[k].forEach(v => add(k + '[]', v)));
                    // Faqat foydalanuvchi tanlagani yuboriladi: sukutda server har guruh uchun o'z joriy haftasini
                    // oladi (kalendari boshqacha guruhlar) — Excel oynadagi bilan bir xil bo'ladi.
                    if (hemisSyncUntil !== null) add('until_week', hemisSyncUntil);
                    if (hemisSyncPeriod) { add('period_from', hemisSyncPeriod.from); add('period_to', hemisSyncPeriod.to); }
                    add('only_diff', F.onlyDiff ? 1 : 0);
                    add('ops_filter', 1);   // hech biri tanlanmasa ham — Excel'da amal bo'lmaydi
                    Object.keys(F.ops).filter(k => F.ops[k]).forEach(k => add('ops[]', k));
                    Object.keys(F.blk).filter(k => F.blk[k]).forEach(k => add('blk[]', k));
                    add('blk_filter', 1);
                    if (F.subjects) { add('subjects_filter', 1); F.subjects.forEach(n => add('subjects[]', n)); }
                    // Guruh filtri hisobotni toraytirmaydi ("groups[]" — qamrov, guruhlararo to'siqlar o'zgarardi), faqat chiqishni.
                    if (F.groups) { add('groups_filter', 1); F.groups.forEach(n => add('filter_groups[]', n)); }
                    // Forma yuborish o'rniga fetch: xato bo'lsa sahifa "ERR_INVALID_RESPONSE" bo'lib qolmaydi — sababi ko'rsatiladi.
                    const btn = this, html = btn.innerHTML;
                    btn.disabled = true; btn.textContent = 'Tayyorlanmoqda…';
                    try {
                        const r = await fetch(BASE + '/boards/' + board.id + '/hemis-sync/export', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
                        const blob = await r.blob();
                        const head = new Uint8Array(await blob.slice(0, 2).arrayBuffer());
                        if (!r.ok || head[0] !== 0x50 || head[1] !== 0x4B) {   // xlsx — "PK" (zip) bilan boshlanadi
                            let msg = 'HTTP ' + r.status;
                            try { const j = JSON.parse(await blob.text()); msg = j.error || j.message || msg; } catch (_) { }
                            throw new Error(msg);
                        }
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(new Blob([blob], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
                        a.download = 'hemis_solishtirish_' + rep.today + '.xlsx';
                        document.body.appendChild(a); a.click(); a.remove();
                        setTimeout(() => URL.revokeObjectURL(a.href), 5000);
                    } catch (e) {
                        alert('Excel faylini yaratib bo\'lmadi: ' + e.message);
                    } finally { btn.disabled = false; btn.innerHTML = html; }
                };
            }
            // "HEMIS solishtirish" bosilganda eng birinchi (HEMIS'siz, faqat LMS): qamrovda ichki jadvalda joylanmagan kartalar bo'lsa —
            // qizil oyna; "Baribir davom etish" — odatdagi hemisSyncAsk(). Joylanmagan bo'lmasa yoki xulosa olinmasa — darhol hemisSyncAsk().
            async function hemisUnplacedGate() {
                hemisSyncModal('<div class="p-6 text-center text-gray-500">Joylanmagan kartalar tekshirilmoqda… (' + hsEsc(scopeLabelText()) + ')</div>');
                let u;
                try {
                    u = await api(BASE + '/boards/' + board.id + '/unplaced-summary', 'POST', scopeBody());
                } catch (e) {
                    console.warn('Joylanmagan kartalar xulosasi olinmadi — ogohlantirishsiz davom etiladi:', e);
                    return hemisSyncAsk();
                }
                const un = hsUnplaced({ unplaced: u });
                if (!un.cardWeeks && !un.cycleCards) return hemisSyncAsk();
                const m = hemisSyncModal('<div id="hsUnpBox" class="p-3 rounded border border-red-400 bg-red-50 text-red-800">' +
                    '<h3 class="font-semibold text-[14px] mb-2">⛔ Hali joylanmagan kartalar bor</h3>' +
                    '<div id="hsUnpText" class="mb-2">Haftalik — ' + un.cardWeeks + ' ta karta-hafta, sikl — ' + un.cycleCards + ' ta karta. Ular HEMIS\'ga yozilmaydi, HEMIS\'da dars kam qoladi.</div>' +
                    '<details open class="mb-3"><summary class="cursor-pointer">Guruh bo\'yicha ro\'yxat</summary>' +
                    '<div id="hsUnpList" class="mt-1 max-h-72 overflow-auto">' + hsUnplacedListHtml(un) + '</div></details>' +
                    '<div class="flex gap-2"><button type="button" id="hsUnpGo" class="px-3 py-1 rounded bg-red-600 text-white">Baribir davom etish</button>' +
                    // Diagnostika (unplaced-export) faqat haftalik kartalar bo'yicha — sikl kartalari unda yo'q; faqat sikl bo'lsa bo'sh fayl bo'lardi.
                    (un.cardWeeks ? '<button type="button" id="hsUnpExcel" class="tt-weekly-excel" title="Haftalik joylanmagan kartalar va sabablari (sikl kartalari bu faylga kirmaydi)">Excel (sabablar)</button>' : '') +
                    '<button type="button" id="hsUnpClose" class="px-3 py-1 rounded border bg-white">Yopish</button></div></div>');
                $('hsUnpGo').onclick = () => hemisSyncAsk();
                $('hsUnpClose').onclick = () => m.remove();
                if ($('hsUnpExcel')) $('hsUnpExcel').onclick = async function () {
                    // Mavjud "Joylanmagan kartalar" diagnostikasi — ichki jadval, shu qamrov bo'yicha.
                    const params = new URLSearchParams();
                    const sb = scopeBody();
                    Object.keys(sb).forEach(k => sb[k].forEach(v => params.append(k + '[]', v)));
                    params.set('layer', 'internal');
                    this.disabled = true;
                    try { await downloadUnplacedXlsx(params); }
                    catch (e) { alert('Diagnostika Excelini yuklab bo\'lmadi: ' + e.message); }
                    finally { this.disabled = false; }
                };
            }
            async function hemisSyncRun() {
                hemisSyncModal('<div class="p-6 text-center text-gray-500">HEMIS bilan solishtirilmoqda… (' + hsEsc(scopeLabelText()) + ')</div>');
                try {
                    // Sukut (until_week yuborilmasa) — server joriy hafta oxirigacha oladi.
                    const body = hemisSyncUntil === null ? scopeBody() : Object.assign({ until_week: hemisSyncUntil }, scopeBody());
                    if (hemisSyncPeriod) Object.assign(body, { period_from: hemisSyncPeriod.from, period_to: hemisSyncPeriod.to });
                    hemisSyncLast = await api(BASE + '/boards/' + board.id + '/hemis-sync', 'POST', body);
                    hemisSyncRender(hemisSyncLast);
                } catch (e) {
                    hemisSyncModal('<div class="p-4 text-red-600">' + hsEsc(hsErrText(e)) + '</div>' + HS_CLOSE);
                }
            }
            const HS_CLOSE = '<button type="button" class="px-2 py-1 border rounded" onclick="this.closest(\'#hemisSyncModal\').remove()">Yopish</button>';
            // "Failed to fetch" — brauzer mark serveriga yeta olmadi (internet/server), HEMIS emas.
            const hsErrText = e => /failed to fetch|networkerror|load failed/i.test(String(e && e.message))
                ? 'Mark serveri bilan aloqa yo\'q (internet yoki server javob bermayapti). Birozdan keyin qayta urinib ko\'ring.'
                : 'Xatolik: ' + (e && e.message);
            const hsAgo = ts => {
                if (!ts) return 'hali import qilinmagan';
                const m = Math.round((Date.now() - new Date(String(ts).replace(' ', 'T')).getTime()) / 60000);
                return hsEsc(ts) + ' (' + (m < 60 ? m + ' daqiqa' : m < 2880 ? Math.round(m / 60) + ' soat' : Math.round(m / 1440) + ' kun') + ' oldin)';
            };
            // 1-qadam: oxirgi import vaqtini ko'rsatib, shu ma'lumot bilan solishtirish yoki avval HEMIS'dan yangilashni so'raydi.
            async function hemisSyncAsk(note) {
                hemisSyncModal('<div class="p-6 text-center text-gray-500">Oxirgi HEMIS importi tekshirilmoqda…</div>');
                let st;
                try { st = await api(BASE + '/boards/' + board.id + '/hemis-sync/import-status'); }
                catch (e) { hemisSyncModal('<div class="p-4 text-red-600">' + hsEsc(hsErrText(e)) + '</div>' + HS_CLOSE); return; }
                if (st.running) return hemisSyncWaitImport();
                const job = st.job || {};
                hemisSyncModal('<h3 class="font-semibold text-[14px] mb-2">HEMIS solishtirish · ' + hsEsc(scopeLabelText()) + '</h3>' +
                    (note ? '<div class="text-red-600 mb-2">' + note + '</div>' : '') +
                    '<div class="text-gray-700 mb-1">Solishtirish HEMIS dars jadvalining mark\'dagi nusxasi bilan qilinadi.</div>' +
                    '<div class="mb-1">Oxirgi import: <b>' + hsAgo(st.last_import_at) + '</b>' +
                    (st.last_import_approx ? ' <span class="text-gray-500">(taxminan — to\'liq import hali qayd etilmagan, bu yozuvlarning oxirgi yangilanishi)</span>' : '') +
                    '. Har kecha 00:30 da avtomatik yangilanadi.</div>' +
                    (job.status === 'error' ? '<div class="text-amber-700 mb-1">Oxirgi qo\'lda yangilash xato bilan tugagan: ' + hsEsc(job.message || '') + '</div>' : '') +
                    '<div class="mb-3 text-gray-600">Shu ma\'lumot bilan solishtiraymi yoki avval HEMIS\'dan yangilab olaymi?</div>' +
                    '<div class="flex gap-2"><button type="button" id="hsUseLast" class="px-3 py-1 rounded bg-emerald-600 text-white">Shu ma\'lumot bilan solishtirish</button>' +
                    '<button type="button" id="hsReimport" class="px-3 py-1 rounded border border-emerald-600 text-emerald-700">HEMIS\'dan yangilab, keyin solishtirish</button>' + HS_CLOSE + '</div>');
                $('hsUseLast').onclick = () => hemisSyncRun();
                $('hsReimport').onclick = async () => {
                    try { await api(BASE + '/boards/' + board.id + '/hemis-sync/import', 'POST', {}); }
                    catch (e) { return hemisSyncAsk(hsEsc(hsErrText(e))); }
                    hemisSyncWaitImport();
                };
            }
            // Import tugashini kutadi (job holati keshda); tugasa — solishtiradi, xato bo'lsa — oxirgi ma'lumotni taklif qiladi.
            // Oyna yopilsa kutish to'xtaydi (import serverda davom etadi) — oyna qayta ochilib turmaydi.
            async function hemisSyncWaitImport() {
                let skip = false;   // "Kutmasdan…" — holat so'ralayotgan paytda bosilsa ham yo'qolmaydi
                for (let i = 0; i < 400; i++) {
                    let st;
                    try { st = await api(BASE + '/boards/' + board.id + '/hemis-sync/import-status'); }
                    catch (e) { st = null; }
                    if (!$('hemisSyncModal')) return;
                    if (skip) return hemisSyncRun();
                    const j = (st && st.job) || {};
                    if (st && !st.running && j.status === 'done') return hemisSyncRun();
                    if (st && !st.running && j.status !== 'queued') {
                        return hemisSyncAsk('HEMIS\'dan yangilab bo\'lmadi' + (j.message ? ': ' + hsEsc(j.message) : '') +
                            '. HEMIS hozir ishlamayotgan bo\'lishi mumkin — oxirgi import bilan solishtirish mumkin.');
                    }
                    const m = hemisSyncModal('<div class="p-6 text-center text-gray-600">HEMIS\'dan dars jadvali yangilanmoqda… ' +
                        (j.pages ? j.page + '/' + j.pages + ' sahifa' : '') + '<div class="text-[11px] text-gray-400 mt-1">' + hsEsc(j.message || '') + '</div>' +
                        '<div class="mt-3"><button type="button" id="hsStopWait" class="px-2 py-1 border rounded">Kutmasdan oxirgi import bilan solishtirish</button></div></div>');
                    $('hsStopWait').onclick = () => { skip = true; };
                    for (let k = 0; k < 6 && !skip && m.isConnected; k++) await new Promise(r => setTimeout(r, 500));
                    if (!m.isConnected) return;
                    if (skip) return hemisSyncRun();
                }
                hemisSyncAsk('Import juda uzoq davom etmoqda.');
            }
            if ($('hemisSyncBtn')) $('hemisSyncBtn').onclick = async function () {
                if (!board) return;
                this.disabled = true;
                hemisSyncUntil = null;
                hemisSyncPeriod = null;
                hemisSyncFilter.subjects = null;   // fan va guruh filtri qamrovga bog'liq — yangi solishtirishda hammasi
                hemisSyncFilter.groups = null;
                // Eng birinchi — joylanmagan kartalar (bo'lsa qizil oyna), so'ng odatdagi hemisSyncAsk().
                try { await hemisUnplacedGate(); } finally { this.disabled = false; }
            };
            // ===== HEMIS to'siqlari bo'yicha so'rovlar (Telegram: o'qituvchi / kafedra mudiri) =====
            const TICKET_FROM_URL = new URLSearchParams(location.search).get('ticket');
            const HS_TICKET_STATUS = { open: 'Ochiq', resolved: 'Hal qilindi', pending_registrar: 'Registrator tasdig\'ida', cancelled: 'Bekor qilingan' };
            const HS_DAY = ['', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];
            const HS_ROLE = { teacher: 'Dars o\'qituvchisi', head: 'Kafedra mudiri', blocker: 'Xonani band qilgan dars o\'qituvchisi' };
            // Oynadagi (filtrlangan) to'siqli amallar → so'rovlar: har to'siq turi alohida.
            function hsTicketItems(rep) {
                const items = [];
                for (const g of rep.groups) {
                    if (!hsGroupOk(g)) continue;
                    for (const u of g.units) {
                        if (!hsSubjOk(u)) continue;
                        for (const a of u.actions) {
                            if (!(a.blocked && a.blocked.length) || !a.card_id || !a.week || !hsOpOk(a)) continue;
                            for (const kind of hsBlockKinds(a)) {
                                if (!hemisSyncFilter.blk[kind]) continue;
                                const b = (a.blocks || []).find(x => x.type === kind) || null;
                                items.push({ card_id: a.card_id, week: a.week, date: a.date, start: a.start, end: a.end, type: kind, group: g.group,
                                    text: kind === 'other' ? a.blocked.filter(t => !(a.blocks || []).some(x => x.text === t)).join('; ') : (b && b.text) || a.blocked.join('; '),
                                    blocker: b ? { source: b.source, group: b.group, subject: b.subject, teacher: b.teacher, room: b.room, time: b.time } : null });
                            }
                        }
                    }
                }
                return items;
            }
            async function hsTicketsSend(rep) {
                const items = hsTicketItems(rep);
                if (!items.length) { alert('Filtrda yuboriladigan to\'siq yo\'q (bugungacha va tanlangan haftadan keyingilar hisobga olinmaydi).'); return; }
                if (!confirm(items.length + ' ta to\'siq bo\'yicha so\'rov yaratiladi va dars o\'qituvchisi, kafedra mudiri (xona band bo\'lsa — xonani band qilgan dars o\'qituvchisi ham) Telegram orqali xabar oladi. Yuboramizmi?')) return;
                // api() massivni "key[]" qilib yuboradi — obyektlar "[object Object]" bo'lib qolardi (422). Shuning uchun
                // Laravel tushunadigan items[i][maydon] (blocker uchun items[i][blocker][maydon]) kalitlari.
                const body = {};
                items.forEach((it, i) => Object.entries(it).forEach(([k, v]) => {
                    if (v && typeof v === 'object') Object.entries(v).forEach(([bk, bv]) => { body['items[' + i + '][' + k + '][' + bk + ']'] = bv; });
                    else body['items[' + i + '][' + k + ']'] = v;
                }));
                try {
                    const r = await api(BASE + '/boards/' + board.id + '/block-tickets', 'POST', body);
                    alert('Yaratildi: ' + r.created + ' ta so\'rov (o\'tkazildi: ' + r.skipped + ' — allaqachon ochiq yoki o\'tgan kun).\nTelegram xabarlari: ' + r.telegram_sent + ' ta.' +
                        (r.without_telegram.length ? '\n\nTelegram ulanmagan / topilmagan: ' + r.without_telegram.join(', ') : ''));
                } catch (e) { alert(hsErrText(e)); }
            }
            async function hsTicketsList() {
                if (!board) return;
                hemisSyncModal('<div class="p-6 text-center text-gray-500">To\'siq so\'rovlari yuklanmoqda…</div>');
                let j;
                try { j = await api(BASE + '/boards/' + board.id + '/block-tickets'); }
                catch (e) { hemisSyncModal('<div class="p-4 text-red-600">' + hsEsc(hsErrText(e)) + '</div>' + HS_CLOSE); return; }
                const rows = (j.tickets || []).map(t => '<tr class="align-top hover:bg-gray-50 cursor-pointer" data-ticket="' + t.id + '">' +
                    '<td class="border px-1">№' + t.id + '</td><td class="border px-1">' + hsEsc(t.lesson_date) + ' ' + hsEsc(t.start_time) + '–' + hsEsc(t.end_time) + '</td>' +
                    '<td class="border px-1">' + hsEsc(t.group_name) + '<div class="text-gray-500">' + hsEsc(t.subject_name) + '</div></td>' +
                    '<td class="border px-1">' + (HS_BLOCK[t.block_type] ? '<span class="px-1 rounded border ' + HS_BLOCK[t.block_type].cls + '">' + HS_BLOCK[t.block_type].label + '</span>' : 'Boshqa') + '</td>' +
                    '<td class="border px-1">' + (t.recipients || []).map(r => hsEsc(r.name) + ': ' + (r.response === 'accepted' ? '✅' : r.response === 'declined' ? '❌' : (r.chat_id ? '…' : '📵'))).join('<br>') + '</td>' +
                    '<td class="border px-1">' + hsEsc(HS_TICKET_STATUS[t.status] || t.status) + (t.can_resolve ? ' <b class="text-emerald-700">— hal qiling</b>' : '') + '</td></tr>').join('');
                hemisSyncModal('<div class="flex items-center mb-2"><h3 class="font-semibold text-[14px] flex-1">To\'siq so\'rovlari</h3>' + HS_CLOSE + '</div>' +
                    '<div class="text-gray-500 text-[11px] mb-2">✅ qabul qildi · ❌ rad etdi · … javob kutilmoqda · 📵 Telegram ulanmagan. Qatorni bosing — batafsil va hal qilish.</div>' +
                    (rows ? '<table class="w-full border text-[11px]"><thead class="bg-gray-50"><tr><th class="border px-1">№</th><th class="border px-1">Sana, vaqt</th><th class="border px-1">Guruh, fan</th><th class="border px-1">To\'siq</th><th class="border px-1">Javoblar</th><th class="border px-1">Holat</th></tr></thead><tbody>' + rows + '</tbody></table>'
                        : '<div class="text-gray-500">So\'rov yo\'q.</div>'));
                document.querySelectorAll('#hemisSyncModal [data-ticket]').forEach(tr => { tr.onclick = () => hsTicketOpen(tr.dataset.ticket); });
            }
            async function hsTicketOpen(id, day) {
                hemisSyncModal('<div class="p-6 text-center text-gray-500">So\'rov №' + hsEsc(id) + ' yuklanmoqda…</div>');
                let t;
                try { t = (await api(BASE + '/block-tickets/' + id + (day ? '?day=' + day : ''))).ticket; }
                catch (e) { hemisSyncModal('<div class="p-4 text-red-600">' + hsEsc(hsErrText(e)) + '</div>' + HS_CLOSE); return; }
                const b = t.blocker || {};
                const blockLine = t.block_type === 'room'
                    ? '<b>' + hsEsc(b.room || '') + '</b>-xona ' + hsEsc(b.time || '') + ' da band: ' + hsEsc([b.source, b.group, b.subject && '«' + b.subject + '»', b.teacher && 'o\'qituvchi: ' + b.teacher].filter(Boolean).join(' · '))
                    : t.block_type === 'teacher'
                        ? '<b>' + hsEsc(b.teacher || '') + '</b> ' + hsEsc(b.time || '') + ' da boshqa darsda: ' + hsEsc([b.source, b.group, b.subject && '«' + b.subject + '»', b.room && 'xona: ' + b.room].filter(Boolean).join(' · '))
                        : hsEsc(t.block_text || '');
                const cls = HS_BLOCK[t.block_type] ? HS_BLOCK[t.block_type].cls : 'bg-red-100 text-red-700 border-red-300';
                let html = '<div class="flex items-center mb-2"><h3 class="font-semibold text-[14px] flex-1">To\'siq №' + t.id + ' · ' + hsEsc(HS_TICKET_STATUS[t.status] || t.status) + '</h3>' +
                    (board ? '<button type="button" id="tkBack" class="px-2 py-1 border rounded mr-1">← Ro\'yxat</button>' : '') + HS_CLOSE + '</div>' +
                    '<div class="mb-1"><b>' + hsEsc(t.group_name) + '</b> · ' + hsEsc(t.subject_name) + ' · ' + hsEsc(t.kafedra_name || '') + '</div>' +
                    '<div class="mb-1">' + hsEsc(t.lesson_date) + ' (' + t.week + '-hafta) ' + hsEsc(t.start_time) + '–' + hsEsc(t.end_time) +
                    (t.card ? ' · o\'qituvchi: ' + hsEsc(t.card.teacher_name || '—') + ' · xona: ' + hsEsc(t.card.auditorium_name || '—') : '') + '</div>' +
                    '<div class="mb-2 px-2 py-1 rounded border ' + cls + '">' + blockLine + '</div>' +
                    '<div class="mb-2"><b>Javoblar:</b> ' + (t.recipients || []).map(r => hsEsc(HS_ROLE[r.role] || r.role) + ' — ' + hsEsc(r.name) + ': ' +
                        (r.response === 'accepted' ? '✅ qabul qildi' : r.response === 'declined' ? '❌ rad etdi' : (r.chat_id ? 'javob kutilmoqda' : '📵 Telegram ulanmagan')) +
                        (r.note ? ' («' + hsEsc(r.note) + '»)' : '')).join('<br>') + '</div>';
                if (t.resolution) {
                    const rs = t.resolution;
                    html += '<div class="mb-2 text-emerald-800"><b>Yechim:</b> ' + (rs.kind === 'room' ? 'xona — ' + hsEsc(rs.auditorium) : rs.kind === 'time' ? 'vaqt — ' + hsEsc(rs.time) :
                        rs.kind === 'day' ? 'kun — ' + hsEsc(HS_DAY[rs.day] || rs.day) + ' (registrator tasdig\'i bilan)' : '') + (rs.by ? ' · ' + hsEsc(rs.by) : '') +
                        (rs.registrar ? ' · <span class="text-red-600">registrator: ' + hsEsc(rs.registrar) + (rs.registrar_note ? ' — ' + hsEsc(rs.registrar_note) : '') + '</span>' : '') + '</div>';
                }
                if (t.can_respond) {
                    html += '<div class="mb-2"><button type="button" id="tkAcc" class="px-2 py-1 rounded bg-emerald-600 text-white">✅ Qabul qildim</button> ' +
                        '<button type="button" id="tkDec" class="px-2 py-1 rounded border border-red-400 text-red-700">❌ Rad etaman</button></div>';
                }
                if (t.can_resolve) {
                    const d = t.free_pairs_day || (t.place && t.place.day);
                    html += '<div class="p-2 border rounded bg-gray-50"><b>Hal qilish</b> <span class="text-gray-500">(faqat shu so\'rov doirasida, shu dars uchun)</span>' +
                        '<div class="mt-1"><label class="mr-3"><input type="radio" name="tkKind" value="room" checked> Xonani almashtirish</label>' +
                        '<label class="mr-3"><input type="radio" name="tkKind" value="time"> Vaqtini almashtirish (shu kun)</label>' +
                        '<label><input type="radio" name="tkKind" value="day"> Kunini almashtirish (registrator tasdig\'i)</label></div>' +
                        '<div id="tkRoomBox" class="mt-1">Bo\'sh xona: <select id="tkRoom" class="border rounded px-1 max-w-full">' +
                        (t.free_rooms || []).map(r => '<option value="' + hsEsc(r.code) + '">' + hsEsc(r.name) + (r.volume ? ' (' + r.volume + ')' : '') + (r.building ? ' — ' + hsEsc(r.building) : '') + '</option>').join('') + '</select></div>' +
                        '<div id="tkTimeBox" class="mt-1 hidden">' +
                        '<span id="tkDayWrap" class="hidden">Kun: <select id="tkDay" class="border rounded px-1">' + [1, 2, 3, 4, 5, 6].map(x => '<option value="' + x + '"' + (x === d ? ' selected' : '') + '>' + HS_DAY[x] + '</option>').join('') + '</select> </span>' +
                        'Bo\'sh vaqt: <select id="tkPair" class="border rounded px-1">' + (t.free_pairs || []).map(p => '<option value="' + p.pair + '">' + hsEsc(p.time) + '</option>').join('') + '</select>' +
                        (!(t.free_pairs || []).length ? ' <span class="text-red-600">bo\'sh vaqt yo\'q</span>' : '') + '</div>' +
                        '<div class="mt-1">Izoh: <input id="tkNote" class="border rounded px-1 w-2/3" maxlength="500"></div>' +
                        '<div class="mt-2"><button type="button" id="tkResolve" class="px-3 py-1 rounded bg-emerald-600 text-white">Saqlash</button></div></div>';
                }
                hemisSyncModal(html);
                if ($('tkBack')) $('tkBack').onclick = () => hsTicketsList();
                const respond = async resp => {
                    try { await api(BASE + '/block-tickets/' + t.id + '/respond', 'POST', { response: resp }); hsTicketOpen(t.id); }
                    catch (e) { alert(hsErrText(e)); }
                };
                if ($('tkAcc')) { $('tkAcc').onclick = () => respond('accepted'); $('tkDec').onclick = () => respond('declined'); }
                if (t.can_resolve) {
                    const kindNow = () => (document.querySelector('#hemisSyncModal input[name="tkKind"]:checked') || {}).value;
                    const showKind = () => {
                        const k = kindNow();
                        $('tkRoomBox').classList.toggle('hidden', k !== 'room');
                        $('tkTimeBox').classList.toggle('hidden', k === 'room');
                        $('tkDayWrap').classList.toggle('hidden', k !== 'day');
                    };
                    document.querySelectorAll('#hemisSyncModal input[name="tkKind"]').forEach(r => { r.onchange = showKind; });
                    if (day) { document.querySelector('#hemisSyncModal input[value="day"]').checked = true; }
                    showKind();
                    $('tkDay').onchange = function () { hsTicketOpen(t.id, +this.value); };
                    $('tkResolve').onclick = async () => {
                        const k = kindNow();
                        const body = { kind: k, note: $('tkNote').value };
                        if (k === 'room') { if (!$('tkRoom').value) return alert('Xona tanlang.'); body.auditorium_code = $('tkRoom').value; }
                        else { if (!$('tkPair').value) return alert('Vaqt tanlang.'); body.pair = +$('tkPair').value; if (k === 'day') body.day = +$('tkDay').value; }
                        try {
                            const r = await api(BASE + '/block-tickets/' + t.id + '/resolve', 'POST', body);
                            alert(r.status === 'pending_registrar' ? 'Kunni o\'zgartirish so\'rovi registrator ofisiga yuborildi.' : 'Saqlandi: o\'zgarish LMS ichki jadvalida qo\'llandi.');
                            hsTicketOpen(t.id);
                        } catch (e) { alert(hsErrText(e)); }
                    };
                }
            }
            if ($('blockTicketsBtn')) $('blockTicketsBtn').onclick = () => hsTicketsList();
            if (TICKET_FROM_URL) setTimeout(() => hsTicketOpen(TICKET_FROM_URL), 300);
            // ===== Ichki jadval: haftani zichlash =====
            // Tashqi jadval (ext_day/ext_pair) andoza: guruhning shu kunidagi shablonda band, lekin
            // shu haftada bo'sh qolgan yarim-slotlariga keyingi darslar yuqoriga suriladi; so'ng
            // joylashmagan kartalar andozadagi joyiga yoki shu kunning pastroq bo'sh joyiga qo'yiladi.
            // Har bir ko'chirish oddiy week-override (server konflikt tekshiradi).
            // Kartaning o'z panjarasidagi haftalar soni (yo'nalish+kurs, bo'lmasa doska).
            const cardTermWeeks = c => {
                const g = gridForCard(c);
                return +(g && g.weeks) || +board.weeks || 15;
            };
            async function compactInternalWeek(W, scopeCards) {
                // Kartaning semestri tugagan haftaga ko'chirish yozilmasin.
                scopeCards = scopeCards.filter(c => W <= cardTermWeeks(c));
                const all = cards.filter(c => !isCycleCardAny(c));
                const key = c => c.id + '|' + W;
                const off = c => { const o = overrides[key(c)]; return !!(o && o.cancelled); };
                const place = c => {
                    const o = overrides[key(c)];
                    if (o) return o.cancelled ? null : { d: +o.day, p: +o.pair };
                    return c.day ? { d: +c.day, p: +c.pair } : null;
                };
                const slots = (c, g, d) => gkey(c, g) + '|' + d;
                const tmpl = {};
                all.forEach(c => {
                    if (!c.ext_day || !c.ext_pair) return;
                    cardGroups(c).forEach(g => {
                        const k = slots(c, g, c.ext_day), set = tmpl[k] || (tmpl[k] = new Set());
                        for (let i = 0; i < cardLen(c); i++) set.add(+c.ext_pair + i);
                    });
                });
                // Ma'ruza vaqti qat'iy: shu haftada o'tiladigan, hali joylanmagan ma'ruzaning tashqi joyi unga band
                // hisoblanadi — amaliy darslar u yerga surilib (yoki qo'yilib), ma'ruzani joysiz qoldirmasin.
                const lecWait = scopeCards.filter(c => c.training_type === 'lecture' && !off(c) && !place(c) && c.ext_day && c.ext_pair);
                const occ = () => {
                    const u = {};
                    all.forEach(c => {
                        if (off(c)) return;
                        const pl = place(c);
                        if (!pl) return;
                        cardGroups(c).forEach(g => {
                            const m = u[slots(c, g, pl.d)] || (u[slots(c, g, pl.d)] = new Map());
                            for (let i = 0; i < cardLen(c); i++) m.set(pl.p + i, c.id);
                        });
                    });
                    lecWait.forEach(c => {
                        if (place(c)) return;
                        cardGroups(c).forEach(g => {
                            const m = u[slots(c, g, +c.ext_day)] || (u[slots(c, g, +c.ext_day)] = new Map());
                            for (let i = 0; i < cardLen(c); i++) if (!m.has(+c.ext_pair + i)) m.set(+c.ext_pair + i, c.id);
                        });
                    });
                    return u;
                };
                const maxPair = Math.max(1, boardSchedule().filter(it => it.type === 'pair').length) || 12;
                const free = (u, c, d, p) => p >= 1 && p + cardLen(c) - 1 <= maxPair && cardGroups(c).every(g => {
                    const m = u[slots(c, g, d)];
                    for (let i = 0; i < cardLen(c); i++) { const x = m && m.get(p + i); if (x && x !== c.id) return false; }
                    return true;
                });
                const move = async (c, d, p) => {
                    try {
                        await api(BASE + '/cards/' + c.id + '/week-override', 'POST', { week: W, action: 'move', day: d, pair: p, layer: 'internal' });
                        overrides[key(c)] = Object.assign({}, overrides[key(c)] || {}, { card_id: c.id, week: W, day: d, pair: p, cancelled: false });
                        return true;
                    } catch (e) { return false; }
                };
                const res = { up: 0, placed: 0, left: 0 };
                // Ma'ruza vaqti qat'iy — tashqi jadvaldagi kun/vaqtda turadi (ma'ruza xonalari umumiy), surilmaydi.
                const act = scopeCards.filter(c => !off(c) && place(c) && c.training_type !== 'lecture')
                    .sort((a, b) => place(a).d - place(b).d || place(a).p - place(b).p);
                for (const c of act) {
                    const pl = place(c), u = occ();
                    let st = pl.p;
                    while (st > 1 && cardGroups(c).every(g => tmpl[slots(c, g, pl.d)] && tmpl[slots(c, g, pl.d)].has(st - 1)) && free(u, c, pl.d, st - 1)) st--;
                    // Para chegarasi faqat butun para (2+ yarim-slot) darslar uchun saqlanadi.
                    const step = cardLen(c) > 1 ? 2 : 1;
                    if (step === 2 && (pl.p - st) % 2) st++;
                    for (; st < pl.p; st += step) { if (await move(c, pl.d, st)) { res.up++; break; } }
                }
                // Joylanmagan kartalar blok bo'lib qo'yiladi: bir guruhning bir fandan (bo'lak harfisiz)
                // shu kundagi amaliy kartalari tashqi jadvalda yonma-yon bo'lsa, ichkida ham ketma-ket
                // turadi — bo'linib ketmaydi. Blok avval tashqi joyiga, band bo'lsa o'sha kunning ichida
                // eng yaqin bo'sh joyga qo'yiladi; boshqa kunga o'tkazilmaydi. Sig'maganlar joylanmagan
                // qoladi (qo'lda surish mumkin — qo'lda joylashga bu qoida qo'yilmaydi).
                const reset = async c => {
                    try {
                        await api(BASE + '/cards/' + c.id + '/week-override', 'POST', { week: W, action: 'reset' });
                        delete overrides[key(c)];
                    } catch (e) { }
                };
                const baseSubj = s => String(s || '').replace(/\s*\([abc]\)\s*$/, '').trim();
                const isBolak = c => /\([abc]\)\s*$/.test(String(c.subject_name || ''));
                const blockKey = c => c.training_type === 'lecture' || isBolak(c) ? 'k' + c.id
                    : cardGroups(c).map(g => gkey(c, g)).sort().join(',') + '|' + baseSubj(c.subject_name) + '|' + c.ext_day;
                // Kun hisobga olinmaydigan kalit: tashqi jadvalda joyi yo'q kartani (masalan KTR bo'yicha
                // 13–15-haftalardagi qo'shimcha 1 soatlik amaliy) shu fanning joylashgan qismiga ulash uchun.
                const sibKey = c => c.training_type === 'lecture' || isBolak(c) ? 'k' + c.id
                    : cardGroups(c).map(g => gkey(c, g)).sort().join(',') + '|' + baseSubj(c.subject_name);
                const sameBlock = (a, b) => sibKey(a) === sibKey(b) && !String(sibKey(a)).startsWith('k');
                const todo = scopeCards.filter(c => !off(c) && !place(c) && c.ext_day && c.ext_pair);
                const groups = {};
                todo.forEach(c => (groups[blockKey(c)] = groups[blockKey(c)] || []).push(c));
                const blocks = [];
                Object.values(groups).forEach(list => {
                    list.sort((a, b) => +a.ext_pair - +b.ext_pair || (+b.weeks || 0) - (+a.weeks || 0));
                    let cur = null;
                    list.forEach(c => {
                        if (cur && +c.ext_pair <= cur.end + 1) { cur.cs.push(c); cur.end = Math.max(cur.end, +c.ext_pair + cardLen(c) - 1); }
                        else { cur = { cs: [c], end: +c.ext_pair + cardLen(c) - 1 }; blocks.push(cur); }
                    });
                });
                // Avval ma'ruzalar (qat'iy vaqt), keyin amaliy bloklar.
                blocks.sort((a, b) => (b.cs[0].training_type === 'lecture') - (a.cs[0].training_type === 'lecture'));
                // Tashqi joyi yo'q, lekin shu haftada shu fanning boshqa qismi joylashgan kartalar —
                // o'sha kunga, joylashgan qismning darhol ketiga (yoki oldiga) qo'yiladi. Ular tashqi joyli
                // bloklardan keyin keladi: boshqa darsning tashqi joyini egallab qo'ymaydi va shu yurishda
                // endi joylashgan qismga ham ulanadi (qism joylash paytida qidiriladi).
                scopeCards.filter(c => !off(c) && !place(c) && !(c.ext_day && c.ext_pair))
                    .forEach(c => blocks.push({ cs: [c], extra: true }));
                for (const b of blocks) {
                    let d = +b.cs[0].ext_day;
                    if (b.extra) {
                        const sib = all.filter(x => x.id !== b.cs[0].id && !off(x) && place(x) && sameBlock(x, b.cs[0]))
                            .sort((x, y) => cardLen(y) - cardLen(x))[0];
                        if (!sib) continue;
                        d = place(sib).d;
                    }
                    const len = b.cs.reduce((s, c) => s + cardLen(c), 0);
                    // Shu fanning shu haftada allaqachon joylashgan qismi bo'lsa — blok uning yoniga yopishadi.
                    const placedSib = all.filter(x => !off(x) && place(x) && place(x).d === d && sameBlock(x, b.cs[0]));
                    // Para chegarasi butun para (2+ yarim-slot) darslar uchun saqlanadi (yuqoriga surishdagidek):
                    // blok tashqi joyi bilan bir xil juftlikda boshlanadi — yarim paraga siljimaydi (yonga yopishganda ham).
                    // Tashqi joyi yo'q kartada — para chegarasi 1-slotdan.
                    const p0 = +b.cs[0].ext_pair || 1;
                    const step = b.cs.some(c => cardLen(c) > 1) ? 2 : 1;
                    const aligned = s => (s - p0) % step === 0;
                    let starts;
                    if (placedSib.length) {
                        const lo = Math.min(...placedSib.map(x => place(x).p));
                        const hi = Math.max(...placedSib.map(x => place(x).p + cardLen(x)));
                        starts = [hi, lo - len].filter(aligned);
                    } else if (b.cs[0].training_type === 'lecture') {
                        // Ma'ruza — faqat tashqi jadvaldagi vaqtiga; band bo'lsa joylanmagan qoladi.
                        starts = [+b.cs[0].ext_pair];
                    } else {
                        starts = [];
                        for (let s = 1; s + len - 1 <= maxPair; s++) if (aligned(s)) starts.push(s);
                        starts.sort((x, y) => (x === p0 ? -1 : y === p0 ? 1 : 0) || Math.abs(x - p0) - Math.abs(y - p0) || x - y);
                    }
                    let ok = false;
                    for (const s of starts) {
                        if (s < 1 || s + len - 1 > maxPair) continue;
                        let p = s, fits = true;
                        const u = occ();
                        for (const c of b.cs) { if (!free(u, c, d, p)) { fits = false; break; } p += cardLen(c); }
                        if (!fits) continue;
                        p = s;
                        const done = [];
                        for (const c of b.cs) {
                            if (!(await move(c, d, p))) break;
                            done.push(c);
                            p += cardLen(c);
                        }
                        if (done.length === b.cs.length) { ok = true; break; }
                        for (const c of done) await reset(c);
                    }
                    if (ok) res.placed += b.cs.length; else res.left += b.cs.length;
                }
                return res;
            }

            // ===== Takliflar: joylanmagan kartalarni sig'dirish yo'llari (server hisoblaydi, foydalanuvchi tanlaydi) =====
            const SUG_LABEL = { slot: 'Bo\'sh vaqt', room: 'Boshqa xona', blocker: 'Darsni surish', day: 'Boshqa kun', week: 'Boshqa hafta', lecture: 'Ma\'ruzani surish', teacher: 'Boshqa o\'qituvchi' };
            const SUG_COLOR = { slot: '#059669', room: '#0284c7', blocker: '#7c3aed', day: '#d97706', week: '#b45309', lecture: '#a16207', teacher: '#dc2626' };
            async function openWeekSuggestions() {
                if (!board || !curWeek || weeklyLayer !== 'internal') return;
                let box = document.getElementById('ttSugModal');
                if (!box) {
                    box = document.createElement('div');
                    box.id = 'ttSugModal';
                    box.style.cssText = 'position:fixed;inset:0;z-index:70;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;padding:16px';
                    box.innerHTML = '<div style="background:#fff;border-radius:12px;width:min(1100px,100%);max-height:92vh;display:flex;flex-direction:column;box-shadow:0 20px 60px #0005">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #e2e8f0">' +
                        '<b id="ttSugTitle">Takliflar</b><button type="button" id="ttSugClose" style="font-size:20px;line-height:1">&times;</button></div>' +
                        '<div id="ttSugBody" style="overflow:auto;padding:12px 16px;font-size:12px"></div></div>';
                    document.body.appendChild(box);
                    box.addEventListener('click', ev => { if (ev.target === box || ev.target.id === 'ttSugClose') box.remove(); });
                }
                const body = box.querySelector('#ttSugBody');
                box.querySelector('#ttSugTitle').textContent = 'Takliflar — ' + curWeek + '-hafta';
                body.innerHTML = '<div style="color:#64748b">Hisoblanmoqda…</div>';
                const params = new URLSearchParams();
                [...selectedFaculties].forEach(v => params.append('faculty_names[]', v));
                [...selectedDirs].forEach(v => params.append('specialty_names[]', v));
                [...selectedCourses].forEach(v => params.append('courses[]', v));
                params.set('type', typeFilter);
                params.set('week', curWeek);
                let res;
                try { res = await api(BASE + '/boards/' + board.id + '/week-suggestions?' + params.toString()); }
                catch (e) { body.innerHTML = '<div style="color:#dc2626">' + esc(e.message) + '</div>'; return; }
                box.querySelector('#ttSugTitle').textContent = 'Takliflar — ' + res.week + '-hafta · joylanmagan: ' + res.total +
                    (res.shown < res.total ? ' (birinchi ' + res.shown + ' tasi)' : '');
                if (!res.cards.length) { body.innerHTML = '<div style="color:#059669">Bu haftada joylanmagan karta yo\'q.</div>'; return; }
                window.__ttSug = res.cards;
                body.innerHTML = res.cards.map((c, ci) =>
                    '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin-bottom:8px">' +
                    '<div><b>' + esc(c.subject) + '</b> <span style="color:#64748b">[' + (c.type === 'lecture' ? 'M' : 'A') + '] ' +
                    esc((c.groups || []).join(', ')) + ' · ' + esc(c.teacher || 'o\'qituvchi yo\'q') + (c.room ? ' · ' + esc(c.room) : '') + '</span></div>' +
                    (c.reason ? '<div style="color:#b91c1c;margin:2px 0 4px">' + esc(c.reason) + '</div>' : '') +
                    (c.suggestions.length ? c.suggestions.map((s, si) =>
                        '<div style="display:flex;gap:8px;align-items:flex-start;padding:3px 0;border-top:1px dashed #f1f5f9">' +
                        '<span style="flex:0 0 auto;font-size:10px;font-weight:700;color:#fff;border-radius:4px;padding:1px 6px;background:' + (SUG_COLOR[s.type] || '#475569') + '">' + (SUG_LABEL[s.type] || s.type) + '</span>' +
                        '<span style="flex:1 1 auto">' + esc(s.title) + (s.note ? ' <i style="color:#64748b">(' + esc(s.note) + ')</i>' : '') + '</span>' +
                        '<button type="button" data-sug="' + ci + ':' + si + '" style="flex:0 0 auto;font-size:11px;border:1px solid #cbd5e1;border-radius:6px;padding:1px 8px;background:#f8fafc">Qo\'llash</button></div>'
                    ).join('') : '<div style="color:#64748b">Taklif topilmadi — qo\'lda joylash kerak.</div>') +
                    '</div>').join('');
                body.querySelectorAll('[data-sug]').forEach(btn => btn.onclick = async () => {
                    const [ci, si] = btn.dataset.sug.split(':').map(Number);
                    const sug = window.__ttSug[ci].suggestions[si];
                    if (sug.type === 'teacher' && !confirm('O\'qituvchi butun semestr uchun almashadi. Davom etamizmi?')) return;
                    if (sug.type === 'lecture' && !confirm('Ma\'ruza vaqti tashqi jadvaldan farq qiladi. Davom etamizmi?')) return;
                    if (sug.type === 'week' && !confirm('KTR taqsimoti o\'zgaradi (' + sug.note + '). Davom etamizmi?')) return;
                    btn.disabled = true; btn.textContent = '…';
                    try {
                        for (const a of sug.actions) {
                            if (a.op === 'move') {
                                await api(BASE + '/cards/' + a.card_id + '/week-override', 'POST',
                                    { week: a.week, action: 'move', day: a.day, pair: a.pair, layer: 'internal', auditorium_code: a.auditorium_code });
                            } else if (a.op === 'reset') {
                                await api(BASE + '/cards/' + a.card_id + '/week-override', 'POST', { week: a.week, action: 'reset', layer: 'internal' });
                            } else if (a.op === 'cancel') {
                                await api(BASE + '/cards/' + a.card_id + '/week-override', 'POST', { week: a.week, action: 'cancel', layer: 'internal' });
                            } else if (a.op === 'teacher') {
                                await api(BASE + '/cards/' + a.card_id + '/update', 'POST', { teacher_id: a.teacher_id });
                            }
                        }
                    } catch (e) {
                        alert('Bajarilmadi: ' + e.message + (sug.actions.length > 1 ? '\n\nTaklifning bir qismi bajarilgan bo\'lishi mumkin — jadvalni tekshiring.' : ''));
                    }
                    await loadBoard(board.id);
                    openWeekSuggestions();
                });
            }
            if ($('suggestWeekBtn')) $('suggestWeekBtn').onclick = openWeekSuggestions;
            if ($('compactWeekBtn')) $('compactWeekBtn').onclick = async function () {
                if (!board || !curWeek || weeklyLayer !== 'internal') return;
                const scope = visibleSpecCards().filter(c => !isCycleCardAny(c));
                // Faqat tanlangan qamrovdagi panjaralar semestri (boshqa, uzunroq panjara emas).
                const total = Math.max(curWeek, ...scope.map(cardTermWeeks));
                if (!confirm('Qamrov: ' + scopeLabelText() + '\n\nIchki jadvalda bo\'sh qolgan dars joylariga keyingi darslar suriladi, ' +
                    'joylashmagan kartalar o\'sha kunning ichida bo\'sh joyga ketma-ket (bo\'linmasdan) qo\'yiladi.\n\nDavom etamizmi?')) return;
                const allWeeks = total > 1 && confirm('Barcha haftalarga (1–' + total + ') qo\'llansinmi?\n\nOK — barcha haftalar, Bekor — faqat ' + curWeek + '-hafta');
                const weeks = allWeeks ? Array.from({ length: total }, (_, i) => i + 1) : [curWeek];
                this.disabled = true;
                const sum = { up: 0, placed: 0, left: 0 };
                try {
                    for (const W of weeks) {
                        $('boardMsg').textContent = 'Zichlanmoqda: ' + W + '-hafta…';
                        const r = await compactInternalWeek(W, scope);
                        sum.up += r.up; sum.placed += r.placed; sum.left += r.left;
                    }
                    $('boardMsg').textContent = 'Zichlash: ' + sum.up + ' ta dars yuqoriga surildi, ' + sum.placed + ' ta karta joylandi' +
                        (sum.left ? ', ' + sum.left + ' tasiga bo\'sh joy topilmadi' : '');
                    await loadBoard(board.id);
                } catch (e) { alert('Xatolik: ' + e.message); }
                finally { this.disabled = false; }
            };
            $('weekSel').onchange = function () {
                curWeek = +this.value || 0;
                selected = null;
                showSkipped = false;
                fillWeekSel();
                renderAll();
            };
            // "Bu haftada o'tilmaydi" kartalarini panelda ko'rsatish/yashirish
            $('skipToggle').onclick = function () {
                showSkipped = !showSkipped;
                renderPanel();
            };
            // Joylashmagan kartalar paneli filtrlari: fan, oqim, guruh, dars turi
            const pnFilter = { subject: '', oqim: '', groups: new Set(), type: '' };
            const pnMatch = c =>
                (!pnFilter.subject || c.subject_name === pnFilter.subject) &&
                (!pnFilter.oqim || (c.oqim_label || '') === pnFilter.oqim) &&
                (!pnFilter.groups.size || cardGroups(c).some(g => pnFilter.groups.has(g))) &&
                (!pnFilter.type || c.training_type === pnFilter.type);
            $('pnSubject').onchange = function () { pnFilter.subject = this.value; renderPanel(); };
            $('pnType').onchange = function () { pnFilter.type = this.value; renderPanel(); };
            $('pnOqim').onchange = function () { pnFilter.oqim = this.value; renderPanel(); };
            // Guruhlar — bir nechtasini belgilash mumkin
            $('pnGroupMenu').addEventListener('change', ev => {
                if (!ev.target.matches('input[type=checkbox]')) return;
                if (ev.target.checked) pnFilter.groups.add(ev.target.value);
                else pnFilter.groups.delete(ev.target.value);
                renderPanel();
            });
            $('pnGroupMenu').addEventListener('click', ev => {
                if (ev.target.dataset.pnGroupsClear === undefined) return;
                pnFilter.groups.clear();
                renderPanel();
            });
            $('pnReset').onclick = function () {
                pnFilter.subject = pnFilter.type = pnFilter.oqim = '';
                pnFilter.groups.clear();
                renderPanel();
            };
            // Guruh ro'yxatini chizadi; paneldan yo'qolgan guruhlar tanlovdan chiqadi.
            function fillPnGroups(values) {
                [...pnFilter.groups].forEach(g => { if (!values.includes(g)) pnFilter.groups.delete(g); });
                const n = pnFilter.groups.size;
                $('pnGroupMenu').innerHTML =
                    (n ? '<div class="tt-dd-tools"><button type="button" data-pn-groups-clear="1">Tanlovni tozalash</button></div>' : '') +
                    (values.map(g =>
                        '<label class="tt-dd-item"><input type="checkbox" value="' + esc(g) + '"' + (pnFilter.groups.has(g) ? ' checked' : '') + '>' +
                        esc(g) + '</label>').join('') || '<div class="tt-dd-empty">—</div>');
                const sum = !n ? 'Barcha guruhlar' : n === 1 ? [...pnFilter.groups][0] : n + ' ta guruh';
                $('pnGroupBtn').innerHTML = '<span class="tt-dd-sum">' + esc(sum) + '</span> <span class="tt-dd-caret">▾</span>';
            }
            // Tanlov ro'yxatini paneldagi kartalardan to'ldiradi; tanlangan qiymat
            // endi yo'q bo'lsa (masalan, joylashtirilib bo'lindi) filtr tozalanadi.
            function fillPnSelect(id, allLabel, values, key) {
                if (pnFilter[key] && !values.includes(pnFilter[key])) pnFilter[key] = '';
                $(id).innerHTML = '<option value="">' + allLabel + '</option>' +
                    values.map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
                $(id).value = pnFilter[key];
            }
            $('viewMode').onchange = function () {
                viewMode = this.value;
                selected = null;
                try { buildSubjectColors(colorScopeCards()); } catch (e) { }
                if (typeof renderWeeklyLayerUi === 'function') renderWeeklyLayerUi();
                if (board) fillWeekSel();
                renderGrid();
                syncUrl();
            };
            // Kartaning tanlangan haftadagi (yoki shablon) effektiv joylashuvi: {day,pair} yoki null
            // Karta QAYSI haftalarda o'tiladi (hafta istisnolaridan: bekor qilinmaganlari).
            // Ma'ruza haftalari semestr bo'ylab tarqatilgan — kartada "6 hafta: 1,3,6..."
            // ko'rinishida chiqadi. Ro'yxat uzun bo'lsa qisqartiriladi.
            function activeWeeksOf(c, totalWeeks) {
                const total = Math.max(1, Math.min(+totalWeeks || 1, +c.term_weeks || +totalWeeks || 1));
                const out = [];
                for (let w = 1; w <= total; w++) {
                    const ov = overrides[c.id + '|' + w];
                    if (!(ov && ov.cancelled)) out.push(w);
                }
                // Hamma haftada o'tilsa — raqamlarni ko'rsatish shart emas
                if (out.length >= total) return [];
                return out.length > 10 ? out.slice(0, 10).concat(['…']) : out;
            }

            function effPlace(c) {
                if (curWeek && c.term_weeks && curWeek > +c.term_weeks) return null;
                if (!curWeek) return c.day ? { day: c.day, pair: c.pair } : null;
                const ov = overrides[c.id + '|' + curWeek];
                // Tashqi qatlamda haftaga xos ko'chirishlar (ichki jadvalniki) hisobga olinmaydi, faqat o'tilmaydigan haftalar.
                if (weeklyLayer === 'external') {
                    if ((ov && ov.cancelled) || !c.day) return null;
                    // Shu haftada o'tilmaydigan dars (masalan ma'ruza) bo'shatgan joyga
                    // keyingi darslar yuqoriga suriladi — kun o'rtasida bo'sh para qolmaydi.
                    const sh = externalCompactMap()[c.id];
                    return { day: c.day, pair: sh || c.pair };
                }
                if (ov) return ov.cancelled ? null : { day: ov.day, pair: ov.pair };
                return c.day ? { day: c.day, pair: c.pair } : null;
            }
            // Tashqi qatlam, alohida hafta: shablonda band bo'lgan, lekin shu haftada
            // bo'shab qolgan (o'tilmaydigan karta egallagan yoki yuqoriga surilgan karta
            // tashlab ketgan) yarim-slotlarga keyingi darslar ketma-ket suriladi.
            // Shablondagi ataylab bo'sh joylar (masalan kun IV paradan boshlansa) to'ldirilmaydi.
            // Natija: { cardId: yangi pair } — faqat surilgan kartalar uchun.
            let compactCache = null;
            function externalCompactMap() {
                const sig = curWeek + '|' + weeklyLayer + '|' + cards.length;
                if (compactCache && compactCache.sig === sig) return compactCache.map;
                const map = {};
                if (curWeek && weeklyLayer === 'external') {
                    const tmpl = {};   // gkey|day -> Set(shablonda band yarim-slotlar)
                    const act = [];
                    cards.forEach(c => {
                        if (!c.day || !c.pair || isCycleCardAny(c)) return;
                        const len = cardLen(c);
                        cardGroups(c).forEach(g => {
                            const k = gkey(c, g) + '|' + c.day;
                            const set = tmpl[k] || (tmpl[k] = new Set());
                            for (let i = 0; i < len; i++) set.add(c.pair + i);
                        });
                        if (!weekCancelled(c)) act.push(c);
                    });
                    act.sort((a, b) => (a.day - b.day) || (a.pair - b.pair) || (cardLen(b) - cardLen(a)));
                    const used = {};   // gkey|day -> Set(shu haftada haqiqatan band slotlar)
                    act.forEach(c => {
                        const len = cardLen(c);
                        const keys = cardGroups(c).map(g => gkey(c, g) + '|' + c.day);
                        let start = c.pair;
                        // Har bir guruh uchun yuqoridagi slot bo'sh va shablonda band bo'lsa — bir qadam yuqoriga
                        while (start > 1 && keys.length && keys.every(k =>
                            tmpl[k].has(start - 1) && !(used[k] && used[k].has(start - 1)))) start--;
                        // Juft yarim-slot chegarasini saqlash (para o'rtasidan boshlanmasin)
                        if (len > 1 && (c.pair - start) % 2) start++;   // yarim para dars yarim-slot bo'shliqqa ham suriladi
                        if (start !== c.pair) map[c.id] = start;
                        keys.forEach(k => {
                            const set = used[k] || (used[k] = new Set());
                            for (let i = 0; i < len; i++) set.add(start + i);
                        });
                    });
                }
                compactCache = { sig, map };
                return map;
            }
            const resetCompactCache = () => { compactCache = null; };
            // Tanlangan haftada vaqt bilan birga auditoriya ham individual
            // override'dan olinadi; shablon ko'rinishida bazaviy xona qoladi.
            function effectiveCard(c) {
                if (!curWeek || weeklyLayer === 'external') return c;
                const ov = overrides[c.id + '|' + curWeek];
                if (!ov || ov.cancelled || !ov.auditorium_code) return c;
                return {
                    ...c,
                    auditorium_code: ov.auditorium_code,
                    auditorium_name: ov.auditorium_name || ov.auditorium_code,
                    auditorium_volume: ov.auditorium_volume || null,
                };
            }
            // Karta shu haftada shablondan farq qiladimi (individual)?
            const hasWeekOverride = c => curWeek && !!overrides[c.id + '|' + curWeek];
            // Karta shu haftada umuman o'tilmaydimi (bekor qilingan / almashinuvchi
            // fanning "bo'sh" haftasi)? Bunday karta joylashmagan hisoblanmaydi —
            // u shu haftaga tegishli emas, shuning uchun panel va statistikadan
            // butunlay chiqarib tashlanadi.
            const weekCancelled = (c) => {
                if (!curWeek) return false;
                if (c.term_weeks && curWeek > +c.term_weeks) return true;
                const ov = overrides[c.id + '|' + curWeek];
                return !!(ov && ov.cancelled);
            };
            // Tanlangan haftada haqiqatan o'tiladigan kartalar (shablon ko'rinishida — hammasi)
            const weekActiveCards = () => visibleSpecCards().filter(c => !weekCancelled(c));
            // Panelda "bu haftada o'tilmaydi" kartalari ham ko'rsatilsinmi (odatda — yo'q)
            let showSkipped = false;
            // Panjara saqlash endi Sozlamalar modalidagi har bir kurs katagida bajariladi.

            // Ko'rinayotgan tanlov qamrovi — backendga yuboriladigan massivlar
            // (tanlangan fakultet/yo'nalish/kurs). "Butun doska" da qamrov yuborilmaydi.
            function scopeBody() {
                return {
                    faculty_names: [...selectedFaculties],
                    specialty_names: [...selectedDirs],
                    courses: [...selectedCourses].sort((a, b) => a - b),
                };
            }
            function scopeLabelText() {
                const fl = selectedFaculties.size > 1 ? (selectedFaculties.size + ' fakultet') : facLabel([...selectedFaculties][0]);
                const dl = selectedDirs.size > 1 ? (selectedDirs.size + ' yo\'nalish') : ([...selectedDirs][0] || '—');
                const cl = [...selectedCourses].sort((a, b) => a - b).join(',') + '-kurs';
                return fl + ' · ' + dl + ' · ' + cl;
            }

            // Ko'rinayotgan qamrovdagi barcha joylashuvlarni bo'shatish (panelga qaytarish)
            $('unplaceBtn').onclick = async function () {
                if (!board || !curSpec) return;
                // "Faqat meniki" ko'rinishi to'liq emas, bo'shatish esa butun qamrovga
                // (boshqalarning darslariga ham) ta'sir qiladi — bu rejimda o'chirilgan.
                if (mineOnly) {
                    alert('"' + scopeModeLabel() + '" rejimida bo‘shatish o‘chirilgan. Avval "Butun doska" ga qayting.');
                    return;
                }
                if (viewMode === 'cycle') {
                    // Bu qatlam faqat ko'rish uchun — server ham rad etadi (403)
                    if (cyclePlanData && cyclePlanData.can_edit === false) {
                        alert('Bu qatlamdagi sikl bloklarini bo‘shatish huquqingiz yo‘q.');
                        return;
                    }
                    if (!confirm('Tanlangan guruhlarning sikl bloklari panelga qaytarilsinmi?')) return;
                    this.disabled = true;
                    $('autoMsg').textContent = 'Sikl bloklari bo‘shatilmoqda...';
                    try {
                        await loadCyclePlan({ clear: true });
                        $('autoMsg').textContent = 'Sikl bloklari panelga qaytarildi.';
                    } catch (e) {
                        $('autoMsg').textContent = '';
                        alert('Sikl bloklarini bo‘shatib bo‘lmadi: ' + e.message);
                    }
                    this.disabled = false;
                    return;
                }
                const whole = $('autoScope').checked;
                const scopeLabel = whole ? 'Butun doska' : scopeLabelText();
                if (!confirm(scopeLabel + ' bo\'yicha barcha joylashuvlar bo\'shatilib, kartochkalar panelga qaytariladi. Davom etamizmi?')) return;
                this.disabled = true; $('autoMsg').textContent = 'Bo\'shatilmoqda...';
                try {
                    const body = { layer: weeklyLayer };
                    if (!whole) Object.assign(body, scopeBody());
                    if (typeFilter !== 'all') body.training_type = typeFilter;
                    const j = await api(BASE + '/boards/' + board.id + '/unplace', 'POST', body);
                    await loadBoard(board.id);
                    $('autoMsg').textContent = (j.unplaced || 0) + ' ta joylashuv bo\'shatildi';
                } catch (e) { $('autoMsg').textContent = ''; alert('Xatolik: ' + e.message); }
                this.disabled = false;
            };

            // ===== Yordamchilar =====
            let viewMode = 'group';   // group | teacher | room | subject (jadval kesimi)
            // Ko'rinadigan kartalar — tanlangan fakultet(lar) × yo'nalish(lar) × kurs(lar) kesishmasi.
            const multiSpec = () => selectedDirs.size > 1;
            // Sikl rejimidagi fan — haftalik panjaraga tushmaydi (faqat sikl kalendarida).
            const isCycleCard = c => (subjectSettings[subjModeKey(c.specialty_name, c.course, c.subject_name)] || {}).mode === 'cycle';
            // Bo'lakli fan ("Fan (a)") sozlamasi asosiy nom bilan saqlangan bo'lishi mumkin.
            const isCycleCardAny = c => isCycleCard(c)
                || isCycleCard(Object.assign({}, c, { subject_name: String(c.subject_name || '').replace(/\s*\(\p{L}\)\s*$/u, '') }));
            const specCards = () => cards.filter(c =>
                selectedFaculties.has(c.faculty_name || '') &&
                selectedDirs.has(c.specialty_name) &&
                selectedCourses.has(c.course) &&
                !isCycleCardAny(c));
            const cardGroups = c => (c.group_names && c.group_names.length) ? c.group_names : (c.group_name ? [c.group_name] : []);
            // Guruh identifikatori. Guruh NOMI fakultetlar bo'ylab takrorlanishi mumkin
            // (mas. 1-kurs "1K-01a (o'z)" ham 1-son, ham 2-son davolashda bor), shuning
            // uchun panjara/konflikt kaliti nom emas — fakultet+yo'nalish+kurs+nom.
            const gkey = (c, g) => (c.faculty_name || '') + '¦' + (c.specialty_name || '') + '¦' + c.course + '¦' + g;
            const cardGKeys = c => cardGroups(c).map(g => gkey(c, g));
            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy) — panel, panjara va statistikaga ta'sir qiladi
            let typeFilter = 'all';
            const typeVisible = c => typeFilter === 'all' || c.training_type === typeFilter;
            const visibleSpecCards = () => specCards().filter(typeVisible);

            // Fan rejimi Darslar jadvalidagi har bir qator ichida boshqariladi.
            function subjectModeParamsHtml(setting) {
                const mode = setting.mode || 'normal';
                if (mode === 'alternate') {
                    return '<span class="asc-subj-param">Guruh <input class="asc-subj-group" maxlength="40" value="' + esc(setting.rotation_group || '') + '" placeholder="A"></span>' +
                        '<span class="asc-subj-param">Hafta <input type="number" class="asc-subj-occ" min="1" max="60" value="' + (setting.occurrences ?? '') + '" placeholder="—"></span>';
                }
                if (mode === 'cycle') {
                    return '<span class="asc-subj-param">Sikl <input type="number" class="asc-subj-cycle" min="1" max="120" value="' + (setting.cycle_days ?? '') + '" placeholder="kun"></span>';
                }
                return '<span class="text-slate-400">—</span>';
            }

            async function saveSubjectModeRow(tr, row) {
                const mode = tr.querySelector('.asc-subj-mode').value;
                const seasonSelect = tr.querySelector('.asc-subj-season');
                const body = {
                    specialty_name: row.specialty_name,
                    course: +row.course,
                    subject_name: row.subject_name,
                    mode,
                    season: seasonSelect ? seasonSelect.value : subjectSeasonOf(row),
                };
                if (mode === 'alternate') {
                    const group = tr.querySelector('.asc-subj-group');
                    const occurrences = tr.querySelector('.asc-subj-occ');
                    if (group && group.value.trim()) body.rotation_group = group.value.trim();
                    if (occurrences && occurrences.value) body.occurrences = +occurrences.value;
                } else if (mode === 'cycle') {
                    const cycle = tr.querySelector('.asc-subj-cycle');
                    if (cycle && cycle.value) body.cycle_days = +cycle.value;
                }
                const status = tr.querySelector('.asc-subj-status');
                status.textContent = '…';
                status.className = 'asc-subj-status text-slate-400';
                try {
                    const saved = await api(BASE + '/boards/' + board.id + '/subject-setting', 'POST', body);
                    const key = subjModeKey(row.specialty_name, row.course, row.subject_name);
                    const effectiveSeason = (saved && saved.season) || body.season || ((board && board.semester_parity) || 'kuzgi');
                    row.season = effectiveSeason;
                    if (mode === 'normal' && effectiveSeason === ((board && board.semester_parity) || 'kuzgi')) delete subjectSettings[key];
                    else subjectSettings[key] = { ...subjectSettingOf(row), ...body, season: effectiveSeason };
                    repaintSubjectSeason(tr, row, effectiveSeason);
                    status.textContent = '✓';
                    status.className = 'asc-subj-status text-emerald-600';
                } catch (e) {
                    status.textContent = '✕';
                    status.className = 'asc-subj-status text-red-600';
                    repaintSubjectSeason(tr, row, subjectSeasonOf(row));
                    alert('Xatolik: ' + e.message);
                }
            }

            function autoCycleStorageKey() {
                return board ? 'lms:auto-cycle-backup:' + board.id : '';
            }

            function loadAutoCycleState() {
                if (!board || autoCycleBackup !== null) return;
                try {
                    const raw = localStorage.getItem(autoCycleStorageKey());
                    if (!raw) return;
                    const saved = JSON.parse(raw);
                    if (Array.isArray(saved.backup) && saved.backup.length) {
                        autoCycleBackup = saved.backup;
                        autoCycleApplied = !!saved.applied;
                    }
                } catch (e) {
                    localStorage.removeItem(autoCycleStorageKey());
                }
            }

            function persistAutoCycleState() {
                if (!board) return;
                try {
                    if (autoCycleApplied && autoCycleBackup && autoCycleBackup.length) {
                        localStorage.setItem(autoCycleStorageKey(), JSON.stringify({
                            applied: true,
                            backup: autoCycleBackup,
                        }));
                    } else {
                        localStorage.removeItem(autoCycleStorageKey());
                    }
                } catch (e) { }
            }

            function syncAutoCycleState() {
                loadAutoCycleState();
                const candidates = autoCycleCandidates();
                if (!candidates.length) return;
                const hasCycle = candidates.some(item =>
                    (subjectSettingOf(item.row).mode || 'normal') === 'cycle');
                if (hasCycle) {
                    autoCycleApplied = true;
                } else if (autoCycleBackup) {
                    autoCycleApplied = false;
                    autoCycleBackup = null;
                    persistAutoCycleState();
                }
            }

            function autoCycleCandidates() {
                return ascData.filter(row => [4, 5, 6].includes(+row.course))
                    .map(row => {
                        const total = (+row.lecture || 0) + (+row.practice || 0)
                            + (+row.laboratory || 0) + (+row.seminar || 0);
                        return { row, total, days: Math.max(1, Math.ceil(total / 6)) };
                    })
                    .filter(item => item.total > 0);
            }

            async function autoCycleSubjects() {
                if (!board || ascType !== 'subjects') return;
                const candidates = autoCycleCandidates();
                autoCycleBackup = candidates.map(item => ({
                    row: item.row,
                    previous: { ...subjectSettingOf(item.row) },
                }));
                if (!candidates.length) {
                    $('ascFootMsg').textContent = '4-6 kurslar uchun soatli fan topilmadi.';
                    return;
                }
                if (!confirm('4-6 kursdagi ' + candidates.length + ' ta fan Sikl rejimiga o‘tkaziladi. Kunlar jami soat / 6 bo‘yicha yuqoriga yaxlitlanadi. Davom etamizmi?')) {
                    return;
                }
                const button = $('aBtnAutoCycle');
                if (button) button.disabled = true;
                let savedCount = 0;
                const failed = [];
                try {
                    for (const item of candidates) {
                        const row = item.row;
                        const body = {
                            specialty_name: row.specialty_name,
                            course: +row.course,
                            subject_name: row.subject_name,
                            mode: 'cycle',
                            cycle_days: item.days,
                            season: subjectSeasonOf(row),
                        };
                        try {
                            const saved = await api(BASE + '/boards/' + board.id + '/subject-setting', 'POST', body);
                            const key = subjModeKey(row.specialty_name, row.course, row.subject_name);
                            const season = (saved && saved.season) || body.season;
                            subjectSettings[key] = { ...subjectSettingOf(row), ...body, season };
                            savedCount++;
                        } catch (e) {
                            failed.push(row.subject_name + ' (' + row.course + '-kurs): ' + e.message);
                        }
                        $('ascFootMsg').textContent = 'Sikl sozlanmoqda: ' + savedCount + '/' + candidates.length;
                    }
                    renderAscTable();
                    autoCycleApplied = savedCount > 0;
                    persistAutoCycleState();
                    renderAscButtons();
                    $('ascFootMsg').textContent = 'Avtomatik sikl sozlandi: ' + savedCount + ' ta fan' +
                        (failed.length ? '; xato: ' + failed.length : '') + '. 6 soat = 1 kun.';
                    if (failed.length) {
                        alert('Ba’zi fanlar saqlanmadi:\\n' + failed.slice(0, 5).join('\\n'));
                    }
                } finally {
                    if (button) button.disabled = false;
                }
            }

            // ===== Sikl (4-6 kurs) kalendar ko‘rinishi =====
            async function toggleAutoCycleSubjects() {
                if (autoCycleApplied) {
                    await restoreAutoCycleSubjects();
                    return;
                }
                await autoCycleSubjects();
            }

            async function restoreAutoCycleSubjects() {
                const restoreItems = (autoCycleBackup && autoCycleBackup.length)
                    ? autoCycleBackup
                    : autoCycleCandidates()
                        .filter(item => (subjectSettingOf(item.row).mode || 'normal') === 'cycle')
                        .map(item => ({
                            row: item.row,
                            previous: { mode: 'normal', season: ((board && board.semester_parity) || 'kuzgi') },
                        }));
                if (!restoreItems.length) return;
                if (!confirm('4-6 kurs fanlarining avtomatik Sikldan oldingi rejimi va kunlari tiklansinmi?')) return;
                const button = $('aBtnAutoCycle');
                if (button) button.disabled = true;
                let restoredCount = 0;
                const failed = [];
                try {
                    for (const item of restoreItems) {
                        const row = item.row;
                        const previous = item.previous || {};
                        const mode = previous.mode || 'normal';
                        const body = {
                            specialty_name: row.specialty_name,
                            course: +row.course,
                            subject_name: row.subject_name,
                            mode,
                            season: previous.season || ((board && board.semester_parity) || 'kuzgi'),
                        };
                        if (mode === 'alternate') {
                            if (previous.rotation_group) body.rotation_group = previous.rotation_group;
                            if (previous.occurrences) body.occurrences = previous.occurrences;
                        } else if (mode === 'cycle' && previous.cycle_days) {
                            body.cycle_days = previous.cycle_days;
                        }
                        try {
                            await api(BASE + '/boards/' + board.id + '/subject-setting', 'POST', body);
                            const key = subjModeKey(row.specialty_name, row.course, row.subject_name);
                            if (mode === 'normal' && body.season === ((board && board.semester_parity) || 'kuzgi')) {
                                delete subjectSettings[key];
                            } else {
                                subjectSettings[key] = { ...previous, ...body };
                            }
                            restoredCount++;
                        } catch (e) {
                            failed.push(row.subject_name + ' (' + row.course + '-kurs): ' + e.message);
                        }
                        $('ascFootMsg').textContent = 'Avvalgi holat tiklanmoqda: ' + restoredCount + '/' + restoreItems.length;
                    }
                    if (!failed.length) {
                        autoCycleApplied = false;
                        autoCycleBackup = null;
                    }
                    persistAutoCycleState();
                    renderAscTable();
                    renderAscButtons();
                    $('ascFootMsg').textContent = 'Avtomatik Sikldan oldingi holatga ' + restoredCount + ' ta fan qaytarildi.' +
                        (failed.length ? ' Xato: ' + failed.length : '');
                    if (failed.length) {
                        alert('Ba’zi fanlar qaytarilmadi:\\n' + failed.slice(0, 5).join('\\n'));
                    }
                } finally {
                    if (button) button.disabled = false;
                }
            }

            let cyclePlanData = null;
            let cycleViewMode = 'flow';
            // Sikl jadvalida bitta guruh/oqim bo'yicha filtr ("Guruh" sarlavhasi orqali).
            // Qiymat — qatorning noyob row_key'i: qamrovda bir xil nomli guruh boshqa
            // yo'nalish/kursda ham bo'lishi mumkin.
            let cycleRowFilter = null;
            // Qator yorlig'i — guruh nomi; bir xil nomli qatorlarga yo'nalish va kurs qo'shiladi.
            function cycleRowLabels(rows) {
                const count = {};
                rows.forEach(r => { count[r.group] = (count[r.group] || 0) + 1; });
                const labels = {};
                rows.forEach(r => { labels[r.row_key] = count[r.group] > 1 ? r.group + ' · ' + r.specialty + ', ' + r.course + '-kurs' : r.group; });
                return labels;
            }
            function closeCycRowPop() { const p = document.getElementById('cycRowPop'); if (p) p.remove(); }
            function openCycRowPop(anchor) {
                closeCycRowPop();
                const planRows = (cyclePlanData && cyclePlanData.rows) || [];
                const labels = cycleRowLabels(planRows);
                const names = planRows.map(r => ({ key: r.row_key, label: labels[r.row_key] }));
                const pop = document.createElement('div');
                pop.id = 'cycRowPop';
                pop.innerHTML = '<input type="text" class="cyc-rp-q" placeholder="Qidirish: masalan 01a" autocomplete="off">'
                    + '<div class="cyc-rp-list"></div>';
                document.body.appendChild(pop);
                const r = anchor.getBoundingClientRect();
                pop.style.left = Math.max(8, r.left) + 'px';
                pop.style.top = (r.bottom + 4) + 'px';
                const list = pop.querySelector('.cyc-rp-list');
                const q = pop.querySelector('.cyc-rp-q');
                const draw = () => {
                    const t = q.value.trim().toLowerCase();
                    const hits = names.filter(n => !t || n.label.toLowerCase().includes(t));
                    list.innerHTML = (t ? '' : '<button type="button" data-rp="" class="' + (!cycleRowFilter ? 'is-on' : '') + '">Hammasi (' + names.length + ')</button>')
                        + hits.map(n => '<button type="button" data-rp="' + esc(n.key) + '" class="' + (n.key === cycleRowFilter ? 'is-on' : '') + '">' + esc(n.label) + '</button>').join('')
                        + (hits.length ? '' : '<div class="cyc-rp-empty">Topilmadi</div>');
                };
                const pick = name => {
                    cycleRowFilter = name || null;
                    closeCycRowPop();
                    if (cyclePlanData) { renderCyclePlan(cyclePlanData); renderCycleCards(cyclePlanData); }
                };
                draw();
                q.addEventListener('input', draw);
                q.addEventListener('keydown', ev => {
                    if (ev.key === 'Enter') {
                        const first = list.querySelector('button[data-rp]:not([data-rp=""])') || list.querySelector('button[data-rp]');
                        if (first) pick(first.dataset.rp);
                    }
                    if (ev.key === 'Escape') closeCycRowPop();
                });
                list.addEventListener('click', ev => { const b = ev.target.closest('button[data-rp]'); if (b) pick(b.dataset.rp); });
                setTimeout(() => q.focus(), 0);
            }
            document.addEventListener('click', ev => {
                const btn = ev.target.closest && ev.target.closest('#cycRowFilterBtn');
                if (btn) { ev.preventDefault(); if (document.getElementById('cycRowPop')) closeCycRowPop(); else openCycRowPop(btn); return; }
                const clr = ev.target.closest && ev.target.closest('#cycRowFilterClear');
                if (clr) {
                    ev.preventDefault(); cycleRowFilter = null; closeCycRowPop();
                    if (cyclePlanData) { renderCyclePlan(cyclePlanData); renderCycleCards(cyclePlanData); }
                    return;
                }
                if (!(ev.target.closest && ev.target.closest('#cycRowPop'))) closeCycRowPop();
            });
            // Server ichki jadvalni guruhcha qatorlarida qaytaradi (view: group) — API so'rovlari shu bilan.
            function cycleEffView() { return (cyclePlanData && cyclePlanData.view) || cycleViewMode; }   // 'flow' — oqim bo'yicha, 'group' — har subguruh alohida qator
            // Sikl jadvali qatlami: null — rol bo'yicha server tanlaydi
            // (o'quv bo'limi — tashqi, kafedra/o'qituvchi — ichki), keyin tugma bilan almashtiriladi.
            let cycleLayer = null;
            function cycleLayerParams() { return cycleLayer ? { layer: cycleLayer } : {}; }
            function renderCycleLayerUi(j) {
                if (j && j.layer) cycleLayer = j.layer;
                [['cycLayerExternal', 'external'], ['cycLayerInternal', 'internal']].forEach(([id, l]) => {
                    const b = $(id);
                    if (!b) return;
                    const on = cycleLayer === l;
                    b.className = 'px-2 py-1 ' + (on ? 'bg-blue-600 font-semibold text-white' : 'bg-white text-gray-500');
                });
                if ($('cycLayerRo')) $('cycLayerRo').classList.toggle('hidden', !(j && j.can_edit === false));
                // Ichki jadval doim guruhcha bo'yicha — Oqim/Guruh almashtirgichi kerak emas.
                const viewSwitch = $('cycViewFlow') ? $('cycViewFlow').parentElement : null;
                if (viewSwitch && j) viewSwitch.classList.toggle('hidden', Boolean(j.sub_rows));
            }
            let cyclePairsShown = null;   // ko'rinadigan para qatorlari (sukut: sikl kuni = 6 soat)
            let cycleBandsResize = null;  // kun chiziqlarini qayta chizuvchi resize ishlovchisi
            let cycleDayGeometry = [];    // kun ustunlari o'lchovi: [{left, width}]
            // Dam olish (yakshanba/bayram) ustunlarini yashirish — faqat ko'rinish.
            // Ichki (kafedra) jadvalda ham ishlaydi: blok ichidagi kun to'ri yashirilgan kunlarni o'tkazib yuboradi.
            let cycleHideOff = false;
            try { cycleHideOff = localStorage.getItem('lms:cycle-hide-off') === '1'; } catch (e) { }
            function cycleHidingOff() {
                return cycleHideOff;
            }
            function cycleColHidden(index) {
                const d = cyclePlanData && cyclePlanData.dates ? cyclePlanData.dates[index] : null;
                return cycleHidingOff() && Boolean(d && d.off);
            }
            // [from, from+span) oralig'idagi ko'rinadigan kalendar indekslari.
            function cycleVisibleIdx(from, span) {
                const out = [];
                for (let i = from; i < from + span; i++) if (!cycleColHidden(i)) out.push(i);
                if (!out.length) for (let i = from; i < from + span; i++) out.push(i);
                return out;
            }
            let cycleDragKey = null;
            let cycleHolidays = [];   // bayram kunlari (Y-m-d)
            // Bayram chiplarini chizadi (× bilan olib tashlash mumkin)
            function renderHolChips() {
                $('cycleHolBar').classList.toggle('hidden', !cycleHolidays.length);
                $('cycleHolList').innerHTML = cycleHolidays.map(d =>
                    '<span class="inline-flex items-center gap-1 bg-white border border-amber-300 rounded px-1.5 py-0.5 text-[10px] text-amber-800">' +
                    esc(d.split('-').reverse().join('.')) +
                    '<button type="button" class="cyc-hol-x text-amber-500 hover:text-red-600 font-bold" data-d="' + esc(d) + '">×</button></span>').join('');
            }
            async function loadCyclePlan(options = {}) {
                if (!board) return;
                loadCycleOverlapUi();
                const body = scopeBody();
               if ($('cycleStart').value) body.start_date = $('cycleStart').value;
                body.holidays = cycleHolidays;
                if (options.clear) body.clear = 1;
                body.view = cycleViewMode;
                Object.assign(body, cycleLayerParams());
                if (scopeMode === 'mine') { body.mine = 1; delete body.clear; }
                else if (scopeMode === 'kafedra') { body.kafedra = 1; delete body.clear; }
                else if (scopeMode === 'subjects') { body.subjects = 1; delete body.clear; }
                $('cycleMsg').classList.remove('text-red-700', 'text-emerald-700', 'text-amber-700', 'font-semibold');
                $('cycleMsg').classList.add('text-gray-500');
                $('cycleMsg').removeAttribute('title');
                $('cycleMsg').textContent = 'Yuklanmoqda...';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/cycle-plan', 'POST', body);
                    // Qayta chizishda jadval joyidan siljimasin (o'zgartirilgan joy ko'z oldida qolsin).
                    const gw = $('cycleGridWrap');
                    const keep = gw ? { top: gw.scrollTop, left: gw.scrollLeft, page: window.scrollY } : null;
                    cyclePlanData = j;
                    try { buildSubjectColors(colorScopeCards()); } catch (e) { }
                    renderCycleLayerUi(j);
                    if (j.start_date && !$('cycleStart').value) $('cycleStart').value = j.start_date;
                    cycleHolidays = j.holidays || [];   // server — yagona manba (saqlangandan keyin)
                    renderHolChips();
                    renderCyclePlan(j);
                    renderCycleCards(j);
                    if (typeof renderStats === 'function') renderStats();
                    if (keep && !options.resetScroll) {
                        const restore = () => { gw.scrollTop = keep.top; gw.scrollLeft = keep.left; window.scrollTo(window.scrollX, keep.page); };
                        restore(); requestAnimationFrame(restore);
                    }
                    $('cycleMsg').textContent = 'Sikl jadvali: ' + (j.rows ? j.rows.length : 0) + ' oqim, ' +
                        ((j.cycle_cards || []).filter(card => card.placed).length) + '/' + ((j.cycle_cards || []).length) + ' joylashgan';
                    $('cycleMsg').textContent = (j.rows ? j.rows.length : 0) + ((j.view || cycleViewMode) === 'group' ? ' guruh · ' : ' oqim · ') + (j.subjects ? j.subjects.length : 0) + ' sikl fani' +
                        (j.total_days ? (' · ' + j.total_days + ' o\'quv kuni') : '') +

                        (cycleHolidays.length ? (' · ' + cycleHolidays.length + ' bayram') : '');
                } catch (e) {
                    $('cycleMsg').textContent = '';
                    $('cycleGrid').innerHTML = '<tbody><tr><td class="p-3 text-sm text-red-600">Xatolik: ' + esc(e.message) + '</td></tr></tbody>';
                    throw e;
                }
            }
            function cycleMemberParts(name) {
                const clean = String(name || '')
                    .replace(/\s*\((?:rus|ru|рус|russ|ing|eng|engl|ang|angl|англ|o['’‘]?z|oz|uz|ўз|узб)[^)]*\)\s*$/iu, '')
                    .trim();
                const match = clean.match(/^(.*?\d)\s*([a-eа-е])$/iu);
                if (!match) return {base: clean, letter: ''};
                const cyrillic = {а: 'a', б: 'b', в: 'c', г: 'd', д: 'e'};
                const letter = match[2].toLowerCase();
                return {base: match[1].trim(), letter: cyrillic[letter] || letter};
            }

            // Oqim to'liq nomi "Fakultet / 1-oqim / ru" ko'rinishida saqlanadi.
            // Tor ustunlarda faqat oqim (va til) bo'lagi ko'rsatiladi.
            // Bo'lakli fan qatori yorlig'i: "(a) bo'lak" belgisi + oqim, fan nomi va
            // bo'lak qaysi guruhchalardan yig'ilgani (pastda, alohida yorliqlar).
            // Guruhcha qatnashadigan bo'lak qatorlari: a'zolari orasida shu guruhcha bor.
            function cycleVariantRowsOf(allRows, row) {
                if (row.variant) return [];
                const key = v => String(v || '').trim().toLowerCase();
                // Bir xil nomli guruhcha boshqa fakultet/yo'nalish/kursda ham bo'lishi mumkin (row_key kabi).
                const sameCurriculum = v => key(v.specialty) === key(row.specialty) && +v.course === +row.course
                    && key(v.faculty) === key(row.faculty);
                return allRows.filter(v => v.variant && sameCurriculum(v) && (v.subgroups || []).some(m => key(m) === key(row.group)));
            }
            // Filtr qo'llangan qatorlar — ekran ham, Excel ham shundan oladi.
            function cycleVisibleRows(allRows) {
                return cycleRowFilter
                    ? allRows.filter(r => r.row_key === cycleRowFilter).flatMap(r => [r, ...cycleVariantRowsOf(allRows, r)])
                    : allRows;
            }
            function cycleVariantLabelHtml(row) {
                const letter = (/\((\p{L})\)\s*$/u.exec(row.variant || '') || [])[1] || '?';
                const subject = String(row.variant || '').replace(/\s*\(\p{L}\)\s*$/u, '');
                const members = (row.subgroups || []).map(s => String(s).replace(/\s*\(.*\)$/, ''));
                return '<div class="cyc-vrow" title="' + esc(row.variant + '\n' + (row.flow || '') + '\nGuruhchalar: ' + members.join(', ')) + '">'
                    + '<div class="cyc-vrow-head"><span class="cyc-vrow-tag">' + esc(letter) + '</span>'
                    + '<span class="cyc-vrow-kind">bo\'lak</span><span class="cyc-vrow-flow">' + esc(cycleFlowShort(row.flow || '')) + '</span></div>'
                    + '<div class="cyc-vrow-subj">' + esc(subject) + '</div>'
                    + '<div class="cyc-vrow-members">' + members.map(m => '<span>' + esc(m) + '</span>').join('') + '</div>'
                    + cycleVariantStudentsHtml(row, members)
                    + '</div>';
            }
            // Bo'lakka HEMIS'da biriktirilgan talabalar: guruh bo'yicha soni va ro'yxat.
            function cycleVariantStudentsHtml(row, members) {
                const list = Array.isArray(row.students) ? row.students : null;
                if (!list) return '';
                if (!list.length) return '<div class="cyc-vrow-stu is-empty">HEMIS\'da talaba biriktirilmagan</div>';
                const tail = g => (/(\d+\p{L}*)\s*$/u.exec(String(g)) || [])[1] || String(g);
                // Tor ustunga sig'ishi uchun: "FAMILIYA I. O." (to'liq ism — tooltipda).
                const short = n => { const p = String(n || '').trim().split(/\s+/u);
                    return p.length < 2 ? p.join(' ') : p[0] + ' ' + p.slice(1, 3).map(x => x.charAt(0) + '.').join(' '); };
                const per = {};
                list.forEach(s => { per[s.group] = (per[s.group] || 0) + 1; });
                const multi = Object.keys(per).length > 1;
                return '<details class="cyc-vrow-stu" open><summary>' + list.length + ' talaba'
                    + (multi ? ' <i>(' + Object.entries(per).map(([g, n]) => esc(tail(g)) + ': ' + n).join(', ') + ')</i>' : '')
                    + '</summary><ol>' + list.map(s => '<li title="' + esc(s.name + ' — ' + s.group) + '">' + esc(short(s.name))
                        + (multi ? ' <b>' + esc(tail(s.group)) + '</b>' : '') + '</li>').join('') + '</ol></details>';
            }
            function cycleFlowShort(flow) {
                const name = String(flow || '').trim();
                const parts = name.split(' / ');
                const tail = parts.slice(-2).filter(part => /-oqim$/i.test(part) || part.length <= 3);
                return tail.join(' / ') || name;
            }

            // Chap ustun: bitta qator = bitta OQIM. Sarlavhada oqim nomi, ostida
            // shu oqim tarkibidagi guruhlar (guruhcha harflari bilan).
            // Faol talabalar soni nishoni (FISH emas — faqat son).
            function cycleStuCountHtml(n, title) {
                return '<em class="cyc-stu-count" title="' + esc(title || (n + ' ta faol talaba')) + '">' + n + '</em>';
            }
            function cycleMembersHtml(flow, members, counts) {
                const grouped = new Map(), perBase = new Map();
                (members || []).flatMap(name => String(name).split(/\s+\+\s+/u)).forEach(name => {
                    const part = cycleMemberParts(name);
                    const base = part.base || flow;
                    if (!grouped.has(base)) grouped.set(base, new Set());
                    if (part.letter) grouped.get(base).add(part.letter);
                    if (counts && counts[name] != null) perBase.set(base, (perBase.get(base) || 0) + (+counts[name] || 0));
                });
                const total = Array.from(perBase.values()).reduce((a, b) => a + b, 0);

                // Oqim nomi guruh nomining o'zi bo'lsa (label'siz eski kartalar),
                // sarlavha takrorlanmasin — faqat guruh qatorlari ko'rsatiladi.
                //
                // Ustunga faqat oqim bo'lagi sig'adi, to'liq nomi tooltipda.
                const flowName = String(flow || '').trim();
                const isFlowLabel = flowName !== '' && !grouped.has(flowName);
                const shortName = cycleFlowShort(flowName);
                const head = isFlowLabel
                    ? '<div class="cyc-flow-head" title="' + esc(flowName) + '">' +
                      '<span>' + esc(shortName) + '</span>' +
                      '<i>' + grouped.size + ' guruh' + (counts ? ' · ' + total + ' talaba' : '') + '</i></div>'
                    : '';

                if (!grouped.size) grouped.set(flowName, new Set());

                return '<div class="cyc-members">' + head +
                    Array.from(grouped.entries()).map(([base, letters]) => {
                        const suffixes = Array.from(letters).sort().join(',');
                        return '<div class="cyc-member-row"><span>' + esc(base)
                            + (perBase.has(base) ? ' ' + cycleStuCountHtml(perBase.get(base)) : '') + '</span>' +
                            '<b>' + esc(suffixes || '—') + '</b></div>';
                    }).join('') + '</div>';
            }
            function renderCyclePlanLegacy(j) {
                const dates = j.dates || [], rows = j.rows || [];
                if (!rows.length) {
                    $('cycleGrid').innerHTML = '<tbody><tr><td class="p-4 text-sm text-gray-400">Sikl rejimidagi fan yoki oqim topilmadi. Fan sozlamasida 4-6 kurs fanlarini <b>Sikl</b> qilib, sikl uzunligini (kun) kiriting, so\'ng shu yerni Yangilang.</td></tr></tbody>';
                    return;
                }
                let h = '<thead><tr><th class="cyc-gcol">Oqim</th>';
                dates.forEach(d => h += '<th class="cyc-dcol' + ((d.sunday || d.holiday) ? ' cyc-off' : '') + '" title="' + (d.sunday ? 'Yakshanba' : (d.holiday ? 'Bayram kuni' : '')) + '">' + esc(d.d) + '</th>');
                h += '</tr></thead><tbody>';
                rows.forEach(r => {
                    h += '<tr><td class="cyc-gcol">' + cycleMembersHtml(r.group, r.subgroups) + '</td>';
                    let col = 0;
                    (r.blocks || []).forEach(b => {
                        while (col < b.from) { h += '<td class="cyc-cell' + (dates[col] && (dates[col].sunday || dates[col].holiday) ? ' cyc-off' : '') + '"></td>'; col++; }
                        const c = subjColor(b.subject);
                        h += '<td class="cyc-cell cyc-block" colspan="' + (b.to - b.from + 1) + '" style="background:' + c.bg + ';border-color:' + c.border + ';" title="' + esc(b.subject) + ' — ' + b.days + ' kun">' +
                            '<span class="cyc-lbl"><b class="cyc-subj">' + esc(b.subject) + '</b> <span class="cyc-days">(' + b.days + ')</span></span></td>';
                        col = b.to + 1;
                    });
                    while (col < dates.length) { h += '<td class="cyc-cell' + (dates[col] && (dates[col].sunday || dates[col].holiday) ? ' cyc-off' : '') + '"></td>'; col++; }
                    h += '</tr>';
                });
                h += '</tbody>';
                $('cycleGrid').innerHTML = h;
                paintCycleDayBands(dates);
                // Oyna o'lchami o'zgarsa ustun eni ham o'zgaradi — qayta chizamiz.
                if (cycleBandsResize) window.removeEventListener('resize', cycleBandsResize);
                cycleBandsResize = () => paintCycleDayBands(dates);
                window.addEventListener('resize', cycleBandsResize);
                // Skrollda ham qayta o'lchanadi — overlay jadval bilan birga yuradi.
                const wrapEl = $('cycleGridWrap');
                if (wrapEl && !wrapEl.dataset.bandScroll) {
                    wrapEl.dataset.bandScroll = '1';
                    wrapEl.addEventListener('scroll', () => {
                        if (cyclePlanData) paintCycleDayBands(cyclePlanData.dates || []);
                    }, { passive: true });
                }
            }
            // Sikl panjarasi interaktiv ko'rinishda chiziladi: bo'sh katakka fan
            // kartasini sudrash uning start indeksini saqlaydi.
            function showCyclePlacementResult(card, index, message, type = 'error') {
                const msg = $('cycleMsg');
                const dateRow = cyclePlanData && cyclePlanData.dates && cyclePlanData.dates[index];
                const date = dateRow ? (dateRow.iso || dateRow.d || '') : '';
                const subject = card && card.subject ? card.subject : 'Fan kartasi';
                const prefix = type === 'success' ? 'Joylandi'
                    : (type === 'pending' ? 'Tasdiqlashga yuborildi' : 'Joylanmadi');
                const details = [subject, date].filter(Boolean).join(' · ');
                const text = prefix + (details ? ': ' + details : '') + (message ? ' — ' + message : '');

                msg.textContent = text;
                msg.classList.remove('text-gray-500', 'text-red-700', 'text-emerald-700', 'text-amber-700', 'font-semibold');
                msg.classList.add(type === 'success' ? 'text-emerald-700'
                    : (type === 'pending' ? 'text-amber-700' : 'text-red-700'), 'font-semibold');
                msg.setAttribute('title', text);

                if (type === 'error' && typeof ttToast === 'function') {
                    ttToast('<b>Sikl kartasi joylanmadi</b><div>' + esc(text) + '</div>', 'error');
                }
            }
            async function placeCycleCardAt(card, index, pair) {
                if (!card) {
                    showCyclePlacementResult(null, index, 'Karta ma\u2019lumoti topilmadi. Sahifani yangilab qayta urinib ko\u2018ring.');
                    return;
                }
                try {
                    const result = await api(BASE + '/boards/' + board.id + '/cycle-place', 'POST', {
                        ...cycleOverlapParams(), ...cycleLayerParams(),
                        action: 'place', specialty_name: card.specialty, course: card.course,
                        group_name: card.group, subject_name: card.subject, start_index: index,
                        training_type: card.type || 'practice', pair: pair || 1, view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                        start_date: $('cycleStart').value, holidays: cycleHolidays,
                    });
                    await loadCyclePlan();
                    const reloaded = ((cyclePlanData && cyclePlanData.cycle_cards) || [])
                        .find(item => item.key === card.key);
                    if (result && result.pending_approval) {
                        showCyclePlacementResult(card, index, '', 'pending');
                    } else if (!reloaded || !reloaded.placed) {
                        showCyclePlacementResult(card, index,
                            'Server yozuvni qabul qildi, lekin qayta yuklanganda blok topilmadi. Fan/oqim nomlari mosligini tekshiring.');
                    } else {
                        showCyclePlacementResult(card, index, '', 'success');
                    }
                } catch (e) {
                    console.error('Sikl joylash xatosi: ' + (e && e.message ? e.message : 'Noma\u2019lum xato'), {karta: card, index: index});
                    showCyclePlacementResult(card, index, e && e.message ? e.message : 'Noma\u2019lum xato.');
                }
            }
            // Tanlangan karta qaysi oqim qatoriga tushishi mumkinligini ko'rsatadi:
            // o'sha qatorning bo'sh kataklari ajratiladi, qolganlari so'niq bo'ladi.
            function highlightCycleRow(cardKey) {
                const card = cardKey
                    ? ((cyclePlanData && cyclePlanData.cycle_cards) || []).find(item => item.key === cardKey)
                    : null;
                $('cycleGrid').querySelectorAll('[data-cycle-row]').forEach(cell => {
                    cell.classList.remove('cycle-row-target', 'cycle-row-muted');
                    if (!card) return;
                    cell.classList.add(cell.dataset.cycleRow === card.row_key
                        ? 'cycle-row-target' : 'cycle-row-muted');
                });
            }
            // Kun chiziqlari va dam olish ustunlari jadval fonida chiziladi:
            // sarlavha kataklaridan haqiqiy o'lchov olinadi, shuning uchun
            // ustun eni o'zgarsa ham chiziq joyida qoladi.
            function paintCycleDayBands(dates) {
                const grid = $('cycleGrid');
                const wrap = $('cycleGridWrap');
                const overlay = $('cycleOverlay');
                const body = grid.querySelector('tbody');
                if (!overlay || !body) return;

                overlay.innerHTML = '';
                cycleDayGeometry = [];

                const heads = grid.querySelectorAll('thead .cyc-dcol');
                if (!heads.length) return;

                // Overlay jadval bilan bir tekislikda: offsetTop/offsetLeft
                // wrap ichidagi haqiqiy joyni beradi va skrollga bog'liq emas,
                // shuning uchun qo'shimcha tuzatish talab qilmaydi.
                const wrapRect = wrap.getBoundingClientRect();
                const bodyRect = body.getBoundingClientRect();
                const offsetX = wrap.scrollLeft - wrapRect.left;

                overlay.style.top = (body.offsetTop) + 'px';
                overlay.style.left = '0px';
                overlay.style.width = grid.offsetWidth + 'px';
                overlay.style.height = bodyRect.height + 'px';

                const frag = document.createDocumentFragment();

                heads.forEach((head, index) => {
                    const rect = head.getBoundingClientRect();
                    const left = Math.round(rect.left + offsetX);
                    const width = Math.round(rect.right + offsetX) - left;
                    cycleDayGeometry[index] = { left: left, width: width };
                });

                // Dam olish kunlari — uzluksiz bo'laklar bo'lib chiziladi.
                let start = null;
                const flush = (endIndex) => {
                    if (start === null) return;
                    const from = cycleDayGeometry[start.index];
                    const to = cycleDayGeometry[endIndex];
                    if (from && to) {
                        const band = document.createElement('div');
                        band.className = 'cyc-off-band ' + (start.holiday ? 'is-holiday' : 'is-sunday');
                        band.style.left = from.left + 'px';
                        band.style.width = (to.left + to.width - from.left) + 'px';
                        frag.appendChild(band);
                    }
                    start = null;
                };

                dates.forEach((d, index) => {
                    const holiday = Boolean(d.holiday);
                    const off = holiday || Boolean(d.sunday);
                    if (!off) { flush(index - 1); return; }
                    if (start && start.holiday !== holiday) flush(index - 1);
                    if (!start) start = { index: index, holiday: holiday };
                });
                flush(dates.length - 1);

                overlay.appendChild(frag);
            }

            // Sudrash paytida aniq bitta kun ustunini belgilaydi; joylab
            // bo'lmaydigan kun qizil bo'ladi. Nishon katak ichiga qo'yiladi,
            // shuning uchun skroll va jadval siljishi ta'sir qilmaydi.
            function markCycleDrop(cell, dayIndex, allowed) {
                clearCycleDropMark();
                if (!cell) return;

                const from = +cell.dataset.cycleFrom || 0;
                const span = Math.max(1, +cell.dataset.cycleSpan || 1);
                const visible = cycleVisibleIdx(from, span);
                let offset = visible.indexOf(dayIndex);
                if (offset < 0) offset = Math.max(0, visible.findIndex(i => i > dayIndex));
                const colWidth = cell.offsetWidth / visible.length;

                const mark = document.createElement('div');
                mark.className = 'cyc-drop-mark' + (allowed ? '' : ' is-bad');
                mark.style.left = (offset * colWidth) + 'px';
                mark.style.width = colWidth + 'px';
                cell.appendChild(mark);
            }

            function clearCycleDropMark() {
                const grid = $('cycleGrid');
                if (grid) grid.querySelectorAll('.cyc-drop-mark').forEach(el => el.remove());
            }

            function cycleDateClass(date) {
                if (!date) return '';
                if (date.holiday) return ' cyc-off cyc-holiday';
                return date.sunday ? ' cyc-off cyc-weekend' : '';
            }
            // ── Sikl blokiga o'qituvchi / vaqt / xona biriktirish ──
            let cycAssignCard = null;

            async function openCycleAssign(key) {
                const card = (cyclePlanData.cycle_cards || []).find(item => item.key === key);
                if (!card || !card.placed) return;
                cycAssignCard = card;

                $('cycAssignTitle').textContent = card.subject
                    + ((cyclePlanData && cyclePlanData.simple) ? '' : (card.type === 'lecture' ? " — Ma'ruza" : ' — Amaliy'));
                $('cycAssignMeta').textContent = card.group + ' · ' + card.days + ' kun';
                $('cycAssignKafedra').textContent = "O'qituvchi";
                $('cycAssignTeacher').innerHTML = '<option value="">Yuklanmoqda...</option>';
                $('cycAssignRoom').innerHTML = '<option value="">Yuklanmoqda...</option>';
                $('cycAssignModal').classList.add('open');

                try {
                    const j = await api(BASE + '/boards/' + board.id + '/cycle-assign-options', 'POST', {
                        specialty_name: card.specialty, course: card.course,
                        group_name: card.group, subject_name: card.subject,
                        training_type: card.type || 'practice', view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                        start_date: $('cycleStart').value, holidays: cycleHolidays,
                    });

                    $('cycAssignKafedra').textContent = "O'qituvchi" + (j.kafedra ? ' (' + j.kafedra + ')' : '');
                    $('cycAssignTeacher').innerHTML = '<option value="">— Tanlanmagan —</option>' +
                        (j.teachers || []).map(t => '<option value="' + t.id + '">' + esc(t.name) + '</option>').join('');
                    $('cycAssignRoom').innerHTML = '<option value="">— Tanlanmagan —</option>' +
                        (j.rooms || []).map(r => '<option value="' + esc(r.code) + '">' + esc(r.name) + (r.volume ? ' (' + r.volume + ' o\u2018rin)' : '') + '</option>').join('');

                    // "A + B" kabi umumiy karta: o'qituvchi/xona boshqa guruhchalarga ham tegadi.
                    if (j.shared_with && j.shared_with.length) {
                        $('cycAssignMeta').textContent = card.group + ' · ' + card.days + ' kun · umumiy karta: '
                            + j.shared_with.join(', ') + ' bilan birga (o‘zgarish ularga ham tegadi)';
                    }
                    const current = j.current || {};
                    if (current.teacher_id) $('cycAssignTeacher').value = String(current.teacher_id);
                    if (current.auditorium_code) $('cycAssignRoom').value = current.auditorium_code;
                } catch (e) {
                    $('cycAssignModal').classList.remove('open');
                    alert('Ma\u2019lumotlarni yuklab bo\u2018lmadi: ' + e.message);
                }
            }

            function closeCycleAssign() {
                $('cycAssignModal').classList.remove('open');
                cycAssignCard = null;
            }

            // Tozalash: o'qituvchi va xona olib tashlanadi.
            $('cycAssignReset').addEventListener('click', async () => {
                if (!cycAssignCard) return;
                if (!confirm("Biriktirilgan o'qituvchi va xona olib tashlansinmi?")) return;
                const button = $('cycAssignReset');
                button.disabled = true;
                try {
                    await api(BASE + '/boards/' + board.id + '/cycle-assign', 'POST', {
                        specialty_name: cycAssignCard.specialty, course: cycAssignCard.course,
                        group_name: cycAssignCard.group, subject_name: cycAssignCard.subject,
                        training_type: cycAssignCard.type || 'practice', view: cycleEffView(), placement_group: (cycAssignCard && cycAssignCard.placement_group) || undefined,
                        teacher_id: null, lesson_time: null, auditorium_code: null,
                        start_date: $('cycleStart').value, holidays: cycleHolidays,
                    });
                    closeCycleAssign();
                    await loadCyclePlan();
                } catch (e) {
                    alert('Tozalab bo\u2018lmadi: ' + e.message);
                } finally {
                    button.disabled = false;
                }
            });

            $('cycAssignClose').addEventListener('click', closeCycleAssign);
            $('cycAssignModal').addEventListener('click', ev => {
                if (ev.target === $('cycAssignModal')) closeCycleAssign();
            });

            $('cycAssignSave').addEventListener('click', async () => {
                if (!cycAssignCard) return;
                const button = $('cycAssignSave');
                button.disabled = true;
                try {
                    await api(BASE + '/boards/' + board.id + '/cycle-assign', 'POST', {
                        specialty_name: cycAssignCard.specialty, course: cycAssignCard.course,
                        group_name: cycAssignCard.group, subject_name: cycAssignCard.subject,
                        training_type: cycAssignCard.type || 'practice', view: cycleEffView(), placement_group: (cycAssignCard && cycAssignCard.placement_group) || undefined,
                        teacher_id: $('cycAssignTeacher').value ? +$('cycAssignTeacher').value : null,
                        auditorium_code: $('cycAssignRoom').value || null,
                        start_date: $('cycleStart').value, holidays: cycleHolidays,
                    });
                    closeCycleAssign();
                    await loadCyclePlan();
                } catch (e) {
                    alert('Saqlab bo\u2018lmadi: ' + e.message);
                } finally {
                    button.disabled = false;
                }
            });

            // ── Fan nomining blok ichidagi joyi ──
            // Sukut: M/A darslar egallagan soatlar oralig'ining o'rtasi (gorizontal — blok
            // o'rtasi). Foydalanuvchi siljitgan joy shu brauzerda (doska + blok bo'yicha) saqlanadi.
            function cycTitleStoreKey() { return board ? 'lms:cyc-title-pos:' + board.id : ''; }
            function cycTitleStore() {
                try { return JSON.parse(localStorage.getItem(cycTitleStoreKey()) || '{}') || {}; } catch (e) { return {}; }
            }
            function cycTitleSave(key, pos) {
                try {
                    const all = cycTitleStore();
                    if (pos) all[key] = pos; else delete all[key];
                    localStorage.setItem(cycTitleStoreKey(), JSON.stringify(all));
                } catch (e) { }
            }
            function cycTitlePosStyle(block, plan, dayHours) {
                const saved = cycTitleStore()[block.key];
                if (saved && isFinite(saved[0]) && isFinite(saved[1])) {
                    return '--mk-x:' + (+saved[0]).toFixed(2) + '%;--mk-y:' + (+saved[1]).toFixed(2) + '%';
                }
                let lo = Infinity, hi = -Infinity;
                Object.values(plan || {}).forEach(dp => {
                    const n = (+dp.lec || 0) + (+dp.prc || 0);
                    if (n < 1) return;
                    const st = +dp.start || 1;
                    lo = Math.min(lo, st); hi = Math.max(hi, st + n - 1);
                });
                if (!isFinite(lo)) return '';
                const y = ((lo - 1) + hi) / 2 / Math.max(1, dayHours) * 100;
                return '--mk-y:' + y.toFixed(2) + '%';
            }
            // Yorliq markazini (blokka nisbatan, px) blok ichida ushlab, foizda qaytaradi:
            // yorliqning yarim kengligi/balandligi chegaradan chiqmasin.
            function cycTitleClampPct(cx, cy, or, ar) {
                const hw = or.width / 2 + 2, hh = or.height / 2 + 2;
                const x = Math.min(Math.max(cx, hw), Math.max(hw, ar.width - hw));
                const y = Math.min(Math.max(cy, hh), Math.max(hh, ar.height - hh));
                return [x / ar.width * 100, y / ar.height * 100];
            }
            // Avtomatik (chetki soat) yoki saqlangan joyda ikki qatorli uzun nom blokdan
            // chiqib, qo'shni qatorni yopmasin (.is-mark — overflow: visible). Avval hamma
            // o'lchov olinadi, keyin yoziladi — bitta layout.
            function clampCycTitles() {
                const items = [...document.querySelectorAll('#cycleGrid .cyc-mk-over')]
                    .map(over => ({ over, or: over.getBoundingClientRect(), ar: over.parentElement.getBoundingClientRect() }));
                items.forEach(({ over, or, ar }) => {
                    if (!ar.width || !ar.height) return;
                    const cx = or.left + or.width / 2 - ar.left, cy = or.top + or.height / 2 - ar.top;
                    const pos = cycTitleClampPct(cx, cy, or, ar);
                    if (Math.abs(pos[0] - cx / ar.width * 100) < 0.05 && Math.abs(pos[1] - cy / ar.height * 100) < 0.05) return;
                    over.style.setProperty('--mk-x', pos[0].toFixed(2) + '%');
                    over.style.setProperty('--mk-y', pos[1].toFixed(2) + '%');
                });
            }
            // Ushlab siljitish: sichqoncha 4px dan ko'p yursa — siljitish, aks holda oddiy bosish.
            let cycTitleDragged = false;
            function setupCycTitleDrag() {
                const grid = $('cycleGrid');
                if (!grid || grid.dataset.mkDragReady) return;
                grid.dataset.mkDragReady = '1';
                grid.addEventListener('pointerdown', ev => {
                    if (ev.button !== 0) return;
                    const title = ev.target.closest ? ev.target.closest('.cyc-mk-title[data-mk-subj]') : null;
                    if (!title) return;
                    const over = title.closest('.cyc-mk-over'), area = over && over.parentElement;
                    if (!area) return;
                    const sx = ev.clientX, sy = ev.clientY;
                    const ar = area.getBoundingClientRect(), or = over.getBoundingClientRect();
                    const cx0 = or.left + or.width / 2 - ar.left, cy0 = or.top + or.height / 2 - ar.top;
                    let moving = false, pos = null;
                    cycTitleDragged = false;
                    const move = e => {
                        const dx = e.clientX - sx, dy = e.clientY - sy;
                        if (!moving && Math.abs(dx) + Math.abs(dy) < 4) return;
                        if (!moving) { moving = true; over.classList.add('is-dragging'); try { title.setPointerCapture(ev.pointerId); } catch (_) { } }
                        e.preventDefault();
                        pos = cycTitleClampPct(cx0 + dx, cy0 + dy, or, ar);
                        over.style.setProperty('--mk-x', pos[0].toFixed(2) + '%');
                        over.style.setProperty('--mk-y', pos[1].toFixed(2) + '%');
                    };
                    const up = () => {
                        document.removeEventListener('pointermove', move);
                        document.removeEventListener('pointerup', up);
                        document.removeEventListener('pointercancel', up);
                        over.classList.remove('is-dragging');
                        if (moving) {
                            cycTitleDragged = true;   // keyingi "click" popoverni ochmasin
                            if (pos) cycTitleSave(title.dataset.mkSubj, [+pos[0].toFixed(2), +pos[1].toFixed(2)]);
                        }
                    };
                    document.addEventListener('pointermove', move);
                    document.addEventListener('pointerup', up);
                    document.addEventListener('pointercancel', up);
                });
                // Ikki marta bosish — avtomatik (darslar markazi) joyga qaytarish.
                grid.addEventListener('dblclick', ev => {
                    const title = ev.target.closest ? ev.target.closest('.cyc-mk-title[data-mk-subj]') : null;
                    if (!title) return;
                    ev.preventDefault();
                    cycTitleSave(title.dataset.mkSubj, null);
                    closeCycDayPop();
                    if (cyclePlanData) renderCyclePlan(cyclePlanData);
                });
            }

            function renderCyclePlan(j) {
                if (typeof closeCycDayPop === 'function') closeCycDayPop();
                const dates = j.dates || [], allRows = j.rows || [];
                // "Guruh" ustuni sarlavhasidan tanlangan bitta guruh/oqim (bo'lmasa hammasi).
                if (cycleRowFilter && !allRows.some(r => r.row_key === cycleRowFilter)) cycleRowFilter = null;
                // Guruhcha tanlanganda u qatnashadigan bo'lak qatorlari (Fan (a)/(b)/(c)) ham ko'rinadi.
                const rows = cycleVisibleRows(allRows);
                if (!rows.length) {
                    // "Faqat meniki": jadvalda sikl bor, lekin shu o'qituvchiga biriktirilgani yo'q —
                    // sozlama haqidagi xabar chalg'itadi.
                    const emptyMsg = scopeMode === 'kafedra'
                        ? 'Tanlangan qamrovda kafedrangizga tegishli sikl fani yo\'q. Barcha darslarni ko\'rish uchun <b>Butun doska</b> ni tanlang.'
                        : scopeMode === 'subjects'
                        ? 'Tanlangan qamrovda siz mas\'ul bo\'lgan sikl fani yo\'q. Barcha darslarni ko\'rish uchun <b>Butun doska</b> ni tanlang.'
                        : mineOnly
                        ? 'Tanlangan qamrovda sizga biriktirilgan sikl darsi yo\'q. Barcha darslarni ko\'rish uchun <b>Butun doska</b> ni tanlang.'
                        : 'Sikl rejimidagi fan yoki oqim topilmadi. Avval fan sozlamasida <b>Sikl</b> rejimi va kun sonini kiriting.';
                    $('cycleGrid').innerHTML = '<tbody><tr><td class="p-4 text-sm text-gray-400">' + emptyMsg + '</td></tr></tbody>';
                    renderCycleCards(j);
                    return;
                }
                // Juftlik qatorlar qo'ng'iroq jadvalidan: nomi (0.5-para, 1-para...) va vaqti bilan.
                const bellPairs = boardSchedule().filter(it => it.type === 'pair');
                const totalPairs = bellPairs.length || Math.max(1, +j.pairs || 1);
                // Sukutda sikl kuniga yetarli qatorlar ko'rinadi (6 soat):
                // yarim-para jadvalida 6 qator, to'liq parada 3 qator. Qolganini
                // "+" tugmasi ochadi; band qatorlar hech qachon yashirilmaydi.
                const parseT = t => { const m = /^(\d{1,2}):(\d{2})$/.exec(t || ''); return m ? (+m[1]) * 60 + (+m[2]) : null; };
                const durs = bellPairs.map(it => { const a = parseT(it.start), b = parseT(it.end); return a != null && b != null && b > a ? b - a : null; }).filter(v => v != null);
                const rowMins = durs.length ? durs.reduce((x, y) => x + y, 0) / durs.length : 80;
                const basePairs = Math.ceil(6 / (rowMins >= 60 ? 2 : 1));
                let maxLaneUsed = 1;
                rows.forEach(row => (row.blocks || []).forEach(block => {
                    const bp = Math.max(1, +block.pair || 1);
                    const laneTo = Math.max(bp + Math.max(+block.span_head || 1, 1) - 1,
                        (+block.span_tail || 0) > 0 ? bp + (+block.tail_offset || 0) + (+block.span_tail) - 1 : bp);
                    if (laneTo > maxLaneUsed) maxLaneUsed = laneTo;
                }));
                // O'quv bo'limi ko'rinishi: ma'ruza/amaliy ajratilmaydi, para
                // qatorlari yo'q — bitta qator, faqat kunlar va qoidalar.
                const simple = Boolean(j.simple);
                const markMode = Boolean(j.mark_mode);
                const dayHours = Math.max(1, +j.day_hours || 6);
                if (cyclePairsShown == null) cyclePairsShown = basePairs;
                const pairs = simple ? 1 : Math.max(1, Math.min(totalPairs, Math.max(cyclePairsShown, maxLaneUsed)));
                if (!simple) cyclePairsShown = pairs;
                const showAddPair = !simple && pairs < totalPairs;
                let h = '<colgroup><col class="cyc-group-col">'
                    + (markMode ? '<col style="width:84px">' : '')
                    + (simple ? '' : '<col style="width:52px">');
                dates.forEach((d, i) => h += '<col class="cyc-date-col' + (cycleColHidden(i) ? ' cyc-col-hidden' : '') + '">');
                const rowWord = (j.view || cycleViewMode) === 'group' ? 'Guruh' : 'Oqim';
                h += '</colgroup><thead><tr><th class="cyc-gcol cyc-gfilter-th">'
                    + '<button type="button" id="cycRowFilterBtn" class="cyc-gfilter-btn' + (cycleRowFilter ? ' is-on' : '') + '" title="' + rowWord + 'ni tanlash yoki qidirish">'
                    + (cycleRowFilter ? esc(cycleRowLabels(allRows)[cycleRowFilter] || '') : rowWord + ' (' + allRows.length + ')') + ' &#9662;</button>'
                    + (cycleRowFilter ? '<button type="button" id="cycRowFilterClear" class="cyc-gfilter-x" title="Hammasini ko\'rsatish">&times;</button>' : '')
                    + '</th>'
                    + (markMode ? '<th class="cyc-mk-side">Para</th>' : '')
                    + (simple ? '' : '<th class="cyc-pcol" title="Juftlik">Para</th>');
                dates.forEach((d, i) => h += '<th class="cyc-dcol' + cycleDateClass(d) + (cycleColHidden(i) ? ' cyc-col-hidden' : '') + '" title="' + (d.sunday ? 'Yakshanba' : (d.holiday ? 'Bayram kuni' : '')) + '">' + esc(d.d) + '</th>');
                h += '</tr></thead><tbody>';
                rows.forEach(row => {
                    // Guruhcha qatorida u qatnashadigan bo'lak bloklari "ishora" sifatida chiziladi:
                    // sikl bor, lekin dars bo'lak qatorida — bosilsa o'sha qator ochiladi.
                    const ghostBlocks = j.mark_mode ? cycleVariantRowsOf(allRows, row).flatMap(v => (v.blocks || [])
                        .filter(b => b.from != null && b.to != null)
                        .map(b => ({ ...b, ghost: true, ghost_row: v.row_key,
                            ghost_letter: (/\((\p{L})\)\s*$/u.exec(v.variant || '') || [])[1] || '' }))) : [];
                    // Bloklar yaxlit to'rtburchak katak bo'lib chiziladi (rowspan)
                    // — ichki chiziqlar yo'q: ma'ruza 1 para, amaliy butun sikl
                    // davomida kuniga 4 soat (2 para). Bo'sh kataklar colspan bilan
                    // birlashtiriladi: DOM kichik, drag & drop qotmaydi; aniq kun
                    // sichqoncha o'rnidan topiladi.
                    const cols = dates.length;
                    const cellAt = {};   // lane -> {boshCol: {html, next}}
                    const covered = {};  // lane -> rowspan/colspan qamrovi
                    for (let l = 1; l <= pairs; l++) { cellAt[l] = {}; covered[l] = new Uint8Array(cols); }
                    const mark = (lane0, lanes, from, to) => {
                        for (let l = lane0; l < lane0 + lanes && l <= pairs; l++)
                            for (let c = from; c <= to && c < cols; c++) covered[l][c] = 1;
                    };
                    const free = (lane0, lanes, from, to) => {
                        for (let l = lane0; l < lane0 + lanes && l <= pairs; l++)
                            for (let c = from; c <= to && c < cols; c++) if (covered[l][c]) return false;
                        return true;
                    };
                    [...(row.blocks || []), ...ghostBlocks].forEach(block => {
                        if (block.ghost) {
                            const gTo = Math.min(block.to, cols - 1);
                            if (block.from >= cols || gTo < block.from || !free(1, pairs, block.from, gTo)) return;
                            const gc = subjColor(String(block.subject).replace(/\s*\(\p{L}\)\s*$/u, ''));
                            const gSubj = String(block.subject).replace(/\s*\(\p{L}\)\s*$/u, '');
                            cellAt[1][block.from] = {
                                html: '<td class="cyc-cell cyc-ghost" rowspan="' + pairs + '" colspan="' + (gTo - block.from + 1) + '"'
                                    + ' style="--gc:' + gc.bg + ';--gb:' + gc.border + '"'
                                    + ' title="' + esc(block.subject) + ' — bu guruhcha \'' + esc(block.ghost_letter) + '\' bo\'lagida o\'qiydi. Bosing: bo\'lak qatorini ochish">'
                                    + '<button type="button" class="cyc-ghost-btn" data-ghost-row="' + esc(block.ghost_row) + '">'
                                    + '<span class="cyc-vrow-tag">' + esc(block.ghost_letter) + '</span>'
                                    + '<span class="cyc-ghost-txt"><b>' + esc(gSubj) + '</b><i>(' + esc(block.ghost_letter) + ') bo\'lakda · ' + block.days + ' kun &rarr;</i></span>'
                                    + '</button></td>',
                                next: gTo + 1,
                            };
                            mark(1, pairs, block.from, gTo);
                            return;
                        }
                        const p = Math.max(1, +block.pair || 1);
                        if (p > pairs || block.from >= cols) return;
                        const spanHead = Math.max(1, +block.span_head || 1);
                        const spanTail = +block.span_tail || 0;
                        const headDays = +block.head_days || 0;
                        const headTo = block.head_to != null ? +block.head_to : block.to;
                        const tailFrom = block.tail_from != null ? +block.tail_from : null;
                        // Amaliy dum bo'lagi ma'ruza bo'shatgan paradan boshlanadi (tail_offset < 0).
                        const tailOffset = +block.tail_offset || 0;
                        const rects = [];
                        if (block.type === 'practice' && spanTail > 0 && tailFrom != null && headDays > 0) {
                            // Bosh bo'lak ichki yakshanba/bayram ustunlarini ham qamrab,
                            // dum bo'lagigacha uzluksiz davom etadi.
                            rects.push({ lanes: spanHead, from: block.from, to: Math.min(tailFrom - 1, block.to), main: true });
                            rects.push({ lanes: spanTail, from: tailFrom, to: block.to, main: false, lane0: p + tailOffset });
                        } else if (block.type === 'practice' && spanTail > 0 && headDays < 1) {
                            rects.push({ lanes: spanTail, from: block.from, to: block.to, main: true, lane0: p + tailOffset });
                        } else {
                            rects.push({ lanes: spanHead, from: block.from, to: block.to, main: true });
                        }
                        const color = subjColor(block.subject);
                        const reqParts = [block.teacher_name, block.auditorium_name || block.auditorium_code]
                            .filter(Boolean);
                        const req = reqParts.join(' · ');
                        const reqHtml = reqParts.map(part => '<span class="cyc-req">' + esc(part) + '</span>').join('');
                        const typeChip = simple
                            ? ''
                            : '<span class="cyc-type ' + block.type + '" title="' + (block.type === 'lecture' ? "Ma'ruza" : 'Amaliy') + '">' + (block.type === 'lecture' ? 'M' : 'A') + '</span>';
                        const twoRects = rects.length > 1;
                        rects.forEach(rect => {
                            // Dum bo'lagi (ma'ruza tugagach) yuqoriroq paradan boshlanishi mumkin.
                            const lp = Math.max(1, rect.lane0 || p);
                            const lanes = Math.min(rect.lanes, pairs - lp + 1);
                            const to = Math.min(rect.to, cols - 1);
                            if (lanes < 1 || to < rect.from || rect.from < 0) return;
                            if (!free(lp, lanes, rect.from, to)) return; // ustma-ust yozuv jadvalni buzmasin
                            const spanCols = to - rect.from + 1;
                            const joinStyle = twoRects ? (rect.main ? 'border-right:0;' : 'border-left:0;') : '';
                            let cellHtml;
                            if (rect.main && markMode) {
                                // Kafedra mudiri: blok ichida kun × soat to'ri.
                                // Ustunlar — blokning kalendar kunlari, qatorlar
                                // — kunlik soatlar; ma'ruza kataklari bo'yaladi.
                                // Ichki jadval: har kun KTR bo'yicha — ma'ruza (M), amaliy (A),
                                // bo'sh soat; blok ichidagi dam olish/bayram ustuni chiziqli.
                                const plan = block.day_plan || {};
                                let sumLec = 0, sumPrc = 0;
                                Object.values(plan).forEach(dp => { sumLec += +dp.lec || 0; sumPrc += +dp.prc || 0; });
                                const lecTotal = block.lecture_hours, prcTotal = block.practice_hours;
                                const sumLabel = 'M ' + sumLec + (lecTotal != null ? '/' + lecTotal : '') + ' · A ' + sumPrc + (prcTotal != null ? '/' + prcTotal : '') + ' s';
                                // Tor blokda yorliq sig'maydi: o'qituvchi/xona faqat sichqoncha ustiga borganda.
                                // Blok ichida ko'rinadigan kunlar (yashirilgan dam olish/bayram ustunlarisiz).
                                const visDays = [];
                                for (let d = 0; d < spanCols; d++) if (!cycleColHidden(rect.from + d)) visDays.push(d);
                                if (!visDays.length) for (let d = 0; d < spanCols; d++) visDays.push(d);
                                const compact = visDays.length <= 6;
                                // Blokni kataklar to'liq qoplaydi va ularning o'z title'i bor — td title'i
                                // hech qachon chiqmaydi. Shuning uchun tor blokda ma'lumot katak title'iga qo'shiladi.
                                const blockTip = compact ? ' — ' + block.subject + (req ? ' · ' + req : '') + ' · ' + sumLabel : '';
                                let inner = '';
                                for (let hour = 1; hour <= dayHours; hour++) {
                                    for (const d of visDays) {
                                        const dayIndex = rect.from + d;
                                        const date = dates[dayIndex];
                                        const off = date && (date.sunday || date.holiday || date.off);
                                        const offset = dayIndex - block.from;
                                        const dp = plan[offset];
                                        let kind = '';
                                        if (!off && dp) {
                                            const st = +dp.start || 1;
                                            if (hour >= st && hour < st + (+dp.lec || 0)) kind = ' is-lecture';
                                            else if (hour >= st + (+dp.lec || 0) && hour < st + (+dp.lec || 0) + (+dp.prc || 0)) kind = ' is-practice';
                                        }
                                        const offLabel = off && hour === Math.ceil(dayHours / 2) ? (date.holiday ? 'bayram' : 'dam') : '';
                                        inner += '<button type="button" class="cyc-mk-cell'
                                            + (off ? ' is-off' + (date.holiday ? ' is-holiday' : '') : '')
                                            + kind
                                            + (hour % 2 === 0 ? ' is-pair-end' : '')
                                            + (hour === 1 ? ' is-first' : '') + (hour === dayHours ? ' is-last' : '')
                                            + '" data-mk-key="' + esc(block.key) + '"'
                                            + ' data-mk-day="' + offset + '" data-mk-hour="' + hour + '"'
                                            + (offLabel ? ' data-off-label="' + offLabel + '"' : '')
                                            + (off ? ' disabled' : '')
                                            + ' title="' + (date ? esc(date.d) : '') + (off ? (date.holiday ? ' · bayram' : ' · dam olish kuni') : (dp ? ' · ' + dp.k + '-kun · ' + hour + '-soat' : ' · ' + hour + '-soat')) + esc(blockTip) + '"></button>';
                                    }
                                }
                                cellHtml = '<td class="cyc-cell cyc-block is-mark" rowspan="' + lanes + '" colspan="' + spanCols + '" data-cycle-row="' + esc(row.row_key) + '" data-cycle-pair="' + lp + '" data-cycle-from="' + rect.from + '" data-cycle-span="' + spanCols + '" data-cycle-key="' + esc(block.key) + '" style="background:' + color.bg + ';border-color:' + color.border + ';' + joinStyle + '" title="' + esc(block.subject) + ' — ' + block.days + ' kun · ' + esc(sumLabel) + (req ? ' · ' + esc(req) : '') + '">' +
                                    '<div class="cyc-mk">' +
                                    '<div class="cyc-mk-grid" style="grid-template-columns:repeat(' + visDays.length + ',1fr);grid-template-rows:repeat(' + dayHours + ',1fr)">' +
                                    inner +
                                    '<div class="cyc-mk-over' + (compact ? ' is-compact' : '') + '" style="' + cycTitlePosStyle(block, plan, dayHours) + '">' +
                                    // O'qituvchi biriktirilmagan fan: kulrang fon, qizil uzuq hoshiya va belgi.
                                    (block.teacher_name
                                        ? '<span class="cyc-mk-title" role="button" tabindex="0" data-mk-subj="' + esc(block.key) + '" title="Bosing — fan haqida (soatlar, o\'qituvchi, xona). Ushlab blok ichida siljiting; ikki marta bosing — markazga qaytadi." style="background:color-mix(in srgb, ' + color.bg + ' 62%, transparent);border:1px solid ' + color.border + '">'
                                        : '<span class="cyc-mk-title is-noteacher" role="button" tabindex="0" data-mk-subj="' + esc(block.key) + '" title="O\'qituvchi biriktirilmagan — ⚙ orqali biriktiring. Bosing — fan haqida; ushlab siljiting; ikki marta bosing — markazga qaytadi.">'
                                            + '<i class="bi bi-person-fill-exclamation" aria-label="O\'qituvchi biriktirilmagan"></i> ') +
                                    esc(block.subject) + ' <span class="cyc-days">(' + block.days + ')</span></span>' +
                                    // O'qituvchi/xona doskada yozilmaydi — fan nomi bosilganda oynada chiqadi.
                                    '</div>' +
                                    '<button type="button" class="cyc-gear cyc-mk-gear" data-cycle-gear="' + esc(block.key) + '" title="O\'qituvchi / xona biriktirish">&#9881;</button>' +
                                    '</div></div></td>';
                            } else if (rect.main) {
                                cellHtml = '<td class="cyc-cell cyc-block" draggable="true" rowspan="' + lanes + '" colspan="' + spanCols + '" data-cycle-row="' + esc(row.row_key) + '" data-cycle-pair="' + lp + '" data-cycle-from="' + rect.from + '" data-cycle-span="' + spanCols + '" data-cycle-key="' + esc(block.key) + '" style="background:' + color.bg + ';border-color:' + color.border + ';' + joinStyle + '" title="' + esc(block.subject) + ' — ' + block.days + ' kun' + (block.hours ? ' · ' + block.hours + ' soat' : '') + (req ? ' · ' + esc(req) : '') + '">' +
                                    '<span class="cyc-shift-actions">' +
                                    '<button type="button" class="cyc-shift-btn" data-cycle-shift="-1" aria-label="Bir o\'quv kuni orqaga">&#8592;</button>' +
                                    '<button type="button" class="cyc-gear" draggable="false" data-cycle-gear="' + esc(block.key) + '" title="O\'qituvchi / vaqt / xona biriktirish">&#9881;</button>' +
                                    '<button type="button" class="cyc-shift-btn" data-cycle-shift="1" aria-label="Bir o\'quv kuni oldinga">&#8594;</button>' +
                                    '</span><span class="cyc-lbl">' + typeChip + '<b class="cyc-subj">' + esc(block.subject) + '</b> <span class="cyc-days">(' + block.days + ')</span>' + (block.hours ? '<i class="cyc-hrs">' + block.hours + ' s</i>' : '') + '</span>' +
                                    reqHtml +
                                    '</td>';
                            } else {
                                cellHtml = '<td class="cyc-cell cyc-block cyc-cont" rowspan="' + lanes + '" colspan="' + spanCols + '" data-cycle-row="' + esc(row.row_key) + '" data-cycle-pair="' + lp + '" data-cycle-from="' + rect.from + '" data-cycle-span="' + spanCols + '" style="background:' + color.bg + ';border-color:' + color.border + ';' + joinStyle + '" title="' + esc(block.subject) + '"></td>';
                            }
                            cellAt[lp][rect.from] = { html: cellHtml, next: to + 1 };
                            mark(lp, lanes, rect.from, to);
                        });
                    });
                    for (let pair = 1; pair <= pairs; pair++) {
                        h += markMode ? '<tr class="cyc-markrow" style="--mk-rows:' + dayHours + '">' : '<tr>';
                        if (pair === 1) {
                            // Guruh rejimida qator — bitta guruh: nomi to'g'ridan-to'g'ri.
                            // Bo'lakli fan qatori ("Fan (c)"): bo'lak nomi + oqim + qaysi guruhchalardan.
                            const groupLabel = row.variant
                                ? cycleVariantLabelHtml(row)
                                : (j.view || cycleViewMode) === 'group'
                                ? '<div class="cyc-members"><div class="cyc-member-row cyc-member-single"><span title="' + esc(row.group) + '">' + esc(row.group) + '</span></div>'
                                    + (row.member_students ? '<div class="cyc-stu-line">' + cycleStuCountHtml(
                                        Object.values(row.member_students).reduce((a, b) => a + (+b || 0), 0)) + ' talaba</div>' : '')
                                    + '</div>'
                                : cycleMembersHtml(row.group, row.subgroups, row.member_students);
                            h += '<td class="cyc-gcol" rowspan="' + (pairs + (showAddPair ? 1 : 0)) + '">' + groupLabel + '</td>';
                            if (markMode) {
                                // Karta ichidagi soat qatorlari bilan bir tekis
                                // turadigan yonbosh yorliqlar (qo'ng'iroq jadvalidan).
                                let sideRows = '';
                                // Qator — akademik soat. To'liq paralik jadvalda bitta para
                                // 2 soat: 1–2-soat — I-para, 3–4 — II-para, 5–6 — III-para.
                                const hpp = rowMins >= 60 ? 2 : 1;
                                // Har qator — yarim para (40 daqiqa): vaqti para boshidan/oxiridan hisoblanadi.
                                const toMin = t => { const m = /^(\d{1,2}):(\d{2})$/.exec(t || ''); return m ? (+m[1]) * 60 + (+m[2]) : null; };
                                const toHm = v => String(Math.floor(v / 60)).padStart(2, '0') + ':' + String(v % 60).padStart(2, '0');
                                for (let hour = 1; hour <= dayHours; hour++) {
                                    const pt = bellPairs[Math.ceil(hour / hpp) - 1];
                                    const part = (hour - 1) % hpp;           // para ichidagi yarim (0 yoki 1)
                                    const a = pt ? toMin(pt.start) : null, b = pt ? toMin(pt.end) : null;
                                    let span = '';
                                    if (a != null && b != null) {
                                        const step = (b - a) / hpp;
                                        span = toHm(Math.round(a + step * part)) + '–' + toHm(Math.round(a + step * (part + 1)));
                                    }
                                    const pairNo = Math.ceil(hour / hpp);
                                    // Qo'ng'iroq jadvali yarim paralarga bo'lingan bo'lsa (hpp=1), ketma-ket bir xil
                                    // qisqartmali qatorlar ("I", "I") bitta para katagiga birlashadi.
                                    const abbrAt = hh => { const t = bellPairs[Math.ceil(hh / hpp) - 1]; const a = t ? String(t.abbr || '').trim() : ''; return a && a !== '—' ? a : null; };
                                    const sameAsPrev = hpp === 1 && hour > 1 && abbrAt(hour) && abbrAt(hour) === abbrAt(hour - 1);
                                    let groupLen = 1;
                                    while (hpp === 1 && abbrAt(hour) && hour + groupLen <= dayHours && abbrAt(hour + groupLen) === abbrAt(hour)) groupLen++;
                                    if (part === 0 && !sameAsPrev) {
                                        // Para nomi — shu paraning barcha yarim qatorlarini qamraydi.
                                        const rowsInPair = hpp === 1 ? groupLen : Math.min(hpp, dayHours - hour + 1);
                                        const abbr = pt ? (pt.abbr && pt.abbr !== '—' ? pt.abbr : (pt.name || pairNo)) : pairNo;
                                        sideRows += '<div class="cyc-mk-side-para" style="grid-column:1;grid-row:' + hour + ' / span ' + rowsInPair + '"'
                                            + ' title="' + esc(pt ? (pt.name || '') + ' ' + (pt.start || '') + '–' + (pt.end || '') : '') + '">'
                                            + '<b>' + esc(abbr) + '</b><i>para</i></div>';
                                    }
                                    const pairEnd = hpp === 1 ? !(abbrAt(hour) && hour < dayHours && abbrAt(hour + 1) === abbrAt(hour)) : hour % hpp === 0;
                                    sideRows += '<div class="cyc-mk-side-row' + (pairEnd ? ' is-pair-end' : '') + '" style="grid-column:2;grid-row:' + hour + '">' +
                                        (span ? '<span>' + span + '</span>' : '') +
                                        '</div>';
                                }
                                h += '<td class="cyc-mk-side"><div class="cyc-mk-side-box">' +
                                    '<div class="cyc-mk-side-gap"></div>' +
                                    '<div class="cyc-mk-side-rows" style="grid-template-rows:repeat(' + dayHours + ',1fr)">' +
                                    sideRows + '</div></div></td>';
                            }
                        }
                        if (!simple) {
                            const pt = bellPairs[pair - 1];
                            h += '<td class="cyc-pcol"><div class="cyc-pname">' + esc(pt ? (pt.name || pt.abbr || pair) : (pair + '-para')) + '</div>' +
                                (pt && (pt.start || pt.end) ? '<div class="cyc-ptime">' + esc(pt.start || '') + '<br>' + esc(pt.end || '') + '</div>' : '') + '</td>';
                        }
                        let col = 0;
                        while (col < cols) {
                            const cellObj = cellAt[pair][col];
                            if (cellObj) { h += cellObj.html; col = cellObj.next; continue; }
                            if (covered[pair][col]) { col++; continue; }
                            let endCol = col;
                            while (endCol + 1 < cols && !covered[pair][endCol + 1] && !cellAt[pair][endCol + 1]) endCol++;
                            h += '<td class="cyc-cell" colspan="' + (endCol - col + 1) + '" data-cycle-row="' + esc(row.row_key) + '" data-cycle-pair="' + pair + '" data-cycle-from="' + col + '" data-cycle-span="' + (endCol - col + 1) + '"></td>';
                            col = endCol + 1;
                        }
                        h += '</tr>';
                    }
                    if (showAddPair) {
                        h += '<tr class="cyc-addrow"><td class="cyc-pcol"><button type="button" class="cyc-addpair" title="Yana bir para qo\'shish">+</button></td>' +
                            '<td class="cyc-addcell" colspan="' + dates.length + '"></td></tr>';
                    }
                });
                h += '</tbody>';
                $('cycleGrid').innerHTML = h;
                setupCycleGridEvents();
                setupCycleMarking();
                setupCycTitleDrag();
                clampCycTitles();
                renderCycleCards(j);
            }

            // ── Sikl jadvali hodisalari (delegatsiya) ──
            // Har renderda minglab katakka to'rttadan listener ulash drag
            // paytida interfeysni qotirardi; endi butun jadvalga bir marta
            // ulanadi va render faqat innerHTML almashtiradi.
            let cycleDropCell = null;
            let cycleGridEventsReady = false;
            // ── Ma'ruza kataklarini belgilash (kafedra mudiri) ──
            // Bosib yoki sudrab belgilanadi; sichqoncha qo'yib yuborilganda
            // butun blok belgilari bitta so'rovda saqlanadi.
            let mkDrag = null;   // {key, turnOn} — sudrash paytidagi holat

            // Hisoblagich yozuvi: belgilangan soat / rejadagi ma'ruza soati.
            function markCountLabel(marked, total) {
                if (total == null || total === '') return 'M: ' + marked + ' soat';
                const left = total - marked;
                if (left > 0) return 'M: ' + marked + '/' + total + ' soat · qoldi ' + left;
                if (left < 0) return 'M: ' + marked + '/' + total + ' soat · ortiqcha ' + (-left);
                return 'M: ' + marked + '/' + total + ' soat';
            }

            function refreshMarkCount(key) {
                const badge = document.querySelector('.cyc-mk-count[data-mk-count="' + CSS.escape(key) + '"]');
                if (!badge) return;
                const marked = document.querySelectorAll(
                    '.cyc-mk-cell.is-lecture:not(.is-off)[data-mk-key="' + CSS.escape(key) + '"]'
                ).length;
                const raw = badge.dataset.mkTotal;
                const total = raw === '' ? null : Number(raw);
                badge.textContent = markCountLabel(marked, total);
                badge.classList.toggle('is-done', total != null && marked === total);
                badge.classList.toggle('is-over', total != null && marked > total);
            }

            function mkSlotsOf(key) {
                const slots = {};
                document.querySelectorAll('.cyc-mk-cell.is-lecture[data-mk-key="' + CSS.escape(key) + '"]')
                    .forEach(cell => {
                        const day = cell.dataset.mkDay;
                        (slots[day] = slots[day] || []).push(+cell.dataset.mkHour);
                    });
                return slots;
            }

            async function saveLectureSlots(key) {
                const card = (cyclePlanData && cyclePlanData.cycle_cards || []).find(item => item.key === key);
                if (!card) return;
                try {
                    await api(BASE + '/boards/' + board.id + '/cycle-lecture-slots', 'POST', {
                        specialty_name: card.specialty, course: card.course,
                        group_name: card.group, subject_name: card.subject,
                        training_type: card.type || 'practice', view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                        slots_json: JSON.stringify(mkSlotsOf(key)),
                    });
                    // Keyingi renderda belgilar joyida qolsin.
                    const block = (cyclePlanData.rows || [])
                        .flatMap(row => row.blocks || [])
                        .find(item => item.key === key);
                    if (block) block.lecture_slots = mkSlotsOf(key);
                    $('cycleMsg').textContent = 'Ma\'ruza soatlari saqlandi.';
                } catch (e) {
                    alert('Ma\'ruza soatlarini saqlab bo\'lmadi: ' + e.message);
                    loadCyclePlan();
                }
            }

            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.
            let cycDaySel = null;   // {key, offset}
            function cycBlockByKey(key) {
                return (cyclePlanData && cyclePlanData.rows || []).flatMap(row => row.blocks || []).find(b => b.key === key) || null;
            }
            function closeCycDayPop() {
                const pop = document.getElementById('cycDayPop');
                if (pop) pop.remove();
                document.querySelectorAll('#cycleGrid .cyc-mk-cell.is-day-sel')
                    .forEach(c => c.classList.remove('is-day-sel', 'is-day-top', 'is-day-bottom'));
                cycDaySel = null;
            }
            function openCycDayPop(key, offset, anchor) {
                closeCycDayPop();
                const block = cycBlockByKey(key);
                const dp = block && block.day_plan ? block.day_plan[offset] : null;
                if (!block || !dp) return;
                cycDaySel = { key: key, offset: +offset };
                // Faqat shu kuni qo'yilgan darslar (M + A) soatlari belgilanadi — butun kun emas.
                const selFrom = +dp.start || 1, selTo = selFrom + (+dp.lec || 0) + (+dp.prc || 0) - 1;
                const cells = [...document.querySelectorAll('#cycleGrid .cyc-mk-cell[data-mk-key="' + CSS.escape(key) + '"][data-mk-day="' + offset + '"]')]
                    .filter(c => +c.dataset.mkHour >= selFrom && +c.dataset.mkHour <= selTo);
                cells.forEach((c, i) => {
                    c.classList.add('is-day-sel');
                    if (i === 0) c.classList.add('is-day-top');
                    if (i === cells.length - 1) c.classList.add('is-day-bottom');
                });
                const dates = cyclePlanData.dates || [];
                const date = dates[block.from + (+offset)];
                const dayHours = Math.max(1, +cyclePlanData.day_hours || 6);
                const total = (+dp.lec || 0) + (+dp.prc || 0);
                const st = +dp.start || 1;
                // Soatlar oralig'i va qaysi paralarga to'g'ri kelishi (to'liq parada 1 para = 2 soat).
                const bell = boardSchedule().filter(it => it.type === 'pair');
                const pm = t => { const m = /^(\d{1,2}):(\d{2})$/.exec(t || ''); return m ? (+m[1]) * 60 + (+m[2]) : null; };
                const d0 = bell.length ? (pm(bell[0].end) - pm(bell[0].start)) : 80;
                const hpp = d0 >= 60 ? 2 : 1;
                const pairName = h => { const pt = bell[Math.ceil(h / hpp) - 1]; return pt ? (pt.abbr && pt.abbr !== '—' ? pt.abbr : (pt.name || '')) : ''; };
                const range = (a, n) => {
                    if (n <= 0) return '';
                    const b = a + n - 1;
                    const p1 = pairName(a), p2 = pairName(b);
                    const paras = p1 ? (p1 === p2 ? p1 + '-para' : p1 + '–' + p2 + ' para') : '';
                    return a + (n > 1 ? '–' + b : '') + '-soat' + (paras ? ' (' + paras + ')' : '');
                };
                const canEdit = Boolean(cyclePlanData.day_edit);
                const dayCard = (cyclePlanData.cycle_cards || []).find(item => item.key === key);
                const pendingSlot = pendingDaySlot(dayCard, dp.k - 1);
                // Tasdiq kutilayotgan bo'lsa, keyingi ↑/↓ shu so'ralgan soatdan hisoblanadi.
                // Faqat o'z so'rovi: registrator/o'quv bo'limi boshqaning so'rovidan emas, joriy soatdan siljitadi.
                const baseSt = pendingSlot && pendingSlot.mine ? +pendingSlot.payload.start_hour : st;
                const pop = document.createElement('div');
                pop.id = 'cycDayPop';
                pop.innerHTML =
                    '<div class="dp-head"><div><div class="dp-title">' + esc(block.subject) + '</div>' +
                    '<div class="dp-sub">' + dp.k + '-kun' + (date ? ' · ' + esc(date.d) : '') + ' · jami ' + total + ' soat</div></div>' +
                    '<button type="button" class="dp-close" aria-label="Yopish">&times;</button></div>' +
                    (dp.lec > 0 ? '<div class="dp-row"><span class="dp-tag l">M</span><div><b>Ma\'ruza ' + dp.lec + ' s</b> <span class="dp-sub">' + range(st, +dp.lec) + '</span>' +
                        (dp.tl ? '<div class="dp-topic">' + esc(dp.tl) + '</div>' : '') + '</div></div>' : '') +
                    (dp.prc > 0 ? '<div class="dp-row"><span class="dp-tag p">A</span><div><b>Amaliy ' + dp.prc + ' s</b> <span class="dp-sub">' + range(st + (+dp.lec || 0), +dp.prc) + '</span>' +
                        (dp.tp ? '<div class="dp-topic">' + esc(dp.tp) + '</div>' : '') + '</div></div>' : '') +
                    (total === 0 ? '<div class="dp-sub">Bu kuni KTR bo\'yicha dars yo\'q.</div>' : '') +
                    (dp.ktr ? '' : '<div class="dp-note">KTR kiritilmagan — soatlar reja bo\'yicha taxminiy.</div>') +
                    (pendingSlot ? '<div class="dp-pending">&#9203; So\'rov: darslar <b>' + (+pendingSlot.payload.start_hour) + '-soatdan</b> — registrator tasdig\'i kutilmoqda</div>' : '') +
                    (canEdit && total > 0
                        ? '<div class="dp-actions"><button type="button" data-dp-move="-1" title="Bir soat oldinga (yuqoriga)"' + (baseSt <= 1 ? ' disabled' : '') + '>&#8593;</button>' +
                          '<button type="button" data-dp-move="1" title="Bir soat keyinga (pastga)"' + (baseSt + total - 1 >= dayHours ? ' disabled' : '') + '>&#8595;</button>' +
                          '<span class="dp-sub">Darslar ' + st + '-soatdan boshlanadi</span></div>'
                        : '');
                document.body.appendChild(pop);
                const r = anchor.getBoundingClientRect();
                const left = Math.min(window.innerWidth - pop.offsetWidth - 8, r.right + 8);
                const top = Math.min(window.innerHeight - pop.offsetHeight - 8, Math.max(8, r.top - 20));
                pop.style.left = Math.max(8, left) + 'px';
                pop.style.top = top + 'px';
                pop.querySelector('.dp-close').onclick = closeCycDayPop;
                pop.querySelectorAll('[data-dp-move]').forEach(btn => btn.onclick = () => moveCycDay(key, +offset, baseSt + (+btn.dataset.dpMove),
                    (date ? date.d + ' (' + dp.k + '-kun)' : dp.k + '-kun') + ': ' + st + '-soatdan → ' + (baseSt + (+btn.dataset.dpMove)) + '-soatdan'));
            }
            // Fan nomi bosilganda: blok bo'yicha jami soatlar, sanalar, o'qituvchi va xona.
            function openCycSubjPop(key, anchor) {
                closeCycDayPop();
                const block = cycBlockByKey(key);
                if (!block) return;
                cycDaySel = { key: key, offset: -1 };
                const plan = Object.values(block.day_plan || {});
                const sumLec = plan.reduce((a, d) => a + (+d.lec || 0), 0);
                const sumPrc = plan.reduce((a, d) => a + (+d.prc || 0), 0);
                const hasKtr = plan.some(d => d.ktr);
                const dates = cyclePlanData.dates || [];
                const d1 = dates[block.from], d2 = dates[block.to];
                const cmp = (got, plan) => plan == null ? got + ' s' : got + ' / ' + plan + ' s' + (+got !== +plan ? ' <b style="color:#b91c1c">(farq ' + (got - plan) + ')</b>' : '');
                const pop = document.createElement('div');
                pop.id = 'cycDayPop';
                pop.innerHTML =
                    '<div class="dp-head"><div><div class="dp-title">' + esc(block.subject) + '</div>' +
                    '<div class="dp-sub">' + block.days + ' kun' + (d1 && d2 ? ' · ' + esc(d1.d) + ' – ' + esc(d2.d) : '') + '</div></div>' +
                    '<button type="button" class="dp-close" aria-label="Yopish">&times;</button></div>' +
                    '<div class="dp-row"><span class="dp-tag l">M</span><div><b>Ma\'ruza</b> <span class="dp-sub">' + cmp(sumLec, block.lecture_hours) + '</span></div></div>' +
                    '<div class="dp-row"><span class="dp-tag p">A</span><div><b>Amaliy</b> <span class="dp-sub">' + cmp(sumPrc, block.practice_hours) + '</span></div></div>' +
                    '<div class="dp-row"><span style="width:16px;text-align:center">&#128100;</span><div><b>O\'qituvchi:</b> ' + (block.teacher_name ? esc(block.teacher_name) : '<span class="dp-sub">biriktirilmagan</span>') + '</div></div>' +
                    '<div class="dp-row"><span style="width:16px;text-align:center">&#127979;</span><div><b>Xona:</b> ' + (block.auditorium_name || block.auditorium_code ? esc(block.auditorium_name || block.auditorium_code) : '<span class="dp-sub">biriktirilmagan</span>') + '</div></div>' +
                    (hasKtr ? '' : '<div class="dp-note">KTR kiritilmagan — soatlar reja bo\'yicha taxminiy.</div>') +
                    '<div class="dp-actions"><span class="dp-sub">Kun bo\'yicha ma\'lumot uchun M / A katagini bosing.</span></div>';
                document.body.appendChild(pop);
                const r = anchor.getBoundingClientRect();
                pop.style.left = Math.max(8, Math.min(window.innerWidth - pop.offsetWidth - 8, r.left)) + 'px';
                pop.style.top = Math.max(8, Math.min(window.innerHeight - pop.offsetHeight - 8, r.bottom + 6)) + 'px';
                pop.querySelector('.dp-close').onclick = closeCycDayPop;
            }

            async function moveCycDay(key, offset, newStart, hint) {
                const card = (cyclePlanData && cyclePlanData.cycle_cards || []).find(item => item.key === key);
                const block = cycBlockByKey(key);
                const dp = block && block.day_plan ? block.day_plan[offset] : null;
                if (!card || !dp) return;
                try {
                    const res = await api(BASE + '/boards/' + board.id + '/cycle-lecture-slots', 'POST', {
                        specialty_name: card.specialty, course: card.course,
                        group_name: card.group, subject_name: card.subject,
                        training_type: card.type || 'practice', view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                        sub_group: card.placement_group && card.placement_group !== card.group ? card.group : undefined,
                        day_index: dp.k - 1, start_hour: newStart,
                        _change_hint: hint || undefined,
                    });
                    if (res && res.pending_approval) {
                        // So'rov bo'ldi: umumiy qayta yuklash o'rniga shu yerda — oyna so'ralgan soat bilan qayta ochiladi.
                        clearTimeout(pendingReloadTimer);
                        await loadChangeRequests();
                    }
                    await loadCyclePlan();
                    const cells = document.querySelectorAll('#cycleGrid .cyc-mk-cell[data-mk-key="' + CSS.escape(key) + '"][data-mk-day="' + offset + '"]');
                    // O'zgargan kun ustuni bir necha soniya yonib turadi — nima o'zgargani ko'rinsin.
                    cells.forEach(c => { c.classList.remove('cyc-just-changed'); void c.offsetWidth; c.classList.add('cyc-just-changed'); });
                    setTimeout(() => cells.forEach(c => c.classList.remove('cyc-just-changed')), 2600);
                    const cell = cells[0];
                    if (cell) openCycDayPop(key, offset, cell);
                } catch (e) {
                    alert('Kun soatini o\'zgartirib bo\'lmadi: ' + e.message);
                }
            }

            function setupCycleMarking() {
                const grid = $('cycleGrid');
                if (!grid || grid.dataset.mkReady) return;
                grid.dataset.mkReady = '1';
                grid.addEventListener('click', ev => {
                    // Guruhcha qatoridagi bo'lak ishorasi: o'sha bo'lak qatorini ochadi.
                    const ghost = ev.target.closest ? ev.target.closest('.cyc-ghost-btn[data-ghost-row]') : null;
                    if (ghost) {
                        ev.preventDefault();
                        cycleRowFilter = ghost.dataset.ghostRow;
                        if (cyclePlanData) renderCyclePlan(cyclePlanData);
                        return;
                    }
                    const subj = ev.target.closest ? ev.target.closest('.cyc-mk-title[data-mk-subj]') : null;
                    if (subj) {
                        ev.preventDefault();
                        if (cycTitleDragged) { cycTitleDragged = false; return; }   // siljitish tugadi — popover ochilmaydi
                        if (cycDaySel && cycDaySel.key === subj.dataset.mkSubj && cycDaySel.offset === -1) { closeCycDayPop(); return; }
                        openCycSubjPop(subj.dataset.mkSubj, subj);
                        return;
                    }
                    const cell = ev.target.closest ? ev.target.closest('.cyc-mk-cell') : null;
                    if (!cell || cell.disabled) return;
                    ev.preventDefault();
                    if (cycDaySel && cycDaySel.key === cell.dataset.mkKey && cycDaySel.offset === +cell.dataset.mkDay) {
                        closeCycDayPop();
                        return;
                    }
                    openCycDayPop(cell.dataset.mkKey, cell.dataset.mkDay, cell);
                });
                document.addEventListener('keydown', ev => { if (ev.key === 'Escape') closeCycDayPop(); });
                // Fan nomi (role=button) klaviaturadan ham ochiladi: Enter / Space.
                grid.addEventListener('keydown', ev => {
                    if (ev.key !== 'Enter' && ev.key !== ' ') return;
                    const subj = ev.target.closest ? ev.target.closest('.cyc-mk-title[data-mk-subj]') : null;
                    if (!subj) return;
                    ev.preventDefault();
                    subj.click();
                });
                document.addEventListener('mousedown', ev => {
                    if (!cycDaySel) return;
                    if (ev.target.closest && (ev.target.closest('#cycDayPop') || ev.target.closest('.cyc-mk-cell') || ev.target.closest('.cyc-mk-title'))) return;
                    closeCycDayPop();
                });
            }

            function setupCycleGridEvents() {
                if (cycleGridEventsReady) return;
                cycleGridEventsReady = true;
                const grid = $('cycleGrid');
                const cellOf = ev => ev.target && ev.target.closest ? ev.target.closest('[data-cycle-from]') : null;
                // Birlashtirilgan katakda aniq kun: katak boshidan sichqoncha
                // X-koordinatasigacha nechta ustun borligi hisoblanadi.
                const indexAt = (cell, ev) => {
                    const span = Math.max(1, +cell.dataset.cycleSpan || 1);
                    const visible = cycleVisibleIdx(+cell.dataset.cycleFrom || 0, span);
                    const rect = cell.getBoundingClientRect();
                    const colW = rect.width / visible.length;
                    const off = colW > 0 && ev.clientX != null ? Math.floor((ev.clientX - rect.left) / colW) : 0;
                    return visible[Math.min(visible.length - 1, Math.max(0, off))];
                };
                const clearDropMark = () => {
                    if (cycleDropCell) { cycleDropCell.classList.remove('cycle-drop-target'); cycleDropCell = null; }
                    clearCycleDropMark();
                };
                grid.addEventListener('dragenter', ev => { if (cellOf(ev)) ev.preventDefault(); });
                grid.addEventListener('dragover', ev => {
                    const cell = cellOf(ev);
                    if (!cell) return;
                    // dragover'da preventDefault bo'lmasa brauzer drop'ni umuman bermaydi.
                    ev.preventDefault();
                    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
                    if (cycleDropCell !== cell) { cycleDropCell = cell; }

                    // Nishon aynan sichqoncha turgan kun ustuniga tushadi.
                    const index = indexAt(cell, ev);
                    const card = (cyclePlanData && cyclePlanData.cycle_cards || [])
                        .find(item => item.key === cycleDragKey);
                    const allowed = Boolean(card) && card.row_key === cell.dataset.cycleRow;

                    markCycleDrop(cell, index, allowed);
                });
                grid.addEventListener('dragleave', ev => {
                    if (!ev.relatedTarget || !grid.contains(ev.relatedTarget)) clearDropMark();
                });
                grid.addEventListener('drop', async ev => {
                    const cell = cellOf(ev);
                    if (!cell) return;
                    ev.preventDefault();
                    clearDropMark();
                    // Kalit sudrash boshlanganda saqlanadi; ba'zi brauzerlarda
                    // u yo'qolsa, dataTransfer'dagi nusxadan olinadi.
                    const key = cycleDragKey || (ev.dataTransfer && ev.dataTransfer.getData('text/plain')) || '';
                    const card = (cyclePlanData.cycle_cards || []).find(item => item.key === key);
                    const rowKey = cell.dataset.cycleRow;
                    const index = indexAt(cell, ev);
                    cycleDragKey = null;
                    if (!card) {
                        showCyclePlacementResult(null, index, 'Karta ma\u2019lumoti topilmadi. Sahifani yangilab qayta urinib ko\u2018ring.');
                        return;
                    }
                    if (card.row_key !== rowKey) {
                        console.warn('Sikl: qator mos kelmadi', {kartaQatori: card.row_key, katakQatori: rowKey});
                        showCyclePlacementResult(card, index, 'Fan kartasini faqat o\u2018z oqimining qatoriga joylang.');
                        return;
                    }
                    await placeCycleCardAt(card, index, +cell.dataset.cyclePair || 1);
                });
                grid.addEventListener('dragstart', ev => {
                    // Belgilash rejimida bloklar ko'chirilmaydi.
                    if (cyclePlanData && cyclePlanData.mark_mode) { ev.preventDefault(); return; }
                    if (ev.target.closest && ev.target.closest('[data-cycle-gear]')) { ev.preventDefault(); return; }
                    const block = ev.target && ev.target.closest ? ev.target.closest('.cyc-block[data-cycle-key]') : null;
                    if (!block) return;
                    cycleDragKey = block.dataset.cycleKey;
                    ev.dataTransfer.effectAllowed = 'move';
                    ev.dataTransfer.setData('text/plain', cycleDragKey);
                    highlightCycleRow(cycleDragKey);
                });
                grid.addEventListener('dragend', () => { cycleDragKey = null; highlightCycleRow(null); clearDropMark(); });
                grid.addEventListener('click', async ev => {
                    const addBtn = ev.target.closest ? ev.target.closest('.cyc-addpair') : null;
                    if (addBtn) {
                        ev.preventDefault();
                        cyclePairsShown = (cyclePairsShown || 1) + 1;
                        if (cyclePlanData) renderCyclePlan(cyclePlanData);
                        return;
                    }
                    const gear = ev.target.closest ? ev.target.closest('[data-cycle-gear]') : null;
                    if (gear) { ev.preventDefault(); ev.stopPropagation(); openCycleAssign(gear.dataset.cycleGear); return; }
                    const shiftBtn = ev.target.closest ? ev.target.closest('[data-cycle-shift]') : null;
                    if (shiftBtn) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        const block = shiftBtn.closest('.cyc-block');
                        const card = (cyclePlanData.cycle_cards || []).find(item => item.key === (block && block.dataset.cycleKey));
                        if (!card || !card.placed) return;
                        const direction = +shiftBtn.dataset.cycleShift;
                        shiftBtn.disabled = true;
                        try {
                            const result = await api(BASE + '/boards/' + board.id + '/cycle-place', 'POST', {
                                ...cycleOverlapParams(), ...cycleLayerParams(),
                                action: 'shift', specialty_name: card.specialty, course: card.course,
                                group_name: card.group, subject_name: card.subject, start_index: card.start_index,
                                training_type: card.type || 'practice', pair: card.pair || 1, view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                                direction: direction, start_date: $('cycleStart').value, holidays: cycleHolidays,
                            });
                            await loadCyclePlan();
                            $('cycleMsg').textContent = (result.shifted || 0) + ' ta sikl karta ' +
                                (direction < 0 ? '1 o\'quv kuni orqaga' : '1 o\'quv kuni oldinga') + ' surildi.';
                        } catch (e) {
                            alert('Sikl kartalarini surib bo\'lmadi: ' + e.message);
                        } finally {
                            shiftBtn.disabled = false;
                        }
                        return;
                    }
                    const cell = cellOf(ev);
                    if (!cell || !cycleDragKey) return;
                    const card = (cyclePlanData.cycle_cards || []).find(item => item.key === cycleDragKey);
                    const rowKey = cell.dataset.cycleRow;
                    const index = indexAt(cell, ev);
                    cycleDragKey = null;
                    highlightCycleRow(null);
                    if (!card || card.row_key !== rowKey) {
                        showCyclePlacementResult(card, index, card
                            ? 'Fan kartasini faqat o\u2018z oqimining qatoriga joylang.'
                            : 'Karta ma\u2019lumoti topilmadi. Sahifani yangilab qayta urinib ko\u2018ring.');
                        return;
                    }
                    placeCycleCardAt(card, index, +cell.dataset.cyclePair || 1);
                });
            }
            function renderCycleCards(j) {
                const cycleCards = (j && j.cycle_cards) || [];
                const groups = [...new Set(cycleCards.map(card => card.group).filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, 'uz', { numeric: true }));
                const groupFilter = $('cycleGroupFilter');
                groupFilter.innerHTML = '<option value="">Barcha oqimlar</option>' + groups.map(group =>
                    '<option value="' + esc(group) + '" title="' + esc(group) + '">' + esc(group) + '</option>'
                ).join('');
                if (!groups.includes(cycleGroupFilter)) cycleGroupFilter = '';
                groupFilter.value = cycleGroupFilter;
                groupFilter.classList.toggle('hidden', !groups.length);
                groupFilter.onchange = () => {
                    cycleGroupFilter = groupFilter.value;
                    renderCycleCards(j);
                };
                $('skipToggle').classList.add('hidden');
                $('unplacedExportBtn').classList.add('hidden');
                $('subjectColorsBtn').classList.toggle('hidden', !cycleCards.length);
                $('cardPanelTitle').textContent = 'Sikl fan kartalari';
                if (j && j.mark_mode && j.mark_ready === false) {
                    $('cycleMsg').textContent = 'Diqqat: ma' + String.fromCharCode(39) + 'ruza belgilari uchun migratsiya bajarilmagan (php artisan migrate).';
                }
                $('cardPanelHint').textContent = (j && j.mark_mode)
                    ? 'Blok ichidagi kataklarni bosing yoki sudrang — belgilangan soatlar ma\'ruza bo\'ladi.'
                    : 'Kartani bosing, so‘ng o‘z oqim qatoridagi boshlanish kunini bosing (yoki sudrab tashlang)';
                $('cardPanelHint').classList.remove('hidden');
                const unplaced = cycleCards.filter(card => !card.placed &&
                    (!cycleGroupFilter || card.group === cycleGroupFilter)).sort((a, b) =>
                    (a.group + a.subject).localeCompare(b.group + b.subject, 'uz', { numeric: true }));
                $('unplacedCount').textContent = unplaced.length + ' ta';
                $('cardPanel').innerHTML = unplaced.map(card => {
                    // Panel kartasi jadvaldagi blok bilan bir xil rangda — qaysi fan
                    // qayerga tushishi ko'rinib tursin.
                    const color = subjColor(card.subject);
                    const typeChip = (j && j.simple)
                        ? ''
                        : '<span class="cycle-pn-type ' + (card.type || 'practice') + '">' + (card.type === 'lecture' ? "Ma'ruza" : 'Amaliy') + '</span>';
                    return '<div class="cycle-pn-card" draggable="true" data-cycle-key="' + esc(card.key) +
                        '" style="background:' + color.bg + ';border-left-color:' + color.border + ';" ' +
                        'title="' + esc(card.subject) + ' — ' + esc(card.group) + '">' +
                        '<span class="cycle-pn-subject">' + typeChip + esc(card.subject) + '</span>' +
                        '<span class="cycle-pn-meta"><span>' + esc(cycleFlowShort(card.group)) + ' · ' + esc(card.course) + '-kurs</span><span class="cycle-pn-days">' + card.days + ' kun' + (card.hours ? ' · ' + card.hours + ' soat' : '') + '</span></span></div>';
                }).join('') || '<div class="text-xs text-slate-400 p-2">Barcha sikl fan kartalari joylashgan.</div>';
                $('cardPanel').querySelectorAll('.cycle-pn-card').forEach(card => {
                    // Bosib tanlash: sudrash ishlamagan holatlarda ham karta
                    // joylanadi — tanlangan karta belgilanib turadi, keyin
                    // jadvaldan boshlanish kuni bosiladi.
                    card.addEventListener('click', () => {
                        const already = cycleDragKey === card.dataset.cycleKey;
                        $('cardPanel').querySelectorAll('.cycle-pn-card').forEach(other =>
                            other.classList.remove('cycle-pn-selected'));
                        cycleDragKey = already ? null : card.dataset.cycleKey;
                        card.classList.toggle('cycle-pn-selected', !already);
                        highlightCycleRow(already ? null : card.dataset.cycleKey);
                        $('cycleMsg').textContent = already ? ''
                            : 'Endi shu oqim qatoridagi boshlanish kunini bosing.';
                    });
                    card.addEventListener('dragstart', ev => {
                        cycleDragKey = card.dataset.cycleKey;
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', cycleDragKey);
                        highlightCycleRow(cycleDragKey);
                    });
                    card.addEventListener('dragend', () => { cycleDragKey = null; highlightCycleRow(null); });
                });
                $('cardPanel').ondragover = ev => { if (cycleDragKey) ev.preventDefault(); };
                $('cardPanel').ondrop = async ev => {
                    ev.preventDefault();
                    if (!cycleDragKey) return;
                    const card = cycleCards.find(item => item.key === cycleDragKey);
                    cycleDragKey = null;
                    if (!card || !card.placed) return;
                    try {
                        await api(BASE + '/boards/' + board.id + '/cycle-place', 'POST', {
                            ...cycleOverlapParams(), ...cycleLayerParams(),
                            action: 'remove', specialty_name: card.specialty, course: card.course,
                            group_name: card.group, subject_name: card.subject,
                            training_type: card.type || 'practice', view: cycleEffView(), placement_group: (card && card.placement_group) || undefined,
                        });
                        await loadCyclePlan();
                    } catch (e) { alert('Sikl blokini olib bo‘lmadi: ' + e.message); }
                };
            }
            // ── Fan ranglari modali ──
            // Ro'yxat modal ochilganda quriladi; tanlangan ranglar Saqlashda
            // doska sozlamalariga yoziladi va jadval qayta chiziladi.
            let scDraft = {};
            function openSubjectColorsModal() {
                const names = [...new Set(((cyclePlanData && cyclePlanData.cycle_cards) || [])
                    .map(card => card.subject).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'uz'));
                if (!names.length) {
                    alert('Sikl fanlari topilmadi.');
                    return;
                }
                scDraft = { ...subjectColorOverrides };
                $('scList').innerHTML = names.map(name => {
                    const color = subjColor(name);
                    const custom = scDraft[name] ? ' checked' : '';
                    return '<label class="flex items-center gap-3 py-1.5 border-b border-gray-50 last:border-0">' +
                        '<input type="color" class="sc-input w-9 h-8 p-0 border border-gray-200 rounded cursor-pointer" ' +
                            'data-subject="' + esc(name) + '" value="' + esc(color.bg) + '">' +
                        '<span class="flex-1 text-xs text-gray-700">' + esc(name) + '</span>' +
                        '<span class="text-[10px] text-gray-400 flex items-center gap-1">' +
                            '<input type="checkbox" class="sc-auto" data-subject="' + esc(name) + '"' +
                            (custom ? '' : ' checked') + '> avtomatik</span></label>';
                }).join('');
                $('scList').querySelectorAll('.sc-input').forEach(input => {
                    input.oninput = () => {
                        scDraft[input.dataset.subject] = input.value.toLowerCase();
                        const auto = $('scList').querySelector('.sc-auto[data-subject="' + CSS.escape(input.dataset.subject) + '"]');
                        if (auto) auto.checked = false;
                    };
                });
                $('scList').querySelectorAll('.sc-auto').forEach(box => {
                    box.onchange = () => {
                        if (box.checked) delete scDraft[box.dataset.subject];
                        else scDraft[box.dataset.subject] = subjColor(box.dataset.subject).bg;
                    };
                });
                $('subjectColorsModal').classList.remove('hidden');
            }
            if ($('subjectColorsBtn')) $('subjectColorsBtn').onclick = openSubjectColorsModal;
            if ($('scClose')) $('scClose').onclick = () => $('subjectColorsModal').classList.add('hidden');
            if ($('scReset')) $('scReset').onclick = () => { scDraft = {}; openSubjectColorsModalRefresh(); };
            function openSubjectColorsModalRefresh() {
                subjectColorOverrides = { ...scDraft };
                $('subjectColorsModal').classList.add('hidden');
                openSubjectColorsModal();
            }
            if ($('scSave')) $('scSave').onclick = async () => {
                subjectColorOverrides = { ...scDraft };
                try {
                    await saveSubjectColorOverrides();
                    $('subjectColorsModal').classList.add('hidden');
                    if (cyclePlanData) { renderCyclePlan(cyclePlanData); renderCycleCards(cyclePlanData); }
                } catch (e) { alert('Ranglarni saqlab bo‘lmadi: ' + e.message); }
            };

            if ($('cycleRefresh')) $('cycleRefresh').onclick = () => loadCyclePlan();
            // Ko'rinish rejimi: oqim bo'yicha / guruh bo'yicha
            function setCycleViewMode(mode) {
                if (cycleViewMode === mode) return;
                cycleViewMode = mode;
                const on = ['bg-blue-600', 'font-semibold', 'text-white'];
                const off = ['bg-white', 'text-gray-500'];
                [['cycViewFlow', 'flow'], ['cycViewGroup', 'group']].forEach(([id, m]) => {
                    const btn = $(id);
                    if (!btn) return;
                    btn.classList.remove(...on, ...off);
                    btn.classList.add(...(m === mode ? on : off));
                });
                loadCyclePlan();
            }
            if ($('cycViewFlow')) $('cycViewFlow').onclick = () => setCycleViewMode('flow');
            if ($('cycViewGroup')) $('cycViewGroup').onclick = () => setCycleViewMode('group');
            if ($('cycleStart')) $('cycleStart').onchange = loadCyclePlan;
            // Qatlam: tashqi / ichki
            function setCycleLayer(layer) {
                if (cycleLayer === layer) return;
                cycleLayer = layer;
                renderCycleLayerUi(null);
                loadCyclePlan();
            }
            if ($('cycLayerExternal')) $('cycLayerExternal').onclick = () => setCycleLayer('external');
            function renderMineUi() {
                [['mineAll', 'all'], ['mineKafedra', 'kafedra'], ['mineSubjects', 'subjects'], ['mineOnly', 'mine']].forEach(([id, mode]) => {
                    const b = $(id);
                    if (b) b.className = 'px-2 py-1 ' + (scopeMode === mode ? 'bg-blue-600 font-semibold text-white' : 'bg-white text-gray-500');
                });
            }
            function setScopeMode(mode) {
                if (scopeMode === mode) return;
                scopeMode = mode;
                mineOnly = mode !== 'all';
                try { localStorage.setItem('lms:timetable-mine', mode === 'mine' ? '1' : mode === 'kafedra' ? 'kafedra' : mode === 'subjects' ? 'subjects' : '0'); } catch (e) { }
                renderMineUi();
                cards = scopeCards(allCards);
                if (typeof renderAll === 'function') renderAll();
                if (viewMode === 'cycle') loadCyclePlan();
            }
            function renderWeeklyLayerUi() {
                [['wkLayerExternal', 'external'], ['wkLayerInternal', 'internal']].forEach(([id, l]) => {
                    const b = $(id);
                    if (b) b.className = 'px-2 py-1 ' + (weeklyLayer === l ? 'bg-blue-600 font-semibold text-white' : 'bg-white text-gray-500');
                });
                if ($('weeklyLayerBox')) $('weeklyLayerBox').classList.toggle('hidden', viewMode === 'cycle');
                if ($('weeklyExcel')) $('weeklyExcel').classList.toggle('hidden', viewMode === 'cycle');
                if ($('compactWeekBtn')) $('compactWeekBtn').classList.toggle('hidden',
                    !(WEEKLY_ADMIN && weeklyLayer === 'internal' && curWeek && viewMode !== 'cycle'));
                if ($('suggestWeekBtn')) $('suggestWeekBtn').classList.toggle('hidden',
                    !(WEEKLY_ADMIN && weeklyLayer === 'internal' && curWeek && viewMode !== 'cycle'));
                document.body.classList.toggle('tt-weekly-external', weeklyLayer === 'external');
            }
            async function setWeeklyLayer(l) {
                if (weeklyLayer === l) return;
                weeklyLayer = l;
                try { localStorage.setItem('lms:timetable-weekly-layer', l); } catch (e) { }
                selected = null;
                renderWeeklyLayerUi();
                // Serverdan qayta yuklanadi: shu ko'rinishda joyida qilingan biriktirishlar (o'qituvchi/xona)
                // kartaning birinchi yuklashdagi nusxasidan tiklanib, eskisi qaytib chiqmasin.
                if (board) {
                    try { await loadBoard(board.id); } catch (e) { alert('Xatolik: ' + e.message); }
                }
            }
            if ($('wkLayerExternal')) { $('wkLayerExternal').onclick = () => setWeeklyLayer('external'); $('wkLayerInternal').onclick = () => setWeeklyLayer('internal'); renderWeeklyLayerUi(); }
            if ($('mineAll')) {
                $('mineAll').onclick = () => setScopeMode('all');
                $('mineOnly').onclick = () => setScopeMode('mine');
                if ($('mineKafedra')) $('mineKafedra').onclick = () => setScopeMode('kafedra');
                if ($('mineSubjects')) $('mineSubjects').onclick = () => setScopeMode('subjects');
                renderMineUi();
            }
            if ($('cycLayerInternal')) $('cycLayerInternal').onclick = () => setCycleLayer('internal');
            // Dam olish kunlarini yashirish (brauzerda eslab qolinadi)
            if ($('cycleHideOff')) {
                $('cycleHideOff').checked = cycleHideOff;
                $('cycleHideOff').onchange = () => {
                    cycleHideOff = $('cycleHideOff').checked;
                    try { localStorage.setItem('lms:cycle-hide-off', cycleHideOff ? '1' : '0'); } catch (e) { }
                    if (cyclePlanData) { renderCyclePlan(cyclePlanData); renderCycleCards(cyclePlanData); }
                };
            }
            // Sikl kesishishiga ruxsat (doska bo'yicha brauzerda eslab qolinadi)
            function cycleOverlapKey() { return board ? 'lms:cycle-overlap:' + board.id : ''; }
            function cycleOverlapParams() {
                const on = !!($('cycleAllowOverlap') && $('cycleAllowOverlap').checked);
                const days = Math.max(1, parseInt(($('cycleOverlapDays') || {}).value, 10) || 1);
                const gap = { ignore_gap: $('cycleIgnoreGap') && $('cycleIgnoreGap').checked ? 1 : 0 };
                return on ? { allow_overlap: 1, overlap_days: days, ...gap } : { allow_overlap: 0, ...gap };
            }
            function syncCycleOverlapUi() {
                if (!$('cycleAllowOverlap')) return;
                $('cycleOverlapDays').disabled = !$('cycleAllowOverlap').checked;
                try { localStorage.setItem(cycleOverlapKey(), JSON.stringify(cycleOverlapParams())); } catch (e) { }
            }
            function loadCycleOverlapUi() {
                if (!$('cycleAllowOverlap')) return;
                let saved = null;
                try { saved = JSON.parse(localStorage.getItem(cycleOverlapKey()) || 'null'); } catch (e) { }
                $('cycleAllowOverlap').checked = !!(saved && saved.allow_overlap);
                if (saved && saved.overlap_days) $('cycleOverlapDays').value = saved.overlap_days;
                if ($('cycleIgnoreGap')) $('cycleIgnoreGap').checked = !!(saved && saved.ignore_gap);
                $('cycleOverlapDays').disabled = !$('cycleAllowOverlap').checked;
            }
            if ($('cycleAllowOverlap')) {
                $('cycleAllowOverlap').onchange = syncCycleOverlapUi;
                $('cycleOverlapDays').onchange = syncCycleOverlapUi;
                if ($('cycleIgnoreGap')) $('cycleIgnoreGap').onchange = syncCycleOverlapUi;
            }

            // Bayram qo'shish
            if ($('cycleHolAddBtn')) $('cycleHolAddBtn').onclick = () => {
                const d = $('cycleHolAdd').value;
                if (!d || cycleHolidays.includes(d)) return;
                cycleHolidays = [...cycleHolidays, d].sort();
                $('cycleHolAdd').value = '';
                renderHolChips();
                loadCyclePlan();
            };
            // Bayramni olib tashlash (chipdagi ×)
            if ($('cycleHolList')) $('cycleHolList').addEventListener('click', ev => {
                if (!ev.target.classList.contains('cyc-hol-x')) return;
                cycleHolidays = cycleHolidays.filter(d => d !== ev.target.dataset.d);
                renderHolChips();
                loadCyclePlan();
            });

            // ===== Qoidalar (aSc "Взаимосвязи" uslubida) =====
            let rulesData = [];        // [{id, condition, description, subjects, scopes, weight, active, note}]
            let ruleConditions = {};   // {kod: tavsif}
            let ruleSelId = null;      // ro'yxatda tanlangan qoida id
            let ruleEditing = null;    // tahrirlanayotgan qoida (yoki null = yangi)

            const WEIGHT_LABEL = { majburiy: 'Majburiy', normal: 'Normal', yengil: 'Yengil' };
            function toggleRuleParams(condition) {
                const panel = $('ruleLectureWeekOptions');
                if (panel) panel.classList.toggle('hidden', condition !== 'lecture_week_distribution');
            }

            async function loadRules() {
                if (!board) return;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/rules');
                    rulesData = j.rules || [];
                    ruleConditions = j.conditions || {};
                    renderRulesTable();
                    updateRulesBadge();
                } catch (e) {
                    $('rulesBody').innerHTML = '';
                    $('rulesMsg').textContent = 'Xatolik: ' + e.message;
                }
            }
            function updateRulesBadge() {
                const n = rulesData.filter(r => r.active).length;
                const b = $('rulesBadge');
                if (!b) return;
                b.textContent = n || '';
                b.classList.toggle('hidden', !n);
            }
            const ruleSubjLabel = r => (r.subjects && r.subjects.length)
                ? (r.subjects.length > 2 ? (r.subjects.length + ' ta fan') : r.subjects.join(', '))
                : 'Barcha fanlar';
            const ruleScopeLabel = r => (r.scopes && r.scopes.length)
                ? (r.scopes.length > 2 ? (r.scopes.length + ' ta yo\'nalish·kurs') : r.scopes.join('; '))
                : 'Barchasi';

            function renderRulesTable() {
                $('rulesEmpty').classList.toggle('hidden', rulesData.length > 0);
                $('rulesBody').innerHTML = rulesData.map((r, i) =>
                    '<tr data-id="' + r.id + '" class="' + (r.id === ruleSelId ? 'sel ' : '') + (r.active ? '' : 'off') + '">' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td style="text-align:center">' + (r.active ? '✔' : '✕') + '</td>' +
                    '<td>' + esc(ruleSubjLabel(r)) + '</td>' +
                    '<td>' + esc(ruleScopeLabel(r)) + '</td>' +
                    '<td>' + esc(r.description || r.condition) + '</td>' +
                    '<td><span class="tt-rule-w ' + esc(r.weight) + '">' + esc(WEIGHT_LABEL[r.weight] || r.weight) + '</span></td>' +
                    '<td>' + esc(r.note || '') + '</td></tr>').join('');
                $('rulesBody').querySelectorAll('tr').forEach(tr => {
                    tr.onclick = () => { ruleSelId = +tr.dataset.id; renderRulesTable(); };
                    tr.ondblclick = () => { ruleSelId = +tr.dataset.id; openRuleEditor(rulesData.find(x => x.id === ruleSelId)); };
                });
            }

            // --- Qoidalar oynasi
            if ($('rulesBtn')) $('rulesBtn').onclick = () => {
                if (!board) return;
                $('rulesMsg').textContent = '';
                $('rulesModal').classList.remove('hidden');
                loadRules();
            };
            const closeRules = () => $('rulesModal').classList.add('hidden');
            if ($('rulesClose')) $('rulesClose').onclick = closeRules;
            if ($('rulesOkBtn')) $('rulesOkBtn').onclick = closeRules;

            // --- Qoida muharriri (shart tanlash oynasi)
            function openRuleEditor(rule) {
                ruleEditing = rule || null;
                $('ruleEditTitle').textContent = rule ? 'Qoidani tahrirlash' : 'Yangi qoida';
                $('ruleEditMsg').textContent = '';
                $('ruleWeight').value = rule ? rule.weight : 'normal';
                $('ruleNote').value = rule ? (rule.note || '') : '';
                $('ruleActive').checked = rule ? !!rule.active : true;
                $('ruleSubjSearch').value = '';
                $('ruleLectureWeekMode').value = rule && rule.params && rule.params.distribution
                    ? rule.params.distribution : 'auto';

                // Shartlar ro'yxati (radio)
                const cur = rule ? rule.condition : '';
                $('ruleCondList').innerHTML = Object.entries(ruleConditions).map(([code, label]) =>
                    '<label class="tt-cond-item' + (code === cur ? ' sel' : '') + '">' +
                    '<input type="radio" name="ruleCond" value="' + esc(code) + '"' + (code === cur ? ' checked' : '') + '>' +
                    esc(label) + '</label>').join('');
                $('ruleCondList').querySelectorAll('input[name=ruleCond]').forEach(inp => {
                    inp.onchange = () => {
                        $('ruleCondList').querySelectorAll('.tt-cond-item')
                            .forEach(el => el.classList.toggle('sel', el.contains(inp) && inp.checked));
                        toggleRuleParams(inp.value);
                    };
                });
                toggleRuleParams(cur);

                // Fanlar va qamrov ro'yxatlari — doskadagi kartalardan
                const subs = [...new Set(cards.map(c => c.subject_name))].sort((a, b) => a.localeCompare(b, 'uz'));
                const scopes = [...new Set(cards.map(c => c.specialty_name + ' · ' + c.course))].sort((a, b) => a.localeCompare(b, 'uz'));
                const selSubs = new Set(rule ? rule.subjects : []);
                const selScopes = new Set(rule ? rule.scopes : []);
                $('ruleAllSubjects').checked = !selSubs.size;
                $('ruleAllScopes').checked = !selScopes.size;
                $('ruleSubjList').innerHTML = subs.map(s =>
                    '<label class="tt-pick-item"><input type="checkbox" class="rule-subj" value="' + esc(s) + '"' +
                    (selSubs.has(s) ? ' checked' : '') + '>' + esc(s) + '</label>').join('') ||
                    '<div class="text-[11px] text-gray-400 p-1">Fan yo\'q</div>';
                $('ruleScopeList').innerHTML = scopes.map(s =>
                    '<label class="tt-pick-item"><input type="checkbox" class="rule-scope" value="' + esc(s) + '"' +
                    (selScopes.has(s) ? ' checked' : '') + '>' + esc(s) + '</label>').join('') ||
                    '<div class="text-[11px] text-gray-400 p-1">Yo\'nalish yo\'q</div>';

                $('ruleEditModal').classList.remove('hidden');
            }
            // "Barchasi" belgilansa — alohida tanlovlar tozalanadi
            if ($('ruleAllSubjects')) $('ruleAllSubjects').onchange = function () {
                if (this.checked) $('ruleSubjList').querySelectorAll('.rule-subj').forEach(c => c.checked = false);
            };
            if ($('ruleAllScopes')) $('ruleAllScopes').onchange = function () {
                if (this.checked) $('ruleScopeList').querySelectorAll('.rule-scope').forEach(c => c.checked = false);
            };
            // Alohida fan belgilansa — "Barchasi" olib tashlanadi
            if ($('ruleSubjList')) $('ruleSubjList').addEventListener('change', ev => {
                if (ev.target.classList.contains('rule-subj') && ev.target.checked) $('ruleAllSubjects').checked = false;
            });
            if ($('ruleScopeList')) $('ruleScopeList').addEventListener('change', ev => {
                if (ev.target.classList.contains('rule-scope') && ev.target.checked) $('ruleAllScopes').checked = false;
            });
            // Fan qidirish
            if ($('ruleSubjSearch')) $('ruleSubjSearch').oninput = function () {
                const q = this.value.toLowerCase().trim();
                $('ruleSubjList').querySelectorAll('.tt-pick-item').forEach(el =>
                    el.style.display = (!q || el.textContent.toLowerCase().includes(q)) ? '' : 'none');
            };

            const closeRuleEditor = () => $('ruleEditModal').classList.add('hidden');
            if ($('ruleEditClose')) $('ruleEditClose').onclick = closeRuleEditor;
            if ($('ruleCancelBtn')) $('ruleCancelBtn').onclick = closeRuleEditor;

            if ($('ruleSaveBtn')) $('ruleSaveBtn').onclick = async function () {
                const cond = $('ruleCondList').querySelector('input[name=ruleCond]:checked');
                if (!cond) { $('ruleEditMsg').textContent = 'Shartni tanlang.'; return; }
                const body = {
                    condition: cond.value,
                    weight: $('ruleWeight').value,
                    active: $('ruleActive').checked ? 1 : 0,
                    note: $('ruleNote').value.trim(),
                    subjects: $('ruleAllSubjects').checked ? []
                        : [...$('ruleSubjList').querySelectorAll('.rule-subj:checked')].map(c => c.value),
                    scopes: $('ruleAllScopes').checked ? []
                        : [...$('ruleScopeList').querySelectorAll('.rule-scope:checked')].map(c => c.value),
                };
                if (cond.value === 'lecture_week_distribution') {
                    body.params = { distribution: $('ruleLectureWeekMode').value };
                }
                if (ruleEditing) body.id = ruleEditing.id;
                this.disabled = true;
                try {
                    await api(BASE + '/boards/' + board.id + '/rules', 'POST', body);
                    closeRuleEditor();
                    await loadRules();
                    if (cond.value === 'lecture_week_distribution') {
                        $('rulesMsg').textContent = 'Qoida saqlandi. Qo\'llash uchun Kartochkalarni qayta yarating.';
                    }
                } catch (e) { $('ruleEditMsg').textContent = 'Xatolik: ' + e.message; }
                this.disabled = false;
            };

            // --- Ro'yxat tugmalari
            const selRule = () => rulesData.find(r => r.id === ruleSelId);
            if ($('ruleAddBtn')) $('ruleAddBtn').onclick = () => openRuleEditor(null);
            if ($('ruleEditBtn')) $('ruleEditBtn').onclick = () => {
                const r = selRule();
                if (!r) { $('rulesMsg').textContent = 'Avval qoidani tanlang.'; return; }
                openRuleEditor(r);
            };
            if ($('ruleDelBtn')) $('ruleDelBtn').onclick = async () => {
                const r = selRule();
                if (!r) { $('rulesMsg').textContent = 'Avval qoidani tanlang.'; return; }
                if (!confirm('Qoida o\'chirilsinmi?\n\n' + (r.description || r.condition))) return;
                try {
                    // POST + _method spoofing — fayldagi boshqa o'chirishlar bilan bir xil uslub
                    await api(BASE + '/boards/' + board.id + '/rules/' + r.id, 'POST', { _method: 'DELETE' });
                    ruleSelId = null;
                    await loadRules();
                    $('rulesMsg').textContent = 'O\'chirildi';
                } catch (e) { $('rulesMsg').textContent = 'Xatolik: ' + e.message; }
            };
            async function ruleState(bodyExtra) {
                const r = selRule();
                if (!r) { $('rulesMsg').textContent = 'Avval qoidani tanlang.'; return; }
                try {
                    await api(BASE + '/boards/' + board.id + '/rules/' + r.id + '/state', 'POST', bodyExtra(r));
                    await loadRules();
                    $('rulesMsg').textContent = '';
                } catch (e) { $('rulesMsg').textContent = 'Xatolik: ' + e.message; }
            }
            if ($('ruleToggleBtn')) $('ruleToggleBtn').onclick = () => ruleState(r => ({ active: r.active ? 0 : 1 }));
            if ($('ruleUpBtn')) $('ruleUpBtn').onclick = () => ruleState(() => ({ move: 'up' }));
            if ($('ruleDownBtn')) $('ruleDownBtn').onclick = () => ruleState(() => ({ move: 'down' }));

            function buildGroupRows() {
                groupRows = [];
                flowStudents = {};
                const seen = {};
                const flowGroupSeen = {};
                const groupFlowStudents = {};
                specCards().forEach(c => {
                    const flowKey = (c.faculty_name || '') + '|' + (c.specialty_name || '') + '|' + c.course + '|' + (c.oqim_label || '');
                    if (c.training_type === 'lecture') {
                        flowStudents[flowKey] = Math.max(flowStudents[flowKey] || 0, +c.students || 0);
                    }
                    cardGroups(c).forEach(g => {
                        const k = gkey(c, g);
                        if (!seen[k]) {
                            seen[k] = 1;
                            groupRows.push({ key: k, oqim_label: c.oqim_label || '', lang: c.lang || 'uz', group: g, faculty: c.faculty_name || '', specialty: c.specialty_name || '', course: c.course });
                        }
                        if (c.training_type !== 'lecture') {
                            const groupFlowKey = flowKey + '|' + g;
                            if (!flowGroupSeen[groupFlowKey]) {
                                flowGroupSeen[groupFlowKey] = 1;
                                groupFlowStudents[flowKey] = (groupFlowStudents[flowKey] || 0) + (+c.students || 0);
                            }
                        }
                    });
                });
                // Ma'ruza oqimi mavjud bo'lsa uning jami olinadi; aks holda
                // amaliy kartalardan har bir guruh talabasini faqat bir marta qo'shamiz.
                Object.keys(groupFlowStudents).forEach(flowKey => {
                    if (!Object.prototype.hasOwnProperty.call(flowStudents, flowKey)) {
                        flowStudents[flowKey] = groupFlowStudents[flowKey];
                    }
                });
                // Fakultet → yo'nalish → kurs → oqim → guruh: bir blok ketma-ket tursin.
                const sk = x => x.faculty + '|' + x.specialty + '|' + x.course + '|' + x.oqim_label + x.group;
                groupRows.sort((a, b) => sk(a).localeCompare(sk(b), undefined, { numeric: true }));
            }

            // Karta uzunligi yarim-slot birligida (1=0.5 para, 2=1 para ...). Sukut 2.
            const cardLen = c => Math.max(1, parseInt(c.len_half) || 2);
            // Serverdagi isSharedRoom bilan bir xil: raqamsiz va "mashg'ulot"/"umumiy" so'zli nom.
            const isSharedRoomName = name => {
                const n = String(name || '').trim();
                return n !== '' && !/\d/.test(n) && /mashg|umumiy/i.test(n);
            };
            // Bitta fanning turli bo'laklari ("Fan (a)" va "Fan (b)") — guruhning turli talabalari,
            // bir vaqtda parallel o'tadi: guruh bandligi hisoblanmaydi (serverdagi bolakSiblings).
            const BOLAK_RX = /^(.*?)\s*\((\p{L})\)\s*$/u;
            function bolakSiblings(a, b) {
                const ma = BOLAK_RX.exec(String(a.subject_name || '').trim()), mb = BOLAK_RX.exec(String(b.subject_name || '').trim());
                return !!(ma && mb && ma[1].trim().toLowerCase() === mb[1].trim().toLowerCase() && ma[2].toLowerCase() !== mb[2].toLowerCase());
            }
            // Ikki karta kamida bitta umumiy faol haftaga egami (bekor qilinmagan haftalar, panjara haftalari ichida).
            function weeksIntersect(a, b) {
                const total = Math.min(+a.term_weeks || +curGrid().weeks || +board.weeks || 15, +b.term_weeks || +curGrid().weeks || +board.weeks || 15);
                for (let w = 1; w <= total; w++) {
                    const oa = overrides[a.id + '|' + w], ob = overrides[b.id + '|' + w];
                    if (!(oa && oa.cancelled) && !(ob && ob.cancelled)) return true;
                }
                return false;
            }
            // Konflikt: karta (day,pair) ga qo'yilsa — sabablar ro'yxati (tanlangan hafta effektiv joylashuvi bo'yicha).
            // `pair` — yarim-slot indeksi; dars cardLen(card) ta yarim-slotni egallaydi,
            // shu oraliq [pair, pair+len) boshqa kartaning oralig'i bilan kesishsa — band.
            // Band qilgan dars: "2-son davolash, 3-kurs · d2/d24-05a · Patologik anatomiya (amaliy)".
            function describeCardBrief(o) {
                const gs = cardGroups(o).map(g => String(g).replace(/\s*\(.*\)\s*$/, ''));
                const gText = gs.length > 4 ? gs.slice(0, 4).join(', ') + ' +' + (gs.length - 4) : gs.join(', ');
                return (o.faculty_name ? o.faculty_name + ', ' : '') + o.course + '-kurs'
                    + (o.training_type === 'lecture' && o.oqim_label ? ' · ' + o.oqim_label : '')
                    + (gText ? ' · ' + gText : '') + ' · ' + o.subject_name + ' (' + (o.training_type === 'lecture' ? "ma'ruza" : 'amaliy') + ')';
            }
            function conflictsAt(card, day, pair, ignoredIds = []) {
                const my = cardGKeys(card);   // guruh kaliti (fakultet+yo'nalish+kurs+nom)
                const errs = [];
                const effectiveSource = effectiveCard(card);
                const a0 = pair, a1 = pair + cardLen(card);   // [a0, a1)
                cards.forEach(o => {
                    if (o.id === card.id || ignoredIds.includes(o.id)) return;
                    const effectiveOther = effectiveCard(o);
                    // Tashqi qatlamda joylash andozaga (barcha haftalar) yoziladi va server bandlikni
                    // andoza bo'yicha tekshiradi — haftaning ko'rinishdagi surilishi/bekori hisobga olinmaydi.
                    const pl = weeklyLayer === 'external' ? (o.day && o.pair ? { day: o.day, pair: o.pair } : null) : effPlace(o);
                    if (!pl || pl.day !== day) return;
                    const b0 = pl.pair, b1 = pl.pair + cardLen(o);   // [b0, b1)
                    if (a0 >= b1 || b0 >= a1) return;   // yarim-slot oraliqlari kesishmasa — konflikt yo'q
                    // Guruh bandligi kalit bo'yicha: bir xil nomli, lekin turli
                    // fakultetdagi guruhlar bir-biriga to'sqinlik qilmasin.
                    const ov = cardGKeys(o).filter(k => my.includes(k));
                    // Tashqi jadval — umumiy shablon: hech qachon bir haftaga tushmaydigan kartalar
                    // bitta katakni bo'lisha oladi (serverdagi findExternalConflicts kabi).
                    if (ov.length && !bolakSiblings(card, o) && !(weeklyLayer === 'external' && !weeksIntersect(card, o))) errs.push('Guruh band: ' + ov.map(k => k.split('¦').pop()).join(',') + ' — ' + describeCardBrief(o));
                    if (card.teacher_id && o.teacher_id === card.teacher_id) errs.push("O'qituvchi band: " + o.teacher_name + ' — ' + describeCardBrief(o));
                    // Raqamsiz umumiy xona ("amaliy mashg'ulot xonasi") — bandlik tekshirilmaydi (serverdagi isSharedRoom).
                    if (effectiveSource.auditorium_code && effectiveOther.auditorium_code === effectiveSource.auditorium_code
                        && !isSharedRoomName(effectiveOther.auditorium_name)) errs.push('Auditoriya band: ' + effectiveOther.auditorium_name + ' — ' + describeCardBrief(o));
                });
                return errs;
            }

            // ===== Joylash (bosish yoki drag-and-drop uchun umumiy) =====
            // Kartani joyidan olib tashlash — modal tugmasi va panelga sudrash uchun umumiy.
            // Shablon ko'rinishida barcha haftalardan; hafta tanlangan bo'lsa faqat shu haftada.
            // Qatlam almashtirilganda yo'qolmasin: joylashuv o'z qatlamining maydoniga ham yoziladi.
            function rememberLayerPlace(card, d, p) {
                if (weeklyLayer === 'external') { card.ext_day = d; card.ext_pair = p; }
                else if (card._int) { card._int.day = d; card._int.pair = p; }
            }
            function weeklyEditGuard() {
                if (weeklyCanEdit()) return;
                throw new Error(weeklyLayer === 'external'
                    ? 'Tashqi dars jadvalini o\'quv bo\'limi tahrirlaydi. Ichki jadvalga o\'ting.'
                    : 'Ichki dars jadvalini kafedra mudiri tahrirlaydi.');
            }
            async function unplaceOneCard(card) {
                weeklyEditGuard();
                if (!curWeek || weeklyLayer === 'external') {
                    await api(BASE + '/cards/' + card.id + '/place', 'POST', { layer: weeklyLayer });
                    card.day = null; card.pair = null;
                    rememberLayerPlace(card, null, null);
                } else {
                    const key = card.id + '|' + curWeek;
                    const ov = overrides[key];
                    // Hafta-hafta (shablonsiz) ichki jadval: shablonda joyi yo'q kartani panelga qaytarish —
                    // shu haftadagi joylashuvni olib tashlash (reset), "bu haftada o'tilmaydi" (cancel) emas.
                    if (ov && !ov.cancelled && !card.day) {
                        await api(BASE + '/cards/' + card.id + '/week-override', 'POST', { week: curWeek, action: 'reset' });
                        delete overrides[key];
                        return;
                    }
                    await api(BASE + '/cards/' + card.id + '/week-override', 'POST', { week: curWeek, action: 'cancel' });
                    overrides[key] = {
                        day: null, pair: null, cancelled: true,
                        auditorium_code: null, auditorium_name: null, auditorium_volume: null,
                    };
                }
            }
            async function placeOneCard(card, d, p) {
                weeklyEditGuard();
                if (!curWeek || weeklyLayer === 'external') {
                    await api(BASE + '/cards/' + card.id + '/place', 'POST', { day: d, pair: p, layer: weeklyLayer });
                    card.day = d; card.pair = p;
                    rememberLayerPlace(card, d, p);
                } else {
                    await api(BASE + '/cards/' + card.id + '/week-override', 'POST',
                        { week: curWeek, action: 'move', day: d, pair: p });
                    const key = card.id + '|' + curWeek;
                    overrides[key] = { ...(overrides[key] || {}), day: d, pair: p, cancelled: false };
                }
            }
            async function placeCardAt(card, d, p) {
                try { await placeOneCard(card, d, p); selected = null; renderAll(); }
                catch (e) { alert('Konflikt: ' + e.message); }
            }
            // Birlashtirilgan (ketma-ket) blok — kartalarni ketma-ket paralarga joylaymiz
            // parallel — bo'lakli fan bo'laklari: hammasi bitta katakka (ketma-ket emas).
            async function placeBlockAt(ids, d, p, parallel, offs) {
                // Bo'laklar o'z siljishini saqlab ko'chadi ((a) 12:40 da bo'lsa, yangi joyda ham yarim para keyin)
                // Ketma-ket blok: har karta oldingilarining uzunligi (yarim-slot) qadar keyin turadi
                // (2 soat + 1 soat → p va p+2; avval k qo'shilardi va kartalar ustma-ust tushardi).
                const seqOff = [];
                ids.reduce((acc, id, k) => { seqOff[k] = acc; const x = cards.find(c => c.id === id); return acc + (x ? cardLen(x) : 1); }, 0);
                const at = k => p + (parallel ? ((offs && offs[k]) || 0) : seqOff[k]);
                // Bo'laklar birga ko'chadi: birortasi sig'masa (o'qituvchi/xona band) — hech biri
                // ko'chirilmaydi, aks holda so'rovlar birma-bir ketib, guruh yarim ko'chgan holda qolardi.
                if (parallel) {
                    for (const id of ids) {
                        const card = cards.find(x => x.id === id);
                        const errs = card ? conflictsAt(card, d, at(ids.indexOf(id)), ids) : [];
                        if (errs.length) { alert('Konflikt (' + card.subject_name + '): ' + errs.join('; ')); return; }
                    }
                }
                try {
                    const moves = ids.map((id, k) => ({ card: cards.find(x => x.id === id), pair: at(k) }))
                        .filter(move => move.card);
                    {
                        // Pastga surilganda oxirgi qism avval ko'chadi: oldingi qismga joy bo'shatadi
                        // (aks holda blok o'z eski joyi bilan "Guruh band" konfliktiga tushadi).
                        const later = moves.every(move => {
                            const pl = effPlace(move.card);
                            return pl && +pl.day === d && move.pair >= +pl.pair;
                        });
                        moves.sort((a, b) => later ? b.pair - a.pair : a.pair - b.pair);
                    }
                    for (const move of moves) await placeOneCard(move.card, d, move.pair);
                    selected = null; renderAll();
                } catch (e) { alert('Konflikt: ' + e.message); }
            }
            // ===== Drag-and-drop holati (blok = bir yoki bir necha ketma-ket karta) =====
            let dragCardIds = null;
            let dragParallel = false;
            let dragOffsets = null;   // bo'laklar sarlavhasi: har bo'lakning katak boshidan siljishi (yarim-slot)
            function startDrag(ids, ev, parallel, offs) {
                if (TIMETABLE_READ_ONLY) { if (ev) ev.preventDefault(); return; }
                dragCardIds = Array.isArray(ids) ? ids.slice() : [ids];
                dragParallel = !!parallel;
                dragOffsets = Array.isArray(offs) ? offs.slice() : null;
                // Bo'lak katagi ichidagi yarim-slot chiziqlari tushish nishoniga aylanadi
                setTimeout(() => { const g = document.getElementById('grid'); if (g && dragCardIds) g.classList.add('tt-dragging'); }, 0);
                if (ev && ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    try { ev.dataTransfer.setData('text/plain', dragCardIds.join(',')); } catch (e) { /* ba'zi brauzerlar */ }
                }
            }
            // Sudrash tugagach holatni tozalash (tashlanmagan bo'lsa ham)
            document.addEventListener('dragend', () => {
                dragCardIds = null;
                dragParallel = false;
                dragOffsets = null;
                const gEl = document.getElementById('grid');
                if (gEl) gEl.classList.remove('tt-dragging');
                document.querySelectorAll('.drag-ok, .drag-bad').forEach(el => el.classList.remove('drag-ok', 'drag-bad'));
                const panel = document.getElementById('cardPanel');
                if (panel) panel.classList.remove('pn-drop');
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
            function renderAll() { resetCompactCache(); try { buildSubjectColors(colorScopeCards()); } catch (e) { } buildGroupRows(); renderPanel(); renderGrid(); renderStats(); updateCheckBadge(); syncUrl(); markPendingChanges(); renderWeeklyLayerUi(); }

            function renderStats() {
                // Shu haftada o'tilmaydigan kartalar hisobga olinmaydi — aks holda
                // ular "joylashmagan" bo'lib ko'rinib qolardi.
                const sc = weekActiveCards();
                const placed = sc.filter(c => effPlace(c)).length;
                // Sikl (4-6 kurs) kartalari haftalik panjaraga (kun/para) emas, sikl kalendariga
                // joylanadi — haftalik hisobga qo'shilsa, doim "joylanmagan" bo'lib ko'rinadi.
                const boardCards = cards.filter(c => !weekCancelled(c) && !isCycleCardAny(c));
                const totPlaced = boardCards.filter(c => effPlace(c)).length;
                const typeLbl = { all: '', lecture: ' · faqat ma\'ruza', practice: ' · faqat amaliy' }[typeFilter];
                const weekLbl = curWeek ? ' · ' + curWeek + '-hafta' : '';
                const cyc = (viewMode === 'cycle' && cyclePlanData && Array.isArray(cyclePlanData.cycle_cards)) ? cyclePlanData.cycle_cards : null;
                const first = cyc
                    ? '<span class="rounded-md px-2 py-1 bg-green-50 text-green-700" title="Tanlangan qamrovdagi sikl bloklari (fan × oqim/guruh × mashg\'ulot turi)">Sikl bloklari: <b>' +
                        cyc.filter(c => c.placed).length + '/' + cyc.length + '</b></span>'
                    : '<span class="rounded-md px-2 py-1 bg-green-50 text-green-700">Joylashgan: <b>' + placed + '/' + sc.length + '</b>' + typeLbl + weekLbl + '</span>';
                $('statChips').innerHTML = first +
                    '<span class="rounded-md px-2 py-1 bg-gray-100 text-gray-600" title="Haftalik jadval kartalari (sikl fanlari bundan tashqari — ular sikl kalendarida)">Doska bo\'yicha (haftalik): <b>' + totPlaced + '/' + boardCards.length + '</b></span>';
                $('unplacedCount').textContent = (sc.length - placed) + ' ta';
                $('unplacedExportBtn').classList.toggle('hidden', !sc.some(c => !effPlace(c)));
                $('weekHint').classList.toggle('hidden', !curWeek);
            }

            // Joylanmagan kartalar diagnostikasi (unplaced-export) — panel tugmasi va HEMIS solishtirish ogohlantirishi uchun.
            async function downloadUnplacedXlsx(params) {
                params.set('_', Date.now());
                const response = await fetch(BASE + '/boards/' + board.id + '/unplaced-export?' + params.toString(), {
                    headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                    cache: 'no-store',
                });
                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.error || 'HTTP ' + response.status);
                }
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'joylanmagan-kartalar-diagnostikasi-' + board.id + '.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }
            async function downloadUnplacedDiagnostics() {
                if (!board) return;
                const btn = $('unplacedExportBtn');
                const old = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Tayyorlanmoqda...';
                try {
                    const params = new URLSearchParams();
                    [...selectedFaculties].forEach(v => params.append('faculty_names[]', v));
                    [...selectedDirs].forEach(v => params.append('specialty_names[]', v));
                    [...selectedCourses].forEach(v => params.append('courses[]', v));
                    params.set('type', typeFilter);
                    params.set('layer', weeklyLayer);
                    if (curWeek) params.set('week', curWeek);
                    await downloadUnplacedXlsx(params);
                } catch (e) {
                    alert('Diagnostika Excelini yuklab bo\'lmadi: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = old;
                }
            }

            $('unplacedExportBtn').onclick = downloadUnplacedDiagnostics;
            // Butun doska bo'yicha joylanmagan kartalar hisoboti — tanlangan fakultetga qaramasdan, joriy qatlam (tashqi/ichki) bilan.
            if ($('unplacedReportBtn')) $('unplacedReportBtn').onclick = async function () {
                if (!board) return;
                const btn = this, old = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Tayyorlanmoqda…';
                try {
                    const params = new URLSearchParams({ layer: weeklyLayer, _: Date.now() });
                    const response = await fetch(BASE + '/boards/' + board.id + '/unplaced-report?' + params.toString(), {
                        headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.error || error.message || 'HTTP ' + response.status);
                    }
                    const blob = await response.blob();
                    const name = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') || '');
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = name ? name[1] : 'joylanmagan_kartalar_butun_doska.xlsx';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(a.href), 5000);
                } catch (e) {
                    alert('Butun doska hisobotini yuklab bo\'lmadi: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = old;
                }
            };

            // Bo'lakli fan ("Tibbiyot kasbiga kirish. Terapiya (a)"): qisqa nomda asosiy qism
            // (oxirgi nuqtadan keyingisi — "Terapiya") va bo'lak harfi doim ko'rinadi,
            // aks holda uzun umumiy boshlanma tufayli (a) yoki (c) ekani bilinmay qolardi.
            function cardLabel(c, short) {
                const t = c.training_type === 'lecture' ? 'M' : 'A';
                const bm = BOLAK_RX.exec(String(c.subject_name || '').trim());
                if (bm) {
                    let baseName = bm[1].trim();
                    if (short) {
                        const parts = baseName.split(/\.\s+/).filter(Boolean);
                        if (parts.length > 1) baseName = parts[parts.length - 1];
                        if (baseName.length > 20) baseName = baseName.slice(0, 20) + '…';
                    }
                    return '<b>[' + t + ']</b> ' + esc(baseName) + ' <b class="tt-bolak-tag">(' + esc(bm[2]) + ')</b>';
                }
                const name = short && c.subject_name.length > 26 ? c.subject_name.slice(0, 26) + '…' : c.subject_name;
                return '<b>[' + t + ']</b> ' + esc(name);
            }
            // Karta ustiga kursor olib borilganda chiqadigan to'liq ma'lumot (sikldagi kabi).
            function cardTooltip(c, extra) {
                const bm = BOLAK_RX.exec(String(c.subject_name || '').trim());
                return [
                    c.subject_name + ' — ' + (c.training_type === 'lecture' ? "ma'ruza" : 'amaliy'),
                    bm ? '(' + bm[2] + ") bo'lak — guruh talabalarining bir qismi, boshqa bo'laklar bilan parallel" : '',
                    (c.oqim_label ? c.oqim_label + ' · ' : '') + 'Guruh: ' + cardGroups(c).join(', ') + (c.students ? ' · ' + c.students + ' talaba' : ''),
                    "O'qituvchi: " + (c.teacher_name || 'biriktirilmagan'),
                    'Xona: ' + (c.auditorium_name ? c.auditorium_name + (c.auditorium_volume ? " (sig'im " + c.auditorium_volume + ')' : '') : 'biriktirilmagan'),
                    c.kafedra_name ? 'Kafedra: ' + c.kafedra_name : '',
                    extra || '',
                ].filter(Boolean).join('\n');
            }

            // Joylashmagan kartalar — pastda gorizontal panel (aSc uslubida): tekis
            // ravishda ketma-ket chiqadi (fan bo'yicha saralangan, guruhlash chizig'i yo'q).
            // Hafta ko'rinishida shu haftada o'tilmaydigan kartalar bu yerga tushmaydi.
            function renderPanel() {
                $('pnFilters').classList.toggle('hidden', viewMode === 'cycle');
                if (viewMode === 'cycle') {
                    renderCycleCards(cyclePlanData);
                    return;
                }
                $('cycleGroupFilter').classList.add('hidden');
                $('subjectColorsBtn').classList.add('hidden');
                $('cardPanelTitle').textContent = 'Joylashmagan kartalar';
                $('cardPanelHint').classList.add('hidden');
                const bySubject = (a, b) => a.subject_name.localeCompare(b.subject_name, 'uz');
                const un = weekActiveCards().filter(c => !effPlace(c)).sort(bySubject);
                // Shu haftada o'tilmaydigan kartalar — odatda yashirin. Ular joylashgan,
                // shunchaki bu haftaga tegishli emas; qo'lda bekor qilinganini qaytarish
                // uchun "ko'rsatish" tugmasi orqali chiqariladi.
                const skipped = curWeek ? visibleSpecCards().filter(weekCancelled).sort(bySubject) : [];
                const skipBtn = $('skipToggle');
                skipBtn.classList.toggle('hidden', !skipped.length);
                skipBtn.textContent = (showSkipped ? 'Yashirish' : 'Ko\'rsatish') +
                    ': bu haftada o\'tilmaydi (' + skipped.length + ' ta)';

                // Filtr variantlari — paneldagi barcha kartalardan
                const pool = showSkipped ? un.concat(skipped) : un;
                const uzSort = (a, b) => a.localeCompare(b, 'uz', { numeric: true });
                fillPnSelect('pnSubject', 'Barcha fanlar', [...new Set(pool.map(c => c.subject_name))].sort(uzSort), 'subject');
                fillPnSelect('pnOqim', 'Barcha oqimlar', [...new Set(pool.map(c => c.oqim_label || '').filter(Boolean))].sort(uzSort), 'oqim');
                // Oqim tanlangan bo'lsa — guruh ro'yxatida faqat shu oqim guruhlari
                const groupPool = pnFilter.oqim ? pool.filter(c => (c.oqim_label || '') === pnFilter.oqim) : pool;
                fillPnGroups([...new Set(groupPool.flatMap(cardGroups))].sort(uzSort));
                $('pnType').value = pnFilter.type;
                const filtering = !!(pnFilter.subject || pnFilter.oqim || pnFilter.groups.size || pnFilter.type);
                const unShown = un.filter(pnMatch);
                const skippedShown = skipped.filter(pnMatch);
                $('pnReset').classList.toggle('hidden', !filtering);
                $('pnFilterCount').classList.toggle('hidden', !filtering);
                $('pnFilterCount').textContent = 'Filtrda: ' + unShown.length + ' / ' + un.length + ' ta';

                const panelCard = (c, skip) =>
                    '<div class="pn-card ' + (c.training_type === 'lecture' ? 'lec' : 'prc') + (skip ? ' skip' : '') + (selected && selected.id === c.id ? ' sel' : '') +
                    ' lang-' + (c.lang || 'uz') + '" draggable="true" style="' + subjStyle(c) + 'border-left-width:3px;" data-id="' + c.id + '" title="' + esc(c.subject_name + (skip ? ' · bu haftada o\'tilmaydi' : '')) + '">' +
                    cardLabel(c, true) +
                    '<div class="text-[9px] text-gray-500">' +
                    (c.training_type === 'lecture'
                        ? esc(c.oqim_label || 'oqim') + ' · ' + (c.group_names || []).length + ' guruh · ' + c.students + ' t.'
                        : esc(c.group_name || '') + ' · ' + c.students + ' t.') +
                    (c.teacher_name ? ' · <i class="bi bi-person-check" aria-hidden="true"></i>' : '') + (c.auditorium_name ? ' · <i class="bi bi-door-open" aria-hidden="true"></i>' : '') +
                    '</div></div>';

                $('cardPanel').innerHTML =
                    unShown.map(c => panelCard(c, false)).join('') +
                    (showSkipped ? skippedShown.map(c => panelCard(c, true)).join('') : '')
                    || (filtering && un.length
                        ? '<div class="text-xs text-gray-400 p-1">Filtrga mos karta yo\'q.</div>'
                        : '<div class="text-xs text-gray-400 p-1">Hammasi joylashgan 🎉</div>');

                document.querySelectorAll('.pn-card').forEach(el => {
                    el.onclick = () => {
                        const c = cards.find(x => x.id === +el.dataset.id);
                        if (TIMETABLE_ROLE === 'oqituvchi') { openTeacherTimeProposal(c); return; }
                        selected = (selected && selected.id === c.id) ? null : c;
                        renderPanel(); renderGrid();
                    };
                    el.addEventListener('dragstart', ev => startDrag(+el.dataset.id, ev));
                });

                // Joylashgan kartani panelga sudrab tashlash — joyidan olib tashlanadi.
                // Paneldagi (joylashmagan) kartani yana panelga tashlash hech narsa qilmaydi.
                // Sikl rejimi panelga o'z hodisalarini qo'yadi; bu yerda ularni almashtiramiz.
                const panel = $('cardPanel');
                const placedDragged = () => (dragCardIds || [])
                    .map(id => cards.find(x => x.id === id))
                    .filter(c => c && effPlace(c));
                panel.ondragover = ev => {
                    if (!placedDragged().length) return;
                    ev.preventDefault();
                    ev.dataTransfer.dropEffect = 'move';
                    panel.classList.add('pn-drop');
                };
                panel.ondragleave = ev => {
                    if (!panel.contains(ev.relatedTarget)) panel.classList.remove('pn-drop');
                };
                panel.ondrop = async ev => {
                    ev.preventDefault();
                    panel.classList.remove('pn-drop');
                    const toUnplace = placedDragged();
                    dragCardIds = null;
                    if (!toUnplace.length) return;
                    try {
                        for (const c of toUnplace) await unplaceOneCard(c);
                    } catch (e) {
                        alert('Xatolik: ' + e.message);
                    }
                    selected = null;
                    renderAll();
                };
            }

            // Jadval kesimi (faqat ko'rish): o'qituvchi / auditoriya / fan ustunlari
            function renderGridCross(mode) {
                const placed = visibleSpecCards().filter(c => effPlace(c)).map(effectiveCard);
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
                let h = '<thead><tr><th class="tt-corner px-1 py-1">Kun</th><th class="tt-corner px-1 py-1" colspan="2" style="left:26px">Para</th>';
                const pGroups = paraGroups(P, pairTime);
                const roomMeta = {};
                 if (mode === 'room') placed.forEach(card => {
                     const roomKey = keyOf(card);
                     if (!roomMeta[roomKey]) roomMeta[roomKey] = {
                         code: card.auditorium_code || '',
                         name: card.auditorium_name || roomKey,
                         volume: card.auditorium_volume
                     };
                 });
                 cols.forEach(c => {
                     if (mode !== 'room') {
                         h += '<th class="tt-grp px-2 py-1">' + esc(c) + '</th>';
                         return;
                     }
                     const room = roomMeta[c] || { code: '', name: c, volume: null };
                     const roomNumber = String(room.name || room.code || c).replace(/^№\s*/u, '');
                     const capacity = room.volume !== null && room.volume !== undefined && room.volume !== ''
                         ? ' <span class="tt-room-vol">(' + esc(String(room.volume)) + ' ta)</span>' : '';
                     h += '<th class="tt-grp tt-room-head px-2 py-1">№ ' + esc(roomNumber) + capacity + '</th>';
                 });
                h += '</tr></thead><tbody>';
                for (let d = 1; d <= D; d++) {
                    for (let p = 1; p <= P; p++) {
                        h += '<tr>';
                        if (p === 1) h += '<td class="tt-day" rowspan="' + P + '">' + esc(dayNames[d - 1] || ('Kun ' + d)) + '</td>';
                        h += paraSideCells(p, P, pairTime, pGroups, rowEndCls);
                        cols.forEach(col => {
                            const key = col + '|' + d + '|' + p;
                            if (consumed[key]) return;
                            const list = startIdx[key] || [];
                            if (!list.length) { h += '<td class="tt-cell' + rowEndCls(p) + '" data-day="' + d + '" data-pair="' + p + '"></td>'; return; }
                            const vs = Math.max(...list.map(cardLen));
                            for (let k = 1; k < vs; k++) consumed[col + '|' + d + '|' + (p + k)] = 1;
                            const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                            const inner = list.map(c => {
                                const isLec = c.training_type === 'lecture';
                                const st = isLec ? 'background:#fde68a;' : ('background-color:' + subjColor(c.subject_name).bg + ';');
                                const detailLines = excelCardText(c).split('\n');
                                return '<div class="tt-chip ' + (isLec ? 'lec' : 'prc') + '" data-chip="' + c.id + '" style="' + st + '">' +
                                    '<div class="tt-cross-fac">' + esc(detailLines[0] || '') + '</div>' +
                                    '<div class="tt-cross-dir">' + esc(detailLines[1] || '') + '</div>' +
                                    '<div class="tt-cross-subject">' + esc(detailLines[2] || '') + '</div>' +
                                    '<div class="tt-cross-flow">' + esc(detailLines[3] || '') + '</div>' +
                                    '<div class="tt-cross-weeks">' + esc(detailLines[4] || '') + '</div>' +
                                    '</div>';
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

            // Para ustuni (sikl ichki jadvali kabi): qo'ng'iroq jadvalida ketma-ket bir xil
            // qisqartmali yarim paralar ("I", "I") bitta para katagiga birlashadi — chapda
            // katta rim raqami + "para", o'ngda har yarim qatorning vaqti.
            // Bo'lak katagidagi yarim-slot chiziqlari: qatorlar balandligi har xil (mazmunli qator
            // baland), shuning uchun chiziqlar foiz emas — katak qamragan haqiqiy qatorlar bo'yicha.
            function alignBolakDrops() {
                document.querySelectorAll('#grid td.tt-bolak-cell').forEach(td => {
                    const zones = td.querySelectorAll('.tt-bolak-drop');
                    if (!zones.length) return;
                    const top0 = td.getBoundingClientRect().top;
                    let tr = td.parentElement;
                    const tops = [];
                    for (let r = 0; r <= zones.length && tr; r++, tr = tr.nextElementSibling) tops.push(tr.getBoundingClientRect().top - top0);
                    const h = td.getBoundingClientRect().height;
                    zones.forEach((z, r) => {
                        if (tops[r] == null) return;
                        const t = Math.max(0, tops[r]), b = tops[r + 1] != null ? tops[r + 1] : h;
                        z.style.top = t + 'px';
                        z.style.height = Math.max(0, b - t) + 'px';
                    });
                });
            }
            // Qotirilgan sarlavha: har bir sarlavha qatori o'zidan yuqoridagi qatorlar balandligicha pastda
            // qotadi (aks holda hammasi top:0 da ustma-ust tushib, pastga aylantirganda sarlavha aralashib ketadi).
            function stickGridHead() {
                const grid = document.getElementById('grid');
                const head = grid && grid.tHead;
                if (!head) return;
                let top = 0;
                [...head.rows].forEach(tr => {
                    [...tr.cells].forEach(th => { th.style.top = top + 'px'; });
                    top += tr.getBoundingClientRect().height;
                });
            }
            window.addEventListener('resize', () => { try { alignBolakDrops(); stickGridHead(); } catch (e) { /* panjara yo'q */ } });
            function paraGroups(P, pairTime) {
                const key = p => { const t = pairTime[p]; const a = t ? String(t.abbr || '').trim() : ''; return a && a !== '—' ? a : null; };
                const out = {};
                for (let p = 1; p <= P; p++) {
                    const k = key(p);
                    let n = 1;
                    while (k && p + n <= P && key(p + n) === k) n++;
                    out[p] = { span: n, label: k || (pairTime[p] ? (pairTime[p].name || p) : p) };
                    for (let q = 1; q < n; q++) out[p + q] = { span: 0 };
                    p += n - 1;
                }
                return out;
            }
            function paraSideCells(p, P, pairTime, groups, endCls) {
                const g = groups[p], pt = pairTime[p];
                let h = '';
                if (g && g.span) {
                    const last = p + g.span - 1;
                    h += '<td class="tt-para tt-para-roman' + endCls(last) + '"' + (g.span > 1 ? ' rowspan="' + g.span + '"' : '') +
                        ' title="' + esc(pt ? (String(pt.name || '').replace(/\s*\(\d\)\s*$/, '') + ' ' + (pt.start || '') + '–' + ((pairTime[last] || pt).end || '')) : '') + '">' +
                        '<b>' + esc(g.label) + '</b><i>para</i></td>';
                }
                h += '<td class="tt-ptime' + endCls(p) + '">' + (pt && (pt.start || pt.end) ? esc(pt.start) + '<br>' + esc(pt.end) : '') + '</td>';
                return h;
            }
            function renderGrid() {
                $('grid').classList.toggle('tt-cross-details', viewMode !== 'group' && viewMode !== 'cycle');
                // Sikl ko'rinishi — alohida kalendar (sana × guruh)
                const cycleView = viewMode === 'cycle';
                $('gridWrap').classList.toggle('hidden', cycleView);
                $('cycleArea').classList.toggle('hidden', !cycleView);
                if (cycleView) { loadCyclePlan(); return; }
                if (viewMode !== 'group') { renderGridCross(viewMode); return; }
                // Bu tanlovda haftalik fan qolmagan bo'lsa-yu, sikl fanlari bo'lsa —
                // "fanlar yo'qolmadi", ular sikl kalendarida. Yo'naltiruvchi xabar.
                if (!groupRows.length) {
                    const hasCycleHere = cards.some(c => selectedFaculties.has(c.faculty_name || '') &&
                        selectedDirs.has(c.specialty_name) && selectedCourses.has(c.course) && isCycleCardAny(c));
                    if (hasCycleHere) {
                        $('grid').innerHTML = '<tbody><tr><td class="p-6 text-sm text-gray-600 text-center">' +
                            'Bu tanlovdagi fanlar <b>sikl (blok)</b> rejimida belgilangan — ular haftalik panjarada emas, ' +
                            '<b>sikl kalendarida</b> ketma-ket kunli blok bo\'lib turadi.<br>' +
                            'Yuqoridagi <b>Kesim</b> ro\'yxatidan <b>“Sikl (4-6 kurs)”</b> ni tanlang.' +
                            '</td></tr></tbody>';
                        activeCell = null;
                        return;
                    }
                }
                const g = curGrid();
                let D = g.days;
                // Yarim-slot (qator) soni — doska qo'ng'iroq jadvalidagi "pair" elementlar
                // soni (bir "pair" = bir yarim-slot). Yo'nalish bo'yicha faqat kun soni farq
                // qilishi mumkin, para soni butun doska uchun bir xil.
                let P = boardSchedule().filter(it => it.type === 'pair').length || g.pairs_per_day;
                // Ko'rinayotgan yo'nalish/kurslarning kun soni har xil bo'lishi mumkin;
                // eng kattasini olamiz (barcha kunlar sig'sin).
                [...new Set(specCards().map(c => c.specialty_name + '|' + c.course))].forEach(k => {
                    const gg = grids[k]; if (gg) { D = Math.max(D, gg.days); }
                });
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
                    // Ustunlar guruh KALITI bo'yicha (nom takrorlanishi mumkin)
                    curO.groups.push(gr.key);
                });
                // Kalit → ko'rinadigan guruh nomi (sarlavha va asos-guruh chegarasi uchun)
                const gName = {};
                groupRows.forEach(gr => { gName[gr.key] = gr.group; });

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
                // Bir katakni bo'lishadigan darslar: hech qachon bir haftaga
                // tushmaydigan kartalar (toq/juft haftalardagi ma'ruzalar,
                // ma'ruza va uni almashtiruvchi amaliy) bitta slotda tura oladi.
                // placedIdx katakka bittasini saqlaydi — qolganlari yo'qolmasligi
                // uchun ular alohida ro'yxatda yig'iladi va shu katakda birga
                // chiziladi. Hafta ko'rinishida ro'yxatda faqat bittasi qoladi.
                const cellStack = {};
                visibleSpecCards().forEach(c => {
                    const pl = effPlace(c);
                    if (!pl) return;
                    const ec = effectiveCard(c);
                    const len = cardLen(ec);
                    cardGroups(ec).forEach(gg => {
                        const base = gkey(ec, gg) + '|' + pl.day;
                        (cellStack[base + '|' + pl.pair] = cellStack[base + '|' + pl.pair] || []).push(ec);
                        for (let k = 0; k < len; k++) { const key = base + '|' + (pl.pair + k); if (!placedIdx[key] || (!curWeek && ec.training_type === 'lecture')) placedIdx[key] = ec; }
                    });
                });
                // Shu katakda boshlanadigan, lekin asosiy chipda ko'rsatilmagan darslar
                const stackExtras = (grp, d, p, ids) => {
                    const list = cellStack[grp + '|' + d + '|' + p];
                    if (!list || list.length < 2) return [];
                    const shown = new Set(ids && ids.length ? ids : []);
                    return list.filter(x => !shown.has(x.id));
                };

                // Vertikal qamrov: karta o'zining cardLen(c) yarim-slotini to'ldiradi; qo'shimcha
                // ravishda ketma-ket bir xil fan kartalari (masalan "para qo'shish" bilan)
                // bitta katakka (rowspan) birlashtiriladi. vConsumed — yuqoridagi rowspan
                // qamragan (chiqarilmaydigan) kataklar. vChain — jami yarim-slot uzunligi
                // va tarkibidagi karta id'lari (badge uchun).
                const vConsumed = {};
                // Shablon ko'rinishida faqat bir xil haftalarda o'tiladigan kartalar birlashtiriladi:
                // har hafta o'tiladigan (15 hafta) va almashib keladigan (masalan 9 hafta) qism
                // alohida chiziladi — ikkinchisi yo'l-yo'l (⇄) bo'lib ajralib tursin.
                // Kalit — o'tiladigan haftalarning TO'LIQ ro'yxati (activeWeeksOf ko'rinish uchun 10 tadan keyin qisqartiradi:
                // 12 va 11 haftalik qismlar bir xil chiqib, birlashib ketardi).
                const weeksKey = x => {
                    if (curWeek) return '';
                    const out = [];
                    for (let w = 1, total = Math.max(1, +curGrid().weeks || 0); w <= total; w++) {
                        const ov = overrides[x.id + '|' + w];
                        if (!(ov && ov.cancelled)) out.push(w);
                    }
                    return out.join(',');
                };
                const vChain = (grp, d, p, c) => {
                    const ids = [];
                    let span = 0, cur = p;
                    const wk = weeksKey(c);
                    while (true) {
                        const n = placedIdx[grp + '|' + d + '|' + cur];
                        if (!n || n.training_type !== c.training_type || n.subject_name !== c.subject_name) break;
                        if (weeksKey(n) !== wk) break;
                        ids.push(n.id);
                        span += cardLen(n);
                        cur += cardLen(n);
                    }
                    return { span: span || cardLen(c), ids: ids.length ? ids : [c.id] };
                };

                // shade — bitta katakda bir necha almashinuvchi karta bo'lsa, ularni
                // biroz och/to'q qilib farqlash uchun (0 = ochroq, 1 = to'qroq).
                const chipHtml = (c, ids, shade) => {
                    const merged = ids && ids.length > 1;
                    const mids = merged ? ' data-merge-ids="' + ids.join(',') + '"' : '';
                    // Birlashgan blokning umumiy davomiyligi — kartalar soni emas, soat (yarim-slot = 1 soat):
                    // 2 soat + 1 soat = "3 soat", 2 + 2 = "4 soat".
                    const mergedHours = merged ? ids.reduce((sum, id) => {
                        const x = cards.find(k => k.id === id);
                        return sum + (x ? cardLen(x) : 0);
                    }, 0) : 0;
                    const badge = merged ? '<span class="tt-merge-badge" title="' + ids.length + ' ta karta">' + mergedHours + ' soat</span>' : '';
                    // Karta necha hafta o'tiladi: "(N hafta)". Semestrning HAR haftasida
                    // o'tiladigan dars uchun buni yozish shart emas — 1 soatmi, 3 soatmi,
                    // farqi yo'q. "Har hafta"ni karta haftalar sonidan emas, hafta
                    // istisnolaridan aniqlaymiz: bitta ham bekor qilingan hafta bo'lmasa,
                    // dars butun semestr davomida o'tiladi.
                    const fullWeeks = curGrid().weeks;
                    const skipsWeeks = (activeWeeksOf(c, fullWeeks).length > 0)
                        || (c.weeks && c.weeks < fullWeeks);
                    const showWks = !!(c.weeks && skipsWeeks);
                    // Qaysi haftalarda o'tilishi — hafta istisnolaridan (cancelled emas)
                    const wkList = showWks ? activeWeeksOf(c, fullWeeks) : [];
                    const wkNums = wkList.length ? ': ' + wkList.join(',') : '';
                    const wks = showWks
                        ? ' <span class="tt-weeks">(' + c.weeks + ' hafta' + wkNums + ')</span>' : '';
                    // Auditoriya raqami (kod) — sig'imi bilan; tooltipда to'liq nomi
                    const roomNo = String(c.auditorium_name || c.auditorium_code || '').replace(/^№\s*/u, '');
                    const roomTxt = roomNo
                        ? '<div class="tt-room">№ ' + esc(roomNo) + '</div>'
                        : '';
                    // Almashinuvchi karta — har hafta emas, faqat ayrim haftalarda o'tiladi
                    // (ma'ruza va uni almashtiruvchi amaliy). Shablon ("Barcha haftalar")
                    // ko'rinishida yo'l-yo'l fon + ⇄ belgisi bilan ajratib turadi, chunki
                    // u yerda barcha variantlar ustma-ust ko'rinadi.
                    const alt = !curWeek && showWks;
                    const altMark = alt ? '<span class="tt-alt-mark" title="Har hafta emas — almashib keladi">⇄</span>' : '';
                    const tri = shade === 0 ? ' tt-shade-a tt-tri-a' : shade === 1 ? ' tt-shade-b tt-tri-b' : '';
                    const head = '<div class="tt-chip ' + (c.training_type === 'lecture' ? 'lec' : 'prc') +
                        (alt ? ' tt-alt' : '') + tri +
                        (selected && selected.id === c.id ? ' sel' : '') + '" style="' + subjStyle(c) +
                        '" data-chip="' + c.id + '"' + mids + ' title="' +
                        esc(cardTooltip(c, (showWks ? 'Haftalar: ' + c.weeks + ' hafta' + wkNums : '')
                            + (alt ? ' · har hafta emas (almashib keladi)' : ''))) + '">';
                    // Uchburchakka bo'lingan katakda fan nomi, qaysi haftalarda
                    // o'tilishi va xona raqami ko'rinadi. O'qituvchi tooltipda
                    // qoladi — uchburchakka sig'may, yozuvni buzadi.
                    if (tri) {
                        return head + '<span class="tt-tri-name">' + cardLabel(c, true) + wks + roomTxt + '</span></div>';
                    }
                    return head +
                        altMark + cardLabel(c, true) + wks + badge +
                        (c.teacher_name ? '<div class="text-[9px] text-gray-600">' + esc(c.teacher_name) + '</div>' : '') +
                        roomTxt +
                        '</div>';
                };

                // Asos guruh kaliti: til qo'shimchasi "(o'z)" va a/b pastki guruh harfini olib tashlab
                // (masalan "1K-01a (o'z)" → "1K-01"). Bir asos guruh = a va b pastki guruhlari.
                const baseKey = gn => String(gn).replace(/\s*\([^)]*\)\s*$/, '').replace(/[a-z]$/i, '');
                // Ustun chap chegara sinfi: oqim boshi (qo'sh chiziq) yoki asos guruh boshi (qalin chiziq)
                // groups — guruh KALITLARI; asos-guruh solishtiruvi ko'rinadigan nom bo'yicha
                const colBorder = (oqimIdx, gi, groups) => {
                    if (gi === 0) return oqimIdx > 0 ? ' sep-oqim' : '';
                    return baseKey(gName[groups[gi]] || '') !== baseKey(gName[groups[gi - 1]] || '') ? ' sep-base' : '';
                };

                // Fakultet sarlavhasi (Excel dars jadvali kabi): guruh → fakultet xaritasidan
                // qo'shni bir xil fakultet ustunlari bitta blokka birlashtiriladi.
                const facOf = {};
                groupRows.forEach(gr => { facOf[gr.key] = gr.faculty || ''; });
                const facRuns = [];
                oqimCols.forEach(o => o.groups.forEach(gr => {
                    const f = facOf[gr] || '';
                    const last = facRuns[facRuns.length - 1];
                    if (last && last.faculty === f) last.span++; else facRuns.push({ faculty: f, span: 1 });
                }));
                const showFac = facRuns.some(r => r.faculty);
                const corSpan = showFac ? 3 : 2;

                const courseCls = course => ' tt-course-' + (((+course || 1) - 1) % 6 + 1);

                // Sarlavha: Kun | Para | [fakultet] | oqim | guruhlar
                let h = '<thead><tr>' +
                    '<th class="tt-corner px-1 py-1" rowspan="' + corSpan + '" style="width:26px;min-width:26px;max-width:26px">Kun</th>' +
                    '<th class="tt-corner px-1 py-1" rowspan="' + corSpan + '" colspan="2" style="left:26px">Para</th>';
                if (showFac) {
                    facRuns.forEach((r, ri) => h += '<th class="tt-fac px-2 py-1' + (ri > 0 ? ' sep-oqim' : '') + '" colspan="' + r.span + '">' + esc(r.faculty || '—') + '</th>');
                    h += '</tr><tr>';
                }
                const multiCourse = selectedCourses.size > 1;
                const showSpec = multiSpec();
                const grpCount = (o, gr) => groupStudents[(o.faculty || '') + '|' + (gName[gr] || '')];
                oqimCols.forEach((o, oi) => {
                    const flowKey = (o.faculty || '') + '|' + (o.specialty || '') + '|' + o.course + '|' + (o.label || '');
                    const known = o.groups.map(gr => grpCount(o, gr)).filter(n => n != null);
                    // Faol talabalar (students) bo'yicha; ma'lumot bo'lmasa — kartalardagi son.
                    const studentCount = known.length ? known.reduce((a, b) => a + (+b || 0), 0) : flowStudents[flowKey];
                    const title = (showSpec && o.specialty ? o.specialty + ' · ' : '') +
                        (multiCourse ? o.course + '-kurs · ' : '') + (o.label || '—');
                    h += '<th class="tt-oqim px-2 py-1' + courseCls(o.course) + (oi > 0 ? ' sep-oqim' : '') + '" colspan="' + o.groups.length + '">' +
                        '<div>' + esc(title) + '</div>' +
                        (studentCount ? '<div class="tt-oqim-count">' + o.groups.length + ' guruh · ' + studentCount + ' talaba</div>' : '') +
                    '</th>';
                });
                h += '</tr><tr>';
                oqimCols.forEach((o, oi) => o.groups.forEach((gr, gi) => {
                    const n = grpCount(o, gr);
                    h += '<th class="tt-grp px-2 py-1' + courseCls(o.course) + colBorder(oi, gi, o.groups) + '">' + esc(gName[gr] || '') +
                        (n != null ? '<div class="tt-grp-count"><em class="tt-stu-count" title="' + n + ' ta faol talaba">' + n + '</em></div>' : '') + '</th>';
                }));
                h += '</tr></thead><tbody>';

                // Para ajratuvchi chiziq darajasi: shu qatorda TUGAYDIGAN katak uchun
                // (endP = boshlanish parasi + rowspan - 1). Kun oxiri (endP===P) — eng
                // qalin; juft endP — ikki butun para orasi (qalinroq); toq endP — bitta
                // butun paraning ikki yarmi orasi (sukut, yupqa — qo'shimcha sinf kerak emas).
                const rowEndCls = endP => endP === P ? ' tt-dayend' : (endP % 2 === 0 ? ' tt-paraend' : '');
                const pGroups = paraGroups(P, pairTime);
                // Bo'lakli fan amaliyoti ("Fan (a)/(b)/(c)") — bitta asos guruh (02a+02b) uchun bitta katak:
                // tepada fanning asosiy nomi, pastida bo'laklar ro'yxati (harf · guruhcha · talaba).
                // Har bo'lak alohida bosiladi/sudraladi; sarlavhani sudrasa — hamma bo'lak birga ko'chadi.
                const bolakBaseOf = x => { const m = BOLAK_RX.exec(String(x.subject_name || '').trim()); return m ? m[1].trim() : null; };
                const bolakLetterOf = x => { const m = BOLAK_RX.exec(String(x.subject_name || '').trim()); return m ? m[2] : ''; };
                const shortGroup = g => String(g).replace(/\s*\(.*\)\s*$/, '').split('-').pop();
                // Bo'lak kechroq boshlanishi mumkin (masalan (a) 12:40 da, (b)/(c) 12:00 da): katak
                // shu asos guruhdagi barcha bo'laklarni qamraydi (rowspan — eng oxirgi tugashgacha),
                // har bo'lak qatorida o'z vaqti yoziladi, katak ichida yarim-slot tushish chiziqlari bor.
                const bolakCell = (o, oi, gi, d, p) => {
                    const startList = (g, q) => (cellStack[g + '|' + d + '|' + q] || []).filter(x => x.training_type !== 'lecture');
                    const l0 = startList(o.groups[gi], p);
                    const B = l0.length ? bolakBaseOf(l0[0]) : null;
                    if (!B || !l0.every(x => bolakBaseOf(x) === B)) return null;
                    const isB = x => bolakBaseOf(x) === B;
                    const startOf = x => { const pl = effPlace(x); return pl ? +pl.pair : p; };
                    // Guruh ustunining [p, end) oralig'idagi bo'laklari; begona dars bo'lsa — null
                    const gather = (g, end, late) => {
                        const out = [];
                        for (let q = p; q < end; q++) {
                            if (q > p && vConsumed[g + '|' + d + '|' + q]) return null;
                            const l = startList(g, q);
                            const occ = placedIdx[g + '|' + d + '|' + q];
                            if (occ && occ.training_type !== 'lecture' && !isB(occ)) return null;
                            if (occ && occ.training_type === 'lecture') return null;
                            if (q > p && !late) { if (l.length) return null; continue; }
                            if (!l.every(isB)) return null;
                            out.push(...l);
                        }
                        return out;
                    };
                    const build = late => {
                        let end = p + Math.max(...l0.map(cardLen));
                        if (!late && !l0.every(x => cardLen(x) === end - p)) return null;
                        let span = 1, guard = 0;
                        while (guard++ < 8) {
                            let items = [];
                            for (let k = 0; k < span; k++) {
                                const l = gather(o.groups[gi + k], end, late);
                                if (!l) return null;
                                items.push(...l);
                            }
                            // Qo'shni ustunlar (shu asos guruh) — ularda ham shu fan bo'laklari bo'lsa
                            const bg = baseKey(gName[o.groups[gi]] || '');
                            while (gi + span < o.groups.length) {
                                const g2 = o.groups[gi + span];
                                if (baseKey(gName[g2] || '') !== bg || vConsumed[g2 + '|' + d + '|' + p]) break;
                                const l2 = gather(g2, end, late);
                                if (!l2 || !l2.length) break;
                                if (!late && !l2.every(x => startOf(x) === p && cardLen(x) === end - p)) break;
                                items.push(...l2);
                                span++;
                            }
                            const map = new Map(items.map(x => [x.id, x]));
                            items = [...map.values()];
                            let newEnd = Math.max(end, ...items.map(x => startOf(x) + cardLen(x)));
                            // Bo'lakning davomi (masalan 2 soat + 1 soat) aynan katak tugagan joydan boshlansa — qo'shamiz
                            if (late && newEnd === end) {
                                for (let k = 0; k < span; k++) {
                                    const nx = startList(o.groups[gi + k], end);
                                    if (nx.length && nx.every(y => isB(y) && items.some(x => x.subject_name === y.subject_name
                                        && cardGroups(x).join(',') === cardGroups(y).join(',') && startOf(x) + cardLen(x) === end))) {
                                        newEnd = Math.max(newEnd, ...nx.map(y => end + cardLen(y)));
                                    }
                                }
                            }
                            if (newEnd === end) return { span, end, items };
                            if (!late) return null;
                            end = newEnd;
                        }
                        return null;
                    };
                    const res = build(true) || build(false);
                    if (!res) return null;
                    const { span, end } = res;
                    const items = res.items.sort((a, b) => startOf(a) - startOf(b) || bolakLetterOf(a).localeCompare(bolakLetterOf(b)) || a.id - b.id);
                    const vs = end - p;
                    for (let r = 1; r < vs; r++) for (let k = 0; k < span; k++) vConsumed[o.groups[gi + k] + '|' + d + '|' + (p + r)] = 1;
                    const first = items[0];
                    const parts = B.split(/\.\s+/).filter(Boolean);
                    const shortB = parts.length > 1 ? parts[parts.length - 1] : B;
                    const ids = items.map(x => x.id).join(',');
                    const offs = items.map(x => startOf(x) - p).join(',');
                    const tStart = q => (pairTime[q] && pairTime[q].start) || '';
                    const tEnd = q => (pairTime[q] && pairTime[q].end) || '';
                    // Bitta bo'lakning ketma-ket qismlari (masalan 2 soat + 1 soat) — bitta qator:
                    // { x: birinchi karta, ids, s: boshlanish, e: tugash (yarim-slot, istisno) }
                    const chains = [];
                    items.forEach(x => {
                        const sx = startOf(x), gk = cardGroups(x).join(',');
                        // O'qituvchi/xona farq qilsa, ikkinchi qismning rekvizitlari yashirinmasin.
                        const prev = chains.find(ch => ch.x.subject_name === x.subject_name && ch.gk === gk && ch.e === sx
                            && (ch.x.teacher_id || null) === (x.teacher_id || null)
                            && (ch.x.auditorium_code || null) === (x.auditorium_code || null));
                        if (prev) { prev.ids.push(x.id); prev.e = sx + cardLen(x); }
                        else chains.push({ x, gk, ids: [x.id], s: sx, e: sx + cardLen(x) });
                    });
                    chains.sort((a, b) => a.s - b.s || bolakLetterOf(a.x).localeCompare(bolakLetterOf(b.x)) || a.x.id - b.x.id);
                    // Vaqt — bo'laklar boshlanishi/tugashi har xil bo'lsa har qatorda ko'rsatiladi
                    const varied = chains.some(ch => ch.s !== p || ch.e !== end);
                    // Katak paradan oshib ketsa (masalan 3 soat: II para + III ning yarmi) — umumiy vaqt sarlavhada
                    const paraOf = q => { let a = q; while (a > 1 && pGroups[a] && pGroups[a].span === 0) a--; return a; };
                    const headTime = !varied && paraOf(end - 1) !== paraOf(p) ? ' <span class="tt-bolak-time">' + esc(tStart(p) + '–' + tEnd(end - 1)) + '</span>' : '';
                    const head = '<div class="tt-bolak-head" data-chip="' + first.id + '" data-parallel-ids="' + ids + '" data-parallel-offs="' + offs + '" title="' +
                        esc(B + ' — amaliy, ' + items.length + " ta bo'lak parallel (sarlavhani sudrasangiz hammasi birga ko'chadi; bitta bo'lakni katak ichidagi chiziqqa sudrab boshlanish vaqtini o'zgartiring)") + '"><b>[A]</b> ' + esc(shortB) + headTime + '</div>';
                    const rows = chains.map(ch => {
                        const x = ch.x, sx = ch.s, late = sx > p;
                        const tm = tStart(sx) + '–' + tEnd(ch.e - 1);
                        // Qismlar birga, o'z siljishi bilan ko'chadi (2 soat + 1 soat ketma-ket qoladi)
                        const mids = ch.ids.length > 1 ? ' data-parallel-ids="' + ch.ids.join(',') + '" data-parallel-offs="' +
                            ch.ids.map(id => startOf(items.find(y => y.id === id)) - ch.s).join(',') + '"' : '';
                        return '<div class="tt-bolak-item' + (late ? ' tt-bolak-late' : '') + (selected && ch.ids.includes(selected.id) ? ' sel' : '') + '" data-chip="' + x.id + '"' + mids + ' title="' + esc(cardTooltip(x, 'Vaqt: ' + tm + (ch.ids.length > 1 ? ' (' + ch.ids.length + ' qism)' : ''))) + '">' +
                            '<b class="tt-bolak-tag">' + esc(bolakLetterOf(x)) + '</b> ' + esc(cardGroups(x).map(shortGroup).join('+')) +
                            (varied ? '<span class="tt-bolak-time">' + (late ? '⏵ ' : '') + esc(tm) + '</span>' : '') +
                            '<span class="tt-bolak-n">' + (x.students || '') + '</span>' +
                            (x.teacher_name ? '<span class="tt-bolak-t">' + esc(String(x.teacher_name).split(' ')[0]) + '</span>' : '') + '</div>';
                    }).join('');
                    // Katak ichidagi yarim-slot tushish chiziqlari (12:00 / 12:40 ...)
                    let drops = '';
                    if (vs > 1) {
                        drops = '<div class="tt-bolak-drops">';
                        // Joyi render'dan keyin haqiqiy qator balandliklari bo'yicha qo'yiladi (alignBolakDrops)
                        for (let r = 0; r < vs; r++) drops += '<div class="tt-bolak-drop" data-drop-day="' + d + '" data-drop-pair="' + (p + r) + '" data-r="' + r + '" style="top:' + (r * 100 / vs) + '%;height:' + (100 / vs) + '%"><i>' + esc(tStart(p + r)) + '</i></div>';
                        drops += '</div>';
                    }
                    const cs = span > 1 ? ' colspan="' + span + '"' : '';
                    const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                    return { span, html: '<td class="tt-cell tt-bolak-cell' + courseCls(o.course) + colBorder(oi, gi, o.groups) + rowEndCls(p + vs - 1) + '"' + cs + rs +
                        ' data-day="' + d + '" data-pair="' + p + '" style="' + subjStyle(first) + '">' + drops + head + '<div class="tt-bolak-list">' + rows + '</div></td>' };
                };

                for (let d = 1; d <= D; d++) {
                    for (let p = 1; p <= P; p++) {
                        h += '<tr>';
                        if (p === 1) h += '<td class="tt-day" rowspan="' + P + '">' + esc(dayNames[d - 1] || ('Kun ' + d)) + '</td>';
                        h += paraSideCells(p, P, pairTime, pGroups, rowEndCls);
                        oqimCols.forEach((o, oi) => {
                            // Har katakka kun/para — drag-and-drop tashlash nishoni uchun
                            const dp = ' data-day="' + d + '" data-pair="' + p + '"';
                            // Tanlangan ma'ruza — butun oqimga bitta birlashtirilgan nishon (colspan).
                            // Ko'p tanlovda faqat o'z fakulteti+yo'nalishi oqimi yonadi.
                            if (selected && selected.training_type === 'lecture' && (selected.oqim_label || '') === o.label
                                && (selected.faculty_name || '') === o.faculty && (selected.specialty_name || '') === o.specialty
                                && selected.course === o.course) {
                                const occupied = o.groups.some(gr => placedIdx[gr + '|' + d + '|' + p]);
                                if (!occupied) {
                                    const bad = conflictsAt(selected, d, p).length > 0;
                                    h += '<td class="tt-cell ' + (bad ? 'tt-bad' : 'tt-ok') + courseCls(o.course) + colBorder(oi, 0, o.groups) + rowEndCls(p) + '" colspan="' + o.groups.length + '"' + dp +
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
                                let c = placedIdx[grp + '|' + d + '|' + p];
                                // placedIdx karta egallagan barcha yarim-slotlarni saqlaydi.
                                // Kartani faqat haqiqiy boshlanish qatorida chizamiz; aks holda
                                // bloklangan birinchi slot sabab ikkinchi slot yangi darsdek chiqadi.
                                if (c) {
                                    const cPlace = effPlace(c);
                                    if (!cPlace || +cPlace.pair !== p) c = null;
                                }
                                if (c && c.training_type !== 'lecture') {
                                    const bc = bolakCell(o, oi, gi, d, p);
                                    if (bc) { h += bc.html; gi += bc.span; continue; }
                                }
                                if (c && c.training_type === 'lecture') {
                                    // Gorizontal birlashma: bir xil karta yoki AYNAN SHU fanning
                                    // ma'ruzasi bo'lsa — bitta katak. Bir oqimdagi guruhlar
                                    // (fakultetlararo qo'shilganlari ham) alohida kartada
                                    // bo'lishi mumkin, lekin dars bitta — bo'linib ko'rinmasin.
                                    let span = 1;
                                    while (gi + span < o.groups.length) {
                                        const c2 = placedIdx[o.groups[gi + span] + '|' + d + '|' + p];
                                        const c2Place = c2 ? effPlace(c2) : null;
                                        if (c2 && c2Place && +c2Place.pair === p && (c2.id === c.id ||
                                            (c2.training_type === 'lecture' && c2.subject_name === c.subject_name))) span++;
                                        else break;
                                    }
                                    // Vertikal: karta o'z uzunligini (yarim-slotlar) egallaydi;
                                    // ketma-ket bir xil fan ma'ruzalari ham birlashtiriladi.
                                    const chain = vChain(o.groups[gi], d, p, c);
                                    const vs = chain.span, ids = chain.ids;
                                    for (let k = 1; k < vs; k++)
                                        for (let gg = gi; gg < gi + span; gg++)
                                            vConsumed[o.groups[gg] + '|' + d + '|' + (p + k)] = 1;
                                    const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                                    // Ma'ruza katagi bir necha guruh ustunini qamrab oladi
                                    // (colspan), amaliy esa bitta guruhniki — shuning uchun
                                    // ma'ruza katagida faqat MA'RUZA kartalari chiziladi.
                                    const lecEx = stackExtras(o.groups[gi], d, p, ids)
                                        .filter(x => x.training_type === 'lecture');
                                    // Bir katakda bir necha almashinuvchi fan — diagonal ajratgich
                                    const lecSplit = lecEx.length ? ' tt-split' : '';
                                    const lecExtras = lecEx.map((x, xi) => chipHtml(x, [x.id], xi + 1)).join('');
                                    h += '<td class="tt-cell tt-lec' + lecSplit + courseCls(o.course) + bord + rowEndCls(p + vs - 1) + '" colspan="' + span + '"' + rs + dp + ' style="' + subjStyle(c) + '">' + chipHtml(c, ids, lecEx.length ? 0 : undefined) + lecExtras + '</td>';
                                    gi += span;
                                } else if (c) {
                                    // Vertikal: karta o'z uzunligini (yarim-slotlar) egallaydi;
                                    // ketma-ket bir xil fan amaliyotlari ham bitta katakka birlashtiriladi.
                                    let span = 1;
                                    while (gi + span < o.groups.length) {
                                        const c2 = placedIdx[o.groups[gi + span] + '|' + d + '|' + p];
                                        const c2Place = c2 ? effPlace(c2) : null;
                                        if (c2 && c2Place && +c2Place.pair === p && c2.id === c.id) span++;
                                        else break;
                                    }
                                    const chain = vChain(grp, d, p, c);
                                    const vs = chain.span, ids = chain.ids;
                                    for (let k = 1; k < vs; k++)
                                        for (let gg = gi; gg < gi + span; gg++)
                                            vConsumed[o.groups[gg] + '|' + d + '|' + (p + k)] = 1;
                                    const rs = vs > 1 ? ' rowspan="' + vs + '"' : '';
                                    const prcEx = stackExtras(grp, d, p, ids)
                                        .filter(x => x.training_type !== 'lecture');
                                    const prcSplit = prcEx.length ? ' tt-split' : '';
                                    const prcExtras = prcEx.map((x, xi) => chipHtml(x, [x.id], xi + 1)).join('');
                                    const cs = span > 1 ? ' colspan="' + span + '"' : '';
                                    h += '<td class="tt-cell' + prcSplit + courseCls(o.course) + bord + rowEndCls(p + vs - 1) + '"' + cs + rs + dp + ' style="' + subjStyle(c) + '">' + chipHtml(c, ids, prcEx.length ? 0 : undefined) + prcExtras + '</td>';
                                    gi += span;
                                } else {
                                    // Bo'sh katak — tanlangan amaliy uchun nishon bo'lishi mumkin
                                    let cls = 'tt-cell' + courseCls(o.course) + bord + rowEndCls(p), clickable = '';
                                    if (selected && selected.training_type === 'practice' && cardGKeys(selected).includes(grp)) {
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
                alignBolakDrops();
                stickGridHead();
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
                    if (TIMETABLE_ROLE === 'oqituvchi') { openTeacherTimeProposal(c); return; }
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
                        // Bo'laklar sarlavhasi — hamma bo'lak bitta katakka birga ko'chadi (data-parallel-ids).
                        const parallel = !!el.dataset.parallelIds;
                        const list = parallel ? el.dataset.parallelIds : el.dataset.mergeIds;
                        const ids = list ? list.split(',').map(Number) : [+el.dataset.chip];
                        const offs = parallel && el.dataset.parallelOffs ? el.dataset.parallelOffs.split(',').map(Number) : null;
                        startDrag(ids, ev, parallel, offs);
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
                        td.classList.add(card && conflictsAt(card, d, p, dragCardIds).length ? 'drag-bad' : 'drag-ok');
                    });
                    td.addEventListener('dragleave', () => td.classList.remove('drag-ok', 'drag-bad'));
                    td.addEventListener('drop', ev => {
                        ev.preventDefault();
                        td.classList.remove('drag-ok', 'drag-bad');
                        if (!dragCardIds) return;
                        const ids = dragCardIds, parallel = dragParallel, offs = dragOffsets;
                        const d = +td.dataset.day, p = +td.dataset.pair;
                        dragCardIds = null;
                        dragParallel = false;
                        dragOffsets = null;
                        placeBlockAt(ids, d, p, parallel, offs);
                    });
                });
                // Bo'lak katagi ichidagi yarim-slot chiziqlari: bitta bo'lakni 12:40 ga sudrash va h.k.
                document.querySelectorAll('#grid .tt-bolak-drop').forEach(z => {
                    z.addEventListener('dragover', ev => {
                        if (!dragCardIds) return;
                        ev.preventDefault(); ev.stopPropagation();
                        ev.dataTransfer.dropEffect = 'move';
                        const card = cards.find(x => x.id === dragCardIds[0]);
                        const d = +z.dataset.dropDay, p = +z.dataset.dropPair;
                        z.classList.add(card && conflictsAt(card, d, p, dragCardIds).length ? 'drag-bad' : 'drag-ok');
                    });
                    z.addEventListener('dragleave', () => z.classList.remove('drag-ok', 'drag-bad'));
                    z.addEventListener('drop', ev => {
                        ev.preventDefault(); ev.stopPropagation();
                        z.classList.remove('drag-ok', 'drag-bad');
                        if (!dragCardIds) return;
                        const ids = dragCardIds, parallel = dragParallel, offs = dragOffsets;
                        dragCardIds = null; dragParallel = false; dragOffsets = null;
                        placeBlockAt(ids, +z.dataset.dropDay, +z.dataset.dropPair, parallel, offs);
                    });
                });
            }

            // Teachers propose a time; neither the card nor the local grid is moved before approval.
            function openTeacherTimeProposal(c) {
                if (!c || +c.teacher_id !== +TIMETABLE_TEACHER_ID) {
                    alert("Faqat o'zingizga biriktirilgan dars vaqtini taklif qila olasiz."); return;
                }
                if (weeklyLayer !== 'internal' || !curWeek) {
                    alert("Taklif berish uchun ichki jadval va haftani tanlang."); return;
                }
                const prior = document.getElementById('teacherTimeProposal');
                if (prior) prior.remove();
                const m = document.createElement('div');
                m.id = 'teacherTimeProposal'; m.className = 'cr-proposal-overlay';
                const days = boardDayNames().slice(0, +board.days || 6);
                const slots = boardSchedule().filter(x => x.type !== 'break');
                const pl = effPlace(c);
                m.innerHTML = '<form class="cr-proposal-form"><h3 class="font-bold text-lg">Dars vaqtini o‘zgartirish taklifi</h3>' +
                    '<p>' + esc(c.subject_name) + ' · ' + curWeek + '-hafta</p>' +
                    '<p class="text-sm">Amaldagi vaqt: ' + esc(pl ? (days[pl.day - 1] + ' · ' + (slots[pl.pair - 1]?.start || pl.pair + '-katak')) : 'Joylashmagan') + '</p>' +
                    '<p class="text-sm">Avval kafedra mudiri, keyin registrator ofisi tasdiqlaydi. Shu vaqtgacha jadval o‘zgarmaydi.</p>' +
                    '<label>Yangi kun<select name="day" required>' + days.map((d, i) => '<option value="' + (i + 1) + '"' + (pl?.day === i + 1 ? ' selected' : '') + '>' + esc(d) + '</option>').join('') + '</select></label>' +
                    '<label>Yangi boshlanish vaqti<select name="pair" required>' + slots.map((s, i) => '<option value="' + (i + 1) + '"' + (pl?.pair === i + 1 ? ' selected' : '') + '>' + esc(s.start || (i + 1) + '-katak') + '</option>').join('') + '</select></label>' +
                    '<label>O‘zgarish sababi<textarea name="reason" required maxlength="1000" rows="3"></textarea></label>' +
                    '<p role="alert" class="text-red-600"></p><button type="button" data-recheck>Vaqtni qayta tekshirish</button><div class="flex justify-end gap-3"><button type="button" data-close>Bekor</button>' +
                    '<button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded">Taklif yuborish</button></div></form>';
                document.body.appendChild(m);
                m.querySelector('[data-close]').onclick = () => m.remove();
                m.onclick = ev => { if (ev.target === m) m.remove(); };
                const proposalWeek = curWeek;
                const proposalForm = m.querySelector('form');
                let checkSequence = 0;
                const checkTime = async () => {
                    const sequence = ++checkSequence, button = proposalForm.querySelector('[type="submit"]');
                    const status = proposalForm.querySelector('[role="alert"]');
                    button.disabled = true; status.textContent = 'Vaqt va bronlar tekshirilmoqda…';
                    try {
                        const result = await api(BASE + '/cards/' + c.id + '/time-proposal/check', 'POST', {
                            week: proposalWeek, day: proposalForm.elements.day.value, pair: proposalForm.elements.pair.value,
                        });
                        if (sequence !== checkSequence || !m.isConnected) return;
                        status.className = 'text-green-700'; status.textContent = result.message;
                        button.disabled = false;
                    } catch (e) {
                        if (sequence !== checkSequence || !m.isConnected) return;
                        status.className = 'text-red-600'; status.textContent = e.message;
                    }
                };
                m.querySelector('[data-recheck]').onclick = checkTime;
                proposalForm.elements.day.onchange = checkTime;
                proposalForm.elements.pair.onchange = checkTime;
                checkTime();
                m.querySelector('form').onsubmit = async ev => {
                    ev.preventDefault();
                    const form = ev.currentTarget, button = form.querySelector('[type="submit"]');
                    button.disabled = true;
                    try {
                        await api(BASE + '/cards/' + c.id + '/time-proposal', 'POST', {
                            week: proposalWeek, day: form.elements.day.value, pair: form.elements.pair.value,
                            reason: form.elements.reason.value,
                        });
                        m.remove();
                        await loadChangeRequests();
                    } catch (e) { form.querySelector('[role="alert"]').className = 'text-red-600'; form.querySelector('[role="alert"]').textContent = e.message; button.disabled = false; }
                };
            }

            // ===== Kartochka modali (o'qituvchi/auditoriya) =====
            async function openModal(c) {
                if (TIMETABLE_ROLE === 'oqituvchi') { openTeacherTimeProposal(c); return; }
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
                fillCmStartSlot(c);
                updateCmTimeHint();
                $('cmMsg').classList.add('hidden');
                $('cardModal').classList.remove('hidden');
                await Promise.all([loadTeachers(''), loadAuds()]);
            }
            // Modal: boshlanish vaqti — dars turgan para ichidagi yarim-slotlar (masalan 12:00 / 12:40).
            // Tanlangan (hafta yoki shablon) joylashuv bo'yicha; o'zgarsa karta shu yarim-slotga ko'chadi.
            function fillCmStartSlot(c) {
                const sel = $('cmStartSlot');
                const pl = effPlace(c);
                if (!pl) { sel.innerHTML = '<option value="">— joylashtirilmagan —</option>'; sel.disabled = true; return; }
                const sched = boardSchedule().filter(it => it.type === 'pair');
                const pt = {};
                sched.forEach((it, i) => { pt[i + 1] = { name: it.name || '', abbr: it.abbr || '', start: it.start || '', end: it.end || '' }; });
                const P = sched.length || board.pairs_per_day || 12;
                const groups = paraGroups(P, pt);
                let a = +pl.pair;
                while (a > 1 && groups[a] && groups[a].span === 0) a--;
                const n = (groups[a] && groups[a].span) || 1;
                let o = '';
                for (let q = a; q < a + n; q++) {
                    o += '<option value="' + q + '"' + (q === +pl.pair ? ' selected' : '') + '>' +
                        esc((pt[q] && pt[q].start) || ('slot ' + q)) + (q === a ? ' — para boshi' : ' — para ichidan') + '</option>';
                }
                sel.innerHTML = o;
                sel.disabled = n < 2;
            }
            // Modal: tanlangan uzunlik/boshlanish bo'yicha dars vaqti oralig'ini ko'rsatish
            function updateCmTimeHint() {
                const el = $('cmTimeHint');
                if (!modalCard || !effPlace(modalCard)) { el.textContent = 'Joylashtirilmagan — uzunlik saqlanadi.'; return; }
                const sched = boardSchedule().filter(it => it.type === 'pair');
                const len = +$('cmLen').value;
                // Bu modelda `pair` — yarim-slot indeksi; har qo'ng'iroq elementi bitta
                // yarim-slot (o'z start/end vaqti bilan). Dars pair'dan boshlab len ta
                // yarim-slotni egallaydi.
                const pl = effPlace(modalCard) || modalCard;
                const sp = +$('cmStartSlot').value || +pl.pair;
                const startEntry = sched[sp - 1];
                const endEntry = sched[sp - 1 + len - 1] || startEntry;
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
            $('cmLen').onchange = $('cmStartHalf').onchange = $('cmStartSlot').onchange = updateCmTimeHint;
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
                    // Boshlanish vaqti o'zgargan bo'lsa — shu kunda yangi yarim-slotga ko'chirish
                    const pl = effPlace(modalCard);
                    const slot = +$('cmStartSlot').value;
                    if (pl && slot && slot !== +pl.pair) {
                        const errs = conflictsAt(modalCard, +pl.day, slot);
                        if (errs.length) throw new Error('Konflikt: ' + errs.join('; '));
                        await placeOneCard(modalCard, +pl.day, slot);
                    }
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
                    await unplaceOneCard(modalCard);
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
            const ICON_BASE = @json(asset('image'));
            const iconImage = file => '<img class="tt-custom-icon" src="' + ICON_BASE + '/' + file + '" alt="" aria-hidden="true">';
            const managerIcons = {
                subjects: iconImage('06_subjects_book.png'),
                groups: iconImage('07_classes.png'),
                auditoriums: iconImage('08_classrooms.png'),
                teachers: iconImage('09_teachers_cap.png'),
            };

            let ascType = null;       // joriy dialog turi
            let ascData = [];         // dialog ma'lumotlari (xom)
            let ascSelId = null;      // tanlangan qator id/kalit
            let ascColumnFilters = {};
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
                if (TIMETABLE_CAN_USE_MANAGER && type !== 'subjects') return;

                const modalIsOpen = !$('ascModal').classList.contains('hidden');
                if (modalIsOpen && ascType === type) return;

                ascType = type;
                ascSelId = null;
                ascColumnFilters = {};
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
                    const haystack = ascType === 'subjects'
                        ? JSON.stringify({
                            ...r,
                            semester_display: subjectSeasonSemesterLabel(r, subjectSeasonOf(r)),
                            season_display: subjectSeasonLabel(subjectSeasonOf(r)),
                        }).toLowerCase()
                        : JSON.stringify(r).toLowerCase();
                    if (q && !haystack.includes(q)) return false;
                    if (ascType !== 'subjects') return true;

                    const f = ascColumnFilters;
                    const mode = (subjectSettings[subjModeKey(r.specialty_name, r.course, r.subject_name)] || { mode: 'normal' }).mode || 'normal';
                    const values = {
                        subject: r.subject_name,
                        course: r.specialty_name + ' · ' + r.course + '-kurs',
                        semester: subjectSeasonSemesterLabel(r, subjectSeasonOf(r)),
                        department: r.kafedra_name || '',
                        lecture: r.lecture,
                        practice: (+r.practice || 0) + (+r.laboratory || 0) + (+r.seminar || 0),
                        week_load: (r.week_plan ? r.week_plan.per_week_hours : ''),
                        mode: SUBJ_MODE_LABELS[mode] || mode,
                    };
                    return Object.entries(f).every(([key, value]) => {
                        if (!value) return true;
                        return String(values[key] ?? '').toLowerCase().includes(String(value).toLowerCase());
                    });
                });
            }

            // Soatni chiroyli ko'rsatish: 6 → "6", 4.5 → "4,5"
            const hrs = v => {
                const n = +v || 0;
                return (Number.isInteger(n) ? String(n) : n.toFixed(1).replace('.', ',')).replace(/,0$/, '');
            };
            // "Hafta yuki" ustuni — jami soat / semestr haftalari
            function weekLoadHtml(r) {
                const w = r.week_plan;
                if (!w || !w.total_hours) return '<span class="text-slate-400">—</span>';
                if ((subjectSettingOf(r).mode || 'normal') === 'cycle') {
                    return '<b>6</b> s<div class="text-[10px] text-slate-400">' + hrs(w.total_hours) + ' s jami</div>';
                }
                return '<b>' + hrs(w.per_week_hours) + '</b> s'
                    + '<div class="text-[10px] text-slate-400">' + hrs(w.total_hours) + ' s jami</div>';
            }
            // "Haftalik taqsimot" ustuni — ma'ruzali / ma'ruzasiz haftalar
            function weekSplitHtml(r) {
                const w = r.week_plan;
                if (!w || !w.total_hours) return '<span class="text-slate-400">—</span>';
                if ((subjectSettingOf(r).mode || 'normal') === 'cycle') {
                    return '';
                }
                const parts = [];
                if (w.lecture_weeks > 0) {
                    parts.push('<div><b>' + w.lecture_weeks + '</b> hafta: 2 s ma\'ruza'
                        + (w.practice_in_lecture_week > 0 ? ' + ' + hrs(w.practice_in_lecture_week) + ' s amaliy' : '') + '</div>');
                }
                if (w.plain_weeks > 0 && w.practice_in_plain_week > 0) {
                    parts.push('<div><b>' + w.plain_weeks + '</b> hafta: ' + hrs(w.practice_in_plain_week) + ' s amaliy</div>');
                }
                if (w.extra_weeks > 0) {
                    parts.push('<div class="text-slate-500">+1 s qo\'shimcha: <b>' + w.extra_weeks + '</b> hafta</div>');
                }
                let warn = w.exact ? ''
                    : '<div class="text-[10px] text-amber-600" title="Reja soati bu hafta soniga aniq sig\'madi">⚠ reja soatiga sig\'madi</div>';
                // Haftalik chegarani buzmaslik uchun joylanmay qolgan amaliy soat
                if (w.practice_shortfall > 0) {
                    warn += '<div class="text-[10px] text-amber-600" title="Haftalik yuk chegarasidan oshmaslik uchun bu soat kartochkaga joylanmadi. Hafta sonini yoki reja soatini ko\'rib chiqing.">'
                        + '⚠ ' + hrs(w.practice_shortfall) + ' s amaliy joylanmadi</div>';
                }
                return '<div class="text-[11px] leading-tight">' + (parts.join('') || '—') + warn + '</div>';
            }

            function subjectFilterControl(key, placeholder, type = 'text') {
                const value = ascColumnFilters[key] || '';
                if (type === 'select') {
                    const optionValues = key === 'mode'
                        ? Object.entries(SUBJ_MODE_LABELS).map(([mode, label]) => [mode, label])
                        : [...new Set(ascData.map(r => r.course))]
                            .sort((a, b) => (+a || 0) - (+b || 0))
                            .map(course => [course, course + '-kurs']);
                    const options = optionValues
                        .map(([optionValue, label]) => '<option value="' + esc(optionValue) + '"' + (String(value) === String(optionValue) ? ' selected' : '') + '>' + esc(label) + '</option>').join('');
                    return '<select class="asc-column-filter" data-filter-key="' + key + '"><option value="">Barchasi</option>' + options + '</select>';
                }
                return '<input class="asc-column-filter" data-filter-key="' + key + '" type="' + type + '" value="' + esc(value) + '" placeholder="' + placeholder + '">';
            }

            function renderAscTable() {
                $('ascTable').classList.toggle('asc-auditorium-table', ascType === 'auditoriums');
                $('ascTable').classList.toggle('asc-subject-table', ascType === 'subjects');
                const rows = filteredAsc();
                $('ascCount').textContent = rows.length + ' ta';
                let h = '';
                if (ascType === 'subjects') {
                    h = '<thead><tr><th>Fan</th><th>Fakultet · yo\'nalish · kurs</th><th>Semestr</th><th>Kafedra</th><th>Ma\'ruza s.</th><th>Amaliy s.</th>' +
                        '<th title="Jami soat / semestr haftalari — haftada shundan oshmasligi kerak">Hafta yuki</th>' +
                        '<th title="Ma\'ruza 2 soatdan. Ma\'ruzali haftada amaliy shunga kamayadi, ma\'ruzasiz haftada to\'liq yuk amaliyga beriladi.">Haftalik taqsimot</th>' +
                        '<th>Fan rejimi</th></tr>' +
                        '<tr class="asc-column-filter-row">' +
                        '<th>' + subjectFilterControl('subject', 'Fan...') + '</th>' +
                        '<th>' + subjectFilterControl('course', 'Kurs', 'select') + '</th>' +
                        '<th>' + subjectFilterControl('semester', 'Semestr...') + '</th>' +
                        '<th>' + subjectFilterControl('department', 'Kafedra...') + '</th>' +
                        '<th>' + subjectFilterControl('lecture', 'Soat', 'number') + '</th>' +
                        '<th>' + subjectFilterControl('practice', 'Soat', 'number') + '</th>' +
                        '<th>' + subjectFilterControl('week_load', 'Soat', 'number') + '</th>' +
                        '<th></th>' +
                        '<th>' + subjectFilterControl('mode', 'Rejim...', 'select') + '</th>' +
                        '</tr></thead><tbody>';
                    let lastSpec = null;
                    rows.forEach((r, i) => {
                        const faculty = r.faculty_name || 'Fakultet ko\'rsatilmagan';
                        const sk = (r.faculty_name || '') + '·' + r.specialty_name + '·' + r.course;
                        if (sk !== lastSpec) {
                            h += '<tr class="asc-row-head"><td colspan="9"><div class="asc-subject-faculty">' + esc(faculty) + '</div><div class="asc-subject-specialty">' + esc(r.specialty_name) + ' · ' + r.course + '-kurs</div></td></tr>';
                            lastSpec = sk;
                        }
                        const setting = { mode: 'normal', ...subjectSettingOf(r), season: subjectSeasonOf(r) };
                        const modeOptions = Object.entries(SUBJ_MODE_LABELS).map(([value, label]) =>
                            '<option value="' + value + '"' + (setting.mode === value ? ' selected' : '') + '>' + label + '</option>').join('');
                        h += rowTag(i) + '<td>' + esc(r.subject_name) + '</td><td class="asc-subject-path"><div class="asc-subject-faculty">' + esc(faculty) + '</div><div class="asc-subject-specialty">' + esc(r.specialty_name) + ' · ' + r.course + '-kurs</div></td>' +
                            '<td><span class="asc-semester-pill asc-semester-' + esc(r.season || '') + '">' + esc(r.semester_label || (r.semester ? r.semester + '-semestr' : '—')) + '</span></td>' +
                            '<td>' + esc(r.kafedra_name || '—') + '</td><td>' + fmt(r.lecture) + '</td><td>' + fmt(r.practice + r.laboratory + r.seminar) +
                            '</td><td>' + weekLoadHtml(r) + '</td><td>' + weekSplitHtml(r) + '</td>' +
                            '<td class="asc-subj-mode-cell"><select class="asc-subj-mode">' + modeOptions + '</select><div class="asc-subj-params">' +
                            subjectModeParamsHtml(setting) + '</div><span class="asc-subj-status" aria-live="polite"></span></td></tr>';
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
                const applyColumnFilter = ev => {
                    if (!ev.target.classList.contains('asc-column-filter')) return;
                    const key = ev.target.dataset.filterKey;
                    const cursor = typeof ev.target.selectionStart === 'number' ? ev.target.selectionStart : null;
                    ascColumnFilters[key] = ev.target.value;
                    renderAscTable();
                    const next = $('ascTable').querySelector('[data-filter-key="' + key + '"]');
                    if (next) {
                        next.focus();
                        if (cursor !== null && typeof next.setSelectionRange === 'function') {
                            next.setSelectionRange(cursor, cursor);
                        }
                    }
                };
                $('ascTable').oninput = applyColumnFilter;
                $('ascTable').onchange = applyColumnFilter;
                document.querySelectorAll('#ascTable tbody tr[data-idx]').forEach(tr => {
                    if (ascType === 'subjects') {
                        const row = rows[+tr.dataset.idx];
                        const semesterCell = tr.querySelector('.asc-semester-pill')?.parentElement || null;
                        if (semesterCell && !tr.querySelector('.asc-subj-season')) {
                            semesterCell.classList.add('asc-subj-season-cell');
                            semesterCell.innerHTML = '<select class="asc-subj-season"></select>';
                            repaintSubjectSeason(tr, row, subjectSeasonOf(row));
                        }
                        const modeSelect = tr.querySelector('.asc-subj-mode');
                        const seasonSelect = tr.querySelector('.asc-subj-season');
                        const refreshParams = () => {
                            const current = subjectSettings[subjModeKey(row.specialty_name, row.course, row.subject_name)] || {};
                            tr.querySelector('.asc-subj-params').innerHTML = subjectModeParamsHtml({ ...current, mode: modeSelect.value });
                        };
                        modeSelect.onchange = async () => {
                            refreshParams();
                            await saveSubjectModeRow(tr, row);
                        };
                        if (seasonSelect) {
                            seasonSelect.onchange = () => saveSubjectModeRow(tr, row);
                            repaintSubjectSeason(tr, row, subjectSeasonOf(row));
                        }
                        tr.addEventListener('change', ev => {
                            if (ev.target.classList.contains('asc-subj-group') ||
                                ev.target.classList.contains('asc-subj-occ') ||
                                ev.target.classList.contains('asc-subj-cycle')) {
                                saveSubjectModeRow(tr, row);
                            }
                        });
                    }
                    tr.onclick = () => {
                        ascSelId = tr.dataset.id || tr.dataset.idx;
                        document.querySelectorAll('#ascTable tbody tr').forEach(x => x.classList.remove('sel'));
                        tr.classList.add('sel');
                        renderAscButtons();
                    };
                    tr.ondblclick = () => {
                        if (ascType !== 'auditoriums') return;
                        const row = ascData.find(x => String(x.id) === String(tr.dataset.id));
                        if (row) openAudEdit(row);
                    };
                });
                if (ascType === 'subjects') renderAscButtons();
            }
            const rowTag = (i, id) => '<tr data-idx="' + i + '"' + (id != null ? ' data-id="' + id + '"' : '') + '>';
            const fmt = v => { v = +v || 0; return Number.isInteger(v) ? v : v.toFixed(1); };

            const actionIcons = {
                plus: '01_new_document.png',
                edit: 'tahrirlash.png',
                trash: '11_delete.png',
                import: '02_open_folder.png',
                template: '05_print_preview.png',
                export: '04_print.png',
            };
            const actionIcon = name => '<span class="asc-action-icon" aria-hidden="true"><img class="tt-custom-icon" src="' + ICON_BASE + '/' + actionIcons[name] + '" alt="" aria-hidden="true"></span>';

            function renderAscButtons() {
                const b = $('ascButtons');
                if (ascType === 'subjects' && ascData.length) syncAutoCycleState();
                if (ascType === 'subjects' && ascData.length &&
                    autoCycleCandidates().some(item => (subjectSettingOf(item.row).mode || 'normal') === 'cycle')) {
                    autoCycleApplied = true;
                }
                const hasSel = ascSelId !== null;
                if (ascType === 'auditoriums') {
                    b.innerHTML =
                        '<button class="asc-btn primary asc-action-btn" id="aBtnNew">' + actionIcon('plus') + 'Yangi</button>' +
                        '<button class="asc-btn asc-action-btn" id="aBtnEdit"' + (hasSel ? '' : ' disabled') + '>' + actionIcon('edit') + 'Tahrirlash</button>' +
                        '<button class="asc-btn danger asc-action-btn" id="aBtnDel"' + (hasSel ? '' : ' disabled') + '>' + actionIcon('trash') + 'O\'chirish</button>' +
                        '<div class="my-1 border-t border-gray-300"></div>' +
                        '<button class="asc-btn asc-action-btn" id="aBtnImport">' + actionIcon('import') + 'Import (Excel)</button>' +
                        '<button class="asc-btn asc-action-btn" id="aBtnTemplate">' + actionIcon('template') + 'Namuna shabloni</button>';
                    $('aBtnNew').onclick = () => openAudEdit(null);
                    $('aBtnEdit').onclick = () => hasSel && openAudEdit(ascData.find(x => String(x.id) === String(ascSelId)));
                    $('aBtnDel').onclick = () => hasSel && deleteAud();
                    $('aBtnImport').onclick = () => $('audImportFile').click();
                    $('aBtnTemplate').onclick = downloadAudTemplate;
                } else if (ascType === 'subjects') {
                    // Fan rejimlari manba jadvalini o'zgartirmaydi, faqat doska
                    // uchun saqlanadigan Sikl sozlamalarini yaratadi.
                    b.innerHTML =
                        '<button class="asc-btn primary asc-action-btn" id="aBtnAutoCycle" title="4-6 kurs fanlarini jami soat / 6 bo‘yicha Sikl qilish">' +
                        '<i class="bi ' + (autoCycleApplied ? 'bi-arrow-counterclockwise' : 'bi-magic') + '" aria-hidden="true"></i> ' +
                        (autoCycleApplied ? 'Avvalgi holatiga qaytarish' : '4-6 kursni avtomatik Sikl') + '</button>' +
                        '<button class="asc-btn asc-action-btn" id="aBtnCsv" title="CSV ga eksport">' + actionIcon('export') + 'CSV ga eksport</button>';
                    $('aBtnAutoCycle').onclick = toggleAutoCycleSubjects;
                    $('aBtnCsv').onclick = exportAscCsv;
                } else {
                    // Faqat o'qish (manba HEMIS/o'quv reja) — eksport imkoniyati
                    b.innerHTML =
                        '<button class="asc-btn asc-action-btn" id="aBtnCsv" title="CSV ga eksport">' + actionIcon('export') + 'CSV ga eksport</button>';
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
                // Ichma-ich obyektlarni (mas. week_plan) alohida ustunlarga yoyamiz —
                // aks holda CSV da "[object Object]" bo'lib chiqadi.
                const flat = r => {
                    const o = {};
                    Object.entries(r).forEach(([k, v]) => {
                        if (v && typeof v === 'object' && !Array.isArray(v)) {
                            Object.entries(v).forEach(([k2, v2]) => { o[k + '_' + k2] = v2; });
                        } else { o[k] = v; }
                    });
                    return o;
                };
                const flatRows = rows.map(flat);
                const cols = [...new Set(flatRows.flatMap(r => Object.keys(r)))];
                const csv = [cols.join(',')].concat(flatRows.map(r =>
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

            // ── Rang yordamchisi (Excel eksporti uchun) ──
            const rgbToHex = c => {
                const m = String(c).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (!m) return null;
                return '#' + [1, 2, 3].map(i => (+m[i]).toString(16).padStart(2, '0')).join('');
            };
            // ── Panjarani ixcham katak ma'lumoti sifatida yig'ish ──────────────
            // Ilgari butun jadval inline-uslubli HTML bo'lib yuborilar, server esa
            // uni PhpSpreadsheet ning HTML o'quvchisi orqali o'qirdi. Katta doskada
            // (o'n minglab katak) bu juda sekin — so'rov timeout bo'lib, brauzer
            // jimgina HTML ni ".xls" nomi bilan saqlab qo'yardi. Excel esa aynan
            // shu sababli "fayl formati kengaytmaga mos emas" deb ogohlantirardi.
            // Endi faqat matn + rang + birlashtirish ma'lumoti yuboriladi va server
            // xlsx ni to'g'ridan-to'g'ri yozadi.
            function excelCardText(card) {
                if (!card) return '';

                const cardGrid = gridForCard(card);
                const totalWeeks = Math.max(1, +cardGrid.weeks || +(board && board.weeks) || +card.weeks || 15);
                const activeWeeks = [];
                let hasWeekExceptions = false;

                for (let week = 1; week <= totalWeeks; week++) {
                    const override = overrides[card.id + '|' + week];
                    if (override && override.cancelled) {
                        hasWeekExceptions = true;
                    } else {
                        activeWeeks.push(week);
                    }
                }

                let weeksText;
                if (hasWeekExceptions) {
                    weeksText = activeWeeks.length ? activeWeeks.join(', ') : '—';
                } else if (+card.weeks > 0 && +card.weeks < totalWeeks) {
                    weeksText = card.weeks + ' hafta';
                } else {
                    weeksText = '1–' + totalWeeks;
                }

                const lessonType = card.training_type === 'lecture' ? 'M' : 'A';
                const flow = card.oqim_label || card.group_name || '—';
                const ec = typeof effectiveCard === 'function' ? effectiveCard(card) : card;
                const room = String(ec.auditorium_name || ec.auditorium_code || '').trim();

                return [
                    card.faculty_name || '—',
                    (card.specialty_name || '—') + (card.course ? ' · ' + card.course + '-kurs' : ''),
                    '[' + lessonType + '] ' + (card.subject_name || '—'),
                    'Oqim: ' + flow,
                    card.teacher_name ? "O'qituvchi: " + card.teacher_name : null,
                    room ? 'Xona: ' + room : null,
                    '(Haftalar: ' + weeksText + ')',
                ].filter(Boolean).join('\n');
            }

            function gridExportCells() {
                const grid = document.getElementById('grid');
                if (!grid || !grid.querySelector('tbody tr')) return null;
                const rows = [];
                grid.querySelectorAll('tr').forEach(tr => {
                    const cells = [];
                    [...tr.children].forEach(el => {
                        if (el.tagName !== 'TD' && el.tagName !== 'TH') return;
                        // Chip (kartochka) bo'lsa rangni undan olamiz — katak oq bo'lsa ham
                        // Excelda dars rangi ekrandagidek chiqsin.
                        const chip = el.querySelector('.tt-chip');
                        const cs = getComputedStyle(chip || el);
                        const bgRaw = cs.backgroundColor;
                        const transparent = !bgRaw || bgRaw === 'transparent' || bgRaw === 'rgba(0, 0, 0, 0)';
                        const subjectChips = [...el.children]
                            .filter(child => child.classList && child.classList.contains('tt-chip'));
                        const subjectTexts = subjectChips.map(child => {
                            const card = cards.find(item => item.id === +child.dataset.chip);
                            return card ? excelCardText(card) : (child.innerText || '').replace(/\s+/g, ' ').trim();
                        }).filter(Boolean);
                        // Bo'lak katagi: sarlavha (fan) + har bo'lak qatori (harf, guruh, talaba, o'qituvchi, vaqt)
                        const bolakText = el.classList.contains('tt-bolak-cell')
                            ? [el.querySelector('.tt-bolak-head'), ...el.querySelectorAll('.tt-bolak-item')]
                                .filter(Boolean).map(x => {
                                    const card = x.classList.contains('tt-bolak-item') ? cards.find(item => item.id === +x.dataset.chip) : null;
                                    const base = (x.innerText || '').replace(/\s+/g, ' ').trim();
                                    const ec = card && typeof effectiveCard === 'function' ? effectiveCard(card) : card;
                                    const room = ec ? String(ec.auditorium_name || ec.auditorium_code || '').trim() : '';
                                    return (x.classList.contains('tt-bolak-item') ? '• ' : '') + base
                                        + (card && card.teacher_name && !base.includes(card.teacher_name) ? ' · ' + card.teacher_name : '')
                                        + (room ? ' · №' + room.replace(/^№\s*/u, '') : '');
                                }).join('\n')
                            : '';
                        const exportedText = bolakText || (subjectTexts.length
                            ? subjectTexts.join('\n────────────\n')
                            : (el.innerText || '').replace(/\s+/g, ' ').trim());
                        const cell = { t: exportedText };
                        if (el.colSpan > 1) cell.cs = el.colSpan;
                        if (el.rowSpan > 1) cell.rs = el.rowSpan;
                        if (!transparent) { const hex = rgbToHex(bgRaw); if (hex) cell.bg = hex; }
                        if (parseInt(cs.fontWeight, 10) >= 600) cell.b = 1;
                        cells.push(cell);
                    });
                    rows.push(cells);
                });
                // Chapda 3 ustun qotiriladi: kun, para (rim raqami), yarim para vaqti.
                return { rows, freeze_rows: grid.querySelectorAll('thead tr').length, freeze_cols: 3, col_widths: [null, null, 9] };
            }

            // Jadvalni HAQIQIY .xlsx fayl sifatida yuklab olish (serverda PhpSpreadsheet
            // orqali) — Excel "format kengaytmага mos emas" ogohlantirishi chiqmaydi.
            async function downloadExcelXls() {
                const cellData = gridExportCells();
                if (!cellData) { alert('Yuklab olish uchun panjara yo\'q.'); return; }
                const title = (board.institution_name ? board.institution_name + ' — ' : '') + (board.name || 'Dars jadvali') +
                    (curWeek ? ' · ' + curWeek + '-hafta' : '');
                const base = (board.name || 'dars-jadvali').replace(/[^\w\-]+/g, '_') +
                    (curWeek ? '_' + curWeek + '-hafta' : '');
                await sendExcelExport(cellData, title, base, $('excelViewBtn'));
            }
            // Katak ma'lumotini serverga yuborib, tayyor .xlsx ni yuklab oladi.
            async function sendExcelExport(cellData, title, base, btn) {
                const btnHtml = btn ? btn.innerHTML : null;
                if (btn) { btn.disabled = true; btn.textContent = 'Tayyorlanmoqda…'; }
                try {
                    const fd = new FormData();
                    fd.append('_token', CSRF);
                    fd.append('payload', JSON.stringify(cellData));
                    fd.append('title', title);
                    fd.append('filename', base);
                    const r = await fetch(BASE + '/boards/' + board.id + '/excel-export', {
                        method: 'POST', headers: { 'Accept': 'application/json' }, body: fd
                    });
                    if (!r.ok) {
                        let msg = 'HTTP ' + r.status;
                        try { const j = await r.json(); if (j && j.error) msg = j.error; } catch (_) { }
                        throw new Error(msg);
                    }
                    const blob = await r.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url; a.download = base + '.xlsx';
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 5000);
                } catch (e) {
                    // Jimgina ".xls" (aslida HTML) saqlamaymiz — Excel uni "format
                    // kengaytmaga mos emas" deb ogohlantiradi va sabab ko'rinmay qoladi.
                    alert('Excel faylini yaratib bo\'lmadi: ' + e.message +
                        '\n\nQamrovni kichraytirib (fakultet/kurs tanlab) qayta urinib ko\'ring.');
                } finally {
                    if (btn && btnHtml !== null) { btn.disabled = false; btn.innerHTML = btnHtml; }
                }
            }
            // ── Sikl jadvalini Excelga yuklash (tashqi yoki ichki — ekranda qaysi bo'lsa) ──
            // Bloklar ekranda ustma-ust (overlay) chizilgani uchun DOM emas,
            // yuklangan reja (cyclePlanData) dan katak ma'lumoti tuziladi.
            function cycleExportPayload(j) {
                const dates = j.dates || [];
                // Ekrandagi qatorlar bilan bir xil (guruhcha tanlansa — uning bo'lak qatorlari ham).
                const rows = cycleVisibleRows(j.rows || []);
                if (!rows.length || !dates.length) return null;
                const WD = ['', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'];
                const OFF_BG = '#e2e8f0', HOL_BG = '#fecaca';
                const offBg = d => d && d.holiday ? HOL_BG : (d && d.off ? OFF_BG : null);
                const vis = [];
                dates.forEach((d, i) => { if (!cycleColHidden(i)) vis.push(i); });
                const visCount = (a, b) => vis.filter(i => i >= a && i <= b).length;
                const blockText = (b, extra) => {
                    const parts = [b.subject + ' (' + b.days + ' kun)'];
                    if (extra) parts.push(...extra.filter(Boolean));
                    return parts.join('\n');
                };
                const groupLabel = row => (j.view || cycleViewMode) === 'group' || !(row.subgroups || []).length
                    ? row.group
                    : row.group + '\n' + row.subgroups.join(', ');
                const isGroupView = (j.view || cycleViewMode) === 'group';
                const out = [], heights = {};
                const push = (cells, h) => { if (h) heights[out.length] = h; out.push(cells); };

                if (j.mark_mode) {
                    // ── Ichki: har guruhcha — fan qatori + kunlik yarim-para qatorlari ──
                    const dayHours = Math.max(1, +j.day_hours || 6);
                    const bell = boardSchedule().filter(it => it.type === 'pair');
                    const tm = t => { const m = /^(\d{1,2}):(\d{2})$/.exec(t || ''); return m ? (+m[1]) * 60 + (+m[2]) : null; };
                    const hm = v => String(Math.floor(v / 60)).padStart(2, '0') + ':' + String(v % 60).padStart(2, '0');
                    // Para necha soatligi ekrandagidek — barcha paralar o'rtacha davomiyligidan
                    // (birinchi para 0.5-para bo'lishi mumkin, faqat unga qarash noto'g'ri).
                    const durs = bell.map(it => { const a = tm(it.start), e = tm(it.end); return a != null && e != null && e > a ? e - a : null; }).filter(v => v != null);
                    const hpp = (durs.length ? durs.reduce((x, y) => x + y, 0) / durs.length : 80) >= 60 ? 2 : 1;
                    push([{ t: isGroupView ? 'Guruh' : 'Oqim', b: 1, bg: '#dbe4f0' }, { t: 'Para', b: 1, bg: '#dbe4f0' }, { t: 'Vaqt', b: 1, bg: '#dbe4f0' }]
                        .concat(vis.map(i => ({ t: dates[i].d + '\n' + (WD[dates[i].dow] || ''), b: 1, bg: offBg(dates[i]) || '#dbe4f0' }))), 26);
                    rows.forEach(row => {
                        // Ustma-ust tushgan blok chizilmaydi (ekrandagidek).
                        const owner = new Array(dates.length).fill(null);
                        (row.blocks || []).forEach(b => {
                            if (b.from == null || b.to == null) return;
                            for (let c = b.from; c <= b.to && c < dates.length; c++) if (owner[c]) return;
                            for (let c = b.from; c <= b.to && c < dates.length; c++) owner[c] = b;
                        });
                        // Guruhcha bo'lak qatori orqali o'qiydigan sikl — ekrandagidek ishora (sanalar bo'sh bo'lsa).
                        cycleVariantRowsOf(j.rows || [], row).forEach(v => (v.blocks || []).forEach(b => {
                            if (b.from == null || b.to == null) return;
                            for (let c = b.from; c <= b.to && c < dates.length; c++) if (owner[c]) return;
                            const ghost = { ...b, ghost: true, ghost_letter: (/\((\p{L})\)\s*$/u.exec(v.variant || '') || [])[1] || '' };
                            for (let c = b.from; c <= b.to && c < dates.length; c++) owner[c] = ghost;
                        }));
                        // Fan qatori
                        const head = [{ t: groupLabel(row), rs: dayHours + 1, b: 1, al: 'l' }, { t: 'Fan', cs: 2, b: 1, bg: '#f1f5f9' }];
                        for (let v = 0; v < vis.length; v++) {
                            const b = owner[vis[v]];
                            if (!b) { head.push({ t: '', bg: offBg(dates[vis[v]]) }); continue; }
                            let n = 1;
                            while (v + n < vis.length && owner[vis[v + n]] === b) n++;
                            if (b.ghost) {
                                head.push({ t: String(b.subject).replace(/\s*\(\p{L}\)\s*$/u, '') + '\n(' + b.ghost_letter + ") bo'lakda · " + b.days + ' kun',
                                    cs: n, b: 1, bg: '#ede9fe', fc: '#6d28d9' });
                                v += n - 1;
                                continue;
                            }
                            const plan = Object.values(b.day_plan || {});
                            const sl = plan.reduce((a, d) => a + (+d.lec || 0), 0), sp = plan.reduce((a, d) => a + (+d.prc || 0), 0);
                            const req = [b.teacher_name, b.auditorium_name || b.auditorium_code].filter(Boolean).join(' · ');
                            head.push({
                                t: blockText(b, [req, 'M ' + sl + (b.lecture_hours != null ? '/' + b.lecture_hours : '') + ' · A ' + sp + (b.practice_hours != null ? '/' + b.practice_hours : '') + ' soat']),
                                cs: n, b: 1, bg: subjColor(b.subject).bg,
                            });
                            v += n - 1;
                        }
                        push(head, 40);
                        // Soat (yarim para) qatorlari
                        for (let hour = 1; hour <= dayHours; hour++) {
                            const pt = bell[Math.ceil(hour / hpp) - 1];
                            const part = (hour - 1) % hpp;
                            const cells = [];
                            if (part === 0) {
                                const abbr = pt ? (pt.abbr && pt.abbr !== '—' ? pt.abbr : (pt.name || Math.ceil(hour / hpp))) : Math.ceil(hour / hpp);
                                cells.push({ t: abbr + '\npara', rs: Math.min(hpp, dayHours - hour + 1), b: 1, bg: '#e8eef6' });
                            }
                            const a = pt ? tm(pt.start) : null, e = pt ? tm(pt.end) : null;
                            cells.push({ t: a != null && e != null ? hm(Math.round(a + (e - a) / hpp * part)) + '–' + hm(Math.round(a + (e - a) / hpp * (part + 1))) : '' });
                            vis.forEach(i => {
                                const d = dates[i], b = owner[i];
                                if (d.off) { cells.push({ t: '', bg: offBg(d) }); return; }
                                if (!b) { cells.push({ t: '' }); return; }
                                if (b.ghost) { cells.push({ t: '', bg: '#f5f3ff' }); return; }   // dars bo'lak qatorida
                                const dp = (b.day_plan || {})[i - b.from];
                                const st = dp ? (+dp.start || 1) : 0, l = dp ? (+dp.lec || 0) : 0, p = dp ? (+dp.prc || 0) : 0;
                                if (dp && hour >= st && hour < st + l) cells.push({ t: 'M', b: 1, bg: '#facc15', fc: '#713f12' });
                                else if (dp && hour >= st + l && hour < st + l + p) cells.push({ t: 'A', b: 1, bg: '#c9e8e5', fc: '#0f766e' });
                                else cells.push({ t: '', bg: subjColor(b.subject).bg });
                            });
                            push(cells, 13);
                        }
                    });
                    return {
                        rows: out, row_heights: heights, freeze_rows: 1, freeze_cols: 3, font_size: 8, landscape: 1,
                        sheet_title: 'Ichki jadval', col_widths: [22, 6, 10].concat(vis.map(() => 4.6)),
                    };
                }

                // ── Tashqi: har qator — oqim/guruh, bloklar sanalar bo'ylab birlashgan ──
                push([{ t: isGroupView ? 'Guruh' : 'Oqim', b: 1, bg: '#dbe4f0' }]
                    .concat(vis.map(i => ({ t: dates[i].d + '\n' + (WD[dates[i].dow] || ''), b: 1, bg: offBg(dates[i]) || '#dbe4f0' }))), 26);
                rows.forEach(row => {
                    // Kesishgan bloklar alohida qatorchaga (lane) tushadi — hech biri yo'qolmaydi.
                    const lanes = [];
                    // "Fan (a)/(b)/(c)" — bitta sikl bo'laklari: bir xil sanalarda bitta blok.
                    const vBase = n => String(n || '').replace(/\s*\(\p{L}\)\s*$/u, '').trim().toLowerCase();
                    const seenVar = new Set();
                    [...(row.blocks || [])].filter(b => b.from != null && b.to != null)
                        .filter(b => { const k = vBase(b.subject) + '|' + b.from + '|' + b.to; if (seenVar.has(k)) return false; seenVar.add(k); return true; })
                        .sort((x, y) => x.from - y.from || (+x.pair || 1) - (+y.pair || 1))
                        .forEach(b => {
                            const to = Math.min(b.to, dates.length - 1);
                            if (visCount(b.from, to) < 1) return;
                            let lane = lanes.find(l => l.every(o => o.to < b.from || o.from > to));
                            if (!lane) { lane = []; lanes.push(lane); }
                            lane.push({ from: b.from, to: to, b: b });
                        });
                    if (!lanes.length) lanes.push([]);
                    const labelLines = (groupLabel(row).match(/\n/g) || []).length + 1 + Math.floor(groupLabel(row).length / 40);
                    const laneH = Math.max(36, Math.ceil(labelLines * 11 / lanes.length) + 4);
                    lanes.forEach((lane, li) => {
                        const cells = li === 0 ? [{ t: groupLabel(row), rs: lanes.length, b: 1, al: 'l' }] : [];
                        const at = {};
                        lane.forEach(o => { at[vis.find(i => i >= o.from && i <= o.to)] = o; });
                        for (let v = 0; v < vis.length; v++) {
                            const o = at[vis[v]];
                            if (!o) { cells.push({ t: '', bg: offBg(dates[vis[v]]) }); continue; }
                            const n = visCount(o.from, o.to);
                            const b = o.b;
                            cells.push({
                                t: (j.simple ? '' : (b.type === 'lecture' ? '[M] ' : '[A] ')) + blockText(b, [b.hours ? b.hours + ' soat' : '']),
                                cs: n, b: 1, bg: subjColor(b.subject).bg,
                            });
                            v += n - 1;
                        }
                        push(cells, laneH);
                    });
                });
                return {
                    rows: out, row_heights: heights, freeze_rows: 1, freeze_cols: 1, font_size: 8, landscape: 1,
                    sheet_title: 'Tashqi jadval', col_widths: [26].concat(vis.map(() => 6.5)),
                };
            }
            async function downloadCycleExcel() {
                const j = cyclePlanData;
                const payload = j ? cycleExportPayload(j) : null;
                if (!payload) { alert('Yuklab olish uchun sikl jadvali yo\'q.'); return; }
                const internal = Boolean(j.mark_mode);
                const label = internal ? 'Ichki' : 'Tashqi';
                const start = j.start_date ? j.start_date.split('-').reverse().join('.') : '';
                const filterLabel = cycleRowFilter ? (cycleRowLabels(j.rows || [])[cycleRowFilter] || '') : '';
                const title = (board.institution_name ? board.institution_name + ' — ' : '') + (board.name || 'Dars jadvali') +
                    ' · Sikl jadvali (' + label.toLowerCase() + ')' + (filterLabel ? ' · ' + filterLabel : '') +
                    (start ? ' · semestr boshi ' + start : '');
                const base = ((board.name || 'dars-jadvali') + '_sikl_' + label.toLowerCase() + (filterLabel ? '_' + filterLabel : ''))
                    .replace(/[^\w\-]+/g, '_');
                await sendExcelExport(payload, title, base, $('cycleExcel'));
            }
            if ($('cycleExcel')) $('cycleExcel').onclick = () => downloadCycleExcel();
            // Haftalik jadval (1–3 kurs): ekrandagi panjara — tanlangan hafta/shablon, tashqi/ichki qatlam
            // va ko'rinish qamrovi (Butun doska / Kafedraniki / Mas'ul fanlarim / Faqat meniki) bilan.
            async function downloadWeeklyExcel() {
                const cellData = gridExportCells();
                if (!cellData) { alert('Yuklab olish uchun panjara yo\'q.'); return; }
                const layer = weeklyLayer === 'external' ? 'tashqi' : 'ichki';
                const wk = curWeek ? curWeek + '-hafta' : 'barcha haftalar (shablon)';
                const scope = scopeMode !== 'all' ? ' · ' + scopeModeLabel() : '';
                // Uzun doska nomida ham hafta, qatlam va ko'rinish sarlavhada saqlansin.
                const title = 'Haftalik jadval (' + layer + ') · ' + wk + scope + (curSpec ? ' · ' + scopeLabelText() : '') +
                    ' · ' + (board.institution_name ? board.institution_name + ' — ' : '') + (board.name || 'Dars jadvali');
                const base = ((board.name || 'dars-jadvali').slice(0, 80) + '_haftalik_' + layer + '_' + (curWeek ? curWeek + '-hafta' : 'shablon') +
                    (scopeMode !== 'all' ? '_' + scopeModeLabel() : '')).replace(/[^\w\-]+/g, '_');
                cellData.sheet_title = 'Haftalik ' + layer;
                cellData.landscape = 1;
                await sendExcelExport(cellData, Array.from(title).slice(0, 255).join(''), base.slice(0, 150), $('weeklyExcel'));
            }
            if ($('weeklyExcel')) $('weeklyExcel').onclick = () => downloadWeeklyExcel();
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
                // Ekranда ko'rinaётган qamrov (tanlangan fakultet × yo'nalish × kurs)
                // o'zini yuklaymiz.
                const src = specCards();
                // Dars turi filtri + tanlangan hafta Excel ko'rinishga ham qo'llanadi
                const placed = src.filter(c => effPlace(c) && typeVisible(c)).map(effectiveCard);
                // Ustun tuzilishi rejimga qarab: guruh / o'qituvchi / auditoriya.
                // headGroups: [{title, span, cols:[{key,label}]}]; idx: "colKey|day|pair" → karta(lar)
                let headGroups = [], idx = {};
                const push = (key, d, p, c) => { const k = key + '|' + d + '|' + p; (idx[k] = idx[k] || []).push(c); };

                if (excelMode === 'group') {
                    const specMap = {};
                    src.forEach(c => {
                        // Fakultet ham kalitga kiradi — bir xil yo'nalish+kurs turli
                        // fakultetlarda bo'lsa, guruhlari aralashib ketmasin.
                        const sk = (c.faculty_name || '') + '|' + c.specialty_name + '|' + c.course;
                        (specMap[sk] = specMap[sk] || { name: c.specialty_name, course: c.course, faculty: c.faculty_name || '', groups: new Map() });
                        cardGroups(c).forEach(g => specMap[sk].groups.set(gkey(c, g), g));
                    });
                    // Ustunlar fakultet bo'yicha guruhlanadi — bir fakultet
                    // ustunlari ketma-ket (davomida) kelib, tepasida fakultet
                    // super-sarlavhasi ustma-ust turadi.
                    Object.values(specMap)
                        .map(s => ({ ...s, groups: [...s.groups.entries()].sort((a, b) => a[1].localeCompare(b[1], undefined, { numeric: true })) }))
                        .sort((a, b) => (a.faculty + '|' + a.name + a.course).localeCompare(b.faculty + '|' + b.name + b.course, 'uz'))
                        .forEach(s => headGroups.push({ title: s.name + ' · ' + s.course + '-kurs', cols: s.groups.map(([k, g]) => ({ key: k, label: g })) }));
                    placed.forEach(c => { const pl = effPlace(c); cardGroups(c).forEach(g => push(gkey(c, g), pl.day, pl.pair, c)); });
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
                    cards.forEach(c => cardGroups(c).forEach(g => { const k = gkey(c, g); if (!(k in gFac)) gFac[k] = c.faculty_name || ''; }));
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
            let gridSettingRows = []; // modalda ko'rsatiladigan fakultet/yo'nalish/kurs qatorlari
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
                    renderGridSettings();
                    const set = s.settings || {};
                    $('stDayOff').value = (set.days_off || []).join(', ');
                    $('stAllowZero').checked = !!set.allow_zero;
                    $('stShowNum').checked = !!set.show_day_number;
                    const br = (set.weekly_breaks || [])[0] || {};
                    $('stBreakFrom').value = br.from || '';
                    $('stBreakTo').value = br.to || '';
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
                $('setGrid').classList.toggle('hidden', name !== 'grid');
                $('setSave').classList.toggle('hidden', name === 'grid');
            }
            let gridFacultyFilter = '';
            let gridSpecialtyFilter = '';

            function renderGridSettings() {
                const seen = new Map();
                (cards || []).forEach(card => {
                    const row = {
                        faculty: card.faculty_name || '',
                        specialty: card.specialty_name || '',
                        course: +card.course || 0,
                        curriculum_hemis_id: +card.curriculum_hemis_id || 0,
                        program_name: card.program_name || 'Umumiy sozlama',
                        education_form_name: card.education_form_name || '',
                    };
                    const key = gridKey(row.faculty, row.specialty, row.course, row.curriculum_hemis_id);
                    if (!seen.has(key)) seen.set(key, row);
                });
                gridSettingRows = [...seen.values()].sort((a, b) =>
                    (a.faculty + '|' + a.specialty + '|' + a.course)
                        .localeCompare(b.faculty + '|' + b.specialty + '|' + b.course, 'uz')
                );

                const facultyValues = [...new Set(gridSettingRows.map(row => row.faculty))].sort((a, b) => a.localeCompare(b, 'uz'));
                const specialtyValues = [...new Set(gridSettingRows
                    .filter(row => !gridFacultyFilter || row.faculty === gridFacultyFilter)
                    .map(row => row.specialty))].sort((a, b) => a.localeCompare(b, 'uz'));

                $('stGridFacultyFilter').innerHTML =
                    '<option value="">Barcha fakultetlar</option>' +
                    facultyValues.map(value => '<option value="' + esc(value) + '">' + esc(value || 'Fakultet ko\'rsatilmagan') + '</option>').join('');
                $('stGridSpecialtyFilter').innerHTML =
                    '<option value="">Barcha yo\'nalishlar</option>' +
                    specialtyValues.map(value => '<option value="' + esc(value) + '">' + esc(value) + '</option>').join('');
                if (facultyValues.includes(gridFacultyFilter)) $('stGridFacultyFilter').value = gridFacultyFilter;
                else gridFacultyFilter = '', $('stGridFacultyFilter').value = '';
                if (specialtyValues.includes(gridSpecialtyFilter)) $('stGridSpecialtyFilter').value = gridSpecialtyFilter;
                else gridSpecialtyFilter = '', $('stGridSpecialtyFilter').value = '';

                const filtered = gridSettingRows.filter(row =>
                    (!gridFacultyFilter || row.faculty === gridFacultyFilter) &&
                    (!gridSpecialtyFilter || row.specialty === gridSpecialtyFilter)
                );
                const groups = new Map();
                filtered.forEach((row, index) => {
                    const key = row.faculty + '|' + row.specialty + '|' + row.curriculum_hemis_id;
                    if (!groups.has(key)) groups.set(key, { faculty: row.faculty, specialty: row.specialty, program: row.program_name, form: row.education_form_name, curriculum: row.curriculum_hemis_id, courses: new Map() });
                    const originalIndex = gridSettingRows.findIndex(item =>
                        gridKey(item.faculty, item.specialty, item.course, item.curriculum_hemis_id) === gridKey(row.faculty, row.specialty, row.course, row.curriculum_hemis_id)
                    );
                    groups.get(key).courses.set(row.course, { row, index: originalIndex });
                });

                if (!groups.size) {
                    $('stGridRows').innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">Mos keladigan yo\'nalish topilmadi.</div>';
                    return;
                }

                let html = '<div class="grid-setting-table-wrap"><table class="grid-setting-table"><thead><tr>' +
                    '<th>Fakultet</th><th>Ta’lim dasturi / o‘quv reja</th><th>Yo\'nalish</th>';
                for (let course = 1; course <= 6; course++) html += '<th>' + course + '-kurs</th>';
                html += '</tr></thead><tbody>';

                groups.forEach(group => {
                    html += '<tr><td class="grid-setting-name"><b>' + esc(group.faculty || 'Fakultet ko\'rsatilmagan') + '</b></td>' +
                        '<td class="grid-setting-name"><b>' + esc(group.program) + '</b><div>' + esc(group.form) + '</div></td>' +
                        '<td class="grid-setting-name"><span>' + esc(group.specialty) + '</span></td>';
                    for (let course = 1; course <= 6; course++) {
                        const item = group.courses.get(course);
                        if (!item) {
                            html += '<td class="grid-setting-empty">—</td>';
                            continue;
                        }
                        const row = item.row;
                        const g = gridForCard(row);
                        html += '<td><div class="grid-setting-cell" data-grid-row="' + item.index + '">' +
                            '<div class="grid-setting-values">' +
                                '<label title="Kunlar soni">K<input data-grid-field="days" type="number" min="1" max="7" value="' + (+g.days || board.days) + '"></label>' +
                                '<label title="Kuniga paralar soni">P<input data-grid-field="pairs_per_day" type="number" min="1" max="24" value="' + (+g.pairs_per_day || board.pairs_per_day) + '"></label>' +
                                '<label title="Hafta soni">H<input data-grid-field="weeks" type="number" min="1" max="30" value="' + (+g.weeks || board.weeks) + '"></label>' +
                                '<label title="Semestr boshlanishi (bo\'sh — doska semestr boshi)">B<input data-grid-field="start_date" type="date" value="' + esc(String(g.start_date || '').slice(0, 10)) + '" style="width:118px"></label>' +
                            '</div>' +
                            '<label title="Bo‘sh qoldirilsa doska ta’tili ishlaydi"><input data-grid-field="inherit_breaks" type="checkbox"' + (g.weekly_breaks == null ? ' checked' : '') + '> Umumiy ta’til</label>' +
                            '<label>Ta’til boshi<input data-grid-field="break_from" type="date" value="' + esc(g.weekly_breaks?.[0]?.from || '') + '"></label>' +
                            '<label>Ta’til oxiri<input data-grid-field="break_to" type="date" value="' + esc(g.weekly_breaks?.[0]?.to || '') + '"></label>' +
                            '<button type="button" class="grid-setting-save" data-grid-save="' + item.index + '" title="Saqlash"><img src="' + ICON_BASE + '/saqlash.png" alt="" aria-hidden="true"></button>' +
                            '<span class="grid-setting-status"></span>' +
                        '</div></td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                $('stGridRows').innerHTML = html;
                $('stGridRows').querySelectorAll('[data-grid-save]').forEach(btn => {
                    btn.onclick = () => saveGridSettingRow(+btn.dataset.gridSave, btn);
                });
            }

            $('stGridFacultyFilter').onchange = function () {
                gridFacultyFilter = this.value;
                gridSpecialtyFilter = '';
                renderGridSettings();
            };
            $('stGridSpecialtyFilter').onchange = function () {
                gridSpecialtyFilter = this.value;
                renderGridSettings();
            };

            async function saveGridSettingRow(index, btn) {
                const row = gridSettingRows[index];
                const wrap = btn.closest('[data-grid-row]');
                if (!row || !wrap || !board) return;
                const value = name => wrap.querySelector('[data-grid-field="' + name + '"]').value;
                const status = wrap.querySelector('.grid-setting-status');
                const inheritBreaks = wrap.querySelector('[data-grid-field="inherit_breaks"]').checked;
                const from = value('break_from'), to = value('break_to');
                if (!inheritBreaks && ((from && !to) || (!from && to) || (from && to < from))) {
                    status.textContent = 'Ta’til sanalarini to‘liq va to‘g‘ri kiriting.';
                    return;
                }
                btn.disabled = true;
                status.textContent = 'Saqlanmoqda...';
                try {
                    await api(BASE + '/boards/' + board.id + '/grid', 'POST', {
                        faculty_name: row.faculty,
                        curriculum_hemis_id: row.curriculum_hemis_id,
                        weekly_breaks: inheritBreaks ? null : (from ? [{ from, to }] : []),
                        specialty_name: row.specialty,
                        course: row.course,
                        days: value('days'),
                        pairs_per_day: value('pairs_per_day'),
                        weeks: value('weeks'),
                        start_date: value('start_date') || '',
                        regenerate: 0,
                    });
                    await loadBoard(board.id);
                    renderGridSettings();
                    $('setMsg').textContent = 'Panjara sozlamasi saqlandi.';
                } catch (e) {
                    status.textContent = 'Xatolik: ' + e.message;
                    btn.disabled = false;
                }
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
                            '<button class="asc-mini" data-edit="' + i + '" title="Tahrirlash"><img src="{{ asset('image/tahrirlash.png') }}" alt="" aria-hidden="true"></button>' +
                            '<button class="asc-mini" data-del="' + i + '" title="O\'chirish"><img src="{{ asset('image/11_delete.png') }}" alt="" aria-hidden="true"></button>' +
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
                if ($('stBreakFrom').value && $('stBreakTo').value) {
                    fd.append('settings[weekly_breaks][0][from]', $('stBreakFrom').value);
                    fd.append('settings[weekly_breaks][0][to]', $('stBreakTo').value);
                } else {
                    fd.append('settings[weekly_breaks]', '');
                }
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
            const ASG_HEADER_KEYS = ['subject', 'type', 'scope', 'department', 'cards', 'teacher'];
            let asgAudRooms = [];
            let asgAudSel = null;
            let asgAudTeacherTimer = null;
            let asgAudDepartmentsLoaded = false;

            function newAsgHeaderFilters() {
                return { subject: '', type: '', scope: '', department: '', cards: '', teacher: '' };
            }

            let asgHeaderFilters = newAsgHeaderFilters();

            // Ustun filtrlari (jadval sarlavhasi ostidagi qator): matn bo'yicha qidiruv, tur — tanlov
            const asgColFilters = { subject: '', spec: '', type: '', scope: '', kaf: '', teacher: '', room: '' };
            const asgNormText = v => String(v || '').toLowerCase().replace(/[‘’ʻʼ`´]/g, "'");
            function asgColMatch(u) {
                const has = (val, q) => !q || asgNormText(val).includes(asgNormText(q));
                return has(u.subject_name, asgColFilters.subject)
                    && has(asgSpecLabel(u), asgColFilters.spec)
                    && (!asgColFilters.type || u.training_type === asgColFilters.type)
                    && has(asgScopeLabel(u) + ' ' + (u.group_names || []).join(' '), asgColFilters.scope)
                    && has(u.kafedra_name, asgColFilters.kaf)
                    && has(asgTeacherText(u), asgColFilters.teacher)
                    && has(u.room_mixed ? 'turlicha' : (u.auditorium_name || ''), asgColFilters.room);
            }
            let asgColTimer = null;
            function ensureAsgHead() {
                const table = $('asgTable');
                if (table.tHead && table.tHead.dataset.f) return;
                const inp = (key, ph) => '<th><input type="search" data-asg-col="' + key + '" placeholder="' + ph + '" aria-label="' + ph + '"></th>';
                table.innerHTML = '<thead data-f="1"><tr><th>Fan</th><th>Fakultet / yo\'nalish</th><th>Tur</th><th>Oqim/Guruh</th><th>Kafedra</th><th>Karta</th><th>O\'qituvchi</th><th>Xona</th></tr>' +
                    '<tr class="asg-frow">' + inp('subject', 'Fan...') + inp('spec', 'Fakultet / yo\'nalish...') +
                    '<th><select data-asg-col="type" aria-label="Tur"><option value="">Hammasi</option><option value="lecture">Ma\'ruza</option><option value="practice">Amaliy</option></select></th>' +
                    inp('scope', 'Oqim / guruh...') + inp('kaf', 'Kafedra...') + '<th></th>' +
                    inp('teacher', "O'qituvchi...") + inp('room', 'Xona...') + '</tr></thead><tbody></tbody>';
                table.tHead.querySelectorAll('[data-asg-col]').forEach(el => {
                    el.value = asgColFilters[el.dataset.asgCol] || '';
                    const apply = () => {
                        asgColFilters[el.dataset.asgCol] = el.value.trim();
                        el.classList.toggle('is-active', !!el.value.trim());
                        clearTimeout(asgColTimer);
                        asgColTimer = setTimeout(renderAsgTable, el.tagName === 'SELECT' ? 0 : 200);
                    };
                    el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', apply);
                });
            }

            function resetAsgHeaderFilters() {
                asgHeaderFilters = newAsgHeaderFilters();
            }

            function asgTypeLabel(unit) {
                return unit.training_type === 'lecture' ? "Ma'ruza" : 'Amaliy';
            }

            function asgScopeLabel(unit) {
                return unit.training_type === 'lecture' ? (unit.oqim_label || 'oqim') : (unit.group_name || '');
            }

            function asgSpecLabel(unit) {
                return (unit.faculty_name ? unit.faculty_name + ' / ' : '') + unit.specialty_name + ' · ' + unit.course + '-kurs';
            }

            function asgSpecHtml(unit) {
                const faculty = unit.faculty_name ? '<b>' + esc(unit.faculty_name) + '</b><br>' : '';
                return faculty + '<span class="text-slate-500">' + esc(unit.specialty_name + ' · ' + unit.course + '-kurs') + '</span>';
            }

            function asgTeacherText(unit) {
                if (unit.teacher_mixed) return 'turlicha';
                return unit.teacher_name || 'biriktirilmagan';
            }

            function asgHeaderValue(unit, key) {
                switch (key) {
                    case 'type': return asgTypeLabel(unit);
                    case 'scope': return asgScopeLabel(unit) || '-';
                    case 'department': return unit.kafedra_name || '-';
                    case 'cards': return String(unit.cards || 0);
                    case 'teacher': return asgTeacherText(unit);
                    case 'subject':
                    default: return unit.subject_name || '-';
                }
            }

            function asgCompareText(a, b) {
                return String(a || '').localeCompare(String(b || ''), 'uz', {
                    numeric: true,
                    sensitivity: 'base'
                });
            }

            function asgMatchesHeaderFilters(unit, exceptKey = null) {
                for (const key of ASG_HEADER_KEYS) {
                    if (key === exceptKey) continue;
                    const active = asgHeaderFilters[key];
                    if (active && asgHeaderValue(unit, key) !== active) return false;
                }
                return true;
            }

            function asgHeaderOptions(key, baseRows) {
                const values = [...new Set((baseRows || [])
                    .filter(unit => asgMatchesHeaderFilters(unit, key))
                    .map(unit => asgHeaderValue(unit, key))
                    .filter(Boolean))];

                if (key === 'cards') {
                    values.sort((a, b) => (+a || 0) - (+b || 0));
                } else {
                    values.sort(asgCompareText);
                }

                return values;
            }

            function asgNormalizeHeaderFilters(baseRows) {
                ASG_HEADER_KEYS.forEach(key => {
                    const active = asgHeaderFilters[key];
                    if (!active) return;
                    if (!asgHeaderOptions(key, baseRows).includes(active)) {
                        asgHeaderFilters[key] = '';
                    }
                });
            }

            function asgHeaderIndicator(key) {
                const active = asgHeaderFilters[key];
                return active ? '<span class="asg-sort-indicator">' + esc(active) + '</span>' : '';
            }

            function asgHeaderCell(label, key, center = false) {
                return '<th>' +
                    '<button type="button" class="asg-sort-btn' + (center ? ' asg-sort-btn-center' : '') + (asgHeaderFilters[key] ? ' is-active' : '') + '" data-asg-filter="' + key + '" title="Bosib filtrni almashtiring">' +
                    '<span>' + label + '</span>' +
                    asgHeaderIndicator(key) +
                    '</button>' +
                    '</th>';
            }

            function asgToggleHeaderFilter(key) {
                const baseRows = asgFiltered();
                const options = asgHeaderOptions(key, baseRows);

                if (!options.length) {
                    asgHeaderFilters[key] = '';
                    renderAsgTable();
                    return;
                }

                const current = asgHeaderFilters[key] || '';
                const currentIndex = options.indexOf(current);
                asgHeaderFilters[key] = currentIndex === -1
                    ? options[0]
                    : (currentIndex === options.length - 1 ? '' : options[currentIndex + 1]);

                renderAsgTable();
            }

             function setAsgTab(tab) {
                 document.querySelectorAll('[data-asg-tab]').forEach(btn => {
                     btn.classList.toggle('active', btn.dataset.asgTab === tab);
                 });
                 document.querySelectorAll('[data-asg-panel]').forEach(panel => {
                     const active = panel.dataset.asgPanel === tab;
                     panel.classList.toggle('hidden', !active);
                     if (panel.dataset.asgPanel === 'auditoriums') panel.classList.toggle('flex', active);
                 });
             }
             document.querySelectorAll('[data-asg-tab]').forEach(btn => {
                 btn.onclick = () => {
                     const tab = btn.dataset.asgTab;
                     setAsgTab(tab);
                     if (tab === 'auditoriums') loadAsgAuditoriums();
                 };
             });

            $('assignBtn').onclick = async () => {
                if (!board) return;
                asgSel = null; setAsgTeacherPanel(null); resetAsgHeaderFilters(); $('asgTypeFilter').value = '';
                setAsgTab(TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY ? 'auditoriums' : 'teachers');
                $('assignModal').classList.remove('hidden');
                $('asgMsg').textContent = '';

                if (TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY) {
                    await loadAsgAuditoriums();
                    return;
                }

                $('asgTable').innerHTML = '<tbody><tr><td class="p-3 text-gray-400">Yuklanmoqda...</td></tr></tbody>';
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/teacher-units');
                    asgUnits = j.units || [];
                } catch (e) { asgUnits = []; $('asgMsg').textContent = 'Xatolik: ' + e.message; }
                const specs = [...new Set(asgUnits.map(u => u.specialty_name + ' · ' + u.course + '-kurs'))].sort();
                $('asgFilter').innerHTML = '<option value="">— barcha yo\'nalishlar —</option>' +
                    specs.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('');
                const scopedSpecs = [...new Set(asgUnits.map(u => asgSpecLabel(u)))].sort();
                $('asgFilter').innerHTML = '<option value="">-- barcha yo\'nalishlar --</option>' +
                    scopedSpecs.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('');
                renderAsgTable();
            };
            // Excel hisobot: kafedralar kesimida biriktirish holati (faqat admin)
            if ($('asgReportBtn')) {
                $('asgReportBtn').onclick = () => {
                    if (board) window.location.href = BASE + '/boards/' + board.id + '/assignment-report';
                };
            }
            $('asgClose').onclick = $('asgCloseBtn').onclick = () => {
                $('assignModal').classList.add('hidden');
                renderAll();   // grid/panel o'qituvchi o'zgarishlarini aks ettirsin
            };
            $('asgFilter').onchange = $('asgSearch').oninput = $('asgOnlyEmpty').onchange = $('asgTypeFilter').onchange = () => renderAsgTable();

            function asgFiltered() {
                const q = ($('asgSearch').value || '').toLowerCase().trim();
                const fv = $('asgFilter').value;
                const tv = $('asgTypeFilter').value;
                const onlyEmpty = $('asgOnlyEmpty').checked;
                return asgUnits.filter(u => {
                    if (fv && (u.specialty_name + ' · ' + u.course + '-kurs') !== fv) return false;
                    if (tv && asgTypeLabel(u) !== tv) return false;
                    if (onlyEmpty && u.teacher_id) return false;
                    if (q && !(u.subject_name.toLowerCase().includes(q) || (u.kafedra_name || '').toLowerCase().includes(q))) return false;
                    return true;
                });
            }

            function asgFilteredScoped() {
                const q = ($('asgSearch').value || '').toLowerCase().trim();
                const fv = $('asgFilter').value;
                const tv = $('asgTypeFilter').value;
                const onlyEmpty = $('asgOnlyEmpty').checked;
                return asgUnits.filter(u => {
                    if (fv && asgSpecLabel(u) !== fv) return false;
                    if (tv && asgTypeLabel(u) !== tv) return false;
                    if (onlyEmpty && u.teacher_id) return false;
                    if (q && !(u.subject_name.toLowerCase().includes(q) || (u.kafedra_name || '').toLowerCase().includes(q))) return false;
                    return true;
                });
            }

            function renderAsgTable() {
                const baseRows = asgFilteredScoped();
                asgNormalizeHeaderFilters(baseRows);
                const rows = baseRows.filter(unit => asgMatchesHeaderFilters(unit) && asgColMatch(unit));
                if (asgSel && !rows.includes(asgSel)) {
                    asgSel = null;
                    setAsgTeacherPanel(null);
                }
                $('asgCount').textContent = rows.length + ' ta';
                ensureAsgHead();
                let h = '';
                if (!rows.length) h += '<tr><td colspan="8" class="p-3 text-gray-400">Ma\'lumot topilmadi</td></tr>';
                rows.forEach((u, i) => {
                    const scopeLabel = asgScopeLabel(u);
                    const ttLabel = u.training_type === 'lecture'
                        ? '<span class="text-blue-600 font-semibold">Ma\'ruza</span>'
                        : '<span class="text-purple-600 font-semibold">Amaliy</span>';
                    const teacherLabel = u.teacher_mixed
                        ? '<span class="text-amber-600">⚠ turlicha</span>'
                        : (u.teacher_name ? esc(u.teacher_name) : '<span class="text-gray-400">— biriktirilmagan —</span>');
                    h += '<tr data-i="' + i + '"' + (asgSel === rows[i] ? ' class="sel"' : '') + '>' +
                        '<td>' + esc(u.subject_name) + '</td><td>' + asgSpecHtml(u) + '</td><td class="text-center">' + ttLabel + '</td>' +
                        '<td>' + esc(scopeLabel) + '</td><td class="asg-kaf" title="' + esc(u.kafedra_name || '') + '">' + esc(u.kafedra_name || '—') + '</td>' +
                        '<td class="text-center">' + u.cards + (u.placed ? ' <span class="text-green-600">(' + u.placed + '✓)</span>' : '') + '</td>' +
                        '<td>' + teacherLabel + '</td>' +
                        '<td>' + (u.room_mixed ? '<span class="text-amber-600">⚠ turlicha</span>'
                            : (u.auditorium_name ? esc(u.auditorium_name) : '<span class="text-gray-400">—</span>')) + '</td></tr>';
                    return;
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
                $('asgTable').tBodies[0].innerHTML = h;
                // Filtr qatori birinchi sarlavha ostida yopishib tursin
                const head = $('asgTable').tHead;
                const firstRowH = head.rows[0].offsetHeight;
                if (firstRowH) head.rows[1].querySelectorAll('th').forEach(th => th.style.top = firstRowH + 'px');
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
                    '<span class="text-slate-600">' + asgSpecHtml(u) + '</span><br>' +
                    (u.training_type === 'lecture' ? "Ma'ruza · " + esc(u.oqim_label || '') : 'Amaliy · ' + esc(u.group_name || '')) +
                    ' · ' + u.cards + ' karta' + (u.kafedra_name ? '<br><span class="text-gray-400">' + esc(u.kafedra_name) + '</span>' : '');
                renderAsgRoomCurrent();
                await loadAsgTeachers('');
            }

            // ----- Xona (auditoriya): ro'yxat alohida oynada, bir marta yuklanadi -----
            let asgRoomsList = null;
            function renderAsgRoomCurrent() {
                if (!asgSel) { $('asgRoomCurrent').textContent = '—'; return; }
                $('asgRoomCurrent').innerHTML = asgSel.room_mixed
                    ? '<span class="text-amber-600">⚠ kartalarda turlicha</span>'
                    : (asgSel.auditorium_name ? '<b class="text-slate-700">' + esc(asgSel.auditorium_name) + '</b>' : '<span class="text-slate-400">biriktirilmagan</span>');
            }
            async function openAsgRoomModal() {
                if (!asgSel) return;
                $('asgRoomFor').textContent = asgSel.subject_name + ' · ' +
                    (asgSel.training_type === 'lecture' ? "Ma'ruza · " + (asgSel.oqim_label || '') : 'Amaliy · ' + (asgSel.group_name || ''));
                $('asgRoomSearch').value = '';
                $('asgRoomList').innerHTML = '<div class="p-3 text-sm text-slate-400">Yuklanmoqda...</div>';
                $('asgRoomModal').classList.remove('hidden');
                if (!asgRoomsList) {
                    try {
                        asgRoomsList = (await api(AUDS_URL) || [])
                            .filter(room => room.active !== false)
                            .sort((a, b) => String(a.name || a.code || '').localeCompare(String(b.name || b.code || ''), undefined, { numeric: true }));
                    } catch (e) {
                        $('asgRoomList').innerHTML = '<div class="p-3 text-sm text-red-500">' + esc(e.message) + '</div>';
                        return;
                    }
                }
                renderAsgRooms();
                $('asgRoomSearch').focus();
            }
            function closeAsgRoomModal() { $('asgRoomModal').classList.add('hidden'); }
            function renderAsgRooms() {
                if (!asgSel || !asgRoomsList) return;
                const q = ($('asgRoomSearch').value || '').toLowerCase().trim();
                const list = asgRoomsList.filter(room => !q || [room.code, room.name, room.building_name, room.auditorium_type_name]
                    .some(v => String(v || '').toLowerCase().includes(q)));
                $('asgRoomList').innerHTML = list.map(room =>
                    '<button type="button" class="rm-row' + (asgSel.auditorium_code === room.code ? ' is-current' : '') + '" data-code="' + esc(room.code) + '">' +
                    '<b>' + esc(room.name || room.code) + '</b>' +
                    '<span>' + esc([room.building_name, room.auditorium_type_name].filter(Boolean).join(' · ')) + '</span>' +
                    '<span class="rm-vol">' + (room.volume ? room.volume + ' o\'rin' : '') + '</span></button>').join('')
                    || '<div class="p-3 text-sm text-slate-400">Xona topilmadi</div>';
            }
            $('asgRoomOpen').onclick = () => openAsgRoomModal();
            $('asgRoomClear').onclick = () => applyAsgRoom(null);
            $('asgRoomSearch').oninput = () => renderAsgRooms();
            $('asgRoomModalClose').onclick = $('asgRoomCancel').onclick = () => closeAsgRoomModal();
            $('asgRoomModal').addEventListener('click', (ev) => {
                if (ev.target === ev.currentTarget || ev.target.classList.contains('tt-modal-body')) { closeAsgRoomModal(); return; }
                const row = ev.target.closest('.rm-row');
                if (row) applyAsgRoom(row.dataset.code);
            });

            async function applyAsgRoom(code) {
                if (!asgSel) return;
                $('asgRoomOpen').disabled = $('asgRoomClear').disabled = true;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/assign-room', 'POST', {
                        faculty_name: asgSel.faculty_name || '', specialty_name: asgSel.specialty_name, course: asgSel.course,
                        subject_name: asgSel.subject_name, training_type: asgSel.training_type,
                        oqim_label: asgSel.oqim_label || '', group_name: asgSel.group_name || '',
                        auditorium_code: code || '',
                    });
                    asgSel.auditorium_code = j.auditorium_code || null;
                    asgSel.auditorium_name = j.auditorium_name || null;
                    asgSel.room_mixed = false;
                    cards.forEach(c => {
                        const sameScope = asgSel.training_type === 'lecture'
                            ? (c.oqim_label === asgSel.oqim_label) : (c.group_name === asgSel.group_name);
                        if ((c.faculty_name || '') === (asgSel.faculty_name || '') && c.specialty_name === asgSel.specialty_name && c.course === asgSel.course &&
                            c.subject_name === asgSel.subject_name && c.training_type === asgSel.training_type && sameScope) {
                            c.auditorium_code = asgSel.auditorium_code; c.auditorium_name = asgSel.auditorium_name;
                        }
                    });
                    $('asgMsg').textContent = (j.auditorium_name ? '«' + j.auditorium_name + '» xonasi biriktirildi' : 'Xona olib tashlandi') +
                        ' · ' + j.affected + ' karta';
                    renderAsgTable();
                    renderAsgRoomCurrent();
                    closeAsgRoomModal();
                } catch (e) {
                    $('asgMsg').textContent = 'Xatolik: ' + e.message;
                    // Oyna ochiq bo'lsa xatoni shu yerda ham ko'rsatamiz
                    if (!$('asgRoomModal').classList.contains('hidden')) {
                        $('asgRoomFor').innerHTML = '<span class="text-red-600">Xatolik: ' + esc(e.message) + '</span>';
                    }
                }
                $('asgRoomOpen').disabled = $('asgRoomClear').disabled = false;
            }

            function setAsgTeacherPanel(u) {
                const on = !!u;
                ['asgTeacherSearch', 'asgTeacher', 'asgApply', 'asgClear', 'asgKafedraOnly',
                    'asgRoomOpen', 'asgRoomClear'].forEach(id => $(id).disabled = !on);
                if (!on) {
                    $('asgUnitInfo').textContent = '← Chapdan dars birligini tanlang';
                    $('asgTeacher').innerHTML = ''; $('asgTeacherSearch').value = '';
                    $('asgRoomCurrent').textContent = '—';
                }
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
                        faculty_name: asgSel.faculty_name || '', specialty_name: asgSel.specialty_name, course: asgSel.course,
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
                        if ((c.faculty_name || '') === (asgSel.faculty_name || '') && c.specialty_name === asgSel.specialty_name && c.course === asgSel.course &&
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

            async function loadAsgAuditoriums() {
                if (!board) return;
                $('asgAudTable').innerHTML = '<tbody><tr><td class="p-3 text-gray-400">Yuklanmoqda...</td></tr></tbody>';
                try {
                    // Xonalar ma'lumotlar modalidagi Auditoriyalar tabida
                    // ishlatiladigan ayni endpointdan olinadi; assignmentlar ustiga birlashtiriladi.
                    const [rooms, assignments] = await Promise.all([
                        api(AUDS_URL),
                        api(BASE + '/boards/' + board.id + '/auditorium-teachers'),
                    ]);
                    const assignmentById = Object.fromEntries(
                        (assignments.auditoriums || []).map(room => [String(room.id), room])
                    );
                    asgAudRooms = (rooms || [])
                        .filter(room => room.active !== false)
                        .map(room => ({ ...room, ...(assignmentById[String(room.id)] || {}) }));
                    if (!asgAudDepartmentsLoaded) {
                        const departments = await api(TEACHER_DEPARTMENTS_URL);
                        const departmentOptions = departments
                            .map(d => '<option value="' + esc(d) + '">' + esc(d) + '</option>')
                            .join('');
                        $('asgAudDepartment').innerHTML = TIMETABLE_DEPARTMENT_HEAD
                            ? departmentOptions
                            : '<option value="">— barcha kafedralar —</option>' + departmentOptions;
                        if (TIMETABLE_DEPARTMENT_HEAD && departments.length) {
                            $('asgAudDepartment').value = departments[0];
                        }
                        asgAudDepartmentsLoaded = true;
                    }
                    renderAsgAudTable();
                    if (asgAudSel) selectAsgAudRoom(asgAudSel.id);
                } catch (e) {
                    asgAudRooms = [];
                    $('asgAudTable').innerHTML = '<tbody><tr><td class="p-3 text-red-500">' + esc(e.message) + '</td></tr></tbody>';
                }
            }

            function filteredAsgAudRooms() {
                const q = ($('asgAudSearch').value || '').toLowerCase().trim();
                return asgAudRooms.filter(room => !q || [room.code, room.name, room.building_name, room.auditorium_type_name].some(value => String(value || '').toLowerCase().includes(q)));
            }

            function renderAsgAudTable() {
                const rows = filteredAsgAudRooms();
                $('asgAudCount').textContent = rows.length + ' ta';

                let h = '<colgroup><col style="width:16%"><col style="width:24%"><col style="width:9%">' +
                    '<col style="width:15%"><col style="width:16%"><col style="width:20%"></colgroup>';

                h += '<thead><tr><th>Xona</th><th>Bino</th><th class="text-center">Sig\'im</th>' +
                    '<th>Turi</th><th>Holat</th><th>O\'qituvchi</th></tr></thead><tbody>';

                rows.forEach((room, i) => {
                    const general = !!room.is_general;
                    const hasTeacher = !!room.teacher_id;
                    const capacity = room.volume ?? room.capacity ?? 0;
                    const unassignButton = room.assignment_id
                        ? '<button type="button" data-asg-aud-unassign="' + room.id + '" class="inline-flex items-center justify-center text-red-500 hover:text-red-700" title="Biriktirishni bekor qilish"><i class="bi bi-trash3" aria-hidden="true"></i></button>'
                        : '';
                    const status = room.active === false
                        ? '<span class="text-red-600 font-semibold">Faol emas</span>'
                        : (general
                            ? '<span class="inline-flex items-center gap-1">' + unassignButton + '<span class="aud-general">Umumiy</span></span>'
                            : (hasTeacher
                                ? '<span class="inline-flex items-center gap-1">' + unassignButton + '<span class="text-amber-600 font-semibold">Biriktirilgan</span></span>'
                                : '<span class="text-slate-400">Biriktirilmagan</span>'));
                    const teacher = general
                        ? '<span class="text-slate-400">Barcha o\'qituvchilar</span>'
                        : (hasTeacher ? '<span class="aud-teacher">' + esc(room.teacher_name || '—') + '</span>' : '<span class="text-slate-400">Tanlanmagan</span>');
                    h += '<tr data-i="' + i + '"' + (asgAudSel && asgAudSel.id === room.id ? ' class="sel"' : '') + '>' +
                        '<td class="font-semibold">' + esc(room.name || room.code) + '</td>' +
                        '<td>' + esc(room.building_name || '—') + '</td>' +
                        '<td class="text-center text-emerald-600 font-semibold">' + esc(String(capacity)) + '</td>' +
                        '<td>' + esc(room.auditorium_type_name || '—') + '</td>' +
                        '<td>' + status + '</td><td>' + teacher + '</td></tr>';
                });

                $('asgAudTable').innerHTML = h + '</tbody>';
                const rowsRef = rows;
                $('asgAudTable').querySelectorAll('tbody tr[data-i]').forEach(tr => tr.onclick = () => {
                    asgAudSel = rowsRef[+tr.dataset.i];
                    $('asgAudTable').querySelectorAll('tbody tr').forEach(row => row.classList.remove('sel'));
                    tr.classList.add('sel');
                    selectAsgAudRoom(asgAudSel.id);
                });
                $('asgAudTable').querySelectorAll('[data-asg-aud-unassign]').forEach(button => {
                    button.onclick = async event => {
                        event.preventDefault();
                        event.stopPropagation();

                        const room = asgAudRooms.find(item => +item.id === +button.dataset.asgAudUnassign);
                        if (!room || !confirm('«' + (room.name || room.code) + '» auditoriyasidagi biriktirishni bekor qilasizmi?')) return;

                        button.disabled = true;
                        try {
                            const result = await api(BASE + '/boards/' + board.id + '/auditorium-teachers/' + room.id, 'DELETE', {});
                            room.assignment_id = null;
                            room.teacher_id = null;
                            room.teacher_name = null;
                            room.is_general = false;
                            if (asgAudSel && +asgAudSel.id === +room.id) {
                                $('asgAudGeneral').checked = false;
                                $('asgAudTeacher').value = '';
                                applyAsgAudGeneralState();
                            }
                            $('asgMsg').textContent = result.message || 'Biriktirish bekor qilindi';
                            renderAsgAudTable();
                        } catch (e) {
                            $('asgMsg').textContent = 'Xatolik: ' + e.message;
                            button.disabled = false;
                        }
                    };
                });
            }

            function setAsgAudControls(on) {
                ['asgAudDepartment', 'asgAudTeacherSearch', 'asgAudTeacher', 'asgAudGeneral', 'asgAudApply', 'asgAudClear'].forEach(id => $(id).disabled = !on);
                if (!on) {
                    $('asgAudInfo').textContent = 'Chapdan auditoriyani tanlang';
                    $('asgAudTeacher').innerHTML = '';
                    $('asgAudTeacherSearch').value = '';
                    $('asgAudGeneral').checked = false;
                }
            }

            function applyAsgAudGeneralState() {
                const general = $('asgAudGeneral').checked;
                $('asgAudTeacher').disabled = !asgAudSel || general;
                $('asgAudTeacherSearch').disabled = !asgAudSel || general;
                $('asgAudDepartment').disabled = !asgAudSel || general || TIMETABLE_DEPARTMENT_HEAD;
            }

            async function selectAsgAudRoom(roomId) {
                asgAudSel = asgAudRooms.find(room => +room.id === +roomId) || null;
                setAsgAudControls(!!asgAudSel);
                if (!asgAudSel) return;
                $('asgAudInfo').innerHTML = '<b>' + esc(asgAudSel.name || asgAudSel.code) + '</b><br>' + esc(asgAudSel.building_name || 'Bino ko\'rsatilmagan') + ' · ' + (asgAudSel.volume || 0) + ' o\'rin';
                $('asgAudGeneral').checked = !!asgAudSel.is_general;
                applyAsgAudGeneralState();
                await loadAsgAudTeachers('');
                if (asgAudSel.teacher_id) $('asgAudTeacher').value = String(asgAudSel.teacher_id);
            }

            async function loadAsgAudTeachers(search) {
                if (!asgAudSel || $('asgAudGeneral').checked) return;
                const p = new URLSearchParams();
                if ($('asgAudDepartment').value) p.set('kafedra', $('asgAudDepartment').value);
                if (search) p.set('search', search);
                try {
                    const list = await api(TEACHERS_URL + '?' + p);
                    $('asgAudTeacher').innerHTML = list.map(t => '<option value="' + t.id + '">' + esc(t.short_name || t.full_name) + (t.lavozim ? ' · ' + esc(t.lavozim) : '') + '</option>').join('') || '<option disabled>topilmadi</option>';
                    if (asgAudSel?.teacher_id) $('asgAudTeacher').value = String(asgAudSel.teacher_id);
                } catch (e) { $('asgAudTeacher').innerHTML = '<option disabled>xato</option>'; }
            }

            $('asgAudSearch').oninput = () => renderAsgAudTable();
            $('asgAudDepartment').onchange = () => loadAsgAudTeachers($('asgAudTeacherSearch').value.trim());
            $('asgAudTeacherSearch').oninput = function () {
                clearTimeout(asgAudTeacherTimer);
                asgAudTeacherTimer = setTimeout(() => loadAsgAudTeachers(this.value.trim()), 300);
            };
            $('asgAudGeneral').onchange = () => {
                applyAsgAudGeneralState();
                if (!$('asgAudGeneral').checked) loadAsgAudTeachers('');
            };
            $('asgAudApply').onclick = () => saveAsgAudRoom(false);
            $('asgAudClear').onclick = () => saveAsgAudRoom(true);

            async function saveAsgAudRoom(forceGeneral) {
                if (!asgAudSel) return;
                const isGeneral = forceGeneral || $('asgAudGeneral').checked;
                const teacherId = isGeneral ? null : ($('asgAudTeacher').value || null);
                if (!isGeneral && !teacherId) {
                    $('asgMsg').textContent = 'Umumiy bo\'lmagan xona uchun o\'qituvchi tanlang';
                    return;
                }
                $('asgAudApply').disabled = $('asgAudClear').disabled = true;
                try {
                    const j = await api(BASE + '/boards/' + board.id + '/assign-auditorium-teacher', 'POST', {
                        auditorium_id: asgAudSel.id, teacher_id: teacherId || '', is_general: isGeneral ? 1 : 0,
                    });
                    asgAudSel.assignment_id = j.assignment_id;
                    asgAudSel.teacher_id = j.teacher_id;
                    asgAudSel.teacher_name = j.teacher_name;
                    asgAudSel.is_general = !!j.is_general;
                    $('asgAudGeneral').checked = asgAudSel.is_general;
                    applyAsgAudGeneralState();
                    renderAsgAudTable();
                    $('asgMsg').textContent = asgAudSel.is_general ? '«' + asgAudSel.name + '» umumiy xona qilindi' : '«' + asgAudSel.name + '» uchun o\'qituvchi biriktirildi';
                } catch (e) { $('asgMsg').textContent = 'Xatolik: ' + e.message; }
                $('asgAudApply').disabled = $('asgAudClear').disabled = false;
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
                    // Guruh kaliti bo'yicha — turli fakultetdagi bir xil nomli
                    // guruhlar bitta qatorga qo'shilib, soxta "oyna" bermasin.
                    const k = gkey(c, g) + '||' + c.day; (gday[k] = gday[k] || new Set()).add(c.pair);
                }));
                const gaps = [];
                Object.entries(gday).forEach(([k, set]) => {
                    const [gk, d] = k.split('||');
                    const g = gk.split('¦').pop();          // ko'rinadigan guruh nomi
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
                const issues = teacherConf.length + roomConf.length + gaps.length + missingGroups.length;
                return { unplacedBySpec, totalUnplaced, teacherConf, roomConf, gaps, noTeacher, missingGroups, issues, dayName };
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
                    '<section class="check-section ' + (count ? 'is-danger' : 'is-ok') + '">' +
                    '<div class="check-section-head">' +
                    '<span class="check-section-icon" aria-hidden="true">' + icon + '</span>' +
                    '<span class="check-section-title">' + title + '</span>' +
                    '<span class="check-section-count">' + (count ? count + ' ta' : '✓ ' + ok) + '</span>' +
                    '</div>' +
                    (count ? '<div class="check-section-body">' + body + '</div>' : '<div class="check-section-empty">' + ok + '</div>') +
                    '</section>';

                let h = '';
                // Rejada fani bor, lekin guruh proyeksiyasi yo'q — doskada umuman chiqmaydi
                if (d.missingGroups.length) {
                    h += sec('<i class="bi bi-people-fill"></i>', "Guruh proyeksiyasi topilmagan (rejada bor, doskada yo'q)", d.missingGroups.length, '',
                        '<div class="mb-1 text-gray-500">Bu yo\'nalish+kurslarda ishchi rejada fan bor, lekin tasdiqlangan guruh (oqim) proyeksiyasi yo\'q — shuning uchun kartochkalar yaratilmaydi va KURS ro\'yxatida ko\'rinmaydi. Yechim: shu yo\'nalish+kurs uchun guruh proyeksiyasini yaratib tasdiqlang, so\'ng doskani qayta yarating.</div>' +
                        d.missingGroups.map(m => '<div>• <b>' + esc(m.specialty_name) + '</b> · ' + m.course + '-kurs</div>').join(''));
                }
                // Joylashmagan
                h += sec('<i class="bi bi-pin-angle-fill"></i>', 'Joylashmagan darslar', d.totalUnplaced, 'hammasi joyida',
                    Object.entries(d.unplacedBySpec).map(([k, v]) => '<div>' + esc(k) + ' — <b>' + v + '</b> karta</div>').join(''));
                // O'qituvchi konflikti
                h += sec('<i class="bi bi-person-workspace"></i>', "O'qituvchi to'qnashuvlari", d.teacherConf.length, 'to\'qnashuv yo\'q',
                    d.teacherConf.map(c => '<div>' + esc(d.dayName(c.d)) + ', ' + c.p + '-para — <b>' + esc(c.name || '') + '</b>: ' + esc(c.subs.join(' / ')) + '</div>').join(''));
                // Auditoriya konflikti
                h += sec('<i class="bi bi-door-open"></i>', 'Auditoriya to\'qnashuvlari', d.roomConf.length, 'to\'qnashuv yo\'q',
                    d.roomConf.map(c => '<div>' + esc(d.dayName(c.d)) + ', ' + c.p + '-para — <b>' + esc(c.name || '') + '</b>: ' + esc(c.subs.join(' / ')) + '</div>').join(''));
                // Oynalar
                h += sec('<i class="bi bi-layout-three-columns"></i>', 'Guruh oynalari (bo\'sh para)', d.gaps.length, 'oyna yo\'q',
                    d.gaps.slice(0, 40).map(g => '<div>' + esc(g.group) + ' · ' + esc(d.dayName(g.day)) + ' — <b>' + g.holes + '</b> oyna (paralar: ' + g.pairs.join(',') + ')</div>').join('') +
                    (d.gaps.length > 40 ? '<div class="text-gray-400">... yana ' + (d.gaps.length - 40) + '</div>' : ''));
                // O'qituvchisiz
                h += sec('<i class="bi bi-person-question"></i>', 'O\'qituvchisi biriktirilmagan birliklar', d.noTeacher.length, 'hammasiga biriktirilgan',
                    d.noTeacher.slice(0, 40).map(u => '<div>' + esc(u.spec) + ' · ' + u.course + '-kurs — ' + esc(u.sub) + '</div>').join('') +
                    (d.noTeacher.length > 40 ? '<div class="text-gray-400">... yana ' + (d.noTeacher.length - 40) + '</div>' : ''));

                const okAll = !d.totalUnplaced && !d.issues && !d.noTeacher.length;
                $('chkBody').innerHTML = (okAll
                    ? '<div class="mb-3 p-3 rounded bg-green-50 text-green-700 text-sm font-semibold">✓ Jadval to\'liq va konfliktsiz.</div>' : '') + h;
            }

            // ===== Dars turi filtri (Hammasi / Ma'ruza / Amaliy) =====
            document.querySelectorAll('.tt-type').forEach(b => b.onclick = () => {
                setTypeFilter(b.dataset.type);
                if (selected && !typeVisible(selected)) selected = null;   // filtrga mos kelmasa tanlovni bekor qilamiz
                renderAll();
            });

            // URLdan doska ochish (F5 / havola) — tanlovlar ham URLdan tiklanadi (applyPendingView)
            const urlBoard = new URLSearchParams(location.search).get('board');
            const notificationRequest = new URLSearchParams(location.search).get('change_request');
            if (urlBoard) loadBoard(urlBoard).then(() => {
                if (notificationRequest) openChangeReqModal();
            }).catch(e => {
                hideBoard();
                $('boardMsg').textContent = 'Doska ochilmadi: ' + e.message;
            });

        })();

        // Menyu va joylashmagan kartalar panelining balandligini sichqoncha bilan o'zgartirish:
        // ular kichraysa asosiy jadval (flex) shu hisobiga kengayadi. O'lcham shu brauzerda eslab qolinadi.
        (function () {
            const store = {
                get(k) { try { return JSON.parse(localStorage.getItem('tt-size-' + k)); } catch (e) { return null; } },
                set(k, v) { try { if (v === null) localStorage.removeItem('tt-size-' + k); else localStorage.setItem('tt-size-' + k, JSON.stringify(v)); } catch (e) { } },
            };
            const top = document.getElementById('ttTopArea');
            const panel = document.getElementById('cardPanel');
            const applyTop = h => {
                if (!top) return;
                if (h === null) { top.style.height = ''; top.style.overflow = ''; return; }
                top.style.height = h + 'px';
                top.style.overflow = 'auto';
            };
            const applyPanel = h => {
                if (!panel) return;
                if (h === null) { panel.style.maxHeight = '120px'; panel.style.height = ''; return; }
                panel.style.maxHeight = h + 'px';
                panel.style.height = h + 'px';
            };
            applyTop(store.get('top'));
            applyPanel(store.get('panel'));
            const drag = (handle, start, move, key) => {
                if (!handle) return;
                handle.addEventListener('pointerdown', ev => {
                    if (ev.button !== 0) return;
                    ev.preventDefault();
                    const y0 = ev.clientY, h0 = start();
                    let last = h0, moved = false;
                    handle.classList.add('dragging');
                    document.body.classList.add('tt-resizing');
                    handle.setPointerCapture(ev.pointerId);
                    // Oddiy bosish (yoki 1-2px titrash) o'lchamni qotirib qo'ymaydi — faqat haqiqiy surish eslab qolinadi.
                    const onMove = e => {
                        if (!moved && Math.abs(e.clientY - y0) < 3) return;
                        moved = true;
                        last = move(h0, e.clientY - y0);
                    };
                    const onUp = () => {
                        handle.classList.remove('dragging');
                        document.body.classList.remove('tt-resizing');
                        handle.removeEventListener('pointermove', onMove);
                        handle.removeEventListener('pointerup', onUp);
                        handle.removeEventListener('pointercancel', onUp);
                        if (moved) store.set(key, last === null ? null : Math.round(last));
                    };
                    handle.addEventListener('pointermove', onMove);
                    handle.addEventListener('pointerup', onUp);
                    handle.addEventListener('pointercancel', onUp);
                });
            };
            // Yuqori menyu: pastga sursangiz kattalashadi (tabiiy balandligidan oshmaydi), tepaga — kichrayadi.
            drag(document.getElementById('ttResizeTop'),
                () => top.getBoundingClientRect().height,
                (h0, dy) => {
                    const natural = top.scrollHeight;
                    const h = Math.max(0, Math.min(natural, h0 + dy));
                    // To'liq balandlik px bilan saqlanmaydi: menyu keyin kattalashsa ham skroll bilan kesilib qolmasin.
                    if (h >= natural - 1) { applyTop(null); return null; }
                    applyTop(h);
                    return h;
                }, 'top');
            // Joylashmagan kartalar: tepaga sursangiz kattalashadi, pastga — kichrayadi.
            drag(document.getElementById('ttResizeBottom'),
                () => panel.getBoundingClientRect().height,
                (h0, dy) => {
                    const h = Math.max(0, Math.min(window.innerHeight * 0.7, h0 - dy));
                    applyPanel(h);
                    return h;
                }, 'panel');
            const reset = (id, key, fn) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('dblclick', () => { fn(null); store.set(key, null); });
            };
            reset('ttResizeTop', 'top', applyTop);
            reset('ttResizeBottom', 'panel', applyPanel);
        })();
    </script>
</x-app-layout>
