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
async function move(from, to, blocked = false, parallel = true) {
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
    await vm.runInContext(source.slice(start,end)+`; placeBlockAt([1,2],1,${to},${parallel},${parallel ? '[0,2]' : 'null'});`,ctx);
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
    console.log('5 timetable chain movement scenarios passed');
})().catch(error=>{ console.error(error); process.exitCode=1; });
