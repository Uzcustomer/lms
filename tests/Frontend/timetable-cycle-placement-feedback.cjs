const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const start = source.indexOf('function showCyclePlacementResult(');
const end = source.indexOf('async function placeCycleCardAt(', start);
assert(start > 0 && end > start, 'cycle placement feedback helper must exist');

const classes = new Set(['text-gray-500']);
const message = {
    textContent: '',
    title: '',
    classList: {
        add: (...names) => names.forEach(name => classes.add(name)),
        remove: (...names) => names.forEach(name => classes.delete(name)),
    },
    setAttribute: (name, value) => { message[name] = value; },
};
const toasts = [];
const ctx = {
    cyclePlanData: { dates: [{ iso: '2026-09-30', d: '30.09' }] },
    $: id => id === 'cycleMsg' ? message : null,
    esc: value => String(value),
    ttToast: (html, type) => toasts.push({ html, type }),
};
vm.createContext(ctx);
vm.runInContext(source.slice(start, end), ctx);

ctx.showCyclePlacementResult({ subject: 'Вакцинопрофилактика' }, 0, 'Bu sanada boshqa sikl bor.');
assert.match(message.textContent, /Joylanmadi: Вакцинопрофилактика · 2026-09-30/);
assert.match(message.textContent, /Bu sanada boshqa sikl bor/);
assert.ok(classes.has('text-red-700'));
assert.equal(toasts.length, 1);
assert.equal(toasts[0].type, 'error');

ctx.showCyclePlacementResult({ subject: 'Вакцинопрофилактика' }, 0, '', 'success');
assert.match(message.textContent, /Joylandi: Вакцинопрофилактика · 2026-09-30/);
assert.ok(classes.has('text-emerald-700'));
assert.ok(!classes.has('text-red-700'));
assert.equal(toasts.length, 1, 'success does not show an error toast');

ctx.showCyclePlacementResult({ subject: 'Вакцинопрофилактика' }, 0, '', 'pending');
assert.match(message.textContent, /Tasdiqlashga yuborildi: Вакцинопрофилактика · 2026-09-30/);
assert.ok(classes.has('text-amber-700'));
assert.ok(!classes.has('text-emerald-700'));
assert.equal(toasts.length, 1, 'pending approval uses the existing approval notice');

const placeSource = source.slice(source.indexOf('async function placeCycleCardAt('),
    source.indexOf('// Tanlangan karta', source.indexOf('async function placeCycleCardAt(')));
assert.match(placeSource, /result && result\.pending_approval/);
assert.match(placeSource, /!reloaded \|\| !reloaded\.placed/);

const eventsStart = source.indexOf('function setupCycleGridEvents()');
const eventsEnd = source.indexOf('function renderCycleCards(', eventsStart);
const eventSource = source.slice(eventsStart, eventsEnd);
assert.match(eventSource, /await placeCycleCardAt\(card, index, \+cell\.dataset\.cyclePair \|\| 1\)/);
assert.doesNotMatch(eventSource, /alert\('Sikl blokini joylab/);

console.log('Cycle placement failures remain visible with subject, date and server reason: passed');
