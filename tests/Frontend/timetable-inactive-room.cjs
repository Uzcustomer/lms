const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
// Nofaol xona: karta oynasidagi xona selecti — faqat faol xonalar; kartadagi nofaol xona "(nofaol)" bilan, tanlab bo'lmaydi (disabled).
const els = { cmAud: { innerHTML: '' } };
const ctx = {
    esc: s => String(s), $: id => els[id], AUDS_URL: '/a', audCache: null, modalCard: null,
    api: async () => [{ code: 'R305', name: '305', volume: 30, active: true }, { code: 'R404', name: '404', volume: 30, active: false },
        { code: 'R500', name: '500', volume: 40, active: false }],
};
vm.createContext(ctx);
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(cut('            let inactiveRooms = {};', "            // O'qituvchi kesimi ustuni").replace('let inactiveRooms = {};', 'var inactiveRooms = { R404: "404" };')
    + cut('            async function loadAuds() {', '            let tSearchTimer = null;') + '; this.loadAuds = loadAuds; this.roomInactive = roomInactive;', ctx);
(async () => {
    ctx.modalCard = { auditorium_code: 'R404', auditorium_name: '404', students: 10 };
    await ctx.loadAuds();
    const html = els.cmAud.innerHTML;
    assert.match(html, /<option value="R404" disabled selected data-room-off="1"[^>]*>404 \(nofaol\)<\/option>/);
    assert.equal((html.match(/value="R404"/g) || []).length, 1, 'nofaol xona faqat belgili bitta variant');
    assert.doesNotMatch(html, /value="R500"/);   // boshqa nofaol xona — ro'yxatda yo'q
    assert.match(html, /<option value="R305"/);
    // Kartadagi xona faol — belgili variant yo'q.
    ctx.modalCard = { auditorium_code: 'R305', auditorium_name: '305', students: 10 };
    await ctx.loadAuds();
    assert.doesNotMatch(els.cmAud.innerHTML, /nofaol|R404|R500/);
    assert.match(els.cmAud.innerHTML, /value="R305" selected/);
    assert.equal(ctx.roomInactive('R404'), true);
    assert.equal(ctx.roomInactive('R305'), false);
    console.log('timetable inactive room: 2 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
