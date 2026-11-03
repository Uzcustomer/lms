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
        require('./helpers/timetable-info-only.cjs')(c);
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
    // "Ko'rsatish" fokusi (alohida testda): bu yerda fokus yo'q — kesim o'zgarmaydi, qizil ramka yo'q.
    focusCrossItems: (mode, items) => items, focusHighlight() {},
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
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(section("            // ===== Haftalik kesimlarda (o'qituvchi / fan / xona) sikl darslari", "            // Para ustuni (sikl ichki jadvali kabi)")
    + '; this.renderGridCross = renderGridCross; this.cycleWeekLessons = cycleWeekLessons; this.loadCycleWeek = loadCycleWeek; this.invalidateCycleWeek = invalidateCycleWeek; this.crossLessonKey = crossLessonKey;'
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
        lesson({ id: 32, day: 4, pair: 2, len_half: 2, hour: 4, hours: 3, row_hours: 2, group_name: 'd22-06b', group_names: ['d22-06b'] })]);
    ctx.renderGridCross('teacher');
    assert.match(el('grid').innerHTML, /rowspan="3" data-day="4" data-pair="1">/);
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    setLessons([lesson({ id: 31, day: 4, pair: 1, len_half: 2, hour: 1, hours: 4, row_hours: 2 }),
        lesson({ id: 32, day: 4, pair: 2, len_half: 2, hour: 4, hours: 3, row_hours: 2, group_name: 'd22-06b', group_names: ['d22-06b'] })]);
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
    // Xonasiz ikki ma'ruza (o'sha fan, o'qituvchi, vaqt) — oqimligi tasdiqlanmaydi: to'qnashuv.
    ctx.weekly.forEach(w => { w.auditorium_code = null; w.auditorium_name = null; });
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
        // Birlashtirilgan chip oynasi: umumiy oraliq (1–2-soat) va har yozuv o'z vaqti bilan (Codex) — ikkinchi yarim yo'qolmaydi.
        const mchip = { dataset: { cyc: String(+(g.match(/data-cyc="(\d+)"/) || [])[1]) }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) };
        gridClick({ target: mchip, preventDefault() {}, stopPropagation() {} });
        let mp = appended[appended.length - 1];
        assert.match(mp.innerHTML, /08:05–09:45/);
        assert.match(mp.innerHTML, /Amaliy 2 s/);
        assert.match(mp.innerHTML, /Guruhchalar vaqti:/);
        assert.match(mp.innerHTML, /d1\/d22-06a: 08:05–08:45 \(1 s\)/);
        assert.match(mp.innerHTML, /d1\/d22-06a: 09:05–09:45 \(1 s\)/);
        assert.doesNotMatch(mp.innerHTML, /data-cyc-ask class/);   // umumiy tugma yo'q — har yozuv o'z tugmasi bilan
        ctx.proposal = null;
        mp.onclick({ target: { closest: () => ({ dataset: { cycAskPart: '1' } }) } });
        assert.deepEqual(JSON.parse(JSON.stringify(ctx.proposal))[0], { subject: 'Klinik farmakologiya', group: 'd1/d22-06a', specialty: 'davolash ishi', course: 5 });
        // "A + B" umumiy karta (ikki guruhcha birligida, vaqti farqli) — yolg'iz bo'lsa ham bitta dars, belgi yo'q.
        setLessons([half({ id: 52, hour: 1, group_name: 'p/p21-01a', group_names: ['p/p21-01a'] }),
            half({ id: 52, hour: 2, group_name: 'p/p21-01b', group_names: ['p/p21-01b'] })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.doesNotMatch(g, /Bir vaqtda/);
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 1);
        assert.match(g, /p\/p21-01a, p\/p21-01b/);   // birlashtirilgan chip — ikkala guruhcha
        // Har guruhcha o'z vaqti va so'rovi bilan: 01b (2-soat) so'rovi — 01b guruhi bilan.
        gridClick({ target: { dataset: { cyc: String(+(g.match(/data-cyc="(\d+)"/) || [])[1]) }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) },
            preventDefault() {}, stopPropagation() {} });
        mp = appended[appended.length - 1];
        assert.match(mp.innerHTML, /p\/p21-01a: 08:05–08:45/);
        assert.match(mp.innerHTML, /p\/p21-01b: 09:05–09:45/);
        mp.onclick({ target: { closest: () => ({ dataset: { cycAskPart: '1' } }) } });
        assert.equal(JSON.parse(JSON.stringify(ctx.proposal))[0].group, 'p/p21-01b');
        // Bir xil vaqtdagi yozuvlar — bitta qatorda, guruhchalari qo'shiladi.
        setLessons([half({ id: 53, hour: 1, group_name: 'x-1a', group_names: ['x-1a'] }), half({ id: 53, hour: 2, group_name: 'x-1c', group_names: ['x-1c'] }),
            half({ id: 53, hour: 1, group_name: 'x-1b', group_names: ['x-1b'] })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        gridClick({ target: { dataset: { cyc: String(+(g.match(/data-cyc="(\d+)"/) || [])[1]) }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) },
            preventDefault() {}, stopPropagation() {} });
        assert.match(appended[appended.length - 1].innerHTML, /x-1a, x-1b: 08:05–08:45/);
        assert.match(appended[appended.length - 1].innerHTML, /x-1c: 09:05–09:45/);
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
        // Sinov natijasi (9-hafta, Dushanba): har guruhning darsi HAR XIL kartalarda (bo'lingan amaliy qismlari, sikl yozuvlari) —
        // fan + tur + guruh bo'yicha bitta dars: 12 yozuv → "Bir vaqtda 3 ta dars"; bitta guruhning yozuvlari yolg'iz — belgi yo'q.
        const parts12 = [];
        let cid = 900;
        ['d1/d22-06a', 'p/p21-01a', 'p/p21-01b'].forEach(gname => [1, 2, 1, 2].forEach(hr => parts12.push(half({ id: ++cid, hour: hr,
            group_name: gname, group_names: [gname] }))));
        setLessons(parts12);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.equal((g.match(/⚠ Bir vaqtda 3 ta dars/g) || []).length, 1);
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 3);
        setLessons(parts12.filter(l => l.group_name === 'p/p21-01a').map(l => Object.assign({}, l, { id: l.id + 100, group_name: "p/p21-01a (o'z)", group_names: ["P/P21-01A (o'z)"] }))
            .concat(parts12.filter(l => l.group_name === 'p/p21-01a')));
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.doesNotMatch(g, /Bir vaqtda/);   // 8 yozuv, bitta dars (qo'shimcha va harf farqi hisobga olinmaydi)
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 1);
        // Haftalik karta + o'sha guruhning o'sha fani (sikl yozuvi) — bitta dars.
        ctx.weekly = [{ id: 950, training_type: 'practice', subject_name: 'Klinik farmakologiya', teacher_id: 5, teacher_name: 'MAHMARAIMOV SH. T.', day: 1, pair: 1,
            len_half: 1, group_name: 'd1/d22-06a', faculty_name: 'F1', specialty_name: 'davolash ishi', course: '5' }];
        setLessons([half({ id: 951, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] })]);
        ctx.renderGridCross('teacher');
        assert.doesNotMatch(el('grid').innerHTML, /Bir vaqtda/);
        // Boshqa fakultet / yo'nalish / kursdagi bir xil nomli guruh — boshqa dars: to'qnashuv (haftalik karta qamrovdan tashqarida ham
        // ko'rinishi mumkin — bir nechta yo'nalish birga tanlanganda).
        const w950 = ctx.weekly[0];
        [{ faculty_name: 'F2' }, { specialty_name: 'pediatriya' }, { course: 4 }].forEach(o => {
            ctx.weekly = [Object.assign({}, w950, o)];
            setLessons([half({ id: 951, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] })]);
            ctx.renderGridCross('teacher');
            assert.match(el('grid').innerHTML, /⚠ Bir vaqtda 2 ta dars/, JSON.stringify(o));
        });
        ctx.weekly = [w950];
        // Bitta darsning yozuvlari, lekin o'qituvchisi yoki xonasi boshqa — alohida chiplar (har biri o'z o'qituvchisi/xonasi bilan),
        // sanoqda bitta dars. Fan kesimi: ketma-ket soatlar, ikki o'qituvchi (karta ham har xil, ham bir xil).
        ctx.weekly = [];
        [[961, 962], [963, 963]].forEach(([a, b]) => {
            setLessons([half({ id: a, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }),
                half({ id: b, hour: 2, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'], teacher_id: 6, teacher_name: 'IKKINCHI' })]);
            ctx.renderGridCross('subject');
            g = el('grid').innerHTML;
            assert.equal((g.match(/tt-cyc-chip/g) || []).length, 2, a + '/' + b);
            assert.match(g, /O'qituvchi: MAHMARAIMOV SH\. T\./);
            assert.match(g, /O'qituvchi: IKKINCHI/);
            assert.doesNotMatch(g, /Bir vaqtda/);
        });
        // O'qituvchi kesimi: o'sha dars ikki xonada — ikkala xona ko'rinadi; xonasi ham bir xil bo'lsa — bitta chip.
        setLessons([half({ id: 964, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }),
            half({ id: 965, hour: 2, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'], auditorium_code: '102', auditorium_name: '102' })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.equal((g.match(/tt-cyc-chip/g) || []).length, 2);
        assert.match(g, /Xona: 101/);
        assert.match(g, /Xona: 102/);
        assert.doesNotMatch(g, /Bir vaqtda/);
        setLessons([half({ id: 964, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }),
            half({ id: 965, hour: 2, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] })]);
        ctx.renderGridCross('teacher');
        assert.equal((el('grid').innerHTML.match(/tt-cyc-chip/g) || []).length, 1);
        ctx.weekly = [w950];
        // Kalit: qamrov (kurs raqam yoki matn — bir xil) + fan + tur + guruh; sikl chiplari ham shu kalit bo'yicha birlashadi.
        const lk = o => ctx.crossLessonKey({ l: half(Object.assign({ group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'] }, o)) });
        assert.equal(lk({ course: '5' }), lk({ course: 5 }));
        assert.equal(lk({ faculty_name: ' f1 ' }), lk({}));
        assert.notEqual(lk({ specialty_name: 'pediatriya' }), lk({}));
        assert.notEqual(lk({ faculty_name: 'F2' }), lk({}));
        assert.notEqual(lk({ course: 4 }), lk({}));
        // Boshqa fan yoki boshqa tur — boshqa dars: to'qnashuv.
        setLessons([half({ id: 951, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'], subject_name: 'Farmakologiya' })]);
        ctx.renderGridCross('teacher');
        assert.match(el('grid').innerHTML, /⚠ Bir vaqtda 2 ta dars/);
        setLessons([half({ id: 951, hour: 1, group_name: 'd1/d22-06a', group_names: ['d1/d22-06a'], training_type: 'lecture' })]);
        ctx.renderGridCross('teacher');
        assert.match(el('grid').innerHTML, /⚠ Bir vaqtda 2 ta dars/);
        ctx.weekly = [];
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
        // Sikl ma'ruzasi zalida shu vaqtda boshqa ma'ruza (server room_clash) — o'qituvchi kesimida ham: sariq hoshiya, sabab va yechim.
        ctx.weekly = [];
        setLessons([half({ id: 81, training_type: 'lecture', hour: 1, hours: 2, len_half: 1, group_name: 'p/p22-02b', group_names: ['p/p22-02b'],
            room_clash: "Zalda bir vaqtda ikki ma'ruza: d1/d22-06b, Klinik farmakologiya (o'qituvchi BOSHQA) — bloklarni bitta ma'ruza oqimiga birlashtiring" })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.match(g, /style="outline:2px solid #f59e0b;outline-offset:-2px;background:#fffbeb" data-room-clash="1" title="Zalda bir vaqtda ikki ma'ruza: d1\/d22-06b/);
        assert.match(g, /<div class="tt-clash-why tt-room-why">⚠ Zalda bir vaqtda ikki ma'ruza<\/div><div class="tt-clash-list"><div>Zalda bir vaqtda ikki ma'ruza: d1\/d22-06b, Klinik farmakologiya \(o'qituvchi BOSHQA\)/);
        assert.doesNotMatch(g, /data-cross-clash/);
        // Birlashtirilgan chip: zal to'qnashuvi faqat keyingi yarmida — yo'qolmaydi (katakda va oynada; Codex).
        setLessons([half({ id: 82, training_type: 'lecture', hour: 1, hours: 1, group_name: 'p/p22-02c', group_names: ['p/p22-02c'] }),
            half({ id: 82, training_type: 'lecture', hour: 2, hours: 1, group_name: 'p/p22-02c', group_names: ['p/p22-02c'], room_clash: 'Zalda bir vaqtda ikki ma\'ruza: KECHKI' })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.match(g, /data-room-clash="1" title="Zalda bir vaqtda ikki ma'ruza: KECHKI"/);
        gridClick({ target: { dataset: { cyc: String(+(g.match(/data-cyc="(\d+)"/) || [])[1]) }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) },
            preventDefault() {}, stopPropagation() {} });
        assert.match(appended[appended.length - 1].innerHTML, /⚠ Zalda bir vaqtda ikki ma'ruza: KECHKI/);
        // Ikkala yarmida ham (har xil) — ikkalasi ham.
        setLessons([half({ id: 82, training_type: 'lecture', hour: 1, hours: 1, group_name: 'p/p22-02c', group_names: ['p/p22-02c'], room_clash: 'ERTAGI' }),
            half({ id: 82, training_type: 'lecture', hour: 2, hours: 1, group_name: 'p/p22-02c', group_names: ['p/p22-02c'], room_clash: 'KECHKI' })]);
        ctx.renderGridCross('teacher');
        g = el('grid').innerHTML;
        assert.match(g, /title="ERTAGI · KECHKI"/);
        gridClick({ target: { dataset: { cyc: String(+(g.match(/data-cyc="(\d+)"/) || [])[1]) }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) },
            preventDefault() {}, stopPropagation() {} });
        assert.match(appended[appended.length - 1].innerHTML, /⚠ ERTAGI<\/div><div class="dp-note" style="color:#b45309">⚠ KECHKI/);
        setLessons([half({ id: 81, training_type: 'lecture', hour: 1, hours: 2, len_half: 1, group_name: 'p/p22-02b', group_names: ['p/p22-02b'],
            room_clash: "Zalda bir vaqtda ikki ma'ruza: d1/d22-06b, Klinik farmakologiya (o'qituvchi BOSHQA) — bloklarni bitta ma'ruza oqimiga birlashtiring" })]);
        ctx.renderGridCross('teacher');
        // Oynada ham.
        gridClick({ target: { dataset: { cyc: '0' }, closest() { return this; }, getBoundingClientRect: () => ({ right: 10, top: 10 }) }, preventDefault() {}, stopPropagation() {} });
        assert.match(appended[appended.length - 1].innerHTML, /⚠ Zalda bir vaqtda ikki ma'ruza: d1\/d22-06b/);
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

    // 6) Online sixth-course cycle lessons stay visible and do not create teacher/room clashes.
    ctx.weeklyLayer = 'internal';
    ctx.viewMode = 'teacher';
    ctx.selectedCourses.add(6);
    ctx.weekly = [{ ...saveWeekly[0], day: 1, pair: 1 }];
    ctx.api = async () => ({ lessons: [lesson({ id: 91, course: 6, day: 1, pair: 1, hour: 1, hours: 4, len_half: 4 })] });
    ctx.invalidateCycleWeek();
    ctx.renderGridCross('teacher');
    await tick(); await tick();
    assert.match(el('grid').innerHTML, /tt-cyc-chip prc tt-info-only/);
    assert.match(el('grid').innerHTML, /6-kurs · ma’lumot \(onlayn\)/);
    assert.doesNotMatch(el('grid').innerHTML, /outline:2px solid #ef4444/);
    console.log('timetable cross cycle: 6 scenarios passed');
    process.exitCode = 0;
})().catch(e => { console.error(e); process.exit(1); });
