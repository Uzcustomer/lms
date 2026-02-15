<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            YN oldi qaydnoma hisoboti
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? '') == $type->education_type_code ? 'selected' : '' }}>
                                        {{ $type->education_type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;">
                                @if(!isset($dekanFacultyIds) || empty($dekanFacultyIds))
                                    <option value="">Barchasi</option>
                                @endif
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="max-width:110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="max-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Semestr</label>
                            <select id="semester" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="max-width:140px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                        <div class="filter-item filter-buttons">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;">
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Ko'rsatish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Legend -->
                <div class="legend-bar">
                    <span class="legend-item"><span class="legend-dot" style="background:#7c3aed;"></span> YNga yuborilgan guruhlar ro'yxati</span>
                    <span class="legend-item" style="margin-left:auto;color:#94a3b8;font-size:10px;">Jurnal sahifasida "YN ga yuborish" tugmasi bosilgan guruh+fan juftliklari</span>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Ko'rsatish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">YNga yuborilgan guruhlar ro'yxatini ko'rish</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f0f4ff;border-bottom:1px solid #c7d2fe;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#7c3aed;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="subject_name">Fan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th>Semestr</th>
                                        <th><a href="#" class="sort-link" data-sort="submitted_by_name">Yuborgan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="submitted_at">Yuborilgan sana <span class="sort-icon active">&#9660;</span></a></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                        <div id="pagination-area" style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'submitted_at';
        let currentDirection = 'desc';
        let currentPage = 1;

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function toggleSemester() {
            var el = document.getElementById('current-semester-toggle');
            el.classList.toggle('active');
        }

        function getFilters() {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            return {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester: $('#semester').val() || '',
                group: $('#group').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
                sort: currentSort,
                direction: currentDirection,
            };
        }

        function loadReport(page) {
            currentPage = page || 1;
            var params = getFilters();
            params.page = currentPage;
            $('#empty-state').hide(); $('#table-area').hide(); $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');
            var startTime = performance.now();
            $.ajax({
                url: '{{ route("admin.yn-submission-report.data") }}',
                type: 'GET', data: params, timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        return;
                    }
                    $('#total-badge').text('Jami: ' + res.total + ' ta yozuv');
                    $('#time-badge').text(elapsed + ' soniyada yuklandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                },
                error: function() {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.subject_name) + '</span></td>';
                html += '<td><span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">' + esc(r.semester_name) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:600;color:#334155;">' + esc(r.submitted_by_name) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-submitted">' + esc(r.submitted_at) + '</span></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function renderPagination(res) {
            if (res.last_page <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            if (res.current_page > 1)
                html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page - 1) + ')">&laquo;</button>';
            for (var p = 1; p <= res.last_page; p++) {
                if (p === 1 || p === res.last_page || (p >= res.current_page - 2 && p <= res.current_page + 2)) {
                    html += '<button class="pg-btn' + (p === res.current_page ? ' pg-active' : '') + '" onclick="loadReport(' + p + ')">' + p + '</button>';
                } else if (p === res.current_page - 3 || p === res.current_page + 3) {
                    html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                }
            }
            if (res.current_page < res.last_page)
                html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page + 1) + ')">&raquo;</button>';
            $('#pagination-area').html(html);
        }

        $(document).ready(function() {
            $(document).on('click', '.sort-link', function(e) {
                e.preventDefault();
                var col = $(this).data('sort');
                if (currentSort === col) { currentDirection = currentDirection === 'asc' ? 'desc' : 'asc'; }
                else { currentSort = col; currentDirection = 'asc'; }
                $('.sort-link .sort-icon').removeClass('active').html('&#9650;&#9660;');
                $(this).find('.sort-icon').addClass('active').html(currentDirection === 'asc' ? '&#9650;' : '&#9660;');
                loadReport(1);
            });

            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            function fp() {
                var df = document.getElementById('dekan_faculty_id');
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: df ? df.value : ($('#faculty').val() || ''),
                    specialty_id: $('#specialty').val() || '',
                    level_code: $('#level_code').val() || '',
                    current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0'
                };
            }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rSem() { rd('#semester'); pd('{{ route("admin.journal.get-semesters") }}', { level_code: $('#level_code').val() || '' }, '#semester'); }
            function rGrp() { rd('#group'); pd('{{ route("admin.journal.get-groups") }}', fp(), '#group'); }

            $('#education_type').change(function() { rSpec(); rGrp(); });
            $('#faculty').change(function() { rSpec(); rGrp(); });
            $('#specialty').change(function() { rGrp(); });
            $('#level_code').change(function() { rSem(); rGrp(); });
            $('#semester').change(function() { rGrp(); });

            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester');
            pd('{{ route("admin.journal.get-groups") }}', fp(), '#group');
        });
    </script>

    <style>
        .filter-container { padding: 12px 16px 10px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { flex: 1; min-width: 130px; }
        .filter-buttons { flex: 0 0 auto; min-width: auto !important; }
        .filter-label { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .legend-bar { display: flex; gap: 16px; padding: 6px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; align-items: center; }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #475569; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

        .btn-calc { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(124,58,237,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #6d28d9, #7c3aed); transform: translateY(-1px); }

        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 8px; padding-right: 48px; color: #1e293b; font-size: 0.78rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 20px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 15px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 5px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.78rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #7c3aed; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 36px; height: 20px; background: #cbd5e1; border-radius: 10px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #7c3aed, #8b5cf6); }
        .toggle-thumb { width: 16px; height: 16px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(16px); }
        .toggle-label { font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #5b21b6; }

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 12px 8px; text-align: left; font-weight: 600; font-size: 10.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 12px 8px 12px 14px; width: 40px; }
        .sort-link { display: inline-flex; align-items: center; gap: 3px; color: #334155; text-decoration: none; cursor: pointer; }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 7px; opacity: 0.4; }
        .sort-icon.active { font-size: 10px; opacity: 1; color: #7c3aed; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table td { padding: 8px 8px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 14px !important; font-weight: 700; color: #64748b; font-size: 12px; }

        .journal-table tbody tr:hover { background: #f5f3ff !important; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
        .badge-submitted { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; font-weight: 700; font-size: 11px; }

        .text-cell { font-size: 12px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 200px; white-space: normal; word-break: break-word; }

        .pg-btn { padding: 5px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #f5f3ff; border-color: #7c3aed; color: #7c3aed; }
        .pg-active { background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important; color: #fff !important; border-color: #7c3aed !important; }
    </style>
</x-app-layout>
