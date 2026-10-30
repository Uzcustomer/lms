const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Butun doska (Excel)": faqat ruxsati bor rollarga ko'rinadi; tanlangan fakultetga qaramasdan, joriy qatlam bilan
// butun doska hisobotini so'raydi; yuklash paytida tugma o'chirilib "Tayyorlanmoqda…" turadi.
assert.match(source, /\$timetableCanBoardReport = in_array\(\$timetableActiveRole, \['superadmin', 'admin', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi'\], true\);/);
assert.match(source, /@if \(\$timetableCanBoardReport\)\s*<button type="button" id="unplacedReportBtn"[\s\S]*?Butun doska \(Excel\)\s*<\/button>\s*@endif/);

const a = source.indexOf("            // Butun doska bo'yicha joylanmagan kartalar hisoboti — tanlangan fakultetga");
const b = source.indexOf('\n            };\n', a);
assert(a > 0 && b > a);
const code = source.slice(a, b) + '\n            };';

let respond;
const fetched = [];
const downloads = [];
const alerts = [];
const btn = { innerHTML: 'Butun doska (Excel)', disabled: false };
const ctx = {
    $: id => (id === 'unplacedReportBtn' ? btn : null),
    board: { id: 7 }, BASE: '/admin', weeklyLayer: 'external',
    fetch: (url, opts) => { fetched.push(url); return new Promise(r => { respond = r; }); },
    URL: { createObjectURL: () => 'blob:x', revokeObjectURL() {} },
    URLSearchParams, setTimeout: () => {}, alert: m => alerts.push(m),
    document: { createElement: () => ({ click() { downloads.push(this.download); }, remove() {} }), body: { appendChild() {} } },
};
vm.createContext(ctx);
vm.runInContext(code, ctx);
const tick = () => new Promise(r => setImmediate(r));

(async () => {
    // 1) Yuklash: tugma o'chirilgan, "Tayyorlanmoqda…"; so'rov — joriy qatlam bilan, qamrov (fakultet) yuborilmaydi.
    let run = btn.onclick.call(btn);
    assert.equal(btn.disabled, true);
    assert.match(btn.innerHTML, /Tayyorlanmoqda…/);
    assert.match(fetched[0], /^\/admin\/boards\/7\/unplaced-report\?layer=external&_=\d+$/);
    respond({ ok: true, blob: async () => 'X', headers: { get: () => 'attachment; filename=joylanmagan_kartalar_butun_doska_2026-10-30.xlsx' } });
    await run;
    assert.deepEqual(downloads, ['joylanmagan_kartalar_butun_doska_2026-10-30.xlsx']);
    assert.equal(btn.disabled, false);
    assert.equal(btn.innerHTML, 'Butun doska (Excel)');

    // 2) Xato — xabar chiqadi, tugma tiklanadi.
    ctx.weeklyLayer = 'internal';
    run = btn.onclick.call(btn);
    assert.match(fetched[1], /layer=internal/);
    respond({ ok: false, status: 403, json: async () => ({ message: 'Bu hisobot uchun ruxsat yo\'q.' }) });
    await run;
    await tick();
    assert.deepEqual(alerts, ['Butun doska hisobotini yuklab bo\'lmadi: Bu hisobot uchun ruxsat yo\'q.']);
    assert.equal(btn.disabled, false);
    assert.equal(btn.innerHTML, 'Butun doska (Excel)');

    console.log('timetable unplaced report button: 2 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
