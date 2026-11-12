const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// "Joylash uchun tanlash" — tanlangan karta guruh ustuni: belgilash (chap/o'ng qalin chiziq, sarlavha ham), jadval shu ustunga suriladi
// (birinchi yashil katak, bo'lmasa ustunning birinchi qatori), ustun filtrda yo'q — "Guruhni ko'rsatish". Haqiqiy brauzer DOM'ida (Playwright).
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const code = cut("            // ── Tanlangan kartaning guruh ustuni", '            function fmEnsureColumn(c) {');
const css = cut('        /* "Joylash uchun tanlash": tanlangan karta guruh ustuni', '        /* Sikl: tanlangan karta guruh qatori');
// Ulanishlar manbada: render har safar belgilaydi; "Joylash uchun tanlash" va fan mas'uli rejimi bitta surish funksiyasi.
assert.match(source, /focusHighlight\(\);\n\s*markPickColumns\(\);/);
assert.match(source, /selected = \(selected && selected\.id === c\.id\) \? null : c;\n\s*renderPanel\(\); renderGrid\(\);\n[^\n]*\n\s*if \(selected\) scrollToPickColumn\(selected\);/);
assert.match(source, /fmPick\.scrolled = true;\n\s*scrollToPickColumn\(fmPick\.card\);/);
assert.match(source, /'" data-gh="' \+ esc\(gr\) \+ '">'/);
assert.equal((source.match(/ data-gs="' \+ esc\(o\.groups\.slice\(gi, gi \+ span\)\.join\('§'\)\) \+ '"/g) || []).length, 2);
assert.match(source, /if \(ok && cycleDragKey === cardKey\) cell\.classList\.add\('cyc-pick-row'\);/);

(async () => {
    const browser = await chromium.launch();
    try {
        const p = await (await browser.newContext({ viewport: { width: 500, height: 300 } })).newPage();
        const errs = [];
        p.on('pageerror', e => errs.push(e.message));
        // Keng jadval (ustun o'ngda, ekrandan tashqarida): A, B (tanlangan karta guruhi), C; B ustunida — ma'ruza (A+B, data-gs) va bo'sh kataklar.
        const cols = ['A', 'B', 'C'];
        const row = (r, b) => '<tr>' + cols.map(g => (g === 'B' && b ? b : '<td data-g="F¦S¦1¦' + g + '" style="min-width:400px;height:200px">' + r + g + '</td>')).join('') + '</tr>';
        await p.setContent('<!doctype html><html><head><style>' + css + '</style></head><body><div id="gridWrap" style="overflow:auto;width:480px;height:280px">' +
            '<table id="grid"><thead><tr>' + cols.map(g => '<th data-gh="F¦S¦1¦' + g + '">' + g + '</th>').join('') + '</tr></thead><tbody>' +
            '<tr><td data-gs="F¦S¦1¦A§F¦S¦1¦B" colspan="2" style="height:200px">Ma\'ruza</td><td data-g="F¦S¦1¦C">1C</td></tr>' +
            row('2') + row('3', '<td data-g="F¦S¦1¦B" class="tt-ok" style="min-width:400px;height:200px">3B</td>') + '</tbody></table></div></body></html>');
        await p.addScriptTag({ content: `
            let viewMode = 'group', selected = null, fmPick = null;
            window.ensure = []; window.rendered = 0;
            const cardGKeys = c => c.groups.map(g => 'F¦S¦1¦' + g);
            function fmEnsureColumn(c) { window.ensure.push(c.id); return true; }
            function renderAll() { window.rendered++; }
            function buildGroupRows() {} function renderPanel() {} function renderGrid() {}
        ` + code + `
            window.T = { pickColumnTargets, markPickColumns, scrollToPickColumn, set: o => { selected = o.selected; viewMode = o.viewMode || 'group'; } };` });
        assert.deepEqual(errs, []);
        const cls = sel => p.evaluate(s => [...document.querySelectorAll(s)].map(x => x.textContent.trim()), sel);
        // 1) Tanlov: B ustuni kataklari (ma'ruza katagi ham) va sarlavhasi belgilanadi; A va C — yo'q.
        await p.evaluate(() => { T.set({ selected: { id: 5, groups: ['B'] } }); T.markPickColumns(); });
        assert.deepEqual(await cls('td.tt-pick-col'), ["Ma'ruza", '2B', '3B']);
        assert.deepEqual(await cls('th.tt-pick-colh'), ['B']);
        const shadow = await p.$eval('th.tt-pick-colh', el => getComputedStyle(el).boxShadow);
        assert.match(shadow, /rgb\(37, 99, 235\) 3px 0px 0px 0px inset/);
        assert.match(shadow, /rgb\(255, 255, 255\) 4px 0px 0px 0px inset/);
        // 2) Surish: birinchi yashil (tt-ok) katak — 3B ko'rinadigan bo'ladi.
        await p.evaluate(() => T.scrollToPickColumn({ id: 5, groups: ['B'] }));
        const vis = await p.$eval('td.tt-ok', el => { const r = el.getBoundingClientRect(), w = document.getElementById('gridWrap').getBoundingClientRect();
            return r.left < w.right && r.right > w.left && r.top < w.bottom && r.bottom > w.top; });
        assert.equal(vis, true);
        assert.ok(await p.$eval('#gridWrap', el => el.scrollLeft > 0 && el.scrollTop > 0));
        // Yashil yo'q — ustunning birinchi qatori (ma'ruza katagi).
        await p.evaluate(() => { document.querySelector('td.tt-ok').classList.remove('tt-ok'); document.getElementById('gridWrap').scrollTo(0, 0); });
        await p.evaluate(() => T.scrollToPickColumn({ id: 5, groups: ['C'] }));
        assert.ok(await p.$eval('#gridWrap', el => el.scrollLeft > 0));
        // 3) Tanlov bekor — belgi olinadi.
        await p.evaluate(() => { T.set({ selected: null }); T.markPickColumns(); });
        assert.equal((await cls('.tt-pick-col,.tt-pick-colh')).length, 0);
        assert.equal(await p.$('#ttPickColMsg'), null);
        // 4) Guruh ustuni filtrda yo'q — xabar va "Guruhni ko'rsatish" (filtrga qo'shib qayta chizish).
        await p.evaluate(() => { T.set({ selected: { id: 9, groups: ['Z'] } }); T.markPickColumns(); });
        assert.equal(await p.$eval('#ttPickColMsg', el => el.textContent), "Bu guruh hozirgi filtrda ko'rinmaydi — Guruhni ko'rsatish");
        await p.click('#ttPickColMsg [data-pick-show]');
        assert.deepEqual(await p.evaluate(() => [window.ensure, window.rendered]), [[9], 1]);
        assert.deepEqual(errs, []);
        console.log('timetable pick column: 4 scenarios passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
