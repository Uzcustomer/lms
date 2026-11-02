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
// Faqat ko'rish rejimida ham: panel belgisi bosilsa — tafsilot oynasi (har to'qnashuv alohida qatorda, guruhlar 3 ta + "+N ta").
const appended = [];
const ctx = {
    esc: s => String(s), window: { innerWidth: 1200, innerHeight: 800 }, curWeek: 9, closeCycDayPop() {},
    allCards: [{ id: 5, subject_name: 'Odam anatomiyasi', group_name: 'd1/d26-06b' }],
    cardGroups: c => c.group_names && c.group_names.length ? c.group_names : [c.group_name], cardConflict: () => 'eski matn',
    weeklyConflictItems: { 5: { 9: [
        { kind: 'teacher_busy', type: 'teacher', teacher_name: 'USTOZ', groups: ['a', 'b', 'c', 'd', 'e'], date_from: '2026-11-04', date_to: '2026-11-04', past: false, detail: 'x' },
        { kind: 'room_capacity', type: 'room', room: '215', groups: ['p/p25-02b'], date_from: '2026-11-04', date_to: '2026-11-04', past: false, detail: 'y' },
    ] } },
    document: { createElement: () => ({ style: {}, querySelector: () => ({}), offsetWidth: 100, offsetHeight: 50 }), body: { appendChild: n => appended.push(n) } },
};
vm.createContext(ctx);
vm.runInContext(section("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + section('            function openWeekCfPop(el) {', '            function setupCycleMarking() {') + '; this.openWeekCfPop = openWeekCfPop;', ctx);
ctx.openWeekCfPop({ dataset: { wcf: '5' }, getBoundingClientRect: () => ({ left: 10, bottom: 10 }) });
const pop = appended[0];
assert.match(pop.innerHTML, /<div class="dp-title">Odam anatomiyasi<\/div><div class="dp-sub">d1\/d26-06b · 9-hafta · to‘qnashuvlar<\/div>/);
const lines = pop.innerHTML.match(/<li class="cf-line[^"]*">/g) || [];
assert.equal(lines.length, 2);
assert.match(pop.innerHTML, /a, b, c, d … \(5 guruh\)/);
assert.doesNotMatch(pop.innerHTML, /eski matn/);

// Sikl bloki oynasi: "Smenani almashtiring" maslahati — oyna ochilganda bir marta so'raladi va faqat kelgusi amaliy "O'qituvchi band"
// qatorining ko'rsatmasi almashtiriladi (ma'ruza, xona va o'tgan qatorlar — server ko'rsatmasi).
async function shiftAdviceScenario(advice, fail) {
    const calls = [];
    const made = [];
    // Kichik soxta DOM: innerHTML'dagi <li> lar va ulardagi .cf-todo.
    const fakePop = () => {
        const el = { style: {}, offsetWidth: 100, offsetHeight: 50, _lis: [] };
        Object.defineProperty(el, 'innerHTML', {
            get() { return this._html; },
            set(h) {
                this._html = h;
                this._lis = (h.match(/<li[^>]*>[\s\S]*?<\/li>/g) || []).map(li => {
                    const m = li.match(/<span class="cf-todo">([\s\S]*?)<\/span>/);
                    const cls = new Set();
                    const todo = m ? { textContent: m[1], dataset: {}, classList: { add: c => cls.add(c), remove: c => cls.delete(c), has: c => cls.has(c) } } : null;
                    return { html: li, querySelector: q => (q === '.cf-todo' ? todo : null), todo };
                });
            },
        });
        el.querySelector = () => ({});
        el.querySelectorAll = q => (q === '.cf-list > li' ? el._lis : []);
        made.push(el);
        return el;
    };
    const c2 = {
        esc: s => String(s), window: { innerWidth: 1200, innerHeight: 800 }, closeCycDayPop() {}, cyclePlanData: null, cfOwners: {}, BASE: '/b', board: { id: 7 },
        CSS: { escape: s => s }, console: { warn() {} },
        cfBlocks: { K: { key: 'K', subject: 'Klinik farmakologiya', group: 'd22-05b', conflicts: [
            { kind: 'teacher_busy', type: 'teacher', who: 'SHARIPOV K.', groups: ['d22-05a'], dates: '16.09–18.09', past: false, lecture: false,
                todo: "boshqa o'qituvchi biriktiring yoki smenani almashtiring" },
            { kind: 'teacher_busy', type: 'teacher', who: 'MA\'RUZACHI', groups: ['d22-06a'], dates: '16.09', past: false, lecture: true, lecture_id: 3,
                todo: "ma'ruza o'qituvchisini yoki vaqtini o'zgartiring" },
            { kind: 'teacher_busy', type: 'teacher', who: 'ESKI', groups: ['d22-07a'], dates: '01.09', past: true, lecture: false, todo: 'x' },
            { kind: 'room_capacity', type: 'room', who: '305', groups: ['d22-05c'], dates: '16.09', past: false, todo: 'kattaroq xona tanlang' },
        ] } },
        api: async (url, method, body) => { calls.push([url, method, body]); if (fail) throw new Error('500'); return { advice }; },
        document: { createElement: fakePop, body: { appendChild() {} }, querySelector: () => null },
    };
    vm.createContext(c2);
    vm.runInContext(section("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
        + section('            function openCfPop(el) {', '            // Panel (sikl va haftalik kartalar) to\'qnashuv belgisi')
        + '; this.openCfPop = openCfPop;', c2);
    // Ma'ruza egasi — boshqa blok bo'lmasin (cfSplit: ma'ruza qatori shu blokda to'liq).
    c2.cfOwners = { 3: 'K' };
    c2.openCfPop({ dataset: { cfKey: 'K' }, getBoundingClientRect: () => ({ left: 10, bottom: 10 }) });
    await new Promise(r => setImmediate(r));
    return { calls, pop: made[0] };
}
(async () => {
    const free = { kind: 'free', text: "VII para (14:00) ga o'tkazing — bo'sh", start: 7 };
    let { calls, pop } = await shiftAdviceScenario(free);
    assert.equal(calls.length, 1);   // bir marta
    assert.deepEqual(JSON.parse(JSON.stringify(calls[0])), ['/b/boards/7/cycle-shift-advice', 'POST', { key: 'K' }]);
    const todos = pop._lis.map(li => li.todo && li.todo.textContent);
    assert.equal(todos[0], "→ VII para (14:00) ga o'tkazing — bo'sh");
    assert.equal(pop._lis[0].todo.dataset.advice, 'free');
    assert.equal(pop._lis[0].todo.classList.has('cf-checking'), false);
    assert(todos.slice(1).every(t => !/14:00/.test(String(t))), 'ma\'ruza, o\'tgan va xona qatorlari o\'zgarmaydi');
    assert.equal(pop._lis.length, 4);
    // Hech biri mos kelmaydi — "boshqa o'qituvchi biriktiring".
    ({ pop } = await shiftAdviceScenario({ kind: 'none', text: "boshqa o'qituvchi biriktiring" }));
    assert.equal(pop._lis[0].todo.textContent, "→ boshqa o'qituvchi biriktiring");
    // Server xatosi — asl ko'rsatma qoladi.
    ({ pop } = await shiftAdviceScenario(null, true));
    assert.equal(pop._lis[0].todo.textContent, "→ boshqa o'qituvchi biriktiring yoki smenani almashtiring");
    assert.equal(pop._lis[0].todo.classList.has('cf-checking'), false);
    console.log('timetable conflict popups: 4 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
