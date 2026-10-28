const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to,a);
    assert(a >= 0 && b > a);
    return source.slice(a,b);
}
function element(tag, text='', classes=[], children=[], id) {
    const node = {tagName:tag, innerText:text, children, dataset:{chip:String(id)}, colSpan:1, rowSpan:1,
        classList:{contains:name=>classes.includes(name)}};
    node.querySelectorAll = selector => children.flatMap(child => [child,...child.querySelectorAll('*')])
        .filter(child=>selector === '*' || child.classList.contains(selector.slice(1)));
    node.querySelector = selector => node.querySelectorAll(selector)[0] || null;
    return node;
}
function exportGrid(bolak=false) {
    const chip = element('DIV','Gigiyena',['tt-chip'],[],1);
    const head = element('DIV','Gigiyena 12:00–14:00',['tt-bolak-head']);
    const part = element('DIV','a 01a 15 SINOV 12:40–14:00',['tt-bolak-item'],[],1);
    const drops = element('DIV','DROP LINE MUST NOT EXPORT',['tt-bolak-drops']);
    const cell = element('TD','',['tt-cell',...(bolak?['tt-bolak-cell']:[])],bolak?[drops,head,part]:[chip]);
    cell.rowSpan = 2; cell.colSpan = 2;
    const header = {children:[element('TH','Guruh')]};
    const body = {children:[cell]};
    const grid = {querySelector:()=>body,querySelectorAll:s=>s==='tr'?[header,body]:s==='thead tr'?[header]:[]};
    const cards = [{id:1,subject_name:'Gigiyena',faculty_name:'F1',specialty_name:'Davolash',course:2,training_type:'practice',
        teacher_name:'SINOV A.',group_name:'01a',weeks:4,auditorium_name:'Eski xona'},
        {id:2,subject_name:'HIDDEN SUBJECT'}];
    const ctx = {document:{getElementById:()=>grid},cards,grids:{},gridKey:()=>'',gridForCard:()=>({weeks:4}),board:{weeks:4},curWeek:4,weeklyLayer:'internal',
        overrides:{'1|2':{cancelled:true},'1|4':{auditorium_code:'R101',auditorium_name:'101'}},
        getComputedStyle:()=>({backgroundColor:'transparent',fontWeight:'400'}),rgbToHex:()=>null};
    vm.createContext(ctx);
    const effective = section('function effectiveCard(c)', '// Karta shu haftada shablondan farq');
    const functions = section('function excelCardText(card)', '// Jadvalni HAQIQIY');
    return vm.runInContext(effective+functions+'; gridExportCells();',ctx);
}
const normal = exportGrid();
const text = normal.rows[1][0].t;
assert.match(text,/\[A\] Gigiyena/);
assert.match(text,/O'qituvchi: SINOV A\./);
assert.match(text,/Xona: 101/);
assert.match(text,/Haftalar: 1, 3, 4/);
assert.doesNotMatch(text,/HIDDEN SUBJECT|Eski xona/);
assert.equal(normal.rows[1][0].cs,2);
assert.equal(normal.rows[1][0].rs,2);
assert.equal(normal.freeze_rows,1);
const split = exportGrid(true).rows[1][0].t;
assert.match(split,/Gigiyena 12:00–14:00/);
assert.match(split,/• a 01a 15 SINOV 12:40–14:00 · SINOV A\. · №101/);
assert.doesNotMatch(split,/DROP LINE MUST NOT EXPORT/);
function initialScope(saved, unavailable=false) {
    const ctx = {TIMETABLE_HAS_SUBJECTS:true,TIMETABLE_ROLE:'fan_masuli',TIMETABLE_TEACHER_ID:1,TIMETABLE_DEPARTMENT_HEAD:false,
        localStorage:{getItem:()=>{if(unavailable)throw Error('Unavailable');return saved;}}};
    vm.createContext(ctx);
    return vm.runInContext(section('let scopeMode =', 'let mineOnly =')+'; scopeMode;',ctx);
}
assert.equal(initialScope(null),'subjects');
assert.equal(initialScope(null,true),'subjects');
assert.equal(initialScope('0'),'all');
const scopeContext = {scopeMode:'subjects',mySubjectCardIds:new Set([1]),list:[{id:1},{id:2}]};
vm.createContext(scopeContext);
assert.equal(vm.runInContext(section('function scopeCards(list)', 'let allCards =')+'; scopeCards(list);',scopeContext).length,1);
(async()=>{
    for (const layer of ['internal','external','long']) {
        const calls=[];
        const ctx = {gridExportCells:()=>({rows:[[{t:'Visible only'}]]}),weeklyLayer:layer,curWeek:4,scopeMode:'subjects',
            scopeModeLabel:()=>"Mas'ul fanlarim",scopeLabelText:()=> 'F1 · 2-kurs',curSpec:{},board:{id:1,name:layer==='long'?'A'.repeat(230):'Sinov'},
            $:()=>({}),sendExcelExport:async (...args)=>calls.push(args),alert:()=>{throw Error('Unexpected alert');}};
        vm.createContext(ctx);
        await vm.runInContext(section('async function downloadWeeklyExcel()', "if ($('weeklyExcel'))")+'; downloadWeeklyExcel();',ctx);
        assert.equal(calls.length,1);
        const [payload,title,filename] = calls[0];
        assert(title.length<=255);
        assert(filename.length<=150);
        assert.equal(payload.sheet_title,'Haftalik '+(layer==='external'?'tashqi':'ichki'));
        assert.equal(payload.landscape,1);
        assert.match(title,/4-hafta/);
        assert.match(title,/Mas'ul fanlarim/);
        assert.match(title,/F1 · 2-kurs/);
    }
    console.log('9 weekly Excel and responsible scope scenarios passed');
})().catch(error=>{console.error(error);process.exitCode=1;});
