<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Guruh starostalari') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-4 lg:px-6">
            <div class="tsp-card">
                <div class="tsp-head">
                    <div>
                        <h3>Guruh starostalarini belgilash</h3>
                        <p>Har bir guruh yetakchisini (starosta) belgilang. Bir guruhda faqat bitta starosta bo'ladi — kerak bo'lsa istalgan vaqtda o'zgartirishingiz mumkin.</p>
                    </div>
                </div>
                <div id="tspAccordion" class="tsp-body">
                    <div class="tsp-loading">Yuklanmoqda...</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tsp-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,.06); }
        .tsp-head { padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
        .tsp-head h3 { margin:0 0 4px; font-size:17px; font-weight:800; color:#0f172a; }
        .tsp-head p { margin:0; font-size:13px; line-height:1.5; color:#64748b; }
        .tsp-body { padding:14px 16px 18px; }
        .tsp-loading, .tsp-empty { text-align:center; color:#64748b; padding:26px 0; font-size:14px; }
        .tsp-group { border:1px solid #e2e8f0; border-radius:12px; margin-bottom:10px; overflow:hidden; }
        .tsp-group-head { width:100%; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 15px; background:#f8fafc; border:0; cursor:pointer; text-align:left; }
        .tsp-group-head:hover { background:#f1f5f9; }
        .tsp-group-name { font-size:14.5px; font-weight:700; color:#0f172a; }
        .tsp-group-meta { display:flex; align-items:center; gap:10px; flex-shrink:0; }
        .tsp-badge { font-size:11.5px; font-weight:700; padding:4px 9px; border-radius:999px; white-space:nowrap; }
        .tsp-badge--ok { background:#dcfce7; color:#166534; }
        .tsp-badge--none { background:#fee2e2; color:#991b1b; }
        .tsp-chev { width:16px; height:16px; color:#94a3b8; transition:transform .18s; }
        .tsp-group.is-open .tsp-chev { transform:rotate(180deg); }
        .tsp-group-body { display:none; padding:10px 12px; border-top:1px solid #e2e8f0; }
        .tsp-group.is-open .tsp-group-body { display:block; }
        .tsp-search { width:100%; box-sizing:border-box; padding:8px 11px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; margin-bottom:8px; }
        .tsp-slist { max-height:320px; overflow-y:auto; }
        .tsp-student { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 6px; border-bottom:1px solid #f1f5f9; }
        .tsp-student:last-child { border-bottom:0; }
        .tsp-student-name { display:block; font-size:13.5px; font-weight:600; color:#1e293b; }
        .tsp-student-id { display:block; font-size:11.5px; color:#94a3b8; }
        .tsp-set { border:1px solid #2563eb; background:#fff; color:#2563eb; font-size:12.5px; font-weight:700; padding:6px 12px; border-radius:8px; cursor:pointer; white-space:nowrap; transition:.12s; }
        .tsp-set:hover { background:#2563eb; color:#fff; }
        .tsp-set[disabled] { opacity:.6; cursor:default; }
        .tsp-current { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; font-weight:700; color:#166534; background:#dcfce7; padding:6px 11px; border-radius:8px; white-space:nowrap; }
    </style>

    <script>
        (function () {
            var GROUPS_URL = "{{ route('teacher.starosta.groups') }}";
            var SET_URL = "{{ route('teacher.starosta.set') }}";
            var CSRF = "{{ csrf_token() }}";
            var groupsData = [];

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function badge(g) {
                if (g.starosta) return '<span class="tsp-badge tsp-badge--ok">Starosta: ' + esc(g.starosta.name) + '</span>';
                if (!g.student_count) return '<span class="tsp-badge tsp-badge--none">Talaba yo\'q</span>';
                return '<span class="tsp-badge tsp-badge--none">Belgilanmagan</span>';
            }

            function studentRow(gi, s) {
                var right = s.is_starosta
                    ? '<span class="tsp-current">Starosta ✓</span>'
                    : '<button type="button" class="tsp-set" data-gi="' + gi + '" data-sid="' + s.id + '">Starosta qilish</button>';
                return '<div class="tsp-student" data-name="' + esc((s.name || '').toLowerCase()) + '">'
                    + '<span><span class="tsp-student-name">' + esc(s.name) + '</span>'
                    + (s.student_id_number ? '<span class="tsp-student-id">' + esc(s.student_id_number) + '</span>' : '')
                    + '</span>' + right + '</div>';
            }

            function render() {
                var box = document.getElementById('tspAccordion');
                if (!box) return;
                if (!groupsData.length) {
                    box.innerHTML = '<div class="tsp-empty">Sizga biriktirilgan faol guruh topilmadi.</div>';
                    return;
                }
                var html = '';
                groupsData.forEach(function (g, gi) {
                    var slist = g.students.length
                        ? g.students.map(function (s) { return studentRow(gi, s); }).join('')
                        : '<div class="tsp-empty">Bu guruhda faol talaba yo\'q.</div>';
                    var search = g.students.length > 6
                        ? '<input type="text" class="tsp-search" placeholder="Talabani qidirish..." data-gi="' + gi + '">'
                        : '';
                    html += '<div class="tsp-group" data-gi="' + gi + '">'
                        + '<button type="button" class="tsp-group-head">'
                        + '<span class="tsp-group-name">' + esc(g.name) + '</span>'
                        + '<span class="tsp-group-meta">' + badge(g)
                        + '<svg class="tsp-chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>'
                        + '</span></button>'
                        + '<div class="tsp-group-body">' + search + '<div class="tsp-slist">' + slist + '</div></div>'
                        + '</div>';
                });
                box.innerHTML = html;
            }

            function setStarosta(gi, sid, btn) {
                if (btn) { btn.disabled = true; btn.textContent = 'Saqlanmoqda...'; }
                fetch(SET_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ student_id: sid })
                }).then(function (r) {
                    if (!r.ok) throw new Error('xatolik');
                    return r.json();
                }).then(function () {
                    var g = groupsData[gi];
                    g.students.forEach(function (s) { s.is_starosta = (s.id === sid); });
                    var chosen = g.students.find(function (s) { return s.id === sid; });
                    g.starosta = chosen ? { id: chosen.id, name: chosen.name } : null;
                    render();
                    var el = document.querySelector('.tsp-group[data-gi="' + gi + '"]');
                    if (el) el.classList.add('is-open');
                }).catch(function () {
                    if (btn) { btn.disabled = false; btn.textContent = 'Starosta qilish'; }
                    alert('Saqlashda xatolik yuz berdi. Qayta urinib ko\'ring.');
                });
            }

            var acc = document.getElementById('tspAccordion');
            if (acc) {
                acc.addEventListener('click', function (e) {
                    var head = e.target.closest('.tsp-group-head');
                    if (head) {
                        var grp = head.parentElement;
                        var wasOpen = grp.classList.contains('is-open');
                        acc.querySelectorAll('.tsp-group.is-open').forEach(function (o) { o.classList.remove('is-open'); });
                        if (!wasOpen) grp.classList.add('is-open');
                        return;
                    }
                    var setBtn = e.target.closest('.tsp-set');
                    if (setBtn) {
                        setStarosta(parseInt(setBtn.dataset.gi, 10), parseInt(setBtn.dataset.sid, 10), setBtn);
                    }
                });
                acc.addEventListener('input', function (e) {
                    var inp = e.target.closest('.tsp-search');
                    if (!inp) return;
                    var q = inp.value.trim().toLowerCase();
                    var body = inp.closest('.tsp-group-body');
                    body.querySelectorAll('.tsp-student').forEach(function (row) {
                        row.style.display = (!q || (row.dataset.name || '').indexOf(q) !== -1) ? '' : 'none';
                    });
                });
            }

            fetch(GROUPS_URL, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) { groupsData = (res && res.groups) ? res.groups : []; render(); })
                .catch(function () {
                    var box = document.getElementById('tspAccordion');
                    if (box) box.innerHTML = '<div class="tsp-empty">Ma\'lumotni yuklashda xatolik.</div>';
                });
        })();
    </script>
</x-teacher-app-layout>
