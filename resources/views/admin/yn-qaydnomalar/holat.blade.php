<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Vedomostlar holati</h2>
    </x-slot>

    @if(isset($dekanFacultyIds) && count($dekanFacultyIds) === 1)
        <input type="hidden" id="dekan_faculty_id" value="{{ $dekanFacultyIds[0] }}">
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="vh-head">
                    <div>
                        <a href="{{ route('admin.vedomost-tekshirish.index') }}" class="vh-back">← Vedomost tekshirish</a>
                        <p class="vh-sub">Filtr bo'yicha guruh va fanlar: YN imtihonlari tugaganlarida vedomost topshirilganmi yoki topshirilishi kerakmi;
                            dekan tasdiqlaganlarining PDF i.</p>
                    </div>
                </div>

                @include('admin.vedomost_tekshirish.partials.filters')

                <div id="results-area" style="display:none;">
                    <div class="vh-bar">
                        {{-- Shakl (12-shakl, qo'shimcha, 12a, 12b) — har biri alohida qaydnoma --}}
                        <label class="vh-form-filter" for="vh-form">Shakl
                            <select id="vh-form" onchange="vhFormFilter(this.value)"></select>
                        </label>
                        <div class="vh-chips" id="vh-chips"></div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="result-table vh-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;">#</th>
                                    <th style="min-width:190px;">Guruh</th>
                                    <th style="min-width:200px;">Fan</th>
                                    <th style="width:120px;">YN sanasi</th>
                                    <th style="min-width:210px;">Amaliy o'qituvchi</th>
                                    <th style="min-width:170px;">Kafedra mudiri</th>
                                    <th style="min-width:170px;">Dekan</th>
                                    <th style="width:150px;">Holat</th>
                                    <th style="width:170px;text-align:right;">Amallar</th>
                                </tr>
                            </thead>
                            <tbody id="results-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="empty-state" style="padding:60px 20px;text-align:center;">
                    <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                    <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Guruh va fanlar vedomost holati bilan shu yerda ko'rinadi</p>
                </div>
                <div id="no-results" style="display:none;padding:40px 20px;text-align:center;">
                    <p style="color:#94a3b8;font-size:14px;">Mos ma'lumot topilmadi</p>
                </div>
            </div>
        </div>
    </div>

    <form id="vh-excel" method="POST" action="{{ route('admin.journal.export-yn-qaydnoma') }}" style="display:none;">@csrf</form>

    @include('admin.vedomost_tekshirish.partials.filters-js')

    <script>
        var vhItems = [], vhCanDelete = false, vhStatus = '', vhForm = '';
        // Shakllar tartibi (YnqForm)
        var VH_FORMS = ['12', '12q', '12a', '12aq', '12b', '12bq'];
        var VH_STATUS = {
            none:     ['Topshirilishi kerak', 'vh-badge--none'],
            partial:  ['Qisman topshirilgan', 'vh-badge--partial'],
            teacher:  ["Kafedra tasdig'i kutilmoqda", 'vh-badge--teacher'],
            head:     ["Dekan tasdig'i kutilmoqda", 'vh-badge--head'],
            rejected: ['Rad etilgan', 'vh-badge--rejected'],
            dean:     ['Topshirilgan', 'vh-badge--dean'],
            waiting:  ['YN hali tugamagan', 'vh-badge--waiting']
        };
        // Chiplar tartibi: avval topshirilishi kerak, so'ng jarayonda, topshirilgan
        var VH_ORDER = ['none', 'rejected', 'partial', 'teacher', 'head', 'dean', 'waiting'];

        function esc(s){ return $('<span>').text(s == null ? '' : s).html(); }
        // Atribut qiymati uchun — qo'shtirnoq ham (JSON data-* atributlarda)
        function escA(s){ return esc(s).replace(/"/g, '&quot;'); }

        function doSearch() {
            $('#empty-state, #no-results, #results-area').hide();
            $('#search-loading').css('display', 'flex');
            $('#btn-search').prop('disabled', true);
            $.ajax({
                url: '{{ route("admin.vedomost-tekshirish.holat") }}', type: 'GET', data: collectParams(), timeout: 90000,
                success: function (res) {
                    $('#search-loading').hide(); $('#btn-search').prop('disabled', false);
                    vhItems = (res && res.items) || []; vhCanDelete = !!(res && res.can_delete); vhStatus = ''; vhForm = '';
                    if (!vhItems.length) { $('#no-results').show(); return; }
                    vhRender();
                    $('#results-area').show();
                },
                error: function () {
                    $('#search-loading').hide(); $('#btn-search').prop('disabled', false);
                    $('#empty-state').show().find('p:first').text('Xatolik yuz berdi. Qayta urinib ko\'ring.');
                }
            });
        }

        function vhFilter(status) { vhStatus = vhStatus === status ? '' : status; vhRender(); }
        function vhFormFilter(form) { vhForm = form; vhStatus = ''; vhRender(); }

        function vhRender() {
            // Shakl filtri — topilgan shakllar soni bilan; holat chiplari tanlangan shakl bo'yicha
            var formCounts = {}, formLabels = {};
            vhItems.forEach(function (r) { formCounts[r.form] = (formCounts[r.form] || 0) + 1; formLabels[r.form] = r.form_label; });
            var opts = '<option value="">Barcha shakllar (' + vhItems.length + ')</option>';
            VH_FORMS.forEach(function (f) {
                if (formCounts[f]) opts += '<option value="' + f + '"' + (vhForm === f ? ' selected' : '') + '>' + esc(formLabels[f]) + ' (' + formCounts[f] + ')</option>';
            });
            $('#vh-form').html(opts);
            var base = vhItems.filter(function (r) { return !vhForm || r.form === vhForm; });

            var counts = {};
            base.forEach(function (r) { counts[r.status] = (counts[r.status] || 0) + 1; });
            var chips = '<button type="button" class="vh-chip' + (vhStatus === '' ? ' is-active' : '') + '" onclick="vhFilter(\'\')">Jami <b>' + base.length + '</b></button>';
            VH_ORDER.forEach(function (s) {
                if (!counts[s]) return;
                chips += '<button type="button" class="vh-chip ' + VH_STATUS[s][1] + (vhStatus === s ? ' is-active' : '') + '" onclick="vhFilter(\'' + s + '\')">'
                    + esc(VH_STATUS[s][0]) + ' <b>' + counts[s] + '</b></button>';
            });
            $('#vh-chips').html(chips);

            var html = '';
            base.filter(function (r) { return !vhStatus || r.status === vhStatus; }).forEach(function (r, i) {
                html += '<tr>'
                    + '<td class="td-num">' + (i + 1) + '</td>'
                    + '<td><b class="vh-group">' + esc(r.group) + '</b>'
                    + (r.member_names.length ? '<span class="vh-small vh-sub-groups">Kichik guruhlar: ' + esc(r.member_names.join(', ')) + '</span>' : '')
                    + '<span class="vh-small">' + esc([r.faculty, r.specialty, r.level].filter(Boolean).join(' · ')) + '</span></td>'
                    + '<td><span class="text-subject">' + esc(r.subject) + '</span>'
                    + (r.form_label ? ' <span class="vh-form' + (r.form === '12' ? ' vh-form--main' : '') + '">' + esc(r.form_label) + '</span>' : '')
                    + '<span class="vh-small">' + esc(r.semester) + '</span></td>'
                    + '<td class="vh-dates">' + (r.oski_date ? '<div><span>OSKI</span> ' + esc(r.oski_date) + '</div>' : '')
                    + (r.test_date ? '<div><span>Test</span> ' + esc(r.test_date) + '</div>' : '') + (!r.oski_date && !r.test_date ? '<span class="vh-muted">—</span>' : '') + '</td>'
                    + '<td>' + vhTeachers(r) + '</td>'
                    + '<td>' + vhPerson(r.head) + '</td>'
                    + '<td>' + (r.dean ? vhPerson(r.dean) : (r.rejection
                        ? '<div class="vh-person"><span class="vh-dot vh-dot--no">✕</span><span>' + esc(r.rejection.by) + '<span class="vh-small">rad etdi · ' + esc(r.rejection.at) + '</span></span></div>'
                          + '<div class="vh-reason">' + esc(r.rejection.reason) + '</div>'
                        : '<span class="vh-muted">—</span>')) + '</td>'
                    + '<td><span class="vh-badge ' + VH_STATUS[r.status][1] + '">' + esc(VH_STATUS[r.status][0]) + '</span></td>'
                    + '<td><div class="vh-actions">' + vhActions(r) + '</div></td>'
                    + '</tr>';
            });
            $('#results-body').html(html || '<tr><td colspan="9" class="vh-muted" style="text-align:center;padding:24px;">Bu holatda qaydnoma yo\'q</td></tr>');
        }

        function vhPerson(p) {
            return p ? '<div class="vh-person"><span class="vh-dot">✓</span><span>' + esc(p.name) + '<span class="vh-small">' + esc(p.at) + '</span></span></div>'
                     : '<span class="vh-muted">—</span>';
        }
        function vhTeachers(r) {
            var html = r.teachers.map(function (t) {
                return '<div class="vh-person"><span class="vh-dot">✓</span><span>' + esc(t.name) + '<span class="vh-small">topshirdi · ' + esc(t.at) + '</span></span></div>';
            }).join('');
            html += r.pending_teachers.map(function (n) {
                return '<div class="vh-person vh-person--wait"><span class="vh-dot vh-dot--wait">!</span><span>' + esc(n) + '</span></div>';
            }).join('');
            return html || '<span class="vh-muted">—</span>';
        }
        // Amal tugmalari belgilari (SVG) — har amal o'z rangi va belgisi bilan
        function vhIcon(d) {
            return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' + d + '"/></svg>';
        }
        var VH_ICO = {
            pdf: vhIcon('M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'),
            view: vhIcon('M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'),
            journal: vhIcon('M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'),
            excel: vhIcon('M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'),
            trash: vhIcon('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16')
        };
        function vhActions(r) {
            var a = '';
            if (r.pdf_url) {
                a += '<a class="vh-btn vh-btn--pdf" href="' + escA(r.pdf_url) + '" onclick="return vhPdf(this)">' + VH_ICO.pdf + '<span>PDF</span></a>'
                   + '<a class="vh-btn vh-btn--view" target="_blank" rel="noopener" href="' + escA(r.pdf_view_url) + '">' + VH_ICO.view + 'Ko\'rish</a>';
            } else if (r.view_url) {
                a += '<a class="vh-btn vh-btn--view" target="_blank" rel="noopener" href="' + escA(r.view_url) + '">' + VH_ICO.view + 'Ko\'rish</a>';
            } else if (r.journal_url) {
                a += '<a class="vh-btn vh-btn--journal" href="' + escA(r.journal_url) + '">' + VH_ICO.journal + 'Jurnal</a>';
            }
            if (r.excel) {
                a += '<button type="button" class="vh-btn vh-btn--excel" data-params="' + escA(JSON.stringify(r.excel)) + '" onclick="vhExcel(this)">' + VH_ICO.excel + 'Excel</button>';
            }
            if (vhCanDelete && r.delete_key) {
                a += '<button type="button" class="vh-btn vh-btn--del" data-key="' + escA(JSON.stringify(r.delete_key)) + '" onclick="vhDelete(this)">' + VH_ICO.trash + 'O\'chirish</button>';
            }
            return a;
        }
        // PDF tayyorlanishi bir necha soniya oladi — tugma holati (belgi qoladi, matn almashadi)
        function vhPdf(a) {
            if (a.classList.contains('is-busy')) return false;
            var label = a.querySelector('span');
            a.classList.add('is-busy'); label.textContent = 'Tayyorlanmoqda…';
            setTimeout(function () { a.classList.remove('is-busy'); label.textContent = 'PDF'; }, 15000);
            return true;
        }
        function vhExcel(btn) {
            var form = document.getElementById('vh-excel');
            $(form).find('input[data-p]').remove();
            $.each(JSON.parse(btn.getAttribute('data-params')), function (k, v) {
                $('<input type="hidden" data-p="1">').attr('name', k).val(v).appendTo(form);
            });
            form.submit();
        }
        // Superadmin: qaydnomani butunlay o'chirish — o'qituvchi qaytadan shakllantiradi
        function vhDelete(btn) {
            if (!confirm('Qaydnoma butunlay o\'chirilsinmi? Tasdiqlar, QR kodlar va fayllar o\'chadi; o\'qituvchi qaytadan shakllantiradi.')) return;
            btn.disabled = true;
            $.ajax({
                url: '{{ route("admin.journal.delete-yn-qaydnoma") }}', type: 'POST',
                data: $.extend({ _token: '{{ csrf_token() }}' }, JSON.parse(btn.getAttribute('data-key'))),
                headers: { 'Accept': 'application/json' },
                success: function () { doSearch(); },
                error: function () { btn.disabled = false; alert('O\'chirib bo\'lmadi.'); }
            });
        }
    </script>

    @include('admin.vedomost_tekshirish.partials.styles')
    <style>
        /* Qaydnoma shakli (12-shakl qo'shimchasi, 12a, …) — alohida qaydnoma */
        .vh-form { display:inline-block; padding:1px 8px; border-radius:6px; font-size:11.5px; font-weight:700; white-space:nowrap;
            color:#6d28d9; background:#f5f3ff; border:1px solid #ddd6fe; }
        .vh-form--main { color:#334155; background:#f1f5f9; border-color:#e2e8f0; }
        .vh-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 20px; border-bottom:1px solid #e2e8f0; background:#fff; }
        .vh-back { font-size:12.5px; color:#2b5ea7; text-decoration:none; font-weight:600; }
        .vh-back:hover { text-decoration:underline; }
        .vh-sub { margin:4px 0 0; font-size:13px; color:#64748b; max-width:90ch; }
        .vh-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px 16px; padding:10px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
        .vh-form-filter { display:inline-flex; align-items:center; gap:8px; font-size:12.5px; font-weight:600; color:#475569; }
        .vh-form-filter select { height:32px; padding:0 28px 0 10px; border:1px solid #cbd5e1; border-radius:8px; background-color:#fff; font-size:13px; color:#0f172a; }
        .vh-form-filter select:focus-visible { outline:2px solid #2563eb; outline-offset:1px; }
        .vh-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .vh-chip { display:inline-flex; align-items:center; gap:6px; height:30px; padding:0 12px; border-radius:999px; border:1px solid #cbd5e1; background:#fff; color:#334155; font-size:12.5px; font-weight:600; cursor:pointer; }
        .vh-chip b { font-variant-numeric:tabular-nums; }
        .vh-chip.is-active { outline:2px solid #2b5ea7; outline-offset:1px; }
        .vh-table td { vertical-align:top; }
        .vh-group { font-size:13px; color:#0f172a; }
        .vh-small { display:block; font-size:11.5px; color:#64748b; margin-top:2px; line-height:1.35; }
        .vh-sub-groups { color:#b45309; }
        .vh-muted { color:#94a3b8; }
        .vh-dates div { white-space:nowrap; font-variant-numeric:tabular-nums; font-size:12.5px; }
        .vh-dates span { display:inline-block; width:34px; color:#64748b; font-size:11px; }
        .vh-person { display:flex; gap:7px; align-items:flex-start; font-size:12.5px; font-weight:600; color:#0f172a; margin-bottom:6px; line-height:1.35; }
        .vh-person--wait { color:#92400e; font-weight:500; }
        .vh-dot { flex-shrink:0; width:18px; height:18px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; background:#dcfce7; color:#15803d; margin-top:1px; }
        .vh-dot--wait { background:#fef3c7; color:#b45309; }
        .vh-dot--no { background:#faf5ff; color:#7e22ce; }
        .vh-reason { font-size:12px; color:#7e22ce; background:#faf5ff; border-radius:6px; padding:4px 8px; }
        .vh-badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; border:1px solid transparent; white-space:nowrap; }
        .vh-badge--none { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
        .vh-badge--partial { background:#fffbeb; color:#b45309; border-color:#fde68a; border-style:dashed; }
        .vh-badge--teacher { background:#fffbeb; color:#b45309; border-color:#fde68a; }
        .vh-badge--head { background:#f0f9ff; color:#0369a1; border-color:#bae6fd; }
        .vh-badge--rejected { background:#faf5ff; color:#7e22ce; border-color:#e9d5ff; }
        .vh-badge--dean { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
        .vh-badge--waiting { background:#f1f5f9; color:#64748b; border-color:#e2e8f0; }
        .vh-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; align-items:center; gap:8px; }
        /* Amal tugmalari — kattaroq, har biri o'z rangi va belgisi bilan */
        .vh-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; height:38px; padding:0 14px; border-radius:9px; border:1px solid #cbd5e1;
            background:#fff; color:#334155 !important; font-size:13px; font-weight:700; text-decoration:none !important; cursor:pointer; white-space:nowrap;
            transition:background-color .15s, border-color .15s, transform .05s; }
        .vh-btn:hover { background:#f1f5f9; border-color:#94a3b8; }
        .vh-btn:active { transform:translateY(1px); }
        .vh-btn svg { width:16px; height:16px; flex-shrink:0; }
        .vh-btn:focus-visible, .vh-chip:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }
        .vh-btn--pdf { background:#b91c1c; border-color:#b91c1c; color:#fff !important; box-shadow:0 2px 6px rgba(185,28,28,.28); }
        .vh-btn--pdf:hover { background:#991b1b; border-color:#991b1b; }
        .vh-btn--pdf.is-busy { background:#94a3b8; border-color:#94a3b8; pointer-events:none; box-shadow:none; }
        .vh-btn--view { color:#1d4ed8 !important; background:#eff6ff; border-color:#bfdbfe; }
        .vh-btn--view:hover { background:#dbeafe; border-color:#93c5fd; }
        .vh-btn--excel { color:#15803d !important; background:#f0fdf4; border-color:#86efac; }
        .vh-btn--excel:hover { background:#dcfce7; border-color:#4ade80; }
        .vh-btn--del { color:#b91c1c !important; border-color:#fca5a5; }
        .vh-btn--del:hover { background:#fef2f2; border-color:#f87171; }
        .vh-btn--del:disabled { opacity:.6; }
    </style>
</x-app-layout>
