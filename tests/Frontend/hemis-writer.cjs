const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.join(__dirname, '../..');
const writerPath = 'public/js/hemis-writer.js';

// 1) Xatcho'p yuklaydigan fayl bor va git uni e'tiborsiz qoldirmaydi (aks holda serverga chiqmaydi — 404).
const blade = fs.readFileSync(path.join(root, 'resources/views/admin/timetable/index.blade.php'), 'utf8');
assert(blade.includes("asset('js/hemis-writer.js')"));
assert(fs.existsSync(path.join(root, writerPath)));
const ignored = spawnSync('git', ['check-ignore', '-q', '--no-index', writerPath], { cwd: root });
if (!ignored.error && ignored.status !== 128) assert.equal(ignored.status, 1, writerPath + ' .gitignore bilan yashirilgan');

// 2) HEMIS formasi bilan ishlash qismi hemis-write.mjs bilan bir xil.
const writer = fs.readFileSync(path.join(root, writerPath), 'utf8');
const mjs = fs.readFileSync(path.join(root, 'tools/hemis-sync/hemis-write.mjs'), 'utf8');
const cut = (s, end) => { const a = s.indexOf('const runInPage = async'), b = s.indexOf(end, a); assert(a >= 0 && b > a); return s.slice(a, b); };
const norm = s => s.split('\n').map(l => l.trim()).filter(Boolean).join('\n');
assert.equal(norm(cut(writer, '// --- Oyna')), norm(cut(mjs, 'const apply = has(')));

// 3) Oyna mantig'i — kichik soxta DOM ichida.
const unesc = s => s.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const els = {};
const node = id => els[id] || (els[id] = new El(id));
function parse(html) {
    for (const m of html.matchAll(/<select id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) {
        // Brauzer kabi: value atributi bo'lmasa — matn, bo'shliqlari qisqartirilgan holda.
        node(m[1]).options = [...m[2].matchAll(/<option(?: value="([^"]*)")?>([^<]*)<\/option>/g)]
            .map(o => o[1] !== undefined ? unesc(o[1]) : unesc(o[2]).replace(/[\t\n\f\r ]+/g, ' ').trim());
        els[m[1]].value = els[m[1]].options[0];
    }
    for (const m of html.matchAll(/<input([^>]*)>/g)) {
        const id = (m[1].match(/id="([^"]+)"/) || [])[1];
        if (id) node(id).checked = /\bchecked\b/.test(m[1]);
    }
    for (const m of html.matchAll(/id="([^"]+)"/g)) node(m[1]);
}
class El {
    constructor(id) { Object.assign(this, { id, style: {}, value: '', checked: false, disabled: false, textContent: '', options: [], html: '' }); }
    get innerHTML() { return this.html; }
    set innerHTML(h) { this.html = h; parse(h); }
    scrollIntoView() {}
    remove() {}
}
const alerts = [];
const ctx = {
    window: {}, location: { hostname: 'hemis.ttatf.uz', pathname: '/curriculum/schedule-info' },
    document: {
        getElementById: id => els[id] || null, createElement: () => new El(null), body: { appendChild() {} },
        querySelector: s => s.includes('csrf') ? { content: '_csrf-backend' } : null,
    },
    alert: m => alerts.push(m), confirm: () => true, setTimeout, console,
};
vm.createContext(ctx);
vm.runInContext(writer, ctx);
assert.deepEqual(alerts, []);

const act = o => Object.assign({ start: '08:30', end: '09:50', code: '13', employee_id: 141, auditorium_code: '438', blocked: [], changes: [] }, o);
const rep = {
    today: '2099-10-27', hemis_pairs: { '08:30-09:50': 11 }, current_week: 7, weeks: [{ week: 7, from: '2099-10-26', to: '2099-11-01' }],
    groups: [
        { group: 'p/st26-01a', group_hemis_id: 1596, units: [{ subject_id: 16, subject_name: 'Lotin tili', actions: [
            act({ op: 'create', date: '2099-10-27' }),                                  // bugun — yozilmaydi
            act({ op: 'update', hemis_id: 780280, date: '2099-10-28' }),
            act({ op: 'create', date: '2099-10-29', blocked: ['xona topilmadi'] }),   // to'siqli
        ] }] },
        { group: 'p/st26-01b', group_hemis_id: 1597, units: [{ subject_id: 17, subject_name: 'Ichki  kasalliklar ', actions: [
            act({ op: 'create', date: '2099-10-28' }),
        ] }] },
    ],
};
const done = (async () => {
    await els.hwFile.onchange.call({ files: [{ text: async () => JSON.stringify(rep) }] });
    const count = () => Number((els.hwSummary.innerHTML.match(/Bajariladi: <b>(\d+)<\/b>/) || [])[1]);
    assert.equal(count(), 2);
    assert.match(els.hwSummary.innerHTML, /Bugun: <b>2099-10-27<\/b>/);
    assert.match(els.hwSummary.innerHTML, /O'tkaziladi: 2 ta \(shundan to'siqli 1\)/);

    // Fan nomida ortiqcha bo'shliq bo'lsa ham tanlangan fan topiladi.
    els.hwSubject.value = els.hwSubject.options.find(v => v.startsWith('Ichki'));
    els.hwSubject.onchange();
    assert.equal(count(), 1);
    assert.match(els.hwList.textContent, /Ichki {2}kasalliklar/);

    els.hwSubject.value = '';
    els.hwGroup.value = els.hwGroup.options.find(v => v === 'p/st26-01a');
    els.hwGroup.onchange();
    assert.equal(count(), 1);
    assert.match(els.hwList.textContent, /O'zgartirish · p\/st26-01a · 2099-10-28/);

    // 4) Yozish davomida filtrlar o'chiq; filtr baribir o'zgarsa ham bajarilayotgan ro'yxat va guruh tasdig'idagi son
    // boshlanishdagi holatdan olinadi (aks holda «0 ta amal» deb so'rab, guruhni yozib yuborardi).
    els.hwGroup.value = '';
    els.hwGroup.onchange();
    assert.equal(count(), 2);
    const confirms = [], fetched = [];
    let openGate;
    ctx.confirm = m => { confirms.push(m.split('\n')[0]); return true; };
    ctx.fetch = url => { fetched.push(url); return fetched.length === 1 ? new Promise(r => { openGate = () => r({ status: 404 }); }) : Promise.resolve({ status: 404 }); };
    els.hwApply.onclick();
    assert.equal(fetched.length, 1);
    for (const id of ['hwUntil', 'hwGroup', 'hwSubject', 'hwOp_create', 'hwOp_update', 'hwOp_delete']) assert.equal(els[id].disabled, true, id);
    els.hwGroup.value = 'p/st26-01a';
    els.hwGroup.onchange();
    openGate();
    while (!/Yozildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 50));
    assert.deepEqual(confirms, ['2 ta amal HEMIS\'ga yoziladi (har guruh oldidan yana so\'raladi). Boshlaymizmi?',
        'p/st26-01a: 1 ta amal HEMIS\'ga yoziladi.', 'p/st26-01b: 1 ta amal HEMIS\'ga yoziladi.']);
    assert.equal(fetched.length, 2);
    assert.equal(els.hwGroup.disabled, false);
})();
done.then(() => console.log('5 HEMIS writer bookmarklet scenarios passed'), e => { console.error(e); process.exit(1); });
