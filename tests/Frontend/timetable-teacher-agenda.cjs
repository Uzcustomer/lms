const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

// 1) Havolalar: "O'qituvchi band" yozuvida o'qituvchi nomi — kun jadvali oynasi (teacher_id, to'qnashuv sanasi, ko'chiriladigan dars).
const c1 = { esc, scopeMode: 'all', cards: [], focusLinkHtml: () => '' };
vm.createContext(c1);
vm.runInContext(cut("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + '\nObject.assign(this, { agendaLinkHtml, agendaLinksHtml, cfLineHtml });', c1);
const dataOf = html => JSON.parse(html.match(/data-agenda="([^"]+)"/)[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&'));
const line = c1.cfLineHtml({ kind: 'teacher_busy', type: 'teacher', who: 'MUXAMMADIYEV S. B.', teacher_id: 7, teacher_name: 'MUXAMMADIYEV S. B.',
    groups: ['d1/d22-01a'], date_from: '2026-11-19', dates: '19.11–21.11', past: false, todo: 'x' }, { move: 'c|K|2026-11-19' });
assert.match(line, /<b>O'qituvchi band:<\/b> <a href="#" class="tt-agenda-link" data-agenda="[^"]+" title="[^"]+">MUXAMMADIYEV S\. B\.<\/a> — d1\/d22-01a/);
assert.deepEqual(dataOf(line), { teacher_id: 7, teacher_name: 'MUXAMMADIYEV S. B.', date: '2026-11-19', move: 'c|K|2026-11-19' });
// teacher_id yo'q (eski yozuv) yoki boshqa tur — oddiy matn.
assert.doesNotMatch(c1.cfLineHtml({ kind: 'teacher_busy', type: 'teacher', who: 'X', groups: [], past: false }), /data-agenda/);
assert.doesNotMatch(c1.cfLineHtml({ kind: 'room_busy', type: 'room', who: '305', teacher_id: 7, date_from: '2026-11-19', groups: [], past: false }), /data-agenda/);
// Haftalik yozuv (who yo'q, teacher_name bor; sana — date_from).
assert.deepEqual(dataOf(c1.cfLineHtml({ kind: 'teacher_busy', type: 'teacher', teacher_id: 9, teacher_name: 'USTOZ', groups: ['p-01'],
    date_from: '2026-11-04', date_to: '2026-11-04', past: false }, { move: 'w|5|9' })), { teacher_id: 9, teacher_name: 'USTOZ', date: '2026-11-04', move: 'w|5|9' });
// Ro'yxat (biriktirish ogohlantirishi, Takliflar): har o'qituvchi + sana bitta havola; boshqa turlar yo'q.
const links = c1.agendaLinksHtml([{ kind: 'teacher_busy', teacher_id: 7, teacher_name: 'MUX', dates: ['2026-11-19', '2026-11-21'] },
    { kind: 'teacher_busy', teacher_id: 7, teacher_name: 'MUX', dates: ['2026-11-19'] }, { kind: 'room_busy', teacher_id: null, dates: ['2026-11-19'] },
    { kind: 'teacher_busy', teacher_id: 8, teacher_name: 'BOSHQA', dates: ['2026-11-20'] }], 'w|5|10');
assert.equal((links.match(/data-agenda/g) || []).length, 2);
assert.match(links, />📅 MUX jadvali, 19\.11<\/a>/);
assert.match(links, />📅 BOSHQA jadvali, 20\.11<\/a>/);
assert.equal(c1.agendaLinksHtml([], null), '');
assert.equal(c1.agendaLinksHtml([{ kind: 'teacher_busy', teacher_id: 7 }], null), '');   // sana yo'q
assert.equal(c1.agendaLinksHtml([{ kind: 'room_busy', teacher_id: 9, teacher_name: 'X', dates: ['2026-11-19'] }], null), '');   // faqat o'qituvchi bandligi
// O'qituvchi yoki sana yo'q — havola emas, oddiy matn.
assert.equal(c1.agendaLinkHtml({ teacher_id: 0, teacher_name: 'X', date: '2026-11-19' }, 'X'), 'X');
assert.equal(c1.agendaLinkHtml({ teacher_id: 7, teacher_name: 'X' }, 'X'), 'X');

// 2) Oyna: yuklash, chizish, tanlash va ko'chirish.
const calls = [];
let mount = null;
const listeners = {};
const fakeEl = () => ({ setAttribute() {}, addEventListener(t, f) { this['on' + t] = f; }, remove() { this.removed = true; mount = null; } });
const responses = [];
const c2 = {
    esc, console, URLSearchParams, Promise, JSON, Object, Date, String, Set,
    board: { id: 3 }, BASE: '/b', weeklyLayer: 'internal', cycleLayer: null, viewMode: 'week',
    cfDate: d => String(d || '').split('-').reverse().slice(0, 2).join('.'),
    cfGroups: g => (g || []).join(', '),
    askSugReason: async () => 'sabab',
    ttToast: (h, t) => calls.push(['toast', h, t]),
    loadChangeRequests: async () => calls.push(['loadChangeRequests']),
    loadBoard: async id => calls.push(['loadBoard', id]),
    loadCyclePlan: async () => calls.push(['loadCyclePlan']),
    api: async (url, method, body) => { calls.push([url, method || 'GET', body ? JSON.parse(JSON.stringify(body)) : null]); return responses.shift() || {}; },
    document: {
        addEventListener: (t, f) => { listeners[t] = f; }, removeEventListener: t => { delete listeners[t]; },
        getElementById: id => (id === 'ttAgendaModal' ? mount : null),
        createElement: () => (mount = fakeEl()),
        body: { appendChild() {} },
    },
};
vm.createContext(c2);
vm.runInContext(cut("            // ===== O'qituvchi kun/hafta jadvali (teacher-agenda) =====", "            // \"Ko'rsatish\" havolasi bosilganda (capture")
    + '\nObject.assign(this, { ttAgendaOpen, agendaClick, agendaStep, agendaApplyRequest, getSt: () => ttAgenda });', c2);

const day = {
    title: 'MUXAMMADIYEV S. B. — 19.11.2026 (payshanba)', range: 'day',
    dates: [{ date: '2026-11-19', dow: 4, label: '19.11 (payshanba)' }],
    pairs: [1, 2, 3, 4, 5, 6].map(k => ({ no: k, from: 2 * k - 1, to: 2 * k + 1, start: ['08:30', '10:00', '12:00', '13:30', '15:00', '16:30'][k - 1],
        end: ['09:50', '11:20', '13:20', '14:50', '16:20', '17:50'][k - 1], label: ['I', 'II', 'III', 'IV', 'V', 'VI'][k - 1] + ' para' })),
    entries: [
        { date: '2026-11-19', from: 1, to: 7, start: '08:30', end: '13:20', kind: 'cycle', type: 'A', subject: 'Klinik farmakologiya', groups: ['d1/d22-01a'],
            room: null, key: 'c|A|2026-11-19', conflict: true, hidden: false },
        { date: '2026-11-19', from: 7, to: 9, start: '13:30', end: '14:50', kind: 'weekly', type: 'A', subject: 'Farmakologiya', groups: ['pp/2024-01b'],
            room: '102-xona', key: 'w|5|10', conflict: true, hidden: false },
        { date: '2026-11-19', from: 7, to: 9, start: '13:30', end: '14:50', kind: 'weekly', type: null, subject: null, groups: [], room: null, key: null,
            conflict: true, hidden: true, label: 'band: boshqa fakultet' },
    ],
    free: { '2026-11-19': [5, 6] },
    move: { key: 'w|5|10', kind: 'weekly', label: 'Farmakologiya (A) — pp/2024-01b', date: '2026-11-19', week: 10, card_id: 5, via: 'apply', why: null,
        rest_weeks: [10, 11, 12], options: { '2026-11-19': {
            5: { ok: false, why: "Guruh band: pp/2024-01b — Anatomiya · Xona sig'maydi: 10 o'rin, 12 talaba (+2)", room: null, day: 4, pair: 9 },
            6: { ok: true, why: "Xona sig'maydi: 10 o'rin, 12 talaba (+2)", room: { code: 'R1', name: '101-xona', volume: 30 }, day: 4, pair: 11 } } }, block: {} },
};
const tick = () => new Promise(r => setImmediate(r));
(async () => {
    responses.push(day);
    await c2.ttAgendaOpen({ teacher_id: '7', teacher_name: 'MUXAMMADIYEV S. B.', date: '2026-11-19', move: 'w|5|10' });
    assert.equal(calls[0][0], '/b/boards/3/teacher-agenda?teacher_id=7&date=2026-11-19&range=day&layer=internal&move=w%7C5%7C10');
    const html = mount.innerHTML;
    assert.match(html, /<b>MUXAMMADIYEV S\. B\. — 19\.11\.2026 \(payshanba\)<\/b>/);
    assert.match(html, /data-ag="day" class="on">Kun<\/button><button type="button" data-ag="week">Hafta/);
    // To'qnashuvdagilar qizil; ko'chiriladigani — ko'k ramka; qamrovdan tashqarisi — faqat "band: boshqa fakultet".
    assert.match(html, /<div class="ag-e ag-conf"><span class="ag-time">08:30–13:20<\/span> Klinik farmakologiya \(A\) — d1\/d22-01a/);
    assert.match(html, /<div class="ag-e ag-conf ag-moving"><span class="ag-time">13:30–14:50<\/span> Farmakologiya \(A\) — pp\/2024-01b · 102-xona/);
    assert.match(html, /<div class="ag-e ag-conf ag-hidden"><span class="ag-time">13:30–14:50<\/span> band: boshqa fakultet/);
    // 6 soatlik amaliyot — II va III parada davomi.
    assert.equal((html.match(/↳ Klinik farmakologiya \(A\)/g) || []).length, 2);
    // V para — o'chiq, sababi bilan; VI para — xona taklifi bilan.
    assert.match(html, /bo‘sh <button type="button" class="ag-mv" disabled title="Guruh band: pp\/2024-01b — Anatomiya · Xona sig'maydi: 10 o'rin, 12 talaba \(\+2\)">Shu vaqtga ko‘chirish<\/button><div class="ag-why">Guruh band/);
    assert.match(html, /<button type="button" class="ag-mv" data-ag-pick="2026-11-19\|6" title="[^"]+">Shu vaqtga ko‘chirish \(101-xona\)<\/button><div class="ag-why ag-warn">Xona sig'maydi: 10 o'rin, 12 talaba \(\+2\) → bo‘sh xona: 101-xona \(30 o‘rin\)<\/div>/);
    assert.match(html, /Ko‘chiriladigan dars: <b>Farmakologiya \(A\) — pp\/2024-01b<\/b>, 19\.11/);

    // VI para tanlanadi → "shu va keyingi haftalar" → Qo'llash: Takliflar "move" amallari (har hafta, 101-xona bilan), admin — apply.
    const el = (sel, obj) => ({ target: { closest: s => (s === sel ? obj : null) }, currentTarget: mount });
    // Para tanlanganda keyingi haftalar serverda tekshiriladi (rest_for): avval band javob — "shu va keyingi haftalar" o'chiq.
    const withRest = rest => Object.assign({}, day, { move: Object.assign({}, day.move, { rest }) });
    responses.push(withRest({ date: '2026-11-19', pair: 6, ok: false, why: "11-hafta (26.11): Guruh band: pp/2024-01b — Fizika" }));
    calls.length = 0;
    c2.agendaClick(el('[data-ag-pick]', { disabled: false, dataset: { agPick: '2026-11-19|6' } }));
    await tick(); await tick();
    assert.match(calls[0][0], /move=w%7C5%7C10&rest_for=2026-11-19%7C6$/);
    assert.match(mount.innerHTML, /<div class="ag-pick"><div><b>Farmakologiya \(A\) — pp\/2024-01b<\/b> → 19\.11, VI para \(16:30–17:50\), xona: 101-xona<\/div>/);
    assert.match(mount.innerHTML, /value="rest" disabled> shu va keyingi haftalar \(3 ta\) <span class="ag-why">— 11-hafta \(26\.11\): Guruh band/);
    // "rest" tanlansa ham yuborilmaydi (server tasdiqlamagan).
    c2.agendaClick(el('input[name="agChoice"]', { value: 'rest' }));
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'submit' } }));
    await tick();
    assert.equal(calls.length, 0);
    // Keyingi haftalar bo'sh — tanlov yoqiladi.
    c2.getSt().pick = null;
    responses.push(withRest({ date: '2026-11-19', pair: 6, ok: true, why: null }));
    c2.agendaClick(el('[data-ag-pick]', { disabled: false, dataset: { agPick: '2026-11-19|6' } }));
    await tick(); await tick();
    assert.match(mount.innerHTML, /value="rest"> shu va keyingi haftalar \(3 ta\)<\/label>/);
    c2.agendaClick(el('input[name="agChoice"]', { value: 'rest' }));
    assert.equal(c2.getSt().pick.choice, 'rest');
    responses.push({ message: "Taklif qo'llandi." }, day);
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'submit' } }));
    await tick(); await tick(); await tick();
    assert.deepEqual(calls[0], ['/b/boards/3/week-suggestions/apply', 'POST', { week: 10, card_id: 5, type: 'agenda', title: "O'qituvchi jadvalidan ko'chirish",
        actions: [10, 11, 12].map(w => ({ op: 'move', card_id: 5, week: w, day: 4, pair: 11, auditorium_code: 'R1' })) }]);
    assert.deepEqual(calls.slice(1, 4).map(c => c[0]), ['toast', 'loadChangeRequests', 'loadBoard']);
    assert.match(calls[4][0], /teacher-agenda\?/);   // oyna yangilanadi
    assert.doesNotMatch(calls[4][0], /rest_for/);   // tanlov tozalandi
    assert.equal(c2.getSt().pick, null);
    assert.equal(c2.getSt().msg.kind, 'ok');

    // O'qituvchi: so'rov (sabab bilan), faqat shu hafta.
    const mvP = Object.assign({}, day.move, { via: 'propose' });
    assert.deepEqual(JSON.parse(JSON.stringify(c2.agendaApplyRequest(mvP, day.move.options['2026-11-19'][6], { date: '2026-11-19', pair: 6, choice: 'one' }, 'sabab'))),
        { path: '/week-suggestions/propose', body: { week: 10, card_id: 5, type: 'agenda', title: "O'qituvchi jadvalidan ko'chirish",
            actions: [{ op: 'move', card_id: 5, week: 10, day: 4, pair: 11, auditorium_code: 'R1' }], reason: 'sabab' } });
    // Sikl: admin — shu kun (cycle-practice-starts, units); o'qituvchi — butun blok (cycle-time-proposal, scope rest, blokning kelgusi kuni).
    const cyc = { kind: 'cycle', via: 'apply', block_date: '2026-11-20', unit: { key: 'K', unit: 'd1/d22-01b', specialty_name: 'davolash ishi', course: 5,
        subject_name: 'Klinik farmakologiya', group_name: 'd1/d22-01b' } };
    assert.deepEqual(JSON.parse(JSON.stringify(c2.agendaApplyRequest(cyc, { ok: true, start_hour: 9 }, { date: '2026-11-19', pair: 5, choice: 'one' }, ''))),
        { path: '/cycle-practice-starts', body: { specialty_name: 'davolash ishi', course: 5, subject_name: 'Klinik farmakologiya', group_name: 'd1/d22-01b',
            date: '2026-11-19', scope: 'day', start_hour: 9, units: ['d1/d22-01b'] } });
    assert.deepEqual(JSON.parse(JSON.stringify(c2.agendaApplyRequest(Object.assign({}, cyc, { via: 'propose' }), { ok: true, start_hour: 11 },
        { date: '2026-11-19', pair: 6, choice: 'block' }, 'sabab'))),
        { path: '/cycle-time-proposal', body: { specialty_name: 'davolash ishi', course: 5, subject_name: 'Klinik farmakologiya', group_name: 'd1/d22-01b',
            date: '2026-11-20', scope: 'rest', start_hour: 11, reason: 'sabab' } });

    // ◀ ▶: kun (yakshanba o'tkaziladi) va hafta.
    assert.equal(c2.agendaStep('2026-11-21', 'day', 1), '2026-11-23');
    assert.equal(c2.agendaStep('2026-11-23', 'day', -1), '2026-11-21');
    assert.equal(c2.agendaStep('2026-11-19', 'week', 1), '2026-11-26');
    // Hafta ko'rinishi: kunlar × paralar.
    const week = Object.assign({}, day, { range: 'week', dates: [{ date: '2026-11-16', label: '16.11 (dushanba)' }, { date: '2026-11-19', label: '19.11 (payshanba)' }],
        free: { '2026-11-16': [1, 2, 3, 4, 5, 6], '2026-11-19': [5, 6] } });
    responses.push(week);
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'week' } }));
    await tick(); await tick();
    assert.match(calls[0][0], /range=week/);
    assert.match(mount.innerHTML, /<table class="ag-week"><tr><th><\/th><th>16\.11 \(dushanba\)<\/th><th>19\.11 \(payshanba\)<\/th><\/tr>/);
    assert.match(mount.innerHTML, /data-ag-pick="2026-11-19\|6"[^>]*>→ ko‘chirish<\/button>/);
    // Qatlam — oyna ochilgan ko'rinishniki: sikl jadvalida sikl qatlami (haftalik qatlam tashqi bo'lsa ham ichki).
    c2.viewMode = 'cycle'; c2.cycleLayer = null; c2.weeklyLayer = 'external';
    responses.push(week);
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'next' } }));
    await tick(); await tick();
    assert.match(calls[0][0], /layer=internal/);
    c2.cycleLayer = 'external';
    responses.push(week);
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'prev' } }));
    await tick(); await tick();
    assert.match(calls[0][0], /layer=external/);
    c2.viewMode = 'week'; c2.weeklyLayer = 'external';
    responses.push(week);
    calls.length = 0;
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'next' } }));
    await tick(); await tick();
    assert.match(calls[0][0], /layer=external/);
    c2.weeklyLayer = 'internal';
    // Kun/hafta tez almashsa — kechikkan (eski) javob yangisining ustiga yozilmaydi.
    const pending = [];
    const realApi = c2.api;
    c2.api = url => new Promise(res => pending.push([url, res]));
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'day' } }));
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'week' } }));
    assert.equal(pending.length, 2);
    pending[1][1](Object.assign({}, week, { title: 'YANGI' }));
    await tick();
    pending[0][1](Object.assign({}, day, { title: 'ESKI' }));
    await tick(); await tick();
    assert.equal(c2.getSt().data.title, 'YANGI');
    assert.match(mount.innerHTML, /<b>YANGI<\/b>/);
    c2.api = realApi;
    // Yopish.
    c2.agendaClick(el('[data-ag]', { dataset: { ag: 'close' } }));
    assert.equal(c2.getSt(), null);
    assert.equal(mount, null);
    console.log('timetable teacher agenda: 4 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
