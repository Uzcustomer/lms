const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../tools/hemis-sync/hemis-write.mjs'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a);
    return source.slice(a, b + to.length);
}

// Playwright yozuvchisi: ochiq readline birinchi Ctrl+C ni o'zi ushlaydi (jarayon to'xtamaydi, ikkinchisi amal o'rtasida
// o'ldiradi va jurnal yozilmaydi). Shuning uchun Ctrl+C ham readline'ga, ham jarayonga ulanadi: birinchisi — yumshoq
// to'xtatish (qolgan amallar o'tkaziladi, jurnal saqlanadi), ikkinchisi — darhol chiqish.
const exits = [], logs = [];
const ctx = { process: { exit: c => exits.push(c) }, console: { log: m => logs.push(m) } };
vm.createContext(ctx);
vm.runInContext('let stop = false;' + section('const ctrlC = () => {', '};') + ' this.ctrlC = ctrlC; this.isStopped = () => stop;', ctx);

ctx.ctrlC();
assert.equal(ctx.isStopped(), true);
assert.deepEqual(exits, []);
assert.match(logs[0], /joriy amal tugagach qolganlari yozilmaydi, jurnal saqlanadi/);
ctx.ctrlC();
assert.deepEqual(exits, [130]);

// Ikkala manbaga ulangan va yozish siklidan oldin; sikl har amaldan oldin stop'ni tekshiradi.
const wired = source.indexOf("rl.on('SIGINT', ctrlC);"), loop = source.indexOf('for (const j of jobs) {');
assert(wired > 0 && source.includes("process.on('SIGINT', ctrlC);") && wired < loop);
assert.match(source.slice(loop, loop + 400), /if \(stop\) \{ results\.push\(\{ \.\.\.j, status: 'skipped'/);
console.log('2 HEMIS writer (Playwright) Ctrl+C scenarios passed');
