const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// To'qnashuvlar: qisqa belgi, rang (qizil/sariq/kulrang), bir qatorli tafsilot, ma'ruza takrorlanmasligi, yig'indi, xona sig'imi.
const a = source.indexOf("            // ===== To'qnashuvlar: qisqa belgi, rang");
const b = source.indexOf('            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.', a);
assert(a > 0 && b > a);
const ctx = { esc: s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;') };
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nObject.assign(this, { cfLevel, cfBadgeText, cfGroups, cfLineHtml, cfLectureOwners, cfSplit, cfBadgeHtml, cfBlockClass, cfSummaryCounts, cfMatchesFilter, audVolumeHtml });', ctx);

const T = (o = {}) => Object.assign({ kind: 'teacher_busy', type: 'teacher', who: 'SHARIPOV K.', groups: ['d22-05b'], dates: '16.09–18.09', past: false, todo: "boshqa o'qituvchi biriktiring" }, o);
const R = (o = {}) => T(Object.assign({ kind: 'room_capacity', type: 'room', who: '305', groups: ['d22-05c'], todo: 'kattaroq xona tanlang' }, o));

// Qisqa belgi va rang.
assert.equal(ctx.cfBadgeText([T(), T({ groups: ['x'] }), R()]), "O'qituvchi band (2) · Xona sig'maydi (1)");
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
console.log('timetable conflict badges: 7 scenarios passed');
