const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.join(__dirname, '../..');

// HEMIS'ga yozish tartibi: xatcho'p va Playwright skriptida bir xil. Guruh ichida avval o'chirish, so'ng
// o'zgartirish, so'ng qo'shish; kun ichida surilgan darslar LMS bergan navbatda (seq) — o'rni bo'shaydigan dars avval.
const sortOf = file => {
    const s = fs.readFileSync(path.join(root, file), 'utf8');
    const order = s.match(/const OP_ORDER = (\{[^}]*\});/);
    const cmp = s.match(/jobs\.sort\(\(x, y\) => ([\s\S]*?)\);\n/);
    assert(order && cmp, file);
    return { order: order[1], cmp: cmp[1].replace(/\s+/g, ' ') };
};
const bm = sortOf('public/js/hemis-writer.js');
const pw = sortOf('tools/hemis-sync/hemis-write.mjs');
assert.deepEqual(bm, pw);
// Repodagi yozuvchining o'z taqqoslash kodi (boshqa frontend testlaridagidek) alohida vm kontekstida baholanadi.
const cmp = vm.runInNewContext('const OP_ORDER = ' + bm.order + '; (x, y) => ' + bm.cmp);

const job = (op, date, start, extra = {}) => ({ group: 'p/st26-01a', group_id: 1596, op, date, start, ...extra });
const jobs = [
    job('create', '2099-10-28', '08:30'),
    job('update', '2099-10-28', '12:00', { hemis_id: 1, seq: 2 }),   // ma'ruza 10:00 → 12:00
    job('update', '2099-10-28', '13:30', { hemis_id: 2, seq: 1 }),   // seminar 12:00 → 13:30 (avval bo'shaydi)
    job('update', '2099-10-28', '15:00', { hemis_id: 3 }),           // vaqti o'zgarmaydi
    job('delete', '2099-10-28', '16:30', { hemis_id: 4 }),
    job('update', '2099-10-27', '14:00', { hemis_id: 5, seq: 9 }),
];
const got = jobs.slice().sort(cmp).map(j => j.op + ' ' + j.date + ' ' + j.start);
assert.deepEqual(got, [
    'delete 2099-10-28 16:30',
    'update 2099-10-27 14:00',
    'update 2099-10-28 15:00',
    'update 2099-10-28 13:30',
    'update 2099-10-28 12:00',
    'create 2099-10-28 08:30',
]);
console.log('HEMIS write order: bookmarklet and Playwright agree, moves follow seq');
