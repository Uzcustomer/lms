const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// "Jadval o'zgarishlari" oynasi: "Rad etish" — sabab sahifa ichidagi oynada (native prompt() emas). Bo'sh yoki "Bekor" — rad etilmaydi,
// so'rov serverga ketmaydi; kiritilgan sabab (tozalangan) — POST /change-requests/{id}/reject { note }.
const slice = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const code = slice('            function renderChangeReqModal() {', "            if ($('changeReqBtn'))")
    + slice('            function askReasonDialog(', '            function sugRowHtml(');

// Soxta DOM: #crModal tanasi (tugmalar innerHTML'dan), sabab oynasi va hujjat darajasidagi klaviatura tinglovchilari.
const listeners = [];
let reasonModal = null;
const cache = {};
const crBody = {
    innerHTML: '',
    querySelectorAll(sel) {
        const attr = sel.slice(1, -1);
        const key = attr + '\0' + this.innerHTML;
        return cache[key] || (cache[key] = [...this.innerHTML.matchAll(new RegExp('<button type="button"[^>]* ' + attr + '="([^"]+)"', 'g'))]
            .map(m => ({ dataset: { crReject: m[1], crApprove: m[1], crCancel: m[1], crTab: m[1] }, disabled: false })));
    },
    querySelector: () => null,
};
const crModal = { querySelector: sel => (sel === '.cr-body' ? crBody : null) };
const calls = [], toasts = [];
let reloads = 0;
const ctx = {
    esc: s => String(s), String, Promise, Boolean,
    prompt: () => { throw new Error('native prompt() ishlatilmasin'); },
    alert: m => { throw new Error('alert: ' + m); },
    confirm: () => true,
    BASE: '/admin', board: { id: 7 }, viewMode: 'week', TIMETABLE_ROLE: 'registrator_ofisi', TIMETABLE_DEPARTMENT_HEAD: false,
    changeReqFilter: 'pending', changeHistory: { rows: [], more: false, loading: false },
    changeReqs: { can_approve: true, approval_required: true },
    pendingReqs: () => [{ id: 12, status: 'pending', can_review: true, summary: 'Fizika: Dushanba I → Seshanba II', created_at: '02.11 09:00' }],
    crStageText: () => 'Kutilmoqda', CR_STATUS: {}, crNoticeHtml: () => '',
    loadChangeRequests: async () => { reloads++; }, loadChangeHistory: async () => {}, loadCyclePlan: async () => {}, loadBoard: async () => {},
    ttToast: (html, tone) => toasts.push([html, tone]),
    api: async (url, method, body) => { calls.push([url, method, JSON.parse(JSON.stringify(body))]); return { ok: true }; },
    document: {
        getElementById: id => (id === 'crModal' ? crModal : id === 'ttReasonModal' ? (reasonModal && !reasonModal.removed ? reasonModal : null) : null),
        createElement: () => ({ style: {}, attrs: {}, handlers: {}, removed: false, setAttribute(k, v) { this.attrs[k] = v; },
            addEventListener(t, fn) { this.handlers[t] = fn; }, remove() { this.removed = true; },
            querySelector(sel) { return sel === '#ttReasonText' ? (this.ta = this.ta || { value: '', focused: false, focus() { this.focused = true; } }) : null; } }),
        body: { appendChild: n => { reasonModal = n; } },
        addEventListener: (t, fn, cap) => listeners.push([t, fn, cap]),
        removeEventListener: (t, fn, cap) => { const i = listeners.findIndex(l => l[0] === t && l[1] === fn && l[2] === cap); if (i >= 0) listeners.splice(i, 1); },
    },
};
vm.createContext(ctx);
vm.runInContext(code + '\nthis.renderChangeReqModal = renderChangeReqModal;', ctx);

const tick = () => new Promise(r => setImmediate(r));
const PENDING = Symbol('pending');
const state = p => Promise.race([p, new Promise(r => setImmediate(() => r(PENDING)))]);
const press = (kind, text) => {
    assert(reasonModal && !reasonModal.removed, 'sabab oynasi ochilmadi');
    if (text !== undefined) reasonModal.ta.value = text;
    reasonModal.handlers.click({ target: { closest: sel => (sel === '[data-reason]' ? { dataset: { reason: kind } } : null) } });
};
let finished = false;
process.on('exit', c => { if (!finished && c === 0) { console.error("test oxirigacha yetmadi"); process.exitCode = 1; } });

(async () => {
    ctx.renderChangeReqModal();
    let btn = crBody.querySelectorAll('[data-cr-reject]')[0];
    assert(btn, '"Rad etish" tugmasi yo\'q');
    assert.equal(btn.dataset.crReject, '12');

    // Bosilganda — sabab oynasi: "Rad etish sababi", qizil "Rad etish" tugmasi; server hali chaqirilmaydi.
    let p = btn.onclick();
    await tick();
    assert.match(reasonModal.innerHTML, /<b id="ttReasonTitle"[^>]*>Rad etish sababi<\/b>/);
    assert.match(reasonModal.innerHTML, /Taklif yuboruvchiga boradi\./);
    assert.match(reasonModal.innerHTML, /data-reason="ok" style="[^"]*background:#dc2626;color:#fff">Rad etish<\/button>/);
    assert.equal(reasonModal.ta.focused, true);
    assert.equal(await state(p), PENDING);
    assert.equal(calls.length, 0);

    // "Bekor" — rad etilmaydi (so'rov yo'q), xabar; tugma faol qoladi.
    press('cancel');
    await p;
    assert.equal(calls.length, 0);
    assert.deepEqual(toasts.pop(), ['Rad etilmadi: sabab kiritilmadi.', 'warn']);
    assert.equal(btn.disabled, false);
    assert.equal(listeners.length, 0);

    // Bo'sh (faqat bo'sh joy) — ham rad etilmaydi.
    p = btn.onclick();
    await tick();
    press('ok', '   ');
    await p;
    assert.equal(calls.length, 0);
    assert.deepEqual(toasts.pop(), ['Rad etilmadi: sabab kiritilmadi.', 'warn']);

    // Esc — bekor (ostidagi "Jadval o'zgarishlari" oynasiga o'tmaydi).
    p = btn.onclick();
    await tick();
    const ev = { key: 'Escape', stopped: false, preventDefault() {}, stopPropagation() { this.stopped = true; } };
    listeners.filter(l => l[0] === 'keydown').forEach(l => l[1](ev));
    await p;
    assert.equal(ev.stopped, true);
    assert.equal(calls.length, 0);
    toasts.pop();

    // Sabab kiritildi — rad etiladi: { note } tozalangan. Oyna ochiq turganda ro'yxat qayta chizilsa ham — o'sha so'rov (id 12).
    p = btn.onclick();
    await tick();
    ctx.pendingReqs = () => [{ id: 15, status: 'pending', can_review: true, summary: 'Kimyo', created_at: '02.11 09:05' },
        { id: 12, status: 'pending', can_review: true, summary: 'Fizika', created_at: '02.11 09:00' }];
    ctx.renderChangeReqModal();
    press('ok', '  Xona shu vaqtda band  ');
    await p;
    await tick();
    assert.deepEqual(calls, [['/admin/change-requests/12/reject', 'POST', { note: 'Xona shu vaqtda band' }]]);
    assert.equal(btn.disabled, true);
    assert.deepEqual(toasts.pop(), ['Rad etildi.', 'ok']);
    assert(reloads >= 1, "ro'yxat yangilanmadi");
    assert.equal(listeners.length, 0);

    // Manbada native prompt() chaqiruvi qolmagan (rad etish handleri sabab oynasini kutadi).
    assert.match(source, /const id = b\.dataset\.crReject;\n\s+const note = await askRejectReason\(\);\n\s+if \(note === null\) \{ ttToast\('Rad etilmadi: sabab kiritilmadi\.', 'warn'\); return; \}/);

    finished = true;
    console.log('timetable change reject: 6 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
