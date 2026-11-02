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
const els = {};
const ctx = { $: id => (els[id] = els[id] || { id, value: '', innerHTML: '' }), esc: s => String(s), asgSel: { auditorium_code: 'R215' },
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

// 3) Qidiruv — saralash saqlanadi; reytingda yo'q xona (masalan, keyin qo'shilgan) — reytinglilardan keyin, o'z tartibida.
els.asgRoomSearch.value = '1';   // "1" — K310, 214, 102 va band 101 (090 yo'q)
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['K310', 'R214', 'R102', 'R101']);
els.asgRoomSearch.value = '';
ctx.setState([room('R001', '001')].concat(list), { teachers: {}, rooms: {}, rank });   // alifbo bo'yicha birinchi, lekin reytingsiz
ctx.renderAsgRooms();
assert.deepEqual(codes(), ['R101', 'K310', 'R214', 'R102', 'R090', 'R001']);

// Ma'lumot oqimi: unit-busy javobidagi room_rank asgBusy.rank ga tushadi; sikl oynasida sabab variant matnida.
assert.match(source, /if \(asgSel === u\) asgBusy = \{ teachers: j\.teachers \|\| \{\}, rooms: j\.rooms \|\| \{\}, rank: j\.room_rank \|\| null \};/);
assert.match(source, /\+ \(r\.why \? ' — ' \+ esc\(r\.why\) : ''\) \+ '<\/option>'\)\.join\(''\);/);
assert.match(source, /#asgRoomModal \.rm-row \.rm-why \{ color: #047857;/);

console.log('timetable asg room rank: 4 scenarios passed');
