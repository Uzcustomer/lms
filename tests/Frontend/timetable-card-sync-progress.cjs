const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
assert.match(source, /id="cardSyncProgress"/);
assert.match(source, /tt-card-sync-slide/);

const start = source.indexOf('let cardSyncTimer = null;');
const end = source.indexOf("$('genBtn').title", start);
assert(start > 0 && end > start);

const nodes = {};
const node = id => nodes[id] || (nodes[id] = {
    textContent: '',
    hidden: true,
    classList: {
        add: name => { if (name === 'hidden') nodes[id].hidden = true; },
        remove: name => { if (name === 'hidden') nodes[id].hidden = false; },
    },
});
let intervalCallback = null;
let cleared = false;
const ctx = {
    $: node,
    setInterval: callback => { intervalCallback = callback; return 91; },
    clearInterval: id => { assert.equal(id, 91); cleared = true; },
};
vm.createContext(ctx);
vm.runInContext(source.slice(start, end), ctx);

ctx.startCardSyncProgress(false);
assert.equal(node('cardSyncProgress').hidden, false);
assert.match(node('cardSyncProgressText').textContent, /sinxronlanmoqda/);
assert.equal(node('cardSyncProgressElapsed').textContent, '0 soniya');
intervalCallback();
assert.equal(node('cardSyncProgressElapsed').textContent, '1 soniya');
ctx.setCardSyncProgressText('Doska qayta yuklanmoqda…');
assert.equal(node('cardSyncProgressText').textContent, 'Doska qayta yuklanmoqda…');
ctx.stopCardSyncProgress();
assert.equal(cleared, true);
assert.equal(node('cardSyncProgress').hidden, true);

console.log('Card synchronization progress is visible, timed and cleared: passed');
