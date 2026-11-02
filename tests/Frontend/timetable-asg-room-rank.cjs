const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Biriktirish oynasi (xona): bo'sh xonalar server reytingi bo'yicha (odatdagi, o'qituvchiniki, kafedraniki, shu bino, sig'im) —
// alifbo emas; band xonalar oxirida (kulrang); reyting yo'q bo'lsa — alifbo tartibi saqlanadi; sabab — yashil yorliq.
const a = source.indexOf('            function asgRoomsSorted(list) {');
const b = source.indexOf("            $('asgRoomOpen').onclick", a);
assert(a > 0 && b > a);
const cfCapText = (() => { const a = source.indexOf('            const cfCapText = '), b = source.indexOf('\n', source.indexOf('\n', a) + 1);
    return vm.runInNewContext(source.slice(a, b) + '\ncfCapText'); })();
const els = {};
const ctx = { $: id => (els[id] = els[id] || { id, value: '', innerHTML: '' }), esc: s => String(s), asgSel: { auditorium_code: 'R215' }, cfCapText,
    asgRoomsList: null, asgBusy: { teachers: {}, rooms: {}, rank: null } };
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nthis.renderAsgRooms = renderAsgRooms;'
    + '\nthis.setState = (list, busy, sel) => { asgRoomsList = list; asgBusy = busy; if (sel) asgSel = sel; };', ctx);

const room = (code, name, extra = {}) => Object.assign({ code, name, volume: 30, building_name: '', auditorium_type_name: '' }, extra);
// Sahifadagidek: ro'yxat alifbo bo'yicha keladi (AUDS_URL + localeCompare).
const list = [room('R090', '090', { volume: 200 }), room('R101', '101'), room('R102', '102'), room('R214', '214', { building_name: 'Bosh bino' }),
    room('K310', 'K-310', { building_name: 'Morfologiya' })];
const codes = () => [...els.asgRoomList.innerHTML.matchAll(/data-code="([^"]+)"/g)].map(m => m[1]);

// 1) Reyting yo'q (yuklanmagan yoki xato) — alifbo tartibi o'zgarmaydi; band xona oxirida.
ctx.setState(list, { teachers: {}, rooms: { R101: 'd1/d24-03b, Anatomiya, 07.09–14.12' }, rank: null });
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['R090', 'R102', 'R214', 'K310', 'R101']);

// 2) Reyting: kafedra xonasi, shu bino, so'ng sig'im bo'yicha (katta zal oxirida); band xona — reytingi yuqori bo'lsa ham oxirida.
const rank = { order: ['R101', 'K310', 'R214', 'R102', 'R090'], why: { R101: "o'qituvchiga biriktirilgan", K310: 'kafedra xonasi', R214: 'shu bino' } };
ctx.setState(list, { teachers: {}, rooms: { R101: 'd1/d24-03b, Anatomiya, 07.09–14.12' }, rank });
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['K310', 'R214', 'R102', 'R090', 'R101']);
const html = els.asgRoomList.innerHTML;
assert.match(html, /data-code="K310"><b>K-310<\/b><span>Morfologiya<\/span><span class="rm-why">kafedra xonasi<\/span><span class="rm-vol">30 o'rin<\/span>/);
assert.match(html, /data-code="R214"><b>214<\/b><span>Bosh bino<\/span><span class="rm-why">shu bino<\/span>/);
// Band xonada sabab yorlig'i yo'q (kulrang, sababi — kim bilan band), sababsiz xonada ham yo'q.
assert.match(html, /data-code="R101" style="opacity:\.55" title="Band: d1\/d24-03b, Anatomiya, 07\.09–14\.12"><b>101<\/b><span><\/span><span class="rm-vol">/);
assert.match(html, /data-code="R102"><b>102<\/b><span><\/span><span class="rm-vol">/);
// Sig'imdan oshgan xona (1.4): server matni o'zi "Xona sig'maydi: …" — "Band: " qo'shilmaydi; yorliq — umumiy cfCapText bilan.
ctx.setState(list, { teachers: {}, rooms: { R101: "Xona sig'maydi: 101 — 30 o'rin, 42 talaba (+12) — d22-05a, Klinik farmakologiya, 15.09" },
    caps: { R101: { room_code: 'R101', room_name: '101', volume: 30, students: 42, over: 12 } }, rank });
ctx.renderAsgRooms();
assert.match(els.asgRoomList.innerHTML, /data-code="R101" style="opacity:\.55" title="Xona sig'maydi: 101 — 30 o'rin, 42 talaba \(\+12\) — d22-05a, Klinik farmakologiya, 15\.09">/);
assert.match(els.asgRoomList.innerHTML, /<span class="rm-cap">Xona sig'maydi: 30 o'rin, 42 talaba \(\+12\)<\/span>/);

// 3) Qidiruv — saralash saqlanadi; reytingda yo'q xona (masalan, keyin qo'shilgan) — reytinglilardan keyin, o'z tartibida.
els.asgRoomSearch.value = '1';   // "1" — K310, 214, 102 va band 101 (090 yo'q)
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['K310', 'R214', 'R102', 'R101']);
els.asgRoomSearch.value = '';
ctx.setState([room('R001', '001')].concat(list), { teachers: {}, rooms: {}, rank });   // alifbo bo'yicha birinchi, lekin reytingsiz
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['R101', 'K310', 'R214', 'R102', 'R090', 'R001']);

// Sikl oynasida sabab variant matnida; yorliq uslubi.
assert.match(source, /\+ \(r\.why \? ' — ' \+ esc\(r\.why\) : ''\) \+ '<\/option>'\)\.join\(''\);/);
assert.match(source, /#asgRoomModal \.rm-row \.rm-why \{ color: #047857;/);

// ── Bandlik/reyting yuklanishi (loadAsgBusy) va biriktirishdan keyin yangilanishi (applyAsg, applyAsgRoom) ──
const cut = (from, to) => { const i = source.indexOf(from), j = source.indexOf(to, i); assert(i > 0 && j > i, from); return source.slice(i, j); };
const els2 = {};
const el2 = id => (els2[id] = els2[id] || { id, value: '', innerHTML: '', textContent: '', disabled: false, hidden: true,
    classList: { contains: c => c === 'hidden' && els2[id].hidden } });
const calls = [];
let marks = 0;
const ctx2 = { $: el2, esc: s => String(s), URLSearchParams, BASE: '/admin', board: { id: 7 }, cards: [], console: { warn() {} }, cfCapText,
    asgSel: null, asgRoomsList: null, asgBusy: { teachers: {}, rooms: {}, rank: null },
    asgUnitParams: u => ({ group_name: u.group_name }), renderAsgTable() {}, renderAsgRoomCurrent() {},
    closeAsgRoomModal() { el2('asgRoomModal').hidden = true; }, showBusyConfirm() {}, markAsgBusy: () => { marks++; },
    api: (url, method, body) => new Promise((res, rej) => calls.push({ url, method, body, res, rej })) };
vm.createContext(ctx2);
vm.runInContext(cut('            let asgBusyFor = null, asgBusySeq = 0;', '            function markAsgBusy() {')
    + cut('            function asgRoomsSorted(list) {', "            $('asgRoomOpen').onclick")
    + cut('            async function applyAsgRoom(code, force) {', '            function setAsgTeacherPanel(u) {')
    + cut('            async function applyAsg(teacherId, force) {', '            async function loadAsgAuditoriums() {')
    + '\nthis.loadAsgBusy = loadAsgBusy; this.renderAsgRooms = renderAsgRooms; this.applyAsg = applyAsg; this.applyAsgRoom = applyAsgRoom;'
    + ' this.st = (sel, list) => { asgSel = sel; if (list) asgRoomsList = list; }; this.busy = () => asgBusy;', ctx2);
const tick = () => new Promise(r => setImmediate(r));
const codes2 = () => [...el2('asgRoomList').innerHTML.matchAll(/data-code="([^"]+)"/g)].map(m => m[1]);
const unitBusyCalls = () => calls.filter(c => c.url.includes('/unit-busy?'));
const resp = (order, why = {}) => ({ teachers: {}, rooms: {}, room_rank: { order, why } });

(async () => {
    // 1) Xona oynasi unit-busy javobidan oldin ochilgan (ro'yxat keshda): avval alifbo, javob kelgach — reyting bo'yicha qayta chiziladi.
    const u = { group_name: 'g-01a', auditorium_code: null };
    ctx2.st(u, list);
    const p1 = ctx2.loadAsgBusy(u);
    el2('asgRoomModal').hidden = false;
    ctx2.renderAsgRooms();
    assert.deepEqual(codes2(), ['R090', 'R101', 'R102', 'R214', 'K310']);
    unitBusyCalls()[0].res(resp(['K310', 'R214', 'R102', 'R101', 'R090'], { K310: 'kafedra xonasi' }));
    await p1;
    assert.deepEqual(codes2(), ['K310', 'R214', 'R102', 'R101', 'R090']);
    assert.match(el2('asgRoomList').innerHTML, /<span class="rm-why">kafedra xonasi<\/span>/);

    // 2) Oyna yopiq bo'lsa — qayta chizilmaydi (keyin ochilganda chiziladi).
    el2('asgRoomModal').hidden = true;
    el2('asgRoomList').innerHTML = '';
    const p2 = ctx2.loadAsgBusy(u);
    unitBusyCalls()[1].res(resp(['R101']));
    await p2;
    assert.equal(el2('asgRoomList').innerHTML, '');

    // 3) Kech kelgan eski javob yangisini bosmaydi; o'sha birlik qayta yuklanayotganda eski reyting javob kelguncha qoladi.
    const pa = ctx2.loadAsgBusy(u), pb = ctx2.loadAsgBusy(u);
    assert.deepEqual(JSON.parse(JSON.stringify(ctx2.busy().rank.order)), ['R101']);
    const [ca, cb] = unitBusyCalls().slice(-2);
    cb.res(resp(['R214']));
    await pb;
    ca.res(resp(['R090']));
    await pa;
    assert.deepEqual(JSON.parse(JSON.stringify(ctx2.busy().rank.order)), ['R214']);
    // Boshqa birlikka o'tilsa — darhol tozalanadi (avvalgi birlik reytingi ko'rsatilmaydi).
    const u2 = { group_name: 'g-02a' };
    ctx2.st(u2);
    const pc = ctx2.loadAsgBusy(u2);
    assert.equal(ctx2.busy().rank, null);
    unitBusyCalls().at(-1).res(resp(['R102']));
    await pc;

    // 4) O'qituvchi biriktirilgach — bandlik va reyting qayta yuklanadi (unga biriktirilgan xonalar), so'ng o'qituvchilar belgisi.
    ctx2.st(u);
    let n = unitBusyCalls().length;
    const marks0 = marks;
    const pt = ctx2.applyAsg('15');
    calls.at(-1).res({ teacher_name: 'USTOZ', affected: 2 });
    await tick();
    assert.equal(unitBusyCalls().length, n + 1);
    assert.equal(unitBusyCalls().at(-1).url, '/admin/boards/7/unit-busy?group_name=g-01a');
    unitBusyCalls().at(-1).res(resp(['R102', 'K310'], { R102: "o'qituvchiga biriktirilgan" }));
    await pt; await tick();
    assert.deepEqual(JSON.parse(JSON.stringify(ctx2.busy().rank.order)), ['R102', 'K310']);
    assert.equal(marks, marks0 + 1);

    // 5) Xona biriktirilgach ham (hozirgi xona — "shu bino" / odat o'zgaradi).
    n = unitBusyCalls().length;
    const pr = ctx2.applyAsgRoom('R214');
    calls.at(-1).res({ auditorium_code: 'R214', auditorium_name: '214', affected: 2 });
    await pr; await tick();
    assert.equal(unitBusyCalls().length, n + 1);
    assert.equal(u.auditorium_code, 'R214');

    // 6) Biriktirish xato bersa — qayta yuklanmaydi.
    n = unitBusyCalls().length;
    const pe = ctx2.applyAsg('16');
    calls.at(-1).rej(Object.assign(new Error('Ruxsat yo\'q'), { data: {} }));
    await pe; await tick();
    assert.equal(unitBusyCalls().length, n);
    assert.equal(el2('asgMsg').textContent, "Xatolik: Ruxsat yo'q");

    console.log('timetable asg room rank: 10 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
