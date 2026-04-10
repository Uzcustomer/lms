<x-app-layout>
    @push('styles')
    <style>
        .gp-container { padding: 16px; }
        .gp-search-bar { background: linear-gradient(135deg, #f0f4f8, #e8edf5); padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; display: flex; gap: 10px; align-items: center; }
        .gp-search-input { flex: 1; height: 40px; padding: 0 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; background: #fff; }
        .gp-search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .gp-btn { padding: 8px 20px; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; height: 40px; display: inline-flex; align-items: center; gap: 6px; }
        .gp-btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; }
        .gp-btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, #2563eb); }

        /* Statistika */
        .gp-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .gp-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
        .gp-stat-header { padding: 10px 14px; font-size: 13px; font-weight: 700; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        .gp-stat-body { padding: 8px 14px; }
        .gp-spec-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .gp-spec-row:last-child { border-bottom: none; }
        .gp-spec-name { color: #334155; font-weight: 500; }
        .gp-grp-badges { display: flex; gap: 4px; flex-wrap: wrap; }
        .gp-grp-badge { padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #dbeafe; color: #1e40af; }
        .gp-total-badge { padding: 2px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,0.25); color: #fff; }

        /* Jadval */
        .gp-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .gp-table thead th { background: #1e293b; color: #fff; padding: 12px 10px; font-size: 11px; text-transform: uppercase; text-align: left; white-space: nowrap; }
        .gp-table tbody td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .gp-table tbody tr:hover { background: #f0f9ff; }
        .gp-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gp-file-btn { display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff; }
        .gp-file-btn:hover { background: #f0f9ff; border-color: #2563eb; color: #2563eb; }
        .gp-empty { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .gp-loading { text-align: center; padding: 40px; color: #2563eb; display: none; }
        .gp-pagination { display: flex; gap: 4px; justify-content: center; padding: 12px; flex-wrap: wrap; }
        .gp-pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .gp-pg-btn:hover { background: #f0f9ff; border-color: #2563eb; }
        .gp-pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
    @endpush

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitiruvchilar ma'lumotlari</h2>
    </x-slot>

    <div class="gp-container">
        <!-- Statistika -->
        <div style="margin-bottom:12px;font-size:15px;font-weight:700;color:#1e293b;">
            Jami: <span style="color:#2563eb;">{{ $total }}</span> ta bitiruvchi ma'lumot to'ldirgan
        </div>

        @if(!empty($byFaculty))
        <div class="gp-stats-grid">
            @php $colors = ['#1e40af','#047857','#b45309','#7c3aed','#dc2626','#0891b2','#4338ca','#be123c','#15803d','#0369a1']; $ci = 0; @endphp
            @foreach($byFaculty as $facName => $facData)
                <div class="gp-stat-card">
                    <div class="gp-stat-header" style="background:{{ $colors[$ci % count($colors)] }};">
                        <span>{{ $facName }}</span>
                        <span class="gp-total-badge">{{ $facData['total'] }}</span>
                    </div>
                    <div class="gp-stat-body">
                        @foreach($facData['specialties'] as $specName => $specData)
                            <div class="gp-spec-row">
                                <span class="gp-spec-name">{{ $specName }} <span style="color:#94a3b8;">({{ $specData['total'] }})</span></span>
                                <div class="gp-grp-badges">
                                    @foreach($specData['groups'] as $grpName => $grpCount)
                                        <span class="gp-grp-badge" style="background:#dcfce7;color:#166534;">{{ $grpName }}: {{ $grpCount }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @php $ci++; @endphp
            @endforeach
        </div>
        @endif

        <!-- Qidirish -->
        <div class="gp-search-bar">
            <input type="text" id="f-search" class="gp-search-input" placeholder="Ism, student ID, passport raqami, JSHSHIR, guruh nomi bo'yicha qidirish..." onkeyup="if(event.key==='Enter')loadData(1)">
            <button class="gp-btn gp-btn-primary" onclick="loadData(1)">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Qidirish
            </button>
        </div>

        <div id="loading" class="gp-loading">
            <div style="width:36px;height:36px;margin:0 auto 8px;border:4px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
            Yuklanmoqda...
        </div>

        <div id="result-count" style="display:none;margin-bottom:8px;font-size:13px;font-weight:700;color:#1e40af;"></div>

        <div id="table-area" style="display:none;">
            <div style="overflow-x:auto;">
                <table class="gp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Talaba FISH</th>
                            <th>Student ID</th>
                            <th>Ism (UZ)</th>
                            <th>Ism (EN)</th>
                            <th>Passport</th>
                            <th>JSHSHIR</th>
                            <th>Fakultet</th>
                            <th>Guruh</th>
                            <th>Fayllar</th>
                            <th>Sana</th>
                        </tr>
                    </thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
            <div id="pagination" class="gp-pagination"></div>
        </div>

        <div id="empty" class="gp-empty" style="display:none;">
            <p style="font-size:15px;font-weight:600;">Ma'lumot topilmadi</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var dataUrl = '{{ route("admin.graduate-passports.data") }}';
        var fileUrlBase = '{{ url("/admin/graduate-passports") }}';

        $(function() { loadData(1); });

        function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function loadData(page) {
            $('#empty').hide(); $('#table-area').hide(); $('#loading').show(); $('#result-count').hide();

            $.ajax({
                url: dataUrl, type: 'GET',
                data: { page: page, per_page: 50, search: $('#f-search').val() || '' },
                success: function(res) {
                    $('#loading').hide();
                    if (!res.data || res.data.length === 0) {
                        $('#empty').show();
                        return;
                    }
                    $('#result-count').text('Topildi: ' + res.total + ' ta').show();
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                },
                error: function() { $('#loading').hide(); $('#empty').show(); }
            });
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var files = '';
                if (r.has_front) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/passport_front_path" target="_blank" class="gp-file-btn">Old</a> ';
                if (r.has_back) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/passport_back_path" target="_blank" class="gp-file-btn">Orqa</a> ';
                if (r.has_foreign) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/foreign_passport_path" target="_blank" class="gp-file-btn">Xorijiy</a>';
                if (!files) files = '<span style="color:#dc2626;font-size:11px;">Yo\'q</span>';

                var nameUz = [r.last_name, r.first_name, r.father_name].filter(Boolean).join(' ');

                html += '<tr>';
                html += '<td style="font-weight:700;color:#2563eb;">' + r.row_num + '</td>';
                html += '<td style="font-weight:700;">' + esc(r.full_name) + '</td>';
                html += '<td style="font-size:11px;color:#64748b;">' + esc(r.student_id_number) + '</td>';
                html += '<td>' + esc(nameUz) + '</td>';
                html += '<td>' + esc(r.name_en) + '</td>';
                html += '<td><span class="gp-badge" style="background:#e0e7ff;color:#3730a3;">' + esc(r.passport) + '</span></td>';
                html += '<td style="font-size:12px;">' + esc(r.jshshir || '-') + '</td>';
                html += '<td style="font-size:12px;">' + esc(r.department_name) + '</td>';
                html += '<td><span class="gp-badge" style="background:#f0fdf4;color:#166534;">' + esc(r.group_name) + '</span></td>';
                html += '<td>' + files + '</td>';
                html += '<td style="font-size:11px;color:#64748b;white-space:nowrap;">' + esc(r.created_at) + '</td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function renderPagination(res) {
            if (res.last_page <= 1) { $('#pagination').html(''); return; }
            var html = '';
            for (var p = 1; p <= res.last_page; p++) {
                html += '<button class="gp-pg-btn ' + (p === res.current_page ? 'gp-pg-active' : '') + '" onclick="loadData(' + p + ')">' + p + '</button>';
            }
            $('#pagination').html(html);
        }
    </script>
</x-app-layout>
