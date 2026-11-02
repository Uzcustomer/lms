const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a, 'bo\'lim topilmadi: ' + from);
    return source.slice(a, b);
}

// api(): so'rov tanasi PHP uslubida — Takliflar actions (obyektlar massivi) actions[0][op]=... bo'lib boradi ("[object Object]" emas).
const ctx = { Object, Array };
vm.createContext(ctx);
vm.runInContext(section('            function appendFormValue(fd, key, v) {', '            async function api(url, method') + '; this.appendFormValue = appendFormValue;', ctx);
const entries = [];
const fd = { append: (k, v) => entries.push([k, String(v)]) };
const body = { week: 9, card_id: 5, type: 'room', title: 'Xonani almashtirish', empty: null, none: undefined, flag: false,
    faculty_names: ['F1', 'F2'],
    actions: [{ op: 'move', card_id: 5, week: 9, day: 2, pair: 3, auditorium_code: '301' }, { op: 'teacher', card_id: 6, teacher_id: 7 }],
    nested: { a: 1, b: [1, 2] } };
Object.entries(body).forEach(([k, v]) => ctx.appendFormValue(fd, k, v));
assert.deepEqual(entries, [
    ['week', '9'], ['card_id', '5'], ['type', 'room'], ['title', 'Xonani almashtirish'], ['flag', 'false'],
    ['faculty_names[]', 'F1'], ['faculty_names[]', 'F2'],
    ['actions[0][op]', 'move'], ['actions[0][card_id]', '5'], ['actions[0][week]', '9'], ['actions[0][day]', '2'], ['actions[0][pair]', '3'],
    ['actions[0][auditorium_code]', '301'],
    ['actions[1][op]', 'teacher'], ['actions[1][card_id]', '6'], ['actions[1][teacher_id]', '7'],
    ['nested[a]', '1'], ['nested[b][]', '1'], ['nested[b][]', '2'],
]);
assert(!entries.some(([, v]) => v === '[object Object]'));
assert.match(source, /Object\.entries\(body\)\.forEach\(\(\[k, v\]\) => appendFormValue\(fd, k, v\)\);/);

// Takliflar: xato — oyna ichida (alert emas), qayta hisoblangan ro'yxat ustida.
const sug = section("            // ===== Takliflar: joylanmagan kartalarni sig'dirish yo'llari", "            if ($('suggestWeekBtn')) $('suggestWeekBtn').onclick");
assert.doesNotMatch(sug, /alert\(/);
assert.match(sug, /sugNote\(body, 'Bajarilmadi \(jadval o\\'zgarmadi\): ' \+ esc\(e\.message\), 'err'\)/);
assert.match(sug, /await finish\(msgs, errs\);/);
const c2 = { document: { createElement: () => { const n = { style: {}, attrs: {}, setAttribute(k, v) { n.attrs[k] = v; } }; return n; } } };
vm.createContext(c2);
vm.runInContext(section('            function sugNote(body, html, kind) {', '            async function openWeekSuggestions(notice) {') + '; this.sugNote = sugNote;', c2);
let note = null;
const box = { querySelector: () => note, prepend: n => { note = n; } };
c2.sugNote(box, 'Bajarilmadi: 422', 'err');
assert.equal(note.innerHTML, 'Bajarilmadi: 422');
assert.equal(note.attrs.role, 'alert');
assert.match(note.style.cssText, /#fef2f2/);
c2.sugNote(box, 'Yuborildi', 'ok');   // o'sha joy yangilanadi
assert.equal(note.innerHTML, 'Yuborildi');
assert.equal(note.attrs.role, 'status');
// /data: istisnolar ixcham massiv (ov) — kartochka|hafta xaritasiga; o'qituvchi / fan mas'uli "Faqat meniki" / "Mas'ul fanlarim" —
// server faqat shu kartalarni beradi, qamrov almashsa doska qayta yuklanadi.
{
    const ld = section("                // Hafta bo'yicha istisnolar: ixcham massiv", '                // Fan rejimi (hafta almashinuvi / sikl)');
    const c3 = { j: { room_volumes: { R1: 24 }, ov: [[7, 3, 2, 5, 0, 'R1', '101'], [7, 5, null, null, 1, null, null]] }, overrides: null };
    vm.createContext(c3);
    vm.runInContext('let overrides; const j = this.j;' + ld + '; this.overrides = overrides;', c3);
    assert.deepEqual(JSON.parse(JSON.stringify(c3.overrides)), {
        '7|3': { day: 2, pair: 5, cancelled: false, auditorium_code: 'R1', auditorium_name: '101', auditorium_volume: 24 },
        '7|5': { day: null, pair: null, cancelled: true, auditorium_code: null, auditorium_name: null, auditorium_volume: null },
    });
    assert.match(source, /const dataScope = serverScopeParam\(\);/);
    assert.match(source, /return TIMETABLE_READ_ONLY && \(scopeMode === 'mine' \|\| scopeMode === 'subjects'\) \? '&scope=' \+ scopeMode : '';/);
    assert.match(source, /if \(TIMETABLE_READ_ONLY && board\) \{\n\s+loadBoard\(board\.id\)/);
    assert.match(source, /toggleAscToolbar\(true\);\n\s+if \(!showLoadedCards\(\)\) return;/);
}
// Qamrov ("Faqat meniki" / "Mas'ul fanlarim" / "Kafedram") bo'yicha bo'sh — qamrov tugmalari (specBar) yashirinmaydi,
// "Butun doska"ga qaytish mumkin; doska haqiqatan bo'sh — "Kartochkalarni yaratish" (specBar yashirin).
{
    const fns = section('            function serverScopeParam() {', "            function buildSpecList() {")
        + section('            function setScopeMode(mode) {', '            function renderWeeklyLayerUi() {');
    const mk = () => { const set = new Set(['hidden']); return { classList: { add: c => set.add(c), remove: c => set.delete(c),
        toggle: (c, on) => on ? set.add(c) : set.delete(c), contains: c => set.has(c) }, textContent: '', hidden: () => set.has('hidden') }; };
    const run = (role, mode, all) => {
        const els = { specBar: mk(), mainArea: mk(), boardMsg: mk() };
        const calls = [];
        const c = { els, calls, all, role, mode };
        vm.createContext(c);
        vm.runInContext(`
            let scopeMode = this.mode, mineOnly = false, allCards = this.all, cards = [], specList = [], curSpec = null, board = { id: 3 }, viewMode = 'weekly';
            const TIMETABLE_READ_ONLY = this.role === 'ro', MINE_KEY = 'k', localStorage = { setItem() {} }, TIMETABLE_TEACHER_ID = 9;
            const $ = id => this.els[id];
            const calls = this.calls;
            const scopeCards = l => scopeMode === 'all' ? l : l.filter(x => +x.teacher_id === 9);
            const buildSpecList = () => { specList = cards.map(x => ({ key: String(x.id) })); };
            const renderMineUi = () => calls.push('mineUi'), syncUrl = () => {}, fillSpecControls = () => calls.push('fill'),
                fillGridInputs = () => {}, renderAll = () => calls.push('render'), loadCyclePlan = () => {};
            const loadBoard = () => { calls.push('load'); return Promise.resolve(); };
            ${fns}
            this.api = { showLoadedCards, setScopeMode, setCards: v => { cards = v; }, get cards() { return cards; } };`, c);
        return c;
    };
    // Kafedra mudiri (mijozda filtr): doskada kartalar bor, "Faqat meniki" bo'sh — tugmalar ko'rinadi.
    let c = run('kafedra', 'mine', [{ id: 1, teacher_id: 5 }]);
    assert.equal(c.api.showLoadedCards(), false);
    assert.equal(c.els.specBar.hidden(), false);
    assert.equal(c.els.mainArea.hidden(), true);
    assert.match(c.els.boardMsg.textContent, /Tanlangan qamrovda darslar yo'q/);
    assert.deepEqual(c.calls, ['mineUi']);   // qamrov tugmalari holati yangilanadi
    // "Butun doska" — ro'yxat qaytadan quriladi va ish maydoni ochiladi (qayta yuklamasdan).
    c.api.setScopeMode('all');
    assert.equal(c.els.mainArea.hidden(), false);
    assert.equal(c.els.boardMsg.textContent, '');
    assert.deepEqual(c.calls.filter(x => x !== 'mineUi'), ['fill', 'render']);
    // Yana "Faqat meniki" (bo'sh) — tugmalar ko'rinib qoladi.
    c.api.setScopeMode('mine');
    assert.equal(c.els.specBar.hidden(), false);
    assert.equal(c.els.mainArea.hidden(), true);
    // O'qituvchi (server qamrov bilan bergan, kartalar bo'sh) — tugmalar ko'rinadi.
    c = run('ro', 'mine', []);
    assert.equal(c.api.showLoadedCards(), false);
    assert.equal(c.els.specBar.hidden(), false);
    assert.match(c.els.boardMsg.textContent, /Butun doska/);
    c.api.setScopeMode('all');
    assert.deepEqual(c.calls.filter(x => x !== 'mineUi'), ['load']);
    // Doska haqiqatan bo'sh (Butun doska) — "Kartochkalarni yaratish", specBar yashirin.
    c = run('kafedra', 'all', []);
    assert.equal(c.api.showLoadedCards(), false);
    assert.equal(c.els.specBar.hidden(), true);
    assert.match(c.els.boardMsg.textContent, /Kartochkalar hali yaratilmagan/);
    // Kartalar bor — odatdagidek.
    c = run('kafedra', 'mine', [{ id: 1, teacher_id: 9 }]);
    c.api.setCards([{ id: 1, teacher_id: 9 }]);
    assert.equal(c.api.showLoadedCards(), true);
    assert.equal(c.els.specBar.hidden(), false);
    assert.equal(c.els.mainArea.hidden(), false);
}
// Qamrov / doska tez almashtirilsa — kechikkan eski javob (mas. scope=mine) yangisining ustiga yozilmaydi.
(async () => {
    const head = section('            let boardLoadSeq = 0;', '                board = j.board; allCards = j.cards || [];');
    const el = () => ({ classList: { add() {}, remove() {} } });
    const c = { applied: [], pending: [], el };
    vm.createContext(c);
    vm.runInContext(`
        let board = null, scopeMode = 'all';
        const TIMETABLE_READ_ONLY = true, BASE = '/b';
        const $ = () => this.el(), toggleAscToolbar = () => {}, syncUrl = () => {};
        const serverScopeParam = () => TIMETABLE_READ_ONLY && (scopeMode === 'mine' || scopeMode === 'subjects') ? '&scope=' + scopeMode : '';
        const api = url => new Promise(res => this.pending.push({ url, res }));
        ${head}
                board = j.board; this.applied.push(j.tag);
            }
        this.loadBoard = loadBoard; this.hideBoard = hideBoard; this.setScope = m => { scopeMode = m; };`, c);
    c.setScope('mine');
    const p1 = c.loadBoard(3);              // "Faqat meniki"
    c.setScope('all');
    const p2 = c.loadBoard(3);              // darrov "Butun doska"
    assert.match(c.pending[0].url, /scope=mine/);
    assert.doesNotMatch(c.pending[1].url, /scope=/);
    c.pending[1].res({ board: { id: 3 }, tag: 'all' });
    await p2;
    c.pending[0].res({ board: { id: 3 }, tag: 'mine' });   // eski javob kech keldi
    await p1;
    assert.deepEqual(c.applied, ['all']);
    // Doska yopildi (hideBoard) — yo'lda qolgan yuklash uni qayta ochmaydi.
    const p3 = c.loadBoard(4);
    c.hideBoard();
    c.pending[2].res({ board: { id: 4 }, tag: 'late' });
    await p3;
    assert.deepEqual(c.applied, ['all']);
    // Oddiy (yagona) yuklash qo'llanadi.
    const p4 = c.loadBoard(5);
    c.pending[3].res({ board: { id: 5 }, tag: 'one' });
    await p4;
    assert.deepEqual(c.applied, ['all', 'one']);
    console.log('timetable api form + takliflar note + /data + bo\'sh qamrov + eskirgan javob: 6 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
