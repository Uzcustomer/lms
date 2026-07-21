<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dars jadvali tuzish</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            {{-- Doska tanlash / yaratish --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-4">
                <div class="p-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-[320px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Jadval doskasi</label>
                        <select id="boardSel" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Tanlang yoki yangi yarating —</option>
                            @foreach($boards as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->cards_count }} karta)</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" id="newBoardBtn" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">+ Yangi doska</button>
                    <button type="button" id="genBtn" class="hidden px-3 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">⚙ Kartochkalarni yaratish</button>
                    <button type="button" id="delBoardBtn" class="hidden px-3 py-2 text-sm bg-red-50 text-red-600 rounded-md hover:bg-red-100">O'chirish</button>
                    <span id="boardMsg" class="text-sm"></span>
                </div>

                {{-- Yangi doska formasi --}}
                <div id="newBoardForm" class="hidden border-t border-gray-100 p-4 grid grid-cols-2 md:grid-cols-7 gap-3 items-end bg-gray-50">
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
                        <input type="number" id="nbWeeks" value="18" min="1" max="30" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-7">
                        <button type="button" id="createBoardBtn" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Yaratish</button>
                        <span class="ml-2 text-xs text-gray-500">Bu sukut sozlamalar — har yo'nalish+kurs uchun keyin alohida o'zgartiriladi. Doska yaratilgach "Kartochkalarni yaratish" bosiladi.</span>
                    </div>
                </div>
            </div>

            {{-- Yo'nalish tanlash + statistika + shu yo'nalish uchun panjara sozlamasi --}}
            <div id="specBar" class="hidden bg-white shadow-sm sm:rounded-lg mb-4 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[300px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Yo'nalish · kurs</label>
                        <select id="specSel" class="w-full rounded-md border-gray-300 shadow-sm text-sm"></select>
                    </div>
                    <div class="flex items-end gap-2 rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2">
                        <div>
                            <label class="block text-[10px] font-medium text-indigo-600 mb-0.5">Kunlar</label>
                            <input type="number" id="gsDays" min="1" max="7" class="w-16 rounded border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-indigo-600 mb-0.5">Kuniga para</label>
                            <input type="number" id="gsPairs" min="1" max="10" class="w-16 rounded border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-indigo-600 mb-0.5">Hafta soni</label>
                            <input type="number" id="gsWeeks" min="1" max="30" class="w-16 rounded border-gray-300 text-sm">
                        </div>
                        <button type="button" id="gsSave" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Saqlash</button>
                        <span class="text-[10px] text-indigo-400 max-w-[160px] leading-tight">Faqat shu yo'nalish+kursga. Hafta soni o'zgarsa kartalar qayta yaratiladi.</span>
                    </div>
                    <div id="statChips" class="flex flex-wrap gap-2 text-xs"></div>
                </div>
                <div class="mt-2 text-xs text-gray-400">
                    Kartani bosing → yashil katakni bosing. Joylashgan kartani bosib olib tashlash/ko'chirish/o'qituvchi-xona biriktirish mumkin.
                </div>
            </div>

            {{-- Asosiy maydon: panel + grid --}}
            <div id="mainArea" class="hidden flex gap-4 items-start">
                {{-- Joylashtirilmagan kartochkalar --}}
                <div class="w-72 shrink-0 bg-white shadow-sm sm:rounded-lg">
                    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Joylashmagan kartalar</span>
                        <span id="unplacedCount" class="text-xs font-bold text-amber-600"></span>
                    </div>
                    <div id="cardPanel" class="p-2 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 260px);"></div>
                </div>

                {{-- Panjara --}}
                <div class="flex-1 bg-white shadow-sm sm:rounded-lg overflow-auto" style="max-height: calc(100vh - 220px);">
                    <table id="grid" class="border-collapse text-[11px] w-full"></table>
                </div>
            </div>

            {{-- Kartochka rekvizitlari modali --}}
            <div id="cardModal" class="hidden fixed inset-0 z-50 bg-black/40">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
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
                            <div id="cmMsg" class="hidden text-sm rounded px-3 py-2"></div>
                        </div>
                        <div class="flex justify-between gap-2 px-5 py-3 border-t bg-gray-50 rounded-b-lg">
                            <button type="button" id="cmUnplace" class="px-3 py-1.5 text-sm bg-amber-50 text-amber-700 rounded-md hover:bg-amber-100">↩ Jadvaldan olish</button>
                            <div class="flex gap-2">
                                <button type="button" id="cmCancel" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700">Yopish</button>
                                <button type="button" id="cmSave" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Saqlash</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        #grid th, #grid td { border: 1px solid #e2e8f0; }
        #grid td.tt-cell { min-width: 92px; height: 44px; vertical-align: top; cursor: default; padding: 1px; }
        #grid td.tt-ok { background: #dcfce7; cursor: pointer; }
        #grid td.tt-bad { background: #fee2e2; }
        .tt-chip { border-radius: 5px; padding: 2px 4px; margin: 1px 0; font-size: 10px; line-height: 1.25; cursor: pointer; }
        .tt-chip.lec { background: #dbeafe; border-left: 3px solid #2563eb; }
        .tt-chip.prc { background: #f3e8ff; border-left: 3px solid #9333ea; }
        .tt-chip.sel { outline: 2px solid #f59e0b; }
        .pn-card { border-radius: 6px; padding: 4px 6px; font-size: 11px; cursor: pointer; border: 1px solid #e2e8f0; }
        .pn-card.lec { background: #eff6ff; }
        .pn-card.prc { background: #faf5ff; }
        .pn-card.sel { outline: 2px solid #f59e0b; }
        .lang-rus { box-shadow: inset 0 0 0 1px #fca5a5; }
        .lang-ing { box-shadow: inset 0 0 0 1px #86efac; }
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
            let curSpec = null;    // tanlangan {specialty_name, course}
            let groupRows = [];    // [{oqim_label, lang, group}]
            let selected = null;   // tanlangan karta (obyekt)
            let audCache = null;
            let modalCard = null;

            const $ = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

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

            function hideBoard() {
                board = null;
                $('genBtn').classList.add('hidden'); $('delBoardBtn').classList.add('hidden');
                $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
            }

            async function loadBoard(id) {
                // Boshqa doskaga o'tayotgan bo'lsak — eski doskaga oid holatni tozalaymiz
                const switching = !board || String(board.id) !== String(id);
                const j = await api(BASE + '/boards/' + id + '/data');
                board = j.board; cards = j.cards;
                grids = {};
                (j.grids || []).forEach(g => { grids[g.specialty_name + '|' + g.course] = g; });
                // Eski kartaga ishora qiluvchi tanlovlarni bekor qilamiz (eski doskaga yozib
                // yubormaslik uchun); doska almashsa yo'nalish tanlovini ham qayta tanlaymiz
                selected = null; modalCard = null;
                $('cardModal').classList.add('hidden');
                if (switching) curSpec = null;
                $('boardSel').value = String(board.id);
                $('genBtn').classList.remove('hidden');
                $('delBoardBtn').classList.remove('hidden');
                buildSpecList();
                if (!cards.length) {
                    $('specBar').classList.add('hidden'); $('mainArea').classList.add('hidden');
                    $('boardMsg').textContent = 'Kartochkalar hali yaratilmagan — "Kartochkalarni yaratish"ni bosing.';
                    return;
                }
                $('boardMsg').textContent = '';
                $('specBar').classList.remove('hidden'); $('mainArea').classList.remove('hidden');
                if ((!curSpec || !specList.find(s => s.key === curSpec.key)) && specList.length) curSpec = specList[0];
                if (curSpec) $('specSel').value = curSpec.key;
                fillGridInputs();
                renderAll();
            }

            function buildSpecList() {
                const seen = {};
                specList = [];
                cards.forEach(c => {
                    const k = c.specialty_name + '|' + c.course;
                    if (!seen[k]) { seen[k] = 1; specList.push({ key: k, specialty_name: c.specialty_name, course: c.course }); }
                });
                specList.sort((a, b) => (a.specialty_name + a.course).localeCompare(b.specialty_name + b.course));
                $('specSel').innerHTML = specList.map(s =>
                    '<option value="' + esc(s.key) + '">' + esc(s.specialty_name) + ' · ' + s.course + '-kurs</option>').join('');
                if (curSpec) $('specSel').value = curSpec.key;
            }
            $('specSel').onchange = function () {
                curSpec = specList.find(s => s.key === this.value) || null;
                selected = null;
                fillGridInputs();
                renderAll();
            };

            // ===== Panjara sozlamasi (yo'nalish+kurs bo'yicha) =====
            function curGrid() {
                const g = curSpec && grids[curSpec.specialty_name + '|' + curSpec.course];
                return g || { days: board.days, pairs_per_day: board.pairs_per_day, weeks: board.weeks };
            }
            function fillGridInputs() {
                const g = curGrid();
                $('gsDays').value = g.days; $('gsPairs').value = g.pairs_per_day; $('gsWeeks').value = g.weeks;
            }
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

            // ===== Yordamchilar =====
            const specCards = () => cards.filter(c => curSpec && c.specialty_name === curSpec.specialty_name && c.course === curSpec.course);
            const cardGroups = c => c.training_type === 'lecture' ? (c.group_names || []) : (c.group_name ? [c.group_name] : []);

            function buildGroupRows() {
                groupRows = [];
                const seen = {};
                specCards().forEach(c => {
                    cardGroups(c).forEach(g => {
                        if (!seen[g]) { seen[g] = 1; groupRows.push({ oqim_label: c.oqim_label || '', lang: c.lang || 'uz', group: g }); }
                    });
                });
                groupRows.sort((a, b) => (a.oqim_label + a.group).localeCompare(b.oqim_label + b.group, undefined, { numeric: true }));
            }

            // Konflikt: karta (day,pair) ga qo'yilsa — sabablar ro'yxati
            function conflictsAt(card, day, pair) {
                const my = cardGroups(card);
                const errs = [];
                cards.forEach(o => {
                    if (o.id === card.id || o.day !== day || o.pair !== pair) return;
                    if (o.specialty_name === card.specialty_name && o.course === card.course) {
                        const ov = cardGroups(o).filter(g => my.includes(g));
                        if (ov.length) errs.push('Guruh band: ' + ov.join(','));
                    }
                    if (card.teacher_id && o.teacher_id === card.teacher_id) errs.push("O'qituvchi band: " + o.teacher_name);
                    if (card.auditorium_code && o.auditorium_code === card.auditorium_code) errs.push('Auditoriya band: ' + o.auditorium_name);
                });
                return errs;
            }

            // ===== Render =====
            function renderAll() { buildGroupRows(); renderPanel(); renderGrid(); renderStats(); }

            function renderStats() {
                const sc = specCards();
                const placed = sc.filter(c => c.day).length;
                const totPlaced = cards.filter(c => c.day).length;
                $('statChips').innerHTML =
                    '<span class="rounded-md px-2 py-1 bg-green-50 text-green-700">Joylashgan: <b>' + placed + '/' + sc.length + '</b></span>' +
                    '<span class="rounded-md px-2 py-1 bg-gray-100 text-gray-600">Doska bo\'yicha: <b>' + totPlaced + '/' + cards.length + '</b></span>';
                $('unplacedCount').textContent = (sc.length - placed) + ' ta';
            }

            function cardLabel(c, short) {
                const t = c.training_type === 'lecture' ? 'M' : 'A';
                const name = short && c.subject_name.length > 26 ? c.subject_name.slice(0, 26) + '…' : c.subject_name;
                return '<b>[' + t + ']</b> ' + esc(name);
            }

            function renderPanel() {
                const un = specCards().filter(c => !c.day);
                // Fan bo'yicha guruhlash
                const bySubj = {};
                un.forEach(c => { (bySubj[c.subject_name] = bySubj[c.subject_name] || []).push(c); });
                $('cardPanel').innerHTML = Object.keys(bySubj).sort().map(subj => {
                    const list = bySubj[subj];
                    return '<details open><summary class="text-[11px] font-semibold text-gray-600 cursor-pointer py-0.5">' +
                        esc(subj.length > 34 ? subj.slice(0, 34) + '…' : subj) + ' <span class="text-gray-400">(' + list.length + ')</span></summary>' +
                        list.map(c =>
                            '<div class="pn-card ' + (c.training_type === 'lecture' ? 'lec' : 'prc') + (selected && selected.id === c.id ? ' sel' : '') +
                            ' lang-' + (c.lang || 'uz') + '" data-id="' + c.id + '">' +
                            cardLabel(c, true) +
                            '<div class="text-[10px] text-gray-500">' +
                            (c.training_type === 'lecture'
                                ? esc(c.oqim_label || 'oqim') + ' · ' + (c.group_names || []).length + ' guruh · ' + c.students + ' t.'
                                : esc(c.group_name || '') + ' · ' + c.students + ' t.') +
                            (c.teacher_name ? ' · 👤' : '') + (c.auditorium_name ? ' · 🚪' : '') +
                            '</div></div>'
                        ).join('') + '</details>';
                }).join('') || '<div class="text-xs text-gray-400 p-2">Hammasi joylashgan 🎉</div>';

                document.querySelectorAll('.pn-card').forEach(el => el.onclick = () => {
                    const c = cards.find(x => x.id === +el.dataset.id);
                    selected = (selected && selected.id === c.id) ? null : c;
                    renderPanel(); renderGrid();
                });
            }

            function renderGrid() {
                const g = curGrid();
                const D = g.days, P = g.pairs_per_day;
                let h = '<thead><tr><th class="bg-gray-50 px-2 py-1 sticky left-0 z-10">Guruh</th>';
                for (let d = 1; d <= D; d++) h += '<th colspan="' + P + '" class="bg-gray-100 px-2 py-1">' + DAY_NAMES[d - 1] + '</th>';
                h += '</tr><tr><th class="bg-gray-50 sticky left-0 z-10"></th>';
                for (let d = 1; d <= D; d++) for (let p = 1; p <= P; p++)
                    h += '<th class="bg-gray-50 px-1 py-0.5 text-gray-500 font-normal">' + p + '</th>';
                h += '</tr></thead><tbody>';

                // Joylashgan kartalar indeksi: group|day|pair → [卡]
                const placedIdx = {};
                specCards().filter(c => c.day).forEach(c => {
                    cardGroups(c).forEach(g => {
                        const k = g + '|' + c.day + '|' + c.pair;
                        (placedIdx[k] = placedIdx[k] || []).push(c);
                    });
                });

                let lastOqim = null;
                groupRows.forEach(gr => {
                    h += '<tr>';
                    const oqimBadge = gr.oqim_label && gr.oqim_label !== lastOqim
                        ? '<span class="text-[9px] text-blue-500 font-bold mr-1">' + esc(gr.oqim_label) + '</span>' : '';
                    lastOqim = gr.oqim_label;
                    h += '<td class="bg-white px-2 py-1 font-semibold text-gray-700 whitespace-nowrap sticky left-0 z-10">' + oqimBadge + esc(gr.group) + '</td>';
                    for (let d = 1; d <= D; d++) {
                        for (let p = 1; p <= P; p++) {
                            const list = placedIdx[gr.group + '|' + d + '|' + p] || [];
                            let cls = 'tt-cell';
                            let clickable = '';
                            if (selected && !list.length && cardGroups(selected).includes(gr.group)) {
                                const errs = conflictsAt(selected, d, p);
                                if (errs.length) { cls += ' tt-bad'; }
                                else { cls += ' tt-ok'; clickable = ' data-place="' + d + '-' + p + '"'; }
                            }
                            h += '<td class="' + cls + '"' + clickable + '>';
                            list.forEach(c => {
                                h += '<div class="tt-chip ' + (c.training_type === 'lecture' ? 'lec' : 'prc') +
                                    (selected && selected.id === c.id ? ' sel' : '') + '" data-chip="' + c.id + '" title="' +
                                    esc(c.subject_name + (c.teacher_name ? ' · ' + c.teacher_name : '') + (c.auditorium_name ? ' · ' + c.auditorium_name : '')) + '">' +
                                    cardLabel(c, true) +
                                    (c.teacher_name ? '<div class="text-[9px] text-gray-500">' + esc(c.teacher_name) + '</div>' : '') +
                                    (c.auditorium_name ? '<div class="text-[9px] text-gray-400">' + esc(c.auditorium_name) + '</div>' : '') +
                                    '</div>';
                            });
                            h += '</td>';
                        }
                    }
                    h += '</tr>';
                });
                h += '</tbody>';
                $('grid').innerHTML = h;

                // Yashil katakni bosish — joylash
                document.querySelectorAll('[data-place]').forEach(td => td.onclick = async () => {
                    if (!selected) return;
                    const [d, p] = td.dataset.place.split('-').map(Number);
                    try {
                        await api(BASE + '/cards/' + selected.id + '/place', 'POST', { day: d, pair: p });
                        selected.day = d; selected.pair = p;
                        selected = null;
                        renderAll();
                    } catch (e) { alert('Konflikt: ' + e.message); }
                });

                // Joylashgan chipni bosish — tanlash + modal
                document.querySelectorAll('[data-chip]').forEach(el => el.onclick = (ev) => {
                    ev.stopPropagation();
                    const c = cards.find(x => x.id === +el.dataset.chip);
                    selected = c;
                    openModal(c);
                    renderPanel(); renderGrid();
                });
            }

            // ===== Kartochka modali (o'qituvchi/auditoriya) =====
            async function openModal(c) {
                modalCard = c;
                $('cmTitle').textContent = c.subject_name;
                $('cmSub').textContent = (c.training_type === 'lecture' ? "Ma'ruza · " + (c.oqim_label || '') : 'Amaliy · ' + (c.group_name || '')) +
                    ' · ' + c.students + ' talaba' + (c.kafedra_name ? ' · ' + c.kafedra_name : '');
                $('cmCap').textContent = '(kamida ' + c.students + ' o\'rin)';
                $('cmMsg').classList.add('hidden');
                $('cardModal').classList.remove('hidden');
                await Promise.all([loadTeachers(''), loadAuds()]);
            }
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
                    });
                    modalCard.teacher_id = $('cmTeacher').value ? +$('cmTeacher').value : null;
                    modalCard.teacher_name = j.teacher_name;
                    modalCard.auditorium_code = j.auditorium_code;
                    modalCard.auditorium_name = j.auditorium_name;
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
                await api(BASE + '/cards/' + modalCard.id + '/place', 'POST', {});
                modalCard.day = null; modalCard.pair = null;
                $('cardModal').classList.add('hidden'); modalCard = null; selected = null;
                renderAll();
            };

            // URLdan doska ochish
            const urlBoard = new URLSearchParams(location.search).get('board');
            if (urlBoard) loadBoard(urlBoard);
        })();
    </script>
</x-app-layout>
