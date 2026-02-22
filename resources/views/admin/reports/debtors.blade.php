<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Qarzdorlar hisoboti
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <!-- Row 1 -->
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
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Min. qarzdorlik</label>
                            <select id="min_debt_count" class="select2" style="width: 100%;">
                                @foreach([1, 2, 3, 4, 5, 6, 7, 8] as $cnt)
                                    <option value="{{ $cnt }}" {{ $cnt == 4 ? 'selected' : '' }}>&ge; {{ $cnt }} ta fan</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <!-- Row 2 -->
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
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                            <select id="department" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kafedra)
                                    <option value="{{ $kafedra->department_id }}">{{ $kafedra->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 280px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 380px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <div class="excel-dropdown" id="excel-dropdown">
                                    <button type="button" id="btn-excel" class="btn-excel" onclick="toggleExcelMenu()" disabled>
                                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Excel
                                        <svg style="width:12px;height:12px;margin-left:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="excel-menu" id="excel-menu">
                                        <button type="button" onclick="downloadExcel('summary')">
                                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Hisobotni yuklash
                                        </button>
                                        <button type="button" onclick="downloadExcel('full')">
                                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V9.41a1 1 0 00-.29-.71l-4.41-4.41a1 1 0 00-.71-.29H7c-2 0-3 1-3 3z"/></svg>
                                            To'liq yuklash
                                        </button>
                                    </div>
                                </div>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">4 va undan ortiq fandan qarzdor talabalar ro'yxati</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:12px;">
                            <span id="total-badge" class="badge" style="background:#dc2626;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
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
                                        <th><a href="#" class="sort-link" data-sort="debt_count">Qarzdor fanlar <span class="sort-icon active">&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="lesson_days">Darslar soni <span class="sort-icon">&#9650;&#9660;</span></a></th>
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
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title" style="margin:0;font-size:16px;font-weight:700;color:#0f172a;"></h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <div id="modal-body" class="modal-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'debt_count';
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

        function toggleSemester() {
            var btn = document.getElementById('current-semester-toggle');
            btn.classList.toggle('active');
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
                department: $('#department').val() || '',
                subject: $('#subject').val() || '',
                student_status: $('#student_status').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
                min_debt_count: $('#min_debt_count').val() || 4,
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
                url: '{{ route("admin.reports.debtors.data") }}',
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
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    reportData = res.data;
                    $('#total-badge').text('Jami: ' + res.total + ' ta qarzdor talaba');
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
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
                    console.error('Debtors report error:', xhr.status, xhr.responseText ? xhr.responseText.substring(0, 500) : '');
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
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span>';
                html += '<span style="font-size:11px;color:#94a3b8;display:block;">' + esc(r.student_id_number) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-debt">' + r.debt_count + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-violet">' + (r.lesson_days || 0) + ' kun</span></td>';
                html += '<td style="text-align:center;"><button class="btn-detail" onclick="showDetail(' + i + ')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Batafsil</button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function showDetail(idx) {
            var r = reportData[idx];
            if (!r) return;

            $('#modal-title').text(r.full_name + ' - Qarzdorliklar (' + r.debt_count + ' ta fan)');

            var journalBase = '{{ url("/admin/journal/show") }}';
            var html = '<table class="detail-table">';
            html += '<thead><tr><th>#</th><th>Fan</th><th>JB</th><th>MT</th><th>ON</th><th>JN%</th><th>OSKI</th><th>Test</th><th>Davomat</th><th>Sabab</th><th>Jurnal</th></tr></thead>';
            html += '<tbody>';

            if (r.debts && r.debts.length) {
                for (var d = 0; d < r.debts.length; d++) {
                    var debt = r.debts[d];
                    html += '<tr>';
                    html += '<td>' + (d + 1) + '</td>';
                    html += '<td style="font-weight:600;color:#0f172a;min-width:160px;">' + esc(debt.subject_name) + '</td>';
                    var minL = debt.minimum_limit || 60;
                    html += '<td class="' + (debt.jb < minL ? 'cell-fail' : 'cell-pass') + '">' + debt.jb + '</td>';
                    html += '<td class="' + (debt.mt < minL ? 'cell-fail' : 'cell-pass') + '">' + debt.mt + '</td>';
                    html += '<td class="' + (debt.on !== null && debt.on < minL ? 'cell-fail' : 'cell-pass') + '">' + (debt.on !== null ? debt.on : '-') + '</td>';
                    html += '<td class="' + (debt.jn_percent < minL ? 'cell-fail' : 'cell-pass') + '" style="font-weight:700;">' + debt.jn_percent + '</td>';
                    html += '<td class="' + (debt.oski !== null && debt.oski < minL ? 'cell-fail' : 'cell-pass') + '">' + (debt.oski !== null ? debt.oski : '-') + '</td>';
                    html += '<td class="' + (debt.test !== null && debt.test < minL ? 'cell-fail' : 'cell-pass') + '">' + (debt.test !== null ? debt.test : '-') + '</td>';
                    html += '<td class="' + (debt.absence_percent > 25 ? 'cell-fail' : 'cell-pass') + '">' + debt.absence_percent + '%</td>';
                    html += '<td>';
                    var reasons = debt.reasons || [];
                    for (var ri = 0; ri < reasons.length; ri++) {
                        html += '<span class="reason-badge">' + esc(reasons[ri]) + '</span>';
                    }
                    html += '</td>';
                    var jUrl = journalBase + '/' + encodeURIComponent(debt.group_id) + '/' + encodeURIComponent(debt.subject_id) + '/' + encodeURIComponent(debt.semester_code);
                    html += '<td style="text-align:center;"><a href="' + jUrl + '" target="_blank" class="journal-link-modal">Jurnal</a></td>';
                    html += '</tr>';
                }
            }

            html += '</tbody></table>';
            $('#modal-body').html(html);
            $('#detail-modal').fadeIn(150);
        }

        function closeModal() {
            $('#detail-modal').fadeOut(150);
        }

        function toggleExcelMenu() {
            var menu = document.getElementById('excel-menu');
            menu.classList.toggle('show');
        }

        // Close excel menu when clicking outside
        document.addEventListener('click', function(e) {
            var dropdown = document.getElementById('excel-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                document.getElementById('excel-menu').classList.remove('show');
            }
        });

        function downloadExcel(type) {
            document.getElementById('excel-menu').classList.remove('show');
            var params = getFilters();
            params.export = type;
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.debtors.data") }}?' + query;
        }

        function renderPagination(res) {
            if (res.last_page <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            if (res.current_page > 1)
                html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page - 1) + ')">&laquo; Oldingi</button>';
            for (var p = 1; p <= res.last_page; p++) {
                if (p === 1 || p === res.last_page || (p >= res.current_page - 2 && p <= res.current_page + 2)) {
                    html += '<button class="pg-btn' + (p === res.current_page ? ' pg-active' : '') + '" onclick="loadReport(' + p + ')">' + p + '</button>';
                } else if (p === res.current_page - 3 || p === res.current_page + 3) {
                    html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                }
            }
            if (res.current_page < res.last_page)
                html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page + 1) + ')">Keyingi &raquo;</button>';
            $('#pagination-area').html(html);
        }

        // ESC bilan modalni yopish
        $(document).keydown(function(e) {
            if (e.keyCode === 27) closeModal();
        });

        $(document).ready(function() {
            // Sort links
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

            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Cascading dropdowns
            function fp() { var df=document.getElementById('dekan_faculty_id'); return { education_type: $('#education_type').val()||'', faculty_id: df ? df.value : ($('#faculty').val()||''), specialty_id: $('#specialty').val()||'', department_id: $('#department').val()||'', level_code: $('#level_code').val()||'', semester_code: $('#semester_code').val()||'', subject_id: $('#subject').val()||'', current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0' }; }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rSubj() { rd('#subject'); pdu('{{ route("admin.journal.get-subjects") }}', fp(), '#subject'); }
            function rGrp() { rd('#group'); pd('{{ route("admin.journal.get-groups") }}', fp(), '#group'); }

            $('#education_type').change(function() { rSpec(); rSubj(); rGrp(); });
            $('#faculty').change(function() { rSpec(); rSubj(); rGrp(); });
            $('#department').change(function() { rSubj(); rGrp(); });
            $('#specialty').change(function() { rGrp(); });
            $('#level_code').change(function() { var lc=$(this).val(); rd('#semester_code'); if(lc) pd('{{ route("admin.journal.get-semesters") }}', {level_code:lc}, '#semester_code'); rSubj(); rGrp(); });
            $('#semester_code').change(function() { rSubj(); rGrp(); });
            $('#subject').change(function() { rGrp(); });

            // Init dropdowns
            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code');
            pdu('{{ route("admin.journal.get-subjects") }}', fp(), '#subject');
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

        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

        .excel-dropdown { position: relative; display: inline-block; }
        .excel-menu { display: none; position: absolute; bottom: 100%; left: 0; margin-bottom: 4px; background: #fff; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; min-width: 200px; z-index: 100; overflow: hidden; }
        .excel-menu.show { display: block; }
        .excel-menu button { display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 16px; border: none; background: #fff; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .excel-menu button:hover { background: #f0fdf4; color: #16a34a; }
        .excel-menu button:first-child { border-bottom: 1px solid #f1f5f9; }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
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
        .journal-table tbody tr:hover { background: #fef2f2 !important; box-shadow: inset 4px 0 0 #dc2626; }
        .journal-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #dc2626; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-debt { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; padding: 4px 14px; font-size: 14px; font-weight: 800; border-radius: 8px; min-width: 36px; display: inline-block; text-align: center; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
        .text-subject { color: #0f172a; font-weight: 600; font-size: 12px; white-space: normal; word-break: break-word; }

        .btn-detail { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; color: #2b5ea7; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .btn-detail:hover { background: #2b5ea7; color: #fff; border-color: #2b5ea7; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }
        .pg-active { background: linear-gradient(135deg, #dc2626, #ef4444) !important; color: #fff !important; border-color: #dc2626 !important; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #fff; border-radius: 16px; width: 95%; max-width: 1100px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-radius: 16px 16px 0 0; }
        .modal-close { width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
        .modal-close:hover { background: #dc2626; color: #fff; }
        .modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }

        .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .detail-table thead tr { background: #f8fafc; }
        .detail-table th { padding: 10px 12px; text-align: left; font-weight: 700; font-size: 11px; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .detail-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; }
        .detail-table td:first-child { text-align: center; font-weight: 700; color: #64748b; width: 40px; }
        .detail-table td:nth-child(2) { text-align: left; }
        .detail-table td:last-child { text-align: left; }
        .detail-table tbody tr:hover { background: #fafafa; }

        .cell-fail { background: #fef2f2; color: #dc2626; font-weight: 700; }
        .cell-pass { color: #16a34a; font-weight: 600; }

        .reason-badge { display: inline-block; padding: 2px 8px; margin: 2px 3px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .journal-link-modal { display: inline-block; padding: 3px 10px; background: #eff6ff; color: #2b5ea7; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.15s; white-space: nowrap; }
        .journal-link-modal:hover { background: #2b5ea7; color: #fff; border-color: #2b5ea7; }
    </style>
</x-app-layout>
