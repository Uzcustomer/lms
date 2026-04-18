<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Dars jadval mosligi hisoboti
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
                            <select id="faculty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 90px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
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
                        <div class="filter-item" style="min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#6d28d9;"></span> Dars turi</label>
                            <select id="training_types" class="select2-multi" multiple style="width: 100%;">
                                @foreach($trainingTypes as $tt)
                                    <option value="{{ $tt->training_type_code }}">{{ $tt->training_type_name }}</option>
                                @endforeach
                            </select>
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
                        <div class="filter-item" style="flex: 1; min-width: 240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Ma'ruza xonalari</label>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <select id="auditorium" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                                <button type="button" id="btn-sync-auditoriums" class="btn-sync" onclick="syncAuditoriums()" title="HEMIS dan sinxronlash">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
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
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Natijalar shu yerda ko'rsatiladi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                            <div style="margin-left:auto;display:flex;gap:8px;">
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel('excel')" disabled title="Umumiy Excel (har fan+guruh bir qator)">
                                    <svg style="width:17px;height:17px;" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 8V3.5L18.5 9H14a1 1 0 0 1-1-1zM8.2 13l1.6 2.4L11.4 13h1.8l-2.4 3.5L13.4 20H11.6l-1.8-2.7L8 20H6.2l2.6-3.7L6.4 13h1.8z"/></svg>
                                    Excel (umumiy)
                                </button>
                                <button type="button" id="btn-excel-lessons" class="btn-excel-lessons" onclick="downloadExcel('excel_lessons')" disabled title="Batafsil Excel: har bir dars o'z ustuni bilan">
                                    <svg style="width:17px;height:17px;" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 8V3.5L18.5 9H14a1 1 0 0 1-1-1zM8.2 13l1.6 2.4L11.4 13h1.8l-2.4 3.5L13.4 20H11.6l-1.8-2.7L8 20H6.2l2.6-3.7L6.4 13h1.8z"/></svg>
                                    Excel (batafsil)
                                </button>
                            </div>
                        </div>
                        <div style="max-height:calc(100vh - 380px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table" id="report-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="faculty_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="semester_name">Semestr <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="subject_name">Fan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="planned_hours">Ajratilgan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="scheduled_hours">Jadvalda <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="ktr_hours">KTR <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="farq">Farq (ajrat.) <span class="sort-icon active">&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="ktr_farq">Farq (KTR) <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                    </tr>
                                    <tr class="cf-filter-row">
                                        <th></th>
                                        <th><button type="button" class="col-filter-btn" data-col="faculty_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="specialty_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="level_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="semester_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="subject_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="group_name"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="planned_hours"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="scheduled_hours"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="ktr_hours"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="farq"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
                                        <th><button type="button" class="col-filter-btn" data-col="ktr_farq"><span class="cf-label">Barchasi</span><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg></button></th>
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

    <!-- Ustun filtri popup (Excel uslubi) -->
    <div id="col-filter-popup" class="cf-popup" style="display:none;"></div>

    <!-- KTR vs HEMIS solishtirish modali -->
    <div id="ktr-compare-modal-overlay" class="kcmp-overlay" style="display:none;" onclick="closeKtrCompareModal(event)">
        <div class="kcmp-modal" onclick="event.stopPropagation()">
            <div class="kcmp-modal-header">
                <div>
                    <h3 class="kcmp-modal-title" id="ktr-compare-modal-title">Fan nomi</h3>
                    <div class="kcmp-modal-subtitle" id="ktr-compare-modal-subtitle"></div>
                </div>
                <button type="button" class="kcmp-modal-close" onclick="closeKtrCompareModal()" aria-label="Yopish">&times;</button>
            </div>
            <div class="kcmp-modal-body" id="ktr-compare-modal-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        let currentSort = 'farq';
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
            var btn = document.getElementById('current-semester-toggle');
            btn.classList.toggle('active');
        }

        function getFilters() {
            var params = {
                education_type: $('#education_type').val() || '',
                faculty: $('#faculty').val() || '',
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                group: $('#group').val() || '',
                department: $('#department').val() || '',
                subject: $('#subject').val() || '',
                auditorium: $('#auditorium').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
                per_page: $('#per_page').val() || 50,
                sort: currentSort,
                direction: currentDirection,
            };
            var tt = $('#training_types').val();
            if (tt && tt.length > 0) params.training_types = tt;

            // Ustun filtrlari - backendga yuborish (barcha sahifalarga ta'sir qilishi uchun)
            var colFiltersObj = {};
            var hasColFilter = false;
            for (var col in columnFilters) {
                var allowed = columnFilters[col];
                if (allowed && allowed.size !== undefined) {
                    var arr = [];
                    allowed.forEach(function(v) { arr.push(v); });
                    colFiltersObj[col] = arr;
                    hasColFilter = true;
                }
            }
            if (hasColFilter) params.col_filters = colFiltersObj;
            return params;
        }

        function syncAuditoriums() {
            var btn = document.getElementById('btn-sync-auditoriums');
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.innerHTML = '<svg style="width:14px;height:14px;animation:spin 0.8s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';

            $.ajax({
                url: '{{ route("admin.reports.schedule-report.sync-auditoriums") }}',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                timeout: 60000,
                success: function(res) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
                    loadAuditoriums();
                    alert(res.message || 'Sinxronlashtirildi');
                },
                error: function(xhr) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
                    var errMsg = 'Xatolik yuz berdi';
                    try { errMsg = JSON.parse(xhr.responseText).error || errMsg; } catch(e) {}
                    alert(errMsg);
                }
            });
        }

        function loadAuditoriums() {
            var el = '#auditorium';
            $(el).empty().append('<option value="">Barchasi</option>');
            $.get('{{ route("admin.reports.schedule-report.get-auditoriums") }}', {}, function(d) {
                $.each(d, function(k, v) {
                    $(el).append('<option value="' + k + '">' + v + '</option>');
                });
            });
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
                url: '{{ route("admin.reports.schedule-report.data") }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    // Server qaytargan ustun qiymatlarini saqlash (filtr dropdown'lari uchun)
                    if (res.column_values) {
                        serverColumnValues = res.column_values;
                    }

                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel, #btn-excel-lessons').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    $('#total-badge').text('Jami: ' + res.total);
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#btn-excel, #btn-excel-lessons').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    var errMsg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.error) errMsg = resp.error + (resp.file ? ' (' + resp.file + ')' : '');
                    } catch(e) {}
                    console.error('Schedule report error:', xhr.responseText);
                    $('#empty-state').show().find('p:first').text(errMsg);
                }
            });
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function farqBadge(farq) {
            if (farq === null || farq === undefined) {
                return '<span style="color:#94a3b8;">-</span>';
            }
            if (farq === 0) {
                return '<span class="badge badge-status-full" style="font-size:13px;">' + farq + '</span>';
            } else if (farq > 0) {
                return '<span class="badge badge-status-partial" style="font-size:13px;">' + farq + '</span>';
            }
            return '<span class="badge badge-status-none" style="font-size:13px;">' + farq + '</span>';
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td data-filter-col="faculty_name"><span class="text-cell text-emerald">' + esc(r.faculty_name) + '</span></td>';
                html += '<td data-filter-col="specialty_name"><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
                html += '<td data-filter-col="level_name"><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
                html += '<td data-filter-col="semester_name"><span class="badge badge-teal">' + esc(r.semester_name) + '</span></td>';
                html += '<td data-filter-col="subject_name"><a href="#" class="subject-link" onclick="openKtrCompareModal(event, ' + r.cs_id + ', ' + (r.group_id || 0) + ')">' + esc(r.subject_name) + '</a></td>';
                html += '<td data-filter-col="group_name"><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td data-filter-col="planned_hours" style="text-align:center;font-weight:600;color:#475569;">' + r.planned_hours + '</td>';
                html += '<td data-filter-col="scheduled_hours" style="text-align:center;font-weight:600;color:#475569;">' + r.scheduled_hours + '</td>';
                if (r.ktr_exists) {
                    html += '<td data-filter-col="ktr_hours" style="text-align:center;font-weight:600;color:#475569;">' + r.ktr_hours + '</td>';
                } else {
                    html += '<td data-filter-col="ktr_hours" style="text-align:center;"><span class="badge badge-status-none" style="font-size:11px;">yo\'q</span></td>';
                }
                html += '<td data-filter-col="farq" style="text-align:center;">' + farqBadge(r.farq) + '</td>';
                if (r.ktr_exists) {
                    html += '<td data-filter-col="ktr_farq" style="text-align:center;">' + farqBadge(r.ktr_farq) + '</td>';
                } else {
                    html += '<td data-filter-col="ktr_farq" style="text-align:center;color:#94a3b8;">-</td>';
                }
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function openKtrCompareModal(e, csId, groupId) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            var params = getFilters();
            $('#ktr-compare-modal-title').text('Yuklanmoqda...');
            $('#ktr-compare-modal-subtitle').text('');
            $('#ktr-compare-modal-body').html('<div style="padding:60px 20px;text-align:center;"><div class="spinner"></div><p style="color:#2b5ea7;font-size:13px;margin-top:14px;font-weight:600;">Yuklanmoqda...</p></div>');
            $('#ktr-compare-modal-overlay').fadeIn(150);

            $.ajax({
                url: '{{ url('admin/reports/schedule-report/detail') }}/' + csId,
                type: 'GET',
                data: {
                    group: groupId || params.group || '',
                    date_from: params.date_from || '',
                    date_to: params.date_to || '',
                    auditorium: params.auditorium || '',
                },
                success: function(res) {
                    renderKtrCompareModal(res);
                },
                error: function(xhr) {
                    var msg = 'Xatolik yuz berdi';
                    try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
                    $('#ktr-compare-modal-body').html('<div style="padding:40px 20px;text-align:center;color:#dc2626;">' + esc(msg) + '</div>');
                }
            });
        }

        function closeKtrCompareModal(e) {
            if (e && e.target && e.target.id && e.target.id !== 'ktr-compare-modal-overlay') return;
            $('#ktr-compare-modal-overlay').fadeOut(120);
        }

        function renderKtrCompareModal(res) {
            $('#ktr-compare-modal-title').text(res.subject_name || '');
            $('#ktr-compare-modal-subtitle').text('Guruh: ' + (res.group_name || '-'));

            if (!res.ktr_exists) {
                $('#ktr-compare-modal-body').html(
                    '<div style="padding:40px 20px;text-align:center;">' +
                    '<svg style="width:48px;height:48px;margin:0 auto 12px;color:#fbbf24;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>' +
                    '<p style="color:#92400e;font-size:15px;font-weight:600;">Bu fan uchun KTR yaratilmagan</p>' +
                    '<p style="color:#94a3b8;font-size:12.5px;margin-top:6px;">KTR (Kalendar tematik reja) sahifasida reja yaratilganidan keyin solishtirish imkoniyati paydo bo\'ladi.</p>' +
                    '</div>'
                );
                return;
            }

            var types = res.training_types || {};
            var typeCodes = Object.keys(types);
            if (typeCodes.length === 0) {
                $('#ktr-compare-modal-body').html('<div style="padding:40px 20px;text-align:center;color:#64748b;">Dars turlari topilmadi</div>');
                return;
            }

            var html = '<div style="overflow-x:auto;">';
            html += '<table class="ktr-cmp-table"><thead>';
            html += '<tr><th rowspan="2" class="wk-col">Dars</th>';
            typeCodes.forEach(function(code, idx) {
                var c = idx % 6;
                html += '<th colspan="4" class="tt-col-head tt-head-' + c + '">' + esc(types[code].name) + '</th>';
            });
            html += '</tr><tr>';
            typeCodes.forEach(function(code, idx) {
                var c = idx % 6;
                html += '<th class="sub-head tt-sub-' + c + '">HEMIS</th>';
                html += '<th class="sub-head tt-sub-' + c + '">KTR</th>';
                html += '<th class="sub-head tt-sub-' + c + '" title="O\'qituvchi belgilagan soat">Belgi</th>';
                html += '<th class="sub-head tt-sub-' + c + '">Farq</th>';
            });
            html += '</tr></thead><tbody>';

            var totalHemis = {}, totalKtr = {}, totalMarked = {};
            typeCodes.forEach(function(c) { totalHemis[c] = 0; totalKtr[c] = 0; totalMarked[c] = 0; });

            function fmt(v) {
                if (v === null || v === undefined) return 0;
                if (Math.abs(v - Math.round(v)) < 0.01) return Math.round(v);
                return Math.round(v * 10) / 10;
            }

            (res.lessons || []).forEach(function(wk) {
                var darsLabel = wk.lesson + '-dars' + (wk.date ? ' <span class="dars-date">(' + wk.date + ')</span>' : '');
                html += '<tr><td class="wk-col">' + darsLabel + '</td>';
                typeCodes.forEach(function(code, idx) {
                    var c = idx % 6;
                    var cell = (wk.cells && wk.cells[code]) ? wk.cells[code] : {hemis:0, ktr:0, marked:0, diff:0};
                    var markedV = cell.marked || 0;
                    totalHemis[code] += (cell.hemis || 0);
                    if (cell.ktr) totalKtr[code] += (cell.ktr || 0);
                    totalMarked[code] += markedV;
                    var diff = cell.diff;
                    var diffNum = diff === null || diff === undefined ? 0 : diff;
                    var diffCls = Math.abs(diffNum) < 0.01 ? 'diff-zero' : (diffNum > 0 ? 'diff-pos' : 'diff-neg');
                    var diffDisp = diff === null ? '-' : (diffNum > 0 ? '+' + fmt(diffNum) : fmt(diffNum));
                    html += '<td class="num-cell tt-body-' + c + '">' + fmt(cell.hemis) + '</td>';
                    html += '<td class="num-cell tt-body-' + c + '">' + fmt(cell.ktr) + '</td>';
                    var markedCls = markedV > 0 ? 'marked-yes' : '';
                    html += '<td class="num-cell tt-body-' + c + ' ' + markedCls + '">' + fmt(markedV) + '</td>';
                    html += '<td class="num-cell tt-body-' + c + ' ' + diffCls + '">' + diffDisp + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody><tfoot><tr><td class="wk-col">Jami</td>';
            var serverTotals = res.totals || {};
            typeCodes.forEach(function(code, idx) {
                var c = idx % 6;
                var t = serverTotals[code] || { hemis: totalHemis[code] || 0, ktr: totalKtr[code] || 0, marked: totalMarked[code] || 0, diff: 0 };
                var h = t.hemis || 0;
                var kt = t.ktr === null || t.ktr === undefined ? 0 : t.ktr;
                var mk = t.marked || 0;
                var d = t.diff === null || t.diff === undefined ? 0 : t.diff;
                var diffCls = Math.abs(d) < 0.01 ? 'diff-zero' : (d > 0 ? 'diff-pos' : 'diff-neg');
                html += '<td class="num-cell tt-foot-' + c + '">' + fmt(h) + '</td>';
                html += '<td class="num-cell tt-foot-' + c + '">' + fmt(kt) + '</td>';
                html += '<td class="num-cell tt-foot-' + c + '">' + fmt(mk) + '</td>';
                html += '<td class="num-cell tt-foot-' + c + ' ' + diffCls + '">' + (d > 0 ? '+' + fmt(d) : fmt(d)) + '</td>';
            });
            html += '</tr></tfoot></table></div>';
            html += '<div style="padding:10px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11.5px;color:#64748b;display:flex;gap:14px;flex-wrap:wrap;">' +
                '<span><span class="legend-dot" style="background:#16a34a;"></span> Farq = 0 (mos)</span>' +
                '<span><span class="legend-dot" style="background:#d97706;"></span> Farq &gt; 0 (KTR ko\'p)</span>' +
                '<span><span class="legend-dot" style="background:#dc2626;"></span> Farq &lt; 0 (HEMIS ko\'p)</span>' +
                '<span><span class="legend-dot" style="background:#bbf7d0;border:1px solid #16a34a;"></span> Belgi: o\'qituvchi belgilagan dars</span>' +
                '</div>';

            $('#ktr-compare-modal-body').html(html);
        }

        // Ustun filtri holati: {columnName: Set<allowedValue>}
        // Agar col kalit ma'lumotda bo'lmasa, u ustun bo'yicha filtr yo'q (barchasi ko'rinadi)
        var columnFilters = {};

        function applyColumnFilters() {
            // Server-side filtr: hisobotni qayta yuklash
            updateFilterIcons();
            loadReport(1);
        }

        // Backend qaytargan ustun qiymatlari (filtrgacha) - dropdown'lar uchun
        var serverColumnValues = {};

        function updateFilterIcons() {
            $('.col-filter-btn').each(function() {
                var col = $(this).data('col');
                var filter = columnFilters[col];
                var $label = $(this).find('.cf-label');
                if (!filter) {
                    $(this).removeClass('cf-active');
                    $label.text('Barchasi');
                } else {
                    $(this).addClass('cf-active');
                    if (filter.size === 0) {
                        $label.text('(tanlanmagan)');
                    } else if (filter.size === 1) {
                        $label.text([...filter][0] || '(bo\'sh)');
                    } else {
                        $label.text('(' + filter.size + ' tanlangan)');
                    }
                }
            });
        }

        function openColFilter(e, col) {
            e.preventDefault();
            e.stopPropagation();
            // Server qaytargan barcha sahifalardagi noyob qiymatlardan foydalanamiz
            var keys = (serverColumnValues[col] || []).slice();
            // Son ko'rinishida bo'lsa raqam tartibida
            var allNum = keys.length > 0 && keys.every(function(k) { return k === '' || /^-?\d+(\.\d+)?$/.test(String(k).replace(',', '.')); });
            if (allNum) {
                keys.sort(function(a, b) { return parseFloat(String(a).replace(',', '.')) - parseFloat(String(b).replace(',', '.')); });
            } else {
                keys.sort(function(a, b) { return String(a).localeCompare(String(b), 'uz'); });
            }

            var active = columnFilters[col] || null;
            var html = '';
            html += '<div class="cf-head"><input type="text" class="cf-search" placeholder="Qidirish..." autocomplete="off" /></div>';
            html += '<div class="cf-actions"><a href="#" class="cf-select-all">Barchasini belgilash</a><a href="#" class="cf-clear-all">Tozalash</a></div>';
            html += '<div class="cf-list">';
            keys.forEach(function(v) {
                var sv = String(v);
                var checked = (active === null || active.has(sv)) ? 'checked' : '';
                var display = sv === '' ? '(bo\'sh)' : sv;
                html += '<label class="cf-item"><input type="checkbox" value="' + esc(sv).replace(/"/g,'&quot;') + '" ' + checked + '><span>' + esc(display) + '</span></label>';
            });
            html += '</div>';
            html += '<div class="cf-footer">';
            html += '<button type="button" class="cf-btn cf-ok" data-col="' + col + '">OK</button>';
            html += '<button type="button" class="cf-btn cf-cancel">Bekor</button>';
            html += '</div>';

            var popup = document.getElementById('col-filter-popup');
            popup.innerHTML = html;
            popup.style.display = 'block';

            // Tugma oldiga joylashtirish
            var btn = e.currentTarget || e.target.closest('.col-filter-btn');
            var rect = btn.getBoundingClientRect();
            var popupRect = popup.getBoundingClientRect();
            var left = rect.left;
            if (left + popupRect.width > window.innerWidth - 8) {
                left = window.innerWidth - popupRect.width - 8;
            }
            popup.style.left = Math.max(8, left) + 'px';
            popup.style.top = (rect.bottom + window.scrollY + 4) + 'px';

            setTimeout(function() { popup.querySelector('.cf-search')?.focus(); }, 30);
        }

        function closeColFilter() {
            var popup = document.getElementById('col-filter-popup');
            if (popup) popup.style.display = 'none';
        }

        function downloadExcel(type) {
            var params = getFilters();
            params.export = type || 'excel';
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.schedule-report.data") }}?' + query;
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
            // Kalendarlarni yaratish
            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            // Ustun filtri tugmasini bosganda popup ochiladi
            $(document).on('click', '.col-filter-btn', function(e) {
                openColFilter(e, $(this).data('col'));
            });

            // Popup ichidagi amallar
            $(document).on('input', '.cf-search', function() {
                var q = ($(this).val() || '').toLowerCase();
                $('.cf-list .cf-item').each(function() {
                    var txt = $(this).find('span').first().text().toLowerCase();
                    $(this).toggle(txt.indexOf(q) !== -1);
                });
            });
            $(document).on('click', '.cf-select-all', function(e) {
                e.preventDefault();
                $('.cf-list .cf-item:visible input').prop('checked', true);
            });
            $(document).on('click', '.cf-clear-all', function(e) {
                e.preventDefault();
                $('.cf-list .cf-item:visible input').prop('checked', false);
            });
            $(document).on('click', '.cf-cancel', function() { closeColFilter(); });
            $(document).on('click', '.cf-ok', function() {
                var col = $(this).data('col');
                var total = $('.cf-list .cf-item input').length;
                var checked = $('.cf-list .cf-item input:checked');
                if (checked.length === 0) {
                    // Hech narsa belgilanmagan - hech qanday qator ko'rinmasin
                    columnFilters[col] = new Set();
                } else if (checked.length === total) {
                    delete columnFilters[col];
                } else {
                    var selected = new Set();
                    checked.each(function() { selected.add($(this).val()); });
                    columnFilters[col] = selected;
                }
                applyColumnFilters();
                closeColFilter();
            });

            // Popup tashqariga bosilganda yopish
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#col-filter-popup').length &&
                    !$(e.target).closest('.col-filter-btn').length) {
                    closeColFilter();
                }
            });

            // Popup tugmalari sort eventini chaqirmasligi uchun
            $(document).on('click', '.col-filter-btn', function(e) { e.stopPropagation(); });

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
            // Multi-select for training types with checkboxes
            var $ttSelect = $('.select2-multi');
            $ttSelect.select2({
                theme: 'classic', width: '100%', placeholder: 'Barchasi', allowClear: true, matcher: fuzzyMatcher,
                closeOnSelect: false,
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    var isSelected = (($('#training_types').val() || []).indexOf(data.id) !== -1);
                    var $el = $('<span style="display:flex;align-items:center;gap:8px;padding:2px 0;cursor:pointer;">' +
                        '<span class="cb-box" style="width:16px;height:16px;border:2px solid ' + (isSelected ? '#6d28d9' : '#cbd5e1') + ';border-radius:4px;display:flex;align-items:center;justify-content:center;background:' + (isSelected ? '#6d28d9' : '#fff') + ';flex-shrink:0;transition:all .15s;">' +
                        (isSelected ? '<svg width="10" height="10" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" fill="none"/></svg>' : '') +
                        '</span>' +
                        '<span>' + data.text + '</span></span>');
                    return $el;
                }
            }).on('select2:select select2:unselect', function() {
                var $sel = $(this);
                setTimeout(function() { $sel.select2('close').select2('open'); }, 1);
            }).on('select2:open', function() {
                setTimeout(function() {
                    var $dropdown = $('.select2-container--open .select2-results');
                    if ($dropdown.length && !$dropdown.find('.tt-select-actions').length) {
                        var allVals = [];
                        $('#training_types option').each(function() { if (this.value) allVals.push(this.value); });
                        var $bar = $('<div class="tt-select-actions" style="display:flex;gap:6px;padding:6px 8px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">' +
                            '<button type="button" class="tt-act-btn tt-check-all" style="flex:1;">Barchasini belgilash</button>' +
                            '<button type="button" class="tt-act-btn tt-uncheck-all" style="flex:1;">Barchasini olib tashlash</button>' +
                            '</div>');
                        $bar.find('.tt-check-all').on('mousedown', function(e) {
                            e.preventDefault();
                            $('#training_types').val(allVals).trigger('change');
                            setTimeout(function() { $ttSelect.select2('close').select2('open'); }, 1);
                        });
                        $bar.find('.tt-uncheck-all').on('mousedown', function(e) {
                            e.preventDefault();
                            $('#training_types').val([]).trigger('change');
                            setTimeout(function() { $ttSelect.select2('close').select2('open'); }, 1);
                        });
                        $dropdown.prepend($bar);
                    }
                }, 1);
            });

            // Cascading dropdowns
            function fp() { return { education_type: $('#education_type').val()||'', faculty_id: $('#faculty').val()||'', specialty_name: $('#specialty').val()||'', department_id: $('#department').val()||'', level_code: $('#level_code').val()||'', semester_code: $('#semester_code').val()||'', subject_id: $('#subject').val()||'', current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0' }; }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }
            // Yo'nalishlar uchun: nom dubllari bo'lishi mumkin bo'lgani sababli option qiymati ham nom bo'ladi
            function pdSpec(url, p, el, cb) { $.get(url, p, function(d) { var names = {}; $.each(d, function(k,v){ if (v) names[v] = true; }); Object.keys(names).sort().forEach(function(n){ $(el).append('<option value="'+n+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdSpec('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
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
            pdSpec('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code');
            pdu('{{ route("admin.journal.get-subjects") }}', fp(), '#subject');
            pd('{{ route("admin.journal.get-groups") }}', fp(), '#group');
            loadAuditoriums();
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }
        .btn-excel-lessons { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(8,145,178,0.3); height: 36px; }
        .btn-excel-lessons:hover:not(:disabled) { background: linear-gradient(135deg, #0e7490, #0891b2); box-shadow: 0 4px 12px rgba(8,145,178,0.4); transform: translateY(-1px); }
        .btn-excel-lessons:disabled { cursor: not-allowed; opacity: 0.5; }

        .btn-sync { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #818cf8); color: #fff; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(99,102,241,0.3); flex-shrink: 0; }
        .btn-sync:hover:not(:disabled) { background: linear-gradient(135deg, #4f46e5, #6366f1); box-shadow: 0 4px 10px rgba(99,102,241,0.4); transform: translateY(-1px); }
        .btn-sync:disabled { cursor: not-allowed; opacity: 0.5; }

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

        .select2-container--classic .select2-selection--multiple { min-height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); padding: 2px 4px; }
        .select2-container--classic .select2-selection--multiple:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice { background: linear-gradient(135deg, #6d28d9, #7c3aed); color: #fff; border: none; border-radius: 5px; padding: 2px 8px; font-size: 11px; font-weight: 600; margin: 2px; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove { color: #e0d0ff; margin-right: 4px; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove:hover { color: #fff; }
        .select2-container--classic .select2-selection--multiple .select2-search__field { font-size: 0.8rem; }

        .tt-act-btn { padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; font-size: 11px; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.15s; }
        .tt-act-btn:hover { background: #6d28d9; color: #fff; border-color: #6d28d9; }

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
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }

        .badge-status-full { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .badge-status-partial { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .badge-status-none { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 12px; font-size: 12px; font-weight: 700; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
        .text-subject { color: #0f172a; font-weight: 700; font-size: 12.5px; max-width: 260px; white-space: normal; word-break: break-word; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }

        /* Ustun filtri (Excel uslubi) - sarlavha ostida katak shaklida */
        .cf-filter-row th { padding: 4px 6px; background: #fff; border-bottom: 1px solid #cbd5e1; border-top: 1px solid #e2e8f0; }
        .col-filter-btn { display: inline-flex; align-items: center; justify-content: space-between; gap: 6px; width: 100%; min-width: 60px; height: 28px; padding: 2px 8px; background: #fff; border: 1px solid #cbd5e1; border-radius: 5px; cursor: pointer; font-size: 11.5px; font-weight: 500; color: #1e293b; transition: all 0.15s; text-align: left; }
        .col-filter-btn:hover { background: #eff6ff; border-color: #2b5ea7; }
        .col-filter-btn svg { width: 14px; height: 14px; color: #64748b; flex-shrink: 0; }
        .col-filter-btn:hover svg { color: #2b5ea7; }
        .col-filter-btn .cf-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #64748b; font-weight: 500; }
        .col-filter-btn.cf-active { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-color: #2b5ea7; color: #1e3a8a; font-weight: 700; }
        .col-filter-btn.cf-active .cf-label { color: #1e3a8a; font-weight: 700; }
        .col-filter-btn.cf-active svg { color: #2b5ea7; }

        .cf-popup { position: absolute; z-index: 999; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 28px rgba(0,0,0,0.18); min-width: 240px; max-width: 320px; padding: 8px; font-size: 12.5px; }
        .cf-head { padding: 2px 2px 6px; }
        .cf-search { width: 100%; height: 30px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 12px; outline: none; }
        .cf-search:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .cf-actions { display: flex; gap: 8px; padding: 4px 2px 6px; border-bottom: 1px solid #e2e8f0; }
        .cf-actions a { color: #2b5ea7; font-size: 11.5px; font-weight: 600; text-decoration: none; }
        .cf-actions a:hover { text-decoration: underline; }
        .cf-list { max-height: 260px; overflow-y: auto; padding: 4px 0; }
        .cf-item { display: flex; align-items: center; gap: 6px; padding: 4px 6px; cursor: pointer; border-radius: 4px; font-size: 12px; color: #1e293b; line-height: 1.3; }
        .cf-item:hover { background: #eff6ff; }
        .cf-item input { margin: 0; cursor: pointer; flex-shrink: 0; }
        .cf-item span:first-of-type { flex: 1; word-break: break-word; }
        .cf-item .cf-count { color: #94a3b8; font-size: 10.5px; font-weight: 600; flex-shrink: 0; }
        .cf-footer { display: flex; gap: 6px; padding: 6px 2px 2px; border-top: 1px solid #e2e8f0; margin-top: 4px; }
        .cf-btn { flex: 1; padding: 6px 10px; border: none; border-radius: 5px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
        .cf-ok { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; }
        .cf-ok:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); }
        .cf-cancel { background: #f1f5f9; color: #475569; }
        .cf-cancel:hover { background: #e2e8f0; }

        .subject-link { color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none; border-bottom: 1.5px dashed #6d28d9; cursor: pointer; max-width: 260px; display: inline-block; word-break: break-word; }
        .subject-link:hover { color: #6d28d9; border-bottom-color: #7c3aed; }

        /* KTR compare modal */
        .kcmp-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.55); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(2px); }
        .kcmp-modal { background: #fff; border-radius: 12px; max-width: 1100px; width: 100%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 16px 48px rgba(0,0,0,0.24); }
        .kcmp-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #f5f3ff, #ede9fe); }
        .kcmp-modal-title { font-size: 17px; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.3; }
        .kcmp-modal-subtitle { font-size: 12.5px; color: #6d28d9; font-weight: 600; margin-top: 4px; }
        .kcmp-modal-close { background: transparent; border: none; font-size: 28px; color: #64748b; cursor: pointer; line-height: 1; padding: 0 6px; border-radius: 6px; transition: all 0.15s; }
        .kcmp-modal-close:hover { background: #fee2e2; color: #dc2626; }
        .kcmp-modal-body { flex: 1; overflow: auto; }

        .ktr-cmp-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
        .ktr-cmp-table thead tr:first-child th { padding: 10px 8px; font-size: 12px; font-weight: 700; text-align: center; }
        .ktr-cmp-table thead tr:last-child th.sub-head { padding: 6px 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; text-align: center; white-space: nowrap; }
        .ktr-cmp-table th.wk-col { background: linear-gradient(135deg, #1e3a5f, #2b5ea7); color: #fff; border-bottom: 1px solid #2b5ea7; }
        .ktr-cmp-table td.wk-col { background: #f8fafc; font-weight: 700; color: #1e3a5f; padding: 8px 10px; border-right: 1px solid #e2e8f0; text-align: center; white-space: nowrap; }
        .dars-date { font-weight: 500; color: #64748b; font-size: 11px; }
        .ktr-cmp-table td.num-cell { padding: 7px 8px; text-align: center; font-weight: 600; color: #334155; border-bottom: 1px solid #f1f5f9; }
        .ktr-cmp-table tbody tr:hover td.num-cell { filter: brightness(0.96); }
        .ktr-cmp-table tbody tr:hover td.wk-col { background: #dbeafe; }
        .ktr-cmp-table td.diff-zero { color: #16a34a; }
        .ktr-cmp-table td.diff-pos { color: #d97706; background: #fffbeb !important; }
        .ktr-cmp-table td.diff-neg { color: #dc2626; background: #fef2f2 !important; }
        .ktr-cmp-table tfoot td { padding: 10px 8px; font-weight: 800; border-top: 2px solid #cbd5e1; text-align: center; }
        .ktr-cmp-table tfoot td.wk-col { background: #1e3a5f; color: #fff; }
        /* Har bir dars turi guruhi 4 sub-ustun (HEMIS|KTR|Belgi|Farq) bilan. Dars ustuni 1-chi.
           Guruhlar orasiga qalin chiziq: 2-ustun, 6, 10, 14, ... (4n+2) */
        .ktr-cmp-table th.tt-col-head { border-left: 3px solid #1e3a5f; }
        .ktr-cmp-table tbody td.num-cell:nth-child(4n+2),
        .ktr-cmp-table thead tr:last-child th.sub-head:nth-child(4n+2),
        .ktr-cmp-table tfoot td.num-cell:nth-child(4n+2) { border-left: 3px solid #1e3a5f; }

        /* Mashg'ulot turlari uchun ranglar */
        .tt-head-0 { background: linear-gradient(135deg, #bfdbfe, #93c5fd); color: #1e3a8a; border-bottom: 1px solid #93c5fd; }
        .tt-sub-0  { background: #dbeafe; color: #1e40af; border-bottom: 2px solid #93c5fd; }
        .tt-body-0 { background: #eff6ff; }
        .tt-foot-0 { background: #dbeafe; color: #1e3a8a; }

        .tt-head-1 { background: linear-gradient(135deg, #bbf7d0, #86efac); color: #064e3b; border-bottom: 1px solid #86efac; }
        .tt-sub-1  { background: #d1fae5; color: #065f46; border-bottom: 2px solid #86efac; }
        .tt-body-1 { background: #ecfdf5; }
        .tt-foot-1 { background: #d1fae5; color: #064e3b; }

        .tt-head-2 { background: linear-gradient(135deg, #fbcfe8, #f9a8d4); color: #831843; border-bottom: 1px solid #f9a8d4; }
        .tt-sub-2  { background: #fce7f3; color: #9f1239; border-bottom: 2px solid #f9a8d4; }
        .tt-body-2 { background: #fdf2f8; }
        .tt-foot-2 { background: #fce7f3; color: #831843; }

        .tt-head-3 { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #7c2d12; border-bottom: 1px solid #fdba74; }
        .tt-sub-3  { background: #ffedd5; color: #9a3412; border-bottom: 2px solid #fdba74; }
        .tt-body-3 { background: #fff7ed; }
        .tt-foot-3 { background: #ffedd5; color: #7c2d12; }

        .tt-head-4 { background: linear-gradient(135deg, #c7d2fe, #a5b4fc); color: #312e81; border-bottom: 1px solid #a5b4fc; }
        .tt-sub-4  { background: #e0e7ff; color: #3730a3; border-bottom: 2px solid #a5b4fc; }
        .tt-body-4 { background: #eef2ff; }
        .tt-foot-4 { background: #e0e7ff; color: #312e81; }

        .tt-head-5 { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #1e293b; border-bottom: 1px solid #cbd5e1; }
        .tt-sub-5  { background: #e2e8f0; color: #334155; border-bottom: 2px solid #cbd5e1; }
        .tt-body-5 { background: #f8fafc; }
        .tt-foot-5 { background: #e2e8f0; color: #1e293b; }

        .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
        .ktr-cmp-table td.marked-yes { background: #bbf7d0 !important; color: #14532d; font-weight: 700; }
    </style>
</x-app-layout>
