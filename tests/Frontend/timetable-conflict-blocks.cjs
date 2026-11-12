const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// To'qnashuv sababi turlarga ajratilgan bloklarda (panel kartasi oynasi, haqiqiy brauzer DOM — Playwright): "Shu joyga joylash" tugmasi va
// ko'rinmaslik sababi (admin, registrator ofisi, kichik admin, kafedra mudiri), rad etilganda / joylangan-to'qnashuvdagi darsda bloklar
// ("Xona band: 123-amaliyot xonasi", "O'qituvchi band: NUROVA Z. A.", "Guruh band: …", sig'im va kun oxiri — alohida), [Ko'rsatish] —
// xona / o'qituvchi kunlik ro'yxati (to'qnashgan qator qizil, shu dars belgilangan), yechimlar; fokus bannerida xona NOMI; sikl bloklari.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };
const code = cut('            // ===== Birlik: dars yaxlit joylanadi =====', "            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)")
    + cut('            function conflictsAtDetail(card, day, pair, ignoredIds = [], opts = {}) {', '            // Joylash hukmi — ')
    + cut('            // ===== "Vaqtincha kafedra mudiriga yozish"', '            function renderPanel() {')
    + cut("            // Uzun guruh ro'yxati: avval 4 tasi", '            // "O\'qituvchi band": o\'qituvchi nomi')
    + cut('            function focusBannerText(show, groups) {', '            function renderFocusBar() {')
    // Panel kartasidagi to'qnashuv belgisi ([data-wcf]) — sahifadagi asl ishlovchi.
    + cut("            // Panel (sikl va haftalik kartalar) to'qnashuv belgisi", "            // Haftalik panel kartasining to'qnashuvlari");
const cycCode = cut("            // Sikl karta oynasi: to'qnashuvlar turlarga ajratilgan bloklarda", "            // Panel kartasi oynasi: to'qnashuvlar (bo'lsa), havolalar va amallar.");

const stubs = `
    window.calls = []; window.agenda = []; window.focus = []; window.roomPick = []; window.mudirOpen = []; window.reply = {};
    const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const BASE = '', board = { id: 7 };
    let TIMETABLE_READ_ONLY = false, TIMETABLE_ROLE = 'admin', WEEKLY_ADMIN = true, curWeek = 11, weeklyLayer = 'internal', selected = null, audCache = null;
    const times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    const ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    const ab = ['I', 'I', 'II', 'II', 'III', 'III', 'IV', 'IV', 'V', 'V', 'VI', 'VI'];
    const boardSchedule = () => times.map((t, i) => ({ type: 'pair', start: t, end: ends[i], abbr: ab[i] }));
    const boardDayNames = () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const weekMonday = () => new Date(2026, 10, 23);
    const $ = id => document.getElementById(id), cardLen = c => +c.len_half || 1, cardGroups = c => [c.group_name], cardGKeys = c => ['F¦S¦1¦' + c.group_name], isCycleCardAny = () => false;
    const isNonBlocking = () => false, bolakSiblings = () => false, isSharedRoomName = () => false, weeksIntersect = () => true, describeCardBrief = c => c.subject_name;
    const effectiveCard = c => c, teacherFullNames = {}, teacherPhones = {}, inactiveRooms = {}, roomInactive = () => false, ROOM_OFF_TEXT = '';
    const weeklyCanEdit = () => !TIMETABLE_READ_ONLY && (WEEKLY_ADMIN || (weeklyLayer === 'external' ? false : TIMETABLE_ROLE === 'kafedra_mudiri'));
    const weeklyEditGuard = () => {}, rememberLayerPlace = () => {}, renderAll = () => {}, renderPanel = () => {}, renderGrid = () => {}, loadBoard = async () => {};
    const ttAgendaOpen = i => window.agenda.push(JSON.parse(JSON.stringify(i))), ttFocusStart = s => window.focus.push(JSON.parse(JSON.stringify(s)));
    const openTeacherTimeProposal = () => {}, openPlaceRoomPick = (c, d, p) => window.roomPick.push([c.id, d, p]);
    window.placeRoom = [];
    const placeCardWithRoom = async (c, d, p, room) => { window.placeRoom.push([c.id, d, p, room.code]); return { ok: true }; };
    const ttToast = () => {};
    const CF_LABEL = { teacher_busy: "O'qituvchi band", group_busy: 'Guruh band', lecture_overlap: "Ma'ruza bilan ustma-ust", room_capacity: "Xona sig'maydi", room_busy: 'Xona band', room_lectures: 'Zal' };
    const cfLabel = k => CF_LABEL[k] || "To'qnashuv";
    const cfLineHtml = i => '<li class="cf-line">' + esc(cfLabel(i.kind) + ': ' + (cfWho(i) || '')) + '</li>';
    const focusDatesText = d => (d && d[0] ? cfDate(d[0]) : ''), focusTimeText = () => '';
    async function api(url, method, body) {
        window.calls.push([url, body ? JSON.parse(JSON.stringify(body)) : null]);
        const k = Object.keys(window.reply).find(x => url.includes(x));
        const r = k ? window.reply[k] : { ok: true, unit: [] };
        if (r && r.status >= 400) { const e = new Error(r.j.error); e.status = r.status; e.data = r.j; throw e; }
        return r;
    }
    let cards = [], overrides = {}, allCards = [], weeklyConflictItems = {};
    window.oldCfPop = 0;
    const openWeekCfPop = () => { window.oldCfPop++; }, openCfPop = () => {};
    const effPlace = c => placeInWeek(c, curWeek);
`;
const base = { faculty_name: '1-son davolash', specialty_name: 'davolash ishi', course: 1, training_type: 'practice', len_half: 2, students: 15,
    subject_name: 'Tibbiy biologiya. Umumiy genetika' };
// 03a: joylanmagan, tashqi jadvalda chorshanba 10:00 (3-qator); 01a: NUROVA, 123-amaliyot xonasi — chorshanba 10:00 (o'qituvchi va xona band).
const data = {
    card: Object.assign({}, base, { id: 708676, group_name: 'd1/d26-03a', teacher_id: 9, teacher_name: 'NUROVA Z. A.', auditorium_code: 'R123', auditorium_name: '123-amaliyot xonasi',
        auditorium_volume: 17, ext_day: 3, ext_pair: 3, day: null, pair: null }),
    blocker: Object.assign({}, base, { id: 708662, group_name: 'd1/d26-01a', teacher_id: 9, teacher_name: 'NUROVA Z. A.', auditorium_code: 'R123', auditorium_name: '123-amaliyot xonasi',
        day: 3, pair: 3 }),
    groupBusy: Object.assign({}, base, { id: 9001, group_name: 'd1/d26-03a', subject_name: 'Anatomiya', teacher_id: 33, teacher_name: 'SHARIPOVA F. S.', auditorium_code: 'R117',
        auditorium_name: '117-xona', day: 3, pair: 3 }),
};

(async () => {
    const browser = await chromium.launch();
    try {
        const p = await (await browser.newContext({ viewport: { width: 1200, height: 900 } })).newPage();
        const errs = [];
        p.on('pageerror', e => errs.push(e.message));
        await p.setContent('<!doctype html><html><body><div id="cardPanel"></div></body></html>');
        await p.addScriptTag({ content: stubs + code + cycCode + `
            window.T = {
                set: o => { if ('role' in o) { TIMETABLE_ROLE = o.role; WEEKLY_ADMIN = ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'].includes(o.role); }
                    if ('layer' in o) weeklyLayer = o.layer; if ('week' in o) curWeek = o.week; if ('cards' in o) cards = o.cards; if ('ov' in o) overrides = o.ov;
                    if ('allCards' in o) allCards = o.allCards; if ('wci' in o) weeklyConflictItems = o.wci; },
                open: id => openPanelCardPop(cards.find(c => c.id === id), document.body),
                cbBlocks, cbTitle, cbBlockHead, cbLineText, cycCfBlocksHtml, focusBannerText, getSelected: () => selected,
            };` });
        assert.deepEqual(errs, []);
        const html = () => p.evaluate(() => document.getElementById('pnCardPop').innerHTML);
        const txt = sel => p.evaluate(s => [...document.querySelectorAll('#pnCardPop ' + s)].map(e => e.textContent.replace(/\s+/g, ' ').trim()), sel);
        const setCards = list => p.evaluate(l => T.set({ cards: l, ov: {} }), list);

        // 1) "Shu joyga joylash": admin, registrator ofisi, kichik admin, kafedra mudiri — ichki jadvalda tugma bor.
        await setCards([data.card, data.blocker]);
        for (const role of ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin', 'kafedra_mudiri']) {
            await p.evaluate(r => { T.set({ role: r, layer: 'internal', week: 11 }); T.open(708676); }, role);
            assert.match(await html(), /data-pop-place[ >=]/, role);
            assert.doesNotMatch(await html(), /data-pop-place-why/, role);
        }
        // Ko'rinmasa — sababi: tashqi jadval (kafedra mudiri uchun ham), hafta tanlanmagan, tashqi jadvalda joyi yo'q.
        await p.evaluate(() => { T.set({ role: 'kafedra_mudiri', layer: 'external' }); T.open(708676); });
        assert.deepEqual(await txt('[data-pop-place-why]'), ["Tashqi jadval tanlangan — Ichki'ga o'ting"]);
        await p.evaluate(() => { T.set({ role: 'registrator_ofisi', layer: 'internal', week: 0 }); T.open(708676); });
        assert.deepEqual(await txt('[data-pop-place-why]'), ['Hafta tanlanmagan']);
        await setCards([Object.assign({}, data.card, { ext_day: null, ext_pair: null }), data.blocker]);
        await p.evaluate(() => { T.set({ role: 'admin', week: 11 }); T.open(708676); });
        assert.deepEqual(await txt('[data-pop-place-why]'), ["Tashqi jadvalda joyi yo'q — 'Joylash uchun tanlash'dan foydalaning"]);
        // Ko'rish huquqi (o'qituvchi) — tugma ham, sabab ham yo'q.
        await p.evaluate(() => { T.set({ role: 'oqituvchi' }); T.open(708676); });
        assert.doesNotMatch(await html(), /data-pop-place/);

        // 2) Oldindan tekshiruv rad etdi: bitta dars (01a) ikki turda band — har blokda o'z sababi; sig'im (17 o'rin, 40 talaba) — alohida, sarlavhada emas.
        await setCards([Object.assign({}, data.card, { students: 40 }), data.blocker]);
        await p.evaluate(() => { T.set({ role: 'admin', week: 11, layer: 'internal' }); T.open(708676); });
        await p.click('#pnCardPop [data-pop-place]');
        assert.deepEqual(await txt('[data-cb-title]'), ["Joylab bo'lmadi — sabab: o'qituvchi band va xona band"]);
        assert.deepEqual(await txt('[data-cb-head]'), ["O'qituvchi band: NUROVA Z. A.", 'Xona band: 123-amaliyot xonasi', "Xona sig'maydi: 123-amaliyot xonasi — 17 o'rin, 40 talaba (+23)"]);
        assert.deepEqual(await txt('[data-cb-line]'), ['10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A. · 123-amaliyot xonasi',
            '10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A. · 123-amaliyot xonasi']);
        assert.ok(!(await p.evaluate(() => calls.some(c => /unit-place/.test(c[0])))), 'oldindan tekshiruvda rad — serverga yuborilmadi');
        // Yechimlar: "Joylash uchun tanlash" — har doim; "Vaqtincha kafedra mudiriga yozish" — oynada bitta (pastki qatorda), yechimlarda
        // takrorlanmaydi (6-bo'lim); xona tanlash — "Xona band" blokida.
        assert.deepEqual(await txt('[data-cb-sol]'), ['Joylash uchun tanlash']);
        assert.ok(((await html()).match(/Vaqtincha kafedra mudiriga yozish/g) || []).length <= 1);
        assert.doesNotMatch(await html(), /yana \d+ ta/);

        // 3) [Ko'rsatish] — xona: shu kunning barcha darslari (server: teacher-agenda, xona), to'qnashgani qizil, shu dars (joylanmoqchi) belgilangan.
        await p.evaluate(() => { window.reply['teacher-agenda?room_code=R123'] = { room: { code: 'R123', volume: 17 }, entries: [
            { start: '08:30', end: '09:50', groups: ['d1/d26-02a'], subject: 'Gistologiya', type: 'A', teacher: 'SHARIPOVA F. S.', card_ids: [5] },
            // Qamrovdan tashqari (boshqa fakultet) — vaqti band, tafsiloti yashirin.
            { start: '09:00', end: '09:40', hidden: true, label: 'band: boshqa fakultet', groups: [], subject: null, type: null, card_ids: [] },
            { start: '10:00', end: '11:20', groups: ['d1/d26-01a'], subject: 'Tibbiy biologiya. Umumiy genetika', type: 'A', teacher: 'NUROVA Z. A.', card_ids: [708662] }] }; });
        await p.click('#pnCardPop [data-cb-block="room"] [data-cb-show]');
        await p.waitForSelector('#pnCardPop [data-cb-block="room"] [data-cb-row]');
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-detail-head]'), ["123-amaliyot xonasi · 17 o'rin · Chorshanba, 25.11"]);
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-row]'), [
            '08:30–09:50 · d1/d26-02a · Gistologiya (amaliy) · SHARIPOVA F. S.',
            '09:00–09:40 · band: boshqa fakultet',
            '10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A.',
            '10:00–11:20 · d1/d26-03a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A. — shu dars (joylanmoqchi)']);
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-hit]'), ['10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A.']);
        assert.equal((await txt('[data-cb-block="room"] [data-cb-mine]')).length, 1);
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-hidden]'), ['09:00–09:40 · band: boshqa fakultet']);
        const q = await p.evaluate(() => calls.find(c => /teacher-agenda/.test(c[0]))[0]);
        assert.match(q, /room_code=R123&date=2026-11-25&range=day&layer=internal/);
        // Havolalar: xonaning haftalik jadvali; jadvalda ko'rsatish (to'qnashuvni ko'rish rejimi — xona NOMI bilan).
        await p.click('#pnCardPop [data-cb-block="room"] [data-cb-agenda]');
        assert.deepEqual(await p.evaluate(() => agenda.pop()), { room_code: 'R123', room_name: '123-amaliyot xonasi', date: '2026-11-25', range: 'week' });

        // 4) O'qituvchi bloki [Ko'rsatish] — o'qituvchining shu kungi darslari (xona bilan); "Jadvalda ko'rsatish" — fokus.
        await p.evaluate(() => T.open(708676));
        await p.click('#pnCardPop [data-pop-place]');
        await p.evaluate(() => { window.reply['teacher-agenda?teacher_id=9'] = { entries: [
            { start: '10:00', end: '11:20', groups: ['d1/d26-01a'], subject: 'Tibbiy biologiya. Umumiy genetika', type: 'A', room: '123-amaliyot xonasi', card_ids: [708662] },
            { start: '13:30', end: '14:50', groups: ['d1/d26-04a'], subject: 'Tibbiy biologiya. Umumiy genetika', type: 'A', room: '117-xona', card_ids: [708680] }] }; });
        await p.click('#pnCardPop [data-cb-block="teacher"] [data-cb-show]');
        await p.waitForSelector('#pnCardPop [data-cb-block="teacher"] [data-cb-row]');
        assert.deepEqual(await txt('[data-cb-block="teacher"] [data-cb-hit]'), ['10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · 123-amaliyot xonasi']);
        await p.click('#pnCardPop [data-cb-block="teacher"] [data-cb-focus]');
        const f = await p.evaluate(() => focus.pop());
        assert.deepEqual([f.kind, f.teacher_id, f.dates, f.hour_from, f.hour_to, f.card_ids, f.week], ['teacher_busy', 9, ['2026-11-25', '2026-11-25'], 3, 5, [708676, 708662], 11]);

        // 5) Faqat xona band — "Xona band" blokining o'zida "Boshqa xona tanlash" (5-bo'lim): shu vaqt uchun xonalar (server room-options —
        //    bo'sh tepada, band / biriktirilgan — kulrang, tanlab bo'lmaydi; qidiruv nom bo'yicha); boshqa sabab yo'q — "Shu xona bilan joylash".
        await setCards([data.card, Object.assign({}, data.blocker, { teacher_id: 44, teacher_name: 'BOSHQA' })]);
        await p.evaluate(() => { window.reply['/room-options'] = { date: '2026-11-25', rooms: [
            { code: 'R117', name: '117-xona', volume: 20, free: true, kind: null, why: 'shu binoda' },
            { code: 'R118', name: '118-xona', volume: 20, free: true, kind: null },
            { code: 'R200', name: '200-xona', volume: 30, free: false, kind: 'busy', busy: 'Band: d1/d26-05a · Anatomiya · 10:00' },
            { code: 'R5', name: '5-xona', volume: 30, free: false, kind: 'assigned', note: 'Biriktirilgan: OSTANAQULOV SH. F.' }] }; T.open(708676); });
        await p.click('#pnCardPop [data-pop-place]');
        assert.deepEqual(await txt('[data-cb-title]'), ["Joylab bo'lmadi — sabab: xona band"]);
        assert.deepEqual(await txt('[data-cb-sol]'), ['Joylash uchun tanlash']);
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-room-pick]'), ['Boshqa xona tanlash']);
        await p.click('#pnCardPop [data-cb-block="room"] [data-cb-room-pick]');
        await p.waitForSelector('#pnCardPop [data-cb-room-code]');
        assert.ok((await p.evaluate(() => calls.map(c => c[0]))).includes('/cards/708676/room-options?week=11&day=3&pair=3'));
        assert.deepEqual(await txt('[data-cb-room-code]'), ["117-xona · 20 o'rin · shu binoda", "118-xona · 20 o'rin",
            "200-xona · 30 o'rin · Band: d1/d26-05a · Anatomiya · 10:00", "5-xona · 30 o'rin · Biriktirilgan: OSTANAQULOV SH. F."]);
        assert.deepEqual(await p.$$eval('#pnCardPop [data-cb-room-code][disabled]', xs => xs.map(x => x.dataset.cbRoomCode)), ['R200', 'R5']);
        await p.fill('#pnCardPop [data-cb-room-q]', '118');
        assert.deepEqual(await txt('[data-cb-room-code]'), ["118-xona · 20 o'rin"]);
        await p.click('#pnCardPop [data-cb-room-code="R118"]');
        assert.deepEqual(await txt('[data-cb-room-apply]'), ['Shu xona bilan joylash']);
        await p.click('#pnCardPop [data-cb-room-apply]');
        assert.deepEqual(await p.evaluate(() => placeRoom.pop()), [708676, 3, 3, 'R118']);
        // Xona ham, o'qituvchi ham band — xona tanlanadi, qolgan sabab ko'rsatiladi, joylanmaydi.
        await setCards([data.card, data.blocker]);
        await p.evaluate(() => T.open(708676));
        await p.click('#pnCardPop [data-pop-place]');
        await p.click('#pnCardPop [data-cb-block="room"] [data-cb-room-pick]');
        await p.waitForSelector('#pnCardPop [data-cb-room-code="R117"]');
        await p.click('#pnCardPop [data-cb-room-code="R117"]');
        assert.deepEqual(await txt('[data-cb-room-rest]'), ["Xona tanlandi: 117-xona. Qolgan sabab: O'qituvchi band: NUROVA Z. A. — joylanmaydi."]);
        assert.equal(await p.$('#pnCardPop [data-cb-room-apply]'), null);
        await setCards([data.card, data.groupBusy]);
        await p.evaluate(() => T.open(708676));
        await p.click('#pnCardPop [data-pop-place]');
        assert.deepEqual(await txt('[data-cb-head]'), ['Guruh band: d1/d26-03a']);
        await p.click('#pnCardPop [data-cb-block="group"] [data-cb-show]');
        assert.deepEqual(await txt('[data-cb-block="group"] [data-cb-row]'), ['10:00–11:20 · Anatomiya (amaliy) · SHARIPOVA F. S. · 117-xona']);

        // 6) Server natijasi (422 conflict_items) — xuddi shu bloklar; kun oxiri — alohida blok.
        await setCards([data.card]);
        await p.evaluate(() => { window.reply['/unit-place'] = { status: 422, j: { error: "4 soat sig'maydi: …", conflict_items: [
            { kind: 'teacher', who: 'NUROVA Z. A.', card_id: 708662, piece_id: 708676, groups: ['d1/d26-01a'], subject: 'Tibbiy biologiya. Umumiy genetika', training_type: 'practice',
                teacher: 'NUROVA Z. A.', teacher_id: 9, room_name: '123-amaliyot xonasi', room_code: 'R123', date: '2026-11-25', time: '10:00–11:20' },
            { kind: 'day_end', who: '', hours: 4, free: 2, from: '16:30' }] } }; T.open(708676); });
        await p.click('#pnCardPop [data-pop-place]');
        await p.waitForSelector('#pnCardPop [data-cb-title]');
        assert.deepEqual(await txt('[data-cb-title]'), ["Joylab bo'lmadi — sabab: o'qituvchi band va kun oxirigacha joy yetmaydi"]);
        assert.deepEqual(await txt('[data-cb-head]'), ["O'qituvchi band: NUROVA Z. A.", "Kun oxiri: 4 soat sig'maydi (16:30 dan keyin 2 soat bo'sh)"]);
        await p.evaluate(() => { delete window.reply['/unit-place']; });

        // 7) Joylangan, to'qnashuvdagi dars — oyna ochilganda bloklar ("To'qnashuv — sabab: …"), "Shu joyga joylash" yo'q.
        await setCards([Object.assign({}, data.card, { day: 3, pair: 3 }), data.blocker]);
        await p.evaluate(() => T.open(708676));
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-title]'), ["To'qnashuv — sabab: o'qituvchi band va xona band"]);
        assert.doesNotMatch(await html(), /data-pop-place[ >=]/);

        // 7b) Codex: bo'laklari turli kunda turgan joylangan birlik — payshanbadagi bo'lak (708677) to'qnashuvi: "Jadvalda ko'rsatish" shu bo'lak
        //     vaqti va kartasi bilan (bosilgan kartaniki emas), kunlik ro'yxat sarlavhasi — payshanba.
        await setCards([Object.assign({}, data.card, { day: 1, pair: 1 }), Object.assign({}, data.card, { id: 708677, day: 4, pair: 5 }),
            Object.assign({}, data.blocker, { day: 4, pair: 5, auditorium_code: 'R900', auditorium_name: '900-xona' })]);
        await p.evaluate(() => T.open(708676));
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-head]'), ["O'qituvchi band: NUROVA Z. A."]);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="teacher"] [data-cb-show]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-block="teacher"] [data-cb-detail-head]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-block="teacher"] [data-cb-detail-head]'), ['NUROVA Z. A. · Payshanba, 26.11']);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="teacher"] [data-cb-focus]');
        const f2 = await p.evaluate(() => focus.pop());
        assert.deepEqual([f2.card_ids, f2.hour_from, f2.hour_to, f2.dates], [[708677, 708662], 5, 7, ['2026-11-26', '2026-11-26']]);

        // 7c) Codex: joylangan dars, xona band — "Boshqa xona tanlash" bosilgan bo'lakning o'z kuni va parasi bilan (birinchi bo'lak kuni emas);
        //     tanlangach — "Xonani almashtirish (shu hafta)"; bo'lingan joylangan darsda bu yechim yo'q.
        const busyRoom = Object.assign({}, data.blocker, { teacher_id: 44, teacher_name: 'BOSHQA', day: 3, pair: 5 });
        await setCards([Object.assign({}, data.card, { day: 3, pair: 3 }), Object.assign({}, data.card, { id: 708677, day: 3, pair: 5 }), busyRoom]);
        await p.evaluate(() => T.open(708677));
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-sol]'), ['Joylash uchun tanlash']);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-room-pick]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-room-code="R117"]');
        // Codex: bosilgan — ikkinchi bo'lak (12:00), ro'yxat esa birlik boshidan (10:00, 3-qator) — joylash (unitMovePlan) bilan bir xil.
        assert.ok((await p.evaluate(() => calls.map(c => c[0]))).includes('/cards/708677/room-options?week=11&day=3&pair=3'));
        await p.click('#pnCardPop [data-pop-cf] [data-cb-room-code="R117"]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-room-apply]'), ['Xonani almashtirish (shu hafta)']);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-room-apply]');
        assert.deepEqual(await p.evaluate(() => placeRoom.pop()), [708677, 3, 5, 'R117']);
        await setCards([Object.assign({}, data.card, { day: 1, pair: 1 }), Object.assign({}, data.card, { id: 708677, day: 3, pair: 5 }), busyRoom]);
        await p.evaluate(() => T.open(708677));
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-head]'), ['Xona band: 123-amaliyot xonasi']);
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-sol]'), ['Joylash uchun tanlash']);
        assert.equal(await p.$('#pnCardPop [data-pop-cf] [data-cb-room-pick]'), null);

        // 7d) Codex: bo'lingan dars bir o'qituvchi bilan ikki kunda to'qnashadi — har kun alohida blok (kun va sana bilan), har birining
        //     [Ko'rsatish] / fokusi o'z kuniniki.
        await setCards([Object.assign({}, data.card, { day: 1, pair: 1 }), Object.assign({}, data.card, { id: 708677, day: 4, pair: 5 }),
            Object.assign({}, data.blocker, { day: 1, pair: 1, auditorium_code: 'R900', auditorium_name: '900-xona' }),
            Object.assign({}, data.blocker, { id: 708663, day: 4, pair: 5, auditorium_code: 'R901', auditorium_name: '901-xona' })]);
        await p.evaluate(() => T.open(708676));
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-head]'), ["O'qituvchi band: NUROVA Z. A. (Dushanba, 23.11)", "O'qituvchi band: NUROVA Z. A. (Payshanba, 26.11)"]);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="teacher"]:nth-of-type(3) [data-cb-show]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-block="teacher"]:nth-of-type(3) [data-cb-detail-head]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-block="teacher"]:nth-of-type(3) [data-cb-detail-head]'), ['NUROVA Z. A. · Payshanba, 26.11']);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="teacher"]:nth-of-type(3) [data-cb-focus]');
        const f3 = await p.evaluate(() => focus.pop());
        assert.deepEqual([f3.card_ids, f3.hour_from, f3.dates], [[708677, 708663], 5, ['2026-11-26', '2026-11-26']]);

        // 7e) Codex: joylanmagan dars bo'laklari turli xonada (708676 — 123-xona, 708678 — 117-xona), 10:00 dan: 117-xona [Ko'rsatish]da
        //     "joylanmoqchi" qatori faqat shu xonadagi bo'lak (12:00–13:20), butun dars emas.
        await setCards([data.card, Object.assign({}, data.card, { id: 708678, auditorium_code: 'R117', auditorium_name: '117-xona' }),
            Object.assign({}, data.groupBusy, { group_name: 'd1/d26-09a', teacher_id: 55, teacher_name: 'BOSHQA', day: 3, pair: 5 })]);
        await p.evaluate(() => { window.reply['teacher-agenda?room_code=R117'] = { room: { code: 'R117', volume: 20 }, entries: [
            { start: '12:00', end: '13:20', groups: ['d1/d26-09a'], subject: 'Anatomiya', type: 'A', teacher: 'BOSHQA', card_ids: [9001] }] }; T.open(708676); });
        await p.click('#pnCardPop [data-pop-place]');
        assert.deepEqual(await txt('[data-cb-head]'), ['Xona band: 117-xona']);
        await p.click('#pnCardPop [data-cb-block="room"] [data-cb-show]');
        await p.waitForSelector('#pnCardPop [data-cb-block="room"] [data-cb-row]');
        assert.deepEqual(await txt('[data-cb-block="room"] [data-cb-mine]'), ['12:00–13:20 · d1/d26-03a · Tibbiy biologiya. Umumiy genetika (amaliy) · NUROVA Z. A. — shu dars (joylanmoqchi)']);

        // 7f) Panel kartasidagi "⚠ Xona band (1)" belgisi ([data-wcf]): karta oynasi yangi bloklar bilan, [Ko'rsatish] o'zi ochiq — xona kunlik
        //     ro'yxati, "Jadvalda ko'rsatish" va "Xonaning haftalik jadvali"; eski qisqa oyna ochilmaydi.
        await setCards([Object.assign({}, data.card, { day: 3, pair: 3 }), Object.assign({}, data.blocker, { teacher_id: 44, teacher_name: 'BOSHQA' })]);
        await p.evaluate(() => { document.getElementById('cardPanel').innerHTML = '<div class="pn-conflict" role="button" data-wcf="708676">⚠ Xona band (1)</div>'; });
        await p.click('#cardPanel [data-wcf]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-agenda]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-head]'), ['Xona band: 123-amaliyot xonasi']);
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-block="room"] [data-cb-agenda]'), ['Xonaning haftalik jadvali']);
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-block="room"] [data-cb-focus]'), ["Jadvalda ko'rsatish"]);
        assert.ok((await txt('[data-pop-cf] [data-cb-block="room"] [data-cb-row]')).length >= 2);
        assert.equal(await p.evaluate(() => window.oldCfPop), 0);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-agenda]');
        assert.deepEqual(await p.evaluate(() => agenda.pop()), { room_code: 'R123', room_name: '123-amaliyot xonasi', date: '2026-11-25', range: 'week' });
        // Hafta tanlanmagan (yoki tashqi jadval) — eski qisqa oyna.
        await p.evaluate(() => { T.set({ week: 0 }); });
        await p.click('#cardPanel [data-wcf]');
        assert.equal(await p.evaluate(() => window.oldCfPop), 1);
        await p.evaluate(() => { T.set({ week: 11 }); });

        // 7g) Codex: cheklangan ko'rinish (kafedra / mas'ul fanlar) — band qilgan dars ko'rinadigan ro'yxatda (cards) yo'q, faqat server
        //     yozuvida (weeklyConflictItems) bor: belgi bosilganda blok server yozuvidan quriladi — "Xona band: 123-amaliyot xonasi" va xona havolalari.
        const scopedCard = Object.assign({}, data.card, { day: 3, pair: 3 });
        const outOfScope = Object.assign({}, data.blocker, { teacher_id: 44, teacher_name: 'BOSHQA' });
        await p.evaluate(([sc, oo]) => T.set({ cards: [sc], ov: {}, allCards: [sc, oo], wci: { __week: 11, 708676: { 11: [{ kind: 'room_busy', type: 'room',
            room: 'R123', room_name: '123-amaliyot xonasi', groups: ['d1/d26-01a'], with_card: 708662, date_from: '2026-11-25', date_to: '2026-11-25', past: false,
            show: { kind: 'room_busy', room_code: 'R123', room_name: '123-amaliyot xonasi', groups: [], dates: ['2026-11-25', '2026-11-25'], hour_from: 3, hour_to: 5,
                hour_unit: 'row', card_ids: [708676, 708662], view: 'week', week: 11 } }] } } }), [scopedCard, outOfScope]);
        await p.click('#cardPanel [data-wcf]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-agenda]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-head]'), ['Xona band: 123-amaliyot xonasi']);
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-block="room"] [data-cb-agenda]'), ['Xonaning haftalik jadvali']);
        assert.equal(await p.evaluate(() => window.oldCfPop), 1);
        await p.click('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-focus]');
        assert.deepEqual((await p.evaluate(() => focus.pop())).card_ids, [708676, 708662]);
        await p.evaluate(() => T.set({ allCards: [], wci: {} }));

        // 7h) Codex: band qilgan dars boshqa kalendarli dasturda (25.11 — uning 10-haftasi): vaqti va xonasi server yozuvidagi o'z haftasidan
        //     (with_week/with_pair/with_room), tanlangan 11-hafta shabloni (juma 15:00, 200-xona) emas.
        const otherCal = Object.assign({}, data.blocker, { teacher_id: 44, teacher_name: 'BOSHQA', specialty_name: 'pediatriya', day: 5, pair: 9,
            auditorium_code: 'R200', auditorium_name: '200-xona' });
        await p.evaluate(([sc, oo]) => T.set({ cards: [sc], ov: {}, allCards: [sc, oo], wci: { __week: 11, 708676: { 11: [{ kind: 'room_busy', type: 'room',
            room: 'R123', room_name: '123-amaliyot xonasi', groups: ['d1/d26-01a'], with_card: 708662, with_week: 10, with_day: 3, with_pair: 3,
            with_room: 'R123', with_room_name: '123-amaliyot xonasi', date_from: '2026-11-25', date_to: '2026-11-25', past: false,
            show: { kind: 'room_busy', room_code: 'R123', room_name: '123-amaliyot xonasi', groups: [], dates: ['2026-11-25'], hour_from: 3, hour_to: 5,
                hour_unit: 'row', card_ids: [708676, 708662], view: 'week', week: 11 } }] } } }), [scopedCard, otherCal]);
        await p.click('#cardPanel [data-wcf]');
        await p.waitForSelector('#pnCardPop [data-pop-cf] [data-cb-block="room"] [data-cb-agenda]');
        assert.deepEqual(await txt('[data-pop-cf] [data-cb-line]'), ['10:00–11:20 · d1/d26-01a · Tibbiy biologiya. Umumiy genetika (amaliy) · BOSHQA · 123-amaliyot xonasi']);
        await p.evaluate(() => T.set({ allCards: [], wci: {} }));

        // 8) Fokus bannerida xona NOMI (kodi emas); sikl karta oynasi — turlarga ajratilgan bloklar.
        assert.equal(await p.evaluate(() => T.focusBannerText({ kind: 'room_busy', room_code: 'R123', room_name: '123-amaliyot xonasi', dates: ['2026-11-25'] }, [{ group: 'd1/d26-03a' }, { group: 'd1/d26-01a' }])),
            "To'qnashuvni ko'rish: Xona 123-amaliyot xonasi — d1/d26-03a va d1/d26-01a, 25.11");
        const cyc = await p.evaluate(() => { const d = document.createElement('div'); d.innerHTML = T.cycCfBlocksHtml([
            { kind: 'teacher_busy', type: 'teacher', teacher_name: 'NUROVA Z. A.', groups: ['d1/d26-01a'] },
            { kind: 'room_busy', type: 'room', room: '123-amaliyot xonasi', groups: ['d1/d26-01a'] },
            { kind: 'room_busy', type: 'room', room: '123-amaliyot xonasi', groups: ['d1/d26-02a'] }], 'k');
            return { title: d.querySelector('[data-cb-title]').textContent, heads: [...d.querySelectorAll('[data-cb-head]')].map(x => x.textContent),
                lines: d.querySelectorAll('[data-cb-block="room"] .cf-line').length }; });
        assert.deepEqual(cyc, { title: "To'qnashuv — sabab: o'qituvchi band va xona band", heads: ["O'qituvchi band: NUROVA Z. A.", 'Xona band: 123-amaliyot xonasi'], lines: 2 });
    } finally {
        await browser.close();
    }
    console.log('timetable conflict blocks: 8 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
