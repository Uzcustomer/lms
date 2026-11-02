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
process.exitCode = 1;
const tick = () => new Promise(r => setImmediate(r));

// O'qituvchi / fan / xona kesimida sikl darslari (cycle-week): "sikl" belgisi, haftalik dars bilan ustma-ust — bitta katak va to'qnashuv;
// qamrov ("Faqat meniki"), bosilsa — oyna va "Vaqtini o'zgartirishni so'rash"; eskirgan javob e'tiborsiz; tashqi qatlamda so'ralmaydi.
assert.match(source, /if \(viewMode !== DEFAULT_VIEW\) p\.set\('view', viewMode\);/);
assert.match(source, /const DEFAULT_VIEW = TEACHER_DEFAULTS \? 'teacher' : 'group';/);
// Qamrov tanlovi har foydalanuvchi va rol uchun alohida saqlanadi (admin "Butun doska" tanlovi o'qituvchiga o'tmaydi).
assert.match(source, /const SCOPE_KEY = 'lms:timetable-scope:' \+ TIMETABLE_USER_KEY \+ ':' \+ TIMETABLE_ROLE;/);
assert.match(source, /const saved = localStorage\.getItem\(SCOPE_KEY\);/);
assert.match(source, /localStorage\.setItem\(SCOPE_KEY, mode\);/);
assert.doesNotMatch(source, /lms:timetable-mine/);
assert.match(source, /\$timetableUserKey = \(\$timetableUser instanceof \\App\\Models\\Teacher \? 't' : 'u'\) \. \(int\) \(\$timetableUser\?->getAuthIdentifier\(\) \?\? 0\);/);
assert.match(source, /teacherFullNames = Object\.assign\(\{\}, j\.teacher_full_names \|\| \{\}\);/);
assert.match(source, /const ROLE_DEFAULT_SCOPE = \{ oqituvchi: 'mine', fan_masuli: 'subjects', kafedra_mudiri: 'kafedra' \}\[TIMETABLE_ROLE\] \|\| 'all';/);

// URL: sukut kesimi yozilmaydi — o'qilganda ham sukut (o'qituvchiga 'teacher', boshqalarga 'group').
{
    const fn = section('            function parseViewFromUrl() {', '            // URLdan o\'qilgan tanlovlarni doska yuklanganda');
    for (const [def, search, want] of [['teacher', '?board=7&week=3', 'teacher'], ['group', '?board=7', 'group'], ['teacher', '?board=7&view=group', 'group']]) {
        const c = { DEFAULT_VIEW: def, location: { search }, URLSearchParams, Number };
        vm.createContext(c);
        vm.runInContext(fn + '; this.v = parseViewFromUrl();', c);
        assert.equal(c.v.view, want, search + ' (' + def + ')');
    }
}

const els = {};
let gridClick = null;
const el = id => els[id] || (els[id] = { id, innerHTML: '', textContent: '', addEventListener(t, fn) { if (id === 'grid' && t === 'click') gridClick = fn; } });
const calls = [];
let answers = [];
const appended = [];
const ctx = {
    $: el, esc: s => String(s), BASE: '/admin', board: { id: 7, days: 6, pairs_per_day: 12 }, console: { warn() {} },
    curWeek: 8, weeklyLayer: 'internal', viewMode: 'teacher', TIMETABLE_ROLE: 'oqituvchi', grids: {},
    selectedFaculties: new Set(['F1']), selectedDirs: new Set(['davolash ishi']), selectedCourses: new Set([5]),
    scopeCards: list => ctx.mineOnly ? list.filter(c => +c.teacher_id === 5) : list, typeVisible: () => true,
    weekly: [], visibleSpecCards: () => ctx.weekly, effPlace: c => ({ day: c.day, pair: c.pair }), effectiveCard: c => c,
    cardLen: c => Math.max(1, parseInt(c.len_half) || 2),
    cardGroups: c => (c.group_names && c.group_names.length) ? c.group_names : (c.group_name ? [c.group_name] : []),
    boardSchedule: () => Array.from({ length: 12 }, (_, i) => ({ type: 'pair', no: i + 1, start: String(8 + i).padStart(2, '0') + ':00', end: String(8 + i).padStart(2, '0') + ':40' })),
    boardDayNames: () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'],
    paraGroups: () => ({}), paraSideCells: () => '<td></td>', subjColor: () => ({ bg: '#eee' }),
    excelCardText: c => ['F1', 'davolash ishi · 5-kurs', '[A] ' + c.subject_name, 'Oqim: 1-oqim', 'Haftalar'].join('\n'),
    scopeBody: () => ({ faculty_names: ['F1'], specialty_names: ['davolash ishi'], courses: [5] }),
    renderGrid: () => { ctx.renders = (ctx.renders || 0) + 1; ctx.renderGridCross(ctx.viewMode); }, renderStats() {},
    openCycleTimeProposal: (...a) => { ctx.proposal = a; },
    cycHourTimes: () => [null].concat(Array.from({ length: 12 }, (_, i) => [String(8 + i).padStart(2, '0') + ':05', String(8 + i).padStart(2, '0') + ':45'])),   // soatlar — qatorlardan farqli
    api: async (url, method, body) => { calls.push([url.replace('/admin/boards/7', ''), JSON.parse(JSON.stringify(body))]); return answers.shift(); },
    document: { addEventListener() {}, getElementById: id => appended.find(x => x.id === id && !x.removed) || null,
        createElement: () => { const n = { innerHTML: '', style: {}, offsetWidth: 100, offsetHeight: 80, remove() { n.removed = true; },
            querySelector: sel => { const m = { '.dp-close': 'close', '[data-cyc-ask]': 'ask' }[sel]; return m && (sel !== '[data-cyc-ask]' || n.innerHTML.includes('data-cyc-ask')) ? (n[m] = n[m] || {}) : null; } }; return n; },
        body: { appendChild: n => appended.push(n) } },
    window: { innerWidth: 1200, innerHeight: 800 }, activeCell: null,
    Array, Object, Set, Math, String, JSON, Promise,
};
vm.createContext(ctx);
vm.runInContext(section("            // ===== Haftalik kesimlarda (o'qituvchi / fan / xona) sikl darslari", "            // Para ustuni (sikl ichki jadvali kabi)")
    + '; this.renderGridCross = renderGridCross; this.cycleWeekLessons = cycleWeekLessons; this.loadCycleWeek = loadCycleWeek; this.invalidateCycleWeek = invalidateCycleWeek;'
    + ' this.cwGet = () => cycleWeek.lessons; this.cwSet = l => { cycleWeek.lessons = l; };', ctx);
const cycleWeekData = () => ctx.cwGet();
const setLessons = l => ctx.cwSet(l);

const lesson = o => Object.assign({ cycle: true, faculty_name: 'F1', specialty_name: 'davolash ishi', course: 5, subject_name: 'Klinik farmakologiya',
    training_type: 'practice', group_name: 'd22-06a', group_names: ['d22-06a'], flow: 'F1 / 2-oqim', teacher_id: 5, teacher_name: 'MAHMARAIMOV SH. T.',
    auditorium_code: '101', auditorium_name: '101', date: '2026-11-02', day: 1, pair: 3, len_half: 4, hour: 3, hours: 4,
    dp: { k: 2, lec: 2, prc: 4, start: 1, ps: 3 }, ask: true }, o);

(async () => {
    // 1) O'qituvchi kesimi: birinchi chizishda so'raladi (joriy hafta, ichki qatlam, qamrov bilan).
    ctx.weekly = [{ id: 11, training_type: 'practice', subject_name: 'Farmakologiya', teacher_id: 5, teacher_name: 'MAHMARAIMOV SH. T.', day: 1, pair: 1, len_half: 4 }];
    answers.push({ week: 8, lessons: [
        lesson({ id: 21, teacher_full_name: "MAHMARAIMOV SHAVKAT TO'XTASHOVICH" }),
        lesson({ id: 22, training_type: 'lecture', group_name: 'd22-05a', group_names: ['d22-05a', 'd22-05b', 'd22-05c', 'd22-05d'], teacher_id: 9,
            teacher_name: 'LEKTOR', day: 2, pair: 1, len_half: 2, ask: true }),   // ma'ruza — ask bo'lsa ham so'rash tugmasi yo'q
    ] });
    ctx.renderGridCross('teacher');
    assert.deepEqual(calls[0], ['/cycle-week', { week: 8, layer: 'internal', faculty_names: ['F1'], specialty_names: ['davolash ishi'], courses: [5] }]);
    await tick(); await tick();
    let h = el('grid').innerHTML;
    // Haftalik (1–4) va sikl (3–6) kesishadi — bitta katak 1-qatordan 6 qator, qizil hoshiya, ikkala chip.
    const clash = h.match(/<td class="tt-cell[^"]*" rowspan="6" data-day="1" data-pair="1" style="outline:2px solid #ef4444[^>]*>([\s\S]*?)<\/td>/);
    assert(clash, 'ustma-ust katak topilmadi');
    assert.match(clash[1], /data-chip="11"/);
    assert.match(clash[1], /<div class="tt-chip tt-cyc-chip prc" data-cyc="0"[^>]*><div class="tt-cross-fac"><span class="tt-cyc-badge">sikl<\/span> F1<\/div>/);
    assert.match(clash[1], /\[A\] Klinik farmakologiya/);
    // Bitta o'qituvchi — bitta ustun (teacher_id): kartada qisqa nom, sikl darsida to'liq — sarlavhada to'liq F.I.Sh.; sabab katakda.
    assert.match(h, /<th class="tt-grp px-2 py-1">MAHMARAIMOV SHAVKAT TO'XTASHOVICH<\/th>/);
    assert.doesNotMatch(h, /<th class="tt-grp px-2 py-1">MAHMARAIMOV SH\. T\.<\/th>/);
    assert.match(clash[0], /title="To'qnashuv — bir vaqtda 2 ta dars: Farmakologiya \(A\) ; sikl: Klinik farmakologiya \(A\) d22-06a"/);
    assert.match(clash[1], /^<div class="tt-clash-why">⚠ Bir vaqtda 2 ta dars<\/div>/);
    // Ma'ruza — o'z ustunida (LEKTOR), guruhlar: 3 ta + "+1 ta".
    assert.match(h, /<th class="tt-grp px-2 py-1">LEKTOR<\/th>/);
    assert.match(h, /d22-05a, d22-05b, d22-05c \+1 ta/);
    assert.equal((h.match(/outline:2px solid #ef4444/g) || []).length, 1);
    assert.equal(ctx.cycleWeekLessons().length, 2);

    // 2) "Faqat meniki": boshqa o'qituvchining sikl darsi ko'rinmaydi.
    ctx.mineOnly = true;
    assert.deepEqual(ctx.cycleWeekLessons().map(l => l.id), [21]);
    ctx.mineOnly = false;

    // 3) Sikl darsi bosilsa — oyna (sana, kun, vaqt); ruxsat bor — so'rash, sikl oynasidagidek ma'lumot bilan.
    const chip = { dataset: { cyc: '0' }, closest: () => chip, getBoundingClientRect: () => ({ right: 10, top: 10 }) };
    let stopped = false;
    gridClick({ target: chip, preventDefault() {}, stopPropagation() { stopped = true; } });
    assert(stopped, 'boshqa ishlovchilarga o\'tmasligi kerak');
    const pop = appended[appended.length - 1];
    assert.match(pop.innerHTML, /02\.11\.2026 · 2-kun · 10:05–13:45/);
    assert.match(pop.innerHTML, /Amaliy 4 s/);
    assert.match(pop.innerHTML, /Guruh: d22-06a · F1 \/ 2-oqim/);
    assert.match(pop.innerHTML, /data-cyc-ask/);
    pop.ask.onclick();
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.proposal)), [{ subject: 'Klinik farmakologiya', group: 'd22-06a', specialty: 'davolash ishi', course: 5 },
        { k: 2, lec: 2, prc: 4, start: 1, ps: 3 }, { iso: '2026-11-02', d: '02.11.2026' }]);
    // Ma'ruza (yoki ruxsatsiz dars) — so'rash tugmasi yo'q.
    gridClick({ target: Object.assign({}, chip, { dataset: { cyc: '1' }, closest() { return this; } }), preventDefault() {}, stopPropagation() {} });
    assert.doesNotMatch(appended[appended.length - 1].innerHTML, /data-cyc-ask/);

    // 3b) To'liq paralar (1 qator = 2 soat): bir o'qituvchining 1–3 va 4–6-soat darslari bir qatorga tushadi (qatorlar 1–2 va 2–3),
    // lekin aniq oraliqlari kesishmaydi — to'qnashuv emas; 3–4-soat bilan kesishsa — to'qnashuv.
    const saveWeekly = ctx.weekly, saveLessons = cycleWeekData();
    ctx.weekly = [];
    setLessons([lesson({ id: 31, day: 4, pair: 1, len_half: 2, hour: 1, hours: 3, row_hours: 2 }),
        lesson({ id: 32, day: 4, pair: 2, len_half: 2, hour: 4, hours: 3, row_hours: 2 })]);
    ctx.renderGridCross('teacher');
    assert.match(el('grid').innerHTML, /rowspan="3" data-day="4" data-pair="1">/);
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    setLessons([lesson({ id: 31, day: 4, pair: 1, len_half: 2, hour: 1, hours: 4, row_hours: 2 }),
        lesson({ id: 32, day: 4, pair: 2, len_half: 2, hour: 4, hours: 3, row_hours: 2 })]);
    ctx.renderGridCross('teacher');
    assert.match(el('grid').innerHTML, /data-day="4" data-pair="1" style="outline:2px solid #ef4444/);
    // Haftalik dars (2-qator = 3–4-soat) va sikl 1–2-soat (1-qator) — kesishmaydi; 4-soatdan — kesishadi.
    ctx.weekly = [Object.assign({}, saveWeekly[0], { id: 13, day: 5, pair: 2, len_half: 1 })];
    setLessons([lesson({ id: 33, day: 5, pair: 1, len_half: 1, hour: 1, hours: 2, row_hours: 2 })]);
    ctx.renderGridCross('teacher');
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    setLessons([lesson({ id: 33, day: 5, pair: 2, len_half: 1, hour: 4, hours: 1, row_hours: 2 })]);
    ctx.renderGridCross('teacher');
    assert.match(el('grid').innerHTML, /data-day="5" data-pair="2" style="outline:2px solid #ef4444/);
    ctx.weekly = saveWeekly;
    setLessons(saveLessons);

    // 3c) Bitta ma'ruza oqimi (ikki yo'nalish kartasi: fan, xona, vaqt bir xil) — to'qnashuv emas; boshqa xona — to'qnashuv.
    // Ism matni farq qilsa ham (qisqa / to'liq) — bitta ustun.
    const saveW = ctx.weekly, saveL = cycleWeekData();
    setLessons([]);
    const lec = o => Object.assign({ training_type: 'lecture', subject_name: 'Klinik farmakologiya', teacher_id: 5, day: 6, pair: 1, len_half: 2,
        auditorium_code: 'Z8', auditorium_name: 'Zal 8' }, o);
    ctx.weekly = [lec({ id: 41, teacher_name: 'MAHMARAIMOV SH. T.', group_names: ['p/p22-02b', 'p/p22-02c'] }),
        lec({ id: 42, teacher_name: "MAHMARAIMOV SHAVKAT TO'XTASHOVICH", group_names: ['d1/d22-06b', 'd1/d22-06c'] })];
    ctx.renderGridCross('teacher');
    assert.equal((el('grid').innerHTML.match(/<th class="tt-grp/g) || []).length, 1);
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    // Xona kesimi: o'sha fan, xona va vaqt, lekin boshqa o'qituvchi — bitta dars emas, to'qnashuv (Codex).
    ctx.renderGridCross('room');
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    ctx.weekly[1].teacher_id = 6;
    ctx.renderGridCross('room');
    assert.match(el('grid').innerHTML, /data-day="6" data-pair="1" style="outline:2px solid #ef4444/);
    ctx.weekly[1].teacher_id = 5;
    ctx.weekly[1].auditorium_code = 'Z9';
    ctx.renderGridCross('teacher');
    assert.match(el('grid').innerHTML, /data-day="6" data-pair="1" style="outline:2px solid #ef4444/);
    ctx.weekly = saveW;
    setLessons(saveL);

    // 3d) Noyob darslar bo'yicha sanash (karta / sikl bloki + guruhcha + sana): bitta paraning ikkala yarmi (08:30 va 09:10 — ikki
    // birlik kun rejasi farqli) — bitta dars, belgi yo'q, bitta chip (guruhlari qo'shiladi).
    {
        const sW = ctx.weekly, sL = cycleWeekData();
        ctx.weekly = [];
        const half = o => lesson(Object.assign({ day: 1, pair: 1, len_half: 1, row_hours: 2, hours: 1 }, o));
        setLessons([half({ id: 51, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }),
            half({ id: 51, hour: 2, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] })]);
        ctx.renderGridCross('teacher');
        let g = el('grid').innerHTML;
        assert.doesNotMatch(g, /outline:2px solid #ef4444/);
        assert.doesNotMatch(g, /Bir vaqtda/);
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 1);
        // "A + B" umumiy karta (ikki guruhcha birligida, vaqti farqli) — yolg'iz bo'lsa ham bitta dars, belgi yo'q.
        setLessons([half({ id: 52, hour: 1, group_name: 'p/p21-01a', group_names: ['p/p21-01a'] }),
            half({ id: 52, hour: 2, group_name: 'p/p21-01b', group_names: ['p/p21-01b'] })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.doesNotMatch(g, /Bir vaqtda/);
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 1);
        assert.match(g, /p\/p21-01a, p\/p21-01b/);   // birlashtirilgan chip — ikkala guruhcha
        // MAHMARAIMOV, dushanba: bir vaqtda 3 ta guruhda (d1/d22-06a, p/p21-01a, p/p21-01b) — har biri ikki yarim para yozuvi
        // bilan (12 yozuv): "Bir vaqtda 3 ta dars" va uchala dars ro'yxati.
        const three = [];
        [[61, 'd1/d22-06a'], [62, 'p/p21-01a'], [63, 'p/p21-01b']].forEach(([id, gname]) => [1, 2].forEach(hr => [0, 1].forEach(() =>
            three.push(half({ id, hour: hr, group_name: gname, group_names: [gname] })))));
        assert.equal(three.length, 12);
        setLessons(three);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.equal((g.match(/⚠ Bir vaqtda 3 ta dars/g) || []).length, 1);
        assert.doesNotMatch(g, /Bir vaqtda 12 ta dars/);
        assert.match(g, /title="To'qnashuv — bir vaqtda 3 ta dars: sikl: Klinik farmakologiya \(A\) d1\/d22-06a; sikl: Klinik farmakologiya \(A\) p\/p21-01a; sikl: Klinik farmakologiya \(A\) p\/p21-01b"/);
        assert.match(g, /<div class="tt-clash-list"><div>sikl: Klinik farmakologiya \(A\) d1\/d22-06a<\/div><div>[^<]*p\/p21-01a<\/div><div>[^<]*p\/p21-01b<\/div><\/div>/);
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 3);
        // Umumiy "01a + 01b" karta va boshqa guruh darsi — 2 sessiya, lekin 3 ta dars (karta + guruhcha).
        setLessons([half({ id: 61, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }),
            half({ id: 64, hour: 1, group_name: 'p/p21-01a + p/p21-01b', group_names: ['p/p21-01a + p/p21-01b'] })]);
        ctx.renderGridCross('teacher');
        assert.match(el('grid').innerHTML, /⚠ Bir vaqtda 3 ta dars/);
        // Ma'ruza oqimi (ikki yo'nalish kartasi) + boshqa amaliy dars — oqim bitta dars: "2 ta dars".
        const lec2 = o => Object.assign({ training_type: 'lecture', subject_name: 'Klinik farmakologiya', teacher_id: 5, teacher_name: 'MAHMARAIMOV SH. T.',
            day: 2, pair: 1, len_half: 2, auditorium_code: 'Z8', auditorium_name: 'Zal 8' }, o);
        ctx.weekly = [lec2({ id: 71, group_names: ['p/p22-02b', 'p/p22-02c'] }), lec2({ id: 72, group_names: ['d1/d22-06b', 'd1/d22-06c'] }),
            { id: 73, training_type: 'practice', subject_name: 'Farmakologiya', teacher_id: 5, teacher_name: 'MAHMARAIMOV SH. T.', day: 2, pair: 1, len_half: 2, group_name: 'd22-01a' }];
        setLessons([]);
        ctx.renderGridCross('teacher');
        assert.match(el('grid').innerHTML, /⚠ Bir vaqtda 2 ta dars/);
        ctx.weekly = sW;
        setLessons(sL);
    }

    // 4) Kesim almashsa — qayta so'ralmaydi (ma'lumot bir xil). Fan kesimi: o'sha fan ustma-ust bo'lsa ham to'qnashuv emas.
    calls.length = 0;
    ctx.viewMode = 'subject';
    ctx.weekly[0].subject_name = 'Klinik farmakologiya';
    ctx.renderGridCross('subject');
    await tick();
    assert.equal(calls.length, 0);
    assert.match(el('grid').innerHTML, /rowspan="6" data-day="1" data-pair="1">/);
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    // Xona kesimi: haftalik dars sikl darsi bilan bir xonada (101) — to'qnashuv; "Kafedra mudiri xonasi" — emas.
    ctx.viewMode = 'room';
    ctx.weekly[0].auditorium_name = '101';
    ctx.renderGridCross('room');
    assert.equal((el('grid').innerHTML.match(/outline:2px solid #ef4444/g) || []).length, 1);
    ctx.weekly = [Object.assign({}, ctx.weekly[0], { auditorium_name: 'Kafedra mudiri xonasi', day: 3 }),
        Object.assign({}, ctx.weekly[0], { id: 12, auditorium_name: 'Kafedra mudiri xonasi', day: 3, pair: 2 })];
    ctx.renderGridCross('room');
    assert.match(el('grid').innerHTML, /№ Kafedra mudiri xonasi/);
    assert.match(el('grid').innerHTML, /rowspan="5" data-day="3" data-pair="1">/);
    assert.doesNotMatch(el('grid').innerHTML, /data-day="3"[^>]*outline/);

    // 4b) Doska / sikl rejasi qayta yuklandi — kesh eskiradi: qayta so'raladi, yo'ldagi (eski) javob e'tiborsiz.
    calls.length = 0;
    let hold;
    ctx.api = async (url, method, body) => { calls.push([url, body]); return new Promise(r => { hold = r; }); };
    ctx.invalidateCycleWeek();
    assert.equal(ctx.cycleWeekLessons().length, 0);
    ctx.renderGridCross('room');
    assert.equal(calls.length, 1);
    const old = hold;
    ctx.invalidateCycleWeek();
    ctx.renderGridCross('room');
    assert.equal(calls.length, 2);
    old({ week: 8, lessons: [lesson({ id: 77 })] });
    await tick(); await tick();
    assert.equal(ctx.cycleWeekLessons().length, 0, 'eski javob qabul qilinmasligi kerak');
    hold({ week: 8, lessons: [lesson({ id: 78 })] });
    await tick(); await tick();
    assert.deepEqual(ctx.cycleWeekLessons().map(l => l.id), [78]);
    assert.match(source, /invalidateWeeklyConflicts\(\);\n\s+invalidateCycleWeek\(\);/);
    assert.match(source, /cyclePlanData = j;\n\s+invalidateCycleWeek\(\);/);

    // 5) Eskirgan javob (hafta o'zgardi) — e'tiborsiz; tashqi qatlam — so'ralmaydi, sikl darslari yo'q.
    ctx.viewMode = 'teacher';
    let release;
    ctx.api = async (url, method, body) => { calls.push([url, body]); return new Promise(r => { release = r; }); };
    ctx.curWeek = 9;
    ctx.renderGridCross('teacher');
    assert.equal(calls[calls.length - 1][1].week, 9);
    ctx.curWeek = 10;
    release({ week: 9, lessons: [lesson({ id: 99 })] });
    await tick(); await tick();
    assert.equal(ctx.cycleWeekLessons().length, 0);
    ctx.curWeek = 9;
    assert.equal(ctx.cycleWeekLessons().length, 0, 'eskirgan javob saqlanmasligi kerak');
    ctx.curWeek = 10;
    ctx.weeklyLayer = 'external';
    const before = calls.length;
    ctx.renderGridCross('teacher');
    assert.equal(calls.length, before);
    assert.equal(ctx.cycleWeekLessons().length, 0);

    console.log('timetable cross cycle: 5 scenarios passed');
    process.exitCode = 0;
})().catch(e => { console.error(e); process.exit(1); });
