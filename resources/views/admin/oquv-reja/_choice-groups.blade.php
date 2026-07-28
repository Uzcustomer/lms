{{-- Tanlov guruhlari muharriri: namunaviy rejadagi muqobil fanlarni ishchi
     rejadagi mos fan(lar) bilan qo'lda bog'lash. $reference talab qilinadi. --}}
<div class="bg-white shadow-sm rounded-lg mb-6" id="cg-panel">
    <button type="button" id="cg-toggle"
            class="w-full flex items-center justify-between px-4 py-3 text-left">
        <span class="font-medium text-gray-800">
            Tanlov guruhlari
            <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800" id="cg-count">—</span>
        </span>
        <span class="text-sm text-blue-600" id="cg-toggle-label">Ochish</span>
    </button>

    <div class="hidden border-t border-gray-100 p-4" id="cg-body">
        <p class="text-sm text-gray-500 mb-4">
            Namunaviy rejada tanlov fanlari muqobillar ro'yxati bo'lib keladi
            ("O'zbek/rus tili YOKI Tibbiyotda xorijiy til"), ishchi rejada esa ko'pincha
            boshqacha yoziladi — masalan bitta qatorda "A / B". Nom bo'yicha avtomatik
            moslash bunda ishlamaydi, shuning uchun muqobillarni va ularga mos ishchi
            fan(lar)ni shu yerda qo'lda bog'lang.
            <strong class="text-gray-700">Kamida bitta guruh saqlansa, avtomatik aniqlash
            butunlay o'chadi</strong> — ro'yxat to'liq bo'lishi kerak.
        </p>

        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" id="cg-add"
                    class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                + Guruh qo'shish
            </button>
            <button type="button" id="cg-suggest"
                    class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200"
                    title="Namunaviy rejadagi 'YOKI' bloklaridan boshlang'ich ro'yxat tuzadi">
                Avtomatik topilganlarni qo'shish
            </button>
            <button type="button" id="cg-save"
                    class="px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                Saqlash va qayta solishtirish
            </button>
            <span class="text-sm self-center" id="cg-status"></span>
        </div>

        <div id="cg-list" class="space-y-3"></div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const panel = document.getElementById('cg-panel');
    if (!panel) return;

    const referenceId = @json($reference->id);
    const loadUrl = @json(route('admin.oquv-reja.choice-groups'));
    const saveUrl = @json(route('admin.oquv-reja.choice-groups.save'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const body = document.getElementById('cg-body');
    const list = document.getElementById('cg-list');
    const statusEl = document.getElementById('cg-status');
    const countEl = document.getElementById('cg-count');

    let refSubjects = [];   // [{name, block, hours, credit, semester}]
    let workSubjects = [];  // [{name, block, semestrlar}]
    let suggestions = [];
    let groups = [];        // [{label, ref_names, work_names, norm_name, note}]
    let loaded = false;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    function setStatus(text, cls) {
        statusEl.className = 'text-sm self-center ' + (cls || 'text-gray-500');
        statusEl.textContent = text;
    }

    // Qidiruv uchun kalit: kichik harf, apostrof va ortiqcha bo'shliqlarsiz.
    // Shunda "o'zbek" ham, "ozbek" ham bir xil topadi.
    const searchKey = (s) => String(s ?? '').toLowerCase()
        .replace(/['‘’ʻʼ`´′]/g, '').replace(/\s+/g, ' ').trim();

    /**
     * Qidiruvli belgilash ro'yxati: <select multiple> o'rniga (Ctrl bilan
     * tanlash uzun ro'yxatda deyarli imkonsiz edi). Tanlanganlar tepada chip
     * ko'rinishida turadi, ro'yxat esa qidiruv bo'yicha filtrlanadi.
     */
    function pickList(options, selected, field, index) {
        const chosen = new Set(selected.map(searchKey));
        const known = new Set(options.map((o) => searchKey(o.name)));

        // Saqlangan, ammo rejada topilmagan nomlar ham ro'yxatda ko'rinsin
        const extras = selected.filter((s) => !known.has(searchKey(s)))
            .map((s) => ({name: s, hint: 'rejada topilmadi', missing: true}));

        const rows = extras.concat(options).map((o) => `
            <label class="flex items-start gap-2 px-2 py-1 text-sm hover:bg-blue-50 cursor-pointer cg-opt"
                   data-text="${esc(searchKey(o.name + ' ' + (o.hint || '')))}">
                <input type="checkbox" value="${esc(o.name)}" data-field="${field}" data-index="${index}"
                       class="mt-0.5 rounded border-gray-300"${chosen.has(searchKey(o.name)) ? ' checked' : ''}>
                <span class="${o.missing ? 'text-red-600' : 'text-gray-700'}">${esc(o.name)}</span>
                ${o.hint ? `<span class="text-gray-400 text-xs ml-auto whitespace-nowrap">${esc(o.hint)}</span>` : ''}
            </label>`).join('');

        return `
            <div data-chips="${field}-${index}" class="mb-1">${chipsHtml(selected, field, index)}</div>
            <input type="text" class="cg-search w-full border-gray-300 rounded-md text-sm mb-1"
                   data-target="${field}-${index}" placeholder="Qidirish...">
            <div class="border border-gray-200 rounded-md max-h-48 overflow-y-auto divide-y divide-gray-50"
                 data-options="${field}-${index}">${rows}</div>
            <p class="text-xs text-gray-400 mt-1" data-empty="${field}-${index}" hidden>Topilmadi</p>`;
    }

    /** Tanlanganlar ro'yxati — uzun ro'yxatda pastga tushib ketmasligi uchun tepada. */
    function chipsHtml(selected, field, index) {
        if (!selected.length) {
            return '<span class="text-xs text-gray-400">Tanlanmagan</span>';
        }
        return selected.map((s) => `
            <span class="inline-flex items-center gap-1 mr-1 mb-1 px-2 py-0.5 rounded text-xs
                         bg-blue-50 text-blue-800 border border-blue-100">
                ${esc(s)}
                <button type="button" class="text-blue-400 hover:text-red-600"
                        data-unpick="${esc(s)}" data-field="${field}" data-index="${index}">&times;</button>
            </span>`).join('');
    }

    function render() {
        countEl.textContent = groups.length ? `${groups.length} ta` : 'yo\'q';

        if (!groups.length) {
            list.innerHTML = '<p class="text-sm text-gray-400">Guruh yo\'q — avtomatik aniqlash amalda.</p>';
            return;
        }

        const refOpts = refSubjects.map((s) => ({
            name: s.name,
            hint: [s.block, s.hours != null ? s.hours + ' soat' : null].filter(Boolean).join(', '),
        }));
        const workOpts = workSubjects.map((s) => ({
            name: s.name,
            hint: (s.semestrlar || []).length ? s.semestrlar.join(',') + '-sem' : (s.block || ''),
        }));

        list.innerHTML = groups.map((g, i) => `
            <div class="border border-gray-200 rounded-md p-3" data-group="${i}">
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" value="${esc(g.label || '')}" data-field="label" data-index="${i}"
                           placeholder="Guruh nomi (masalan: 2.02 Chet tili)"
                           class="flex-1 border-gray-300 rounded-md text-sm">
                    <button type="button" data-remove="${i}"
                            class="px-2 py-1 text-sm text-red-600 hover:bg-red-50 rounded">O'chirish</button>
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Namunaviy rejadagi muqobillar
                        </label>
                        ${pickList(refOpts, g.ref_names || [], 'ref_names', i)}
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Ishchi rejadagi mos fan(lar)
                        </label>
                        ${pickList(workOpts, g.work_names || [], 'work_names', i)}
                    </div>
                </div>
                <div class="mt-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Norma soat/kredit qaysi muqobildan olinsin
                    </label>
                    <div data-norm="${i}" class="md:w-1/2">${normHtml(g, i)}</div>
                </div>
            </div>
        `).join('');
    }

    /** Norma tanlash selecti — muqobillar o'zgarganda alohida yangilanadi. */
    function normHtml(g, i) {
        const opts = (g.ref_names || []).map((n) => {
            const s = refSubjects.find((r) => searchKey(r.name) === searchKey(n));
            const hint = s && s.hours != null ? ` (${s.hours} soat)` : '';
            const sel = searchKey(g.norm_name || '') === searchKey(n) ? ' selected' : '';
            return `<option value="${esc(n)}"${sel}>${esc(n)}${esc(hint)}</option>`;
        }).join('');
        return `<select data-field="norm_name" data-index="${i}"
                        class="w-full border-gray-300 rounded-md text-sm">
                    <option value="">— birinchi soatli muqobil —</option>${opts}
                </select>`;
    }

    /**
     * Norma sifatida tanlangan muqobil ro'yxatdan chiqarilgan bo'lsa — normani
     * bo'shatadi. Aks holda select sukut qiymatini ko'rsatib turgani holda
     * groups[i].norm_name da guruhga aloqasi qolmagan fan nomi saqlanib qolar
     * va solishtirishda soat/kredit o'sha fandan olinardi.
     */
    function dropStaleNorm(g) {
        if (g.norm_name && !(g.ref_names || []).some((n) => searchKey(n) === searchKey(g.norm_name))) {
            g.norm_name = '';
        }
        return g;
    }

    /** Guruhning chip va norma qismlarini butun panelni qayta chizmasdan yangilash. */
    function refreshGroup(i, field) {
        const chips = list.querySelector(`[data-chips="${field}-${i}"]`);
        if (chips) chips.innerHTML = chipsHtml(groups[i][field] || [], field, i);
        if (field !== 'ref_names') return;

        // Muqobillar o'zgardi — norma yaroqliligini shu yerda tekshiramiz,
        // shunda katakcha ham, chipdagi ×, ham kelajakdagi boshqa yo'llar
        // bir xil ishlaydi.
        dropStaleNorm(groups[i]);
        const norm = list.querySelector(`[data-norm="${i}"]`);
        if (norm) norm.innerHTML = normHtml(groups[i], i);
    }

    // Tahrirlar to'g'ridan-to'g'ri groups massiviga yoziladi
    list.addEventListener('change', function (e) {
        const el = e.target;
        const i = parseInt(el.dataset.index, 10);
        const field = el.dataset.field;
        if (Number.isNaN(i) || !field) return;

        if (el.type === 'checkbox') {
            const current = groups[i][field] || [];
            groups[i][field] = el.checked
                ? current.concat([el.value])
                : current.filter((n) => searchKey(n) !== searchKey(el.value));
            refreshGroup(i, field);
        } else {
            groups[i][field] = el.value;
        }
    });

    // Qidiruv: ro'yxatni joyida filtrlaydi (qayta chizilmaydi — fokus saqlanadi)
    list.addEventListener('input', function (e) {
        const target = e.target.dataset.target;
        if (!target) return;
        const box = list.querySelector(`[data-options="${target}"]`);
        if (!box) return;

        const q = searchKey(e.target.value);
        let shown = 0;
        box.querySelectorAll('.cg-opt').forEach((row) => {
            const hit = !q || row.dataset.text.includes(q);
            row.hidden = !hit;
            if (hit) shown++;
        });
        const empty = list.querySelector(`[data-empty="${target}"]`);
        if (empty) empty.hidden = shown > 0;
    });

    list.addEventListener('click', function (e) {
        // Chipdagi × — tanlovni bekor qilish
        const unpick = e.target.dataset.unpick;
        if (unpick !== undefined) {
            const i = parseInt(e.target.dataset.index, 10);
            const field = e.target.dataset.field;
            groups[i][field] = (groups[i][field] || []).filter((n) => searchKey(n) !== searchKey(unpick));
            const box = list.querySelector(`[data-options="${field}-${i}"]`);
            box?.querySelectorAll('input[type=checkbox]').forEach((cb) => {
                if (searchKey(cb.value) === searchKey(unpick)) cb.checked = false;
            });
            refreshGroup(i, field);
            return;
        }

        const idx = e.target.dataset.remove;
        if (idx === undefined) return;
        groups.splice(parseInt(idx, 10), 1);
        render();
    });

    document.getElementById('cg-add').addEventListener('click', function () {
        groups.push({label: '', ref_names: [], work_names: [], norm_name: '', note: ''});
        render();
    });

    document.getElementById('cg-suggest').addEventListener('click', function () {
        // Takrorlanmasligi uchun: birinchi muqobili allaqachon ishlatilgan taklif tashlanadi
        const used = new Set(groups.flatMap((g) => (g.ref_names || []).map((n) => n.toLowerCase())));
        let added = 0;
        suggestions.forEach((s) => {
            if ((s.ref_names || []).some((n) => used.has(n.toLowerCase()))) return;
            groups.push({
                label: s.label, ref_names: s.ref_names, work_names: [],
                norm_name: s.norm_name || '', note: '',
            });
            (s.ref_names || []).forEach((n) => used.add(n.toLowerCase()));
            added++;
        });
        render();
        setStatus(added ? `${added} ta taklif qo'shildi — ishchi fanni ko'rsating` : 'Yangi taklif yo\'q',
            added ? 'text-gray-600' : 'text-gray-400');
    });

    document.getElementById('cg-save').addEventListener('click', async function () {
        groups.forEach(dropStaleNorm); // yuborishdan oldin oxirgi tekshiruv
        const invalid = groups.findIndex((g) => !(g.ref_names || []).length);
        if (invalid >= 0) {
            setStatus(`${invalid + 1}-guruhda namunaviy muqobil tanlanmagan`, 'text-red-600');
            return;
        }
        setStatus('Saqlanmoqda...', 'text-gray-500');
        try {
            const r = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({reference_id: referenceId, groups: groups}),
            });
            if (!r.ok) throw new Error((await r.json()).message || 'Xatolik');
            setStatus('Saqlandi, sahifa yangilanmoqda...', 'text-green-600');
            window.location.reload();
        } catch (err) {
            setStatus('Xatolik: ' + err.message, 'text-red-600');
        }
    });

    async function load() {
        setStatus('Yuklanmoqda...', 'text-gray-500');
        try {
            const r = await fetch(loadUrl + '?reference_id=' + referenceId, {headers: {'Accept': 'application/json'}});
            const data = await r.json();
            refSubjects = data.ref_subjects || [];
            workSubjects = data.work_subjects || [];
            suggestions = data.suggestions || [];
            groups = (data.groups || []).map((g) => ({
                label: g.label || '', ref_names: g.ref_names || [], work_names: g.work_names || [],
                norm_name: g.norm_name || '', note: g.note || '',
            }));
            loaded = true;
            setStatus('');
            render();
        } catch (err) {
            setStatus('Yuklashda xatolik: ' + err.message, 'text-red-600');
        }
    }

    document.getElementById('cg-toggle').addEventListener('click', function () {
        const hidden = body.classList.toggle('hidden');
        document.getElementById('cg-toggle-label').textContent = hidden ? 'Ochish' : 'Yopish';
        if (!hidden && !loaded) load();
    });
})();
</script>
@endpush
