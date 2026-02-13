<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Diagnostika (Test markazi)
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $fac)
                                    <option value="{{ $fac }}">{{ $fac }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="direction" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($directions as $dir)
                                    <option value="{{ $dir }}">{{ $dir }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Semestr</label>
                            <select id="semester" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($semesters as $sem)
                                    <option value="{{ $sem }}">{{ $sem }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Quiz turi</label>
                            <select id="quiz_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($quizTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Talaba</label>
                            <input type="text" id="student_name" placeholder="Ism yoki ID" class="filter-input">
                        </div>
                        <div class="filter-item" style="max-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="max-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item filter-buttons">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-search" class="btn-calc" onclick="loadData(1)">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="action-left">
                        <span id="selection-info" class="sel-info">
                            <span id="selected-count">0</span> ta tanlangan
                        </span>
                        <span id="total-info" class="total-info" style="display:none;"></span>
                    </div>
                    <div class="action-right">
                        <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Excel
                        </button>

                        <button type="button" id="btn-diagnostika" class="btn-diag" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Diagnostika
                        </button>

                        <button type="button" id="btn-upload" class="btn-upload" disabled style="display:none;">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            Sistemaga yuklash
                        </button>

                        <div class="import-group">
                            <input type="file" id="file-upload" accept=".xlsx,.xls,.csv" style="display:none;">
                            <button type="button" class="btn-file" onclick="document.getElementById('file-upload').click()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span id="file-label">Fayl tanlash</span>
                            </button>
                            <button type="button" id="btn-import" class="btn-import" onclick="importFile()" disabled>
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Yuklash
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Diagnostika / Upload natijalar paneli -->
                <div id="diagnostika-panel" style="display:none;">
                    <div id="diagnostika-loading" class="diag-msg diag-info" style="display:none;">
                        <div class="spinner-sm"></div> Diagnostika tekshirilmoqda...
                    </div>
                    <div id="diagnostika-summary" style="display:none;"></div>
                    <div id="diagnostika-errors" style="display:none;">
                        <div class="diag-msg diag-error">
                            <strong>Xato natijalar:</strong>
                            <div style="max-height:200px;overflow-y:auto;margin-top:8px;">
                                <table class="diag-table">
                                    <thead><tr>
                                        <th>Student ID</th><th>Talaba</th><th>Fan</th><th>Baho</th><th>Sababi</th>
                                    </tr></thead>
                                    <tbody id="diagnostika-errors-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="upload-result" style="display:none;"></div>
                <div id="import-result" style="display:none;"></div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" class="empty-state">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Quiz natijalarini filtrlash, diagnostika va sistemaga yuklash</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table" id="results-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;padding-left:14px;">
                                            <input type="checkbox" id="select-all" class="cb-styled">
                                        </th>
                                        <th class="th-num">#</th>
                                        <th>Student ID</th>
                                        <th>Talaba</th>
                                        <th>Fakultet</th>
                                        <th>Yo'nalish</th>
                                        <th>Semestr</th>
                                        <th>Fan</th>
                                        <th>Quiz turi</th>
                                        <th>Shakl</th>
                                        <th>Baho</th>
                                        <th>Eski baho</th>
                                        <th>Boshlanish</th>
                                        <th>Tugash</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                        <div id="pagination-area" class="pagination-area"></div>
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
        var csrfToken = '{{ csrf_token() }}';
        var dataUrl = '{{ route($routePrefix . ".diagnostika.data") }}';
        var diagnostikaUrl = '{{ route($routePrefix . ".quiz-results.diagnostika") }}';
        var uploadUrl = '{{ route($routePrefix . ".quiz-results.upload") }}';
        var importUrl = '{{ route($routePrefix . ".quiz-results.import") }}';
        var destroyUrlBase = '{{ url("/" . $routePrefix . "/quiz-results") }}';
        var diagnostikaRan = false;
        var currentPage = 1;

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function getFilters() {
            return {
                faculty: $('#faculty').val() || '',
                direction: $('#direction').val() || '',
                semester: $('#semester').val() || '',
                quiz_type: $('#quiz_type').val() || '',
                student_name: $('#student_name').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
            };
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        // Data yuklash
        function loadData(page) {
            currentPage = page || 1;
            var params = getFilters();
            params.page = currentPage;
            params.per_page = 50;

            $('#empty-state').hide(); $('#table-area').hide(); $('#loading-state').show();
            $('#btn-search').prop('disabled', true).css('opacity', '0.6');
            // Diagnostika panellarini yashirish
            $('#diagnostika-panel').hide();
            $('#upload-result').hide();
            diagnostikaRan = false;
            $('#btn-upload').hide().prop('disabled', true);

            $.ajax({
                url: dataUrl, type: 'GET', data: params, timeout: 120000,
                success: function(res) {
                    $('#loading-state').hide();
                    $('#btn-search').prop('disabled', false).css('opacity', '1');
                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true);
                        $('#total-info').hide();
                        return;
                    }
                    $('#total-info').text('Jami: ' + res.total + ' ta natija').show();
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false);
                    updateButtons();
                },
                error: function() {
                    $('#loading-state').hide();
                    $('#btn-search').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row" id="row-' + r.id + '">';
                html += '<td style="padding-left:14px;"><input type="checkbox" class="row-checkbox cb-styled" value="' + r.id + '"></td>';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.student_id) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.student_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.faculty) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.direction) + '</span></td>';
                html += '<td><span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">' + esc(r.semester) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:600;">' + esc(r.fan_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.quiz_type) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.shakl) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-grade">' + esc(r.grade) + '</span></td>';
                html += '<td style="text-align:center;color:#64748b;font-size:12px;">' + esc(r.old_grade) + '</td>';
                html += '<td style="font-size:12px;white-space:nowrap;color:#475569;">' + esc(r.date_start) + '</td>';
                html += '<td style="font-size:12px;white-space:nowrap;color:#475569;">' + esc(r.date_finish) + '</td>';
                html += '<td style="text-align:center;"><button class="btn-del" onclick="deleteRow(' + r.id + ')" title="O\'chirish">';
                html += '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
                html += '</button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
            $('#select-all').prop('checked', false);
        }

        // Pagination
        function renderPagination(res) {
            if (res.last_page <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            if (res.current_page > 1) html += '<button class="pg-btn" onclick="loadData(' + (res.current_page - 1) + ')">&laquo;</button>';
            for (var p = 1; p <= res.last_page; p++) {
                if (p === 1 || p === res.last_page || (p >= res.current_page - 2 && p <= res.current_page + 2)) {
                    html += '<button class="pg-btn' + (p === res.current_page ? ' pg-active' : '') + '" onclick="loadData(' + p + ')">' + p + '</button>';
                } else if (p === res.current_page - 3 || p === res.current_page + 3) {
                    html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                }
            }
            if (res.current_page < res.last_page) html += '<button class="pg-btn" onclick="loadData(' + (res.current_page + 1) + ')">&raquo;</button>';
            $('#pagination-area').html(html);
        }

        // Tanlash boshqaruvi
        function getSelectedIds() {
            var ids = [];
            $('.row-checkbox:checked').each(function() { ids.push(parseInt($(this).val())); });
            return ids;
        }

        function updateButtons() {
            var count = getSelectedIds().length;
            $('#selected-count').text(count);
            $('#btn-diagnostika').prop('disabled', count === 0);
            if (diagnostikaRan && count > 0) {
                diagnostikaRan = false;
                $('#btn-upload').hide().prop('disabled', true);
                $('#diagnostika-panel').hide();
                $('#upload-result').hide();
            }
        }

        // Qatorni o'chirish
        function deleteRow(id) {
            if (!confirm("Bu natijani o'chirishni xohlaysizmi?")) return;
            $.ajax({
                url: destroyUrlBase + '/' + id,
                type: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function() { $('#row-' + id).fadeOut(300, function() { $(this).remove(); updateButtons(); }); },
                error: function(xhr) { alert("Xato: " + (xhr.responseJSON?.message || 'Server xatosi')); }
            });
        }

        // Excel yuklash
        function downloadExcel() {
            var params = getFilters();
            params.export = 'excel';
            window.location.href = dataUrl + '?' + $.param(params);
        }

        // Fayl import
        function importFile() {
            var fileInput = document.getElementById('file-upload');
            if (!fileInput.files.length) return;

            var formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', csrfToken);

            $('#btn-import').prop('disabled', true).text('Yuklanmoqda...');
            $('#import-result').hide();

            $.ajax({
                url: importUrl, type: 'POST', data: formData,
                processData: false, contentType: false,
                success: function() {
                    $('#import-result').html('<div class="diag-msg diag-success">Fayl muvaffaqiyatli yuklandi! Sahifa yangilanmoqda...</div>').show();
                    setTimeout(function() { loadData(1); }, 1000);
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Yuklashda xatolik';
                    $('#import-result').html('<div class="diag-msg diag-error">' + msg + '</div>').show();
                },
                complete: function() {
                    $('#btn-import').prop('disabled', false).text('Yuklash');
                    fileInput.value = '';
                    $('#file-label').text('Fayl tanlash');
                }
            });
        }

        $(document).ready(function() {
            // Scroll kalendarlarni yaratish
            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            // Select2
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic', width: '100%', allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10);
                });
            });

            // File tanlanganda nomi ko'rsatilsin
            $('#file-upload').on('change', function() {
                var name = $(this).val().split('\\').pop();
                $('#file-label').text(name || 'Fayl tanlash');
                $('#btn-import').prop('disabled', !name);
            });

            // Select all
            $('#select-all').on('change', function() {
                var checked = $(this).is(':checked');
                $('.row-checkbox').prop('checked', checked);
                updateButtons();
            });

            $(document).on('change', '.row-checkbox', function() {
                updateButtons();
                var total = $('.row-checkbox').length;
                var checked = $('.row-checkbox:checked').length;
                $('#select-all').prop('checked', total > 0 && checked === total);
            });

            // DIAGNOSTIKA
            $('#btn-diagnostika').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;

                var btn = $(this);
                btn.prop('disabled', true);
                var origText = btn.html();
                btn.html('<span class="spinner-sm"></span> Tekshirilmoqda...');
                $('#diagnostika-panel').show();
                $('#diagnostika-loading').show();
                $('#diagnostika-summary').hide();
                $('#diagnostika-errors').hide();
                $('#btn-upload').hide().prop('disabled', true);
                $('#upload-result').hide();

                $.ajax({
                    url: diagnostikaUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: ids }),
                    success: function(data) {
                        $('#diagnostika-loading').hide();
                        diagnostikaRan = true;

                        var cls, text;
                        if (data.error_count === 0) {
                            cls = 'diag-success'; text = 'Hammasi tayyor! ' + data.ok_count + ' ta natija muammosiz yuklanishi mumkin.';
                        } else if (data.ok_count === 0) {
                            cls = 'diag-error'; text = 'Hech bir natija yuklanmaydi. ' + data.error_count + ' ta xato topildi.';
                        } else {
                            cls = 'diag-warning'; text = data.ok_count + ' ta tayyor, ' + data.error_count + ' ta xato.';
                        }
                        $('#diagnostika-summary').attr('class', 'diag-msg ' + cls).html('<strong>' + text + '</strong>').show();

                        if (data.error_count > 0) {
                            var tbody = $('#diagnostika-errors-body').empty();
                            data.errors.forEach(function(err) {
                                tbody.append('<tr><td>' + esc(err.student_id) + '</td><td>' + esc(err.student_name) + '</td><td>' + esc(err.fan_name) + '</td><td>' + esc(err.grade) + '</td><td style="color:#dc2626;font-weight:600;">' + esc(err.error) + '</td></tr>');
                            });
                            $('#diagnostika-errors').show();
                        }

                        if (data.ok_count > 0) {
                            $('#btn-upload').show().prop('disabled', false).find('span.upload-count').remove();
                            $('#btn-upload').append(' <span class="upload-count">(' + data.ok_count + ' ta)</span>');
                        }
                    },
                    error: function(xhr) {
                        $('#diagnostika-loading').hide();
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        $('#diagnostika-summary').attr('class', 'diag-msg diag-error').html('<strong>Xato:</strong> ' + msg).show();
                    },
                    complete: function() { btn.prop('disabled', false).html(origText); }
                });
            });

            // SISTEMAGA YUKLASH
            $('#btn-upload').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;
                if (!confirm("Tanlangan " + ids.length + " ta natijani sistemaga yuklashni tasdiqlaysizmi?")) return;

                var btn = $(this);
                btn.prop('disabled', true);
                var origHtml = btn.html();
                btn.html('<span class="spinner-sm"></span> Yuklanmoqda...');

                $.ajax({
                    url: uploadUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: ids }),
                    success: function(data) {
                        var html = '';
                        if (data.success_count > 0) {
                            html += '<div class="diag-msg diag-success"><strong>Muvaffaqiyatli!</strong> ' + data.success_count + ' ta natija sistemaga yuklandi.</div>';
                        }
                        if (data.error_count > 0) {
                            html += '<div class="diag-msg diag-error"><strong>' + data.error_count + ' ta xato:</strong><ul style="margin-top:4px;padding-left:20px;">';
                            data.errors.forEach(function(err) { html += '<li>' + esc(err.student_name) + ' — ' + esc(err.fan_name) + ': ' + esc(err.error) + '</li>'; });
                            html += '</ul></div>';
                        }
                        $('#upload-result').html(html).show();
                        btn.hide();
                        $('#diagnostika-panel').hide();

                        if (data.success_count > 0) {
                            $('.row-checkbox:checked').each(function() {
                                var rowId = $(this).val();
                                var hasError = false;
                                if (data.errors) { data.errors.forEach(function(err) { if (err.id == rowId) hasError = true; }); }
                                if (!hasError) {
                                    $('#row-' + rowId).addClass('row-uploaded').find('.row-checkbox').prop('checked', false).prop('disabled', true);
                                    $('#row-' + rowId).find('.btn-del').remove();
                                }
                            });
                            updateButtons();
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        $('#upload-result').html('<div class="diag-msg diag-error"><strong>Xato!</strong> ' + msg + '</div>').show();
                    },
                    complete: function() { btn.prop('disabled', false).html(origHtml); }
                });
            });

            // Enter bosilganda qidirish
            $('#student_name').on('keypress', function(e) { if (e.which === 13) loadData(1); });
        });
    </script>

    <style>
        /* === FILTERS === */
        .filter-container { padding: 12px 16px 10px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { flex: 1; min-width: 130px; }
        .filter-buttons { flex: 0 0 auto; min-width: auto !important; }
        .filter-label { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-input { padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 500; color: #1e293b; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; width: 100%; height: 36px; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.78rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        /* === ACTION BAR === */
        .action-bar { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 8px; }
        .action-left { display: flex; align-items: center; gap: 12px; }
        .action-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .sel-info { font-size: 12px; font-weight: 600; color: #64748b; padding: 4px 10px; background: #e2e8f0; border-radius: 6px; }
        .total-info { font-size: 12px; font-weight: 700; color: #1e3a5f; padding: 4px 10px; background: #dbeafe; border-radius: 6px; }
        .import-group { display: flex; align-items: center; gap: 4px; }

        /* === BUTTONS === */
        .btn-calc { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-excel { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 32px; white-space: nowrap; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-diag { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #d97706, #f59e0b); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(217,119,6,0.3); height: 32px; white-space: nowrap; }
        .btn-diag:hover:not(:disabled) { background: linear-gradient(135deg, #b45309, #d97706); transform: translateY(-1px); }
        .btn-diag:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-upload { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(124,58,237,0.3); height: 32px; white-space: nowrap; }
        .btn-upload:hover:not(:disabled) { background: linear-gradient(135deg, #6d28d9, #7c3aed); transform: translateY(-1px); }
        .btn-upload:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-file { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #fff; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.15s; height: 32px; white-space: nowrap; max-width: 160px; overflow: hidden; text-overflow: ellipsis; }
        .btn-file:hover { background: #f1f5f9; border-color: #94a3b8; }
        .btn-import { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(37,99,235,0.3); height: 32px; white-space: nowrap; }
        .btn-import:hover:not(:disabled) { background: linear-gradient(135deg, #1d4ed8, #2563eb); transform: translateY(-1px); }
        .btn-import:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-del { padding: 4px; background: none; border: 1px solid transparent; border-radius: 6px; color: #94a3b8; cursor: pointer; transition: all 0.15s; }
        .btn-del:hover { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }

        /* === DIAGNOSTIKA PANELS === */
        .diag-msg { padding: 10px 16px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .diag-info { background: #eff6ff; color: #1e40af; border-bottom: 1px solid #bfdbfe; }
        .diag-success { background: #f0fdf4; color: #166534; border-bottom: 1px solid #bbf7d0; }
        .diag-warning { background: #fffbeb; color: #92400e; border-bottom: 1px solid #fde68a; }
        .diag-error { background: #fef2f2; color: #991b1b; border-bottom: 1px solid #fecaca; }
        .diag-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .diag-table th { padding: 6px 10px; text-align: left; font-weight: 600; font-size: 10px; color: #991b1b; text-transform: uppercase; background: #fee2e2; border-bottom: 1px solid #fecaca; }
        .diag-table td { padding: 5px 10px; border-bottom: 1px solid #fef2f2; }

        /* === TABLE === */
        .empty-state { padding: 60px 20px; text-align: center; }
        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 10px 8px; text-align: left; font-weight: 600; font-size: 10.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { width: 40px; }
        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #f1f5f9 !important; }
        .journal-table td { padding: 7px 8px; vertical-align: middle; line-height: 1.4; }
        .td-num { font-weight: 700; color: #64748b; font-size: 12px; }
        .row-uploaded { background: #f0fdf4 !important; }
        .row-uploaded td { opacity: 0.6; }

        /* === BADGES === */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
        .badge-grade { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; font-weight: 800; min-width: 32px; text-align: center; }
        .text-cell { font-size: 12px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 200px; white-space: normal; word-break: break-word; }

        /* === CHECKBOX === */
        .cb-styled { width: 16px; height: 16px; accent-color: #2b5ea7; cursor: pointer; }

        /* === PAGINATION === */
        .pagination-area { padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .pg-btn { padding: 5px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }

        /* === SPINNER === */
        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .spinner-sm { width: 16px; height: 16px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* === SELECT2 === */
        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 8px; padding-right: 48px; color: #1e293b; font-size: 0.78rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 20px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 15px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 5px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.78rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }
    </style>
</x-app-layout>
