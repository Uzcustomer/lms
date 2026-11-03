const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
// Load the production policy into tests that execute individual Blade functions.
module.exports = context => {
    if (!Object.prototype.hasOwnProperty.call(context, 'board')) context.board = null;
    const source = fs.readFileSync(path.join(__dirname, '../../../resources/views/admin/timetable/index.blade.php'), 'utf8');
    const start = source.indexOf('            function isHemisExcluded(card)');
    const end = source.indexOf('            function applyWeeklyLayer(list)', start);
    if (start < 0 || end <= start) throw new Error('Information-only policy is missing');
    vm.runInContext(source.slice(start, end), context);
};
