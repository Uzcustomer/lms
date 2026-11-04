const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// "Vaqtini o'zgartirishni so'rash" tugmasi sikl kun oynasida (#cycDayPop) va kesimdagi sikl darsi oynasida (#crossCycPop) ko'k fonli, oq
// yozuvli ko'rinadi: "#cycDayPop .dp-actions button" (oq-kulrang fon) qoidasi Tailwind bg-blue-600 ni bosib ketmasin; ↑ ↓ — odatdagidek.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
// Sahifadagi Tailwind klasslari (tugmadagi).
const tw = '.bg-blue-600{background-color:#2563eb}.text-white{color:#fff}.rounded{border-radius:.25rem}.px-2{padding-left:.5rem;padding-right:.5rem}.py-1{padding-top:.25rem;padding-bottom:.25rem}';
const code = cut('            function cycCanAsk(card, dp, date, today, kind = \'practice\') {', '            async function loadChangeRequests() {')
    + cut('            function openCycDayPop(key, offset, anchor, keepMulti = false) {', '            // Fan nomi bosilganda:')
    + cut('            function closeCrossCycPop() {', '                document.body.appendChild(pop);\n                const r = anchor.getBoundingClientRect();\n                pop.style.left = Math.max(8, Math.min(window.innerWidth - pop.offsetWidth - 8, r.right + 8))')
    + '                document.body.appendChild(pop);\n            }\n';

const stubs = role => `
    const TIMETABLE_ROLE = ${JSON.stringify(role)};
    const esc = s => String(s == null ? '' : s);
    let cycDaySel = null;
    const CARD = { key: 'K', group: 'd1/d22-01a', subject: 'Klinik farmakologiya', type: 'practice', ask: true };
    const cyclePlanData = { day_edit: true, day_hours: 12, cycle_cards: [CARD],
        dates: Object.assign([], { 52: { iso: '2026-11-05', d: '05.11' } }) };
    const cycBlockByKey = () => ({ subject: 'Klinik farmakologiya', from: 50, day_plan: { 2: { k: 3, lec: 0, prc: 6, start: 1, ktr: true } } });
    const boardSchedule = () => [{ type: 'pair', start: '08:30', end: '09:50', abbr: 'I' }, { type: 'pair', start: '10:00', end: '11:20', abbr: 'II' },
        { type: 'pair', start: '12:00', end: '13:20', abbr: 'III' }];
    const pendingDaySlot = () => null, pendingPracticeAsk = () => null, crStageText = () => '';
    const cycLocalToday = () => '2026-11-03';
    const cycHourTimes = () => [null, ['08:30', '09:10'], ['09:10', '09:50']];
    const cycRoomClashes = () => [];
    function closeCycDayPop() { const p = document.getElementById('cycDayPop'); if (p) p.remove(); cycDaySel = null; }
    const openCycleTimeProposal = () => {}, moveCycDay = () => {};
    let cycMultiSel = null;
    const cycMultiPaint = () => {}, cycAskDays = () => [], cycAskDp = dp => dp;
`;
const look = (p, sel) => p.evaluate(s => {
    const b = document.querySelector(s);
    if (!b) return null;
    const cs = getComputedStyle(b);
    return { bg: cs.backgroundColor, color: cs.color, text: b.textContent.trim() };
}, sel);

(async () => {
    const browser = await chromium.launch();
    try {
        const p = await (await browser.newContext({ viewport: { width: 1200, height: 800 } })).newPage();
        for (const role of ['oqituvchi', 'fan_masuli']) {
            await p.setContent('<!doctype html><html><head><style>' + tw + css + '</style></head><body><button id="cell">05.11</button></body></html>');
            await p.addScriptTag({ content: stubs(role) + code + '\nwindow.T = { openCycDayPop, openCrossCycPop };' });
            await p.mouse.move(1100, 700);   // oldingi hover qolmasin
            // O'qituvchi: 05.11 katagi (3-kun) — kun oynasi.
            await p.evaluate(() => T.openCycDayPop('K', 2, document.getElementById('cell')));
            const ask = await look(p, '#cycDayPop [data-dp-ask]');
            assert.deepEqual(ask, { bg: 'rgb(37, 99, 235)', color: 'rgb(255, 255, 255)', text: "Vaqtini o'zgartirishni so'rash" }, role);
            // ↑ ↓ — odatdagidek (oq-kulrang fon, to'q yozuv).
            const up = await look(p, '#cycDayPop [data-dp-move="1"]');
            assert.equal(up.bg, 'rgb(248, 250, 252)');
            assert.notEqual(up.color, 'rgb(255, 255, 255)');
            // Hover — to'qroq ko'k.
            await p.hover('#cycDayPop [data-dp-ask]');
            assert.equal((await look(p, '#cycDayPop [data-dp-ask]')).bg, 'rgb(29, 78, 216)');

            // Kesimdagi sikl darsi oynasi: bitta va guruhchalar bo'yicha so'rov tugmalari ham xuddi shunday.
            const lesson = { date: '2026-11-05', pair: 1, len_half: 2, hour: 1, hours: 2, training_type: 'practice', subject_name: 'Klinik farmakologiya',
                specialty_name: 'davolash ishi', course: 5, group_name: 'd1/d22-01a', ask: true, dp: {} };
            await p.evaluate(l => T.openCrossCycPop(l, document.getElementById('cell')), lesson);
            assert.deepEqual(await look(p, '#crossCycPop [data-cyc-ask]'), { bg: 'rgb(37, 99, 235)', color: 'rgb(255, 255, 255)', text: "Vaqtini o'zgartirishni so'rash" });
            await p.evaluate(l => T.openCrossCycPop(Object.assign({}, l, { parts: [Object.assign({}, l, { ask: true }), Object.assign({}, l, { group_name: 'd1/d22-01b', ask: true })] }),
                document.getElementById('cell')), lesson);
            const part = await look(p, '#crossCycPop [data-cyc-ask-part="0"]');
            assert.equal(part.bg, 'rgb(37, 99, 235)');
            assert.equal(part.color, 'rgb(255, 255, 255)');
        }
        console.log('timetable cycle ask button: 2 roles passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
