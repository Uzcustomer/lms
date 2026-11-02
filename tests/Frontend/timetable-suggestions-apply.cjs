const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Takliflar qatori: qo'llash mumkin — katakcha va faol tugma; mumkin emas — o'chiq tugma va sababi; faqat ko'rish — tugma yo'q.
const a = source.indexOf('            function sugApplyLabel(mode)');
const b = source.indexOf("            if ($('suggestWeekBtn')) $('suggestWeekBtn').onclick = openWeekSuggestions;", a);
assert(a > 0 && b > a);
// Soxta DOM: sabab oynasi (div), uning matn maydoni va hujjat darajasidagi klaviatura tinglovchilari.
const listeners = [];
const doc = {
    nodes: [],
    createElement: tag => {
        const n = { tag, id: '', attrs: {}, style: {}, innerHTML: '', removed: false, handlers: {},
            setAttribute(k, v) { this.attrs[k] = v; }, addEventListener(type, fn) { this.handlers[type] = fn; }, remove() { this.removed = true; },
            querySelector(sel) { return sel === '#ttReasonText' ? (this.ta = this.ta || { value: '', focused: false, focus() { this.focused = true; } }) : null; } };
        return n;
    },
    getElementById: id => doc.nodes.find(n => n.id === id && !n.removed) || null,
    body: { appendChild: n => doc.nodes.push(n) },
    addEventListener: (type, fn, cap) => listeners.push([type, fn, cap]),
    removeEventListener: (type, fn, cap) => { const i = listeners.findIndex(l => l[0] === type && l[1] === fn && l[2] === cap); if (i >= 0) listeners.splice(i, 1); },
};
const ctx = { esc: s => String(s), SUG_COLOR: {}, SUG_LABEL: { slot: "Bo'sh vaqt", blocker: 'Darsni surish' }, document: doc, String, Promise,
    prompt: () => { throw new Error("native prompt() ishlatilmasin"); } };
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nthis.sugRowHtml = sugRowHtml; this.sugFooterHtml = sugFooterHtml; this.askSugReason = askSugReason; this.askRejectReason = askRejectReason;', ctx);

const PENDING = Symbol('pending');
// Hal bo'lmagan promise'da Node jim chiqib ketadi — oxirigacha yetganini alohida tekshiramiz.
let finished = false;
process.on('exit', code => { if (!finished && code === 0) { console.error('test oxirigacha yetmadi (kutilgan promise hal bo\'lmadi)'); process.exitCode = 1; } });
const state = p => Promise.race([p, new Promise(r => setImmediate(() => r(PENDING)))]);
const open = () => { const p = ctx.askSugReason(); return [p, doc.getElementById('ttReasonModal')]; };
const click = (m, kind) => m.handlers.click({ target: kind ? { closest: sel => (sel === '[data-reason]' ? { dataset: { reason: kind } } : null) } : m });
const key = o => {
    const ev = Object.assign({ prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } }, o);
    listeners.filter(l => l[0] === 'keydown').forEach(l => l[1](ev));
    return ev;
};

(async () => {
    // "So'rov yuborish" — sabab sahifa ichidagi oynada (native prompt emas): matn maydoni, Yuborish / Bekor.
    let [p, m] = open();
    assert(m, 'oyna ochilmadi');
    assert.equal(m.attrs.role, 'dialog');
    assert.equal(m.attrs['aria-modal'], 'true');
    assert.match(m.innerHTML, /<textarea id="ttReasonText" rows="4" maxlength="1000"/);
    assert.match(m.innerHTML, /data-reason="cancel"[^>]*>Bekor<\/button>/);
    assert.match(m.innerHTML, /data-reason="ok"[^>]*>Yuborish<\/button>/);
    assert.match(m.innerHTML, /Kafedra mudiri va registrator ofisi ko'radi/);
    assert.equal(m.ta.focused, true);
    assert.deepEqual(listeners.map(l => [l[0], l[2]]), [['keydown', true]]);
    assert.equal(await state(p), PENDING);
    // Bekor — null; oyna yopiladi, tinglovchi olib tashlanadi.
    click(m, 'cancel');
    assert.equal(await p, null);
    assert.equal(m.removed, true);
    assert.equal(listeners.length, 0);
    // Bo'sh (faqat bo'sh joy) — null; kiritilgani — tozalangan holda.
    [p, m] = open();
    m.ta.value = '   ';
    click(m, 'ok');
    assert.equal(await p, null);
    [p, m] = open();
    m.ta.value = '  Ertalab amaliyot  ';
    click(m, 'ok');
    assert.equal(await p, 'Ertalab amaliyot');
    assert.equal(m.removed, true);
    // Orqa fonga bosish yopmaydi (ikki marta bosish bekor qilib yubormasin); Esc — bekor, ostidagi oynaga o'tmaydi.
    [p, m] = open();
    m.ta.value = 'yarim';
    click(m, null);
    assert.equal(await state(p), PENDING);
    assert.equal(m.removed, false);
    const esc = key({ key: 'Escape' });
    assert.equal(await p, null);
    assert.equal(esc.stopped, true);
    assert.equal(listeners.length, 0);
    // Oddiy Enter — yangi qator (yubormaydi); Ctrl+Enter / Cmd+Enter — yuboradi.
    [p, m] = open();
    m.ta.value = 'Sabab';
    assert.equal(key({ key: 'Enter' }).prevented, false);
    assert.equal(await state(p), PENDING);
    key({ key: 'Enter', ctrlKey: true });
    assert.equal(await p, 'Sabab');
    [p, m] = open();
    m.ta.value = 'Mac';
    key({ key: 'Enter', metaKey: true });
    assert.equal(await p, 'Mac');
    // Qayta ochilsa — avvalgisi bekor (kutayotgan handler osilib qolmaydi), yangisi ishlaydi.
    const [p1, m1] = open();
    const [p2, m2] = open();
    assert.equal(await state(p1), null);
    assert.equal(m1.removed, true);
    assert.notEqual(m1, m2);
    m2.ta.value = 'ikkinchi';
    click(m2, 'ok');
    assert.equal(await p2, 'ikkinchi');
    assert.equal(listeners.length, 0);
    // Handlerlar oynani kutadi; bekor yoki bo'sh bo'lsa — avvalgidek oyna ichida xabar, so'rov yuborilmaydi.
    assert.match(source, /Object\.assign\(\{ week: res\.week, card_id: c\.id, type: sug\.type, title: sug\.title, actions: sug\.actions \}, sug\.mode === 'direct' \? \{\} : \{ reason \}\)\);/);
    assert.match(source, /const reason = sug\.mode === 'direct' \? '' : await askSugReason\(\);\n\s+if \(reason === null\) \{ sugNote\(body, "So'rov yuborilmadi: sabab kiritilmadi\.", 'err'\); return; \}/);
    assert.match(source, /const reason = list\.some\(x => x\.sug\.mode !== 'direct'\) \? await askSugReason\(\) : '';\n\s+if \(reason === null\) \{ sugNote\(body, "So'rov yuborilmadi: sabab kiritilmadi\.", 'err'\); return; \}/);
    assert.doesNotMatch(source.slice(source.indexOf('function askReasonDialog('), source.indexOf('function sugRowHtml(')), /prompt\(/);
    assert.doesNotMatch(source, /(^|[^.\w]|window\.)prompt\(\s*['"`]/m, "sahifada native prompt() chaqiruvi qolmasin");

    // "Jadval o'zgarishlari" — rad etish sababi: o'sha oyna, o'z sarlavhasi va qizil "Rad etish" tugmasi; bo'sh yoki bekor — null.
    let [r, rm] = [ctx.askRejectReason(), doc.getElementById('ttReasonModal')];
    assert.match(rm.innerHTML, /<b id="ttReasonTitle"[^>]*>Rad etish sababi<\/b>/);
    assert.match(rm.innerHTML, /Taklif yuboruvchiga boradi\./);
    assert.match(rm.innerHTML, /data-reason="ok" style="[^"]*background:#dc2626;color:#fff">Rad etish<\/button>/);
    assert.match(rm.innerHTML, /placeholder="Masalan: xona shu vaqtda band"/);
    rm.ta.value = '  ';
    click(rm, 'ok');
    assert.equal(await r, null);
    [r, rm] = [ctx.askRejectReason(), doc.getElementById('ttReasonModal')];
    rm.ta.value = ' Xona band ';
    click(rm, 'ok');
    assert.equal(await r, 'Xona band');
    assert.equal(listeners.length, 0);
    // Oyna "Jadval o'zgarishlari" (#crModal) va Takliflar oynasi ustida, xabarlar (#ttToastBox) ostida — aks holda ko'rinmay qolardi.
    [r, rm] = [ctx.askSugReason(), doc.getElementById('ttReasonModal')];
    const z = +rm.style.cssText.match(/z-index:(\d+)/)[1];
    const cssZ = id => +source.match(new RegExp('#' + id + ' \\{[^}]*z-index: (\\d+)'))[1];
    assert(z > cssZ('crModal'), 'z-index ' + z + ' <= #crModal ' + cssZ('crModal'));
    assert(z < cssZ('ttToastBox'), 'z-index ' + z + ' >= #ttToastBox');
    assert(z > +source.match(/box\.id = 'ttSugModal';\n\s+box\.style\.cssText = '[^']*z-index:(\d+)/)[1]);
    click(rm, 'cancel');
    assert.equal(await r, null);

const ok = ctx.sugRowHtml({ type: 'slot', title: 'Seshanba III', can_apply: true, mode: 'department' }, 0, 1, 'request');
assert.match(ok, /data-sug-pick="0:1"/);
assert.match(ok, /data-sug="0:1" style=/);
assert.match(ok, />So'rov yuborish<\/button>/);
const no = ctx.sugRowHtml({ type: 'blocker', title: 'Surish', can_apply: false, mode: null, why: "Boshqa o'qituvchining darsini surish kerak" }, 0, 2, 'request');
assert.doesNotMatch(no, /data-sug-pick/);
assert.match(no, /data-sug="0:2" disabled title="Boshqa o'qituvchining darsini surish kerak"/);
assert.match(no, /class="sug-why"[^>]*>Boshqa o'qituvchining/);
const view = ctx.sugRowHtml({ type: 'slot', title: 'x', can_apply: false, why: "O'quv bo'limi takliflarni faqat ko'radi." }, 0, 0, null);
assert.doesNotMatch(view, /data-sug|sug-why/);
assert.match(ctx.sugRowHtml({ type: 'slot', title: 'x', can_apply: true, mode: 'direct' }, 1, 0, 'direct'), />Qo'llash<\/button>/);
assert.match(ctx.sugFooterHtml('request'), /data-sug-apply-all disabled[^>]*>Tanlanganlarni qo'llash/);
assert.match(ctx.sugFooterHtml(null), /Faqat ko'rish/);
finished = true;
console.log('timetable suggestions apply: 17 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
