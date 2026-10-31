const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const section = (from, to) => { const a = source.indexOf(from), b = source.indexOf(to, a); assert(a >= 0 && b > a, from); return source.slice(a, b); };

// «Bugungi darslar (HEMIS)»: tugma faqat registrator ofisi / admin uchun (HEMIS solishtirish yonida); oyna sarlavhasidagi hisoblar,
// tegilmaydigan darslar kulrang, guruh filtri, JSON (faqat bugungi o'zgartirishlar) va Excel.
assert.match(source, /@if\(\$timetableCanApprove\)\n\s*<button type="button" id="hemisSyncBtn"[^\n]*\n\s*<button type="button" id="hemisTodayBtn" [^>]*>Bugungi darslar \(HEMIS\)<\/button>/);
assert.match(source, /\$timetableCanApprove = in_array\(\$timetableActiveRole, \['superadmin', 'admin', 'registrator_ofisi'\], true\);/);

const els = {};
const el = id => els[id] || (els[id] = { id, onclick: null, onchange: null, value: '', disabled: false, innerHTML: '' });
const calls = [];
const downloads = [];
const excel = [];
let modalHtml = '';
const ctx = {
    $: id => (modalHtml.includes('id="' + id + '"') || id === 'hemisTodayBtn' ? el(id) : null),
    hsEsc: v => String(v == null ? '' : v).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch])),
    hemisSyncModal: html => { modalHtml = html; return { remove() { modalHtml = ''; } }; },
    HS_CLOSE: '<button>Yopish</button>', hsErrText: e => 'Xatolik: ' + e.message,
    scopeBody: () => ({ faculty_names: ['F1'], specialty_names: ['stomatologiya'], courses: [1] }), scopeLabelText: () => 'F1 · stomatologiya · 1-kurs',
    BASE: '/admin', board: { id: 7 }, JSON, Object, Set, String,
    api: async (url, method, body) => { calls.push([method, url, body]); return rep; },
    sendExcelExport: async (data, title, base) => { excel.push({ data, title, base }); },
    Blob: class { constructor(parts) { downloads.push(JSON.parse(parts[0])); } },
    URL: { createObjectURL: () => 'blob:x' },
    document: { createElement: () => ({ click() {}, remove() {} }), body: { appendChild() {} } },
};
vm.createContext(ctx);
vm.runInContext(section('            // ===== "Bugungi darslar (HEMIS)"', "            // ===== HEMIS to'siqlari bo'yicha so'rovlar"), ctx);

const row = o => Object.assign({ group: 'p/st26-01a', type: 'Amaliy', changes: [], blocked: [], reason: null }, o);
const rep = {
    today: '2026-09-14', missing_groups: [], import_failed: 0,
    rows: [
        row({ subject: 'Biologiya', hemis_id: 2, start: '08:30', end: '09:50', new_start: '08:30', new_end: '09:50', employee_name: 'E6006', new_employee_name: 'USTOZ',
            auditorium_name: '71', new_auditorium_name: '71', status: 'locked', reason: 'baho qo\'yilgan — tegilmaydi', changes: ["o'qituvchi: E6006 → USTOZ"] }),
        row({ subject: 'Odam anatomiyasi', hemis_id: 1, start: '10:00', end: '11:20', new_start: '10:00', new_end: '11:20', employee_name: 'E6006', new_employee_name: 'USTOZ',
            auditorium_name: '71', new_auditorium_name: '71', status: 'locked', reason: 'davomat olingan — tegilmaydi' }),
        row({ subject: 'Kimyo', hemis_id: 3, start: '12:00', end: '13:20', new_start: '13:30', new_end: '14:50', employee_name: 'E5001', new_employee_name: 'E5001',
            auditorium_name: '73', new_auditorium_name: '74', status: 'update', changes: ['vaqt: 12:00–13:20 → 13:30–14:50', 'xona: 73 → 74'] }),
        row({ subject: 'Fizika', hemis_id: null, start: '13:30', end: '14:50', employee_name: 'USTOZ', auditorium_name: '75', status: 'info', reason: 'yangi dars kerak — bugun qo\'shilmaydi' }),
        row({ group: 'p/st26-01b', subject: 'Tarix', hemis_id: 9, start: '10:00', end: '11:20', new_start: '10:00', new_end: '11:20', employee_name: 'E1', new_employee_name: 'E2',
            auditorium_name: '80', new_auditorium_name: '80', status: 'blocked', blocked: ['o\'qituvchi band: E2 — x · Fan 10:00–11:20'] }),
    ],
    json: { mode: 'today', today: '2026-09-14', lock_check_url: '/hemis-today-locks/7?s=1', hemis_pairs: {}, groups: [
        { group: 'p/st26-01a', group_hemis_id: 1596, units: [{ subject_id: 476, actions: [{ op: 'update', today: true, hemis_id: 3 }, { op: 'create', today: true }, { op: 'update', hemis_id: 99 }] }] },
        { group: 'p/st26-01b', group_hemis_id: 1597, units: [{ subject_id: 1, actions: [{ op: 'update', today: true, hemis_id: 10 }] }] },
    ] },
};

(async () => {
    await el('hemisTodayBtn').onclick.call(el('hemisTodayBtn'));
    assert.deepEqual(JSON.parse(JSON.stringify(calls)), [['POST', '/admin/boards/7/hemis-today', { faculty_names: ['F1'], specialty_names: ['stomatologiya'], courses: [1] }]]);
    assert.match(modalHtml, /Bugun \(14\.09\.2026\): 5 ta dars · 1 ta o'zgartiriladi · 2 ta davomat\/baho bor — tegilmaydi · 1 ta to'siqli/);
    // Tegilmaydigan darslar kulrang, sababi bilan; o'zgarish eski → yangi; to'siq matni.
    const tr = s => (modalHtml.match(new RegExp('<tr class="align-top[^"]*" data-status="' + s + '">[\\s\\S]*?</tr>', 'g')) || []);
    assert.equal(tr('locked').length, 2);
    tr('locked').forEach(t => assert.match(t, /^<tr class="align-top bg-gray-100 text-gray-400"/));
    assert.match(tr('locked')[0], /Tegilmaydi<\/span><div>baho qo&#39;yilgan|Tegilmaydi<\/span><div>baho qo'yilgan — tegilmaydi/);
    assert.match(tr('update')[0], /12:00–13:20 → <b>13:30–14:50<\/b>/);
    assert.match(tr('update')[0], /73 → <b>74<\/b>/);
    assert.match(tr('info')[0], /yangi dars kerak — bugun qo'shilmaydi/);
    assert.match(tr('blocked')[0], /To'siqli<\/span><div>o'qituvchi band: E2/);

    // JSON: faqat bugungi o'zgartirishlar.
    el('htJson').onclick();
    assert.deepEqual(downloads[0].groups.map(g => g.units[0].actions.map(a => a.hemis_id)), [[3], [10]]);
    assert.equal(downloads[0].mode, 'today');
    assert.equal(downloads[0].lock_check_url, '/hemis-today-locks/7?s=1');

    // Guruh filtri: sarlavha, JSON va Excel — faqat shu guruh.
    el('htGroup').onchange({ target: { value: 'p/st26-01b' } });
    assert.match(modalHtml, /Bugun \(14\.09\.2026\): 1 ta dars · 0 ta o'zgartiriladi · 0 ta davomat\/baho bor — tegilmaydi · 1 ta to'siqli/);
    assert.match(modalHtml, /<option value="p\/st26-01b" selected>/);
    assert.match(modalHtml, /id="htJson" class="tt-ktr-weeks-btn" disabled>/);
    el('htJson').onclick();
    assert.deepEqual(downloads[1].groups.map(g => g.group_hemis_id), [1597]);
    await el('htExcel').onclick.call(el('htExcel'));
    assert.equal(excel[0].data.rows.length, 2);
    assert.equal(excel[0].data.rows[1][0].t, 'p/st26-01b');
    el('htGroup').onchange({ target: { value: '' } });
    await el('htExcel').onclick.call(el('htExcel'));
    const kimyo = JSON.parse(JSON.stringify(excel[1].data.rows.find(r => r[1].t === 'Kimyo').map(c => c.t)));
    assert.deepEqual(kimyo, ['p/st26-01a', 'Kimyo', 'Amaliy', '3', '12:00–13:20', '13:30–14:50', 'E5001', 'E5001', '73', '74', 'O\'zgartiriladi',
        'vaqt: 12:00–13:20 → 13:30–14:50; xona: 73 → 74']);
    assert.equal(excel[1].data.rows.find(r => r[1].t === 'Biologiya')[0].fc, '#9ca3af');
    assert.match(excel[1].title, /^Bugungi darslar \(HEMIS\) · 14\.09\.2026 · /);
    console.log('HEMIS bugungi darslar oynasi: hisoblar, kulrang qatorlar, guruh filtri, JSON va Excel');
})().catch(e => { console.error(e); process.exit(1); });
