const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Sikl fanida alohida ma'ruza ("ma'ruza alohida", kunlik soatlar — SOATDA): "Darslar" qatorida umumiy belgi va soatlar; fakultet /
// o'quv reja oynasida har qatorda "meros / amaliy ichida / alohida" + soatlar ("alohida" — soatlar kiritilgach saqlanadi; ogohlantirish
// — oynada). Panelda alohida ma'ruza kartasi oqimning istalgan guruhcha qatoriga tashlanadi (row_keys).
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const code = cut('            // Fan sozlamasini saqlash:', '            async function saveSubjectModeRow(tr, row) {');
const params = cut('            function subjectModeParamsHtml(setting) {', '            // Fan sozlamasini saqlash:');
const rowOk = cut('            function cycCardRowOk(card, rowKey) {', '            async function placeCycleCardAt(card, index, pair) {');

const SCOPES = {
    mode: 'cycle', days: 18, lecture: null, plan_lecture_hours: 26, day_hours: 14, faculties: [
        { name: 'Xalqaro ta\'lim fakulteti', days: null, lecture: null, plan_lecture_hours: 26, curricula: [
            { id: 601, name: 'Davolash ishi (xalqaro) 2022', semester: '8-semestr', prefix: 'xd/22', groups: 4, days: 13, lecture: null },
            { id: 701, name: 'Davolash ishi (xalqaro) 2023', semester: '7-semestr', prefix: 'xd/23', groups: 3, days: 21, lecture: { on: true, hours: [8, 8, 6, 6, 6] } },
        ] },
    ],
};

const page0 = `<!doctype html><html><head><style>${css}</style></head><body><script>
    const BASE = '/admin/timetable';
    const board = { id: 7 };
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    window.calls = [];
    window.scopes = ${JSON.stringify(SCOPES)};
    window.warnings = [];
    async function api(url, method = 'GET', body = null) {
        window.calls.push({ url, method, body: body ? JSON.parse(JSON.stringify(body)) : null });
        if (url.includes('/subject-setting/scopes')) return JSON.parse(JSON.stringify(window.scopes));
        return { ok: true, dry_run: !!(body && body.dry_run), affected: [], warnings: body && body.dry_run ? [] : window.warnings };
    }
${params}
${code}
${rowOk}
    window.subjectModeParamsHtml = subjectModeParamsHtml;
    window.openSubjScopeDlg = openSubjScopeDlg;
    window.cycCardRowOk = cycCardRowOk;
</script></body></html>`;

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    page.on('pageerror', e => { throw e; });
    await page.setContent(page0);
    const saves = () => page.evaluate(() => window.calls.filter(c => c.method === 'POST' && !c.body.dry_run).map(c => c.body));

    // 1) "Darslar" qatori: sikl fani — "Ma'ruza alohida" belgisi va soatlar (belgisiz — soatlar maydoni yashirin).
    const html = await page.evaluate(() => [
        window.subjectModeParamsHtml({ mode: 'cycle', cycle_days: 21, lecture: { on: true, hours: [8, 8, 6, 6, 6] } }),
        window.subjectModeParamsHtml({ mode: 'cycle', cycle_days: 18, lecture: { on: false, hours: [] } }),
        window.subjectModeParamsHtml({ mode: 'cycle', cycle_days: 18 })]);
    assert.match(html[0], /class="asc-subj-lecon" checked> Ma'ruza alohida/);
    assert.match(html[0], /class="asc-subj-lechours" value="8,8,6,6,6" placeholder="8,8,6,6,6" title=/);
    assert.match(html[1], /class="asc-subj-lecon"> Ma'ruza alohida/);
    assert.match(html[1], /class="asc-subj-lechours" value="" placeholder="8,8,6,6,6" hidden/);
    assert.match(html[2], /class="asc-subj-lechours" value="" placeholder="8,8,6,6,6" hidden/);

    // 2) Oyna: umumiy ma'ruza, reja soati; har qatorda tanlov (meros — yuqori darajaning qiymati bilan).
    await page.evaluate(() => { window.saved = 0;
        window.openSubjScopeDlg({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Ichki kasalliklar' }, async () => { window.saved++; }); });
    await page.waitForSelector('#subjScopeDlg select[data-ssl]');
    assert.match(await page.locator('#subjScopeDlg').innerText(), /umumiy: 18 kun, ma'ruza: amaliy ichida \(rejada 26 soat\)/);
    const ctl = await page.$$eval('#subjScopeDlg select[data-ssl]', xs => xs.map(x => [x.dataset.ssl, x.value, x.options[0].text,
        document.querySelector('#subjScopeDlg input[data-sslh="' + x.dataset.ssl + '"]').value, document.querySelector('#subjScopeDlg input[data-sslh="' + x.dataset.ssl + '"]').hidden]));
    assert.deepEqual(ctl, [
        ['0', 'inherit', "ma'ruza: meros (amaliy ichida)", '', true],
        ['0|0', 'inherit', "ma'ruza: meros (amaliy ichida)", '', true],
        ['0|1', 'on', "ma'ruza: meros (amaliy ichida)", '8,8,6,6,6', false],
    ]);

    // 3) xd/22: "alohida" tanlansa — soatlar kiritilmaguncha saqlanmaydi; kiritilgach — kunlar (13) va soatlar bilan; ogohlantirish oynada.
    await page.evaluate(() => { window.calls = []; window.warnings = ['Ma‘ruza soatlari yig‘indisi 24 — rejada 26 soat.']; });
    await page.selectOption('#subjScopeDlg select[data-ssl="0|0"]', 'on');
    assert.equal(await page.isHidden('#subjScopeDlg input[data-sslh="0|0"]'), false);
    assert.match(await page.locator('#subjScopeDlg [role="alert"]').innerText(), /Kunlik ma'ruza soatlarini kiriting/);
    assert.deepEqual(await saves(), []);
    await page.evaluate(() => { window.scopes.faculties[0].curricula[0].lecture = { on: true, hours: [6, 6, 6, 6] }; });
    await page.fill('#subjScopeDlg input[data-sslh="0|0"]', '6,6,6,6');
    await page.press('#subjScopeDlg input[data-sslh="0|0"]', 'Tab');
    await page.waitForFunction(() => /Saqlandi/.test(document.querySelector('#subjScopeDlg [role="alert"]').textContent));
    let body = (await saves())[0];
    assert.deepEqual([body.faculty_name, body.curriculum_hemis_id, body.cycle_days, body.lecture, body.lecture_hours, body.mode],
        ['Xalqaro ta\'lim fakulteti', 601, 13, 'on', '6,6,6,6', 'cycle']);
    assert.match(await page.locator('#subjScopeDlg [role="alert"]').innerText(), /Saqlandi\. ⚠ Ma‘ruza soatlari yig‘indisi 24 — rejada 26 soat\./);
    assert.equal(await page.evaluate(() => window.saved), 1);
    // Qayta chizilgan oyna — saqlangan qiymat bilan.
    assert.equal(await page.inputValue('#subjScopeDlg input[data-sslh="0|0"]'), '6,6,6,6');

    // 4) xd/23: "meros" — darhol saqlanadi (soatlarsiz); kunlar (21) yuborilgan maydondan.
    await page.evaluate(() => { window.calls = []; window.warnings = []; });
    await page.selectOption('#subjScopeDlg select[data-ssl="0|1"]', 'inherit');
    await page.waitForFunction(() => window.calls.some(c => c.method === 'POST' && !c.body.dry_run));
    body = (await saves())[0];
    assert.deepEqual([body.curriculum_hemis_id, body.cycle_days, body.lecture, body.lecture_hours], [701, 21, 'inherit', undefined]);

    // 5) Panel: alohida ma'ruza kartasi oqimning istalgan guruhcha qatoriga; oddiy karta — faqat o'z qatoriga.
    const ok = await page.evaluate(() => [
        window.cycCardRowOk({ row_key: 'a', row_keys: ['a', 'b'] }, 'b'), window.cycCardRowOk({ row_key: 'a', row_keys: ['a', 'b'] }, 'c'),
        window.cycCardRowOk({ row_key: 'a' }, 'a'), window.cycCardRowOk({ row_key: 'a' }, 'b'), window.cycCardRowOk(null, 'a')]);
    assert.deepEqual(ok, [true, false, true, false, false]);

    // 6) Mijoz kodi: ma'ruza kartasi oqim ko'rinishida joylanadi (card.view), ogohlantirish — toast; alohida ma'ruza bloki "M" belgisi va
    // ⚠ (kesishuv, boshqa fan amaliyoti, soat yig'indisi); kataklari sudralmaydi.
    assert.match(source, /view: card\.view \|\| cycleEffView\(\), placement_group: \(card && card\.placement_group\) \|\| undefined,\n\s+start_date/);
    // Ogohlantirish toast'i — api() ichida (bir marta); holat qatorida ham.
    assert.match(source, /showCyclePlacementResult\(card, index, warns\.length \? '⚠ ' \+ warns\.join\('; '\) : '', 'success'\)/);
    assert.doesNotMatch(source, /Joylandi, ogohlantirish bilan/);
    assert.match(source, /const sepNotes = \[block\.lecture_clash, \.\.\.\(block\.lecture_warn \|\| \[\]\), block\.lecture_note\]\.filter\(Boolean\);/);
    assert.match(source, /if \(block\.type === 'lecture' && block\.lecture_separate\) return;/);
    assert.match(source, /const typeChip = \(j && j\.simple && !card\.separate\)/);

    await browser.close();
    console.log('timetable-cycle-lecture-separate: ok');
})().catch(e => { console.error(e); process.exit(1); });
