const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Brauzerda haqiqiy maket (flex) o'lchanadi — Playwright va Chromium kerak.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Sahifa maketi blade'dan olinadi: Blade sintaksisi olib tashlanadi, stillar va o'lchamni o'zgartiruvchi skript o'zgarmaydi.
const cut = (from, to) => {
  const a = source.indexOf(from), b = source.indexOf(to, a);
  assert(a > 0 && b > a, from);
  return source.slice(a, b);
};
const markup = cut('<div class="max-w-full mx-auto px-2 lg:px-4"', '{{-- Fan ranglari modali --}}')
  .replace(/\{\{--[\s\S]*?--\}\}/g, '')
  .replace(/\{\{[\s\S]*?\}\}/g, '')
  .replace(/^\s*@\w+.*$/gm, '')
  .replace(/@(foreach|endforeach)[^<]*/g, '') + '</div></div>';
const css = cut('<style>', '</style>').slice('<style>'.length);
const resizer = cut("        // Menyu va joylashmagan kartalar panelining", '    </script>');
// Sahifada Tailwind bor; maket uchun kerakli bir nechta klass.
const tw = '.hidden{display:none}.flex{display:flex}.flex-wrap{flex-wrap:wrap}.overflow-auto{overflow:auto}'
  + '.overflow-y-auto{overflow-y:auto}.p-2{padding:.5rem}.gap-1\\.5{gap:.375rem}*{box-sizing:border-box}body{margin:0}';

async function layout(page, stored) {
  await page.goto('http://tt.test/');
  await page.evaluate(s => {
    localStorage.clear();
    for (const [k, v] of Object.entries(s)) localStorage.setItem('tt-size-' + k, JSON.stringify(v));
    document.getElementById('mainArea').classList.remove('hidden');
    document.getElementById('grid').innerHTML = '<tr><td style="height:1500px">panjara</td></tr>';
    document.getElementById('cardPanel').innerHTML = '<div style="width:100%;height:40px">karta</div>'.repeat(60);
  }, stored);
  await page.addScriptTag({ content: resizer });
  return page.evaluate(() => {
    const r = id => document.getElementById(id).getBoundingClientRect();
    const box = document.querySelector('.max-w-full').getBoundingClientRect();
    return { boxBottom: box.bottom, gridH: r('gridWrap').height, panelBottom: r('cardPanel').bottom, panelH: r('cardPanel').height };
  });
}

(async () => {
  const browser = await chromium.launch();
  try {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 700 } })).newPage();
    // localStorage ishlashi uchun sahifa oddiy manzildan beriladi.
    const html = '<!doctype html><html><head><style>' + tw + css + '</style></head><body>' + markup + '</body></html>';
    await page.route('http://tt.test/**', r => r.fulfill({ contentType: 'text/html', body: html }));

    // Odatiy holat: panel 120px, panjara qolgan joyni egallaydi.
    let m = await layout(page, {});
    assert.equal(Math.round(m.panelH), 120);
    assert.ok(m.panelBottom <= m.boxBottom + 0.5, JSON.stringify(m));

    // Katta ekranda saqlangan (yoki shu oynada juda katta surilgan) panel balandligi: panel konteynerdan
    // chiqib, pastki kartalari ko'rinmay qolmasligi va panjara butunlay yo'qolmasligi kerak.
    m = await layout(page, { panel: 900 });
    assert.ok(m.panelBottom <= m.boxBottom + 0.5, 'panel konteynerdan chiqib ketdi: ' + JSON.stringify(m));
    assert.ok(m.gridH >= 40, 'panjara yo\'qolib qoldi: ' + JSON.stringify(m));

    console.log('timetable resizers layout: 2 scenarios passed');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
