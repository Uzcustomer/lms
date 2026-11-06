const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Kartochka (cmSave): xona boshqa o'qituvchiga biriktirilgan — server 409 needs_confirm; xato emas, "Baribir biriktirish"
// (showBusyConfirm) cmMsg da; bosilsa — o'sha rekvizitlar force=1 bilan qayta yuboriladi. Boshqa xato — avvalgidek qizil matn.
const a = source.indexOf("            $('cmSave').onclick = () => cmSaveRun(false);");
const b = source.indexOf("            $('cmUnplace').onclick", a);
assert(a > 0 && b > a);

const cls = () => { const s = new Set(['hidden']); return { add: x => s.add(x), remove: x => s.delete(x), contains: x => s.has(x) }; };
const els = {};
const el = id => (els[id] = els[id] || { id, value: '', innerHTML: '', textContent: '', className: '', disabled: false, classList: cls(),
    selectedOptions: [{ dataset: {} }] });
const calls = [];
const shown = [];
let renders = 0;
const ctx = {
    $: el, BASE: '/admin', modalCard: null, selected: null, modalUnit: null,
    api: (url, method, body) => new Promise((res, rej) => calls.push({ url, method, body, res, rej })),
    modalStartPlace: () => null, placeUnitAt: async () => {}, placeOneCard: async () => {}, conflictsAt: () => [],
    renderAll: () => { renders++; },
    showBusyConfirm: (target, msg, onForce, items) => shown.push({ target: target.id, msg, onForce, items }),
};
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nthis.cmSaveRun = cmSaveRun; this.setCard = c => { modalCard = c; };'
    + '\nthis.card = () => modalCard;', ctx);
const tick = () => new Promise(r => setImmediate(r));

(async () => {
    const card = { id: 42, teacher_id: 7, auditorium_code: 'R103', day: null, pair: null };
    ctx.setCard(card);
    el('cmTeacher').value = '7';
    el('cmAud').value = 'R102';
    el('cmLen').value = '2';
    el('cmStartHalf').value = '0';

    // 1) Birinchi yuborish — force yo'q; 409 needs_confirm → ogohlantirish cmMsg da (sariq), oyna ochiq, tugma yana faol.
    el('cmSave').onclick();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/admin/cards/42/update');
    assert.equal(calls[0].body.force, undefined);
    assert.equal(calls[0].body.auditorium_code, 'R102');
    assert.equal(el('cmSave').disabled, true);
    const msg = "Xona «102» boshqa o'qituvchiga biriktirilgan: USTOZ B (dars o'qituvchisi — USTOZ A)";
    calls[0].rej(Object.assign(new Error(msg), { data: { needs_confirm: true, room_owner: true, conflict_items: [] } }));
    await tick(); await tick();
    assert.deepEqual(shown.map(x => [x.target, x.msg]), [['cmMsg', msg]]);
    assert.equal(el('cmMsg').className, 'text-sm rounded px-3 py-2 bg-amber-50');
    assert.equal(el('cmSave').disabled, false);
    assert.equal(renders, 0);
    assert.equal(ctx.card(), card);

    // 2) "Baribir biriktirish" — o'sha rekvizitlar force=1 bilan; muvaffaqiyat — oyna yopiladi, xona yangilanadi.
    shown[0].onForce();
    assert.equal(calls.length, 2);
    assert.equal(calls[1].body.force, 1);
    assert.equal(calls[1].body.auditorium_code, 'R102');
    assert.equal(calls[1].body.teacher_id, '7');
    calls[1].res({ teacher_name: 'USTOZ A', auditorium_code: 'R102', auditorium_name: '102', len_half: 2 });
    await tick(); await tick();
    assert.equal(card.auditorium_code, 'R102');
    assert.equal(el('cardModal').classList.contains('hidden'), true);
    assert.equal(ctx.card(), null);
    assert.equal(renders, 1);

    // 2b) Ogohlantirishdan keyin xona yoki o'qituvchi o'zgartirilsa — eski "Baribir" yangi tanlovni force bilan yubormaydi:
    // force'siz qayta tekshiriladi (yangi xonaning o'z ogohlantirishi chiqadi).
    for (const change of [() => { el('cmAud').value = 'R101'; }, () => { el('cmTeacher').value = '8'; }]) {
        ctx.setCard({ id: 44, teacher_id: 7 });
        el('cmTeacher').value = '7';
        el('cmAud').value = 'R102';
        shown.length = 0;
        el('cmSave').onclick();
        calls.at(-1).rej(Object.assign(new Error(msg), { data: { needs_confirm: true, room_owner: true, conflict_items: [] } }));
        await tick(); await tick();
        assert.equal(shown.length, 1);
        change();
        shown[0].onForce();
        assert.equal(calls.at(-1).body.force, undefined);
        calls.at(-1).rej(Object.assign(new Error('boshqa xona'), { data: { needs_confirm: true, conflict_items: [] } }));
        await tick(); await tick();
        assert.deepEqual(shown.map(x => x.msg), [msg, 'boshqa xona']);
        // Yangi ogohlantirish — yangi tanlov uchun: o'zgarishsiz bosilsa — force=1.
        shown[1].onForce();
        assert.equal(calls.at(-1).body.force, 1);
        calls.at(-1).res({ teacher_name: 'X', auditorium_code: el('cmAud').value, auditorium_name: 'x', len_half: 2 });
        await tick(); await tick();
    }

    // 3) force bilan ham rad etilsa (yoki oddiy xato) — qizil matn, qayta ogohlantirish yo'q.
    ctx.setCard({ id: 43, teacher_id: 7 });
    shown.length = 0;
    const p = ctx.cmSaveRun(true);
    calls.at(-1).rej(Object.assign(new Error('Konflikt'), { data: { needs_confirm: true } }));
    await p;
    assert.equal(shown.length, 0);
    assert.equal(el('cmMsg').className, 'text-sm rounded px-3 py-2 bg-red-50 text-red-700');
    assert.equal(el('cmMsg').textContent, 'Konflikt');
    const p2 = ctx.cmSaveRun(false);
    calls.at(-1).rej(Object.assign(new Error('Auditoriya topilmadi'), { data: {} }));
    await p2;
    assert.equal(shown.length, 0);
    assert.equal(el('cmMsg').textContent, 'Auditoriya topilmadi');

    console.log('timetable card room owner: 4 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
