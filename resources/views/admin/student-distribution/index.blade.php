<x-app-layout>
<style>
    .sd {
        --navy:#0f2748; --navy-soft:#1b3a63; --gold:#c9a227;
        --ink:#17233a; --ink-soft:#4d6180; --muted:#8798b1;
        --line:#dde5ef; --line-soft:#eef2f8;
        --ok:#0f7a52; --bad:#b3261e;
        color:var(--ink); font-family:'Roboto',system-ui,-apple-system,'Segoe UI',sans-serif;
    }
    .sd-head {
        padding:20px 24px; border:1px solid var(--line); border-left:3px solid var(--gold);
        border-radius:6px; background:linear-gradient(180deg,#fbfcfe,#f5f8fc);
    }
    .sd-head h1 { margin:0; color:var(--navy); font-size:21px; font-weight:800; letter-spacing:-.01em; }
    .sd-head p { margin:5px 0 0; color:var(--ink-soft); font-size:13px; }

    /* ---- Ikki ustun ---- */
    .sd-cols { display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:start; }
    .sd-side { overflow:hidden; border:1px solid var(--line); border-radius:6px; background:#fff; }
    .sd-side-head {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:13px 16px; border-bottom:1px solid var(--line-soft);
        background:linear-gradient(180deg,#fbfcfe,#f5f8fc);
    }
    .sd-side.is-right .sd-side-head { background:linear-gradient(180deg,#fffcf7,#fdf7ec); }
    .sd-side-head h2 { margin:0; color:var(--navy); font-size:14.5px; font-weight:700; }
    .sd-side-head p { margin:3px 0 0; color:var(--muted); font-size:11.5px; }
    .sd-count {
        padding:4px 10px; border-radius:999px; background:#eef4fd; color:var(--navy);
        font-size:11px; font-weight:700; white-space:nowrap;
    }
    .sd-side.is-right .sd-count { background:#fdf3e0; color:#8a5a06; }

    /* ---- Har paneldagi filtrlar (ixcham) ---- */
    .sd-filters {
        display:grid; grid-template-columns:1fr 1fr 74px 1fr; gap:6px;
        padding:9px 12px; border-bottom:1px solid var(--line-soft); background:#fdfeff;
    }
    .sd-filters select, .sd-filters input {
        width:100%; height:30px; padding:0 7px; border:1px solid #cfd9e6; border-radius:4px;
        background:#fff; color:var(--ink); font-family:inherit; font-size:11.5px; outline:none;
        transition:border-color .14s, box-shadow .14s;
    }
    .sd-filters select:focus, .sd-filters input:focus {
        border-color:var(--navy-soft); box-shadow:0 0 0 2px rgba(27,58,99,.1);
    }

    /* ---- Tablar ---- */
    .sd-tabs { display:flex; gap:2px; padding:8px 12px 0; border-bottom:1px solid var(--line-soft); background:#fdfeff; }
    .sd-tab {
        padding:7px 12px; border:0; border-bottom:2px solid transparent; background:transparent;
        color:var(--muted); font-family:inherit; font-size:11.5px; font-weight:600; cursor:pointer;
    }
    .sd-tab:hover { color:var(--navy); }
    .sd-tab.is-on { border-color:var(--gold); color:var(--navy); }
    .sd-tab span {
        margin-left:5px; padding:1px 6px; border-radius:999px;
        background:#eef2f8; color:var(--ink-soft); font-size:10px; font-weight:700;
    }
    .sd-tab.is-on span { background:var(--navy); color:#fff; }

    /* ---- Ro'yxat ---- */
    .sd-rows { max-height:600px; overflow-y:auto; }
    .sd-row {
        display:grid; grid-template-columns:26px auto minmax(0,1fr) auto; align-items:center; gap:10px;
        padding:10px 16px; border-bottom:1px solid var(--line-soft); transition:background .12s;
    }
    .sd-row:last-child { border-bottom:0; }
    .sd-row:hover { background:#fafcfe; }
    .sd-row.is-picked { background:#fffbf2; }
    .sd-idx { color:var(--muted); font-size:11px; font-weight:600; text-align:right; font-variant-numeric:tabular-nums; }
    .sd-row input[type=checkbox] { width:15px; height:15px; accent-color:var(--navy); cursor:pointer; }
    .sd-name { color:var(--ink); font-size:13px; font-weight:600; }
    .sd-meta { margin-top:2px; color:var(--muted); font-size:11px; }
    .sd-num {
        padding:3px 9px; border-radius:4px; background:#f2f6fc; color:var(--navy);
        font-size:11.5px; font-weight:700; white-space:nowrap;
    }
    .sd-num em { font-style:normal; color:var(--muted); font-weight:500; }
    .sd-tag {
        margin-left:6px; padding:2px 7px; border-radius:999px;
        background:#fdf3e0; color:#8a5a06; font-size:10px; font-weight:700;
    }

    .sd-empty { padding:42px 20px; color:var(--muted); font-size:12.5px; text-align:center; }
    .sd-empty b { display:block; margin-bottom:5px; color:var(--ink-soft); font-size:13.5px; font-weight:700; }

    .sd-actions {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:12px 16px; border-top:1px solid var(--line-soft); background:#fbfcfe;
    }
    .sd-hint { color:var(--muted); font-size:12px; }
    .sd-hint b { color:var(--navy); font-weight:700; }
    .sd-btn {
        height:34px; padding:0 16px; border:0; border-radius:5px; background:var(--navy); color:#fff;
        font-family:inherit; font-size:12.5px; font-weight:500; letter-spacing:.03em; cursor:pointer;
        transition:background .15s;
    }
    .sd-btn:hover { background:var(--navy-soft); }
    .sd-btn:disabled { background:#9aa9bd; cursor:not-allowed; }

    @media (max-width:1100px) { .sd-cols { grid-template-columns:1fr; } }
    @media (max-width:560px) { .sd-filters { grid-template-columns:1fr 1fr; } }
</style>

    <div class="sd py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

            <div class="sd-head">
                <h1>Talabalarni taqsimlash</h1>
                <p>Chapda bo'sh joyli guruhlar to'ldiriladi, o'ngda talabalari taqsimlanadigan guruhlar belgilanadi. Faqat bakalavr, faol guruhlar va o'qiyotgan talabalar.</p>
            </div>

            <div class="sd-cols">
                {{-- CHAP --}}
                <section class="sd-side" id="leftSide">
                    <div class="sd-side-head">
                        <div>
                            <h2>Bo'sh guruhlarni to'ldirish</h2>
                            <p>Talabalar shu guruhlarga ko'chiriladi</p>
                        </div>
                        <span class="sd-count" id="leftCount">0 ta</span>
                    </div>

                    <div class="sd-filters">
                        <select data-f="faculty"><option value="">Barcha fakultetlar</option></select>
                        <select data-f="specialty"><option value="">Barcha yo'nalishlar</option></select>
                        <select data-f="course"><option value="">Kurs</option></select>
                        <input data-f="search" type="search" placeholder="Guruh nomi">
                    </div>

                    <div class="sd-rows" id="leftRows"></div>
                </section>

                {{-- O'NG --}}
                <section class="sd-side is-right" id="rightSide">
                    <div class="sd-side-head">
                        <div>
                            <h2>Taqsimlanadigan guruhlar</h2>
                            <p>Talabalari ko'chiriladigan guruhlarni belgilang</p>
                        </div>
                        <span class="sd-count" id="rightCount">0 ta</span>
                    </div>

                    <div class="sd-filters">
                        <select data-f="faculty"><option value="">Barcha fakultetlar</option></select>
                        <select data-f="specialty"><option value="">Barcha yo'nalishlar</option></select>
                        <select data-f="course"><option value="">Kurs</option></select>
                        <input data-f="search" type="search" placeholder="Guruh nomi">
                    </div>

                    <div class="sd-tabs">
                        <button class="sd-tab is-on" data-view="all" type="button">Barcha guruhlar <span id="tabAll">0</span></button>
                        <button class="sd-tab" data-view="picked" type="button">Belgilanganlar <span id="tabPicked">0</span></button>
                    </div>

                    <div class="sd-rows" id="rightRows"></div>

                    <div class="sd-actions">
                        <span class="sd-hint"><b id="pickedTotal">0</b> ta guruh belgilangan</span>
                        <button class="sd-btn" id="saveSources" type="button">Saqlash</button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const $ = id => document.getElementById(id);
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const saveUrl = @json(route('admin.student-distribution.source-groups.store'));

        let groups = @json($groupPayloads);
        let picked = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
        let rightView = 'all';

        const courseLabel = g => g.level_name || (g.level_code ? g.level_code + '-kurs' : '');

        // Har bir panelning o'z filtrlari bor — ular bir-biriga ta'sir qilmaydi.
        const panels = {
            left:  {root: $('leftSide'),  rows: $('leftRows'),  count: $('leftCount'),  checkbox: false},
            right: {root: $('rightSide'), rows: $('rightRows'), count: $('rightCount'), checkbox: true},
        };

        const readFilters = panel => {
            const get = key => panel.root.querySelector('.sd-filters [data-f="' + key + '"]').value;
            return {
                faculty: get('faculty'),
                specialty: get('specialty'),
                course: get('course'),
                search: get('search').trim().toLowerCase(),
            };
        };

        const applyFilters = f => groups.filter(g =>
            (!f.faculty || g.faculty_name === f.faculty) &&
            (!f.specialty || g.specialty_name === f.specialty) &&
            (!f.course || String(g.level_code) === f.course) &&
            (!f.search || String(g.group_name).toLowerCase().includes(f.search))
        );

        function rowHtml(g, index, withCheckbox) {
            const isPicked = picked.has(g.group_hemis_id);
            const check = withCheckbox
                ? '<input type="checkbox" data-id="' + g.group_hemis_id + '"' + (isPicked ? ' checked' : '') + '>'
                : '<span></span>';
            const tag = !withCheckbox && isPicked ? '<span class="sd-tag">Taqsimlanadi</span>' : '';

            return '<label class="sd-row' + (withCheckbox && isPicked ? ' is-picked' : '') + '">' +
                '<span class="sd-idx">' + index + '.</span>' +
                check +
                '<span><span class="sd-name">' + esc(g.group_name) + tag + '</span>' +
                '<span class="sd-meta">' + esc(g.faculty_name || '—') + ' · ' + esc(g.specialty_name || '—') +
                (courseLabel(g) ? ' · ' + esc(courseLabel(g)) : '') + '</span></span>' +
                '<span class="sd-num">' + g.student_count + ' <em>talaba</em></span>' +
                '</label>';
        }

        function renderPanel(key) {
            const panel = panels[key];
            let list = applyFilters(readFilters(panel));

            if (key === 'right' && rightView === 'picked') {
                list = list.filter(g => picked.has(g.group_hemis_id));
            }

            panel.rows.innerHTML = list.length
                ? list.map((g, i) => rowHtml(g, i + 1, panel.checkbox)).join('')
                : '<div class="sd-empty"><b>Guruh topilmadi</b>Tanlangan filtr bo\'yicha guruh yo\'q.</div>';
            panel.count.textContent = list.length + ' ta';
        }

        function renderTabs() {
            const inRight = applyFilters(readFilters(panels.right));
            $('tabAll').textContent = inRight.length;
            $('tabPicked').textContent = inRight.filter(g => picked.has(g.group_hemis_id)).length;
            $('pickedTotal').textContent = picked.size;
        }

        function render() {
            renderPanel('left');
            renderPanel('right');
            renderTabs();
        }

        function setOptions(select, values, placeholder) {
            const current = select.value;
            select.innerHTML = '<option value="">' + placeholder + '</option>' +
                [...new Set(values.filter(Boolean))].sort().map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
            if ([...select.options].some(o => o.value === current)) select.value = current;
        }

        // Fakultet -> yo'nalish -> kurs bog'lanishi har panelda alohida ishlaydi.
        function refreshOptions(panel) {
            const pick = key => panel.root.querySelector('.sd-filters [data-f="' + key + '"]');
            setOptions(pick('faculty'), groups.map(g => g.faculty_name), 'Barcha fakultetlar');

            const faculty = pick('faculty').value;
            const scoped = groups.filter(g => !faculty || g.faculty_name === faculty);
            setOptions(pick('specialty'), scoped.map(g => g.specialty_name), 'Barcha yo\'nalishlar');

            const specialty = pick('specialty').value;
            const scoped2 = scoped.filter(g => !specialty || g.specialty_name === specialty);
            setOptions(pick('course'), scoped2.map(g => g.level_code), 'Kurs');
        }

        // Filtrlar: tugma yo'q — tanlangan/yozilgan zahoti chiqadi.
        Object.entries(panels).forEach(([key, panel]) => {
            const box = panel.root.querySelector('.sd-filters');

            box.addEventListener('change', event => {
                if (!event.target.matches('select')) return;
                if (event.target.dataset.f !== 'search') refreshOptions(panel);
                render();
            });

            box.addEventListener('input', event => {
                if (event.target.dataset.f !== 'search') return;
                renderPanel(key);
                if (key === 'right') renderTabs();
            });
        });

        $('rightRows').addEventListener('change', event => {
            const box = event.target.closest('input[type=checkbox]');
            if (!box) return;
            const id = Number(box.dataset.id);
            if (box.checked) picked.add(id); else picked.delete(id);
            render();
        });

        document.querySelectorAll('.sd-tab').forEach(tab => tab.addEventListener('click', () => {
            if (rightView === tab.dataset.view) return;
            rightView = tab.dataset.view;
            document.querySelectorAll('.sd-tab').forEach(t => t.classList.toggle('is-on', t === tab));
            renderPanel('right');
            renderTabs();
        }));

        $('saveSources').addEventListener('click', async () => {
            const button = $('saveSources');
            button.disabled = true;
            try {
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                    body: JSON.stringify({group_hemis_ids: [...picked]}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Saqlab bo\'lmadi.');
                groups = data.groups;
                picked = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
                render();
                alert(data.message);
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });

        Object.values(panels).forEach(refreshOptions);
        render();
    })();
    </script>
</x-app-layout>
