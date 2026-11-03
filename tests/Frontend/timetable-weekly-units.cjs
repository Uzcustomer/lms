const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Birlik (dars yaxlit) yordamchilari: kalit, tartib, qisman joylangan, bo'shliq va vaqt oralig'i.
const helpers = source.slice(source.indexOf('            // ===== Birlik: dars yaxlit joylanadi ====='),
    source.indexOf("            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)"));
const times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
const ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
const base = { faculty_name: '2-son davolash', specialty_name: 'davolash ishi', course: 1, group_name: 'd2/d26-01b (rus)',
    subject_name: 'Tibbiy va biologik fizika', training_type: 'practice', teacher_id: 5 };
const cards = [
    Object.assign({ id: 1, len_half: 2 }, base),
    Object.assign({ id: 2, len_half: 1 }, base),
    Object.assign({ id: 3, len_half: 2 }, base, { teacher_id: 6 }),   // boshqa o'qituvchi — boshqa birlik
    Object.assign({ id: 4, len_half: 2 }, base, { subject_name: 'Anatomiya', teacher_id: 9 }),
];
const ctx = {
    cards, overrides: { '1|8': { day: 5, pair: 5, cancelled: false }, '4|8': { day: 5, pair: 8, cancelled: false } }, curWeek: 8,
    cardLen: c => c.len_half, cardGKeys: c => [c.group_name], isCycleCardAny: () => false,
    boardSchedule: () => times.map((t, i) => ({ type: 'pair', start: t, end: ends[i] })),
};
vm.createContext(ctx);
vm.runInContext(helpers + '; Object.assign(this, { unitKey, unitOf, unitState, unitLen, gapsOf, groupDayRows, unitGapEffect, slotRangeText, placeInWeek });', ctx);
const plain = x => JSON.parse(JSON.stringify(x));

assert.equal(ctx.unitKey(cards[0]), ctx.unitKey(cards[1]));
assert.notEqual(ctx.unitKey(cards[0]), ctx.unitKey(cards[2]));
assert.equal(ctx.unitKey({ id: 7, training_type: 'lecture' }), 'L#7');
// 8-hafta: 2 soatlik bo'lak 12:00 da, 1 soatlik — joylanmagan: qisman (joylangan oldin).
let st = ctx.unitState(cards[1], 8);
assert.deepEqual(st.list.map(c => c.id), [1, 2]);
assert.deepEqual([st.partial, st.len, st.placed.length], [true, 3, 1]);
// Shu haftada 1 soatlik bo'lak o'tilmaydi (bekor) — birlik to'liq joylangan.
ctx.overrides['2|8'] = { cancelled: true };
assert.equal(ctx.unitState(cards[0], 8).partial, false);
delete ctx.overrides['2|8'];
// Vaqt oralig'i: 12:40 dan 3 soat — "12:40–14:50"; 12:40 dan 4 soat (III para yarmidan) — "12:40–15:40".
assert.equal(ctx.slotRangeText(6, 3), '12:40–14:50');
assert.equal(ctx.slotRangeText(6, 4), '12:40–15:40');
// Bo'shliq: {5,6,9} — 7, 8 bo'sh (2 yarim-slot); 4-qator (10:40) va 5-qator (12:00) orasidagi katta tanaffus qator emas.
assert.equal(ctx.gapsOf(new Set([5, 6, 9])), 2);
assert.equal(ctx.gapsOf(new Set([3, 4, 5, 6])), 0);
// Guruh kunida: 1-bo'lak (5–6) va Anatomiya (8–9). Birlikni 12:00 dan (5–7) — 7 to'ladi, bo'shliq yo'q; 13:30 dan (7–9) — Anatomiya
// bilan to'qnashadi (bu yerda faqat bo'shliq); 15:40 dan (10–12) — Anatomiyaga yopishgan.
const rows = ctx.groupDayRows(8, new Set([1, 2]));
assert.deepEqual(plain([...rows['d2/d26-01b (rus)|5']]), [8, 9]);
const unit = ctx.unitOf(cards[0], 8);
assert.deepEqual(plain(ctx.unitGapEffect(unit, 5, 5, rows)), { delta: 0, touch: true });
assert.deepEqual(plain(ctx.unitGapEffect(unit, 5, 10, rows)), { delta: 0, touch: true });
assert.deepEqual(plain(ctx.unitGapEffect(unit, 5, 1, rows)), { delta: 4, touch: false });
console.log('timetable weekly units: 4 scenarios passed');
