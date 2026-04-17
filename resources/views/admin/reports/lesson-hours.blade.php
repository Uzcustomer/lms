<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Dars soati belgilash hisoboti
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
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
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
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
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 80px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                            <select id="department" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kafedra)
                                    <option value="{{ $kafedra->department_id }}">{{ $kafedra->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Soat holati</label>
                            <select id="status_filter" class="select2" style="width: 100%;">
                                <option value="" selected>Barchasi</option>
                                <option value="mismatch">Farq bor</option>
                                <option value="not_marked">Belgilanmagan (0)</option>
                                <option value="partial">Qisman belgilangan</option>
                                <option value="over_marked">Ortiqcha belgilangan</option>
                                <option value="match">Mos</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Hisoblash</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Jadvalda qo'yilgan soat va o'qituvchi HEMIS da belgilagan soat taqqoslanadi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div id="summary-bar" style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span class="summary-chip" style="background:#e0e7ff;color:#3730a3;" title="Dars jadvaliga qo'yilgan jami soat">Jadvalda: <b id="sum-scheduled">0</b> soat</span>
                            <span class="summary-chip" style="background:#dbeafe;color:#1e40af;" title="O'qituvchilar HEMIS da belgilagan jami soat">O'qituvchi belgilagan: <b id="sum-hemis">0</b> soat</span>
                            <span class="summary-chip" id="sum-diff-chip" title="Jadvaldagi soat − O'qituvchi belgilagan soat">Farq: <b id="sum-diff">0</b> soat</span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;margin-left:auto;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 380px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="employee_name">Xodim FISH <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="faculty_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Kafedra <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="subject_name">Fan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="training_type">Mashg'ulot turi <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="lesson_pair_time">Juftlik vaqti <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="lesson_date">Dars sanasi <span class="sort-icon active">&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Dars jadvaliga qo'yilgan soat (1 juftlik = 2 akademik soat)"><a href="#" class="sort-link" data-sort="scheduled_hours">Jadvaldagi soat <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;" title="O'qituvchi HEMIS davomat nazoratida belgilagan soat"><a href="#" class="sort-link" data-sort="hemis_hours">O'qituvchi belgilagan soat <span class="text-xs" style="font-weight:400;opacity:0.7;">(HEMIS)</span> <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;" title="Jadvaldagi soat − O'qituvchi belgilagan soat"><a href="#" class="sort-link" data-sort="hours_diff">Farq <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;">Jurnal</th>
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
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        let currentSort = 'lesson_date';
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
            document.getElementById('current-semester-toggle').classList.toggle('active');
        }

        function getFilters() {
            return {
                education_type: $('#education_type').val() || '',
                faculty: $('#faculty').val() || '',
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                group: $('#group').val() || '',
                department: $('#department').val() || '',
                subject: $('#subject').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
                status_filter: $('#status_filter').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
                per_page: $('#per_page').val() || 50,
                sort: currentSort,
                direction: currentDirection,
            };
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            var parts = dateStr.split('-');
            if (parts.length === 3) return parts[2] + '.' + parts[1] + '.' + parts[0];
            return dateStr;
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function loadReport(page) {
            currentPage = page || 1;
            var params = getFilters();
            params.page = currentPage;

            if (!params.date_from || !params.date_to) {
                alert("Iltimos, 'Sanadan' va 'Sanagacha' ni tanlang.");
                return;
            }

            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();

            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.reports.lesson-hours.data") }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();

                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        return;
                    }

                    $('#total-badge').text('Jami: ' + res.total);
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');

                    var s = res.summary || {scheduled_total:0, hemis_total:0, diff_total:0};
                    $('#sum-scheduled').text(s.scheduled_total);
                    $('#sum-hemis').text(s.hemis_total);
                    $('#sum-diff').text(s.diff_total);
                    var chip = document.getElementById('sum-diff-chip');
                    if (s.diff_total === 0) {
                        chip.style.background = '#dcfce7';
                        chip.style.color = '#166534';
                    } else if (s.diff_total > 0) {
                        chip.style.background = '#fee2e2';
                        chip.style.color = '#b91c1c';
                    } else {
                        chip.style.background = '#fef3c7';
                        chip.style.color = '#92400e';
                    }

                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    var errMsg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
                    $('#empty-state').show().find('p:first').text(errMsg);
                }
            });
        }

        function hoursBadge(val) {
            var v = (val === undefined || val === null) ? 0 : val;
            if (v === 0) return '<span class="badge badge-status-no">0</span>';
            return '<span class="badge badge-hours">' + v + '</span>';
        }

        function diffBadge(diff, match) {
            var d = (diff === undefined || diff === null) ? 0 : diff;
            if (match) return '<span class="badge badge-status-yes">0</span>';
            var label = d > 0 ? ('−' + d) : ('+' + Math.abs(d));
            var title = d > 0 ? "HEMIS da " + d + " soat kam" : "HEMIS da " + Math.abs(d) + " soat ortiqcha";
            return '<span class="badge badge-status-no" title="' + title + '">' + label + '</span>';
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.employee_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.faculty_name) + '</span></td>';
                html += '<td><span class="text-cell" style="color:#92400e;">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell text-subject">' + esc(r.subject_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td><span class="badge badge-training">' + esc(r.training_type) + '</span></td>';
                html += '<td><span class="badge badge-pair">' + esc(r.lesson_pair_time) + '</span></td>';
                html += '<td><span class="badge badge-date">' + formatDate(r.lesson_date) + '</span></td>';
                html += '<td style="text-align:center;">' + hoursBadge(r.scheduled_hours) + '</td>';
                html += '<td style="text-align:center;">' + hoursBadge(r.hemis_hours) + '</td>';
                html += '<td style="text-align:center;">' + diffBadge(r.hours_diff, r.hours_match) + '</td>';
                html += '<td style="text-align:center;"><a href="/admin/journal/show/' + encodeURIComponent(r.group_db_id) + '/' + encodeURIComponent(r.subject_id) + '/' + encodeURIComponent(r.semester_code) + '" target="_blank" class="btn-view-journal" title="Jurnalga o\'tish"><svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
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

            var calFrom = new ScrollCalendar('date_from');
            var calTo = new ScrollCalendar('date_to');
            var defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() - 1);
            if (defaultDate.getDay() === 0) defaultDate.setDate(defaultDate.getDate() - 1);
            var yy = defaultDate.getFullYear();
            var mm = String(defaultDate.getMonth() + 1).padStart(2, '0');
            var dd = String(defaultDate.getDate()).padStart(2, '0');
            var defaultDateStr = yy + '-' + mm + '-' + dd;
            calFrom.setValue(defaultDateStr);
            calTo.setValue(defaultDateStr);

            function fp() { return { education_type: $('#education_type').val()||'', faculty_id: $('#faculty').val()||'', specialty_id: $('#specialty').val()||'', department_id: $('#department').val()||'', level_code: $('#level_code').val()||'', semester_code: $('#semester_code').val()||'', subject_id: $('#subject').val()||'', current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0' }; }
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

            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code');
            pdu('{{ route("admin.journal.get-subjects") }}', fp(), '#subject');
            pd('{{ route("admin.journal.get-groups") }}', fp(), '#group');
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; overflow: visible; position: relative; z-index: 20; }
        .filter-row { display: flex; gap: 10px; flex-wrap: nowrap; margin-bottom: 10px; align-items: flex-end; overflow: visible; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; height: 36px; }
        .btn-calc:hover:not(:disabled) { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); outline: none; }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic.select2-container--focus .select2-selection--single,
        .select2-container--classic.select2-container--open .select2-selection--single { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); outline: none; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow b { border-color: #64748b transparent transparent transparent; }
        .select2-container--classic.select2-container--open .select2-selection--single .select2-selection__arrow b { border-color: transparent transparent #64748b transparent; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #2b5ea7; box-shadow: 0 8px 24px rgba(0,0,0,0.12); overflow: hidden; }
        .select2-container--classic .select2-search--dropdown .select2-search__field { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; font-size: 0.8rem; outline: none; }
        .select2-container--classic .select2-search--dropdown .select2-search__field:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .select2-container--classic .select2-results__option { padding: 8px 12px; }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; color: #fff; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; }
        .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
        .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        .summary-chip { padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; }

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 14px 12px 14px 16px; width: 44px; }
        .sort-link { display: inline-flex; align-items: center; gap: 4px; color: #334155; text-decoration: none; cursor: pointer; }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 8px; opacity: 0.4; }
        .sort-icon.active { font-size: 11px; opacity: 1; color: #ef4444; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .journal-table td { padding: 10px 12px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-date { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; white-space: nowrap; font-weight: 500; }
        .badge-training { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; white-space: nowrap; }
        .badge-pair { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .badge-status-yes { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .badge-status-no { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .badge-hours { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 4px 12px; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-subject { color: #0f172a; font-weight: 700; font-size: 12.5px; max-width: 260px; white-space: normal; word-break: break-word; }

        .btn-view-journal { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; color: #2b5ea7; background: #eff6ff; border: 1px solid #bfdbfe; transition: all 0.2s; text-decoration: none; }
        .btn-view-journal:hover { background: #2b5ea7; color: #fff; border-color: #2b5ea7; transform: scale(1.1); }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }
    </style>
</x-app-layout>
