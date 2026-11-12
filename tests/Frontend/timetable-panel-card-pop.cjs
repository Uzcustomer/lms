const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Joylanmagan kartalar" paneli: karta oynasi (o'qituvchi, xona, tashqi jadval, havolalar), qisman joylangan belgisi va tugmalari,
// bitta birlikning "yaxlit joylash"i (card_ids), natija oynasi (alert emas).
const a = source.indexOf('            // ===== "Vaqtincha kafedra mudiriga yozish"');
const b = source.indexOf('            function renderPanel() {', a);
assert(a > 0 && b > a);
const helpers = source.slice(source.indexOf('            // ===== Birlik: dars yaxlit joylanadi ====='),
    source.indexOf("            // Dars turi filtri (Hammasi / Ma'ruza / Amaliy)"));
const times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
const ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
const ab = ['I', 'I', 'II', 'II', 'III', 'III', 'IV', 'IV', 'V', 'V', 'VI', 'VI'];

function el(tag) {
    const e = { tag, children: [], style: {}, attrs: {}, classList: { s: new Set(), add(c) { this.s.add(c); }, remove(c) { this.s.delete(c); }, contains(c) { return this.s.has(c); } },
        listeners: {}, innerHTML: '', offsetHeight: 200,
        setAttribute(k, v) { this.attrs[k] = v; }, addEventListener(t, f) { this.listeners[t] = f; }, remove() { this.removed = true; doc.body.children = doc.body.children.filter(x => x !== this); },
        contains(x) { return x === this; }, qs: doc.nextQs || null, querySelector(sel) { return this.qs ? this.qs(sel) : null; }, getBoundingClientRect: () => ({ left: 10, right: 200, top: 50 }) };
    return e;
}
const doc = { body: { children: [], appendChild(x) { this.children.push(x); } }, createElement: el,
    getElementById: id => doc.body.children.find(x => x.id === id && !x.removed) || (id === 'grid' ? grid : null),
    addEventListener() {}, removeEventListener() {} };
// Jadval: 01b guruhi ustunida (data-g) va boshqa guruh ustunida seshanba II para kataklari.
const tdMine = { dataset: { day: '2', pair: '3', g: 'F|S|1|d2/d26-01b (rus)' }, classList: el().classList, scrollIntoView() { this.scrolled = true; } };
const tdOther = { dataset: { day: '2', pair: '3', g: 'F|S|1|d2/d26-02a (rus)' }, classList: el().classList, scrollIntoView() {} };
const grid = { querySelectorAll: sel => (sel === '.tt-slot-flash' ? [] : sel.includes('[data-day="2"][data-pair="3"]') ? [tdOther, tdMine] : []) };

const base = { faculty_name: 'F', specialty_name: 'S', course: 1, group_name: 'd2/d26-01b (rus)', subject_name: 'Tibbiy va biologik fizika',
    training_type: 'practice', teacher_id: 5, teacher_name: 'CHORIYEVA G. Y.', students: 15 };
const cards = [
    Object.assign({ id: 1, len_half: 2, auditorium_code: 'R12', auditorium_name: "Tibbiyot klasteri, 1-qavat, 12-o'quv xona", auditorium_volume: 10, ext_day: 2, ext_pair: 3 }, base),
    Object.assign({ id: 2, len_half: 1 }, base),
    Object.assign({ id: 3, len_half: 2 }, base, { group_name: 'd2/d26-07b (rus)', subject_name: 'Tibbiyotda axborot texnologiyalari', teacher_id: 0, teacher_name: null }),
    Object.assign({ id: 4, len_half: 1 }, base, { group_name: 'd2/d26-07b (rus)', subject_name: 'Tibbiyotda axborot texnologiyalari', teacher_id: 0, teacher_name: null }),
];
const calls = [], agenda = [], proposals = [];
const ctx = {
    document: doc, window: { innerWidth: 1400, innerHeight: 900, ev: {}, addEventListener(t, f) { this.ev[t] = f; }, removeEventListener(t) { delete this.ev[t]; } }, setTimeout: () => 0, URLSearchParams, Date, Math, Object, String, JSON, Set, Map, Promise,
    cards, overrides: { '3|8': { day: 2, pair: 3, cancelled: false } }, curWeek: 8, weeklyLayer: 'internal', BASE: '', board: { id: 7 },
    TIMETABLE_READ_ONLY: false, TIMETABLE_ROLE: 'kafedra_mudiri', WEEKLY_ADMIN: false, effPlace: c => vm.runInContext('placeInWeek', ctx)(c, 8), $: () => null, teacherFullNames: { 5: 'CHORIYEVA GULNORA YUSUPOVNA' }, teacherPhones: { 5: { text: '+998 90 123 45 67', tel: '+998901234567' } }, audCache: null,
    esc: s => String(s), cardLen: c => c.len_half, cardGKeys: c => ['F|S|1|' + c.group_name], cardGroups: c => [c.group_name], isCycleCardAny: () => false,
    isSharedRoomName: () => false, weeklyConflictItems: {}, allCards: [], inactiveRooms: { R404: '404' }, roomInactive: code => !!code && code in ctx.inactiveRooms, ROOM_OFF_TEXT: 'Xona nofaol — almashtiring',
    effectiveCard: c => c, weeklyCanEdit: () => true, boardDayNames: () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'],
    boardSchedule: () => times.map((t, i) => ({ type: 'pair', start: t, end: ends[i], abbr: ab[i] })),
    weekMonday: () => new Date(2026, 9, 26), weeklyEditGuard() {}, rememberLayerPlace() {},
    conflictsAt: () => [], conflictsAtDetail: () => [], renderAll() {}, renderPanel() {}, renderGrid() {}, selected: null,
    ttAgendaOpen: info => agenda.push(JSON.parse(JSON.stringify(info))), openTeacherTimeProposal: c => proposals.push(c.id),
    loadBoard: async () => {}, confirm: () => true,
    api: async (url, method, body) => {
        calls.push([url, body && JSON.parse(JSON.stringify(body))]);
        if (url.includes('/mudir-placeholder?')) return ctx.phInfo;
        if (url.endsWith('/mudir-placeholder/revert')) { if (ctx.revertFail) throw new Error(ctx.revertFail); return { ok: true }; }
        if (url.endsWith('/mudir-placeholder')) return { ok: true };
        if (url.endsWith('/unit-fix')) return { done: 0, failed: [{ group: 'd2/d26-07b (rus)', subject: 'Tibbiyotda axborot texnologiyalari', day: 'Seshanba',
            error: "3 soat sig'maydi: 10:00 dan keyin 2 soat bo'sh", card_ids: [3, 4] }] };
        return { unit: [{ id: 1, active: true, day: 2, pair: 3 }, { id: 2, active: true, day: 2, pair: 5 }] };
    },
};
vm.createContext(ctx);
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(helpers + source.slice(a, b) + '; Object.assign(this, { phRevertErrSet: (c, v) => { phRevertErr[unitKey(c)] = v; }, openMudirPhConfirm, mudirPhSectionHtml, weeklyPhCards, mudirPhRevert, panelPopPlace, panelPopSplitError, panelPopMsgHtml, openPanelCardPop, partialBadgeHtml, runUnitFix, gotoGridSlot, unitState, unitFixErr, closePanelCardPop, placedSlotText });', ctx);
// dragPlan — haqiqiy (bo'sh joy).
vm.runInContext(source.slice(source.indexOf('            function dragPlan('), source.indexOf('            // Sudrash paytida tushadigan vaqt oralig')), ctx);

(async () => {
    // 1) Oyna: o'qituvchi to'liq F.I.Sh., xona va sig'im (talabalar ortig'i), tashqi jadval joyi, tugmalar.
    ctx.openPanelCardPop(cards[0], el());
    const pop = doc.getElementById('pnCardPop');
    assert.ok(pop);
    assert.match(pop.innerHTML, /O'qituvchi:<\/span> <a href="#" class="tt-agenda-link" data-pop-teacher title="CHORIYEVA GULNORA YUSUPOVNA · \+998 90 123 45 67">CHORIYEVA GULNORA YUSUPOVNA<\/a> <a href="tel:\+998901234567"[^>]*>· \+998 90 123 45 67<\/a>/);
    assert.match(pop.innerHTML, /data-pop-room>Tibbiyot klasteri, 1-qavat, 12-o'quv xona · 10 o'rin · 15 talaba \(\+5\)<\/a>/);
    assert.match(pop.innerHTML, /data-pop-ext>Seshanba, II para \(10:00–12:40\)<\/a>/);   // 3 soatlik dars (2 + 1)
    assert.match(pop.innerHTML, /data-pop-place/);
    assert.match(pop.innerHTML, /Joylash uchun tanlash/);
    const click = async attr => pop.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === attr, disabled: false }) }, preventDefault() {}, stopPropagation() {} });
    // Havolalar: o'qituvchi jadvali (shu hafta, shu karta ko'chiriladigan dars), xona bandligi, tashqi joy katagi.
    await click('data-pop-teacher');
    assert.deepEqual(agenda[0], { teacher_id: 5, teacher_name: 'CHORIYEVA G. Y.', date: '2026-10-27', range: 'week', move: 'w|1|8' });
    ctx.openPanelCardPop(cards[0], el());
    let p2 = doc.getElementById('pnCardPop');
    await p2.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === 'data-pop-room' }) }, preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(agenda[agenda.length - 1], { room_code: 'R12', room_name: "Tibbiyot klasteri, 1-qavat, 12-o'quv xona", date: '2026-10-27', range: 'week' });
    ctx.openPanelCardPop(cards[0], el());
    p2 = doc.getElementById('pnCardPop');
    await p2.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === 'data-pop-ext' }) }, preventDefault() {}, stopPropagation() {} });
    assert.ok(tdMine.classList.contains('tt-slot-flash') && tdMine.scrolled && !tdOther.classList.contains('tt-slot-flash'));
    // "Shu joyga joylash" — birlik bilan (unit-place, tashqi joy).
    await p2.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === 'data-pop-place' }) }, preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(calls[calls.length - 1], ['/cards/1/unit-place', { layer: 'internal', week: 8, day: 2, pair: 3 }]);

    // 2) Biriktirilmagan o'qituvchi/xona, tashqi jadvalda yo'q; ko'rish huquqi — joylash tugmalari yo'q.
    ctx.weeklyCanEdit = () => false;
    ctx.openPanelCardPop(cards[3], el());
    p2 = doc.getElementById('pnCardPop');
    assert.match(p2.innerHTML, /O'qituvchi:<\/span> <i>biriktirilmagan<\/i>/);
    assert.match(p2.innerHTML, /Xona:<\/span> <i>biriktirilmagan<\/i>/);
    assert.match(p2.innerHTML, /tashqi jadvalda yo'q/);
    assert.doesNotMatch(p2.innerHTML, /data-pop-place|data-pop-select/);
    // O'qituvchi roli — vaqt so'rovi tugmasi.
    ctx.TIMETABLE_ROLE = 'oqituvchi';
    ctx.openPanelCardPop(cards[0], el());
    p2 = doc.getElementById('pnCardPop');
    assert.match(p2.innerHTML, /data-pop-propose/);
    await p2.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === 'data-pop-propose' }) }, preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(proposals, [1]);
    ctx.TIMETABLE_ROLE = 'kafedra_mudiri';
    ctx.weeklyCanEdit = () => true;

    // 3) Qisman joylangan belgi: 07b — 3 soatdan 2 soati (Seshanba 10:00), qolgani 1 soat; shu birlik tugmalari.
    const st = Object.assign({ card: cards[3] }, ctx.unitState(cards[3], 8));
    const html = ctx.partialBadgeHtml(st, null);
    assert.match(html, /Qisman: 3 soatdan 2 soati joylangan \(Seshanba 10:00\); qolgani 1 soat/);
    assert.match(html, /data-unit-fix="complete" data-unit-card="4"/);
    assert.match(html, /data-unit-fix="clear" data-unit-card="4"/);

    // 4) Bitta birlikning "yaxlit joylash"i — faqat shu birlik kartalari; sig'magani — kartada qizil izoh va sahifa ichidagi oyna.
    calls.length = 0;
    await ctx.runUnitFix(null, 'complete', [st]);
    assert.deepEqual(calls, [['/boards/7/unit-fix', { week: 8, action: 'complete', card_ids: [3, 4] }]]);
    assert.equal(ctx.unitFixErr[vm.runInContext('unitKey', ctx)(cards[3])], "3 soat sig'maydi: 10:00 dan keyin 2 soat bo'sh");
    assert.match(ctx.partialBadgeHtml(st, ctx.unitFixErr[vm.runInContext('unitKey', ctx)(cards[3])]), /pn-partial-err[^>]*>✖ 3 soat sig'maydi/);
    const res = doc.getElementById('ttUnitFixModal');
    assert.ok(res);
    assert.match(res.innerHTML, /Yaxlit joylandi: 0 ta · joylanmadi: 1 ta/);
    assert.match(res.innerHTML, /<b>d2\/d26-07b \(rus\) · Tibbiyotda axborot texnologiyalari<\/b> \(Seshanba\): 3 soat sig'maydi/);
    assert.doesNotMatch(source.slice(a, b), /\balert\(/);
    // 5) Joylashuv (768px balandlik): ekran pastidagi karta — oyna kartaning ustiga ochiladi; kech kelgan "Band" yozuvidan keyin ham
    // ekrandan chiqmaydi; juda baland — yuqoridan 8px (ichida scroll: max-height calc(100vh − 16px), sarlavha sticky).
    ctx.window.innerHeight = 768; ctx.window.innerWidth = 1024;
    const card = Object.assign(el(), { isConnected: true, getBoundingClientRect: () => ({ left: 40, right: 260, top: 700, bottom: 740 }) });
    ctx.openPanelCardPop(cards[0], card);
    const pp = doc.getElementById('pnCardPop');
    assert.match(pp.style.cssText, /max-height:calc\(100vh - 16px\);overflow-y:auto/);
    assert.match(pp.innerHTML, /class="pn-pop-head" style="position:sticky;top:0/);
    pp.offsetWidth = 340; pp.offsetHeight = 300;
    ctx.panelPopPlace(pp, card);
    assert.deepEqual([pp.style.left, pp.style.top], ['268px', '440px']);   // o'ngda, karta ustiga (740 − 300)
    pp.offsetHeight = 520;   // kech kelgan "Band: …" — cho'zildi
    ctx.window.ev.scroll();   // scroll / resize / ResizeObserver — reposition
    assert.equal(pp.style.top, '220px');
    assert.ok(220 + 520 <= 768 - 8);
    pp.offsetHeight = 900;   // ekrandan baland — yuqoridan 8px, ichida scroll
    ctx.panelPopPlace(pp, card);
    assert.equal(pp.style.top, '8px');
    // O'ngda joy yo'q — chapga; chapda ham yo'q — ekran ichiga siqiladi.
    const right = Object.assign(el(), { isConnected: true, getBoundingClientRect: () => ({ left: 700, right: 1000, top: 100, bottom: 140 }) });
    pp.offsetHeight = 200;
    ctx.panelPopPlace(pp, right);
    assert.deepEqual([pp.style.left, pp.style.top], ['352px', '100px']);
    const wide = Object.assign(el(), { isConnected: true, getBoundingClientRect: () => ({ left: 100, right: 900, top: 100, bottom: 140 }) });
    ctx.panelPopPlace(pp, wide);
    assert.equal(pp.style.left, '100px');
    ctx.closePanelCardPop();
    assert.equal(ctx.window.ev.scroll, undefined);

    // 6) "Band: …": takrorlar olib tashlanadi, hammasi ko'rinadi ("yana N ta" ostiga yashirilmaydi); server xatosi sarlavha va ro'yxatga.
    const html6 = ctx.panelPopMsgHtml('Band:', ['Auditoriya band: 428 — A', 'Auditoriya band: 428 — A', 'B', 'C', 'D', 'E']);
    assert.equal((html6.match(/Auditoriya band: 428/g) || []).length, 1);
    assert.match(html6, /<li>C<\/li><li>D<\/li><li>E<\/li><\/ul>$/);
    assert.doesNotMatch(html6, /yana \d+ ta|data-pop-rest/);
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.panelPopSplitError("4 soat sig'maydi: 13:30 dan keyin 2 soat bo'sh — Guruh band: x — y · O'qituvchi band: z"))),
        { title: "4 soat sig'maydi: 13:30 dan keyin 2 soat bo'sh", items: ['Guruh band: x — y', "O'qituvchi band: z"] });
    // 7) "Vaqtincha kafedra mudiriga yozish": tugma (kech kelgan holat), sahifa ichidagi tasdiq matni, so'rov; mudir topilmasa — o'chiq.
    const tick = () => new Promise(r => setImmediate(r));
    ctx.phInfo = { ok: false, why: "Kafedra mudiri topilmadi (shu kafedraga «kafedra_mudiri» roli berilgan xodim yo'q).", mudir: null, room: null, slot: { day: 2, pair: 3 } };
    const btnEl = { disabled: true, title: '' }, whyEl = { textContent: '' };
    doc.nextQs = sel => (sel === '[data-pop-mudir]' ? btnEl : sel === '[data-pop-mudir-why]' ? whyEl : null);
    ctx.openPanelCardPop(cards[0], el());
    doc.nextQs = null;
    let pm = doc.getElementById('pnCardPop');
    assert.match(pm.innerHTML, /<button type="button" data-pop-mudir disabled title="tekshirilmoqda…"[^>]*>Vaqtincha kafedra mudiriga yozish<\/button>/);
    await tick(); await tick();
    assert.equal(btnEl.disabled, true);
    assert.match(whyEl.textContent, /Kafedra mudiri topilmadi/);
    assert.deepEqual(calls.filter(c => c[0].includes('/mudir-placeholder?')).pop(), ['/cards/1/mudir-placeholder?week=8&day=2&pair=3', undefined]);
    ctx.closePanelCardPop();
    // Mudir bor — tasdiq oynasi (sahifa ichida) va yozish so'rovi.
    ctx.phInfo = { ok: true, mudir: { id: 9, name: 'XOLMATOV A. B.' }, room: { code: 'KM1', name: 'Kafedra mudiri xonasi' }, slot: { day: 2, pair: 3 }, len: 3 };
    const modal = await ctx.openMudirPhConfirm(cards[0], { day: 2, pair: 3 });
    assert.match(modal.innerHTML, /d2\/d26-01b \(rus\) · Tibbiy va biologik fizika — Seshanba, II para \(10:00–12:40\) ga kafedra mudiri XOLMATOV A\. B\. nomiga, «Kafedra mudiri xonasi»da yoziladi\. To'qnashuv tekshirilmaydi\./);
    assert.match(modal.innerHTML, /data-ph-reason/);
    assert.match(modal.innerHTML, /value="rest"> shu va keyingi haftalar/);
    const okBtn = { disabled: false };
    modal.querySelector = sel => (sel === 'input[name="phScope"]:checked' ? { value: 'rest' } : sel === '[data-ph-reason]' ? { value: 'xona band' } : null);
    await modal.listeners.click({ target: { closest: sel => (sel === '[data-ph-ok]' ? okBtn : null) } });
    assert.deepEqual(calls.filter(c => c[0] === '/cards/1/mudir-placeholder').pop(), ['/cards/1/mudir-placeholder',
        { week: 8, scope: 'rest', day: 2, pair: 3, reason: 'xona band' }]);
    // Mudir topilmagan — tasdiq oynasida faqat sabab, "Yozish" tugmasi yo'q.
    ctx.phInfo = { ok: false, why: 'Kafedra mudiri topilmadi.' };
    const m2 = await ctx.openMudirPhConfirm(cards[0], { day: 2, pair: 3 });
    assert.match(m2.innerHTML, /data-ph-why[^>]*>Kafedra mudiri topilmadi\./);
    assert.doesNotMatch(m2.innerHTML, /data-ph-ok/);

    // 8) Panel bo'limi: "Vaqtincha kafedra mudirida: N ta", asl o'qituvchi va qaytarish tugmasi; qaytarish band — qizil sabab.
    // Belgi — shu haftaning yozuvida (karta asosiy o'qituvchisi o'zgarmaydi).
    const phCard = Object.assign({}, cards[2], { id: 30 });
    ctx.overrides['30|8'] = { day: 3, pair: 3, cancelled: false, mudir_placeholder: { teacher_name: 'XOLMATOV A. B.', orig_teacher_name: 'ALIYEV SH. N.', reason: 'xona band' } };
    let sec = ctx.mudirPhSectionHtml([phCard]);
    assert.match(sec, /Vaqtincha kafedra mudirida: <b>1 ta<\/b>/);
    assert.match(sec, /\(asl o'qituvchi: ALIYEV SH\. N\.\)/);
    assert.match(sec, /data-ph-revert="30" data-ph-mode="original"[^>]*>Asl holatga qaytarish<\/button>/);
    assert.match(sec, /data-ph-revert="30" data-ph-mode="here"[^>]*>Shu joyda asl o'qituvchiga<\/button>/);
    // Bo'lim ro'yxati panelning joylanmagan / to'qnashuvli ro'yxatidan mustaqil (sikl panelidagi xato bu yerda yo'q): to'qnashuvsiz
    // joylangan mudir belgili dars (30) kiradi; belgisi boshqa haftada (31 — 9-hafta) yoki belgisiz (1) — yo'q; tashqi qatlamda — bo'sh.
    ctx.overrides['31|9'] = { day: 3, pair: 3, cancelled: false, mudir_placeholder: { teacher_name: 'XOLMATOV A. B.', orig_teacher_name: 'X' } };
    ctx.weekActiveCards = () => [cards[0], phCard, Object.assign({}, cards[2], { id: 31 })];
    assert.deepEqual([...ctx.weeklyPhCards().map(c => c.id)], [30]);
    ctx.weeklyLayer = 'external';
    assert.equal(ctx.weeklyPhCards().length, 0);
    ctx.weeklyLayer = 'internal';
    // Asosiy — asl holatga, barcha haftalar (week yuborilmaydi).
    await ctx.mudirPhRevert(phCard, { disabled: false });
    assert.deepEqual(calls.filter(c => c[0].endsWith('/revert')).pop(), ['/cards/30/mudir-placeholder/revert', { mode: 'original' }]);
    // Shu joyda (bandlik bilan) — band: qizil "✖", dastlabki 2 sabab va "yana N ta".
    ctx.revertFail = "Shu joyda asl o'qituvchiga qaytarib bo'lmaydi — band: …";
    await ctx.mudirPhRevert(phCard, { disabled: false }, 'here', 8);
    assert.deepEqual(calls.filter(c => c[0].endsWith('/revert')).pop(), ['/cards/30/mudir-placeholder/revert', { mode: 'here', week: 8 }]);
    sec = ctx.mudirPhSectionHtml([phCard]);
    assert.match(sec, /✖ Shu joyda asl o'qituvchiga qaytarib bo'lmaydi — band: …/);
    ctx.phRevertErrSet(phCard, ['8-hafta: A', '9-hafta: B', '10-hafta: C', '11-hafta: D']);
    sec = ctx.mudirPhSectionHtml([phCard]);
    assert.match(sec, /✖ 8-hafta: A · 9-hafta: B <details[^>]*><summary[^>]*>yana 2 ta<\/summary>10-hafta: C · 11-hafta: D<\/details>/);

    // 9) Joylangan, lekin to'qnashuvli karta (panelda to'qnashuv sababi bilan): oynada joyi ("Chorshanba I–II para") va
    //    "Shu joyga joylash" o'rniga "Jadvalda ko'rsatish" — o'sha katakka o'tadi.
    ctx.closePanelCardPop();
    const placedCard = Object.assign({}, cards[2], { id: 40, len_half: 4, ext_day: 2, ext_pair: 3 });
    ctx.overrides['40|8'] = { day: 3, pair: 1, cancelled: false };
    assert.equal(ctx.placedSlotText(placedCard), 'Chorshanba I–II para');
    assert.equal(ctx.placedSlotText(cards[2]), 'Seshanba II para');   // 2 qator — bitta para
    ctx.openPanelCardPop(placedCard, el());
    const pq = doc.getElementById('pnCardPop');
    assert.match(pq.innerHTML, /Joyi:<\/span> <b data-pop-placed>Chorshanba I–II para<\/b> <span[^>]*>· to'qnashuv<\/span>/);
    assert.match(pq.innerHTML, /data-pop-show[^>]*>Jadvalda ko'rsatish<\/button>/);
    assert.doesNotMatch(pq.innerHTML, /data-pop-place[ >]/);
    const flashed = [];
    const tdWed = { dataset: { day: '3', pair: '1', g: 'F|S|1|d2/d26-07b (rus)' }, classList: { add: c => flashed.push(c), remove() {} }, scrollIntoView() { this.scrolled = true; } };
    const oldQsa = grid.querySelectorAll;
    grid.querySelectorAll = sel => (sel === '.tt-slot-flash' ? [] : sel.includes('[data-day="3"][data-pair="1"]') ? [tdWed] : []);
    await pq.listeners.click({ target: { closest: () => ({ hasAttribute: x => x === 'data-pop-show', disabled: false }) }, preventDefault() {}, stopPropagation() {} });
    grid.querySelectorAll = oldQsa;
    assert.ok(tdWed.scrolled && flashed.includes('tt-slot-flash'));
    assert.ok(!doc.getElementById('pnCardPop'), 'oyna yopiladi');
    ctx.closePanelCardPop();

    // 10) Kartadagi xona nofaol — oynada "(nofaol) — Xona nofaol — almashtiring".
    const offCard = Object.assign({}, cards[0], { id: 50, auditorium_code: 'R404', auditorium_name: '404' });
    ctx.openPanelCardPop(offCard, el());
    assert.match(doc.getElementById('pnCardPop').innerHTML, /data-pop-room-off[^>]*>\(nofaol\) — Xona nofaol — almashtiring<\/span>/);
    ctx.closePanelCardPop();
    ctx.openPanelCardPop(cards[0], el());
    assert.doesNotMatch(doc.getElementById('pnCardPop').innerHTML, /data-pop-room-off/);
    ctx.closePanelCardPop();
    console.log('timetable panel card pop: 10 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
