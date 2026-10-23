#!/usr/bin/env node
/**
 * HEMIS dars jadvaliga yozish (registrator ofisi).
 *
 * Kirish: LMS "HEMIS solishtirish" oynasidan yuklangan JSON (hemis_amallar_YYYY-MM-DD.json).
 * Har bir amal (qo'shish / o'zgartirish / o'chirish) HEMIS'ning o'z formasi orqali bajariladi —
 * ochiq brauzerda (Playwright, Chromium), siz HEMIS'ga O'ZINGIZ kirasiz (parol skriptda yo'q).
 *
 * Ishlatish:
 *   node hemis-write.mjs --json hemis_amallar_2026-10-23.json                 # faqat ko'rish (hech narsa yozilmaydi)
 *   node hemis-write.mjs --json ... --check                                    # HEMIS formalarini tekshirish (yozmaydi)
 *   node hemis-write.mjs --json ... --until-week 7 --apply                     # 7-hafta oxirigacha yozish
 *   node hemis-write.mjs --json ... --until 2026-10-31 --group p/st26-01a --apply
 *
 * Parametrlar:
 *   --json FAYL        LMS'dan olingan JSON (majburiy)
 *   --until-week N     faqat N-hafta oxirigacha (JSON'dagi haftalar ro'yxati bo'yicha)
 *   --current-week     faqat joriy hafta oxirigacha
 *   --until SANA       faqat shu sanagacha (YYYY-MM-DD)
 *   --group NOM        faqat shu guruh(lar) (vergul bilan)
 *   --only create,update,delete   faqat shu amallar
 *   --check            HEMIS'ga kirib formalarni tekshiradi (para, xona, o'qituvchi, fan bormi) — yozmaydi
 *   --apply            HAQIQATAN yozadi (har guruhdan oldin tasdiq so'raladi; --yes bilan so'ramaydi)
 *   --profile PAPKA    brauzer profili (HEMIS sessiyasi saqlanadi), sukut: ./.hemis-profile
 *   --base URL         HEMIS manzili, sukut: https://hemis.ttatf.uz
 *
 * Natija: hemis-write-log-<vaqt>.json — har amal: ok / xato / o'tkazib yuborildi va sababi.
 * Yozib bo'lgach LMS'da HEMIS jadval importini yangilang va "HEMIS solishtirish"ni qayta bosing.
 */
import fs from 'node:fs';
import readline from 'node:readline/promises';

const args = process.argv.slice(2);
const opt = (name, def = null) => {
    const i = args.indexOf('--' + name);
    if (i < 0) return def;
    const v = args[i + 1];
    return v === undefined || v.startsWith('--') ? true : v;
};
const has = name => args.includes('--' + name);

const jsonPath = opt('json');
if (!jsonPath || jsonPath === true) {
    console.error('JSON fayl ko\'rsatilmagan: --json hemis_amallar_YYYY-MM-DD.json');
    process.exit(1);
}
const rep = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const BASE = String(opt('base', 'https://hemis.ttatf.uz')).replace(/\/$/, '');
const OP_NAME = { create: 'Qo\'shish', update: 'O\'zgartirish', delete: 'O\'chirish' };

// --- Chegaralar
let until = opt('until');
if (has('current-week')) {
    const w = (rep.weeks || []).find(x => x.week === rep.current_week);
    until = w ? w.to : null;
}
if (opt('until-week')) {
    const n = Number(opt('until-week'));
    const w = (rep.weeks || []).find(x => x.week === n);
    if (!w) {
        console.error(n + '-hafta JSON\'dagi haftalar ro\'yxatida yo\'q.');
        process.exit(1);
    }
    until = w.to;
}
const groupsOnly = opt('group') ? String(opt('group')).split(',').map(s => s.trim().toLowerCase()) : null;
const opsOnly = opt('only') ? String(opt('only')).split(',').map(s => s.trim()) : null;
// Bugun — JSON'dagi sana va shu kompyuterdagi sanadan kattasi: eski JSON yoki admin sinov sanasi bilan
// olingan JSON ham bugungi va o'tgan kunlarga yozdirmaydi.
const today = [String(rep.today || ''), new Date().toLocaleDateString('en-CA')].sort().pop();

// --- Amallar ro'yxati
const jobs = [];
const skipped = [];
for (const g of rep.groups || []) {
    if (groupsOnly && !groupsOnly.includes(String(g.group).toLowerCase())) continue;
    for (const u of g.units || []) {
        for (const a of u.actions || []) {
            const base = { group: g.group, subject: u.subject_name, type: (u.code_names || {})[a.code] || a.code, ...a };
            if (a.date <= today) { skipped.push({ ...base, reason: 'bugun yoki o\'tgan kun' }); continue; }
            if (until && a.date > until) { skipped.push({ ...base, reason: 'chegaradan keyin (' + until + ')' }); continue; }
            if (opsOnly && !opsOnly.includes(a.op)) { skipped.push({ ...base, reason: 'amal turi tanlanmagan' }); continue; }
            if (a.blocked && a.blocked.length) { skipped.push({ ...base, reason: 'to\'siq: ' + a.blocked.join('; ') }); continue; }
            const pair = (rep.hemis_pairs || {})[a.start + '-' + a.end];
            if (a.op !== 'delete' && !pair) { skipped.push({ ...base, reason: 'HEMIS para topilmadi: ' + a.start + '-' + a.end }); continue; }
            jobs.push({
                ...base, pair,
                curriculum: g.curriculum_hemis_id, education_year: g.education_year, semester: g.semester_code,
                group_id: g.group_hemis_id, subject_id: u.subject_id,
            });
        }
    }
}
// Guruh bo'yicha (tasdiq har guruhga bir marta); guruh ichida avval o'chirish, so'ng o'zgartirish, so'ng qo'shish —
// bo'shaydigan vaqtga yangi dars eskisi olib tashlangandan keyin qo'yiladi.
const OP_ORDER = { delete: 0, update: 1, create: 2 };
jobs.sort((x, y) => String(x.group).localeCompare(String(y.group)) || x.group_id - y.group_id
    || OP_ORDER[x.op] - OP_ORDER[y.op] || (x.date + x.start).localeCompare(y.date + y.start));

const line = j => `${OP_NAME[j.op].padEnd(12)} ${j.group.padEnd(12)} ${j.date} ${j.start}-${j.end}  ${j.subject.slice(0, 34).padEnd(34)} ${String(j.type).padEnd(12)} ${j.employee_name || '—'} · ${j.auditorium_name || '—'}${j.hemis_id ? ' · #' + j.hemis_id : ''}${j.changes && j.changes.length ? '  (' + j.changes.join('; ') + ')' : ''}`;

console.log(`JSON: ${jsonPath} · bugun ${today} · chegara: ${until || 'semestr oxirigacha'}${groupsOnly ? ' · guruh: ' + groupsOnly.join(',') : ''}`);
const cnt = { create: 0, update: 0, delete: 0 };
jobs.forEach(j => cnt[j.op]++);
console.log(`Bajariladi: ${jobs.length} ta (+${cnt.create} qo'shish, ~${cnt.update} o'zgartirish, −${cnt.delete} o'chirish); o'tkazib yuboriladi: ${skipped.length} ta\n`);
jobs.forEach(j => console.log('  ' + line(j)));
const blockedCount = skipped.filter(s => s.reason.startsWith('to\'siq')).length;
if (blockedCount) console.log(`\n${blockedCount} ta amal to'siqli (masalan xona/o'qituvchi topilmadi) — LMS'da tuzatib, JSON'ni qayta yuklang.`);

if (!has('apply') && !has('check')) {
    console.log('\nBu faqat ko\'rish. Tekshirish: --check, yozish: --apply');
    process.exit(0);
}

// --- Brauzer
let chromium;
try {
    ({ chromium } = await import('playwright'));
} catch {
    console.error('Playwright o\'rnatilmagan: shu papkada "npm install" va "npx playwright install chromium" bajaring.');
    process.exit(1);
}
const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
const ctx = await chromium.launchPersistentContext(String(opt('profile', './.hemis-profile')), { headless: false, viewport: { width: 1280, height: 860 } });
const page = ctx.pages()[0] || await ctx.newPage();
await page.goto(BASE + '/curriculum/schedule-info');
const loggedIn = async () => !!(await page.$('meta[name="csrf-param"]')) && !/login/i.test(page.url());
if (!(await loggedIn())) {
    await rl.question('\nBrauzerda HEMIS\'ga kiring (login, parol, captcha — o\'zingiz), so\'ng shu yerda Enter bosing... ');
    await page.goto(BASE + '/curriculum/schedule-info');
    if (!(await loggedIn())) {
        console.error('HEMIS\'ga kirilmagan.');
        await ctx.close();
        process.exit(1);
    }
}

/**
 * Sahifa ichida bajariladi: HEMIS formasini olib, maydonlarni to'ldiradi va (apply bo'lsa) yuboradi.
 * Qaytaradi: { ok, error?, problems?[] }.
 */
const runInPage = async ({ job, apply }) => {
    const q = String.fromCharCode(63);
    const csrfParam = document.querySelector('meta[name="csrf-param"]').content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const parse = html => new DOMParser().parseFromString(html, 'text/html');
    const errorsOf = doc => [...doc.querySelectorAll('.has-error .help-block, .help-block-error, .alert-danger, .error-summary li')]
        .map(e => e.textContent.trim()).filter(Boolean);

    if (job.op === 'delete') {
        const editUrl = '/curriculum/schedule-edit' + q + 'id=' + job.hemis_id;
        const er = await fetch(editUrl);
        if (er.status === 404) return { ok: true, note: 'HEMIS\'da allaqachon yo\'q' };
        if (!apply) return { ok: true, note: 'tekshirildi' };
        const fd = new FormData();
        fd.append(csrfParam, csrfToken);
        const r = await fetch(editUrl + '&delete=1', { method: 'POST', body: fd });
        const doc = parse(await r.text());
        const errs = errorsOf(doc);
        return errs.length ? { ok: false, error: errs.join('; ') } : { ok: r.ok, error: r.ok ? null : 'HTTP ' + r.status };
    }

    const url = job.op === 'create'
        ? '/curriculum/schedule-create' + q + 'date=' + job.date + '&curriculum=' + job.curriculum + '&education_year=' + job.education_year
            + '&semester=' + job.semester + '&group=' + job.group_id
        : '/curriculum/schedule-edit' + q + 'id=' + job.hemis_id;
    const gr = await fetch(url);
    if (gr.status === 404) return { ok: false, error: 'HEMIS yozuvi topilmadi (#' + job.hemis_id + ')' };
    const doc = parse(await gr.text());
    const form = [...doc.querySelectorAll('form')].find(f => f.querySelector('[name^="ESubjectSchedule"]'));
    if (!form) return { ok: false, error: 'HEMIS formasi topilmadi (sessiya tugaganmi?)' };

    const problems = [];
    const setSelect = (name, value, label, mustExist = true) => {
        // Tahrirlash formasida o'qituvchi/guruh bitta qiymatli ("[_employees]"), yaratishda ko'p qiymatli ("[_employees][]").
        const el = form.querySelector('select[name="' + name + '"]') || form.querySelector('select[name="' + name.replace(/\[\]$/, '') + '"]');
        if (!el) { problems.push(label + ' maydoni yo\'q'); return; }
        const values = Array.isArray(value) ? value.map(String) : [String(value)];
        [...el.options].forEach(o => { o.selected = false; o.removeAttribute('selected'); });
        for (const v of values) {
            let o = [...el.options].find(x => x.value === v);
            if (!o) {
                if (mustExist) { problems.push(label + ' ro\'yxatida yo\'q: ' + v); continue; }
                o = doc.createElement('option');
                o.value = v;
                el.appendChild(o);
            }
            o.selected = true;
            o.setAttribute('selected', 'selected');
        }
    };
    setSelect('ESubjectSchedule[_lesson_pair]', job.pair, 'Para');
    setSelect('ESubjectSchedule[_auditorium]', job.auditorium_code, 'Xona');
    setSelect('ESubjectSchedule[_subject]', job.subject_id, 'Fan');
    // Dars turi ro'yxati fan tanlangach (AJAX) to'ladi — qiymat qo'lda qo'shiladi.
    setSelect('ESubjectSchedule[_training_type]', job.code, 'Dars turi', false);
    setSelect('ESubjectSchedule[_employees][]', [job.employee_id], 'O\'qituvchi');
    setSelect('ESubjectSchedule[groups][]', [job.group_id], 'Guruh');
    if (problems.length) return { ok: false, error: problems.join('; '), problems };
    if (!apply) return { ok: true, note: 'forma to\'g\'ri' };

    const fd = new FormData(form);
    fd.set(csrfParam, csrfToken);
    const action = form.getAttribute('action') || url;
    const r = await fetch(action, { method: 'POST', body: fd });
    const html = await r.text();
    const after = parse(html);
    const errs = errorsOf(after);
    if (errs.length) return { ok: false, error: errs.join('; ') };
    // Muvaffaqiyatda HEMIS jadval sahifasiga qaytaradi; forma qayta chiqsa — saqlanmagan.
    const stillForm = /schedule-(create|edit)/.test(r.url) && after.querySelector('form [name^="ESubjectSchedule"]');
    return stillForm ? { ok: false, error: 'saqlanmadi (forma qayta qaytdi)' } : { ok: true };
};

const apply = has('apply');
const results = [];
let lastGroup = null;
let stop = false;
for (const j of jobs) {
    if (apply && !has('yes') && j.group_id !== lastGroup) {
        const n = jobs.filter(x => x.group_id === j.group_id).length;
        const ans = (await rl.question(`\n${j.group}: ${n} ta amal HEMIS'ga yoziladi. Davom etamizmi? [ha / o'tkaz / to'xtat] `)).trim().toLowerCase();
        if (ans.startsWith('t')) { stop = true; }
        if (ans.startsWith('o')) { jobs.filter(x => x.group_id === j.group_id).forEach(x => x._skip = true); }
    }
    lastGroup = j.group_id;
    if (stop) { results.push({ ...j, status: 'skipped', reason: 'to\'xtatildi' }); continue; }
    if (j._skip) { results.push({ ...j, status: 'skipped', reason: 'foydalanuvchi o\'tkazdi' }); continue; }
    const res = await page.evaluate(runInPage, { job: j, apply }).catch(e => ({ ok: false, error: String(e.message || e) }));
    results.push({ ...j, status: res.ok ? 'ok' : 'error', note: res.note || null, error: res.error || null });
    console.log((res.ok ? '  ✓ ' : '  ✗ ') + line(j) + (res.ok ? (res.note ? '  — ' + res.note : '') : '  — ' + res.error));
    await page.waitForTimeout(apply ? 400 : 100);
}

const ok = results.filter(r => r.status === 'ok').length;
const err = results.filter(r => r.status === 'error').length;
const logFile = 'hemis-write-log-' + new Date().toISOString().replace(/[:.]/g, '-') + '.json';
fs.writeFileSync(logFile, JSON.stringify({ json: jsonPath, until, apply, results, skipped }, null, 1));
console.log(`\n${apply ? 'Yozildi' : 'Tekshirildi'}: ${ok} ta muvaffaqiyatli, ${err} ta xato. Jurnal: ${logFile}`);
if (apply) console.log('Endi LMS\'da HEMIS jadval importini yangilang va "HEMIS solishtirish"ni qayta bosing.');
rl.close();
await ctx.close();
