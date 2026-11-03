const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a >= 0 && b > a, from); return source.slice(a, b); };
const code = cut('            function isHemisExcluded(card)', '            function applyWeeklyLayer(list)')
    + cut('            // ===== Registrator: HEMIS bilan solishtirish', '            async function hsTicketsSend(rep)');
const lesson = { date: '2026-09-21', start: '10:00', end: '11:20', code: '13', employee_name: 'Teacher', auditorium_name: 'Kafedra mudiri xonasi' };
const action = (id, missing) => ({ ...lesson, op: 'update', hemis_id: id, today: true, flags: missing ? ['mudir_xonasi'] : [], missing_room: missing, blocked: [], changes: ['xona: old → new'] });
const unit = (name, actions, missing = []) => ({ subject_name: name, actions, missing_room_lessons: missing, actions_later: 0,
    code_names: { 13: 'Amaliy' }, codes: { 13: 'A' }, before: { hours_diff: {}, count_diff: {} }, after: { hours_diff: {}, count_diff: {} }, notes: [], fixes: [] });
const warning = "Kafedra mudiri xonasi kafedraga bog'lanmagan (HEMIS): Anatomiya";
const full = { today: '2026-09-19', current_week: 1, period_from: 1, period_to: 1, until_week: 2, weeks: [], missing_room_count: 2,
    fallback_room: { code: 'KM1' }, groups_not_in_hemis: [], warnings: [warning], groups: [
        { group: 'G1', units: [unit('Missing', [action(1, true)], [lesson]), unit('Assigned', [action(2, false)])], skipped: [] },
        { group: 'G2', units: [unit('Missing OK', [], [lesson])], skipped: [] },
        { group: 'G3', units: [], skipped: [], cards_without_subject: ['Assigned unmatched'] },
    ] };
(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.setContent('<div id="board"></div>');
        await page.addScriptTag({ content: "const $ = id => document.getElementById(id); const BASE = ''; const CSRF = 't'; const HS_WRITER_URL = 'writer.js'; const board = {id:1};"
            + " const scopeLabelText = () => 'Qamrov', scopeBody = () => ({}), api = async () => ({}); const sendExcelExport = async data => {window.__excel = data;};" });
        await page.addScriptTag({ content: code });
        await page.evaluate(rep => {
            window.__full = rep;
            URL.createObjectURL = blob => { window.__blob = blob; return 'blob:test'; };
            HTMLAnchorElement.prototype.click = function () {};
            hemisSyncRender(rep);
        }, full);
        const text = () => page.locator('#hemisSyncModal').textContent();
        const bodyText = async () => (await page.locator('#hemisSyncModal table').allTextContents()).join(' ');
        assert.match(await text(), /Xonasi bo‘sh: 2 ta dars \(mudir xonasi qo‘yiladi\)/);
        assert((await text()).includes(warning));
        assert((await bodyText()).includes('Assigned'));
        await page.check('#hsMissingRooms');
        assert.equal(await page.isChecked('#hsOnlyDiff'), false, 'the missing-room list includes matching lessons');
        assert(!(await bodyText()).includes('Assigned'));
        assert((await bodyText()).includes('Missing OK'));
        await page.click('#hsJson');
        let json = JSON.parse(await page.evaluate(() => window.__blob.text()));
        let actions = json.groups.flatMap(g => g.units.flatMap(u => u.actions));
        assert.equal(actions.length, 1);
        assert(actions.every(a => a.missing_room));
        await page.click('#hsGroupBox summary');
        await page.uncheck('.hs-pick-Group[data-i="0"]');
        assert.match(await text(), /Xonasi bo‘sh: 1 ta dars/);
        const today = { today: '2026-09-14', warnings: [warning], rows: [
            { group: 'G1', subject: 'Missing', status: 'update', hemis_id: 1, missing_room: true, fallback_room: true, start: '10:00', end: '11:20', blocked: [], changes: [] },
            { group: 'G1', subject: 'Assigned', status: 'update', hemis_id: 2, missing_room: false, start: '10:00', end: '11:20', blocked: [], changes: [] },
            { group: 'G2', subject: 'Missing OK', status: 'ok', hemis_id: 3, missing_room: true, fallback_room: true, start: '10:00', end: '11:20', blocked: [], changes: [] },
        ], json: { mode: 'today', groups: [{ group: 'G1', units: [{ subject_name: 'Missing', actions: [action(1, true)] }, { subject_name: 'Assigned', actions: [action(2, false)] }] }] } };
        await page.evaluate(rep => { window.__today = rep; hemisTodayRender(rep); }, today);
        assert.match(await text(), /Xonasi bo‘sh: 2 ta dars/);
        await page.check('#htMissingRooms');
        assert(!(await bodyText()).includes('Assigned'));
        assert((await bodyText()).includes('Missing OK'));
        await page.click('#htJson');
        json = JSON.parse(await page.evaluate(() => window.__blob.text()));
        assert.deepEqual(json.groups.flatMap(g => g.units.flatMap(u => u.actions.map(a => a.hemis_id))), [1]);
        await page.selectOption('#htGroup', 'G1');
        assert.match(await text(), /Xonasi bo‘sh: 1 ta dars/);
        await page.click('#htExcel');
        const excel = await page.evaluate(() => window.__excel);
        assert.equal(excel.rows.length, 2);
        assert(!JSON.stringify(excel).includes('Assigned'));
        const block = await page.evaluate(() => hsActionHtml({ op: 'update', date: '2026-09-21', start: '10:00', end: '11:20', code: '13',
            blocked: ['room busy · yana 29 ta guruh'], blocks: [{ type: 'room', room: '71', time: '10:00–11:20', group: 'G1', subject: 'S',
                source: 'HEMIS', text: 'room busy · yana 29 ta guruh', other_groups: 29 }] }, { code_names: { 13: 'Amaliy' } }));
        assert.match(block, /yana 29 ta guruh/);
        console.log('hemis missing rooms: 7 scenarios passed (Chromium filters, JSON, Excel, matching lessons and warnings)');
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
