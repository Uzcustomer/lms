const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };

// Amaliy dars bo'linmaydi (birlik): bo'lingan birlik belgisi ("Bo'lingan: N ta", kunlar, "Yaxlitlash" — faqat shu birlik), bitta bo'lakni
// katakka qo'yish va sariq katakda boshqa xona bilan joylash — butun birlik (unit-place). 03a misoli: 11-hafta, d1/d26-03a, Tibbiy biologiya.
// Umumiy genetika (NUROVA): 708676 seshanba 08:30; 708674, 708675 chorshanba 10:00, 12:00.
const code = cut('            // ===== Birlik: dars yaxlit joylanadi =====', "            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)")
    + cut('            // ===== Joylash (bosish yoki drag-and-drop uchun umumiy) =====', '            // ===== Sariq katak (faqat xona band)')
    + cut('            function conflictsAtDetail(card, day, pair, ignoredIds = [], opts = {}) {', '            // Joylash hukmi — ')
    + cut('            function placeRoomCandidates(card, d, p, rooms, rank) {', '            async function openPlaceRoomPick(card, d, p) {')
    + cut('            // Kartani (d, p) ga shu xona bilan joylash', "            // ===== Parallel bo'laklar")
    + cut('            // ===== "Vaqtincha kafedra mudiriga yozish"', '            function renderPanel() {');

const times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
const ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
const base = { faculty_name: '1-son davolash', specialty_name: 'davolash ishi', course: 1, group_name: 'd1/d26-03a', subject_name: 'Tibbiy biologiya. Umumiy genetika',
    training_type: 'practice', teacher_id: 9, teacher_name: 'NUROVA Z. A.', len_half: 2, students: 15, auditorium_code: 'R123', auditorium_name: '123-amaliyot xonasi' };
const mk = (id, day, pair, x = {}) => Object.assign({ id, day, pair }, base, x);
const calls = [];
const doc = { body: { children: [], appendChild(x) { this.children.push(x); } },
    createElement: () => ({ style: {}, setAttribute() {}, addEventListener() {}, remove() {}, querySelector: () => null }), getElementById: () => null };
const ctx = {
    document: doc, Date, Math, Object, String, JSON, Set, Map, Promise, Number,
    cards: [], overrides: {}, curWeek: 11, weeklyLayer: 'internal', BASE: '', board: { id: 7 }, TIMETABLE_READ_ONLY: false, TIMETABLE_ROLE: 'admin',
    WEEKLY_ADMIN: true, weeklyCanEdit: () => true, esc: s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'),
    cardLen: c => +c.len_half || 1, cardGroups: c => [c.group_name], cardGKeys: c => ['k|' + c.group_name], isCycleCardAny: () => false,
    boardDayNames: () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'],
    boardSchedule: () => times.map((t, i) => ({ type: 'pair', start: t, end: ends[i] })),
    effectiveCard: c => c, isNonBlocking: () => false, bolakSiblings: () => false, isSharedRoomName: () => false, weeksIntersect: () => true,
    describeCardBrief: c => c.subject_name, effPlace: c => vm.runInContext('placeInWeek', ctx)(c, ctx.curWeek),
    loadBoard: async () => { calls.push(['reload']); }, renderAll() {}, renderPanel() {}, confirm: () => true,
    api: async (url, method, body) => {
        calls.push([url, body && JSON.parse(JSON.stringify(body))]);
        if (url.endsWith('/unit-fix')) return ctx.fixReply;
        if (url.endsWith('/unit-place')) {
            const list = ctx.cards.filter(c => c.group_name === base.group_name);
            let at = body.pair;
            return { ok: true, unit: list.sort((x, y) => ctx.order.indexOf(x.id) - ctx.order.indexOf(y.id))
                .map(c => { const r = { id: c.id, active: true, day: body.day, pair: at, len_half: c.len_half }; at += c.len_half; return r; }) };
        }
        return { ok: true };
    },
};
vm.createContext(ctx);
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(code + '\nObject.assign(this, { placeUnitAt, unitState, unitIsSplit, unitDaysText, unitMovePlan, weeklySplitUnits, splitHeadHtml, runUnitFix, placeOneCard, placeCardWithRoom, placeRoomCandidates, unitFixErr, unitFixPending, unitFixPendingSync });', ctx);
ctx.weekActiveCards = () => ctx.cards;
const W = 11;
const setOv = (c, d, p) => { ctx.overrides[c.id + '|' + W] = { day: d, pair: p, cancelled: false }; };

(async () => {
    // 1) 03a: seshanba 08:30 + chorshanba 10:00, 12:00 — bo'lingan (turli kun); kunlar matni; "Bo'lingan: 1 ta" va "Yaxlitlash".
    const a = mk(708676, 2, 1), b = mk(708674, 3, 3), c = mk(708675, 3, 5);
    ctx.cards = [a, b, c];
    ctx.order = [708676, 708674, 708675];
    let st = ctx.unitState(a, W);
    assert.equal(st.split, true);
    assert.equal(st.partial, false);
    assert.deepEqual([...st.list.map(x => x.id)], [708676, 708674, 708675]);   // joyi bo'yicha (para), keyin id
    assert.equal(ctx.unitDaysText(st.list, W), 'Seshanba 08:30–09:50; Chorshanba 10:00–13:20');
    let units = ctx.weeklySplitUnits();
    assert.equal(units.size, 1);
    let html = ctx.splitHeadHtml(units);
    assert.match(html, /Bo'lingan: <b>1 ta<\/b>/);
    assert.match(html, /d1\/d26-03a · Tibbiy biologiya\. Umumiy genetika<\/a> — 6 soat \(2\+2\+2\): <span data-split-days>Seshanba 08:30–09:50; Chorshanba 10:00–13:20<\/span>/);
    assert.match(html, /<button type="button" data-unit-merge="[^"]+"[^>]*>Yaxlitlash<\/button>/);
    // Tahrir huquqi yo'q — tugma yo'q (belgi qoladi).
    ctx.TIMETABLE_READ_ONLY = true;
    assert.doesNotMatch(ctx.splitHeadHtml(units), /data-unit-merge/);
    ctx.TIMETABLE_READ_ONLY = false;

    // 2) Bir kunda oraliq bilan — ham bo'lingan; yaxlit (ketma-ket) — yo'q; bitta bo'lak joylanmagan — qisman (bo'lingan emas).
    setOv(a, 3, 9);
    assert.equal(ctx.unitState(a, W).split, true);
    assert.equal(ctx.unitDaysText(ctx.unitState(a, W).list, W), 'Chorshanba 10:00–13:20, 15:00–16:20');
    setOv(a, 3, 1);
    assert.equal(ctx.unitState(a, W).split, false);
    assert.equal(ctx.weeklySplitUnits().size, 0);
    assert.equal(ctx.splitHeadHtml(ctx.weeklySplitUnits()), '');
    ctx.overrides[a.id + '|' + W] = { day: null, pair: null, cancelled: false };
    st = ctx.unitState(a, W);
    assert.equal(st.partial, true);
    assert.equal(st.split, false);
    delete ctx.overrides[a.id + '|' + W];

    // 3) "Yaxlitlash" — faqat shu birlik (card_ids), action merge; sig'masa — sababi bandda (qizil), jadval qayta yuklanadi.
    units = ctx.weeklySplitUnits();
    const [key] = [...units.keys()];
    ctx.fixReply = { ok: true, done: 0, failed: [{ group: 'd1/d26-03a', subject: base.subject_name, day: 'Seshanba', card_ids: [708676, 708674, 708675],
        error: "6 soat sig'maydi: 13:30 dan keyin bo'sh joy yo'q — Guruh band: d1/d26-03a — Anatomiya" }] };
    // "Bir kunga sig'maydi…" tasdig'i — "Yo'q" (hozirgidek: sabab qatorda).
    const asked = [];
    ctx.ttYesNo = async (t, b) => { asked.push([t, b]); return false; };
    await ctx.runUnitFix(null, 'merge', [units.get(key)]);
    assert.deepEqual(asked, [["Bir kunga sig'maydi. Darsni shu hafta jadvalidan olib, joylanmaganlarga o'tkazaymi?",
        "6 soat sig'maydi: 13:30 dan keyin bo'sh joy yo'q — Guruh band: d1/d26-03a — Anatomiya"]]);
    assert.equal(calls.filter(x => x[0] === '/boards/7/unit-fix').length, 1);
    assert.deepEqual(calls.find(x => x[0] === '/boards/7/unit-fix'), ['/boards/7/unit-fix', { week: 11, action: 'merge', card_ids: [708676, 708674, 708675] }]);
    assert.ok(calls.some(x => x[0] === 'reload'));
    assert.match(ctx.splitHeadHtml(units), /pn-split-err[^>]*>✖ 6 soat sig'maydi: 13:30 dan keyin bo'sh joy yo'q — Guruh band/);

    // 3b) Sig'madi — server 422 (failed + sabab): xato tashlanadi, lekin sabab kartada (umumiy "Xatolik" emas).
    const api0 = ctx.api;
    ctx.api = async () => { const e = new Error("Yaxlitlab bo'lmadi: 6 soat sig'maydi"); e.status = 422;
        e.data = { ok: false, done: 0, failed: [{ group: 'd1/d26-03a', subject: base.subject_name, card_ids: [708676], error: "6 soat sig'maydi (422)" }] }; throw e; };
    await ctx.runUnitFix(null, 'merge', [units.get(key)]);
    assert.match(ctx.splitHeadHtml(units), /✖ 6 soat sig'maydi \(422\)/);
    // 3b2) "Ha" — ikkinchi so'rov unplace_if_no_fit bilan; birlik joylanmaganlarga o'tdi: panel tepasida xabar, sabab bloklari va
    //      "Joylash uchun tanlang" (birlik joylanguncha / hafta almashguncha).
    let n = 0;
    ctx.api = async (url, method, body) => {
        calls.push([url, body && JSON.parse(JSON.stringify(body))]);
        if (!n++) { const e = new Error("Yaxlitlab bo'lmadi"); e.status = 422;
            e.data = { ok: false, done: 0, failed: [{ group: 'd1/d26-03a', subject: base.subject_name, card_ids: [708676, 708674, 708675], error: "6 soat sig'maydi" }] }; throw e; }
        ['708676', '708674', '708675'].forEach(id => { ctx.overrides[id + '|11'] = { day: null, pair: null, cancelled: true }; });
        return { ok: true, done: 1, failed: [], message: "Yaxlitlab bo'lmadi — d1/d26-03a joylanmaganlarga o'tkazildi (sabab: 6 soat sig'maydi). Joylash uchun tanlang.",
            cleared: [{ group: 'd1/d26-03a', text: "Yaxlitlab bo'lmadi — d1/d26-03a joylanmaganlarga o'tkazildi (sabab: 6 soat sig'maydi).",
                conflict_items: [{ kind: 'group', who: 'd1/d26-03a', groups: ['d1/d26-03a'], subject: 'Anatomiya', time: '13:30–14:50', day: 3 }] }] };
    };
    ctx.ttYesNo = async () => true;
    ctx.ttToast = (h, t) => calls.push(['toast', h, t]);
    calls.length = 0;
    await ctx.runUnitFix(null, 'merge', [units.get(key)]);
    const fixCalls = calls.filter(x => x[0] === '/boards/7/unit-fix');
    assert.deepEqual(fixCalls.map(x => x[1]), [{ week: 11, action: 'merge', card_ids: [708676, 708674, 708675] },
        { week: 11, action: 'merge', card_ids: [708676, 708674, 708675], unplace_if_no_fit: true }]);
    assert.ok(calls.some(x => x[0] === 'reload'));
    assert.ok(calls.some(x => x[0] === 'toast' && /joylanmaganlarga o'tkazildi/.test(x[1]) && x[2] === 'warn'));
    const ch = ctx.unitClearedHtml();
    assert.match(ch, /data-pn-cleared/);
    assert.match(ch, /Yaxlitlab bo'lmadi — d1\/d26-03a joylanmaganlarga o'tkazildi \(sabab: 6 soat sig'maydi\)\./);
    assert.match(ch, /data-cleared-select="708676"[^>]*>Joylash uchun tanlang/);
    assert.match(ch, /Guruh band/);
    // Boshqa haftada — ko'rinmaydi; birlik qayta joylansa — yo'qoladi.
    ctx.curWeek = 12;
    assert.equal(ctx.unitClearedHtml(), '');
    ctx.curWeek = 11;
    ctx.overrides['708676|11'] = { day: 3, pair: 1, cancelled: false };
    assert.equal(ctx.unitClearedHtml(), '');
    ['708676', '708674', '708675'].forEach(id => { delete ctx.overrides[id + '|11']; });
    // 3c) Tasdiq rejimi (pending_approval): "Yaxlitlandi: 0 ta" oynasi chiqmaydi; qatorda "tasdiq kutilmoqda (№130513)", tugma o'chiq.
    ctx.api = api0;
    ctx.fixReply = { ok: true, pending_approval: true, change_request_id: 130513, message: "O'zgarish registrator ofisi va o'quv bo'limiga tasdiqlash uchun yuborildi." };
    const shown = doc.body.children.length;
    await ctx.runUnitFix(null, 'merge', [units.get(key)]);
    assert.equal(doc.body.children.length, shown);
    assert.match(ctx.splitHeadHtml(units), /data-unit-merge="[^"]*" disabled/);
    assert.match(ctx.splitHeadHtml(units), /⏳ tasdiq kutilmoqda \(№130513\)/);
    // Codex: belgi hafta (va doska) bo'yicha — boshqa haftada shu birlik o'chiq emas; so'rov kutilmay qolsa (bekor / rad) — olinadi.
    ctx.curWeek = 12;
    assert.doesNotMatch(ctx.splitHeadHtml(units), /tasdiq kutilmoqda/);
    ctx.curWeek = 11;
    ctx.pendingReqs = () => [{ id: 130513, status: 'pending' }];
    assert.equal(ctx.unitFixPendingSync(), false);
    assert.match(ctx.splitHeadHtml(units), /tasdiq kutilmoqda/);
    ctx.pendingReqs = () => [];
    assert.equal(ctx.unitFixPendingSync(), true);
    assert.doesNotMatch(ctx.splitHeadHtml(units), /tasdiq kutilmoqda/);

    // 4) Bitta bo'lakni katakka qo'yish (tanlash → katak, sudrash emas): butun birlik unit-place bilan — 708676 chorshanba 08:30 da,
    //    birinchi bo'lak boshi 1-qator; week-override (bitta bo'lak) yuborilmaydi.
    calls.length = 0;
    await ctx.placeOneCard(a, 3, 1);
    assert.deepEqual(calls[0], ['/cards/708676/unit-place', { layer: 'internal', week: 11, day: 3, pair: 1 }]);
    assert.ok(!calls.some(x => /week-override/.test(x[0])));
    assert.deepEqual([a, b, c].map(x => ctx.overrides[x.id + '|' + W] && [ctx.overrides[x.id + '|' + W].day, ctx.overrides[x.id + '|' + W].pair]), [[3, 1], [3, 3], [3, 5]]);
    // O'rtadagi bo'lak (708674) 13:30 ga qo'yilsa — birinchi bo'lak (708676) 12:00 dan, hammasi ketma-ket.
    calls.length = 0;
    ctx.order = [708676, 708674, 708675];
    await ctx.placeOneCard(b, 3, 7);
    assert.deepEqual(calls[0], ['/cards/708674/unit-place', { layer: 'internal', week: 11, day: 3, pair: 5 }]);
    // Codex: 2-bo'lak 2-qatorga — birinchi bo'lak 0-qatorga tushadi: xato, so'rov yuborilmaydi ("olib tashlash" bo'lib ketmasin).
    calls.length = 0;
    await assert.rejects(ctx.placeOneCard(b, 3, 2), /jadval chegarasidan tashqarida/);
    assert.equal(calls.length, 0);
    // Ataylab olib tashlash (d = p = null) — avvalgidek.
    await ctx.placeUnitAt(a, null, null);
    assert.deepEqual(calls[0], ['/cards/708676/unit-place', { layer: 'internal', week: 11 }]);
    // Bitta bo'lakli dars — avvalgidek week-override.
    const solo = mk(5001, 4, 1, { group_name: 'd1/d26-04a' });
    ctx.cards.push(solo);
    calls.length = 0;
    await ctx.placeOneCard(solo, 4, 3);
    assert.deepEqual(calls[0], ['/cards/5001/week-override', { week: 11, action: 'move', day: 4, pair: 3 }]);
    ctx.cards.pop();

    // 5) Sariq katak — boshqa xona bilan: butun birlik shu xona bilan (unit-place + auditorium_code), har bo'lak yozuviga xona.
    Object.keys(ctx.overrides).forEach(k => delete ctx.overrides[k]);
    calls.length = 0;
    await ctx.placeCardWithRoom(a, 4, 1, { code: 'R117', name: '117-xona', volume: 20 });
    assert.deepEqual(calls[0], ['/cards/708676/unit-place', { week: 11, layer: 'internal', day: 4, pair: 1, auditorium_code: 'R117' }]);
    assert.deepEqual([a, b, c].map(x => ctx.overrides[x.id + '|' + W].auditorium_code), ['R117', 'R117', 'R117']);

    // 6) Xona nomzodlari — butun birlik oralig'ida bo'sh bo'lishi kerak: 117-xona 12:00 da (708675 ning yangi vaqti) band — taklif qilinmaydi.
    Object.keys(ctx.overrides).forEach(k => delete ctx.overrides[k]);
    const busy117 = mk(6001, 4, 5, { group_name: 'd1/d26-09a', teacher_id: 77, teacher_name: 'BOSHQA', auditorium_code: 'R117', auditorium_name: '117-xona' });
    ctx.cards.push(busy117);
    const rooms = [{ code: 'R117', name: '117-xona', volume: 20 }, { code: 'R118', name: '118-xona', volume: 20 }];
    assert.deepEqual(ctx.placeRoomCandidates(a, 4, 1, rooms, null).map(r => r.code), ['R118']);
    // Bitta bo'lakli dars uchun (faqat 08:30–09:50) — 117-xona bo'sh.
    assert.deepEqual(ctx.placeRoomCandidates(solo, 4, 1, rooms, null).map(r => r.code), ['R117', 'R118']);
    // unitMovePlan: ma'ruza va bitta bo'lakli dars — null.
    assert.equal(ctx.unitMovePlan(solo, 1, W), null);
    assert.equal(ctx.unitMovePlan(Object.assign({}, a, { training_type: 'lecture' }), 1, W), null);
    assert.deepEqual(ctx.unitMovePlan(c, 9, W).map(([x, p]) => [x.id, p]), [[708676, 5], [708674, 7], [708675, 9]]);

    console.log('timetable unit split: 6 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
