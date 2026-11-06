const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// "Darslar" oynasi: sikl fanining kunlari soni fakultet / o'quv reja bo'yicha alohida. Saqlashdan OLDIN oldindan ko'rish (dry_run):
// joylangan bloklar uzunligi o'zgarsa — "N ta joylangan blok uzunligi o'zgaradi" + ro'yxat (fakultet, oqim), "Saqlash" / "Bekor".
// Oynada: fakultetlar, ichida o'quv rejalar ("Xalqaro ta'lim · xd/22 · 8-semestr (4 guruh)" + reja nomi); bo'sh — yuqori daraja.
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

const SCOPES = {
    mode: 'cycle', days: 18, faculties: [
        { name: '2-son davolash fakulteti', days: null, curricula: [{ id: 501, name: 'Davolash ishi 2022', semester: '8-semestr', prefix: 'd2/22', groups: 6, days: null }] },
        { name: 'Xalqaro ta\'lim fakulteti', days: 21, curricula: [
            { id: 601, name: 'Davolash ishi (xalqaro) 2022', semester: '8-semestr', prefix: 'xd/22', groups: 4, days: 13 },
            { id: 701, name: 'Davolash ishi (xalqaro) 2023', semester: '7-semestr', prefix: 'xd/23', groups: 3, days: null },
            { id: 0, name: 'O‘quv rejasi aniqlanmagan', semester: null, prefix: 'xd/21', groups: 1, days: null },
        ] },
    ],
};

const page0 = `<!doctype html><html><head><style>${css}</style></head><body><script>
    const BASE = '/admin/timetable';
    const board = { id: 7 };
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    window.calls = [];
    window.affected = [];
    window.scopes = ${JSON.stringify(SCOPES)};
    async function api(url, method = 'GET', body = null) {
        window.calls.push({ url, method, body: body ? JSON.parse(JSON.stringify(body)) : null });
        if (url.includes('/subject-setting/scopes')) return JSON.parse(JSON.stringify(window.scopes));
        return { ok: true, dry_run: !!(body && body.dry_run), affected: window.affected };
    }
${params}
${code}
    window.subjSettingSave = subjSettingSave;
    window.openSubjScopeDlg = openSubjScopeDlg;
    window.subjectModeParamsHtml = subjectModeParamsHtml;
</script></body></html>`;

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    page.on('pageerror', e => { throw e; });
    await page.setContent(page0);
    const calls = () => page.evaluate(() => window.calls.map(c => (c.method || 'GET') + (c.body && c.body.dry_run ? ':dry' : '')));
    const reset = () => page.evaluate(() => { window.calls = []; });

    // 1) Sikl qatori: tugma (saqlangan aniqlovchilar soni bilan).
    const html = await page.evaluate(() => [window.subjectModeParamsHtml({ mode: 'cycle', cycle_days: 18 }),
        window.subjectModeParamsHtml({ mode: 'cycle', cycle_days: 18, scoped: [{}, {}] }), window.subjectModeParamsHtml({ mode: 'normal' })]);
    assert.match(html[0], /class="asc-subj-scope"[^>]*>Fakultet \/ o'quv reja bo'yicha alohida<\/button>/);
    assert.match(html[1], />Fakultet \/ o'quv reja bo'yicha alohida \(2\)<\/button>/);
    assert.doesNotMatch(html[2], /asc-subj-scope/);

    // 2) Joylangan bloklar o'zgarmaydi — tasdiqsiz: oldindan ko'rish + saqlash.
    let r = await page.evaluate(() => window.subjSettingSave({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Ichki kasalliklar', mode: 'cycle', cycle_days: 18 }));
    assert.equal(r.dry_run, false);
    assert.deepEqual(await calls(), ['POST:dry', 'POST']);
    assert.equal(await page.locator('#subjAffectDlg').count(), 0);

    // 3) Sikl emas — oldindan ko'rishsiz, bitta so'rov.
    await reset();
    await page.evaluate(() => window.subjSettingSave({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Fan', mode: 'normal' }));
    assert.deepEqual(await calls(), ['POST']);

    // 4) Bloklar o'zgaradi — tasdiq oynasi (fakultet, oqim, eski → yangi). "Bekor": saqlanmaydi (faqat oldindan ko'rish), null.
    await reset();
    await page.evaluate(() => {
        window.affected = [
            { placement_id: 1, row: 'Xalqaro ta\'lim fakulteti / 1-oqim', faculty: 'Xalqaro ta\'lim fakulteti', type: 'practice', from: 18, to: 13 },
            { placement_id: 2, row: 'xd/22-03a', faculty: 'Xalqaro ta\'lim fakulteti', type: 'lecture', from: 18, to: 13 },
        ];
        window.pending = window.subjSettingSave({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Ichki kasalliklar', mode: 'cycle',
            faculty_name: 'Xalqaro ta\'lim fakulteti', curriculum_hemis_id: 601, cycle_days: 13 });
    });
    await page.waitForSelector('#subjAffectDlg');
    const dlg = await page.locator('#subjAffectDlg').innerText();
    assert.match(dlg, /2 ta joylangan blok uzunligi o'zgaradi/);
    assert.match(dlg, /Xalqaro ta'lim fakulteti · Xalqaro ta'lim fakulteti \/ 1-oqim: 18 → 13 kun/);
    assert.match(dlg, /xd\/22-03a \(ma'ruza\): 18 → 13 kun/);
    assert.deepEqual(await page.locator('#subjAffectDlg button').allInnerTexts(), ['Bekor', 'Saqlash']);
    await page.click('#subjAffectDlg [data-saf="cancel"]');
    assert.equal(await page.evaluate(() => window.pending), null);
    assert.deepEqual(await calls(), ['POST:dry']);
    assert.equal(await page.locator('#subjAffectDlg').count(), 0);

    // "Saqlash": haqiqiy so'rov (aniqlovchilar bilan).
    await reset();
    await page.evaluate(() => { window.pending = window.subjSettingSave({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Ichki kasalliklar',
        mode: 'cycle', faculty_name: 'Xalqaro ta\'lim fakulteti', curriculum_hemis_id: 601, cycle_days: 13 }); });
    await page.waitForSelector('#subjAffectDlg');
    await page.click('#subjAffectDlg [data-saf="ok"]');
    r = await page.evaluate(() => window.pending);
    assert.equal(r.ok, true);
    assert.deepEqual(await calls(), ['POST:dry', 'POST']);
    const sent = await page.evaluate(() => window.calls[1].body);
    assert.deepEqual([sent.faculty_name, sent.curriculum_hemis_id, sent.cycle_days, sent.dry_run], ['Xalqaro ta\'lim fakulteti', 601, 13, undefined]);

    // 5) Oyna: fakultetlar va o'quv rejalar; bo'sh joyda meros qiymat (o'quv reja → fakultet → umumiy).
    await reset();
    await page.evaluate(() => { window.affected = []; window.saved = 0;
        window.openSubjScopeDlg({ specialty_name: 'Davolash ishi', course: 4, subject_name: 'Ichki kasalliklar' }, async () => { window.saved++; }); });
    await page.waitForSelector('#subjScopeDlg input[data-ssc]');
    assert.match(await calls().then(c => c.join()), /^GET$/);
    assert.match(await page.evaluate(() => window.calls[0].url), /\/boards\/7\/subject-setting\/scopes\?specialty_name=Davolash\+ishi&course=4&subject_name=Ichki\+kasalliklar$/);
    const text = await page.locator('#subjScopeDlg').innerText();
    assert.match(text, /Ichki kasalliklar · Davolash ishi · 4-kurs — umumiy: 18 kun/);
    assert.match(text, /Xalqaro ta'lim · xd\/22 · 8-semestr \(4 guruh\)\s*Davolash ishi \(xalqaro\) 2022/);
    assert.match(text, /Xalqaro ta'lim · xd\/23 · 7-semestr \(3 guruh\)/);
    assert.match(text, /2-son davolash · d2\/22 · 8-semestr \(6 guruh\)/);
    const inputs = await page.$$eval('#subjScopeDlg input[data-ssc]', xs => xs.map(x => [x.dataset.ssc, x.value, x.placeholder, x.disabled]));
    assert.deepEqual(inputs, [
        ['0', '', 'umumiy: 18', false], ['0|0', '', 'umumiy: 18', false],
        ['1', '21', 'umumiy: 18', false], ['1|0', '13', 'fakultet: 21', false], ['1|1', '', 'fakultet: 21', false],
        ['1|2', '', 'fakultet: 21', true],   // o'quv rejasi aniqlanmagan — alohida qiymat berib bo'lmaydi
    ]);

    // O'quv reja qiymatini o'zgartirish: oldindan ko'rish → (bloklar o'zgarmasa) saqlash → onSaved → oyna yangilanadi.
    await reset();
    await page.evaluate(() => { window.scopes.faculties[1].curricula[1].days = 16; });
    await page.fill('#subjScopeDlg input[data-ssc="1|1"]', '16');
    await page.press('#subjScopeDlg input[data-ssc="1|1"]', 'Tab');   // foydalanuvchi kabi: qiymat yozilib, maydondan chiqiladi
    await page.waitForFunction(() => /Saqlandi/.test(document.querySelector('#subjScopeDlg [role="alert"]').textContent));
    assert.deepEqual(await calls(), ['POST:dry', 'POST', 'GET']);
    const body = await page.evaluate(() => window.calls[1].body);
    assert.deepEqual([body.mode, body.faculty_name, body.curriculum_hemis_id, body.cycle_days], ['cycle', 'Xalqaro ta\'lim fakulteti', 701, 16]);
    assert.equal(await page.evaluate(() => window.saved), 1);
    assert.equal(await page.inputValue('#subjScopeDlg input[data-ssc="1|1"]'), '16');

    // Fakultet qiymatini o'chirish (bo'sh) — cycle_days yuborilmaydi (server aniqlovchili yozuvni o'chiradi); bloklar o'zgaradi —
    // tasdiq; "Bekor" — saqlanmaydi, oyna eski qiymat bilan qayta chiziladi.
    await reset();
    await page.evaluate(() => { window.affected = [{ placement_id: 3, row: 'Xalqaro ta\'lim fakulteti / 2-oqim', faculty: 'Xalqaro ta\'lim fakulteti', type: 'practice', from: 21, to: 18 }]; });
    await page.fill('#subjScopeDlg input[data-ssc="1"]', '');
    await page.press('#subjScopeDlg input[data-ssc="1"]', 'Tab');
    await page.waitForSelector('#subjAffectDlg');
    assert.match(await page.locator('#subjAffectDlg').innerText(), /1 ta joylangan blok uzunligi o'zgaradi[\s\S]*2-oqim: 21 → 18 kun/);
    const dryBody = await page.evaluate(() => window.calls[0].body);
    assert.equal(dryBody.cycle_days, null);   // api() null qiymatni formaga qo'shmaydi — cycle_days yuborilmaydi
    assert.deepEqual([dryBody.faculty_name, dryBody.curriculum_hemis_id], ['Xalqaro ta\'lim fakulteti', 0]);
    await page.click('#subjAffectDlg [data-saf="cancel"]');
    await page.waitForFunction(() => /Bekor qilindi/.test(document.querySelector('#subjScopeDlg [role="alert"]').textContent)
        || document.querySelector('#subjScopeDlg input[data-ssc="1"]').value === '21');
    await page.waitForFunction(() => document.querySelector('#subjScopeDlg input[data-ssc="1"]').value === '21');
    assert.deepEqual((await calls()).filter(c => c !== 'GET'), ['POST:dry']);
    assert.equal(await page.evaluate(() => window.saved), 1);

    await browser.close();
    console.log('timetable-subject-scope: ok');
})().catch(e => { console.error(e); process.exit(1); });
