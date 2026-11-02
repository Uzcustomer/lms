const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Takliflar qatori: qo'llash mumkin — katakcha va faol tugma; mumkin emas — o'chiq tugma va sababi; faqat ko'rish — tugma yo'q.
const a = source.indexOf('            function sugApplyLabel(mode)');
const b = source.indexOf("            if ($('suggestWeekBtn')) $('suggestWeekBtn').onclick = openWeekSuggestions;", a);
assert(a > 0 && b > a);
const ctx = { esc: s => String(s), SUG_COLOR: {}, SUG_LABEL: { slot: "Bo'sh vaqt", blocker: 'Darsni surish' }, answers: [] };
ctx.prompt = () => ctx.answers.shift();
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '\nthis.sugRowHtml = sugRowHtml; this.sugFooterHtml = sugFooterHtml; this.askSugReason = askSugReason;', ctx);
// "So'rov yuborish" — sabab oynasi: bekor yoki bo'sh — null (yuborilmaydi); kiritilgani — tozalangan holda.
ctx.answers = [null, '   ', '  Ertalab amaliyot  '];
assert.equal(ctx.askSugReason(), null);
assert.equal(ctx.askSugReason(), null);
assert.equal(ctx.askSugReason(), 'Ertalab amaliyot');
assert.match(source, /Object\.assign\(\{ week: res\.week, card_id: c\.id, type: sug\.type, title: sug\.title, actions: sug\.actions \}, sug\.mode === 'direct' \? \{\} : \{ reason \}\)\);/);
assert.match(source, /const reason = sug\.mode === 'direct' \? '' : askSugReason\(\);\n\s+if \(reason === null\) \{ sugNote\(body, "So'rov yuborilmadi: sabab kiritilmadi\.", 'err'\); return; \}/);
assert.match(source, /const reason = list\.some\(\(\[ci, si\]\) => window\.__ttSug\[ci\]\.suggestions\[si\]\.mode !== 'direct'\) \? askSugReason\(\) : '';/);

const ok = ctx.sugRowHtml({ type: 'slot', title: 'Seshanba III', can_apply: true, mode: 'department' }, 0, 1, 'request');
assert.match(ok, /data-sug-pick="0:1"/);
assert.match(ok, /data-sug="0:1" style=/);
assert.match(ok, />So'rov yuborish<\/button>/);
const no = ctx.sugRowHtml({ type: 'blocker', title: 'Surish', can_apply: false, mode: null, why: "Boshqa o'qituvchining darsini surish kerak" }, 0, 2, 'request');
assert.doesNotMatch(no, /data-sug-pick/);
assert.match(no, /data-sug="0:2" disabled title="Boshqa o'qituvchining darsini surish kerak"/);
assert.match(no, /class="sug-why"[^>]*>Boshqa o'qituvchining/);
const view = ctx.sugRowHtml({ type: 'slot', title: 'x', can_apply: false, why: "O'quv bo'limi takliflarni faqat ko'radi." }, 0, 0, null);
assert.doesNotMatch(view, /data-sug|sug-why/);
assert.match(ctx.sugRowHtml({ type: 'slot', title: 'x', can_apply: true, mode: 'direct' }, 1, 0, 'direct'), />Qo'llash<\/button>/);
assert.match(ctx.sugFooterHtml('request'), /data-sug-apply-all disabled[^>]*>Tanlanganlarni qo'llash/);
assert.match(ctx.sugFooterHtml(null), /Faqat ko'rish/);
console.log('timetable suggestions apply: 7 scenarios passed');
