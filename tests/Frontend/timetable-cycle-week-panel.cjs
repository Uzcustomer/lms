const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };

// Sikl paneli hafta tanloviga bog'liq: 8-hafta (02.11–07.11) tanlanganda 13.11 dan boshlanadigan to'qnashuvli karta panelda yo'q,
// "Butun semestr"da bor; hisoblagichlar tanlovga mos; joylangan + to'qnashuvli karta holati; ko'p guruh filtri panelga ham;
// jadvalda hafta ustunlari ajratiladi.
const cls = () => { const s = new Set(); return { add: c => s.add(c), remove: c => s.delete(c), toggle: (c, on) => { if (on === undefined ? !s.has(c) : on) s.add(c); else s.delete(c); }, has: c => s.has(c), contains: c => s.has(c) }; };
const el = () => ({ classList: cls(), textContent: '', innerHTML: '', value: '', dataset: {} });
const els = {};
['cycleGroupFilter', 'skipToggle', 'unplacedExportBtn', 'subjectColorsBtn', 'cardPanelTitle', 'cycleMsg', 'cardPanelHint', 'unplacedCount'].forEach(id => { els[id] = el(); });
let allBox = null;
const panel = Object.assign(el(), {
    querySelectorAll: () => [],
    querySelector: sel => (sel === '[data-cyc-all]' && /data-cyc-all/.test(panel.innerHTML) ? (allBox = allBox || { checked: false }) : null),
});
els.cardPanel = panel;
// Jadval: 01.11–10.11 sanalari; bloklar — [boshi indeksi, kun].
const dates = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10'].map(d => ({ iso: '2026-11-' + d, d: d + '.11' }));
// Geometriya: jadval oynasi 100px dan, qotirilgan "Guruh" (170px) va "Para" (84px) ustunlari, kun ustuni 30px.
let scrolled = 0, scrollLeft = 0;
const wrap = { getBoundingClientRect: () => ({ left: 100, right: 900 }) };
Object.defineProperty(wrap, 'scrollLeft', { get: () => scrollLeft, set: v => { scrolled++; scrollLeft = v; } });
const frozen = [[0, 170], [170, 84]].map(([x, w]) => ({ getBoundingClientRect: () => ({ left: 100 + x, right: 100 + x + w }) }));
const heads = dates.map((d, i) => Object.assign(el(), { getBoundingClientRect: () => ({ left: 100 + 254 + i * 30 - scrollLeft, right: 100 + 254 + (i + 1) * 30 - scrollLeft }) }));
const blocks = [[0, 1], [4, 3], [8, 2]].map(([f, n]) => Object.assign(el(), { dataset: { cycleFrom: String(f), cycleSpan: String(n) } }));
els.cycleGrid = { querySelectorAll: sel => (sel === 'thead .cyc-dcol' ? heads : sel === 'td.cyc-block[data-cycle-from]' ? blocks
    : sel === 'thead .cyc-gcol, thead .cyc-mk-side' ? frozen : []) };
els.cycleGridWrap = wrap;

const T = (from, to, past = false) => ({ kind: 'teacher_busy', type: 'teacher', who: 'MUX', groups: ['x'], past, date_from: past ? null : from, date_to: past ? null : to, from, to });
const cards = [
    // 13.11 dan boshlanadi — to'qnashuv 13.11–24.11 (8-haftada emas).
    { key: 'A', row_key: 'r-01b', group: 'd1/d22-01b', subject: 'Klinik farmakologiya', course: 5, days: 5, placed: true, conflict: "O'qituvchi band",
        conflicts: [T('2026-11-13', '2026-11-24')] },
    // Joylanmagan — har doim.
    { key: 'B', row_key: 'r-01a', group: 'd1/d22-01a', subject: 'Farmakologiya', course: 5, days: 5, placed: false, conflict: null, conflicts: [] },
    // 8-haftada to'qnashuv (04.11–05.11) va keyingi oyda yana bir to'qnashuv (hisoblagichga 8-haftada kirmaydi).
    { key: 'C', row_key: 'r-02a', group: 'd1/d22-02a', subject: 'Terapiya', course: 5, days: 5, placed: true, conflict: "O'qituvchi band",
        conflicts: [T('2026-11-04', '2026-11-05'), Object.assign(T('2026-12-01', '2026-12-02'), { kind: 'room_capacity', type: 'room', who: '305' })] },
];
const ctx = {
    $: id => els[id], esc: s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'),
    curWeek: 8, viewMode: 'cycle', cycleGroupFilter: '', cycleCfFilter: '', cycleRowFilters: [], TIMETABLE_READ_ONLY: true,
    cfOwners: {}, cyclePlanData: { dates, cycle_cards: cards, rows: [] },
    weekMonday: n => (n === 8 ? new Date(2026, 10, 2) : n === 9 ? new Date(2026, 10, 9) : null),
    weekDates: n => (n === 8 ? '02.11–07.11' : '09.11–14.11'),
    cycleVisibleRows: rows => rows.filter(r => ctx.cycleRowFilters.includes(r.row_key)),
    subjColor: () => ({ bg: '#fff', border: '#000' }), cycleFlowShort: g => g, highlightCycleRow() {},
    Set, Object, Date, String, Boolean, Math,
};
vm.createContext(ctx);
vm.runInContext(cut("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + cut('            // ── Sikl paneli va hafta tanlovi (weekSel) ──', '            // ── Fan ranglari modali ──')
    + '\nObject.assign(this, { renderCycleCards, cycleCardInWeek, cycleCardStateText, cycleSelWeek, setAll: v => { cyclePanelAll = v; } });', ctx);

const render = () => ctx.renderCycleCards({ cycle_cards: cards, rows: ctx.cyclePlanData.rows });
const keys = () => [...panel.innerHTML.matchAll(/data-cycle-key="([^"]+)"/g)].map(m => m[1]);
// 8-hafta: A (13.11 dan) yo'q, B (joylanmagan) va C (04.11 to'qnashuv) bor; sarlavha, hisoblagichlar.
render();
assert.deepEqual(keys().sort(), ['B', 'C']);
assert.equal(els.unplacedCount.textContent, '2 ta');
assert.match(panel.innerHTML, /<div class="cyc-wk-head"><span>8-hafta \(02\.11–07\.11\): 2 ta<\/span><label class="cyc-wk-all"><input type="checkbox" data-cyc-all> Butun semestr<\/label><\/div>/);
// Kelgusi to'qnashuvlar — faqat shu haftadagilar: C ning o'qituvchi bandligi (01.12 dagi xona sig'imi — boshqa hafta).
assert.match(panel.innerHTML, /o‘qituvchi — 1<\/button>, <button type="button" data-cf-filter="room" class="cf-sum-btn">xona — 0/);
// Holat: joylangan + to'qnashuvli — sanalari bilan; joylanmagan — alohida.
// (hafta tanlanganda — shu haftadagi to'qnashuv sanalari)
assert.match(panel.innerHTML, /<span class="cycle-pn-state is-placed">joylangan · to‘qnashuv 04\.11–05\.11<\/span>/);
assert.match(panel.innerHTML, /<span class="cycle-pn-state">joylanmagan<\/span>/);
assert.equal(ctx.cycleCardStateText(cards[0]), 'joylangan · to‘qnashuv 13.11–24.11');
assert.equal(ctx.cycleCardStateText(cards[1]), 'joylanmagan');
assert.equal(ctx.cycleCardStateText({ placed: true, conflicts: [T('2026-09-01', '2026-09-03', true)] }), 'joylangan · to‘qnashuv 01.09–03.09');   // hammasi o'tgan
// Jadval: 02.11–08.11 ustunlari ajratilgan, qolganlari xira; hafta tashqarisidagi bloklar xira; bir marta scroll.
assert.deepEqual(heads.map(h => h.classList.has('cyc-wk-on')), [false, true, true, true, true, true, true, true, false, false]);
assert.deepEqual(heads.map(h => h.classList.has('cyc-wk-out')), [true, false, false, false, false, false, false, false, true, true]);
assert.deepEqual(blocks.map(b => b.classList.has('cyc-wk-out')), [true, false, true]);
assert.equal(scrolled, 1);
// 02.11 ustuni qotirilgan ustunlardan (254px) darhol keyin — ko'rinadigan maydon boshida.
assert.equal(scrollLeft, 30);
assert.equal(heads[1].getBoundingClientRect().left, 100 + 254);
render();
assert.equal(scrolled, 1);   // shu hafta — qayta scroll yo'q

// "Butun semestr": hammasi (hozirgidek), hisoblagichlar butun semestr bo'yicha, jadval ajratilmaydi.
allBox.checked = true;
allBox.onchange();
assert.deepEqual(keys().sort(), ['A', 'B', 'C']);
assert.equal(els.unplacedCount.textContent, '3 ta');
assert.match(panel.innerHTML, /<span>Butun semestr: 3 ta<\/span><label class="cyc-wk-all"><input type="checkbox" data-cyc-all checked> Butun semestr/);
assert.match(panel.innerHTML, /o‘qituvchi — 2<\/button>, <button type="button" data-cf-filter="room" class="cf-sum-btn">xona — 1/);
assert(heads.every(h => !h.classList.has('cyc-wk-on') && !h.classList.has('cyc-wk-out')));
assert(blocks.every(b => !b.classList.has('cyc-wk-out')));
// Boshqa hafta tanlansa — yana shu hafta bo'yicha ("Butun semestr" o'chadi): 9-haftada (09.11–15.11) A ning 13.11 to'qnashuvi bor.
ctx.curWeek = 9;
render();
assert.deepEqual(keys().sort(), ['A', 'B']);
assert.match(panel.innerHTML, /9-hafta \(09\.11–14\.11\): 2 ta<\/span><label class="cyc-wk-all"><input type="checkbox" data-cyc-all> Butun/);
assert.equal(scrolled, 2);
assert.equal(scrollLeft, 8 * 30);   // 09.11 (8-indeks) — qotirilgan ustunlardan keyin
assert.equal(heads[8].getBoundingClientRect().left, 100 + 254);
// Hafta tanlanmagan (barcha haftalar) — sarlavhasiz, hammasi.
ctx.curWeek = 0;
render();
assert.deepEqual(keys().sort(), ['A', 'B', 'C']);
assert.doesNotMatch(panel.innerHTML, /cyc-wk-head/);

// Ko'p guruh filtri (jadvaldagi tanlov) panelga ham: 01a va 02a tanlangan — 01b (A) yo'q.
ctx.cyclePlanData.rows = [{ row_key: 'r-01a' }, { row_key: 'r-01b' }, { row_key: 'r-02a' }];
ctx.cycleRowFilters = ['r-01a', 'r-02a'];
render();
assert.deepEqual(keys().sort(), ['B', 'C']);
assert.equal(els.unplacedCount.textContent, '2 ta');
ctx.cycleRowFilters = ['r-01b'];
ctx.curWeek = 8;
render();
assert.deepEqual(keys(), []);   // 01b to'qnashuvi 13.11 dan — 8-haftada yo'q
assert.match(panel.innerHTML, /Bu haftada joylanmagan yoki to‘qnashuvli sikl kartasi yo‘q\./);
ctx.cycleRowFilters = [];
// Hafta oralig'i: dushanba–yakshanba.
assert.deepEqual(JSON.parse(JSON.stringify(ctx.cycleSelWeek())), { from: '2026-11-02', to: '2026-11-08', label: '8-hafta (02.11–07.11)' });
assert.equal(ctx.cycleCardInWeek(cards[0], { from: '2026-11-09', to: '2026-11-15' }), true);   // 13.11 — 9-haftada
assert.equal(ctx.cycleCardInWeek(cards[0], { from: '2026-11-25', to: '2026-11-30' }), false);
// Haftadan oldin boshlanib, hafta ichida tugaydigan to'qnashuv (30.10–03.11) — 8-haftaga tegishli.
assert.equal(ctx.cycleCardInWeek({ placed: true, conflicts: [T('2026-10-30', '2026-11-03')] }, { from: '2026-11-02', to: '2026-11-08' }), true);
// O'tgan va kelgusi yozuvlar aralash — holatda kelgusi sanalar.
assert.equal(ctx.cycleCardStateText({ placed: true, conflicts: [T('2026-09-01', '2026-09-03', true), T('2026-11-13', '2026-11-24')] }),
    'joylangan · to‘qnashuv 13.11–24.11');

// Qabul testi: semestr 14.09.2026 dan (70 kun), 10-hafta (16.11–21.11) tanlansa 16.11 ustuni chapdagi qotirilgan "Guruh" va "Para"
// ustunlaridan darhol keyin turadi (scrollIntoView "center" uni o'ng chetdan tashqariga chiqarardi).
const semester = Array.from({ length: 70 }, (_, i) => { const d = new Date(Date.UTC(2026, 8, 14 + i)); const iso = d.toISOString().slice(0, 10);
    return { iso, d: iso.slice(8, 10) + '.' + iso.slice(5, 7) }; });
const semHeads = semester.map((d, i) => Object.assign(el(), { getBoundingClientRect: () => ({ left: 100 + 254 + i * 30 - scrollLeft, right: 100 + 254 + (i + 1) * 30 - scrollLeft }) }));
els.cycleGrid.querySelectorAll = sel => (sel === 'thead .cyc-dcol' ? semHeads : sel === 'thead .cyc-gcol, thead .cyc-mk-side' ? frozen : []);
ctx.cyclePlanData = { dates: semester, cycle_cards: [], rows: [] };
ctx.weekMonday = n => new Date(2026, 8, 14 + (n - 1) * 7);
ctx.weekDates = () => '16.11–21.11';
scrollLeft = 1161;   // avvalgi holat: hafta ustunlari o'ng chetdan tashqarida
ctx.curWeek = 10;
ctx.renderCycleCards({ cycle_cards: [], rows: [] });
const i1611 = semester.findIndex(d => d.iso === '2026-11-16');
assert.equal(i1611, 63);
assert(semHeads[i1611].classList.has('cyc-wk-on') && !semHeads[i1611 - 1].classList.has('cyc-wk-on'));
assert.equal(scrollLeft, i1611 * 30);
assert.equal(semHeads[i1611].getBoundingClientRect().left, frozen[1].getBoundingClientRect().right);   // "Para" ustunining o'ng chetida
// Yashirilgan dam olish ustuni haftaning birinchi kuni bo'lsa — keyingi ko'rinadigan kun qotirilgan ustunlardan keyin.
semHeads[i1611].classList.add('cyc-col-hidden');
ctx.curWeek = 9;
ctx.renderCycleCards({ cycle_cards: [], rows: [] });
ctx.curWeek = 10;
ctx.renderCycleCards({ cycle_cards: [], rows: [] });
assert.equal(scrollLeft, (i1611 + 1) * 30);
console.log('timetable cycle week panel: 8 scenarios passed');
