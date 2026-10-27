const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Menyu va joylashmagan kartalar paneli balandligini surish: o'lcham faqat haqiqiy surishda eslab qolinadi.
const a = source.indexOf("        // Menyu va joylashmagan kartalar panelining");
const b = source.indexOf('    </script>', a);
assert(a > 0 && b > a);
const code = source.slice(a, b);

function page() {
  const storage = {};
  const el = (h, natural) => {
    const on = {};
    return {
      style: {}, scrollHeight: natural, classList: { add() { }, remove() { } },
      addEventListener: (t, f) => (on[t] = on[t] || []).push(f),
      removeEventListener: (t, f) => { on[t] = (on[t] || []).filter(x => x !== f); },
      fire: (t, ev) => [...(on[t] || [])].forEach(f => f(Object.assign({ button: 0, pointerId: 1, preventDefault() { } }, ev))),
      setPointerCapture() { },
      getBoundingClientRect() { return { height: this.style.height ? parseFloat(this.style.height) : h }; },
    };
  };
  // Menyu tabiiy balandligi 200px; panelda 2 ta karta — 40px.
  const els = { ttTopArea: el(200, 200), cardPanel: el(40, 40), ttResizeTop: el(7), ttResizeBottom: el(7) };
  const ctx = {
    window: { innerHeight: 900 },
    document: { getElementById: id => els[id] || null, body: { classList: { add() { }, remove() { } } } },
    localStorage: {
      getItem: k => (k in storage ? storage[k] : null),
      setItem: (k, v) => { storage[k] = String(v); },
      removeItem: k => { delete storage[k]; },
    },
  };
  vm.createContext(ctx);
  vm.runInContext(code, ctx);
  const drag = (handle, ...ys) => {
    const h = els[handle];
    h.fire('pointerdown', { clientY: 500 });
    ys.forEach(y => h.fire('pointermove', { clientY: y }));
    h.fire('pointerup', {});
  };
  return { els, storage, drag };
}

// Oddiy bosish (surmasdan) panelning hozirgi (kartalar soniga bog'liq) balandligini qotirib qo'ymaydi:
// keyin kartalar ko'paysa ham panel avvalgidek 120px gacha o'sadi.
let p = page();
p.drag('ttResizeBottom');
p.drag('ttResizeBottom', 501);
assert.equal(p.storage['tt-size-panel'], undefined);
assert.equal(p.els.cardPanel.style.height, '');
p.drag('ttResizeTop');
assert.equal(p.storage['tt-size-top'], undefined);

// Haqiqiy surish eslab qolinadi.
p.drag('ttResizeBottom', 450, 400);
assert.equal(p.storage['tt-size-panel'], '140');
assert.equal(p.els.cardPanel.style.height, '140px');
p.drag('ttResizeTop', 480, 450);
assert.equal(p.storage['tt-size-top'], '150');
assert.equal(p.els.ttTopArea.style.height, '150px');

// Menyu to'liq balandligiga qaytarilsa — px emas, "tabiiy" holat saqlanadi (menyu keyin kattalashsa ham
// skroll bilan kesilib qolmaydi).
p.drag('ttResizeTop', 600);
assert.equal(p.storage['tt-size-top'], undefined);
assert.equal(p.els.ttTopArea.style.height, '');

console.log('timetable resizers: 3 scenarios passed');
