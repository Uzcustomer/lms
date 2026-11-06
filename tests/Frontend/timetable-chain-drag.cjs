const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const start = source.indexOf('async function placeBlockAt(');
const end = source.indexOf('// ===== Drag-and-drop holati', start);
const conflictStart = source.indexOf('function conflictsAt(');
const conflictEnd = source.indexOf('// ===== Joylash', conflictStart);
assert(start > 0 && end > start);
async function move(from, to, blocked = false, parallel = true, type = 'lecture') {
    const cards = [{id:1, pair:from, len:2, day:1, training_type:type}, {id:2, pair:from+2, len:1, day:1, training_type:type}];
    const calls = [], alerts = [];
    const ctx = {cards, phOf: () => null, parallelSet: () => [], parallelMoveOffer() {}, selected:null, effPlace:card=>({day:card.day,pair:card.pair}), renderAll:()=>{},
        alert:message=>alerts.push(message), weeklyLayer:'internal', effectiveCard:card=>card,
        cardGKeys:()=>['g'], cardLen:card=>card.len, bolakSiblings:()=>false,
        describeCardBrief:()=> 'Test darsi', isSharedRoomName:()=>false, weeksIntersect:()=>true,
        activeInWeek: c => !c.cancelled, curWeek: 8,
        unitOf: c => cards.filter(x => x.id !== 3), unitLen: l => l.reduce((n, x) => n + x.len, 0), unitCalls: [],
        placeUnitAt: async (c, d, p) => { ctx.unitCalls.push([c.id, d, p]); if (blocked) throw new Error("3 soat sig'maydi: 09:10 dan keyin bo'sh joy yo'q"); }};
    if (blocked) cards.push({id:3,day:1,pair:to,len:1});
    vm.createContext(ctx);
    require('./helpers/timetable-info-only.cjs')(ctx);
    vm.runInContext(source.slice(conflictStart,conflictEnd),ctx);
    ctx.placeOneCard = async (card,day,pair) => {
        if (ctx.conflictsAt(card,day,pair).length) throw new Error('Band');
        calls.push(card.id); card.day=day; card.pair=pair;
    };
    await vm.runInContext(source.slice(start,end)+`; placeBlockAt([1,2],1,${to},${parallel},${parallel ? '[0,2]' : 'null'});`,ctx);
    return {cards:cards.filter(c=>c.id!==3),calls,alerts,unitCalls:ctx.unitCalls};
}
(async () => {
    const later = await move(1,2);
    assert.deepEqual(later.alerts,[]);
    assert.deepEqual(later.calls,[2,1]);
    assert.deepEqual(later.cards.map(c=>c.pair),[2,4]);
    const earlier = await move(2,1);
    assert.deepEqual(earlier.alerts,[]);
    assert.deepEqual(earlier.calls,[1,2]);
    assert.deepEqual(earlier.cards.map(c=>c.pair),[1,3]);
    const blocked = await move(1,2,true);
    assert.equal(blocked.alerts.length,1);
    assert.deepEqual(blocked.calls,[]);
    assert.deepEqual(blocked.cards.map(c=>c.pair),[1,3]);
    // Ketma-ket (birlashgan) blok: 2 soatlik karta + 1 soatlik karta. Ikkinchisi birinchisining uzunligi qadar
    // keyin turadi (p+1 emas — u holda o'z bloki ustiga tushardi); pastga surilganda oxirgisi avval ko'chadi.
    const seqLater = await move(1,2,false,false);
    assert.deepEqual(seqLater.alerts,[]);
    assert.deepEqual(seqLater.calls,[2,1]);
    assert.deepEqual(seqLater.cards.map(c=>c.pair),[2,4]);
    const seqEarlier = await move(3,1,false,false);
    assert.deepEqual(seqEarlier.alerts,[]);
    assert.deepEqual(seqEarlier.calls,[1,2]);
    assert.deepEqual(seqEarlier.cards.map(c=>c.pair),[1,3]);
    // Amaliy dars (birlik): bo'laklar bitta so'rov bilan (unit-place) — sudralgan bo'lak qo'yilgan joydan birlik boshi hisoblanadi.
    const unit = await move(1,2,false,false,'practice');
    assert.deepEqual(unit.unitCalls,[[1,1,2]]);
    assert.deepEqual(unit.calls,[]);
    const unitBad = await move(1,2,true,false,'practice');
    assert.deepEqual(unitBad.alerts,["3 soat sig'maydi: 09:10 dan keyin bo'sh joy yo'q"]);

    // Codex: shu haftada bekor qilingan amaliy dars sudrab qaytarilsa — unit-place emas, kartaning o'zi tiklanadi (week-override).
    {
        const cards2 = [{ id: 1, pair: 1, len: 2, day: 1, training_type: 'practice', cancelled: true }];
        const c3 = { phOf: () => null, parallelSet: () => [], parallelMoveOffer() {}, cards: cards2, selected: null, effPlace: c => ({ day: c.day, pair: c.pair }), renderAll() {}, alert() {}, weeklyLayer: 'internal',
            effectiveCard: c => c, cardGKeys: () => ['g'], cardLen: c => c.len, bolakSiblings: () => false, describeCardBrief: () => '',
            isSharedRoomName: () => false, weeksIntersect: () => true, activeInWeek: c => !c.cancelled, curWeek: 8, unitCalls: [], placed: [],
            unitOf: () => cards2, unitLen: l => l.reduce((n, x) => n + x.len, 0), placeUnitAt: async (c) => { c3.unitCalls.push(c.id); } };
        vm.createContext(c3);
        require('./helpers/timetable-info-only.cjs')(c3);
        vm.runInContext(source.slice(conflictStart, conflictEnd), c3);
        c3.placeOneCard = async (card, d, p) => { c3.placed.push([card.id, d, p]); };
        await vm.runInContext(source.slice(start, end) + '; placeBlockAt([1], 2, 5, false, null);', c3);
        assert.deepEqual(c3.unitCalls, []);
        assert.deepEqual(c3.placed, [[1, 2, 5]]);
    }

    // Yarim para qadami: 2 qatorli (rowspan) katakning pastki yarmiga tushirilsa — ikkinchi yarim-slot.
    const helpers = source.slice(source.indexOf('            function dropSlotAt('), source.indexOf('            // Sudrash paytida tushadigan vaqt oralig'));
    const hx = { phOf: () => null, activeInWeek: () => true, curWeek: 8, cards: [{id:1, day:5, pair:5, len:2, training_type:'practice'}, {id:2, day:5, pair:7, len:1, training_type:'practice'},
        {id:9, day:5, pair:8, len:2, training_type:'practice', teacher_id: 7}], Math, boardSchedule: () => Array.from({length: 12}, () => ({type: 'pair'})) };
    hx.cards[0].teacher_id = hx.cards[1].teacher_id = 7;
    Object.assign(hx, { cardLen: c => c.len, unitOf: c => (c.id === 9 ? [c] : hx.cards.slice(0, 2)), unitLen: l => l.reduce((n, x) => n + x.len, 0),
        conflictsAt: (c, d, p, ign) => hx.cards.filter(o => o.id !== c.id && !ign.includes(o.id) && o.day === d && o.teacher_id === c.teacher_id
            && p < o.pair + o.len && o.pair < p + c.len).map(o => "O'qituvchi band: #" + o.id) });
    // dragPlan sabablarni tafsilot bilan oladi (conflictsAtDetail — rang turi uchun): stub — o'sha matnlar, o'qituvchi turi.
    hx.conflictsAtDetail = (c, d, p, ign) => hx.conflictsAt(c, d, p, ign).map(text => ({ kind: 'teacher', cardId: null, text }));
    const plan = r => { const { start, len, errs } = r; return JSON.parse(JSON.stringify({ start, len, errs })); };
    vm.createContext(hx);
    require('./helpers/timetable-info-only.cjs')(hx);
    vm.runInContext(helpers + '; this.dropSlotAt = dropSlotAt; this.dragPlan = dragPlan;', hx);
    const td = { dataset: { pair: '5' }, rowSpan: 2, getBoundingClientRect: () => ({ top: 100, height: 40 }) };
    assert.equal(hx.dropSlotAt(td, { clientY: 110 }), 5);
    assert.equal(hx.dropSlotAt(td, { clientY: 130 }), 6);
    assert.equal(hx.dropSlotAt({ dataset: { pair: '3' }, rowSpan: 1 }, { clientY: 0 }), 3);
    // 3 soatlik dars (2 + 1) 12:40 ga (6 — 12:40–14:50): o'z bo'laklari to'qnashuv emas; 14:10 dagi (8) boshqa dars — haqiqiy to'qnashuv.
    assert.deepEqual(plan(hx.dragPlan([1], 5, 6, false)), { start: 6, len: 3, errs: ["O'qituvchi band: #9"] });
    assert.deepEqual(plan(hx.dragPlan([1], 5, 5, false)), { start: 5, len: 3, errs: [] });
    hx.cards[2].pair = 9;
    assert.deepEqual(plan(hx.dragPlan([1], 5, 6, false)), { start: 6, len: 3, errs: [] });
    // 1 soatlik bo'lakdan sudralsa — birlik boshi undan oldingi bo'lak uzunligicha yuqorida.
    assert.equal(hx.dragPlan([2], 5, 8, false).start, 6);
    console.log('10 timetable chain movement scenarios passed');
})().catch(error=>{ console.error(error); process.exitCode=1; });
