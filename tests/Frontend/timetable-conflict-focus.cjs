const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Ko'rsatish": to'qnashuv havolasi bosilsa — guruh filtri vaqtincha to'qnashuvdagi guruhlarga (boshqa fakultet/yo'nalish/kurs —
// qamrov ham kengayadi), hafta/ko'rinish, qizil ramka, bir marta scroll, lenta; "Yopish" — oldingi holat. Huquq: "Butun doska"dan
// boshqa rejimda faqat yuklangan qamrovdagi guruhlar (qolganlari faqat matnda).
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };
const code = cut("            // \"Ko'rsatish\" havolasi bosilganda", "            // ===== To'qnashuvlar: qisqa belgi")
    + cut("            // ===== \"Ko'rsatish\": to'qnashuvni jadvalda ko'rsatish =====", "            // Xona sig'imi: kiritilmagan (0)")
    + cut('            const specCards = () => cards.filter(c =>', "            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)")
    + cut('            function buildGroupRows() {', '            // Karta uzunligi yarim-slot birligida');

// Sahifadagi esc (atribut ichidagi JSON brauzerda qayta ochiladi).
const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
assert(source.includes("const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');"));
const unesc = s => s.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');

// Soxta DOM
const classList = () => { const set = new Set(); return { set, add: (...c) => c.forEach(x => set.add(x)), remove: (...c) => c.forEach(x => set.delete(x)),
    contains: c => set.has(c), toggle: (c, on) => (on ? set.add(c) : set.delete(c)) }; };
const els = {};
const node = (id, extra = {}) => Object.assign({ id, innerHTML: '', value: '', dataset: {}, style: {}, classList: classList(), removed: false,
    remove() { this.removed = true; delete docById[this.id]; }, querySelector: () => null, querySelectorAll: () => [] }, extra);
const docById = {};
const $ = id => (els[id] = els[id] || node(id));
els.viewMode = node('viewMode', { options: [{ value: 'group' }, { value: 'teacher' }, { value: 'room' }, { value: 'cycle' }], value: 'teacher' });
els.mainArea = node('mainArea', { insertBefore(n) { docById[n.id] = n; n.removed = false; } });
const docListeners = [];
let gridChips = [];
const document = {
    getElementById: id => docById[id] || null,
    createElement: () => {
        const n = node('');
        const btn = node('ttFocusClose');
        n.querySelector = sel => (sel === '#ttFocusClose' ? btn : null);
        return n;
    },
    querySelectorAll: sel => (sel === '#grid [data-chip], #grid [data-cyc]' ? gridChips : []),
    addEventListener: (t, fn, capture) => docListeners.push([t, fn, capture]),
};
const calls = [];
const ctx = {
    $, esc, document, JSON, Set, String, Math, Object, Array,
    CF_LABEL: { teacher_busy: "O'qituvchi band", group_busy: 'Guruh band', room_capacity: "Xona sig'maydi", room_busy: 'Xona band' },
    board: { id: 7 }, scopeMode: 'all', cards: [], specList: [], viewMode: 'teacher', curWeek: 0, selected: null,
    selectedFaculties: new Set(), selectedDirs: new Set(), selectedCourses: new Set(), cycleRowFilters: [], cycleRowStoreKey: null,
    cyclePlanData: null, ttFocus: null, flowStudents: {}, groupRows: [],
    boardSchedule: () => [{ type: 'pair', start: '08:30' }, { type: 'break' }, { type: 'pair', start: '09:10' }, { type: 'pair', start: '10:00' }],
    isCycleCardAny: c => !!c.cycle,
    cycleRowStorageKey: () => 'tt:cycRows:KEY',
    reconcileSelection: seed => calls.push('reconcile:' + seed), fillSelectors: () => calls.push('fillSelectors'),
    fillGridInputs: () => calls.push('fillGridInputs'), weekNeedsReload: () => ctx.needReload === true,
    loadBoard: id => { calls.push('loadBoard:' + id); return Promise.resolve(); }, renderAll: () => { calls.push('renderAll'); ctx.api.build(); },
    ensureScopeLoaded: () => calls.push('ensureScopeLoaded'), closeCycDayPop: () => calls.push('closeCycDayPop'),
    closeCycleAssign: () => calls.push('closeCycleAssign'), alert: m => calls.push('alert:' + m),
    typeFilter: 'lecture', setTypeFilter: t => { ctx.typeFilter = t; },
    // Haftalik qatlam: tashqi ochiq — haftalik to'qnashuvda vaqtincha ichki (kartalar yuklangan qiymatlaridan, saqlanmaydi).
    weeklyLayer: 'external', allCards: [], layerApplied: [],
    applyWeeklyLayer: list => ctx.layerApplied.push(ctx.weeklyLayer + ':' + (list === ctx.allCards)),
    invalidateWeeklyConflicts() {}, renderWeeklyLayerUi() {},
    // Sikl qatlami: tashqi ochiq — sikl to'qnashuvida vaqtincha ichki (loadCyclePlan shu qatlam bilan so'raydi).
    cycleLayer: 'external', cycleLayerUi: 0, renderCycleLayerUi: j => { ctx.cycleLayerUi++; },
    CROSS_MODES: ['teacher', 'subject', 'room'], crossCycleShown: [],
    // Akademik soatlar (sahifadagi cycHourTimes kabi): to'liq parada bir qatorda ikki soat.
    cycHourTimes: () => [null, ['08:30', '09:10'], ['09:10', '09:50'], ['10:00', '10:40'], ['10:40', '11:20']],
};
els.wkLayerInternal = node('wkLayerInternal');
els.cycLayerInternal = node('cycLayerInternal');
vm.createContext(ctx);
vm.runInContext(code + '\nthis.api = { link: focusLinkHtml, links: focusLinksHtml, dates: focusDatesText, banner: focusBannerText, start: ttFocusStart,'
    + ' close: ttFocusClose, forget: ttFocusForget, discard: ttFocusDiscard, rows: focusCycleRowKeys, highlight: focusHighlight, visible: focusVisibleGroups,'
    + ' crossItems: focusCrossItems,'
    + ' build: () => { buildGroupRows(); return groupRows; } };', ctx);
const api = ctx.api;
const plain = v => JSON.parse(JSON.stringify(v));
const click = html => {
    const m = /data-focus="([^"]*)"/.exec(html);
    assert(m, 'havola yo\'q: ' + html);
    const target = { closest: sel => (sel === '[data-focus]' ? { dataset: { focus: unesc(m[1]) } } : null) };
    let prevented = 0, stopped = 0;
    docListeners.filter(l => l[0] === 'click').forEach(l => l[1]({ target, preventDefault: () => prevented++, stopPropagation: () => stopped++ }));
    assert.equal(prevented, 1);
    assert.equal(stopped, 1);
};
const colGroups = () => plain(api.build().map(r => r.group));

// Doska: F1 / Davolash ishi / 2-kurs (d2-01a, d2-02a) va boshqa fakultet F2 / Pediatriya / 3-kurs (p3-01b).
const card = (id, fac, spec, course, group, extra = {}) => Object.assign({ id, faculty_name: fac, specialty_name: spec, course, group_name: group,
    oqim_label: '1-oqim', training_type: 'practice', students: 10 }, extra);
ctx.cards = [card(1, 'F1', 'Davolash ishi', 2, 'd2-01a'), card(2, 'F2', 'Pediatriya', 3, 'p3-01b'), card(3, 'F1', 'Davolash ishi', 2, 'd2-02a')];
ctx.specList = [{ faculty: 'F1', specialty_name: 'Davolash ishi', course: 2 }, { faculty: 'F2', specialty_name: 'Pediatriya', course: 3 }];
ctx.selectedFaculties = new Set(['F1']); ctx.selectedDirs = new Set(['Davolash ishi']); ctx.selectedCourses = new Set([2]);
ctx.cycleRowFilters = ['k9'];
assert.deepEqual(colGroups(), ['d2-01a', 'd2-02a']);
// Tuzilgan ma'lumot (server): yozilishi kartadan farq qilsa ham (katta-kichik harf) — tanlov ro'yxatidagi qiymat olinadi.
const weekShow = { kind: 'teacher_busy', teacher_id: 15, teacher_name: 'USTOZ', room_code: null,
    groups: [{ group: 'd2-01a', faculty: 'F1', specialty: 'davolash ishi', course: 2 }, { group: 'p3-01b', faculty: 'F2', specialty: 'PEDIATRIYA', course: 3 }],
    subject: 'Anatomiya', dates: ['2026-11-13', '2026-11-13'], hour_from: 3, hour_to: 5, card_ids: [1, 2], view: 'week', week: 9 };

// 1) Havola: "Butun doska" — ikkala guruh; JSON atributda (matn parse qilinmaydi).
const html = api.link(weekShow);
assert.match(html, /^<a href="#" class="cf-show" data-focus="[^"]+" title="Jadvalda ko'rsatish: d2-01a, p3-01b">Ko'rsatish<\/a>$/);
assert.deepEqual(JSON.parse(unesc(/data-focus="([^"]*)"/.exec(html)[1])), weekShow);
assert.equal(api.link(null), '');
assert.equal(api.link({ groups: [] }), '');

// 2) Bosilganda: ikkala guruh filtrda (yonma-yon ustunlar), qamrov vaqtincha kengaygan, guruh kesimi, o'sha hafta; lenta
//    (vaqt — qo'ng'iroq jadvalining 3-para qatori, tanaffus hisobga olinmaydi).
calls.length = 0;
click(html);
assert.deepEqual(plain([...ctx.selectedFaculties]), ['F1', 'F2']);
assert.deepEqual(plain([...ctx.selectedDirs]), ['Davolash ishi', 'Pediatriya']);
assert.deepEqual(plain([...ctx.selectedCourses]), [2, 3]);
assert.equal(ctx.viewMode, 'group');
assert.equal(els.viewMode.value, 'group');
assert.equal(ctx.curWeek, 9);
assert.equal(ctx.typeFilter, 'all');   // "Ma'ruza" filtri vaqtincha olinadi — amaliy tomoni ham ko'rinsin
assert.equal(ctx.weeklyLayer, 'internal');
assert.deepEqual(plain(ctx.layerApplied), ['internal:true']);
assert.equal(ctx.cycleLayer, 'external');   // haftalik to'qnashuv — sikl qatlamiga tegilmaydi
assert.deepEqual(calls, ['closeCycDayPop', 'reconcile:false', 'fillSelectors', 'fillGridInputs', 'renderAll', 'ensureScopeLoaded']);
assert.deepEqual(colGroups(), ['d2-01a', 'p3-01b']);   // d2-02a — yashirin
const bar = docById.ttFocusBar;
assert(bar && !bar.removed);
assert.equal(bar.innerHTML, "<span>To'qnashuvni ko'rish: O'qituvchi USTOZ — d2-01a va p3-01b, 13.11, 10:00</span><span>·</span>"
    + '<button type="button" id="ttFocusClose">Yopish</button>');

// 3) Qizil ramka: to'qnashgan darslar (card_ids) kataklari; birinchisiga bir marta scroll.
const td = n => ({ n, classList: classList(), scrolls: 0, scrollIntoView(o) { this.scrolls++; this.opt = o; } });
const tds = [td(1), td(2), td(3), td(4), td(5)];
gridChips = [{ dataset: { chip: '1' }, closest: () => tds[0] }, { dataset: { chip: '3' }, closest: () => tds[2] },
    { dataset: { chip: '2' }, closest: () => tds[1] }, { dataset: { chip: '1' }, closest: () => tds[0] },
    // Birlashtirilgan blok: to'qnashgan karta (2) — birinchi emas, data-merge-ids ichida; bo'laklar sarlavhasi — data-parallel-ids.
    { dataset: { chip: '7', mergeIds: '7,2' }, closest: () => tds[3] }, { dataset: { chip: '8', parallelIds: '8,9' }, closest: () => tds[4] }];
api.highlight();
assert.deepEqual(tds.map(t => t.classList.contains('tt-focus-hit')), [true, true, false, true, false]);
gridChips[5].dataset.parallelIds = '8,1';
ctx.ttFocus.scrolled = true;
api.highlight();
assert.equal(tds[4].classList.contains('tt-focus-hit'), true);
assert.equal(tds[0].scrolls, 1);
assert.deepEqual(plain(tds[0].opt), { block: 'center', inline: 'center' });
api.highlight();   // qayta chizilganda ramka qoladi, scroll takrorlanmaydi
assert.equal(tds[0].scrolls, 1);

// 4) Yopish: oldingi qamrov, ko'rinish, hafta va sikl filtri tiklanadi; lenta yo'qoladi.
calls.length = 0;
bar.querySelector('#ttFocusClose').onclick();
assert.equal(ctx.ttFocus, null);
assert(bar.removed);
assert.deepEqual(plain([...ctx.selectedFaculties]), ['F1']);
assert.deepEqual(plain([...ctx.selectedDirs]), ['Davolash ishi']);
assert.deepEqual(plain([...ctx.selectedCourses]), [2]);
assert.equal(ctx.viewMode, 'teacher');
assert.equal(els.viewMode.value, 'teacher');
assert.equal(ctx.curWeek, 0);
assert.equal(ctx.typeFilter, 'lecture');
assert.equal(ctx.weeklyLayer, 'external');
assert.deepEqual(plain(ctx.layerApplied), ['internal:true', 'external:true']);
assert.deepEqual(plain(ctx.cycleRowFilters), ['k9']);
assert.equal(ctx.cycleRowStoreKey, 'tt:cycRows:KEY');   // saqlangandan qayta o'qilmaydi
assert.deepEqual(calls, ['reconcile:false', 'fillSelectors', 'fillGridInputs', 'renderAll', 'ensureScopeLoaded']);
assert.deepEqual(colGroups(), ['d2-01a', 'd2-02a']);

// 5) Fokusdan fokusga: Yopish eng boshidagi holatga qaytaradi; hafta yuklanmagan bo'lsa — doska qayta so'raladi.
click(html);
const roomShow = Object.assign({}, weekShow, { kind: 'room_busy', teacher_id: null, teacher_name: null, room_code: 'R215', week: 4,
    groups: [weekShow.groups[0], { group: 'd2-02a', faculty: 'F1', specialty: 'Davolash ishi', course: 2 }] });
calls.length = 0;
ctx.needReload = true;
click(api.link(roomShow));
assert.deepEqual(calls.slice(-2), ['fillGridInputs', 'loadBoard:7']);
ctx.needReload = false;
assert.equal(ctx.curWeek, 4);
assert.deepEqual(colGroups(), ['d2-01a', 'd2-02a']);
assert.match(docById.ttFocusBar.innerHTML, /To'qnashuvni ko'rish: Xona R215 — d2-01a va d2-02a, 13\.11, 10:00/);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();
assert.deepEqual(plain([...ctx.selectedFaculties]), ['F1']);
assert.equal(ctx.viewMode, 'teacher');
assert.equal(ctx.curWeek, 0);

// 6) Sikl: guruh, oqim va bo'lak ("Fan (a)") qatorlari a'zolari bo'yicha; boshqa yo'nalishdagi bir xil nom — yo'q; sikl kesimi, hafta o'zgarmaydi.
const cycleShow = { kind: 'teacher_busy', teacher_name: 'SHARIPOV K. SH.', groups: [{ group: 'd22-05a', faculty: 'F1', specialty: 'Davolash ishi', course: 2 },
    { group: 'd22-05b', faculty: 'F1', specialty: 'Davolash ishi', course: 2 }], dates: ['2026-09-16', '2026-09-18'], hour_from: 1, hour_to: 7,
    card_ids: [11, 12], view: 'cycle', week: null };
calls.length = 0;
click(api.link(cycleShow));
assert.equal(ctx.viewMode, 'cycle');
assert.equal(ctx.curWeek, 0);
assert.equal(ctx.weeklyLayer, 'external');   // sikl to'qnashuvi — haftalik qatlamga tegilmaydi
assert.equal(ctx.cycleLayer, 'internal');    // sikl to'qnashuvi ichki (kafedra) jadval bo'yicha
assert(ctx.cycleLayerUi > 0);
assert.match(docById.ttFocusBar.innerHTML, /O'qituvchi SHARIPOV K\. SH\. — d22-05a va d22-05b, 16–18\.09, 08:30/);
const rows = [
    { row_key: 'r1', group: 'd22-05a', faculty: 'F1', specialty: 'Davolash ishi', course: 2 },
    { row_key: 'r2', group: '1-oqim', subgroups: ['d22-05b + d22-05c'], faculty: 'F1', specialty: 'davolash ishi', course: 2 },
    { row_key: 'r3', group: 'd22-05c', faculty: 'F1', specialty: 'Davolash ishi', course: 2 },
    { row_key: 'r4', group: 'd22-05a', faculty: 'F1', specialty: 'Pediatriya', course: 2 },
    { row_key: 'v1', group: 'Fan (a)', variant: 'Fan (a)', subgroups: ['d22-05a'], faculty: 'F1', specialty: 'Davolash ishi', course: 2 },
];
assert.deepEqual(plain(api.rows(rows)), ['r1', 'r2', 'v1']);
// Guruh faqat bo'lak qatorida qatnashsa ham — filtr bo'sh qolmaydi (bo'sh filtr butun jadvalni ko'rsatardi).
assert.deepEqual(plain(api.rows([rows[2], rows[3], Object.assign({}, rows[4], { row_key: 'v2', subgroups: ['d22-05b'] })])), ['v2']);
// Manbada: render — fokusda saqlangan tanlov o'rniga shu qatorlar (saqlanmaydi); qo'lda tanlansa — fokus tugaydi.
assert.match(source, /if \(ttFocus && ttFocus\.show\.view === 'cycle'\) \{\n(?:\s+\/\/[^\n]*\n)?\s+cycleRowFilters = focusCycleRowKeys\(allRows\);/);
assert.match(source, /function cycleRowFiltersApply\(\) \{\n(?:\s+\/\/[^\n]*\n)?\s+if \(ttFocus && ttFocus\.show\.view === 'cycle'\) ttFocusForget\(\);\n\s+cycleRowFiltersSave\(\);/);
// Sikl qizil ramkasi: fokus qatorlarining to'qnashuv sanalariga tushadigan bloklari va sana sarlavhalari.
ctx.cycleRowFilters = ['r1', 'r2', 'v1'];
ctx.cyclePlanData = { dates: ['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19'].map(iso => ({ iso })) };
const block = (row, from, span) => ({ dataset: { cycleRow: row, cycleFrom: String(from), cycleSpan: String(span) }, classList: classList(),
    scrolls: 0, scrollIntoView() { this.scrolls++; } });
const blocks = [block('r1', 0, 5), block('r2', 5, 1), block('r3', 2, 3), block('r2', 0, 2), block('v1', 3, 2)];
const ths = ctx.cyclePlanData.dates.map(() => ({ classList: classList() }));
els.cycleGrid = node('cycleGrid', { querySelectorAll: sel => (sel === 'td.cyc-block[data-cycle-row]' ? blocks : sel === 'thead th.cyc-dcol' ? ths : []) });
api.highlight();
assert.deepEqual(blocks.map(b => b.classList.contains('tt-focus-hit')), [true, false, false, false, true]);
assert.deepEqual(ths.map(t => t.classList.contains('tt-focus-date')), [false, false, true, true, true, false]);
assert.equal(blocks[0].scrolls, 1);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();
assert.deepEqual(plain(ctx.cycleRowFilters), ['k9']);
assert.equal(ctx.viewMode, 'teacher');
assert.equal(ctx.cycleLayer, 'external');

// 6b) Boshqa doska ochilsa / doska yopilsa: fokus tashlanadi (lenta yo'q, filtr qo'llanmaydi); kesim, dars turi va qatlamlar —
//     oldingisi; qamrov va hafta o'zgartirilmaydi (yangi doskaniki).
click(api.link(cycleShow));
assert.equal(ctx.cycleLayer, 'internal');
ctx.selectedFaculties = new Set(['YANGI']);
api.discard();
assert.equal(ctx.ttFocus, null);
assert.equal(docById.ttFocusBar, undefined);
assert.equal(ctx.viewMode, 'teacher');
assert.equal(els.viewMode.value, 'teacher');
assert.equal(ctx.typeFilter, 'lecture');
assert.equal(ctx.cycleLayer, 'external');
assert.deepEqual(plain([...ctx.selectedFaculties]), ['YANGI']);
click(html);   // haftalik: ichki qatlam — tashlanganda qaytadi
api.discard();
assert.equal(ctx.weeklyLayer, 'external');
assert.deepEqual(colGroups(), ['d2-01a', 'd2-02a', 'p3-01b']);   // guruh filtri endi qo'llanmaydi (qamrovni yangi doska qo'yadi)
api.discard();   // fokus yo'q — hech narsa
assert.equal(ctx.viewMode, 'teacher');
ctx.selectedFaculties = new Set(['F1']); ctx.selectedDirs = new Set(['Davolash ishi']); ctx.selectedCourses = new Set([2]); ctx.curWeek = 0;

// 6c) Aralash (haftalik + sikl) to'qnashuv: haftalik o'qituvchi (yoki xona) kesimi, haftalik tomonning haftasi, ichki qatlam;
//     kesimda faqat shu o'qituvchi / xona; qizil ramka — haftalik karta va shu kartaning to'qnashuv sanasidagi sikl darsi.
const mixed = { kind: 'teacher_busy', teacher_id: 15, teacher_name: 'USTOZ B', groups: [weekShow.groups[0],
    { group: 'd2-05c', faculty: 'F1', specialty: 'Davolash ishi', course: 2 }], dates: ['2026-11-13', '2026-11-13'], hour_from: 1,
    hour_to: 3, card_ids: [1, 31], view: 'cross', week: 9 };
click(api.link(mixed));
assert.equal(ctx.viewMode, 'teacher');
assert.equal(els.viewMode.value, 'teacher');
assert.equal(ctx.curWeek, 9);
assert.equal(ctx.weeklyLayer, 'internal');
assert.equal(ctx.cycleLayer, 'external');
const it = (x, isCyc) => (isCyc ? { l: x } : { c: x });
assert.deepEqual(plain(api.crossItems('teacher', [it({ teacher_id: 15 }), it({ teacher_id: 16 }, true), it({ teacher_id: 15 }, true)])),
    [{ c: { teacher_id: 15 } }, { l: { teacher_id: 15 } }]);
// Biriktirishdan oldin: biriktirilayotgan dars (card_ids) hali o'qituvchisiz yoki boshqa o'qituvchida — u ham ko'rinadi.
assert.deepEqual(plain(api.crossItems('teacher', [it({ id: 1, teacher_id: null }), it({ id: 31, teacher_id: 16 }, true), it({ id: 7, teacher_id: 16 })])),
    [{ c: { id: 1, teacher_id: null } }, { l: { id: 31, teacher_id: 16 } }]);
assert.equal(api.crossItems('subject', [it({ teacher_id: 16 })]).length, 1);
ctx.crossCycleShown = [{ id: 31, date: '2026-11-13' }, { id: 31, date: '2026-11-14' }, { id: 32, date: '2026-11-13' }];
const ctd = [td(1), td(2), td(3), td(4), td(5)];
gridChips = [{ dataset: { chip: '1' }, closest: () => ctd[0] }, { dataset: { cyc: '0' }, closest: () => ctd[1] },
    { dataset: { cyc: '1' }, closest: () => ctd[2] }, { dataset: { cyc: '2' }, closest: () => ctd[3] }, { dataset: { chip: '2' }, closest: () => ctd[4] }];
api.highlight();
assert.deepEqual(ctd.map(t => t.classList.contains('tt-focus-hit')), [true, true, false, false, false]);
assert.equal(ctd[0].scrolls, 1);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();
assert.equal(ctx.viewMode, 'teacher');
assert.equal(ctx.weeklyLayer, 'external');
assert.equal(ctx.curWeek, 0);
// Xona aralash to'qnashuvi — xona kesimi, faqat shu xona.
click(api.link(Object.assign({}, mixed, { kind: 'room_busy', teacher_id: null, room_code: 'R102' })));
assert.equal(ctx.viewMode, 'room');
assert.deepEqual(plain(api.crossItems('room', [it({ auditorium_code: 'R102' }), it({ auditorium_code: 'R101' }, true)])), [{ c: { auditorium_code: 'R102' } }]);
assert.deepEqual(plain(api.crossItems('room', [it({ id: 1, auditorium_code: null }), it({ id: 8, auditorium_code: 'R101' })])),
    [{ c: { id: 1, auditorium_code: null } }]);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();
assert.equal(ctx.viewMode, 'teacher');
// Oddiy (haftalik) fokusda va fokussiz kesim filtri qo'llanmaydi.
assert.equal(api.crossItems('teacher', [it({ teacher_id: 16 })]).length, 1);
click(html);
assert.equal(api.crossItems('teacher', [it({ teacher_id: 16 })]).length, 1);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();

// 7) Huquq: "Faqat meniki" / "Kafedraniki" — faqat yuklangan qamrovdagi guruhlar; qolgani faqat matnda; hech biri yo'q — havola yo'q.
ctx.scopeMode = 'mine';
ctx.cards = [card(1, 'F1', 'Davolash ishi', 2, 'd2-01a')];
const own = api.link(weekShow);
assert.match(own, /title="Jadvalda ko'rsatish: d2-01a">/);
click(own);
assert.deepEqual(plain([...ctx.selectedFaculties]), ['F1']);   // boshqa fakultet qo'shilmaydi
assert.match(docById.ttFocusBar.innerHTML, /O'qituvchi USTOZ — d2-01a, 13\.11/);
assert.deepEqual(colGroups(), ['d2-01a']);
docById.ttFocusBar.querySelector('#ttFocusClose').onclick();
assert.equal(api.link({ groups: [{ group: 'p3-01b', faculty: 'F2', specialty: 'Pediatriya', course: 3 }], dates: [] }), '');
// "A + B" umumiy karta — har bir guruh ko'rinadi.
ctx.cards = [card(5, 'F1', 'Davolash ishi', 2, 'd2-03a + d2-03b')];
assert.deepEqual(plain(api.visible({ groups: [{ group: 'd2-03b', faculty: 'F1', specialty: 'Davolash ishi', course: 2 }] }).map(g => g.group)), ['d2-03b']);
ctx.scopeMode = 'all';

// 8) Bir nechta to'qnashuv (biriktirish ogohlantirishi, Takliflar): har biriga — guruhlar va sanalar bilan.
assert.equal(api.links([]), '');
assert.equal(api.links(null), '');
assert.match(api.links([weekShow]), /^ <a [^>]+>Ko'rsatish<\/a>$/);
const two = api.links([weekShow, Object.assign({}, cycleShow)]);
assert.match(two, /^ <a [^>]+>Ko'rsatish: d2-01a, p3-01b, 13\.11<\/a> <a [^>]+>Ko'rsatish: d22-05a, d22-05b, 16–18\.09<\/a>$/);

// 9) Sanalar: bir oy — "13–24.11"; oylar har xil — "28.10–03.11"; bitta kun — "15.09".
assert.equal(api.dates(['2026-11-13', '2026-11-24']), '13–24.11');
assert.equal(api.dates(['2026-10-28', '2026-11-03']), '28.10–03.11');
assert.equal(api.dates(['2026-09-15', '2026-09-15']), '15.09');
assert.equal(api.dates(['2026-09-15']), '15.09');
assert.equal(api.dates([]), '');

// 9b) Lentadagi vaqt: hour_unit "row" — qo'ng'iroq jadvali qatori (tanaffus hisobga olinmaydi), "hour" — akademik soat.
const tb = x => api.banner(Object.assign({ kind: 'group_busy', dates: ['2026-09-16'] }, x), [{ group: 'g1' }]);
assert.equal(tb({ hour_from: 3, hour_unit: 'row' }), "To'qnashuvni ko'rish: Guruh band — g1, 16.09, 10:00");
assert.equal(tb({ hour_from: 4, hour_unit: 'hour' }), "To'qnashuvni ko'rish: Guruh band — g1, 16.09, 10:40");
assert.equal(tb({ hour_from: 4 }), "To'qnashuvni ko'rish: Guruh band — g1, 16.09");   // qator yo'q — vaqtsiz
assert.equal(tb({ hour_from: 9, hour_unit: 'hour' }), "To'qnashuvni ko'rish: Guruh band — g1, 16.09");

// 10) Havolalar manbada: to'qnashuv qatori, biriktirish ogohlantirishi (409 conflict_items), band o'qituvchi tanlovi, Takliflar.
assert.match(source, /: ' <span class="cf-todo">→ ' \+ esc\(i\.todo \|\| CF_TODO\[i\.kind\] \|\| ''\) \+ '<\/span>'\) \+ focusLinkHtml\(i\.show\) \+ '<\/li>';/);
assert.match(source, /'<div style="color:#b45309">⚠ ' \+ esc\(message\) \+ focusLinksHtml\(items\) \+ '<\/div>'/);
assert.equal((source.match(/showBusyConfirm\([^\n]*, e\.data\.conflict_items\);/g) || []).length, 3);
assert.match(source, /esc\(c\.reason\) \+ focusLinksHtml\(c\.clashes\) \+ '<\/div>'/);
assert.match(source, /focusLinksHtml\(cycAssignBusy\.items\[id\]\)/);
assert.match(source, /focusLinksHtml\(\(\(asgBusy\.items \|\| \{\}\)\.teachers \|\| \{\}\)\[id\]\)/);
assert.match(source, /groupRows = focusWeekColumns\(groupRows\);/);
assert.match(source, /const items = focusCrossItems\(mode, placed\.map\(c => \{/);
assert.match(source, /\$\('grid'\)\.innerHTML = h;\n\s+activeCell = null;\n\s+focusHighlight\(\);\n\s+\}\n\s+\/\/ Sikl darsi oynasi \(kesimlarda\)/);
assert.match(source, /if \(switching\) \{ ttFocusDiscard\(\); curSpec = null;/);
assert.match(source, /function hideBoard\(\) \{\n[^\n]*\n\s+ttFocusDiscard\(\);/);

// 11) Sikl jadvali yuklanishi: odatda eski scroll joyi tiklanadi; fokusning birinchi scroll'i qilingan chizishda — tiklanmaydi.
(async () => {
    const gw = { scrollTop: 40, scrollLeft: 300 };
    const raf = [];
    const els2 = { cycleGridWrap: gw };
    const el2 = id => (els2[id] = els2[id] || { id, value: '', textContent: '', classList: { add() {}, remove() {} }, removeAttribute() {} });
    const c2 = { $: el2, esc, BASE: '/admin', board: { id: 7 }, scopeMode: 'all', cycleHolidays: [], cycleViewMode: 'group', ttFocus: null, cyclePlanData: null,
        loadCycleOverlapUi() {}, scopeBody: () => ({}), cycleLayerParams: () => ({}), invalidateCycleWeek() {}, buildSubjectColors() {},
        colorScopeCards: () => [], renderCycleLayerUi() {}, renderHolChips() {}, renderCycleCards() {}, renderStats() {},
        api: async () => ({ rows: [], subjects: [], holidays: [] }), requestAnimationFrame: fn => raf.push(fn),
        // Haqiqiydagi kabi: chizishda fokus bo'lsa — to'qnashuvga scroll (focusHighlight).
        renderCyclePlan() { if (c2.ttFocus && !c2.ttFocus.scrolled) { c2.ttFocus.scrolled = true; gw.scrollTop = 900; gw.scrollLeft = 2400; } },
        window: { scrollX: 0, scrollY: 0, scrollTo() {} } };
    c2.window.scrollTo = () => {};
    vm.createContext(c2);
    vm.runInContext(cut('            async function loadCyclePlan(options = {}) {', '            function cycleMemberParts(name) {')
        + '\nthis.load = loadCyclePlan;', c2);
    await c2.load();   // fokus yo'q — joy tiklanadi
    raf.forEach(fn => fn());
    assert.deepEqual([gw.scrollTop, gw.scrollLeft], [40, 300]);
    c2.ttFocus = { scrolled: false };
    raf.length = 0;
    await c2.load();   // fokus: to'qnashuvga scroll — bekor qilinmaydi
    raf.forEach(fn => fn());
    assert.deepEqual([gw.scrollTop, gw.scrollLeft], [900, 2400]);
    gw.scrollTop = 50; gw.scrollLeft = 60;
    raf.length = 0;
    await c2.load();   // keyingi chizishlar (scroll bir marta bo'lgan) — odatdagidek tiklanadi
    gw.scrollTop = 1; gw.scrollLeft = 2;
    raf.forEach(fn => fn());
    assert.deepEqual([gw.scrollTop, gw.scrollLeft], [50, 60]);
    console.log('timetable conflict focus: 11 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
