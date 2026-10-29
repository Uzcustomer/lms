const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a);
    return source.slice(a, b);
}

// HEMIS solishtirish oynasi: "Bu davrda moslar" belgisi olinsa — tanlangan davrgacha amali yo'q, keyingi haftalarda
// amali bor fanlar jadvaldan yashiriladi va sanoqda alohida ko'rsatiladi; JSON (HEMIS'ga yozish) o'zgarmaydi.
const els = {};
let blob = null;
const ctx = {
    $: id => els[id] || (els[id] = { id, value: '1', checked: true }),
    document: { querySelectorAll: () => [], createElement: () => ({ click() {}, remove() {} }), body: { appendChild() {} }, addEventListener() {} },
    scopeLabelText: () => 'Hammasi', HS_WRITER_URL: '/js/hemis-writer.js',
    Blob, URL: { createObjectURL: b => { blob = b; return 'blob:test'; } },
};
vm.createContext(ctx);
vm.runInContext(section('let hemisSyncFilter', 'async function hemisSyncRun()')
    + '; this.render = hemisSyncRender; this.filter = () => hemisSyncFilter;', ctx);

const unit = (name, actions, later = 0) => ({
    subject_name: name, kafedra: 'K', codes: { 13: 'A' }, code_names: { 13: 'Amaliy' }, fixes: [], notes: [], actions, actions_later: later,
    before: { hours_diff: { 13: 2 }, count_diff: { A: 1 } }, after: { hours_diff: { 13: 0 }, count_diff: { A: 0 } }, period: null,
});
const act = o => Object.assign({ op: 'create', date: '2099-10-05', start: '12:00', end: '13:20', code: '13', employee_name: 'USTOZ',
    auditorium_name: '423', flags: [], changes: [], blocked: [], blocks: [] }, o);
const rep = {
    today: '2099-10-01', weeks: [{ week: 1, from: '2099-09-28', to: '2099-10-04' }], current_week: 1, period_from: 1, period_to: 1,
    fallback_room: ['KM1', 'Kafedra mudiri xonasi'], groups_not_in_hemis: [],
    groups: [
        // Fiziologiya — bu davrda mos (amali faqat keyingi haftalarda)
        { group: 'G-A', units: [unit('Anatomiya', [act({})]), unit('Fiziologiya', [], 3)], skipped: [], cards_without_subject: [] },
        // Faqat "bu davrda mos" fanli guruh — belgi olinsa sarlavhasi ham chiqmaydi
        { group: 'G-B', units: [unit('Biologiya', [], 2)], skipped: [], cards_without_subject: [] },
        // Fanlari yashirilsa ham ogohlantirishlar (o'tkazilgan fan, rejada topilmagan kartochka) qoladi
        { group: 'G-C', units: [unit('Kimyo', [], 1)],
            skipped: [{ subject_name: 'Lotin tili', reason: 'LMS doskasida kartochka yo\'q' }], cards_without_subject: ['Eski fan'] },
    ],
};
const html = () => els.hemisSyncModal.innerHTML;
const shown = () => {
    const h = html();
    return [...h.matchAll(/<h4[^>]*>([^<]*)/g)].map(m => 'guruh:' + m[1].trim())
        .concat([...h.matchAll(/<tr class="align-top"><td class="border px-1">([^<]*)/g)].map(m => 'fan:' + m[1]))
        .concat([...h.matchAll(/<tr class="text-gray-500"><td class="border px-1">([^<]*)/g)].map(m => 'o\'tkazilgan:' + m[1]))
        .concat([...h.matchAll(/rejasida topilmagan kartochkalar: ([^<]*)/g)].map(m => 'kartochka:' + m[1]));
};
const json = () => { els.hsJson.onclick(); return blob.text(); };

(async () => {
    // 1) Sukut (belgi bor): "bu davrda mos" fanlar avvalgidek ko'rinadi.
    ctx.render(rep);
    assert.equal(els.hsPeriodMatch.checked, true);
    assert.match(html(), /id="hsPeriodMatch" checked/);
    assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-B', 'guruh:G-C', 'fan:Anatomiya', 'fan:Fiziologiya', 'fan:Biologiya', 'fan:Kimyo',
        'o\'tkazilgan:Lotin tili', 'kartochka:Eski fan']);
    assert.match(html(), /keyingi haftalarda 3 ta amal/);
    assert(!html().includes('bu davrda mos (yashirildi)'));
    const before = await json();

    // 2) Belgi olinadi (checkbox orqali): Fiziologiya, Biologiya, Kimyo yashiriladi; G-B sarlavhasi ham;
    // G-C ogohlantirishlari qoladi; sanoqda — alohida; keyingi haftalardagi amallar soni o'zgarmaydi.
    els.hsPeriodMatch.checked = false;
    els.hsPeriodMatch.onchange();
    assert.equal(ctx.filter().periodMatch, false);
    assert.match(html(), /id="hsPeriodMatch">/);
    assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-C', 'fan:Anatomiya', 'o\'tkazilgan:Lotin tili', 'kartochka:Eski fan']);
    assert.match(html(), /3 ta fan bu davrda mos \(yashirildi\)/);
    assert.match(html(), /tanlangan haftadan keyin yana 6 ta amal/);
    // Faqat ko'rinish: HEMIS'ga yozish JSON'i o'zgarmaydi.
    assert.equal(await json(), before);

    // 3) Amal turi filtri bilan birga: "bu davrda mos" fanlar shu sanoqda, qolgan amalsizlar — "tanlangan turdagi amal yo'q"da.
    ctx.filter().ops = { create: false, update: false, delete: false, blocked: true };
    ctx.render(rep);
    assert.deepEqual(shown(), []);
    assert.match(html(), /1 ta fanda tanlangan turdagi amal yo'q \(yashirildi\) · 3 ta fan bu davrda mos \(yashirildi\)/);

    // 4) Fan va guruh filtri bilan birga: faqat tanlanganlar sanaladi.
    ctx.filter().ops = { create: true, update: true, delete: true, blocked: true };
    ctx.filter().subjects = ['Anatomiya', 'Fiziologiya'];
    ctx.render(rep);
    // Rejada topilmagan kartochkalar ogohlantirishi fan filtriga bog'liq emas (avvalgidek).
    assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-C', 'fan:Anatomiya', 'kartochka:Eski fan']);
    assert.match(html(), /· 1 ta fan bu davrda mos \(yashirildi\)/);
    ctx.filter().subjects = null;
    ctx.filter().groups = ['G-B'];
    ctx.render(rep);
    assert.deepEqual(shown(), []);
    assert.match(html(), /· 1 ta fan bu davrda mos \(yashirildi\)/);

    console.log('4 HEMIS compare "Bu davrda moslar" scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
