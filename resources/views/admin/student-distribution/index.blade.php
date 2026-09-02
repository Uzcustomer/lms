<x-app-layout>
<style>
    .sd {
        --navy:#0f2748; --navy-soft:#1b3a63; --gold:#c9a227;
        --ink:#17233a; --ink-soft:#4d6180; --muted:#8798b1;
        --line:#dde5ef; --line-soft:#eef2f8;
        --ok:#0f7a52; --ok-bg:#e9f7f0; --bad:#b3261e; --bad-bg:#fdeceb;
        color:var(--ink); font-family:'Roboto',system-ui,-apple-system,'Segoe UI',sans-serif;
    }
    .sd-head {
        padding:22px 26px; border:1px solid var(--line); border-left:3px solid var(--gold);
        border-radius:6px; background:linear-gradient(180deg,#fbfcfe,#f5f8fc);
    }
    .sd-head h1 { margin:0; color:var(--navy); font-size:22px; font-weight:800; letter-spacing:-.01em; }
    .sd-head p { margin:5px 0 0; color:var(--ink-soft); font-size:13px; }

    /* ---- Filtrlar ---- */
    .sd-panel { padding:16px 20px; border:1px solid var(--line); border-radius:6px; background:#fff; }
    .sd-filters { display:grid; grid-template-columns:2fr 2fr 1fr auto auto; gap:11px; align-items:end; }
    .sd-filters label { display:block; margin-bottom:6px; color:var(--ink-soft); font-size:11px; font-weight:600; letter-spacing:.04em; }
    .sd-filters select, .sd-search {
        width:100%; height:38px; padding:0 11px; border:1px solid #c4d0e0; border-radius:5px;
        background:#fcfdff; color:var(--ink); font-family:inherit; font-size:13px; outline:none;
    }
    .sd-filters select:focus, .sd-search:focus { border-color:var(--navy-soft); box-shadow:0 0 0 3px rgba(27,58,99,.1); }
    .sd-btn {
        height:38px; padding:0 17px; border:0; border-radius:5px; background:var(--navy); color:#fff;
        font-family:inherit; font-size:13px; font-weight:500; letter-spacing:.03em; cursor:pointer;
        transition:background .15s;
    }
    .sd-btn:hover { background:var(--navy-soft); }
    .sd-btn:disabled { background:#9aa9bd; cursor:not-allowed; }
    .sd-btn-light { border:1px solid #c4d0e0; background:#fff; color:var(--navy); }
    .sd-btn-light:hover { background:#f1f5fa; }

    /* ---- Ikki ustun ---- */
    .sd-cols { display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:start; }
    .sd-side { overflow:hidden; border:1px solid var(--line); border-radius:6px; background:#fff; }
    .sd-side-head {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:14px 18px; border-bottom:1px solid var(--line-soft);
        background:linear-gradient(180deg,#fbfcfe,#f5f8fc);
    }
    .sd-side.is-right .sd-side-head { background:linear-gradient(180deg,#fffcf7,#fdf7ec); }
    .sd-side-head h2 { margin:0; color:var(--navy); font-size:15px; font-weight:700; }
    .sd-side-head p { margin:3px 0 0; color:var(--muted); font-size:11.5px; }
    .sd-count {
        padding:4px 11px; border-radius:999px; background:#eef4fd; color:var(--navy);
        font-size:11px; font-weight:700; white-space:nowrap;
    }
    .sd-side.is-right .sd-count { background:#fdf3e0; color:#8a5a06; }

    /* ---- Tablar (o'ng tomon) ---- */
    .sd-tabs { display:flex; gap:2px; padding:9px 14px 0; border-bottom:1px solid var(--line-soft); background:#fdfeff; }
    .sd-tab {
        padding:8px 14px; border:0; border-bottom:2px solid transparent; background:transparent;
        color:var(--muted); font-family:inherit; font-size:12px; font-weight:600; cursor:pointer;
    }
    .sd-tab:hover { color:var(--navy); }
    .sd-tab.is-on { border-color:var(--gold); color:var(--navy); }
    .sd-tab span {
        margin-left:5px; padding:1px 7px; border-radius:999px;
        background:#eef2f8; color:var(--ink-soft); font-size:10px; font-weight:700;
    }
    .sd-tab.is-on span { background:var(--navy); color:#fff; }

    /* ---- Ro'yxat ---- */
    .sd-rows { max-height:620px; overflow-y:auto; }
    .sd-row {
        display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:12px;
        padding:11px 18px; border-bottom:1px solid var(--line-soft); transition:background .12s;
    }
    .sd-row:last-child { border-bottom:0; }
    .sd-row:hover { background:#fafcfe; }
    .sd-row.is-picked { background:#fffbf2; }
    .sd-row input[type=checkbox] { width:16px; height:16px; accent-color:var(--navy); cursor:pointer; }
    .sd-name { color:var(--ink); font-size:13.5px; font-weight:600; }
    .sd-meta { margin-top:2px; color:var(--muted); font-size:11.5px; }
    .sd-num {
        padding:3px 10px; border-radius:4px; background:#f2f6fc; color:var(--navy);
        font-size:12px; font-weight:700; white-space:nowrap;
    }
    .sd-num em { font-style:normal; color:var(--muted); font-weight:500; }
    .sd-tag {
        margin-left:7px; padding:2px 8px; border-radius:999px;
        background:#fdf3e0; color:#8a5a06; font-size:10px; font-weight:700;
    }

    .sd-empty { padding:46px 20px; color:var(--muted); font-size:13px; text-align:center; }
    .sd-empty b { display:block; margin-bottom:5px; color:var(--ink-soft); font-size:14px; font-weight:700; }

    .sd-actions {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:13px 18px; border-top:1px solid var(--line-soft); background:#fbfcfe;
    }
    .sd-hint { color:var(--muted); font-size:12px; }
    .sd-hint b { color:var(--navy); font-weight:700; }

    @media (max-width:1100px) { .sd-cols { grid-template-columns:1fr; } }
    @media (max-width:760px) { .sd-filters { grid-template-columns:1fr; } }
</style>

    <div class="sd py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

            <div class="sd-head">
                <h1>Talabalarni taqsimlash</h1>
                <p>Chapda bo'sh joyli guruhlar to'ldiriladi, o'ngda talabalari taqsimlanadigan guruhlar belgilanadi.</p>
            </div>

            <div class="sd-panel">
                <div class="sd-filters">
                    <div>
                        <label for="fFaculty">Fakultet</label>
                        <select id="fFaculty">
                            <option value="">Barcha fakultetlar</option>
                            @foreach($faculties as $faculty)
                                <option value="{{ $faculty }}">{{ $faculty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fSpecialty">Yo'nalish</label>
                        <select id="fSpecialty"><option value="">Barcha yo'nalishlar</option></select>
                    </div>
                    <div>
                        <label for="fCourse">Kurs</label>
                        <select id="fCourse"><option value="">Barchasi</option></select>
                    </div>
                    <div>
                        <label for="fSearch">Qidirish</label>
                        <input class="sd-search" id="fSearch" type="search" placeholder="Guruh nomi" style="min-width:150px">
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="sd-btn sd-btn-light" id="fReset" type="button">Tozalash</button>
                        <button class="sd-btn sd-btn-light" id="fRefresh" type="button">Yangilash</button>
                    </div>
                </div>
            </div>

            <div class="sd-cols">
                {{-- CHAP: bo'sh guruhlarni to'ldirish --}}
                <section class="sd-side">
                    <div class="sd-side-head">
                        <div>
                            <h2>Bo'sh guruhlarni to'ldirish</h2>
                            <p>Talabalar shu guruhlarga ko'chiriladi</p>
                        </div>
                        <span class="sd-count" id="leftCount">0 ta</span>
                    </div>
                    <div class="sd-rows" id="leftRows"></div>
                </section>

                {{-- O'NG: taqsimlanadigan guruhlar --}}
                <section class="sd-side is-right">
                    <div class="sd-side-head">
                        <div>
                            <h2>Taqsimlanadigan guruhlar</h2>
                            <p>Talabalari boshqa guruhga ko'chiriladigan guruhlarni belgilang</p>
                        </div>
                        <span class="sd-count" id="rightCount">0 ta</span>
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
        const urls = {
            groups: @json(route('admin.student-distribution.groups')),
            saveSources: @json(route('admin.student-distribution.source-groups.store')),
        };

        let groups = @json($groupPayloads);
        let picked = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
        let rightView = 'all';

        const courseLabel = g => g.level_name || (g.level_code ? g.level_code + '-kurs' : '');

        function filtered() {
            const faculty = $('fFaculty').value;
            const specialty = $('fSpecialty').value;
            const course = $('fCourse').value;
            const search = $('fSearch').value.trim().toLowerCase();

            return groups.filter(g =>
                (!faculty || g.faculty_name === faculty) &&
                (!specialty || g.specialty_name === specialty) &&
                (!course || String(g.level_code) === course) &&
                (!search || String(g.group_name).toLowerCase().includes(search))
            );
        }

        function rowHtml(g, withCheckbox) {
            const isPicked = picked.has(g.group_hemis_id);
            const check = withCheckbox
                ? '<input type="checkbox" data-id="' + g.group_hemis_id + '"' + (isPicked ? ' checked' : '') + '>'
                : '<span style="width:16px"></span>';
            const tag = !withCheckbox && isPicked ? '<span class="sd-tag">Taqsimlanadi</span>' : '';

            return '<label class="sd-row' + (withCheckbox && isPicked ? ' is-picked' : '') + '">' +
                check +
                '<span><span class="sd-name">' + esc(g.group_name) + tag + '</span>' +
                '<span class="sd-meta">' + esc(g.faculty_name || '—') + ' · ' + esc(g.specialty_name || '—') +
                (courseLabel(g) ? ' · ' + esc(courseLabel(g)) : '') + '</span></span>' +
                '<span class="sd-num">' + g.student_count + ' <em>talaba</em></span>' +
                '</label>';
        }

        function render() {
            const list = filtered();

            // Chap tomon — barcha guruhlar, checkboxsiz
            $('leftRows').innerHTML = list.length
                ? list.map(g => rowHtml(g, false)).join('')
                : '<div class="sd-empty"><b>Guruh topilmadi</b>Tanlangan filtr bo\'yicha guruh yo\'q.</div>';
            $('leftCount').textContent = list.length + ' ta';

            // O'ng tomon — tab bo'yicha
            const rightList = rightView === 'picked'
                ? list.filter(g => picked.has(g.group_hemis_id))
                : list;

            $('rightRows').innerHTML = rightList.length
                ? rightList.map(g => rowHtml(g, true)).join('')
                : '<div class="sd-empty"><b>' + (rightView === 'picked' ? 'Belgilangan guruh yo\'q' : 'Guruh topilmadi') + '</b>' +
                  (rightView === 'picked' ? 'Guruhlarni belgilash uchun "Barcha guruhlar" tabiga o\'ting.' : 'Tanlangan filtr bo\'yicha guruh yo\'q.') + '</div>';

            $('rightCount').textContent = rightList.length + ' ta';
            $('tabAll').textContent = list.length;
            $('tabPicked').textContent = list.filter(g => picked.has(g.group_hemis_id)).length;
            $('pickedTotal').textContent = picked.size;
        }

        function setOptions(select, values, placeholder) {
            const current = select.value;
            select.innerHTML = '<option value="">' + placeholder + '</option>' +
                [...new Set(values.filter(Boolean))].sort().map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
            if ([...select.options].some(o => o.value === current)) select.value = current;
        }

        function refreshFilterOptions() {
            const faculty = $('fFaculty').value;
            const scoped = groups.filter(g => !faculty || g.faculty_name === faculty);
            setOptions($('fSpecialty'), scoped.map(g => g.specialty_name), 'Barcha yo\'nalishlar');

            const specialty = $('fSpecialty').value;
            const scoped2 = scoped.filter(g => !specialty || g.specialty_name === specialty);
            setOptions($('fCourse'), scoped2.map(g => g.level_code), 'Barchasi');
        }

        // Checkbox holatini o'zgartirish
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
            render();
        }));

        ['fFaculty','fSpecialty','fCourse'].forEach(id => $(id).addEventListener('change', () => {
            refreshFilterOptions();
            render();
        }));
        $('fSearch').addEventListener('input', render);

        $('fReset').addEventListener('click', () => {
            $('fFaculty').value = ''; $('fSpecialty').value = ''; $('fCourse').value = ''; $('fSearch').value = '';
            refreshFilterOptions();
            render();
        });

        $('fRefresh').addEventListener('click', async () => {
            const button = $('fRefresh');
            button.disabled = true;
            try {
                const response = await fetch(urls.groups, {headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Yuklab bo\'lmadi.');
                groups = data.groups;
                picked = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
                refreshFilterOptions();
                render();
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });

        $('saveSources').addEventListener('click', async () => {
            const button = $('saveSources');
            button.disabled = true;
            try {
                const response = await fetch(urls.saveSources, {
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

        refreshFilterOptions();
        render();
    })();
    </script>
</x-app-layout>
