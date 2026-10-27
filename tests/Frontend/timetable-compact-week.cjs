const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Haftani zichlash": joylanmagan kartalar blok bo'lib, o'sha kunning ichida eng yaqin bo'sh joyga qo'yiladi.
const a = source.indexOf('const cardTermWeeks = ');
const fn = source.indexOf('async function compactInternalWeek(', a);
const end = '\n                return res;\n            }\n';
const b = source.indexOf(end, fn) + end.length;
assert(a > 0 && fn > a && b > fn);
const code = source.slice(a, b);

async function compact(cards, slotsPerDay = 12, reject = []) {
  const moves = [], resets = [];
  const ctx = {
    cards, overrides: {}, BASE: '', board: { weeks: 15 }, grids: {}, gridKey: () => '',
    isCycleCardAny: () => false, cardGroups: c => [c.group_name], cardLen: c => c.len_half,
    gkey: (c, g) => g, boardSchedule: () => Array.from({ length: slotsPerDay }, () => ({ type: 'pair' })),
    api: async (url, method, body) => {
      const id = +url.split('/')[2];
      if (body.action === 'reset') { resets.push(id); return {}; }
      if (reject.includes(id)) throw new Error('band');   // server to'qnashuv topdi
      moves.push([id, body.day, body.pair]);
      return {};
    },
  };
  vm.createContext(ctx);
  vm.runInContext(code + '\nthis.run = compactInternalWeek;', ctx);
  const res = JSON.parse(JSON.stringify(await ctx.run(1, cards)));
  return { res, moves, resets, overrides: JSON.parse(JSON.stringify(ctx.overrides)) };
}
const card = (id, o) => Object.assign({ id, group_name: 'g', training_type: 'practice', subject_name: 'Fan ' + id, len_half: 2, weeks: 15 }, o);

(async () => {
  // Tashqi joyi (3-4) band: 3-yarim-slotda boshqa dars, 6-da yana biri. Butun paralik dars para chegarasidan
  // siljimaydi — 4-5 ga (yarim para siljib) emas, 1-2 ga qo'yiladi.
  let r = await compact([
    card(1, { ext_day: 1, ext_pair: 3 }),
    card(2, { len_half: 1, day: 1, pair: 3 }),
    card(3, { len_half: 1, day: 1, pair: 6 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 1]]);
  assert.equal(r.res.placed, 1);

  // Bir fanning yonma-yon ikki kartasi blok bo'lib, ketma-ket qo'yiladi (tashqi joyi bo'sh).
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 2, ext_pair: 5 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1, ext_day: 2, ext_pair: 7 }),
  ]);
  assert.deepEqual(r.moves, [[1, 2, 5], [2, 2, 7]]);
  assert.equal(r.res.placed, 2);

  // Blokning ikkinchi kartasini server rad etsa — birinchisi ham qaytariladi (blok bo'linib qolmaydi).
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 2, ext_pair: 5 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1, ext_day: 2, ext_pair: 7 }),
  ], 8, [2]);
  assert.ok(r.resets.includes(1));
  assert.equal(r.overrides['1|1'], undefined);
  assert.deepEqual([r.res.placed, r.res.left], [0, 2]);

  // Shu fanning joylashgan qismi yoniga yopishishda ham para chegarasi saqlanadi: 1-yarim-slotdagi qism yonida
  // butun paralik karta 2-3 ga (yarim para siljib) qo'yilmaydi — joylanmagan qoladi.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', len_half: 1, day: 1, pair: 1, ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, []);
  assert.deepEqual([r.res.placed, r.res.left], [0, 1]);

  // Tashqi joyi yo'q qo'shimcha karta (KTR bo'yicha 1 soat) shu yurishda tashqi joyiga qo'yilgan qismga ulanadi —
  // ikkinchi marta bosish shart emas.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 1], [2, 1, 3]]);
  assert.equal(r.res.placed, 2);

  // Qo'shimcha karta boshqa fanning tashqi joyini egallab, uni surib yubormaydi: Anatomiya o'z joyiga (3) qo'yiladi,
  // Gigiyenaning qo'shimcha soatiga yonida joy qolmasa — joylanmagan qoladi.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', day: 1, pair: 1, ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1 }),
    card(3, { subject_name: 'Anatomiya', ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, [[3, 1, 3]]);
  assert.deepEqual([r.res.placed, r.res.left], [1, 1]);

  // Ma'ruza vaqti qat'iy: shu haftada o'tiladigan, hali joylanmagan ma'ruzaning tashqi joyiga amaliy dars yuqoriga
  // surilmaydi — ma'ruza o'z joyiga qo'yiladi.
  r = await compact([
    card(1, { training_type: 'lecture', subject_name: 'Fiziologiya', ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', day: 1, pair: 3, ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 1]]);
  assert.deepEqual([r.res.up, r.res.placed, r.res.left], [0, 1, 0]);

  // Joylashgan ma'ruza bo'sh joyga yuqoriga surilmaydi; tashqi joyi band bo'lgan ma'ruza boshqa joyga qo'yilmaydi.
  r = await compact([
    card(1, { training_type: 'lecture', subject_name: 'Fiziologiya', day: 1, pair: 3, ext_day: 1, ext_pair: 3 }),
    card(2, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 1 }),
    card(3, { training_type: 'lecture', subject_name: 'Anatomiya', ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, [[2, 1, 1]]);
  assert.deepEqual([r.res.up, r.res.placed, r.res.left], [0, 1, 1]);

  console.log('timetable compact week: 8 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
