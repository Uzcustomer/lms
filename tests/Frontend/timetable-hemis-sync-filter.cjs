const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Ochiladigan ro'yxatning yopilishi haqiqiy brauzer hodisalarida tekshiriladi — Playwright va Chromium kerak.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// HEMIS solishtirish oynasi (filtrlar, JSON, to'siq so'rovlari) — blade'dan o'zgarishsiz olinadi.
const a = source.indexOf("            // ===== Registrator: HEMIS bilan solishtirish");
const b = source.indexOf('            async function hsTicketsSend(rep) {', a);
assert(a > 0 && b > a);
const code = source.slice(a, b);
const stubs = "const $ = id => document.getElementById(id); const BASE = ''; const CSRF = 't'; const HS_WRITER_URL = 'w.js';"
  + " let board = { id: 1 }; const scopeLabelText = () => 'Qamrov'; const scopeBody = () => ({}); const api = async () => ({});";

const act = (card, extra = {}) => Object.assign({ op: 'create', date: '2026-11-02', start: '08:30', end: '09:50', code: '11',
  employee_name: 'O\'qituvchi', auditorium_name: '101', card_id: card, week: 9, blocked: [], blocks: [] }, extra);
const blockedAct = card => act(card, { blocked: ['Xona band'],
  blocks: [{ type: 'room', text: 'Xona band', room: '101', time: '08:30', source: 'HEMIS', group: 'x', subject: 'y' }] });
const unit = (subject, actions) => ({ subject_name: subject, kafedra: 'K', actions, actions_later: 0, notes: [], fixes: [],
  code_names: { 11: 'Amaliy' }, codes: { 11: 'A' }, period: null,
  before: { hours_diff: { 11: 2 }, count_diff: {} }, after: { hours_diff: {}, count_diff: {} } });
const group = (name, units) => ({ group: name, imported_at: '2026-10-29', until: null, period_from: 1, period_to: 9,
  units, skipped: [], cards_without_subject: [] });
const rep = {
  today: '2026-10-29', period_from: 1, period_to: 9, until_week: 9, current_week: 9, weeks: [], fallback_room: { code: 'M' },
  groups_not_in_hemis: [],
  groups: [
    group('pp/21-01 (rus)', [unit('Gigiyena', [act(1), blockedAct(2)]), unit('Anatomiya', [act(3)])]),
    group('pp/21-02 (rus)', [unit('Gigiyena', [act(4), blockedAct(5)])]),
  ],
};

(async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.setContent('<!doctype html><html><body><div id="board"></div></body></html>');
    await page.addScriptTag({ content: stubs });
    await page.addScriptTag({ content: code });
    await page.evaluate(r => {
      window.__rep = r;
      // JSON yuklab olish: fayl o'rniga Blob ushlab qolinadi.
      URL.createObjectURL = blob => { window.__blob = blob; return 'blob:test'; };
      HTMLAnchorElement.prototype.click = function () { };
      hemisSyncRender(r);
    }, rep);
    const isOpen = id => page.evaluate(i => document.getElementById(i).open, id);
    const jsonOut = async () => {
      await page.click('#hsJson');
      return JSON.parse(await page.evaluate(() => window.__blob.text()));
    };
    const groupHeads = () => page.$$eval('#hemisSyncModal h4', hs => hs.map(h => h.textContent.split(' · ')[0].trim()));

    // Filtrsiz: ikkala guruh, JSON'da ikkalasining amallari.
    assert.deepEqual(await groupHeads(), ['pp/21-01 (rus)', 'pp/21-02 (rus)']);
    assert.equal((await jsonOut()).groups.length, 2);

    // Guruh filtri: faqat pp/21-01 — ro'yxat checkbox bosilgandan keyin ham ochiq qoladi.
    await page.click('#hsGroupBox summary');
    await page.click('#hemisSyncModal .hs-pick-Group[data-i="1"]');
    assert.equal(await isOpen('hsGroupBox'), true, 'checkbox bosilgach guruhlar ro\'yxati yopilib qoldi');
    assert.match(await page.textContent('#hsGroupBox summary'), /Guruhlar: 1 \/ 2/);
    assert.deepEqual(await groupHeads(), ['pp/21-01 (rus)']);
    // "Ko'rsatilgan amallar" — faqat tanlangan guruhniki (2 ta qo'shish, 1 ta to'siqli).
    assert.match(await page.textContent('#hemisSyncModal'), /Ko'rsatilgan amallar: \+2 qo'shish.*1 ta to'siqli/);
    let out = await jsonOut();
    assert.deepEqual(out.groups.map(g => g.group), ['pp/21-01 (rus)'], 'JSON boshqa guruh amallarini ham o\'z ichiga oldi');
    assert.deepEqual(out.groups[0].units.map(u => u.actions.map(x => x.card_id)), [[1, 2], [3]]);
    // To'siqlar bo'yicha xabar — faqat tanlangan guruhning to'sig'i.
    assert.deepEqual(await page.evaluate(() => hsTicketItems(window.__rep).map(t => t.card_id)), [2]);

    // Fan filtri bilan birga (ikkala shart): pp/21-01 + Gigiyena.
    await page.click('#hsSubjBox summary');
    assert.equal(await isOpen('hsGroupBox'), false, 'boshqa ro\'yxat ochilganda guruhlar ro\'yxati yopilmadi');
    await page.click('#hemisSyncModal .hs-pick-Subj[data-i="0"]');   // Anatomiya (alifbo bo'yicha birinchi) — olib tashlanadi
    assert.equal(await isOpen('hsSubjBox'), true);
    out = await jsonOut();
    assert.deepEqual(out.groups.map(g => [g.group, g.units.map(u => [u.subject_name, u.actions.map(x => x.card_id)])]),
      [['pp/21-01 (rus)', [['Gigiyena', [1, 2]], ['Anatomiya', []]]]]);

    // Tashqariga bosish — ro'yxat yopiladi; Esc — ham.
    await page.click('#hsSubjBox summary');
    assert.equal(await isOpen('hsSubjBox'), true);
    await page.click('#hemisSyncModal h3');
    assert.equal(await isOpen('hsSubjBox'), false, 'tashqariga bosilganda ro\'yxat yopilmadi');
    await page.click('#hsGroupBox summary');
    await page.keyboard.press('Escape');
    assert.equal(await isOpen('hsGroupBox'), false, 'Esc bosilganda ro\'yxat yopilmadi');

    // Ro'yxat ichidagi "Hammasi" (oynani qayta chizadi) — ro'yxat yopilmaydi.
    await page.click('#hsGroupBox summary');
    await page.click('#hsGroupAll');
    assert.equal(await isOpen('hsGroupBox'), true, '"Hammasi" bosilgach ro\'yxat yopilib qoldi');
    assert.match(await page.textContent('#hsGroupBox summary'), /Guruhlar: hammasi/);
    assert.deepEqual((await jsonOut()).groups.map(g => g.group), ['pp/21-01 (rus)', 'pp/21-02 (rus)']);

    console.log('timetable hemis sync filter: 6 scenarios passed');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
