<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            74 soat dars qoldirish hisoboti
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters - bir qatorda -->
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
                                <option value="">Barchasi</option>
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
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Talaba holati</label>
                            <select id="student_status" class="select2" style="width: 100%;">
                                @foreach($studentStatuses as $status)
                                    <option value="{{ $status->student_status_code }}"
                                        {{ str_contains(mb_strtolower($status->student_status_name ?? ''), 'qimoqda') ? 'selected' : '' }}>
                                        {{ $status->student_status_name }}
                                    </option>
                                @endforeach
                            </select>
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
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Legend -->
                <div class="legend-bar">
                    <span class="legend-item"><span class="legend-dot" style="background:#eab308;"></span> 30-45 soat / 15-20 kun</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#f97316;"></span> 45-60 soat / 20-25 kun</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> 60-74 soat / 25-30 kun</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#7f1d1d;"></span> 74+ soat / 30+ kun</span>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Sababsiz 30 soat va undan ko'p yoki 15 kun va undan ko'p dars qoldirgan talabalar</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#dc2626;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="full_name">FISH <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="unexcused_hours">Sababsiz <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="excused_hours">Sababli <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="total_hours">Jami soat <span class="sort-icon active">&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="total_days">Jami kun <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="white-space:normal;min-width:100px;"><a href="#" class="sort-link" data-sort="attendance_after_74">74 soat keyin qatnashgan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="min-width:90px;">Hisobot sanasi</th>
                                        <th style="white-space:normal;min-width:120px;">Status</th>
                                        <th></th>
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

    <!-- Batafsil Modal -->
    <div id="detail-overlay" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeDetail()">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Batafsil ma'lumot</h3>
                <button onclick="closeDetail()" class="modal-close">&times;</button>
            </div>
            <div id="modal-student-info" class="modal-info"></div>
            <div id="modal-loading" style="display:none;padding:40px;text-align:center;">
                <div class="spinner"></div>
            </div>
            <div id="modal-table-wrap" style="max-height:60vh;overflow-y:auto;">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fan</th>
                            <th>Sana</th>
                            <th>Juftlik</th>
                            <th>Vaqti</th>
                            <th>Turi</th>
                            <th>Soat</th>
                        </tr>
                    </thead>
                    <tbody id="detail-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'total_hours';
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

        function toggleSemester() { document.getElementById('current-semester-toggle').classList.toggle('active'); }

        function getFilters() {
            return {
                education_type: $('#education_type').val() || '',
                faculty: $('#faculty').val() || '',
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester: $('#semester').val() || '',
                group: $('#group').val() || '',
                student_status: $('#student_status').val() || '',
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
                url: '{{ route("admin.absence_report.data") }}',
                type: 'GET', data: params, timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }
                    $('#total-badge').text('Jami: ' + res.total + ' ta talaba');
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function() {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        var statusMap = {
            'yellow':   {label: 'Ogohlantirish', cls: 'status-yellow'},
            'orange':   {label: 'Xavfli',        cls: 'status-orange'},
            'red':      {label: 'Jiddiy',         cls: 'status-red'},
            'critical': {label: 'Chegara',        cls: 'status-critical'},
            'late':     {label: 'Kechikkan',      cls: 'status-late'},
            'has_time': {label: 'Muddati bor',    cls: 'status-hastime'}
        };

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var st = statusMap[r.status] || {label: '-', cls: ''};
                html += '<tr class="journal-row row-' + r.status + '">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-unexcused">' + r.unexcused_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-excused">' + r.excused_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-total">' + r.total_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-days">' + r.total_days + '</span></td>';
                html += '<td style="text-align:center;font-size:12px;white-space:nowrap;">' + esc(r.attendance_after_74) + '</td>';
                html += '<td style="text-align:center;font-size:12px;white-space:nowrap;">' + esc(r.report_date) + '</td>';
                html += '<td style="text-align:center;"><span class="badge ' + st.cls + '">' + st.label + '</span></td>';
                html += '<td style="text-align:center;"><button class="btn-detail" onclick="showDetail(\'' + r.student_hemis_id + '\',\'' + esc(r.full_name).replace(/'/g, "\\'") + '\')">Batafsil</button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        // Batafsil modal
        function showDetail(hemisId, name) {
            $('#modal-title').text(name);
            $('#modal-student-info').empty();
            $('#detail-body').empty();
            $('#modal-loading').show();
            $('#modal-table-wrap').hide();
            $('#detail-overlay').fadeIn(150);

            var cs = document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0';

            $.ajax({
                url: '{{ route("admin.absence_report.detail") }}',
                data: { hemis_id: hemisId, current_semester: cs },
                success: function(res) {
                    $('#modal-loading').hide();
                    if (res.student) {
                        var s = res.student;
                        var info = '<div class="info-grid">';
                        info += '<div><span class="info-label">FISH:</span> <span class="info-value">' + esc(s.full_name) + '</span></div>';
                        info += '<div><span class="info-label">Fakultet:</span> <span class="info-value">' + esc(s.department_name) + '</span></div>';
                        info += '<div><span class="info-label">Yo\'nalish:</span> <span class="info-value">' + esc(s.specialty_name) + '</span></div>';
                        info += '<div><span class="info-label">Kurs:</span> <span class="info-value">' + esc(s.level_name) + '</span></div>';
                        info += '<div><span class="info-label">Semestr:</span> <span class="info-value">' + esc(s.semester_name) + '</span></div>';
                        info += '<div><span class="info-label">Guruh:</span> <span class="info-value">' + esc(s.group_name) + '</span></div>';
                        info += '</div>';
                        $('#modal-student-info').html(info);
                    }
                    if (res.data && res.data.length > 0) {
                        var html = '';
                        for (var i = 0; i < res.data.length; i++) {
                            var d = res.data[i];
                            var typeCls = d.type === 'Sababsiz' ? 'badge-unexcused' : 'badge-excused';
                            html += '<tr>';
                            html += '<td style="color:#64748b;font-weight:600;">' + (i + 1) + '</td>';
                            html += '<td style="font-weight:600;color:#0f172a;">' + esc(d.subject_name) + '</td>';
                            html += '<td>' + esc(d.lesson_date) + '</td>';
                            html += '<td>' + esc(d.pair_name) + '</td>';
                            html += '<td style="font-size:11.5px;color:#475569;">' + esc(d.pair_time) + '</td>';
                            html += '<td><span class="badge ' + typeCls + '">' + d.type + '</span></td>';
                            html += '<td style="text-align:center;font-weight:700;">' + d.hours + '</td>';
                            html += '</tr>';
                        }
                        $('#detail-body').html(html);
                        $('#modal-table-wrap').show();
                    } else {
                        $('#modal-table-wrap').html('<p style="padding:30px;text-align:center;color:#94a3b8;">Ma\'lumot topilmadi</p>').show();
                    }
                },
                error: function() {
                    $('#modal-loading').hide();
                    $('#modal-table-wrap').html('<p style="padding:30px;text-align:center;color:#ef4444;">Xatolik yuz berdi</p>').show();
                }
            });
        }

        function closeDetail() { $('#detail-overlay').fadeOut(150); }

        function downloadExcel() {
            var params = getFilters();
            params.export = 'excel';
            window.location.href = '{{ route("admin.absence_report.data") }}?' + $.param(params);
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

            $(document).on('keydown', function(e) { if (e.key === 'Escape') closeDetail(); });

            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            function fp() {
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: $('#faculty').val() || '',
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

        .legend-bar { display: flex; gap: 16px; padding: 6px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #475569; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

        .btn-calc { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-excel { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 36px; white-space: nowrap; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

        .btn-detail { padding: 4px 12px; background: #f0f4f8; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .btn-detail:hover { background: #2b5ea7; color: #fff; border-color: #2b5ea7; }

        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 8px; padding-right: 48px; color: #1e293b; font-size: 0.78rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 20px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 15px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 5px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.78rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 36px; height: 20px; background: #cbd5e1; border-radius: 10px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 16px; height: 16px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(16px); }
        .toggle-label { font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 12px 8px; text-align: left; font-weight: 600; font-size: 10.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 12px 8px 12px 14px; width: 40px; }
        .sort-link { display: inline-flex; align-items: center; gap: 3px; color: #334155; text-decoration: none; cursor: pointer; }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 7px; opacity: 0.4; }
        .sort-icon.active { font-size: 10px; opacity: 1; color: #ef4444; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table td { padding: 8px 8px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 14px !important; font-weight: 700; color: #64748b; font-size: 12px; }

        .row-yellow td:first-child { border-left: 4px solid #eab308; }
        .row-orange td:first-child { border-left: 4px solid #f97316; }
        .row-red td:first-child { border-left: 4px solid #ef4444; }
        .row-critical td:first-child { border-left: 4px solid #7f1d1d; }
        .row-critical { background: #fef2f2 !important; }
        .journal-table tbody tr:hover { background: #f1f5f9 !important; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
        .badge-unexcused { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 700; }
        .badge-excused { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-total { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 700; }
        .badge-days { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; font-weight: 700; }

        .status-yellow { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; font-weight: 700; }
        .status-orange { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; font-weight: 700; }
        .status-red { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-weight: 700; }
        .status-critical { background: #7f1d1d; color: #fff; border: none; font-weight: 700; }
        .status-late { background: #dc2626; color: #fff; border: none; font-weight: 700; }
        .status-hastime { background: #16a34a; color: #fff; border: none; font-weight: 700; }

        .text-cell { font-size: 12px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 200px; white-space: normal; word-break: break-word; }

        .pg-btn { padding: 5px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }
        .pg-active { background: linear-gradient(135deg, #dc2626, #ef4444) !important; color: #fff !important; border-color: #dc2626 !important; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-box { background: #fff; border-radius: 16px; width: 100%; max-width: 900px; box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow: hidden; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; }
        .modal-header h3 { color: #fff; }
        .modal-close { width: 32px; height: 32px; border: none; background: rgba(255,255,255,0.15); color: #fff; border-radius: 8px; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
        .modal-close:hover { background: rgba(255,255,255,0.3); }
        .modal-info { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 20px; }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .info-value { font-size: 13px; font-weight: 600; color: #0f172a; }

        .detail-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .detail-table thead { background: #f1f5f9; position: sticky; top: 0; }
        .detail-table th { padding: 10px 12px; text-align: left; font-weight: 600; font-size: 10.5px; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #e2e8f0; }
        .detail-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .detail-table tbody tr:hover { background: #fef2f2; }
    </style>
</x-app-layout>
