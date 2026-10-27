const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a);
    return source.slice(a, b);
}
const escLine = source.split('\n').find(l => l.includes('const hsEsc = v =>'));
const ctx = {};
vm.createContext(ctx);
vm.runInContext(escLine + section('const HS_BLOCK = {', 'const hsOpOk') + '; this.hsActionHtml = hsActionHtml;', ctx);
const u = { code_names: { 13: 'Amaliy' } };
const act = o => Object.assign({ op: 'create', date: '2026-10-05', start: '12:00', end: '13:20', code: '13', employee_name: 'USTOZ',
    auditorium_name: '423', flags: [], changes: [], blocked: [], blocks: [] }, o);

// 1) Xona band (tuzilgan tafsilot) + rejalashtiruvchining o'z to'sig'i — ikkalasi ham ko'rinadi.
const roomText = 'xona band: HEMIS: p/st26-09a · Fiziologiya 12:00-13:20';
let html = ctx.hsActionHtml(act({
    blocked: ["HEMIS'da 12:00-13:20 para vaqti yo'q", roomText],
    blocks: [{ type: 'room', time: '12:00–13:20', room: '423', teacher: 'BOSHQA', source: 'HEMIS', group: 'p/st26-09a', subject: 'Fiziologiya', text: roomText }],
}), u);
assert.match(html, /⛔ Xona band/);
assert.match(html, /<b>423<\/b>-xona 12:00–13:20 da band: HEMIS · p\/st26-09a · «Fiziologiya» · o'qituvchi: BOSHQA/);
assert.match(html, /⛔ To'siq/);
assert.match(html, /HEMIS'da 12:00-13:20 para vaqti yo'q/);
assert(!html.includes('xona band: HEMIS'), 'tuzilgan to\'siq matni ikki marta chiqmaydi');

// 2) Faqat rejalashtiruvchi to'sig'i — umumiy belgi va matn.
html = ctx.hsActionHtml(act({ blocked: ["o'qituvchi ham, kafedra mudiri ham topilmadi"] }), u);
assert.match(html, /⛔ To'siq/);
assert.match(html, /o'qituvchi ham, kafedra mudiri ham topilmadi/);
assert(!html.includes('Xona band') && !html.includes('O\'qituvchi band'));

// 3) O'qituvchi band: boshqa darsning xonasi ko'rsatiladi; nomlar escape qilinadi.
const tText = "o'qituvchi band: LMS p/st26-01b 08:30-09:50";
html = ctx.hsActionHtml(act({
    blocked: [tText],
    blocks: [{ type: 'teacher', time: '08:30–09:50', room: '<B1>', teacher: 'USTOZ', source: 'LMS', group: 'p/st26-01b', subject: 'Anatomiya', text: tText }],
}), u);
assert.match(html, /⛔ O'qituvchi band/);
assert.match(html, /<b>USTOZ<\/b> 08:30–09:50 da boshqa darsda: LMS · p\/st26-01b · «Anatomiya» · xona: &lt;B1&gt;/);
assert(!html.includes('⛔ To\'siq'));

// 4) O'zgarishlar qisqa ko'rinishda: HEMIS'dagi → LMS'dagi.
html = ctx.hsActionHtml(act({ op: 'update', changes: ['xona: 55 → 423'] }), u);
assert.match(html, /<b>Xona:<\/b> HEMIS'da <span class="line-through">55<\/span> → LMS'da <b>423<\/b>/);

// 5) Bartaraf etilgan to'siq: yashil belgi va band qilgan dars tafsiloti (title escape qilinadi); qizil to'siq yo'q.
html = ctx.hsActionHtml(act({ flags: ['mudir', "to'siq sababli"], employee_name: 'MUDIR',
    resolved: [{ type: 'teacher', time: '12:00–13:20', source: 'HEMIS', group: 'p/st26-09a', subject: '<Fan>', text: "o'qituvchi band: …" }] }), u);
assert.match(html, /✔ O'qituvchi band → kafedra mudiri/);
assert.match(html, /title="HEMIS · p\/st26-09a · «&lt;Fan&gt;» 12:00–13:20"/);
assert(!html.includes('⛔'));
html = ctx.hsActionHtml(act({ resolved: [{ type: 'room', time: '12:00–13:20', source: 'LMS', group: 'g', subject: 'F' }] }), u);
assert.match(html, /✔ Xona band → mudir xonasi/);
console.log('5 HEMIS compare action row scenarios passed');
