const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../tools/hemis-sync/hemis-write.mjs'), 'utf8');

// Playwright yozuvchisi: ochiq readline birinchi Ctrl+C ni o'zi ushlaydi (jarayon to'xtamaydi, ikkinchisi amal o'rtasida
// o'ldiradi va jurnal yozilmaydi). Shuning uchun Ctrl+C ham readline'ga, ham jarayonga ulanadi: birinchisi — yumshoq
// to'xtatish (kutilayotgan savol bekor bo'ladi, qolgan amallar o'tkaziladi, jurnal saqlanadi), ikkinchisi — darhol chiqish.
const a = source.indexOf('let stop = false;'), c = source.indexOf('const ctrlC = () => {'), b = source.indexOf('};', c) + 2;
assert(a >= 0 && c > a && b > c);
const exits = [], logs = [], asked = [];
const rl = {
    // node:readline/promises kabi: signal bekor qilinsa — AbortError bilan rad etiladi.
    question: (q, { signal }) => new Promise((resolve, reject) => {
        asked.push(q);
        const abort = () => reject(Object.assign(new Error('The operation was aborted'), { name: 'AbortError' }));
        if (signal.aborted) abort(); else signal.addEventListener('abort', abort);
    }),
};
const ctx = { rl, AbortController, process: { exit: code => exits.push(code) }, console: { log: m => logs.push(m) } };
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + ' this.ctrlC = ctrlC; this.question = question; this.isStopped = () => stop;', ctx);

// Savol bekor qilinmasa, kutish hech qachon tugamaydi va Node jim chiqadi — buni ham xato deb hisoblaymiz.
let finished = false;
process.on('exit', () => { if (!finished) { console.error('Test oxirigacha yetmadi: kutilayotgan savol bekor qilinmadi'); process.exitCode = 1; } });
(async () => {
    // 1) Kirish savoli kutilayotganda Ctrl+C — savol bekor (null), skript odatdagi yakunga o'tadi; chiqish yo'q.
    const login = ctx.question('Kirish... ');
    ctx.ctrlC();
    assert.equal(await login, null);
    assert.equal(ctx.isStopped(), true);
    assert.deepEqual(exits, []);
    assert.match(logs[0], /joriy amal tugagach qolganlari yozilmaydi, jurnal saqlanadi/);
    // Keyingi savol (tasdiq) ham kutib qolmaydi.
    assert.equal(await ctx.question('Boshlaymizmi? '), null);
    // 2) Ikkinchi Ctrl+C — darhol chiqish.
    ctx.ctrlC();
    assert.deepEqual(exits, [130]);

    // 3) Ikkala manbaga ulangan, brauzer ochilishidan (kirish bosqichi) va yozish siklidan oldin; Playwright'ning o'z SIGINT
    // ishlovchisi o'chirilgan (aks holda birinchi signalda brauzerni yopib chiqib ketadi); savollar shu bekor qilinadigan
    // yordamchi orqali; sikl har amaldan oldin stop'ni tekshiradi.
    const wired = source.indexOf("rl.on('SIGINT', ctrlC);"), loop = source.indexOf('for (const j of jobs) {');
    const launch = source.indexOf('chromium.launchPersistentContext(');
    assert(wired > 0 && source.includes("process.on('SIGINT', ctrlC);") && wired < launch && launch < loop);
    assert.match(source.slice(launch, source.indexOf(');', launch)), /handleSIGINT: false/);
    assert(!source.includes('await rl.question('), 'savollar question() orqali — Ctrl+C ularni bekor qila oladi');
    assert.match(source.slice(loop, loop + 400), /if \(stop\) \{ results\.push\(\{ \.\.\.j, status: 'skipped'/);
    finished = true;
    console.log('3 HEMIS writer (Playwright) Ctrl+C scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
