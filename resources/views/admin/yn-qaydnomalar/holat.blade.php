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
        var vhItems = [], vhCanDelete = false, vhStatus = '';
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

        function doSearch() {
            $('#empty-state, #no-results, #results-area').hide();
            $('#search-loading').css('display', 'flex');
            $('#btn-search').prop('disabled', true);
            $.ajax({
                url: '{{ route("admin.vedomost-tekshirish.holat") }}', type: 'GET', data: collectParams(), timeout: 90000,
                success: function (res) {
                    $('#search-loading').hide(); $('#btn-search').prop('disabled', false);
                    vhItems = (res && res.items) || []; vhCanDelete = !!(res && res.can_delete); vhStatus = '';
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

        function vhRender() {
            var counts = {};
            vhItems.forEach(function (r) { counts[r.status] = (counts[r.status] || 0) + 1; });
            var chips = '<button type="button" class="vh-chip' + (vhStatus === '' ? ' is-active' : '') + '" onclick="vhFilter(\'\')">Jami <b>' + vhItems.length + '</b></button>';
            VH_ORDER.forEach(function (s) {
                if (!counts[s]) return;
                chips += '<button type="button" class="vh-chip ' + VH_STATUS[s][1] + (vhStatus === s ? ' is-active' : '') + '" onclick="vhFilter(\'' + s + '\')">'
                    + esc(VH_STATUS[s][0]) + ' <b>' + counts[s] + '</b></button>';
            });
            $('#vh-chips').html(chips);

            var html = '';
            vhItems.filter(function (r) { return !vhStatus || r.status === vhStatus; }).forEach(function (r, i) {
                html += '<tr>'
                    + '<td class="td-num">' + (i + 1) + '</td>'
                    + '<td><b class="vh-group">' + esc(r.group) + '</b>'
                    + (r.member_names.length ? '<span class="vh-small vh-sub-groups">Kichik guruhlar: ' + esc(r.member_names.join(', ')) + '</span>' : '')
                    + '<span class="vh-small">' + esc([r.faculty, r.specialty, r.level].filter(Boolean).join(' · ')) + '</span></td>'
                    + '<td><span class="text-subject">' + esc(r.subject) + '</span><span class="vh-small">' + esc(r.semester) + '</span></td>'
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
        function vhActions(r) {
            var a = '';
            if (r.pdf_url) {
                a += '<a class="vh-btn vh-btn--pdf" href="' + esc(r.pdf_url) + '" onclick="return vhPdf(this)">PDF</a>'
                   + '<a class="vh-btn" target="_blank" rel="noopener" href="' + esc(r.pdf_view_url) + '">Ko\'rish</a>';
            } else if (r.view_url) {
                a += '<a class="vh-btn" target="_blank" rel="noopener" href="' + esc(r.view_url) + '">Ko\'rish</a>';
            } else if (r.journal_url) {
                a += '<a class="vh-btn" href="' + esc(r.journal_url) + '">Jurnal</a>';
            }
            if (r.excel) {
                a += '<button type="button" class="vh-btn vh-btn--excel" data-params="' + esc(JSON.stringify(r.excel)) + '" onclick="vhExcel(this)">Excel</button>';
            }
            if (vhCanDelete && r.delete_key) {
                a += '<button type="button" class="vh-btn vh-btn--del" data-key="' + esc(JSON.stringify(r.delete_key)) + '" onclick="vhDelete(this)">O\'chirish</button>';
            }
            return a;
        }
        // PDF tayyorlanishi bir necha soniya oladi — tugma holati
        function vhPdf(a) {
            if (a.classList.contains('is-busy')) return false;
            a.classList.add('is-busy'); a.textContent = 'Tayyorlanmoqda…';
            setTimeout(function () { a.classList.remove('is-busy'); a.textContent = 'PDF'; }, 15000);
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
        .vh-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 20px; border-bottom:1px solid #e2e8f0; background:#fff; }
        .vh-back { font-size:12.5px; color:#2b5ea7; text-decoration:none; font-weight:600; }
        .vh-back:hover { text-decoration:underline; }
        .vh-sub { margin:4px 0 0; font-size:13px; color:#64748b; max-width:90ch; }
        .vh-bar { padding:10px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
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
        .vh-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; }
        .vh-btn { display:inline-flex; align-items:center; height:30px; padding:0 12px; border-radius:7px; border:1px solid #cbd5e1; background:#fff; color:#1e3a8a !important; font-size:12px; font-weight:700; text-decoration:none !important; cursor:pointer; white-space:nowrap; }
        .vh-btn:hover { background:#f1f5f9; }
        .vh-btn:focus-visible, .vh-chip:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }
        .vh-btn--pdf { background:#b91c1c; border-color:#b91c1c; color:#fff !important; }
        .vh-btn--pdf:hover { background:#991b1b; }
        .vh-btn--pdf.is-busy { background:#94a3b8; border-color:#94a3b8; pointer-events:none; }
        .vh-btn--excel { color:#15803d !important; border-color:#bbf7d0; }
        .vh-btn--del { color:#b91c1c !important; border-color:#fecaca; }
        .vh-btn--del:disabled { opacity:.6; }
    </style>
</x-app-layout>
