const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Haftani zichlash": joylanmagan kartalar birlik bo'lib (bir guruh, bir fan, bir o'qituvchining bo'laklari — yaxlit, ketma-ket),
// o'sha kunning ichida bo'shliq qoldirmaydigan joyga qo'yiladi; qisman joylangan birlik qolmaydi.
const a = source.indexOf('const cardTermWeeks = ');
const fn = source.indexOf('async function compactInternalWeek(', a);
const end = '\n                return res;\n            }\n';
const b = source.indexOf(end, fn) + end.length;
assert(a > 0 && fn > a && b > fn);
const helpers = source.slice(source.indexOf('            // ===== Birlik: dars yaxlit joylanadi ====='),
    source.indexOf("            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)"));
const code = helpers + source.slice(a, b);

async function compact(cards, slotsPerDay = 12, reject = []) {
  const moves = [];
  const ctx = {
    cards, overrides: {}, BASE: '', board: { weeks: 15 }, grids: {}, gridKey: () => '', gridForCard: () => ({weeks:15}), curWeek: 1,
    weeklyLayer: 'internal', weeklyEditGuard() {}, rememberLayerPlace() {},
    isCycleCardAny: () => false, cardGroups: c => [c.group_name], cardLen: c => c.len_half, cardGKeys: c => [c.group_name],
    gkey: (c, g) => g, boardSchedule: () => Array.from({ length: slotsPerDay }, () => ({ type: 'pair' })),
    // Server unit-place: birlik (kalit bo'yicha) — joylanganlar joyi bo'yicha, keyin joylanmaganlar (id); ketma-ket yoziladi.
    api: async (url, method, body) => {
      const id = +url.split('/')[2];
      const c = cards.find(x => x.id === id);
      const unit = cards.filter(x => ctx.unitKey(x) === ctx.unitKey(c)).sort((x, y) => {
        const px = ctx.placeInWeek(x, 1), py = ctx.placeInWeek(y, 1);
        return (!px) - (!py) || (px && py ? px.pair - py.pair : 0) || x.id - y.id;
      });
      if (!body.day) return { unit: unit.map(x => ({ id: x.id, active: !x.day, day: null, pair: null })) };
      if (unit.some(x => reject.includes(x.id))) throw new Error('band');   // server to'qnashuv topdi
      let p = body.pair;
      return { unit: unit.map(x => { const r = { id: x.id, active: true, day: body.day, pair: p }; moves.push([x.id, body.day, p]); p += x.len_half; return r; }) };
    },
  };
  vm.createContext(ctx);
  vm.runInContext(code + '\nthis.run = compactInternalWeek; this.unitKey = unitKey; this.placeInWeek = placeInWeek;', ctx);
  const res = JSON.parse(JSON.stringify(await ctx.run(1, cards)));
  return { res, moves, overrides: JSON.parse(JSON.stringify(ctx.overrides)) };
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

  // Birlikning bir bo'lagi sig'masa (server rad etadi) — hech biri joylanmaydi (dars bo'linib qolmaydi).
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 2, ext_pair: 5 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1, ext_day: 2, ext_pair: 7 }),
  ], 8, [2]);
  assert.deepEqual(r.moves, []);
  assert.equal(r.overrides['1|1'], undefined);
  assert.deepEqual([r.res.placed, r.res.left], [0, 2]);

  // Qisman joylangan birlik (1 soat joylangan, 2 soat qolgan) yaxlit bo'ladi: qolgan bo'lak joylangan qismning darhol ketidan
  // (3 soatlik dars ketma-ket: 1–3-yarim-slot).
  r = await compact([
    card(1, { subject_name: 'Gigiyena', len_half: 1, day: 1, pair: 1, ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 1], [2, 1, 2]]);
  assert.deepEqual([r.res.placed, r.res.left], [1, 0]);

  // Tashqi joyi yo'q qo'shimcha karta (KTR bo'yicha 1 soat) shu yurishda tashqi joyiga qo'yilgan qismga ulanadi —
  // ikkinchi marta bosish shart emas.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 1], [2, 1, 3]]);
  assert.equal(r.res.placed, 2);

  // Qo'shimcha karta boshqa fanning tashqi joyini egallab, uni surib yubormaydi: Anatomiya o'z joyiga (3) qo'yiladi; Gigiyena
  // (2 soat joylangan + 1 soat) qisman qolmaydi — butun birlik Anatomiyaning ketiga (bo'shliqsiz) suriladi.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', day: 1, pair: 1, ext_day: 1, ext_pair: 1 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1 }),
    card(3, { subject_name: 'Anatomiya', ext_day: 1, ext_pair: 3 }),
  ]);
  assert.deepEqual(r.moves, [[3, 1, 3], [1, 1, 5], [2, 1, 7]]);
  assert.deepEqual([r.res.placed, r.res.left], [2, 0]);

  // Sig'magan qisman birlik — butunlay joydan olinadi (qisman qolmaydi): 4 yarim-slotli kunda 2 soat joylangan, 3 soat qo'shilmaydi.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', day: 1, pair: 1 }),
    card(2, { subject_name: 'Gigiyena', len_half: 1 }),
    card(3, { subject_name: 'Anatomiya', day: 1, pair: 3 }),
  ], 4);
  assert.deepEqual(r.moves, []);
  assert.deepEqual(r.overrides['1|1'], { day: null, pair: null, cancelled: true, auditorium_code: null, auditorium_name: null });
  assert.deepEqual([r.res.placed, r.res.left], [0, 2]);

  // Bo'shliqsiz joy: tashqi joyi (9–10) guruhning 5–6-dagi darsi bilan oraliq (7–8) qoldiradi — darsga yopishgan 7–8 tanlanadi.
  r = await compact([
    card(1, { subject_name: 'Gigiyena', ext_day: 1, ext_pair: 9 }),
    card(2, { subject_name: 'Anatomiya', day: 1, pair: 5 }),
  ]);
  assert.deepEqual(r.moves, [[1, 1, 7]]);

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

  console.log('timetable compact week: 10 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
