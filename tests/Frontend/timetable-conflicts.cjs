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
process.exitCode = 1;   // promise osilib qolsa — muvaffaqiyatsiz
const tick = () => new Promise(r => setImmediate(r));
const plain = x => JSON.parse(JSON.stringify(x));

// Ichki jadvaldagi to'qnashuv: karta joyida qoladi, lekin "Joylashgan" hisobiga kirmaydi; sikl bloki — qizil belgi bilan;
// biriktirishda band o'qituvchi/xona — ogohlantirish va "Baribir biriktirish".

// 0) Sikl kalendari: to'qnashuvli blok qizil hoshiyali, nomi oldida "!" belgisi (sababi title'da); panelda sababi bilan.
assert.match(source, /'<td class="cyc-cell cyc-block is-mark' \+ \(block\.conflict \? ' is-conflict' : ''\)/);
assert.match(source, /\(block\.conflict \? '<span class="cyc-conflict-badge" title="To\\'qnashuv: ' \+ esc\(block\.conflict\) \+ '">!<\/span>' : ''\)/);
assert.match(source, /#cycleGrid \.cyc-block\.is-conflict \{ box-shadow: inset 0 0 0 3px #dc2626; \}/);
assert.match(source, /const unplaced = cycleCards\.filter\(card => \(!card\.placed \|\| card\.conflict\) &&/);
assert.match(source, /\(card\.conflict \? '<span class="cycle-pn-conflict">⚠ To\\'qnashuv: ' \+ esc\(card\.conflict\) \+ '<\/span>' : ''\)/);

const els = {};
const el = id => els[id] || (els[id] = { id, innerHTML: '', textContent: '', value: '', options: [], dataset: {},
    classList: { _s: new Set(['hidden']), add(c) { this._s.add(c); }, remove(c) { this._s.delete(c); }, toggle(c, on) { on ? this._s.add(c) : this._s.delete(c); }, contains(c) { return this._s.has(c); } },
    querySelector(sel) { return sel === '.busy-force' ? (this._force || (this._force = { onclick: null })) : null; } });
const calls = [];
let answers = [];
const ctx = {
    $: el, esc: s => String(s), BASE: '/admin', board: { id: 7 }, console: { warn() {} },
    allCards: [], cards: [], overrides: {}, weeklyLayer: 'internal', curWeek: 2, viewMode: 'week', cyclePlanData: null, typeFilter: 'all',
    effPlace: c => (c.day ? { day: c.day, pair: c.pair } : null), weekCancelled: () => false, isCycleCardAny: () => false,
    weekActiveCards: () => ctx.cards, renderPanel: () => { ctx.panelRenders = (ctx.panelRenders || 0) + 1; },
    setTimeout: (fn) => { ctx.timers = (ctx.timers || 0) + 1; setImmediate(fn); }, clearTimeout() {},
    api: async (url, method = 'GET', body = null) => {
        calls.push([method, url.replace('/admin/boards/7', ''), body && plain(body)]);
        const a = answers.shift();
        if (a instanceof Error) throw a;
        return a;
    },
    URLSearchParams, Object, Set, Promise, Error, Number, String,
};
vm.createContext(ctx);
vm.runInContext(section("            // ===== Ichki (haftalik) jadvaldagi to'qnashuvlar", '            // Joylanmagan kartalar diagnostikasi (unplaced-export)')
    + section("            // Tanlangan birlik darslari vaqtida boshqa guruhcha bilan band", '            let asgAudSel = null;')
    + section('            async function applyAsg(teacherId, force) {', '            async function loadAsgAuditoriums() {')
    + section('            async function saveCycleAssign(force) {', "            $('cycAssignSave').addEventListener")
    + '; this.wc = () => weeklyConflicts; this.setBusy = b => { asgBusy = b; };', ctx);
const busyErr = msg => Object.assign(new Error(msg), { status: 409, data: { needs_confirm: true, error: msg } });

(async () => {
    // 1) cardConflict: joriy hafta; shablon ko'rinishida — to'qnashgan haftalar; tashqi qatlam va joylanmagan karta — yo'q.
    const a = { id: 1, day: 2, pair: 3 }, b = { id: 2, day: 2, pair: 3 }, u = { id: 3 };
    ctx.cards = [a, b, u];
    ctx.allCards = ctx.cards;
    answers.push({ weekly: { 1: { 2: "O'qituvchi band: USTOZ — 2-kurs · d2-01b", 3: 'Auditoriya band: 101' } } });
    ctx.refreshWeeklyConflicts();
    await tick(); await tick();
    assert.deepEqual(calls[0], ['GET', '/conflicts', null]);
    assert.equal(ctx.cardConflict(a), "O'qituvchi band: USTOZ — 2-kurs · d2-01b");
    assert.equal(ctx.cardConflict(b), null);
    assert.equal(ctx.cardConflict(u), null);
    ctx.curWeek = null;
    assert.equal(ctx.cardConflict(a), "2, 3-hafta: O'qituvchi band: USTOZ — 2-kurs · d2-01b");
    ctx.weeklyLayer = 'external';
    assert.equal(ctx.cardConflict(a), null);
    ctx.weeklyLayer = 'internal';
    ctx.curWeek = 2;
    assert.equal(ctx.panelRenders, 1, 'javobdan keyin panel qayta chizilmadi');

    // 2) Hisoblagich: to'qnashuvli karta joylashganlar sonidan chiqadi, joylanmaganlar soniga qo'shiladi.
    ctx.renderStats();
    assert.match(el('statChips').innerHTML, /Joylashgan: <b>1\/3<\/b>/);
    assert.equal(el('unplacedCount').textContent, '2 ta');
    // Sikl: "Sikl bloklari" — joylashgan va to'qnashuvsiz bloklar.
    ctx.viewMode = 'cycle';
    ctx.cyclePlanData = { cycle_cards: [{ placed: true }, { placed: true, conflict: "O'qituvchi X band" }, { placed: false }] };
    ctx.renderStats();
    assert.match(el('statChips').innerHTML, /Sikl bloklari: <b>1\/3<\/b>/);
    ctx.viewMode = 'week';

    // 3) Ma'lumot o'zgarmasa — qayta so'ralmaydi; o'qituvchi almashsa — so'raladi va belgi yo'qoladi.
    ctx.refreshWeeklyConflicts();
    await tick();
    assert.equal(calls.length, 1);
    b.teacher_id = 9;
    answers.push({ weekly: {} });
    ctx.refreshWeeklyConflicts();
    await tick(); await tick();
    assert.equal(calls.length, 2);
    assert.equal(ctx.cardConflict(a), null);

    // 4) O'qituvchi biriktirish: band o'qituvchilar ro'yxatda kulrang (sababi title'da).
    el('asgTeacher').options = [{ value: '5', style: {}, dataset: {} }, { value: '6', style: {}, dataset: {} }];
    ctx.setBusy({ teachers: { 5: 'p/p22-03c, Klinik farmakologiya, 30.10–10.11' }, rooms: {} });
    ctx.markAsgBusy();
    assert.equal(el('asgTeacher').options[0].style.color, '#9ca3af');
    assert.equal(el('asgTeacher').options[0].title, 'Band: p/p22-03c, Klinik farmakologiya, 30.10–10.11');
    assert.equal(el('asgTeacher').options[1].style.color, '');

    // 5) Band o'qituvchini biriktirish — ogohlantirish va "Baribir biriktirish"; bosilsa force=1 bilan qayta yuboriladi.
    calls.length = 0;
    const unit = { faculty_name: 'F1', specialty_name: 'davolash ishi', course: 5, subject_name: 'Klinik farmakologiya', training_type: 'practice', group_name: 'd1/d22-05c' };
    vm.runInContext('asgSel = ' + JSON.stringify(unit) + '; renderAsgTable = () => {}; cards = [];', ctx);
    answers.push(busyErr("O'qituvchi SHARIPOV K. SH. shu davrda boshqa guruhcha bilan band: p/p22-03c, Klinik farmakologiya, 30.10–10.11"));
    await ctx.applyAsg('5');
    assert.deepEqual(calls[0], ['POST', '/assign-teacher', Object.assign({}, unit, { oqim_label: '', teacher_id: '5' })]);
    assert.match(el('asgMsg').innerHTML, /⚠ O'qituvchi SHARIPOV K\. SH\. shu davrda boshqa guruhcha bilan band: p\/p22-03c, Klinik farmakologiya, 30\.10–10\.11/);
    assert.match(el('asgMsg').innerHTML, />Baribir biriktirish<\/button>/);
    answers.push({ teacher_name: 'SHARIPOV K. SH.', affected: 1 });
    await el('asgMsg')._force.onclick();
    assert.deepEqual(calls[1], ['POST', '/assign-teacher', Object.assign({}, unit, { oqim_label: '', teacher_id: '5', force: 1 })]);
    assert.equal(el('asgMsg').textContent, '«SHARIPOV K. SH.» biriktirildi · 1 karta');
    // Boshqa xato — ogohlantirishsiz, oddiy xabar
    answers.push(Object.assign(new Error('Faqat o\'z kafedrangizdagi o\'qituvchini biriktira olasiz.'), { status: 422, data: {} }));
    await ctx.applyAsg('6');
    assert.equal(el('asgMsg').textContent, 'Xatolik: Faqat o\'z kafedrangizdagi o\'qituvchini biriktira olasiz.');

    // 6) Sikl bloki: ogohlantirish oynaning o'zida, "Baribir biriktirish" — force bilan saqlanadi.
    calls.length = 0;
    vm.runInContext("cycAssignCard = { specialty: 'davolash ishi', course: 5, group: 'd22-05b', subject: 'Klinik farmakologiya', type: 'practice' };"
        + " cycleEffView = () => 'group'; cycleHolidays = []; loadCyclePlan = async () => { this.reloaded = true; }; closeCycleAssign = () => { this.closed = true; };", ctx);
    el('cycAssignTeacher').value = '5';
    el('cycleStart').value = '2026-09-14';
    answers.push(busyErr("Tanlangan o'qituvchi/xona shu davrda boshqa guruhcha bilan band: O'qituvchi SHARIPOV K. SH.: d22-05a, Klinik farmakologiya, 16.09–18.09"));
    await ctx.saveCycleAssign(false);
    assert.equal(calls[0][2].force, undefined);
    assert(!el('cycAssignWarn').classList.contains('hidden'));
    assert.match(el('cycAssignWarn').innerHTML, /O'qituvchi SHARIPOV K\. SH\.: d22-05a, Klinik farmakologiya, 16\.09–18\.09/);
    assert.equal(ctx.closed, undefined);
    answers.push({ ok: true });
    await el('cycAssignWarn')._force.onclick();
    assert.equal(calls[1][2].force, 1);
    assert.equal(calls[1][2].teacher_id, 5);
    assert.equal(ctx.closed, true);
    assert.equal(ctx.reloaded, true);

    // 7) Haqiqiy api(): xato javobining tanasi (409 needs_confirm) xatoga biriktiriladi — "Baribir biriktirish" shunga tayanadi.
    const actx = { CSRF: 't', FormData: class { append() {} }, onPendingApproval() {}, ttToast() {}, esc: x => x, Object, Array, Error,
        fetch: async () => ({ ok: false, status: 409, json: async () => ({ error: 'Band', needs_confirm: true }) }) };
    vm.createContext(actx);
    vm.runInContext(section("            async function api(url, method = 'GET', body = null) {", '\n            }\n') + '\n            }; this.api = api;', actx);
    const err = await actx.api('/x', 'POST', { a: 1 }).then(() => null, e => e);
    assert.equal(err.message, 'Band');
    assert.equal(err.status, 409);
    assert.equal(err.data.needs_confirm, true);

    process.exitCode = 0;
    console.log('timetable conflicts: 7 scenarios passed');
})().catch(e => { console.error(e); process.exitCode = 1; });
