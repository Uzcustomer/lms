const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// To'qnashuvlar: qisqa belgi, rang (qizil/sariq/kulrang), bir qatorli tafsilot, ma'ruza takrorlanmasligi, yig'indi, xona sig'imi.
const a = source.indexOf("            // ===== To'qnashuvlar: qisqa belgi, rang");
const b = source.indexOf('            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.', a);
assert(a > 0 && b > a);
const ctx = { esc: s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'), scopeMode: 'all', cards: [] };
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nObject.assign(this, { cfLevel, cfBadgeText, cfGroups, cfLineHtml, cfLectureOwners, cfMergeLectureItems, cfSplit, cfBadgeHtml, cfBlockClass, cfSummaryCounts, cfMatchesFilter, audVolumeHtml, cfMergeItems, CF_LABEL });', ctx);

const T = (o = {}) => Object.assign({ kind: 'teacher_busy', type: 'teacher', who: 'SHARIPOV K.', groups: ['d22-05b'], dates: '16.09–18.09', past: false, todo: "boshqa o'qituvchi biriktiring" }, o);
const R = (o = {}) => T(Object.assign({ kind: 'room_capacity', type: 'room', who: '305', groups: ['d22-05c'], todo: 'kattaroq xona tanlang' }, o));

// Qisqa belgi va rang. Bir xil o'qituvchi (turli guruhlar bilan) — bitta qator, belgida ham bitta; boshqa o'qituvchi — alohida.
assert.equal(ctx.cfBadgeText([T(), T({ groups: ['x'] }), R()]), "O'qituvchi band (1) · Xona sig'maydi (1)");
assert.equal(ctx.cfBadgeText([T(), T({ who: 'BOSHQA' }), R()]), "O'qituvchi band (2) · Xona sig'maydi (1)");
assert.equal(ctx.cfLevel([T(), R()]), 'red');
assert.equal(ctx.cfLevel([R()]), 'yellow');
assert.equal(ctx.cfLevel([T({ past: true })]), 'past');
assert.equal(ctx.cfBadgeText([T({ past: true })]), 'o‘tgan');
assert.equal(ctx.cfLevel([]), null);
assert.equal(ctx.cfBadgeText([T({ past: true }), R()]), "Xona sig'maydi (1)");   // o'tgani hisoblanmaydi

// Guruhlar qisqartiriladi; qator: kim, guruhlar, sanalar, nima qilish kerak; o'tgan — kulrang.
assert.equal(ctx.cfGroups(['a', 'b', 'c', 'd', 'e']), 'a, b, c +2 ta');
const line = ctx.cfLineHtml(T({ groups: ['a', 'b', 'c', 'd'] }));
assert.match(line, /^<li class="cf-line cf-red"><b>O'qituvchi band<\/b> SHARIPOV K\. — a, b, c \+1 ta, 16\.09–18\.09 <span class="cf-todo">→ boshqa o'qituvchi biriktiring<\/span><\/li>$/);
assert.match(ctx.cfLineHtml(T({ past: true })), /cf-line cf-past.*o‘tgan/);
// Haftalik yozuv (who yo'q) — server matni.
assert.match(ctx.cfLineHtml({ kind: 'group_busy', detail: 'Guruh band: d-01a — Fizika', date_from: '2026-09-15', date_to: '2026-09-15', past: false }),
  /<b>Guruh band<\/b> d-01a — Fizika, 15\.09 <span class="cf-todo">→ darsni boshqa vaqtga suring/);

// room_lectures — o'zbekcha nom (xom kalit emas), ko'rsatma, sariq; qisqa belgida ham sanaladi.
const RL = T({ kind: 'room_lectures', type: 'room', who: 'Zal', groups: ['d22-06a'], lecture: true, todo: undefined });
assert.match(ctx.cfLineHtml(RL), /^<li class="cf-line cf-yellow"><b>Ma'ruza zali bitta oqimga<\/b> Zal — d22-06a, 16\.09–18\.09 <span class="cf-todo">→ bitta ma'ruza oqimiga birlashtiring yoki boshqa zal tanlang<\/span><\/li>$/);
assert.doesNotMatch(ctx.cfLineHtml(RL), /room_lectures/);
assert.equal(ctx.cfBadgeText([RL, R()]), "Xona sig'maydi (1) · Ma'ruza zali bitta oqimga (1)");
assert.equal(ctx.cfLevel([RL]), 'yellow');
// Har bir server turi uchun nom bor (xom kalit ko'rinmaydi).
['teacher_busy', 'group_busy', 'lecture_overlap', 'room_capacity', 'room_busy', 'room_lectures'].forEach(k => assert(ctx.CF_LABEL[k], k));

// Jamlash: bir xil turdagi, bir xil xonadagi yozuvlar (har sana / boshqa guruhcha bilan) — bitta qator; guruhlar va sanalar
// birlashadi; "Ko'rsatish" (show) — barcha guruhlar, kartalar, to'liq sana va soat oralig'i.
const g = (n, fac = 'F1') => ({ group: n, faculty: fac, specialty: 'davolash ishi', course: 5 });
const RC = (grp, from, to, dates, card, h) => R({ groups: [grp], date_from: from, from, dates, show: { kind: 'room_capacity', room_code: 'R305',
    groups: [g('d22-05a'), g(grp)], card_ids: [1, card], dates: [from, to], hour_from: h, hour_to: h + 6, hour_unit: 'hour', view: 'cycle' } });
const six = [RC('d22-05c', '2026-09-18', '2026-09-18', '18.09', 3, 3), RC('d22-05b', '2026-09-16', '2026-09-17', '16.09–17.09', 2, 1),
    RC('d22-05c', '2026-09-18', '2026-09-18', '18.09', 3, 3), RC('d22-05d', '2026-09-21', '2026-09-21', '21.09', 4, 1),
    RC('d22-05e', '2026-09-22', '2026-09-22', '22.09', 5, 1), RC('d22-05b', '2026-09-16', '2026-09-17', '16.09–17.09', 2, 1)];
const merged = ctx.cfMergeItems(six);
assert.equal(merged.length, 1);
const m1 = JSON.parse(JSON.stringify(merged[0]));
assert.deepEqual(m1.groups, ['d22-05c', 'd22-05b', 'd22-05d', 'd22-05e']);
assert.equal(m1.dates, '16.09–17.09, 18.09, 21.09, 22.09');   // sana tartibida, takrorsiz
assert.deepEqual(m1.show.groups.map(x => x.group), ['d22-05a', 'd22-05c', 'd22-05b', 'd22-05d', 'd22-05e']);
assert.deepEqual(m1.show.card_ids, [1, 3, 2, 4, 5]);
assert.deepEqual(m1.show.dates, ['2026-09-16', '2026-09-22']);
assert.deepEqual([m1.show.hour_from, m1.show.hour_to], [1, 9]);
assert.deepEqual([m1.show.kind, m1.show.room_code, m1.show.view, m1.show.hour_unit], ['room_capacity', 'R305', 'cycle', 'hour']);
const mline = ctx.cfLineHtml(merged[0]);
assert.match(mline, /<b>Xona sig'maydi<\/b> 305 — d22-05c, d22-05b, d22-05d \+1 ta, 16\.09–17\.09, 18\.09, 21\.09, 22\.09 /);
// Bitta "Ko'rsatish" havolasi — barcha guruhlar bilan (6 ta emas).
assert.equal((mline.match(/class="cf-show"/g) || []).length, 1);
assert.match(mline, /title="Jadvalda ko'rsatish: d22-05a, d22-05c, d22-05b, d22-05d, d22-05e">Ko'rsatish<\/a>/);
assert.equal(ctx.cfBadgeText(six), "Xona sig'maydi (1)");
// Asl yozuvlar o'zgarmaydi (server ma'lumoti qayta ishlatiladi).
assert.deepEqual(JSON.parse(JSON.stringify(six[0].groups)), ['d22-05c']);
assert.equal(six[0].show.groups.length, 2);
// Boshqa xona, boshqa tur, o'tgani, ma'ruza/amaliy — alohida; bitta yozuv — o'zgarishsiz.
assert.equal(ctx.cfMergeItems([six[0], R({ who: '306' }), R({ kind: 'room_busy' }), R({ past: true }), R({ lecture: true })]).length, 5);
assert.equal(ctx.cfMergeItems([six[1]])[0].dates, '16.09–17.09');
assert.equal(ctx.cfMergeItems([]).length, 0);
// Bitta tomonida "Ko'rsatish" ma'lumoti yo'q — boridan olinadi.
assert.equal(ctx.cfMergeItems([R(), six[0]])[0].show.room_code, 'R305');
// Tafsilot oynalari (sikl bloki / panel kartasi va haftalik) jamlangan qatorlarni chizadi.
assert.match(source, /<ul class="cf-list">' \+ cfMergeItems\(own\)\.map\(cfLineHtml\)\.join\(''\)/);
assert.match(source, /\(items\.length \? cfMergeItems\(items\)\.map\(cfLineHtml\)\.join\(''\)/);

// Oqim ma'ruzasi to'qnashuvi: birinchi blokda to'liq, boshqasida — havola.
const L = T({ lecture: true, lecture_id: 77, who: 'LEKTOR', groups: ['d22-06a'] });
const rows = [{ blocks: [{ key: 'A', conflicts: [L] }] }, { blocks: [{ key: 'B', conflicts: [L, R()] }] }];
const owner = ctx.cfLectureOwners(rows);
assert.equal(owner[77], 'A');
assert.match(ctx.cfBadgeHtml('A', [L], owner), /data-cf-key="A"[^>]*>O'qituvchi band \(1\)</);
const bh = ctx.cfBadgeHtml('B', [L, R()], owner);
assert.match(bh, /lvl-yellow[^>]*>Xona sig'maydi \(1\)</);
assert.match(bh, /data-cf-lecture="77"[^>]*>ma'ruzada muammo bor ↗</);
assert.equal(ctx.cfBlockClass('B', [L, R()], owner), ' is-conflict-warn');
assert.equal(ctx.cfBlockClass('A', [L], owner), ' is-conflict');
assert.equal(ctx.cfBlockClass('C', [T({ past: true })], owner), ' is-conflict-past');

// Guruhchalarda bitta ma'ruzaning to'qnashuvlari har xil (joylashuv / soat farqi): egasida o'tgan, boshqasida kelgusi — egasiga qo'shiladi,
// takrorlanganlari bir marta; egasi bloki rangi kelgusi bo'yicha, havola egasidagi to'liq ro'yxatni ochadi.
const Lp = T({ lecture: true, lecture_id: 88, who: 'LEKTOR', past: true, line: "O'qituvchi LEKTOR band: d22-07a, 01.09" });
const Lf = T({ lecture: true, lecture_id: 88, who: 'LEKTOR', line: "O'qituvchi LEKTOR band: d22-07b, 21.09" });
const rows2 = [{ blocks: [{ key: 'P', conflicts: [Lp] }] }, { blocks: [{ key: 'Q', conflicts: [Lf, Lp] }] }, { blocks: [{ key: 'S', conflicts: [Lf] }] }];
assert.match(source, /cfOwners = cfLectureOwners\(allRows\);\n\s+cfMergeLectureItems\(allRows, cfOwners\);/);
const owner2 = ctx.cfLectureOwners(rows2);
assert.equal(owner2[88], 'P');
ctx.cfMergeLectureItems(rows2, owner2);
ctx.cfMergeLectureItems(rows2, owner2);   // qayta chizish — takrorlanmaydi
assert.deepEqual(rows2[0].blocks[0].conflicts.map(i => i.line), ["O'qituvchi LEKTOR band: d22-07a, 01.09", "O'qituvchi LEKTOR band: d22-07b, 21.09"]);
assert.equal(ctx.cfBlockClass('P', rows2[0].blocks[0].conflicts, owner2), ' is-conflict');
assert.equal(rows2[1].blocks[0].conflicts.length, 2);   // boshqa bloklar o'zgarmaydi (havola bilan)
assert.match(ctx.cfBadgeHtml('Q', rows2[1].blocks[0].conflicts, owner2), /^<span class="cf-lecture-link"[^>]*data-cf-lecture="88"/);

// Yig'indi: ma'ruza bir marta; filtr turi bo'yicha.
const cards = [{ key: 'A', conflicts: [L] }, { key: 'B', conflicts: [L, R()] }, { key: 'C', conflicts: [T({ past: true })] }];
assert.deepEqual(JSON.parse(JSON.stringify(ctx.cfSummaryCounts(cards))), { teacher: 1, room: 1 });
assert.equal(ctx.cfMatchesFilter(cards[1], 'room'), true);
assert.equal(ctx.cfMatchesFilter(cards[0], 'room'), false);
assert.equal(ctx.cfMatchesFilter(cards[2], 'teacher'), false);

// Xona sig'imi: kiritilmagan va shubhali ajratiladi.
assert.match(ctx.audVolumeHtml(0), /aud-vol-bad.*kiritilmagan/);
assert.match(ctx.audVolumeHtml(10), /aud-vol-warn.*10 · shubhali/);
assert.equal(ctx.audVolumeHtml(30), '30');
console.log('timetable conflict badges: 8 scenarios passed');
