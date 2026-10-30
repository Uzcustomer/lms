const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const a = source.indexOf('            async function openWeekSuggestions() {');
const b = source.indexOf("            if ($('suggestWeekBtn'))", a);
assert(a > 0 && b > a);
process.exitCode = 1;

// "Takliflar": vaqt byudjeti tugasa server qisqartirilgan (partial) kartalar qaytaradi — oynada sariq yozuv, kartada
// "To'liq takliflar" tugmasi faqat shu karta uchun (card_id) to'liq takliflarni olib, kartani almashtiradi.
const calls = [];
let answers = [];
const cache = {};   // bir xil chizilgan HTML uchun bir xil tugma obyektlari (ishlov beruvchilar ularga qo'yiladi)
const buttons = sel => {
    const attr = sel.slice(1, -1);   // [data-full] → data-full
    const key = attr + '\0' + ctx.body.innerHTML;
    return cache[key] || (cache[key] = [...ctx.body.innerHTML.matchAll(new RegExp('<button type="button" ' + attr + '="([^"]+)"', 'g'))]
        .map(m => ({ dataset: { full: m[1], sug: m[1] }, disabled: false, textContent: '' })));
};
const ctx = {
    body: { innerHTML: '', querySelectorAll: buttons },
    title: { textContent: '' },
    board: { id: 7 }, BASE: '/admin', curWeek: 7, weeklyLayer: 'internal', typeFilter: 'all',
    selectedFaculties: new Set(['F1']), selectedDirs: new Set(), selectedCourses: new Set([1]),
    SUG_COLOR: {}, SUG_LABEL: {}, esc: s => String(s), URLSearchParams, Set, Promise, Error,
    window: {}, alert: m => { throw new Error('alert: ' + m); },
    document: {
        getElementById: () => ({ querySelector: sel => (sel === '#ttSugBody' ? ctx.body : ctx.title), addEventListener() {} }),
    },
    api: async url => {
        calls.push(url.replace('/admin/boards/7', ''));
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

    process.exitCode = 0;
    console.log('timetable week suggestions partial: 2 scenarios passed');
})().catch(e => { console.error(e); process.exitCode = 1; });
