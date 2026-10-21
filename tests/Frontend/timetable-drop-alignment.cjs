const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const start = source.indexOf('function alignBolakDrops()');
const end = source.indexOf("window.addEventListener('resize'",start);
assert(start > 0 && end > start);
function align(rowTops, height, expected) {
    const zones = Array.from({length:expected.length},()=>({style:{}}));
    const rows = rowTops.map(top=>({getBoundingClientRect:()=>({top})}));
    rows.forEach((row,i)=>row.nextElementSibling=rows[i+1] || null);
    const td={querySelectorAll:()=>zones,parentElement:rows[0],getBoundingClientRect:()=>({top:rowTops[0],height})};
    const ctx={document:{querySelectorAll:()=>[td]}};
    vm.createContext(ctx);
    vm.runInContext(source.slice(start,end)+'; alignBolakDrops();',ctx);
    assert.deepEqual(zones.map(z=>[z.style.top,z.style.height]),expected);
}
align([100,180,200,260],160,[['0px','80px'],['80px','20px'],['100px','60px']]);
align([100,180,200],160,[['0px','80px'],['80px','20px'],['100px','60px']]);
console.log('2 timetable drop alignment scenarios passed');
