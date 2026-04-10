<x-app-layout>
    @push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .gp-container { padding: 16px; }
        .gp-filters { background: linear-gradient(135deg, #f0f4f8, #e8edf5); padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .gp-filter { display: flex; flex-direction: column; gap: 4px; }
        .gp-filter label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; }
        .gp-filter input, .gp-filter select { height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
        .gp-filter input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        .gp-btn { padding: 8px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; height: 36px; display: inline-flex; align-items: center; gap: 6px; }
        .gp-btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; }
        .gp-btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, #2563eb); }
        .gp-stats { display: flex; gap: 8px; margin-bottom: 12px; }
        .gp-stat { padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; }
        .gp-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .gp-table thead th { background: #1e293b; color: #fff; padding: 12px 10px; font-size: 11px; text-transform: uppercase; text-align: left; white-space: nowrap; }
        .gp-table tbody td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .gp-table tbody tr:hover { background: #f0f9ff; }
        .gp-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gp-badge-ok { background: #dcfce7; color: #166534; }
        .gp-badge-no { background: #fee2e2; color: #991b1b; }
        .gp-file-btn { display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; color: #334155; background: #fff; }
        .gp-file-btn:hover { background: #f0f9ff; border-color: #2563eb; color: #2563eb; }
        .gp-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .gp-loading { text-align: center; padding: 40px; color: #2563eb; }
        .gp-pagination { display: flex; gap: 4px; justify-content: center; padding: 12px; flex-wrap: wrap; }
        .gp-pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .gp-pg-btn:hover { background: #f0f9ff; border-color: #2563eb; }
        .gp-pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; font-size: 13px; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; }
    </style>
    @endpush

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitiruvchilar ma'lumotlari</h2>
    </x-slot>

    <div class="gp-container">
        <div class="gp-filters">
            <div class="gp-filter" style="min-width:140px;">
                <label>Ta'lim turi</label>
                <select id="f-education-type" class="select2" style="width:100%;">
                    <option value="">Barchasi</option>
                    @foreach($educationTypes as $t)
                        <option value="{{ $t->education_type_code }}">{{ $t->education_type_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="gp-filter" style="flex:1;min-width:180px;">
                <label>Fakultet</label>
                <select id="f-faculty" class="select2" style="width:100%;">
                    <option value="">Barchasi</option>
                    @foreach($faculties as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="gp-filter" style="min-width:140px;">
                <label>Guruh</label>
                <input type="text" id="f-group" placeholder="d1/22-01a...">
            </div>
            <div class="gp-filter" style="flex:1;min-width:200px;">
                <label>Qidirish</label>
                <input type="text" id="f-search" placeholder="Ism, ID, passport, JSHSHIR...">
            </div>
            <div class="gp-filter">
                <label>&nbsp;</label>
                <button class="gp-btn gp-btn-primary" onclick="loadData(1)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Qidirish
                </button>
            </div>
        </div>

        <div id="stats-area" style="display:none;" class="gp-stats">
            <span class="gp-stat" style="background:#dbeafe;color:#1e40af;" id="stat-total"></span>
        </div>

        <div id="loading" class="gp-loading" style="display:none;">
            <div style="width:36px;height:36px;margin:0 auto 8px;border:4px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
            Yuklanmoqda...
        </div>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>

        <div id="empty" class="gp-empty">
            <p style="font-size:15px;font-weight:600;">Bitiruvchilar passport ma'lumotlarini ko'rish</p>
            <p style="font-size:13px;margin-top:4px;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
        </div>

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
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        var dataUrl = '{{ route("admin.graduate-passports.data") }}';
        var fileUrlBase = '{{ url("/admin/graduate-passports") }}';
        var csrfToken = '{{ csrf_token() }}';

        $(function() {
            $('.select2').select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: 'Barchasi' });
            loadData(1);
        });

        function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function loadData(page) {
            $('#empty').hide(); $('#table-area').hide(); $('#loading').show(); $('#stats-area').hide();

            $.ajax({
                url: dataUrl, type: 'GET',
                data: {
                    page: page, per_page: 50,
                    education_type: $('#f-education-type').val() || '',
                    faculty: $('#f-faculty').val() || '',
                    group_name: $('#f-group').val() || '',
                    search: $('#f-search').val() || '',
                },
                success: function(res) {
                    $('#loading').hide();
                    if (!res.data || res.data.length === 0) {
                        $('#empty').show().find('p:first').text("Ma'lumot topilmadi");
                        return;
                    }
                    $('#stat-total').text('Jami: ' + res.total + ' ta');
                    $('#stats-area').show();
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                },
                error: function() {
                    $('#loading').hide();
                    $('#empty').show().find('p:first').text('Xatolik yuz berdi');
                }
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
                if (!files) files = '<span class="gp-badge gp-badge-no">Yo\'q</span>';

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

        $('#f-search').on('keyup', function(e) { if (e.key === 'Enter') loadData(1); });
        $('#f-group').on('keyup', function(e) { if (e.key === 'Enter') loadData(1); });
    </script>
</x-app-layout>
