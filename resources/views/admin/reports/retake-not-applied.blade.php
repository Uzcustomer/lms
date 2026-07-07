<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Qarzdorlar — akademik ma'lumotlar (academic records)
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
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="only-debtors-toggle" onclick="this.classList.toggle('active')">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Faqat qarzdorlar</span>
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
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Talabalarning academic records yozuvlari bo'yicha qarzdor (bahosi yo'q / yiqilgan) fanlar ro'yxati</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <span id="total-badge" class="badge" style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                                <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:nowrap;">
                                <select id="retake_status_filter" class="select2" style="width:260px;">
                                    <option value="">Barchasi</option>
                                    <option value="no_application">Ariza bermaganlar</option>
                                    <option value="group_assigned">Guruhga biriktirilganlar</option>
                                </select>
                                <button type="button" onclick="exportRetakeNotAppliedExcel()" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;height:38px;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 2px 8px rgba(22,163,74,0.3);white-space:nowrap;flex:0 0 auto;">
                                    Excel
                                </button>
                            </div>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="full_name">Talaba FISH <span class="sort-icon active">&#9650;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="subject_name">Fan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th>Yopilish shakli</th>
                                        <th><a href="#" class="sort-link" data-sort="semester_name">Semestr <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;">Soat</th>
                                        <th style="text-align:center;">Kredit</th>
                                        <th style="text-align:center;"><a href="#" class="sort-link" data-sort="total_point">Olgan bali <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;"><a href="#" class="sort-link" data-sort="grade">Olgan bahosi <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th title="Qayta o'qishga ariza berganlik holati">Qayta o'qish holati</th>
                                        <th title="O'qishi holati">O'qish holati</th>
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

    <div id="detail-modal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeDetailModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modal-title">Batafsil ma'lumot</h3>
                <button onclick="closeDetailModal()" class="modal-close">&times;</button>
            </div>
            <div id="modal-student-info" class="modal-info"></div>
            <div id="modal-body" style="max-height:65vh;overflow-y:auto;padding:0 4px 8px;"></div>
        </div>
    </div>

    <div id="score-modal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeScoreModal()">
        <div class="modal-box" style="max-width:760px;">
            <div class="modal-header">
                <h3 id="score-modal-title">Qayta o'qish baholari</h3>
                <button onclick="closeScoreModal()" class="modal-close">&times;</button>
            </div>
            <div id="score-modal-body" style="max-height:65vh;overflow-y:auto;padding:0 4px 8px;"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'full_name';
        let currentDirection = 'asc';
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
                retake_status_filter: $('#retake_status_filter').val() || '',
                only_debtors: document.getElementById('only-debtors-toggle').classList.contains('active') ? '1' : '0',
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
                    $('#total-badge').text('Jami: ' + res.total + ' ta yozuv');
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

        function exportRetakeNotAppliedExcel() {
            var params = getFilters();
            params.export = 'excel';
            var url = new URL('{{ route('admin.reports.retake-not-applied.data') }}', window.location.origin);
            Object.keys(params).forEach(function(key) {
                if (params[key] !== null && params[key] !== '') {
                    url.searchParams.set(key, params[key]);
                }
            });
            window.location.href = url.toString();
        }

        function esc(s) { return $('<span>').text((s === 0 || s) ? s : '-').html(); }
        function fmtNum(c) {
            if (c === null || c === '' || typeof c === 'undefined') return '<span style="color:#94a3b8;">-</span>';
            var n = parseFloat(c);
            if (isNaN(n)) return esc(c);
            return (Math.round(n * 100) / 100).toString();
        }

        function retakePill(status) {
            var map = {
                'Ariza bermagan': 'pill-red',
                "Ko'rib chiqilmoqda": 'pill-gray',
                "To'lovini qilmagan": 'pill-amber',
                "To'lov tekshirilmoqda": 'pill-blue',
                "To'lov tasdiqlandi": 'pill-teal',
                'Guruhga tasdiqlangan': 'pill-green'
            };
            return '<span class="pill ' + (map[status] || 'pill-gray') + '">' + esc(status) + '</span>';
        }

        function studyPill(code, label) {
            var map = { passed: 'pill-green', failed: 'pill-red', not_examined: 'pill-amber', not_graded: 'pill-gray' };
            return '<span class="pill ' + (map[code] || 'pill-gray') + '">' + esc(label) + '</span>';
        }

        function fmtCredit(c) {
            var n = parseFloat(c);
            if (isNaN(n)) return '-';
            return (Math.round(n * 100) / 100).toString();
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var scoreBtn = r.has_score_details
                    ? '<button class="btn-detail" onclick="showScoreDetail(' + i + ')">Ko\'rish</button>'
                    : '<span style="color:#94a3b8;">-</span>';
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell" style="font-weight:600;color:#1e293b;">' + esc(r.full_name) + '</span><span style="font-size:11px;color:#94a3b8;">' + esc(r.student_id_number) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.subject_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.closing_form) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.semester_name) + '</span></td>';
                html += '<td style="text-align:center;">' + fmtNum(r.total_acload) + '</td>';
                html += '<td style="text-align:center;">' + fmtNum(r.credit) + '</td>';
                html += '<td style="text-align:center;">' + scoreBtn + '</td>';
                var gradeStyle = r.is_debt ? 'color:#dc2626;font-weight:800;' : 'color:#15803d;font-weight:800;';
                var gradeVal = (r.grade === null || typeof r.grade === 'undefined') ? '<span style="color:#94a3b8;">-</span>' : '<span style="' + gradeStyle + '">' + esc(r.grade) + '</span>';
                html += '<td style="text-align:center;">' + gradeVal + '</td>';
                html += '<td>' + retakePill(r.retake_status) + '</td>';
                html += '<td>' + studyPill(r.study_status_code, r.study_status) + '</td>';
                html += '<td style="text-align:center;"><button class="btn-detail" onclick="showDetail(' + i + ')">Batafsil</button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function showScoreDetail(idx) {
            var r = reportData[idx];
            if (!r) return;

            var rows = Array.isArray(r.score_details) ? r.score_details : [];
            $('#score-modal-title').text("Qayta o'qish baholari");

            if (!rows.length) {
                $('#score-modal-body').html('<div class="sec-empty">Baholar topilmadi</div>');
                $('#score-modal').css('display', 'flex');
                return;
            }

            var html = '';
            html += '<div class="modal-info" style="margin-bottom:12px;">';
            html += '<div class="mi-grid">';
            html += '<div><span class="mi-l">Talaba</span><span class="mi-v">' + esc(r.full_name) + '</span></div>';
            html += '<div><span class="mi-l">ID</span><span class="mi-v">' + esc(r.student_id_number) + '</span></div>';
            html += '<div><span class="mi-l">Fan</span><span class="mi-v">' + esc(r.subject_name) + '</span></div>';
            html += '<div><span class="mi-l">Semestr</span><span class="mi-v">' + esc(r.semester_name) + '</span></div>';
            html += '</div>';
            html += '</div>';

            html += '<table class="det-table">';
            html += '<thead><tr><th style="width:120px;">Baho turi</th><th style="text-align:center;width:120px;">Qiymat</th><th>O\'qituvchi</th><th style="width:170px;">Sana</th></tr></thead><tbody>';
            for (var i = 0; i < rows.length; i++) {
                var s = rows[i];
                html += '<tr>';
                html += '<td><span class="pill pill-blue">' + esc(s.type) + '</span></td>';
                html += '<td style="text-align:center;font-weight:800;color:#1e293b;">' + fmtNum(s.score) + '</td>';
                html += '<td>' + esc(s.teacher || '—') + '</td>';
                html += '<td>' + esc(s.date || '—') + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table>';

            $('#score-modal-body').html(html);
            $('#score-modal').css('display', 'flex');
        }

        function showDetail(idx) {
            var r = reportData[idx];
            if (!r) return;

            $('#modal-title').text(r.full_name);
            $('#modal-student-info').html(
                '<div class="mi-grid">' +
                '<div><span class="mi-l">ID</span><span class="mi-v">' + esc(r.student_id_number) + '</span></div>' +
                '<div><span class="mi-l">Fakultet</span><span class="mi-v">' + esc(r.department_name) + '</span></div>' +
                "<div><span class=\"mi-l\">Yo'nalish</span><span class=\"mi-v\">" + esc(r.specialty_name) + '</span></div>' +
                '<div><span class="mi-l">Guruh</span><span class="mi-v">' + esc(r.group_name) + '</span></div>' +
                '<div><span class="mi-l">Semestr</span><span class="mi-v">' + esc(r.semester_name) + '</span></div>' +
                '<div><span class="mi-l">Fan</span><span class="mi-v">' + esc(r.subject_name) + '</span></div>' +
                '</div>'
            );

            $('#modal-body').html('<div class="sec-empty">Yuklanmoqda...</div>');
            $('#detail-modal').css('display', 'flex');

            $.get('{{ route('admin.reports.student-all-records') }}', {
                student_id: r.hemis_id,
                group_name: r.group_name,
                current_semester: 0
            }, function(resp) {
                var semCode = String(r.semester_code || '');
                var planned = (resp.planned_subjects || []).filter(function(item) {
                    return String(item.semester_code) === semCode;
                });
                var extra = (resp.extra_subjects || []).filter(function(item) {
                    return String(item.semester_code) === semCode;
                });

                var body = '';
                body += "<div class=\"sec-title\" style=\"color:#2563eb;\">O'quv rejadagi fanlar</div>";
                if (planned.length) {
                    body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th style="text-align:center;">Kredit</th><th style="text-align:center;">Soat</th><th style="text-align:center;">Holat</th><th style="text-align:center;">Ball</th><th style="text-align:center;">Baho</th></tr></thead><tbody>';
                    for (var i = 0; i < planned.length; i++) {
                        var p = planned[i];
                        var pStatus = p.has_record ? '<span class="pill pill-green">Yozuv bor</span>' : "<span class=\"pill pill-red\">Yozuv yo'q</span>";
                        body += '<tr><td>' + (i + 1) + '</td><td>' + esc(p.subject_name) + '</td><td style="text-align:center;">' + fmtCredit(p.credit) + '</td><td style="text-align:center;">' + fmtNum(p.total_acload) + '</td><td style="text-align:center;">' + pStatus + '</td><td style="text-align:center;">' + fmtNum(p.total_point) + '</td><td style="text-align:center;">' + fmtNum(p.grade) + '</td></tr>';
                    }
                    body += '</tbody></table>';
                } else {
                    body += "<div class=\"sec-empty\">Bu semestr uchun o'quv rejadagi fan topilmadi</div>";
                }

                body += '<div class="sec-title" style="color:#7c3aed;">Ortiqcha fanlar</div>';
                if (extra.length) {
                    body += '<table class="det-table"><thead><tr><th style="width:36px;">#</th><th>Fan</th><th style="text-align:center;">Kredit</th><th style="text-align:center;">Ball</th><th style="text-align:center;">Baho</th></tr></thead><tbody>';
                    for (var j = 0; j < extra.length; j++) {
                        var e = extra[j];
                        body += '<tr><td>' + (j + 1) + '</td><td>' + esc(e.subject_name) + '</td><td style="text-align:center;">' + fmtCredit(e.credit) + '</td><td style="text-align:center;">' + fmtNum(e.total_point) + '</td><td style="text-align:center;">' + fmtNum(e.grade) + '</td></tr>';
                    }
                    body += '</tbody></table>';
                } else {
                    body += "<div class=\"sec-empty\">Yo'q</div>";
                }

                $('#modal-body').html(body);
            }).fail(function(xhr) {
                var msg = "Batafsil ma'lumotni yuklab bo'lmadi";
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg += ': ' + xhr.responseJSON.error;
                }
                $('#modal-body').html('<div class="sec-empty">' + esc(msg) + '</div>');
            });
        }

        function closeDetailModal() {
            $('#detail-modal').hide();
        }

        function closeScoreModal() {
            $('#score-modal').hide();
        }

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
            $('#retake_status_filter').change(function() { loadReport(1); });

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

        .btn-detail { display:inline-flex; align-items:center; justify-content:center; padding:6px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#1e40af; font-size:12px; font-weight:700; }
        .btn-detail:hover { background:#eff6ff; }
        .modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; padding:20px; z-index:9999; }
        .modal-box { width:min(1100px, 100%); max-height:90vh; overflow:hidden; background:#fff; border-radius:16px; box-shadow:0 25px 60px rgba(15,23,42,.25); border:1px solid #dbe4ef; }
        .modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#f0f6ff,#f8fbff); }
        .modal-header h3 { margin:0; font-size:18px; font-weight:700; color:#0f172a; }
        .modal-close { width:36px; height:36px; border:none; border-radius:10px; background:#fff; color:#475569; font-size:24px; line-height:1; cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.12); }
        .modal-info { padding:14px 18px 6px; border-bottom:1px solid #eef2f7; }
        .mi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 14px; }
        .mi-grid div { display:flex; flex-direction:column; gap:2px; }
        .mi-l { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
        .mi-v { font-size:13px; font-weight:600; color:#0f172a; }
        .sec-title { margin:14px 0 8px; font-size:14px; font-weight:800; }
        .sec-empty { padding:12px 14px; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b; background:#f8fafc; }
        .det-table { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:10px; }
        .det-table thead th { position:sticky; top:0; background:#f8fafc; color:#475569; font-size:12px; font-weight:700; padding:10px 12px; border-bottom:1px solid #e2e8f0; }
        .det-table tbody td { padding:10px 12px; border-bottom:1px solid #eef2f7; font-size:13px; color:#0f172a; vertical-align:top; }
        .pill { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; font-size:11px; font-weight:700; }
        .pill-green { background:#dcfce7; color:#166534; }
        .pill-red { background:#fee2e2; color:#b91c1c; }
        .pill-gray { background:#e2e8f0; color:#475569; }
        .pill-amber { background:#fef3c7; color:#b45309; }
        .pill-blue { background:#dbeafe; color:#1d4ed8; }
        .pill-teal { background:#ccfbf1; color:#0f766e; }

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

        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; line-height: 1.4; white-space: nowrap; }
        .pill-red { background: #fee2e2; color: #b91c1c; }
        .pill-green { background: #dcfce7; color: #15803d; }
        .pill-amber { background: #fef3c7; color: #b45309; margin: 1px; }
        .pill-gray { background: #e2e8f0; color: #475569; }
        .pill-blue { background: #dbeafe; color: #1d4ed8; }
        .pill-teal { background: #ccfbf1; color: #0f766e; }
    </style>
</x-app-layout>
