const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Butun doska (Excel)": faqat ruxsati bor rollarga ko'rinadi; hisobot fonda tayyorlanadi — bosilganda oyna (progress bar,
// foiz, bosqich nomi), holat so'rab turiladi, tayyor bo'lsa fayl o'zi yuklanadi; xato oynada chiqadi (alert yo'q).
assert.match(source, /\$timetableCanBoardReport = in_array\(\$timetableActiveRole, \['superadmin', 'admin', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi'\], true\);/);
assert.match(source, /@if \(\$timetableCanBoardReport\)\s*<button type="button" id="unplacedReportBtn"[\s\S]*?Butun doska \(Excel\)\s*<\/button>\s*@endif/);

const a = source.indexOf("            // Butun doska bo'yicha joylanmagan kartalar hisoboti — tanlangan fakultetga");
const b = source.indexOf('\n            };\n', a);
assert(a > 0 && b > a);
const code = source.slice(a, b) + '\n            };';
assert(!/alert\(/.test(code), 'xato alert bilan chiqmasin');
// Progress bar rangini o'z klassidan oladi (saytning tayyor CSS'ida Tailwind'ning h-3/bg-emerald-500 klasslari yo'q).
assert.match(code, /<div class="tt-report-track"><div id="urBar" class="tt-report-bar" style="width: 0%"><\/div><\/div>/);
assert(!/bg-emerald-500|bg-gray-200|\bh-3\b/.test(code), 'progress bar Tailwind klasslariga bog\'liq bo\'lmasin');
const css = source.slice(source.indexOf('.tt-report-track {'), source.indexOf('.tt-report-bar.is-error {') + 80);
assert.match(css, /\.tt-report-track \{[^}]*height: 10px;[^}]*overflow: hidden;[^}]*border-radius: 9999px;[^}]*background: #e5e7eb;/);
assert.match(css, /\.tt-report-bar \{[^}]*height: 100%;[^}]*background: #10b981;[^}]*transition: width \.3s;/);
assert.match(css, /\.tt-report-bar\.is-error \{ background: #ef4444; \}/);

process.exitCode = 1;   // osilib qolsa (promise hal bo'lmasa) — muvaffaqiyatsiz
const tick = () => new Promise(r => setImmediate(r));
const flush = async () => { for (let i = 0; i < 20; i++) await tick(); };

function makeCtx() {
    const els = {};
    const body = { appendChild(el) { if (el.id) els[el.id] = el; } };
    const el = id => {
        const cls = new Set();
        let text = '';
        return { id, style: {}, onclick: null,
            // Har yozilgan matn tarixda (progress ketma-ketligi)
            get textContent() { return text; },
            set textContent(v) { text = v; (ctx.log[id] = ctx.log[id] || []).push(v); },
            classList: { remove: c => cls.delete(c), add: c => cls.add(c), contains: c => cls.has(c) } };
    };
    const ctx = {
        calls: [], downloads: [], answers: [], timers: [], log: {},
        board: { id: 7 }, BASE: '/admin', weeklyLayer: 'internal',
        $: id => {
            if (id === 'unplacedReportBtn') return els.btn || (els.btn = { innerHTML: 'Butun doska (Excel)' });
            const m = els.unplacedReportModal;
            if (!m || m.removed) return null;
            // Oyna ichidagi elementlar — innerHTML dan (har chizishda yangilari)
            if (!m.kids || m.kidsFor !== m.innerHTML) {
                m.kidsFor = m.innerHTML;
                m.kids = {};
                for (const [, kid] of m.innerHTML.matchAll(/id="([^"]+)"/g)) m.kids[kid] = el(kid);
                m.kids.urErr.classList.add('hidden');
            }
            return id === 'unplacedReportModal' ? m : m.kids[id] || null;
        },
        api: (url, method = 'GET', data = null) => {
            ctx.calls.push([method, url, data]);
            const next = ctx.answers.shift();
            return next instanceof Error ? Promise.reject(next) : Promise.resolve(next);
        },
        fetch: (url) => { ctx.calls.push(['FETCH', url]); return Promise.resolve(ctx.download); },
        setTimeout: (fn, ms) => { ctx.timers.push(ms); setImmediate(fn); },
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL() {} },
        encodeURIComponent, Math, Number, Promise, Error,
        document: {
            createElement: tag => tag === 'a'
                ? { click() { ctx.downloads.push(this.download); }, remove() {} }
                : Object.assign(el(''), { remove() { this.removed = true; } }),
            body,
        },
    };
    vm.createContext(ctx);
    vm.runInContext(code, ctx);
    ctx.btn = ctx.$('unplacedReportBtn');
    ctx.modal = () => ctx.$('unplacedReportModal');
    ctx.txt = id => ctx.$(id).textContent;
    return ctx;
}
const plain = x => JSON.parse(JSON.stringify(x));   // vm kontekstidagi obyekt — boshqa prototip
const st = (status, percent, step_label, message = '') => ({ status, percent, step_label, message });

(async () => {
    // 1) Boshlash → holat so'raladi → progress (foiz, bosqich) → tayyor bo'lsa fayl yuklanadi.
    let c = makeCtx();
    c.answers.push({ token: 'T/1', state: st('queued', 0, 'Navbatda') },
        st('running', 37, 'Sabablar…'), st('done', 100, 'Tayyor'));
    c.download = { ok: true, blob: async () => 'X', headers: { get: () => 'attachment; filename=joylanmagan_kartalar_butun_doska_2026-10-30.xlsx' } };
    const run = c.btn.onclick.call(c.btn);
    assert.deepEqual(plain(c.calls[0]), ['POST', '/admin/boards/7/unplaced-report', { layer: 'internal' }]);
    assert.match(c.modal().innerHTML, /Butun doska \(Excel\)/);
    await run;
    assert.deepEqual(c.calls.map(x => x[0] + ' ' + x[1]).slice(1), ['GET /admin/boards/7/unplaced-report/status?token=T%2F1',
        'GET /admin/boards/7/unplaced-report/status?token=T%2F1', 'FETCH /admin/boards/7/unplaced-report/download?token=T%2F1']);
    assert.deepEqual(c.log.urStep, ['Navbatda', 'Sabablar…', 'Fayl yuklab olinmoqda…', 'Tayyor — fayl yuklab olindi.']);
    assert.deepEqual(c.log.urPct, ['0%', '37%', '100%']);
    assert.equal(c.$('urBar').style.width, '100%');
    assert(!c.$('urBar').classList.contains('is-error'));
    assert.deepEqual(c.downloads, ['joylanmagan_kartalar_butun_doska_2026-10-30.xlsx']);
    assert.equal(c.txt('urPct'), '100%');
    assert.equal(c.txt('urStep'), 'Tayyor — fayl yuklab olindi.');
    assert(c.$('urErr').classList.contains('hidden'));
    assert.equal(c.timers[0], 1500);

    // 2) Job xatosi — oynada qizil xabar, yuklanmaydi; qatlam (tashqi) so'rovga ketadi.
    c = makeCtx();
    c.weeklyLayer = 'external';
    c.answers.push({ token: 'T2', state: st('running', 10, 'Haftalik kartalar…') }, st('error', 30, 'Sabablar…', 'Xatolik: xotira yetmadi'));
    await c.btn.onclick.call(c.btn);
    assert.deepEqual(plain(c.calls[0][2]), { layer: 'external' });
    assert(!c.$('urErr').classList.contains('hidden'));
    assert.equal(c.txt('urErr'), 'Hisobotni tayyorlab bo\'lmadi: Xatolik: xotira yetmadi');
    assert.deepEqual(c.downloads, []);
    // Bar qizil, to'xtagan joyida; xato holatidagi foiz/bosqich ko'rsatilmaydi
    assert(c.$('urBar').classList.contains('is-error'));
    assert.equal(c.$('urBar').style.width, '10%');
    assert.deepEqual(c.log.urStep, ['Haftalik kartalar…', 'Xatolik']);
    assert.deepEqual(c.log.urPct, ['10%', '']);

    // 2b) Xato holati 100% va "Tayyor" bilan kelsa ham (eski holat) — "Tayyor 100%" chiqmaydi; ishlayotganda 100% emas.
    c = makeCtx();
    c.answers.push({ token: 'T2b', state: st('running', 100, 'Excel yozilmoqda…') }, st('error', 100, 'Tayyor', 'Xatolik: disk to\'la'));
    await c.btn.onclick.call(c.btn);
    assert.deepEqual(c.log.urPct, ['99%', '']);
    assert.deepEqual(c.log.urStep, ['Excel yozilmoqda…', 'Xatolik']);
    assert(c.$('urBar').classList.contains('is-error'));

    // 3) Boshlashning o'zi rad etildi (403) — xabar oynada.
    c = makeCtx();
    c.answers.push(new Error('Bu hisobot uchun ruxsat yo\'q.'));
    await c.btn.onclick.call(c.btn);
    assert.equal(c.txt('urErr'), 'Hisobotni tayyorlab bo\'lmadi: Bu hisobot uchun ruxsat yo\'q.');

    // 4) Holat so'rovi bir-ikki marta uzilsa — kutadi; uchinchi ketma-ket xatoda — xabar.
    c = makeCtx();
    c.answers.push({ token: 'T3', state: st('running', 5, 'Haftalik kartalar…') }, new Error('Failed to fetch'),
        st('running', 50, 'Sabablar…'), new Error('Failed to fetch'), new Error('Failed to fetch'), new Error('Hisobot topilmadi'));
    await c.btn.onclick.call(c.btn);
    assert.equal(c.calls.length, 6);
    assert.equal(c.txt('urErr'), 'Hisobotni tayyorlab bo\'lmadi: Hisobot topilmadi');

    // 5) Oyna yopilsa — so'rash to'xtaydi, fayl yuklanmaydi (hisobot fonda davom etadi).
    c = makeCtx();
    c.answers.push({ token: 'T4', state: st('running', 5, 'Haftalik kartalar…') }, st('done', 100, 'Tayyor'));
    const run5 = c.btn.onclick.call(c.btn);
    await tick();
    c.$('urClose').onclick();
    assert.equal(c.modal(), null);
    await run5;
    await flush();
    assert.equal(c.calls.length, 1);
    assert.deepEqual(c.downloads, []);

    // 6) Yuklab olish rad etilsa (409) — server xabari oynada.
    c = makeCtx();
    c.answers.push({ token: 'T5', state: st('done', 100, 'Tayyor') });
    c.download = { ok: false, status: 409, json: async () => ({ error: 'Hisobot hali tayyor emas yoki fayl o\'chirilgan.' }) };
    await c.btn.onclick.call(c.btn);
    assert.equal(c.txt('urErr'), 'Hisobotni tayyorlab bo\'lmadi: Hisobot hali tayyor emas yoki fayl o\'chirilgan.');

    // 7) Yuklab olishda server xatosi (500, ruxsat) — aniq xabar, bar qizil, "Tayyor 100%" qolmaydi.
    c = makeCtx();
    c.answers.push({ token: 'T6', state: st('done', 100, 'Tayyor') });
    c.download = { ok: false, status: 500, json: async () => ({ error: 'Fayl o\'qib bo\'lmadi (ruxsat)' }) };
    await c.btn.onclick.call(c.btn);
    assert.equal(c.txt('urErr'), 'Hisobotni tayyorlab bo\'lmadi: Fayl o\'qib bo\'lmadi (ruxsat)');
    assert(c.$('urBar').classList.contains('is-error'));
    assert.equal(c.txt('urStep'), 'Xatolik');
    assert.equal(c.txt('urPct'), '');

    process.exitCode = 0;
    console.log('timetable unplaced report button: 8 scenarios passed');
})().catch(e => { console.error(e); process.exitCode = 1; });
