<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Qayta o'qishga ariza topshirmaganlar
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 160px;">
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
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;" {{ isset($dekanFacultyId) && $dekanFacultyId ? 'disabled' : '' }}>
                                @if(isset($dekanFacultyId) && $dekanFacultyId)
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" selected>{{ $faculty->name }}</option>
                                    @endforeach
                                @else
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @if(isset($dekanFacultyId) && $dekanFacultyId)
                                <input type="hidden" id="dekan_faculty_id" value="{{ $dekanFacultyId }}">
                            @endif
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Chetlashganlik holati</label>
                            <select id="student_status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($studentStatuses as $status)
                                    <option value="{{ $status->student_status_code }}"
                                        {{ str_contains(mb_strtolower($status->student_status_name ?? ''), 'qimoqda') ? 'selected' : '' }}>
                                        {{ $status->student_status_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 90px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.SH</label>
                            <input id="student_name" type="text" class="w-full px-3 text-sm" placeholder="Talaba ismi..." style="height:36px;border-radius:8px;border:1px solid #cbd5e1;font-size:0.8rem;" />
                        </div>
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0ea5e9;"></span> Talaba toifasi</label>
                            <select id="student_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($studentTypes ?? [] as $type)
                                    <option value="{{ $type->student_type_code }}">{{ $type->student_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 210px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="only-not-applied-toggle" onclick="this.classList.toggle('active')">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Faqat ariza bermaganlar</span>
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Hisoblash
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tugagan semestrlarda bahosi yo'q (qarz) fanlari bo'lib, qayta o'qishga ariza bermagan talabalar</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;display:flex;align-items:center;gap:12px;">
                            <span id="total-badge" class="badge" style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="full_name">Talaba FISH <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="semester_name">Semestr <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Bahosi yo'q (rejada bor, academic records'da baho yo'q)"><a href="#" class="sort-link" data-sort="no_grade_count">Bahosi yo'q <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Bahosi yo'q fanlardan qayta o'qishga ariza bermaganlari"><a href="#" class="sort-link" data-sort="not_applied_count">Ariza bermagan <span class="sort-icon active">&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Rejada yo'q, academic records'da bor"><a href="#" class="sort-link" data-sort="extra_count">Ortiqcha <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Joriy semestr — potensial yiqilganlar"><a href="#" class="sort-link" data-sort="current_risk_count">Joriy xavf <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;width:90px;">Batafsil</th>
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

    <!-- Detail Modal -->
    <div id="detail-modal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modal-title">Batafsil ma'lumot</h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <div id="modal-student-info" class="modal-info"></div>
            <div id="modal-body" style="max-height:65vh;overflow-y:auto;padding:0 4px 8px;"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'not_applied_count';
        let currentDirection = 'desc';
        let currentPage = 1;
        let reportData = [];

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function getFilters() {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            return {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                group: $('#group').val() || '',
                student_status: $('#student_status').val() || '',
                student_type: $('#student_type').val() || '',
                only_not_applied: document.getElementById('only-not-applied-toggle').classList.contains('active') ? '1' : '0',
                student_name: $('#student_name').val() || '',
                per_page: $('#per_page').val() || 50,
                sort: currentSort,
                direction: currentDirection,
            };
        }

        function loadReport(page) {
            currentPage = page || 1;
            var params = getFilters();
            params.page = currentPage;

            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

            var startTime = performance.now();

            $.ajax({
                url: '{{ route('admin.reports.retake-not-applied.data') }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        return;
                    }

                    reportData = res.data;
                    $('#total-badge').text('Jami: ' + res.total + ' ta talaba');
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');

                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg += ' (' + xhr.responseJSON.error + ')';
                    } else if (xhr.status) {
                        msg += ' (HTTP ' + xhr.status + ')';
                    }
                    $('#empty-state').show().find('p:first').text(msg);
                    console.error('Retake-not-applied report error:', xhr.status, xhr.responseText ? xhr.responseText.substring(0, 500) : '');
                }
            });
        }

        function esc(s) { return $('<span>').text((s === 0 || s) ? s : '-').html(); }
        function fmtCredit(c) { var n = parseFloat(c); if (isNaN(n)) return '-'; return (Math.round(n * 100) / 100).toString(); }

        function cnt(n, color) {
            n = n || 0;
            if (n === 0) return '<span style="color:#94a3b8;">0</span>';
            return '<span class="badge-cnt" style="background:' + color + ';">' + n + '</span>';
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell" style="font-weight:600;color:#1e293b;">' + esc(r.full_name) + '</span><span style="font-size:11px;color:#94a3b8;">' + esc(r.student_id_number) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.semester_name) + '</span></td>';
                html += '<td style="text-align:center;">' + cnt(r.no_grade_count, '#64748b') + '</td>';
                html += '<td style="text-align:center;">' + cnt(r.not_applied_count, 'linear-gradient(135deg,#dc2626,#ef4444)') + '</td>';
                html += '<td style="text-align:center;">' + cnt(r.extra_count, 'linear-gradient(135deg,#7c3aed,#a855f7)') + '</td>';
                html += '<td style="text-align:center;">' + cnt(r.current_risk_count, 'linear-gradient(135deg,#d97706,#f59e0b)') + '</td>';
                html += '<td style="text-align:center;"><button class="btn-detail" onclick="showDetail(' + i + ')">Ko\'rish</button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function showDetail(idx) {
            var r = reportData[idx];
            if (!r) return;
            $('#modal-title').text(r.full_name);
            $('#modal-student-info').html(
                '<div class="mi-grid">' +
                '<div><span class="mi-l">ID</span><span class="mi-v">' + esc(r.student_id_number) + '</span></div>' +
                '<div><span class="mi-l">Fakultet</span><span class="mi-v">' + esc(r.department_name) + '</span></div>' +
                '<div><span class="mi-l">Yo\'nalish</span><span class="mi-v">' + esc(r.specialty_name) + '</span></div>' +
                '<div><span class="mi-l">Guruh</span><span class="mi-v">' + esc(r.group_name) + '</span></div>' +
                '<div><span class="mi-l">Semestr</span><span class="mi-v">' + esc(r.semester_name) + '</span></div>' +
                '</div>'
            );

            var body = '';

            // 1. O'quv rejadagi fanlar
            body += '<div class="sec-title" style="color:#2563eb;">O\\'quv rejadagi fanlar</div>';
            if (r.planned_subjects && r.planned_subjects.length) {
                body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th style="text-align:center;">Semestr</th><th style="text-align:center;">Kredit</th><th style="text-align:center;">Holat</th></tr></thead><tbody>';
                for (var p = 0; p < r.planned_subjects.length; p++) {
                    var ps = r.planned_subjects[p];
                    var planStatus = ps.has_grade
                        ? '<span class="pill pill-green">Baho bor</span>'
                        : '<span class="pill pill-red">Bahosi yo\\'q</span>';
                    body += '<tr><td>' + (p + 1) + '</td><td>' + esc(ps.subject_name) + '</td><td style="text-align:center;">' + esc(ps.semester_name || ps.semester_code) + '</td><td style="text-align:center;">' + fmtCredit(ps.credit) + '</td><td style="text-align:center;">' + planStatus + '</td></tr>';
                }
                body += '</tbody></table>';
            } else {
                body += '<div class="sec-empty">Yo\\'q</div>';
            }

            // 2. Bahosi yo'q (yetmayotgan) + qayta o'qish holati
            body += '<div class="sec-title" style="color:#dc2626;">Bahosi yo\'q (yetmayotgan) fanlar — qayta o\'qish holati</div>';
            if (r.no_grade_subjects && r.no_grade_subjects.length) {
                body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th style="text-align:center;">Semestr</th><th style="text-align:center;">Kredit</th><th style="text-align:center;">Qayta o\'qishga ariza</th></tr></thead><tbody>';
                for (var j = 0; j < r.no_grade_subjects.length; j++) {
                    var d = r.no_grade_subjects[j];
                    var statusCell = d.has_application
                        ? '<span class="pill pill-green">Bergan' + (d.application_status ? ' — ' + esc(d.application_status) : '') + '</span>'
                        : '<span class="pill pill-red">Bermagan</span>';
                    body += '<tr><td>' + (j + 1) + '</td><td>' + esc(d.subject_name) + '</td><td style="text-align:center;">' + esc(d.semester_name || d.semester_code) + '</td><td style="text-align:center;">' + fmtCredit(d.credit) + '</td><td style="text-align:center;">' + statusCell + '</td></tr>';
                }
                body += '</tbody></table>';
            } else {
                body += '<div class="sec-empty">Yo\'q</div>';
            }

            // 3. Ortiqcha
            body += '<div class="sec-title" style="color:#7c3aed;">Ortiqcha fanlar (rejada yo\'q, academic records\'da bor)</div>';
            if (r.extra_subjects && r.extra_subjects.length) {
                body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th style="text-align:center;">Semestr</th><th style="text-align:center;">Kredit</th></tr></thead><tbody>';
                for (var k = 0; k < r.extra_subjects.length; k++) {
                    var e = r.extra_subjects[k];
                    body += '<tr><td>' + (k + 1) + '</td><td>' + esc(e.subject_name) + '</td><td style="text-align:center;">' + esc(e.semester_code) + '</td><td style="text-align:center;">' + fmtCredit(e.credit) + '</td></tr>';
                }
                body += '</tbody></table>';
            } else {
                body += '<div class="sec-empty">Yo\'q</div>';
            }

            // 4. Joriy semestr xavf
            body += '<div class="sec-title" style="color:#d97706;">Joriy semestr — potensial yiqilganlar</div>';
            if (r.current_risks && r.current_risks.length) {
                body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th>Sabablar</th></tr></thead><tbody>';
                for (var m = 0; m < r.current_risks.length; m++) {
                    var cr = r.current_risks[m];
                    var reasons = (cr.reasons || []).map(function(x){ return '<span class="pill pill-amber">' + esc(x) + '</span>'; }).join(' ');
                    body += '<tr><td>' + (m + 1) + '</td><td>' + esc(cr.subject_name) + '</td><td>' + reasons + '</td></tr>';
                }
                body += '</tbody></table>';
            } else {
                body += '<div class="sec-empty">Yo\'q</div>';
            }

            $('#modal-body').html(body);
            $('#detail-modal').css('display', 'flex');
        }

        function closeModal() { $('#detail-modal').hide(); }

        function renderPagination(res) {
            var total = res.last_page || 1;
            var cur = res.current_page || 1;
            if (total <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            html += '<button class="pg-btn" ' + (cur <= 1 ? 'disabled' : '') + ' onclick="loadReport(' + (cur - 1) + ')">‹</button>';
            var start = Math.max(1, cur - 2), end = Math.min(total, cur + 2);
            if (start > 1) html += '<button class="pg-btn" onclick="loadReport(1)">1</button>' + (start > 2 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : '');
            for (var p = start; p <= end; p++) {
                html += '<button class="pg-btn ' + (p === cur ? 'active' : '') + '" onclick="loadReport(' + p + ')">' + p + '</button>';
            }
            if (end < total) html += (end < total - 1 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : '') + '<button class="pg-btn" onclick="loadReport(' + total + ')">' + total + '</button>';
            html += '<button class="pg-btn" ' + (cur >= total ? 'disabled' : '') + ' onclick="loadReport(' + (cur + 1) + ')">›</button>';
            $('#pagination-area').html(html);
        }

        $(document).keydown(function(e) { if (e.keyCode === 27) closeModal(); });

        $(document).ready(function() {
            $(document).on('click', '.sort-link', function(e) {
                e.preventDefault();
                var col = $(this).data('sort');
                if (currentSort === col) {
                    currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = col;
                    currentDirection = 'asc';
                }
                $('.sort-link .sort-icon').removeClass('active').html('&#9650;&#9660;');
                $(this).find('.sort-icon').addClass('active').html(currentDirection === 'asc' ? '&#9650;' : '&#9660;');
                loadReport(1);
            });

            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            function fp() { var df=document.getElementById('dekan_faculty_id'); return { education_type: $('#education_type').val()||'', faculty_id: df ? df.value : ($('#faculty').val()||''), specialty_id: $('#specialty').val()||'', level_code: $('#level_code').val()||'', semester_code: $('#semester_code').val()||'' }; }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rGrp() { rd('#group'); pd('{{ route("admin.journal.get-groups") }}', fp(), '#group'); }

            $('#education_type').change(function() { rSpec(); rGrp(); });
            $('#faculty').change(function() { rSpec(); rGrp(); });
            $('#specialty').change(function() { rGrp(); });
            $('#level_code').change(function() { var lc=$(this).val(); rd('#semester_code'); if(lc) pd('{{ route("admin.journal.get-semesters") }}', {level_code:lc}, '#semester_code'); rGrp(); });
            $('#semester_code').change(function() { rGrp(); });

            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code');
            pd('{{ route("admin.journal.get-groups") }}', fp(), '#group');
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
        .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 14px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 14px 10px 14px 16px; width: 44px; }
        .sort-link { display: inline-flex; align-items: center; gap: 4px; color: #334155; text-decoration: none; cursor: pointer; }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 8px; opacity: 0.4; }
        .sort-icon.active { font-size: 11px; opacity: 1; color: #ef4444; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .journal-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-cnt { display: inline-block; min-width: 30px; padding: 4px 10px; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 800; text-align: center; }
        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }

        .btn-detail { padding: 5px 12px; background: #fff; border: 1px solid #2b5ea7; color: #2b5ea7; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
        .btn-detail:hover { background: #2b5ea7; color: #fff; }

        .pg-btn { min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover:not(:disabled) { border-color: #2b5ea7; color: #2b5ea7; }
        .pg-btn.active { background: #2b5ea7; color: #fff; border-color: #2b5ea7; }
        .pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: #fff; border-radius: 14px; width: 100%; max-width: 920px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); display: flex; flex-direction: column; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 22px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #f0f4f8, #e8edf5); }
        .modal-header h3 { font-size: 16px; font-weight: 700; color: #1e293b; }
        .modal-close { font-size: 26px; line-height: 1; color: #94a3b8; background: none; border: none; cursor: pointer; }
        .modal-close:hover { color: #ef4444; }
        .modal-info { padding: 14px 22px; border-bottom: 1px solid #f1f5f9; background: #fafbfc; }
        .mi-grid { display: flex; flex-wrap: wrap; gap: 18px; }
        .mi-l { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700; }
        .mi-v { display: block; font-size: 13px; color: #1e293b; font-weight: 600; }

        .sec-title { margin: 16px 22px 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
        .sec-empty { margin: 0 22px 8px; font-size: 13px; color: #94a3b8; padding: 6px 0; }
        .det-table { width: calc(100% - 44px); margin: 0 22px 8px; border-collapse: collapse; font-size: 12.5px; }
        .det-table th { background: #f1f5f9; padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .det-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }

        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; line-height: 1.4; white-space: nowrap; }
        .pill-red { background: #fee2e2; color: #b91c1c; }
        .pill-green { background: #dcfce7; color: #15803d; }
        .pill-amber { background: #fef3c7; color: #b45309; margin: 1px; }
    </style>
</x-app-layout>
