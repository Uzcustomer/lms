const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a);
    return source.slice(a, b);
}

// "To'siqlar bo'yicha xabar": so'rovlar ro'yxati (obyektlar massivi) serverga Laravel tushunadigan
// items[i][maydon] kalitlari bilan boradi — "[object Object]" emas.
const sent = [];
const ctx = {
    CSRF: 'tok', BASE: '/admin/dars-jadvali-tuzish', board: { id: 7 }, FormData, URLSearchParams,
    confirm: () => true, alerts: [], onPendingApproval() {}, ttToast() {}, esc: s => s, hsErrText: e => String(e && e.message),
    fetch: async (url, opt) => {
        sent.push({ url, method: opt.method, form: [...opt.body.entries()] });
        return { ok: true, status: 200, json: async () => ({ ok: true, created: 1, skipped: 0, telegram_sent: 2, without_telegram: [] }) };
    },
    hsTicketItems: () => [
        { card_id: 5, week: 3, date: '2099-10-28', start: '08:30', end: '09:50', type: 'room', group: 'g-01a', text: 'xona band: …',
            blocker: { source: 'HEMIS', group: 'x-02', subject: 'Fiziologiya', teacher: 'B.', room: '101', time: '08:30–09:50' } },
        { card_id: 6, week: 3, date: '2099-10-29', start: '10:00', end: '11:20', type: 'other', group: 'g-01b', text: 'para yo\'q', blocker: null },
    ],
};
ctx.alert = m => ctx.alerts.push(m);
vm.createContext(ctx);
vm.runInContext(section('async function api(url', '// ===== Jadval o\'zgarishlari') + section('async function hsTicketsSend(rep)', 'async function hsTicketsList()')
    + '; this.hsTicketsSend = hsTicketsSend;', ctx);

ctx.hsTicketsSend({}).then(() => {
    assert.equal(sent.length, 1);
    assert.equal(sent[0].url, '/admin/dars-jadvali-tuzish/boards/7/block-tickets');
    const form = Object.fromEntries(sent[0].form);
    assert(!sent[0].form.some(([, v]) => String(v) === '[object Object]'), 'obyekt matnga aylanib ketmasin');
    assert.equal(form['items[0][card_id]'], '5');
    assert.equal(form['items[0][type]'], 'room');
    assert.equal(form['items[0][blocker][room]'], '101');
    assert.equal(form['items[1][card_id]'], '6');
    assert.equal(form['items[1][blocker]'], undefined);
    assert.match(ctx.alerts.at(-1), /Yaratildi: 1 ta/);
    console.log('1 HEMIS block-ticket send scenario passed');
}).catch(e => { console.error(e); process.exit(1); });
