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

    // Ko'p tanlovli select: tanlanganlar values massivida
    function multiSelect(options, selected, dataAttr, index, size) {
        const chosen = new Set(selected.map((s) => s.toLowerCase()));
        const opts = options.map((o) => {
            const label = o.hint ? `${o.name} — ${o.hint}` : o.name;
            const sel = chosen.has(o.name.toLowerCase()) ? ' selected' : '';
            return `<option value="${esc(o.name)}"${sel}>${esc(label)}</option>`;
        }).join('');
        // Saqlangan, ammo ro'yxatda yo'q nomlar ham ko'rinishi kerak
        const known = new Set(options.map((o) => o.name.toLowerCase()));
        const extra = selected.filter((s) => !known.has(s.toLowerCase()))
            .map((s) => `<option value="${esc(s)}" selected>${esc(s)} (rejada topilmadi)</option>`).join('');
        return `<select multiple size="${size}" data-field="${dataAttr}" data-index="${index}"
                        class="w-full border-gray-300 rounded-md text-sm">${opts}${extra}</select>`;
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
                            Namunaviy rejadagi muqobillar (Ctrl bilan bir nechta)
                        </label>
                        ${multiSelect(refOpts, g.ref_names || [], 'ref_names', i, 6)}
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Ishchi rejadagi mos fan(lar)
                        </label>
                        ${multiSelect(workOpts, g.work_names || [], 'work_names', i, 6)}
                    </div>
                </div>
                <div class="mt-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Norma soat/kredit qaysi muqobildan olinsin
                    </label>
                    <select data-field="norm_name" data-index="${i}"
                            class="w-full md:w-1/2 border-gray-300 rounded-md text-sm">
                        <option value="">— birinchi soatli muqobil —</option>
                        ${(g.ref_names || []).map((n) => {
                            const s = refSubjects.find((r) => r.name.toLowerCase() === n.toLowerCase());
                            const hint = s && s.hours != null ? ` (${s.hours} soat)` : '';
                            const sel = (g.norm_name || '').toLowerCase() === n.toLowerCase() ? ' selected' : '';
                            return `<option value="${esc(n)}"${sel}>${esc(n)}${esc(hint)}</option>`;
                        }).join('')}
                    </select>
                </div>
            </div>
        `).join('');
    }

    // Tahrirlar to'g'ridan-to'g'ri groups massiviga yoziladi
    list.addEventListener('change', function (e) {
        const el = e.target;
        const i = parseInt(el.dataset.index, 10);
        const field = el.dataset.field;
        if (Number.isNaN(i) || !field) return;

        if (el.multiple) {
            groups[i][field] = Array.from(el.selectedOptions).map((o) => o.value);
            if (field === 'ref_names') render(); // norma ro'yxati yangilanadi
        } else {
            groups[i][field] = el.value;
        }
    });

    list.addEventListener('click', function (e) {
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
