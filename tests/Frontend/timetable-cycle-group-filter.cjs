const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Sikl jadvali "Guruh" sarlavhasi filtri: bir nechta guruh (checkbox), qidiruv, "Hammasi" / "Tozalash", tanlov qidiruvdan keyin ham
// saqlanadi, tugmada "d1/d22-05a" yoki "3 ta guruh", × — tozalash, tashqariga bosilsa yopiladi, localStorage (doska + qamrov kaliti).
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };
const code = cut('            let cycleRowFilters = [];', '            // Server ichki jadvalni guruhcha qatorlarida')
    + cut("            // Guruhcha qatnashadigan bo'lak qatorlari", '            function cycleVariantLabelHtml(row) {');

// Soxta DOM: oyna (div), uning ichidagi qidiruv / ro'yxat / tugmalar; hujjat darajasidagi click tinglovchisi.
const docListeners = [];
const el = tag => {
    const n = { tag, id: '', innerHTML: '', value: '', style: {}, listeners: {}, removed: false,
        addEventListener(t, fn) { (this.listeners[t] = this.listeners[t] || []).push(fn); },
        fire(t, ev) { (this.listeners[t] || []).forEach(fn => fn(ev)); },
        remove() { this.removed = true; },
        getBoundingClientRect: () => ({ left: 10, bottom: 20 }),
        focus() {},
        querySelector(sel) { return this.kids[sel] || null; }, kids: {} };
    return n;
};
let pop = null;
const store = {};
let storageBroken = false;
const localStorage = {
    getItem: k => { if (storageBroken) throw new Error('blocked'); return k in store ? store[k] : null; },
    setItem: (k, v) => { if (storageBroken) throw new Error('blocked'); store[k] = String(v); },
    removeItem: k => { if (storageBroken) throw new Error('blocked'); delete store[k]; },
};
let renders = 0;
const ctx = {
    esc: s => String(s), localStorage, JSON, Array, Set, String, setTimeout: fn => fn(),
    board: { id: 7 }, selectedFaculties: new Set(['F1']), selectedDirs: new Set(['Davolash ishi']), selectedCourses: new Set([3]),
    cyclePlanData: { rows: [] }, renderCyclePlan() { renders++; ctx.api.load(); }, renderCycleCards() {},   // haqiqiysi kabi: saqlanganini o'qiydi
    document: {
        getElementById: id => (id === 'cycRowPop' && pop && !pop.removed ? pop : null),
        createElement: tag => {
            const n = el(tag);
            n.kids['.cyc-rp-q'] = el('input');
            n.kids['.cyc-rp-list'] = el('div');
            n.kids['[data-rp-all]'] = el('button');
            n.kids['[data-rp-clear]'] = el('button');
            pop = n;
            return n;
        },
        body: { appendChild() {} },
        addEventListener: (t, fn) => docListeners.push([t, fn]),
    },
};
vm.createContext(ctx);
vm.runInContext(code + '\nthis.api = { get: () => cycleRowFilters, set: v => { cycleRowFilters = v; }, reset: () => { cycleRowStoreKey = null; },'
    + ' load: cycleRowFiltersLoad, text: cycleRowFilterText, open: openCycRowPop, close: closeCycRowPop, visible: cycleVisibleRows };', ctx);
const api = ctx.api;
const plain = v => JSON.parse(JSON.stringify(v));

const rows = [
    { row_key: 'k1', group: 'd1/d22-05a', specialty: 'Davolash ishi', course: 3, faculty: 'F1' },
    { row_key: 'k2', group: 'd1/d22-05b', specialty: 'Davolash ishi', course: 3, faculty: 'F1' },
    { row_key: 'k3', group: 'd1/d22-06a', specialty: 'Davolash ishi', course: 3, faculty: 'F1' },
    // Bo'lak qatori: 05a va 05b guruhchalari qatnashadi — tanlangan guruh bilan birga, bir marta chiqadi.
    { row_key: 'v1', group: 'Fan (a)', variant: 'Fan (a)', subgroups: ['d1/d22-05a', 'd1/d22-05b'], specialty: 'Davolash ishi', course: 3, faculty: 'F1' },
];
ctx.cyclePlanData = { rows };

// 1) Tugma yozuvi: hech biri — "Guruh (4)", bittasi — nomi, bir nechta — "N ta guruh".
assert.equal(api.text(rows, 'Guruh'), 'Guruh (4)');
api.set(['k1']);
assert.equal(api.text(rows, 'Guruh'), 'd1/d22-05a');
api.set(['k1', 'k2', 'k3']);
assert.equal(api.text(rows, 'Guruh'), '3 ta guruh');
assert.equal(api.text(rows, 'Oqim'), '3 ta oqim');

// 2) Ko'rinadigan qatorlar: tanlanganlar (jadval tartibida) + ularning bo'lak qatori bir marta.
api.set(['k2', 'k1']);
assert.deepEqual(plain(api.visible(rows).map(r => r.row_key)), ['k1', 'v1', 'k2']);
api.set([]);
assert.equal(api.visible(rows).length, 4);

// 3) Oyna: checkbox belgilash darhol qo'llanadi va saqlanadi; qidiruvdan keyin ham tanlov qoladi; "Hammasi" — topilganlar;
//    "Tozalash" — tanlov yo'q.
api.reset(); api.load();
api.open(el('button'));
const list = pop.kids['.cyc-rp-list'], q = pop.kids['.cyc-rp-q'];
assert.equal((list.innerHTML.match(/type="checkbox"/g) || []).length, 4);
const tick = key => list.fire('change', { target: { closest: () => ({ dataset: { rp: key }, checked: true }) } });
const untick = key => list.fire('change', { target: { closest: () => ({ dataset: { rp: key }, checked: false }) } });
const r0 = renders;
tick('k1');
assert.deepEqual(plain(api.get()), ['k1']);
assert.equal(renders, r0 + 1);
const key = 'tt:cycRows:7:F1|Davolash ishi|3';
assert.equal(store[key], '["k1"]');
q.value = '06a'; q.fire('input');
assert.match(list.innerHTML, /data-rp="k3"/);
assert.doesNotMatch(list.innerHTML, /data-rp="k1"/);
tick('k3');
assert.deepEqual(plain(api.get()), ['k1', 'k3']);   // qidiruvda yashirin k1 tanlovda qoldi
q.value = ''; q.fire('input');
assert.match(list.innerHTML, /<label class="cyc-rp-item is-on"><input type="checkbox" data-rp="k1" checked>/);
untick('k1');
assert.deepEqual(plain(api.get()), ['k3']);
q.value = '05'; q.fire('input');
pop.kids['[data-rp-all]'].fire('click');
assert.deepEqual(plain(api.get()), ['k3', 'k1', 'k2']);
q.value = 'zzz'; q.fire('input');
assert.match(list.innerHTML, /Topilmadi/);
q.fire('keydown', { key: 'Enter' });   // topilmasa — hech narsa
assert.equal(api.get().length, 3);
q.value = '06a'; q.fire('input');
q.fire('keydown', { key: 'Enter' });   // birinchi topilgan — olib tashlanadi (tanlangan edi)
assert.deepEqual(plain(api.get()), ['k1', 'k2']);
pop.kids['[data-rp-clear]'].fire('click');
assert.deepEqual(plain(api.get()), []);
assert.equal(key in store, false);   // bo'sh tanlov saqlanmaydi

// 4) Tashqariga bosish — yopiladi; oyna ichiga — yopilmaydi; × — tozalaydi.
api.open(el('button'));
const click = target => docListeners.filter(l => l[0] === 'click').forEach(l => l[1]({ target, preventDefault() {} }));
click({ closest: sel => (sel === '#cycRowPop' ? pop : null) });
assert.equal(pop.removed, false);
click({ closest: () => null });
assert.equal(pop.removed, true);
api.set(['k1']);
click({ closest: sel => (sel === '#cycRowFilterClear' ? {} : null) });
assert.deepEqual(plain(api.get()), []);

// 5) localStorage: sahifa yangilanganda (kalit qayta o'qiladi) tiklanadi; boshqa qamrov — o'z tanlovi; saqlash bloklansa — xatosiz.
store[key] = '["k2","k3"]';
api.reset(); api.load();
assert.deepEqual(plain(api.get()), ['k2', 'k3']);
ctx.selectedCourses = new Set([4]);
api.load();
assert.deepEqual(plain(api.get()), []);
ctx.selectedCourses = new Set([3]);
api.load();
assert.deepEqual(plain(api.get()), ['k2', 'k3']);
storageBroken = true;
api.reset(); api.load();
assert.deepEqual(plain(api.get()), []);
api.open(el('button'));
tick('k1');   // saqlab bo'lmasa ham tanlov ishlaydi
assert.deepEqual(plain(api.get()), ['k1']);

// 6) Manbada: render — saqlangan tanlovni o'qiydi va jadvalda yo'q qatorlarni chiqaradi; tugma va × yozuvi.
assert.match(source, /cycleRowFiltersLoad\(\);\n\s+if \(ttFocus && ttFocus\.show\.view === 'cycle'\) \{\n(?:\s+\/\/[^\n]*\n)?\s+cycleRowFilters = focusCycleRowKeys\(allRows\);\n\s+\} else \{\n\s+const keep = cycleRowFilters\.filter\(k => allRows\.some\(r => r\.row_key === k\)\);\n\s+if \(keep\.length !== cycleRowFilters\.length\) \{ cycleRowFilters = keep; cycleRowFiltersSave\(\); \}/);
assert.match(source, /\+ esc\(cycleRowFilterText\(allRows, rowWord\)\) \+ ' &#9662;<\/button>'/);

console.log('timetable cycle group filter: 6 scenarios passed');
