const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const a = source.indexOf('            function sugNote(body, html, kind) {');
const b = source.indexOf("            if ($('suggestWeekBtn'))", a);
assert(a > 0 && b > a);
process.exitCode = 1;

// "Takliflar": vaqt byudjeti tugasa server qisqartirilgan (partial) kartalar qaytaradi — oynada sariq yozuv, kartada
// "To'liq takliflar" tugmasi faqat shu karta uchun (card_id) to'liq takliflarni olib, kartani almashtiradi.
const calls = [];
const posts = [];   // POST so'rovlar tanasi (propose / apply)
let answers = [];
let checked = [];   // "Tanlanganlarni qo'llash" uchun belgilangan katakchalar ('ci:si')
const allBtns = {}; // chizilgan HTML uchun bitta "Tanlanganlarni qo'llash" tugmasi
let reasonModal = null;   // sabab oynasi (askSugReason)
const cache = {};   // bir xil chizilgan HTML uchun bir xil tugma obyektlari (ishlov beruvchilar ularga qo'yiladi)
const buttons = sel => {
    const attr = sel.slice(1, -1);   // [data-full] → data-full
    const key = attr + '\0' + ctx.body.innerHTML;
    return cache[key] || (cache[key] = [...ctx.body.innerHTML.matchAll(new RegExp('<button type="button" ' + attr + '="([^"]+)"', 'g'))]
        .map(m => ({ dataset: { full: m[1], sug: m[1] }, disabled: false, textContent: '' })));
};
const ctx = {
    body: { innerHTML: '', prepend() {},
        querySelectorAll: sel => (sel === '[data-sug-pick]:checked' ? checked.map(v => ({ dataset: { sugPick: v } })) : buttons(sel)),
        querySelector: sel => (sel === '[data-sug-apply-all]' && ctx.body.innerHTML.includes('data-sug-apply-all')
            ? (allBtns[ctx.body.innerHTML] = allBtns[ctx.body.innerHTML] || { disabled: true, textContent: '' }) : null) },
    title: { textContent: '' },
    board: { id: 7 }, BASE: '/admin', curWeek: 7, weeklyLayer: 'internal', typeFilter: 'all',
    selectedFaculties: new Set(['F1']), selectedDirs: new Set(), selectedCourses: new Set([1]),
    SUG_COLOR: {}, SUG_LABEL: {}, esc: s => String(s), focusLinksHtml: () => "", agendaLinksHtml: () => "", URLSearchParams, Set, Promise, Error,
    window: {}, alert: m => { throw new Error('alert: ' + m); }, String,
    ttToast() {}, loadChangeRequests: async () => {}, loadBoard: async () => {}, confirm: () => true,
    document: {
        getElementById: id => (id === 'ttReasonModal' ? (reasonModal && !reasonModal.removed ? reasonModal : null)
            : { querySelector: sel => (sel === '#ttSugBody' ? ctx.body : ctx.title), addEventListener() {} }),
        createElement: () => ({ style: {}, attrs: {}, handlers: {}, removed: false, setAttribute(k, v) { this.attrs[k] = v; },
            addEventListener(t, fn) { this.handlers[t] = fn; }, remove() { this.removed = true; },
            querySelector(sel) { return sel === '#ttReasonText' ? (this.ta = this.ta || { value: '', focus() {} }) : null; } }),
        body: { appendChild: n => { if (n.id === 'ttReasonModal') reasonModal = n; } },
        addEventListener() {}, removeEventListener() {},
    },
    api: async (url, method, body) => {
        calls.push(url.replace('/admin/boards/7', ''));
        if (method === 'POST') posts.push({ url: url.replace('/admin/boards/7', ''), body: JSON.parse(JSON.stringify(body)) });
        return answers.shift();
    },
};
vm.createContext(ctx);
vm.runInContext(source.slice(a, b), ctx);
const sug = (title, type) => ({ type, title, note: '', actions: [] });
const card = (id, partial, sugs) => ({ id, subject: 'S' + id, type: 'practice', groups: ['g' + id], teacher: 'T', room: null, reason: '', partial, suggestions: sugs });

(async () => {
    answers.push({ week: 7, total: 2, shown: 2, partial: true, message: "1 ta karta uchun to'liq takliflar hisoblanmadi",
        cards: [card(1, false, [sug('Bo\'sh: Dushanba, 1-para', 'slot'), sug('O\'qituvchini almashtirish: X', 'teacher')]), card(2, true, [sug('Bo\'sh: Seshanba, 1-para', 'slot')])] });
    await ctx.openWeekSuggestions();
    assert.equal(calls[0], '/week-suggestions?faculty_names%5B%5D=F1&courses%5B%5D=1&type=all&week=7');
    assert.match(ctx.body.innerHTML, /<div id="ttSugPartial"[^>]*>⚠ 1 ta karta uchun to'liq takliflar hisoblanmadi — kartadagi «To'liq takliflar» tugmasini bosing\.<\/div>/);
    // Tugma — faqat qisqartirilgan (2-) kartada
    const full = ctx.body.querySelectorAll('[data-full]');
    assert.deepEqual(full.map(x => x.dataset.full), ['1']);

    // Bosilganda: shu karta uchun card_id bilan so'rov, karta almashadi, sariq yozuv yo'qoladi
    answers.push({ week: 7, total: 1, shown: 1, partial: false, message: null,
        cards: [card(2, false, [sug('Bo\'sh: Seshanba, 1-para', 'slot'), sug('Boshqa kun: Juma, 1-para', 'day')])] });
    await full[0].onclick();
    assert.equal(calls[1], '/week-suggestions?faculty_names%5B%5D=F1&courses%5B%5D=1&type=all&week=7&card_id=2');
    assert.equal(ctx.window.__ttSug[1].partial, false);
    assert.deepEqual(ctx.window.__ttSug[1].suggestions.map(s => s.type), ['slot', 'day']);
    assert(!ctx.body.innerHTML.includes('ttSugPartial'), 'sariq yozuv qoldi');
    assert.deepEqual(ctx.body.querySelectorAll('[data-full]'), []);
    assert.match(ctx.body.innerHTML, /Boshqa kun: Juma, 1-para/);

    // To'qnashuvli karta: sarlavhada alohida son, kartada belgi; ikkalasi ham 0 bo'lsa — "yo'q" xabari.
    answers.push({ week: 7, total: 2, unplaced_total: 1, conflict_total: 1, shown: 2, partial: false, message: null,
        cards: [card(3, false, [sug('Bo\'sh: Dushanba, 1-para', 'slot')]),
            Object.assign(card(4, false, [sug('Xonani almashtirish: 216', 'room')]), { conflict: true, reason: 'Xona 215 band: p/p25-02b · Odam anatomiyasi · Juma V para' })] });
    await ctx.openWeekSuggestions();
    assert.equal(ctx.title.textContent, "Takliflar — 7-hafta · Joylanmagan: 1 · To'qnashuvli: 1");
    assert.equal((ctx.body.innerHTML.match(/class="sug-conflict"/g) || []).length, 1);
    assert.match(ctx.body.innerHTML, /Xona 215 band: p\/p25-02b · Odam anatomiyasi · Juma V para/);
    answers.push({ week: 7, total: 0, unplaced_total: 0, conflict_total: 0, shown: 0, partial: false, message: null, cards: [] });
    await ctx.openWeekSuggestions();
    assert.equal(ctx.title.textContent, "Takliflar — 7-hafta · Joylanmagan: 0 · To'qnashuvli: 0");
    assert.match(ctx.body.innerHTML, /Bu haftada joylanmagan yoki to'qnashuvli karta yo'q\./);

    // Sabab oynasi ochiq turganda "To'liq takliflar" javobi kartani almashtirsa (yangi takliflar oldinga qo'shiladi) — yuboriladigan
    // taklif bosilgan paytdagisi bo'lib qoladi (yakka "So'rov yuborish" ham, "Tanlanganlarni qo'llash" ham).
    const tick = () => new Promise(r => setImmediate(r));
    const S = (title, type) => ({ type, title, note: '', can_apply: true, mode: 'department', actions: [{ op: 'move', card_id: 5, title }] });
    const submitReason = text => {
        assert(reasonModal && !reasonModal.removed, 'sabab oynasi ochilmadi');
        reasonModal.ta.value = text;
        reasonModal.handlers.click({ target: { closest: () => ({ dataset: { reason: 'ok' } }) } });
    };
    for (const bulk of [false, true]) {
        posts.length = 0;
        answers.push({ week: 7, total: 1, shown: 1, partial: true, apply_mode: 'request', message: null,
            cards: [card(5, true, [S("Ma'ruzani surish: Seshanba II", 'slot')])] });
        await ctx.openWeekSuggestions();
        const fullBtn = ctx.body.querySelectorAll('[data-full]')[0];
        let releaseFull;
        answers.push(new Promise(r => { releaseFull = r; }));
        const fullDone = fullBtn.onclick();   // to'liq takliflar so'rovi — hali javob yo'q
        let sent;
        if (bulk) {
            checked = ['0:0'];
            sent = ctx.body.querySelector('[data-sug-apply-all]').onclick();
        } else {
            sent = ctx.body.querySelectorAll('[data-sug]')[0].onclick();
        }
        await tick();
        // Foydalanuvchi sabab yozayotganda to'liq javob keladi: oldinga boshqa taklif qo'shiladi.
        releaseFull({ cards: [card(5, false, [S('Darsni surish: Fizika', 'blocker'), S("Ma'ruzani surish: Seshanba II", 'slot')])] });
        await fullDone;
        assert.equal(ctx.window.__ttSug[0].suggestions[0].title, 'Darsni surish: Fizika');
        answers.push({ message: "So'rov yuborildi." });
        answers.push({ week: 7, total: 0, unplaced_total: 0, conflict_total: 0, shown: 0, partial: false, message: null, cards: [] });
        submitReason('  Ertalab amaliyot  ');
        await sent;
        assert.equal(posts.length, 1, 'bulk=' + bulk);
        assert.equal(posts[0].url, '/week-suggestions/propose');
        assert.deepEqual(posts[0].body, { week: 7, card_id: 5, type: 'slot', title: "Ma'ruzani surish: Seshanba II",
            actions: [{ op: 'move', card_id: 5, title: "Ma'ruzani surish: Seshanba II" }], reason: 'Ertalab amaliyot' }, 'bulk=' + bulk);
        checked = [];
    }

    process.exitCode = 0;
    console.log('timetable week suggestions partial: 5 scenarios passed');
})().catch(e => { console.error(e); process.exitCode = 1; });
