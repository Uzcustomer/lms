const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const start = source.indexOf('const bolakCell = ');
const end = source.indexOf('\n\n                for (let d = 1;', start);
assert(start > 0 && end > start);
function render(cards) {
  const cellStack = {}, placedIdx = {};
  for (const c of cards) {
    const key = 'g|1|' + c.pair;
    (cellStack[key] ||= []).push(c);
    for (let q=c.pair; q<c.pair+c.len_half; q++) placedIdx['g|1|'+q] = c;
  }
  const ctx = {cellStack, placedIdx, pGroups:{1:{span:2},2:{span:0},3:{span:2},4:{span:0}}, vConsumed:{}, gName:{g:'01a'},
    BOLAK_RX:/^(.*)\s+\(([a-z])\)$/i,
    bolakBaseOf:x => /^(.*)\s+\(([a-z])\)$/i.exec(x.subject_name)?.[1] ?? null,
    bolakLetterOf:x => /\(([a-z])\)$/i.exec(x.subject_name)?.[1] ?? '',
    cardLen:x=>x.len_half, effPlace:x=>({pair:x.pair}), baseKey:x=>x,
    pairTime:{1:{start:'12:00',end:'12:40'},2:{start:'12:40',end:'13:20'},3:{start:'13:20',end:'14:00'}},
    selected:null, esc:String, cardTooltip:x=>x.subject_name, cardGroups:()=>['01a'], shortGroup:x=>x,
    courseCls:()=>'',colBorder:()=>'',rowEndCls:()=>'',subjStyle:()=>''};
  vm.createContext(ctx);
  return vm.runInContext(source.slice(start,end)+'; bolakCell({groups:["g"],course:2},0,0,1,1)',ctx);
}
const a={id:1,subject_name:'Gigiyena (a)',pair:1,len_half:2,training_type:'practice'};
const b={id:2,subject_name:'Gigiyena (b)',pair:2,len_half:2,training_type:'practice'};
const late=render([a,b]);
assert.match(late.html,/rowspan="3"/);
assert.match(late.html,/data-parallel-offs="0,1"/);
assert.match(late.html,/12:40–14:00/);
assert.match(late.html,/data-drop-pair="2"/);
const equal=render([a,{...b,pair:1}]);
assert.match(equal.html,/rowspan="2"/);
assert.doesNotMatch(equal.html,/tt-bolak-late/);
const foreign=render([a,{...b,subject_name:'Anatomiya'}]);
assert.equal(foreign,null);
const lecture=render([a,{...b,training_type:'lecture'}]);
assert.equal(lecture,null);
const continuation = render([a,{...a,id:3,pair:3,len_half:1}]);
assert.match(continuation.html,/rowspan="3"/);
assert.match(continuation.html,/data-parallel-ids="1,3" data-parallel-offs="0,2"/);
assert.equal((continuation.html.match(/class="tt-bolak-item/g) || []).length,1);
assert.match(continuation.html,/12:00–14:00/);
const gap = render([a,{...a,id:3,pair:4,len_half:1}]);
assert.match(gap.html,/rowspan="2"/);
assert.doesNotMatch(gap.html,/data-parallel-ids="1,3"/);
const differentTeacher = render([{...a,teacher_id:1,teacher_name:'A'},
  {...a,id:3,pair:3,len_half:1,teacher_id:2,teacher_name:'B'}]);
assert.equal((differentTeacher.html.match(/class="tt-bolak-item/g) || []).length,2);
const differentRoom = render([{...a,auditorium_code:'R1'},
  {...a,id:3,pair:3,len_half:1,auditorium_code:'R2'}]);
assert.equal((differentRoom.html.match(/class="tt-bolak-item/g) || []).length,2);
console.log('8 timetable split-cell rendering scenarios passed');
