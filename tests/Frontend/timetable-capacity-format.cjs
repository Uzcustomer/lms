const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a > 0 && b > a, from); return source.slice(a, b); };

// Qabul testi (1.4): xona sig'imi "N o'rin, M talaba (+K)" — to'qnashuvlar oynasi, biriktirish oynasi (xona ro'yxati), haftalik karta va
// "Takliflar" bir xil ko'rinishda. Front raqamlarni bitta formatlovchi (cfCapText) bilan yozadi; to'liq qator ("Xona sig'maydi: X — …")
// — serverdan (TimetableController::capacityLine) keladi va o'zgarishsiz ko'rsatiladi.
const ctx = {};
vm.createContext(ctx);
vm.runInContext(cut('            const cfCapText = ', '            const cfDate = ') + '\nthis.cfCapText = cfCapText;', ctx);
const cap = { room_code: 'Z8', room_name: "8-ma'ruza zali", volume: 65, students: 98, over: 33 };
assert.equal(ctx.cfCapText(cap), "65 o'rin, 98 talaba (+33)");
assert.equal(ctx.cfCapText({ volume: 10, students: 12 }), "10 o'rin, 12 talaba (+2)");   // over yo'q — hisoblanadi
assert.equal(ctx.cfCapText({ volume: 0, students: 12 }), '');                            // sig'imi kiritilmagan — bo'sh

// Server qatori bilan bir xil: "Xona sig'maydi: <xona> — " + cfCapText(...).
const serverLine = "Xona sig'maydi: 8-ma'ruza zali — 65 o'rin, 98 talaba (+33)";
assert.equal("Xona sig'maydi: " + cap.room_name + ' — ' + ctx.cfCapText(cap), serverLine);

// Biriktirish oynasi va to'qnashuvlar oynasi o'z formatlovchisini emas, cfCapText ni ishlatadi (bitta joyda).
assert.match(source, /'<span class="rm-cap">Xona sig\\'maydi: ' \+ esc\(cfCapText\(caps\[room\.code\]\)\)/);
assert.doesNotMatch(source, /const capText = c => c\.volume/);

// "Takliflar": karta ostida server matni (cap_warn) — sariq ogohlantirish.
const body = { innerHTML: '', querySelectorAll: () => [], querySelector: () => null };
const tctx = {
    window: { __ttSug: [{ id: 7, subject: 'Farmakologiya', type: 'practice', groups: ['pp/2024-01b'], teacher: 'MUX', room: '102-xona', reason: '',
        clashes: [], suggestions: [], partial: false, conflict: false, cap_warn: "Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba (+2)" }] },
    esc: s => String(s), focusLinksHtml: () => '', agendaLinksHtml: () => '', sugRowHtml: () => '', sugFooterHtml: () => '',
};
vm.createContext(tctx);
vm.runInContext(cut('            function renderSugCards(body, res, params) {', '            function sugApplyLabel(mode) {') + '\nthis.renderSugCards = renderSugCards;', tctx);
tctx.renderSugCards(body, { week: 10 }, {});
assert.match(body.innerHTML, /<div class="sug-cap-warn" style="color:#a16207;margin:2px 0 4px">⚠ Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba \(\+2\)<\/div>/);
tctx.window.__ttSug[0].cap_warn = null;
tctx.renderSugCards(body, { week: 10 }, {});
assert.doesNotMatch(body.innerHTML, /sug-cap-warn/);

// Haftalik karta: server ogohlantirishi faqat o'sha hafta uchun (boshqa hafta yoki "Barcha haftalar" — yo'q).
const wctx = { curWeek: 10 };
vm.createContext(wctx);
vm.runInContext(cut('            let weeklyCapWarn = ', '            let conflictSig = ') + '\nthis.set = v => { weeklyCapWarn = v; }; this.weeklyCapLine = weeklyCapLine;', wctx);
wctx.set({ week: 10, map: { 7: { line: "Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba (+2)" } } });
assert.equal(wctx.weeklyCapLine({ id: 7 }), "Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba (+2)");
assert.equal(wctx.weeklyCapLine({ id: 8 }), '');
wctx.curWeek = 11;
assert.equal(wctx.weeklyCapLine({ id: 7 }), '');
wctx.curWeek = 0;
assert.equal(wctx.weeklyCapLine({ id: 7 }), '');
console.log('timetable capacity format: 5 scenarios passed');
