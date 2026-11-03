const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a, from);
    return source.slice(a, b);
};
const esc = value => String(value ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const ctx = { board: { id: 7 }, esc, cardGroups: c => c.group_names || [c.group_name], cfGroups: gs => gs.join(', '), BOLAK_RX: /^(.*?)\s*\(([a-z])\)$/i };
vm.createContext(ctx);
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(cut('            function agendaOpt(', '            function agendaHtml(')
    + cut('            function agendaEntryHtml(', '            function agendaDayHtml(')
    + cut('            const sameCrossLesson =', '            // Sikl darsining zal')
    + cut('            function cardLabel(', '            // Karta ustiga kursor')
    + cut('            function describeCardBrief(', '            // ===== Joylash (bosish'), ctx);
const card = { id: 1, course: 6, subject_name: 'Klinik farmakologiya', training_type: 'practice', group_name: 'p/p21-01a' };
assert(ctx.isInfoOnly(card));
assert.match(ctx.cardLabel(card, false), /6-kurs · ma’lumot \(onlayn\)/);
assert.equal(ctx.infoOnlyClass(card), ' tt-info-only');
ctx.board.settings = { info_only_courses: [] };
assert.equal(ctx.isInfoOnly(card), false);
assert.equal(ctx.infoOnlyBadgeHtml(card), '');
ctx.board.settings.info_only_courses = [3];
assert(ctx.isInfoOnly({ course: 3 }));
assert.equal(ctx.isInfoOnly(card), false);
delete ctx.board.settings;
const item = (id, course, fa, fb) => ({ c: { ...card, id, course, group_name: 'group-' + id }, fa, fb });
assert.equal(ctx.crossCellLessons([item(1, 5, 1, 3), item(2, 6, 1, 5)], true).clashing.length, 0);
assert.equal(ctx.crossCellLessons([item(1, 5, 1, 3), item(2, 5, 1, 5)], true).clashing.length, 2);
// An online lesson bridging two separate ordinary lessons must not create an indirect conflict.
assert.equal(ctx.crossCellLessons([item(1, 5, 1, 3), item(2, 6, 1, 7), item(3, 5, 4, 7)], true).clashing.length, 0);
Object.assign(ctx, { cards: [{ ...card, teacher_id: 1, auditorium_code: '101', auditorium_name: '101', day: 1, pair: 1 }],
    cardGKeys: c => [c.course + '|' + c.group_name], phOf: () => null, effectiveCard: c => c, effPlace: c => ({ day: c.day, pair: c.pair }),
    cardLen: () => 2, weeklyLayer: 'internal', isSharedRoomName: () => false });
const ordinary = { ...card, id: 2, course: 5, group_name: 'd1/d22-04', teacher_id: 1, auditorium_code: '101' };
assert.equal(ctx.conflictsAt(ordinary, 1, 1).length, 0);
ctx.cards[0].course = 5;
assert.equal(ctx.conflictsAt(ordinary, 1, 1).length, 2);
assert.equal(ctx.conflictsAt(card, 1, 1).length, 0);
const flow = course => ({ c: { ...card, id: course, course, training_type: 'lecture', auditorium_code: '101', teacher_id: 1 }, fa: 1, fb: 3 });
assert.equal(ctx.crossCellLessons([flow(5), flow(6)], true).lessons.length, 2, 'online and ordinary lectures retain their separate labels');
const date = '2026-09-14';
const j = { entries: [{ date, from: 1, to: 3, key: 'online', info_only: true, course: 6, subject: card.subject_name,
    type: 'A', groups: ['p/p21-01a'], conflict: false, start: '08:30', end: '09:50' }],
    move: { kind: 'weekly', options: { [date]: { 1: { ok: true } } } } };
const html = ctx.agendaCellHtml(j, { move: 'ordinary' }, date, { no: 1, from: 1, to: 3 }, false);
assert.match(html, /bo‘sh · 6-kurs onlayn/);
assert.doesNotMatch(html, /ag-conf|to‘qnashuv/);
assert.match(ctx.cycleInfoOverlapHtml(card, { key: 'online-2', subject: 'Ikkinchi fan' }), /tt-info-only-overlap.*Ikkinchi fan/);
assert.equal(ctx.cycleInfoOverlapHtml({ course: 5 }, { key: 'ordinary' }), '');
// Render two overlapping online blocks through the actual cycle renderer.
const grid = { innerHTML: '' };
const noop = () => {};
Object.assign(ctx, { $: () => grid, cfLectureOwners: () => ({}), cfMergeLectureItems: noop, cycleRowFiltersLoad: noop,
    ttFocus: null, cycleRowFilters: [], cycleVisibleRows: rows => rows, scopeMode: 'all', mineOnly: false,
    boardSchedule: () => [], cyclePairsShown: null, cycleViewMode: 'group', cycleColHidden: () => false,
    cycleRowFilterText: () => 'Guruh', cycleDateClass: () => '', subjColor: () => ({ bg: '#eee', border: '#ccc' }),
    setupCycleGridEvents: noop, setupCycleMarking: noop, setupCycTitleDrag: noop, clampCycTitles: noop, renderCycleCards: noop, focusHighlight: noop });
vm.runInContext(cut('            function renderCyclePlan(j)', '            // ── Sikl jadvali hodisalari'), ctx);
ctx.renderCyclePlan({ simple: true, view: 'group', dates: [{ d: '14.09' }, { d: '15.09' }], rows: [{
    row_key: 'online-row', group: 'p/p21-01a', course: 6, blocks: [
        { key: 'online-1', subject: 'Birinchi fan', type: 'practice', from: 0, to: 1, days: 2 },
        { key: 'online-2', subject: 'Ikkinchi fan', type: 'practice', from: 0, to: 1, days: 2 },
    ] }] });
assert.match(grid.innerHTML, /cyc-block tt-info-only/);
assert.match(grid.innerHTML, /Birinchi fan/);
assert.match(grid.innerHTML, /tt-info-only-overlap.*Ikkinchi fan/);
assert.doesNotMatch(grid.innerHTML, /is-conflict|data-cf-key/);
(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        const css = cut('        .tt-info-only {', '    </style>');
        await page.setContent('<style>' + css + '</style><div id="ttAgendaModal">' + html + '</div><table>' + grid.innerHTML + '</table>');
        assert.equal(await page.locator('.ag-e.tt-info-only').count(), 1);
        assert.equal(await page.locator('.ag-e').evaluate(el => getComputedStyle(el).opacity), '0.68');
        assert.match(await page.locator('.ag-e').evaluate(el => getComputedStyle(el).backgroundImage), /repeating-linear-gradient/);
        assert.equal(await page.getByRole('button', { name: 'Shu vaqtga ko‘chirish', exact: true }).isEnabled(), true);
        assert.equal(await page.locator('.tt-info-only-overlap button').textContent(), 'Ikkinchi fan');
        await page.close();
        console.log('timetable info only: 10 scenarios passed (including Chromium display and enabled move)');
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
