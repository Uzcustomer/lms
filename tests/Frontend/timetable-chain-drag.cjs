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
async function move(from, to, blocked = false) {
    const cards = [{id:1, pair:from, len:2, day:1}, {id:2, pair:from+2, len:1, day:1}];
    const calls = [], alerts = [];
    const ctx = {cards, selected:null, effPlace:card=>({day:card.day,pair:card.pair}), renderAll:()=>{},
        alert:message=>alerts.push(message), weeklyLayer:'internal', effectiveCard:card=>card,
        cardGKeys:()=>['g'], cardLen:card=>card.len, bolakSiblings:()=>false,
        describeCardBrief:()=> 'Test darsi', isSharedRoomName:()=>false, weeksIntersect:()=>true};
    if (blocked) cards.push({id:3,day:1,pair:to,len:1});
    vm.createContext(ctx);
    vm.runInContext(source.slice(conflictStart,conflictEnd),ctx);
    ctx.placeOneCard = async (card,day,pair) => {
        if (ctx.conflictsAt(card,day,pair).length) throw new Error('Band');
        calls.push(card.id); card.day=day; card.pair=pair;
    };
    await vm.runInContext(source.slice(start,end)+`; placeBlockAt([1,2],1,${to},true,[0,2]);`,ctx);
    return {cards:cards.filter(c=>c.id!==3),calls,alerts};
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
    console.log('3 timetable chain movement scenarios passed');
})().catch(error=>{ console.error(error); process.exitCode=1; });
