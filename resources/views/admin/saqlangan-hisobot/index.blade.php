<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sistemaga yuklangan natijalar
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Sana filtrlari -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="max-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#16a34a;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="max-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#16a34a;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item filter-buttons">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-load" class="btn-tartibga" onclick="loadData()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M16 3H8v4h8V3z"/></svg>
                                Ko'rsatish
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="action-left">
                        <span id="total-info" class="total-info" style="display:none;"></span>
                    </div>
                    <div class="action-right">
                        <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Excel
                        </button>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" class="empty-state">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#86efac;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M16 3H8v4h8V3z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Sanalarni tanlang va "Ko'rsatish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Sistemaga yuklangan quiz natijalarini ko'rish</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#16a34a;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table" id="results-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th>Attempt ID</th>
                                        <th>Student ID</th>
                                        <th>FISH</th>
                                        <th>Fakultet</th>
                                        <th>Yo'nalish</th>
                                        <th>Semestr</th>
                                        <th>Fan ID</th>
                                        <th>Fan nomi</th>
                                        <th>Quiz turi</th>
                                        <th>Urinish nomi</th>
                                        <th>Shakl</th>
                                        <th>Baho</th>
                                        <th>Boshlanish</th>
                                        <th>Tugash</th>
                                    </tr>
                                    <tr class="filter-header-row">
                                        <th></th>
                                        <th><input type="text" class="col-filter-input" data-col="attempt_id" placeholder="ID..."></th>
                                        <th><input type="text" class="col-filter-input" data-col="student_id" placeholder="ID..."></th>
                                        <th><input type="text" class="col-filter-input" data-col="student_name" placeholder="Ism..."></th>
                                        <th><select class="col-filter" data-col="faculty"><option value="">Barchasi</option></select></th>
                                        <th><select class="col-filter" data-col="direction"><option value="">Barchasi</option></select></th>
                                        <th><select class="col-filter" data-col="semester"><option value="">Barchasi</option></select></th>
                                        <th><input type="text" class="col-filter-input" data-col="fan_id" placeholder="ID..."></th>
                                        <th><select class="col-filter" data-col="fan_name"><option value="">Barchasi</option></select></th>
                                        <th><select class="col-filter" data-col="quiz_type"><option value="">Barchasi</option></select></th>
                                        <th><select class="col-filter" data-col="attempt_name"><option value="">Barchasi</option></select></th>
                                        <th><select class="col-filter" data-col="shakl"><option value="">Barchasi</option></select></th>
                                        <th>
                                            <div class="adv-filter-wrap">
                                                <button type="button" class="adv-filter-btn" onclick="toggleAdvFilter('baho')">
                                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                                    <span id="baho-filter-label">Baho</span>
                                                </button>
                                                <div class="adv-filter-popup" id="baho-popup">
                                                    <div class="adv-filter-title">Baho filtri</div>
                                                    <select id="baho-op" class="adv-filter-select" onchange="$('#baho-val2').toggle($('#baho-op').val()==='between')">
                                                        <option value="">Barchasi</option>
                                                        <option value="eq">Teng (=)</option>
                                                        <option value="gt">Dan katta (&gt;)</option>
                                                        <option value="gte">Dan katta yoki teng (&ge;)</option>
                                                        <option value="lt">Dan kichik (&lt;)</option>
                                                        <option value="lte">Dan kichik yoki teng (&le;)</option>
                                                        <option value="between">Orasida</option>
                                                    </select>
                                                    <div class="adv-filter-inputs">
                                                        <input type="number" id="baho-val1" class="adv-filter-input" placeholder="Qiymat" step="0.1">
                                                        <input type="number" id="baho-val2" class="adv-filter-input" placeholder="gacha" step="0.1" style="display:none;">
                                                    </div>
                                                    <div class="adv-filter-actions">
                                                        <button type="button" class="adv-btn-clear" onclick="clearAdvFilter('baho')">Tozalash</button>
                                                        <button type="button" class="adv-btn-apply" onclick="applyAdvFilter('baho')">Qo'llash</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        var dataUrl = '{{ route($routePrefix . ".saqlangan-hisobot.data") }}';

        var allData = [];
        var filteredData = [];
        var advFilters = { baho: null };

        function esc(s) { return $('<span>').text(s || '-').html(); }

        // ========== DATA YUKLASH ==========
        function loadData() {
            var params = {
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
            };

            $('#empty-state').hide(); $('#table-area').hide(); $('#loading-state').show();
            $('#btn-load').prop('disabled', true).css('opacity', '0.6');

            $.ajax({
                url: dataUrl, type: 'GET', data: params, timeout: 120000,
                success: function(res) {
                    $('#loading-state').hide();
                    $('#btn-load').prop('disabled', false).css('opacity', '1');
                    if (!res.data || res.data.length === 0) {
                        allData = []; filteredData = [];
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true);
                        $('#total-info').hide();
                        return;
                    }
                    allData = res.data;
                    populateColumnFilters();
                    applyColumnFilters();
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false);
                },
                error: function() {
                    $('#loading-state').hide();
                    $('#btn-load').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        // ========== USTUN FILTRLARI ==========
        function populateColumnFilters() {
            var cols = ['faculty','direction','semester','fan_name','quiz_type','attempt_name','shakl'];
            cols.forEach(function(col) {
                var unique = [], seen = {};
                allData.forEach(function(r) {
                    var v = r[col] || '';
                    if (v && !seen[v]) { seen[v] = true; unique.push(v); }
                });
                unique.sort();
                var sel = $('select.col-filter[data-col="' + col + '"]');
                var curVal = sel.val();
                sel.find('option:not(:first)').remove();
                unique.forEach(function(v) {
                    sel.append('<option value="' + esc(v) + '">' + esc(v) + '</option>');
                });
                if (curVal) sel.val(curVal);
            });
        }

        function applyColumnFilters() {
            var filters = {};
            $('select.col-filter').each(function() {
                var val = $(this).val();
                if (val) filters[$(this).data('col')] = val;
            });
            $('input.col-filter-input').each(function() {
                var val = $.trim($(this).val()).toLowerCase();
                if (val) filters[$(this).data('col')] = val;
            });

            filteredData = allData.filter(function(r) {
                for (var col in filters) {
                    var fv = filters[col];
                    var rv = (r[col] || '').toString();
                    if ($('input.col-filter-input[data-col="' + col + '"]').length) {
                        if (rv.toLowerCase().indexOf(fv) === -1) return false;
                    } else {
                        if (rv !== fv) return false;
                    }
                }
                if (!matchAdvFilter(advFilters.baho, r.grade, false)) return false;
                return true;
            });

            renderTable(filteredData);
            $('#total-info').text('Jami: ' + allData.length + ' | Ko\'rsatilmoqda: ' + filteredData.length).show();
        }

        // ========== BAHO ADVANCED FILTR ==========
        function toggleAdvFilter(type) {
            var popup = document.getElementById(type + '-popup');
            var isVisible = popup.style.display === 'block';
            document.querySelectorAll('.adv-filter-popup').forEach(function(p) { p.style.display = 'none'; });
            if (!isVisible) popup.style.display = 'block';
        }

        function applyAdvFilter(type) {
            var op = $('#' + type + '-op').val();
            var val1 = $('#' + type + '-val1').val();
            var val2 = $('#' + type + '-val2').val();

            if (!op || !val1) {
                advFilters[type] = null;
                $('#' + type + '-filter-label').text('Baho').removeClass('adv-active-label');
                $('.adv-filter-btn').removeClass('adv-active');
            } else {
                advFilters[type] = { op: op, val1: val1, val2: val2 };
                var opLabels = { eq: '=', gt: '>', gte: '≥', lt: '<', lte: '≤', between: '↔' };
                var labelText = opLabels[op] + ' ' + val1;
                if (op === 'between' && val2) labelText = val1 + ' — ' + val2;
                $('#' + type + '-filter-label').text(labelText).addClass('adv-active-label');
                $('#' + type + '-popup').closest('.adv-filter-wrap').find('.adv-filter-btn').addClass('adv-active');
            }
            document.getElementById(type + '-popup').style.display = 'none';
            applyColumnFilters();
        }

        function clearAdvFilter(type) {
            $('#' + type + '-op').val('');
            $('#' + type + '-val1').val('');
            $('#' + type + '-val2').val('').hide();
            advFilters[type] = null;
            $('#' + type + '-filter-label').text('Baho').removeClass('adv-active-label');
            $('#' + type + '-popup').closest('.adv-filter-wrap').find('.adv-filter-btn').removeClass('adv-active');
            document.getElementById(type + '-popup').style.display = 'none';
            applyColumnFilters();
        }

        function matchAdvFilter(filter, cellValue, isDate) {
            if (!filter) return true;
            var cv = parseFloat(cellValue);
            var v1 = parseFloat(filter.val1);
            var v2 = parseFloat(filter.val2);
            if (isNaN(cv) || isNaN(v1)) return false;
            switch (filter.op) {
                case 'eq':  return cv === v1;
                case 'gt':  return cv > v1;
                case 'gte': return cv >= v1;
                case 'lt':  return cv < v1;
                case 'lte': return cv <= v1;
                case 'between': return !isNaN(v2) ? (cv >= v1 && cv <= v2) : cv >= v1;
            }
            return true;
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.adv-filter-wrap')) {
                document.querySelectorAll('.adv-filter-popup').forEach(function(p) { p.style.display = 'none'; });
            }
        });

        // ========== JADVAL RENDERI ==========
        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + (i + 1) + '</td>';
                html += '<td><span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">' + esc(r.attempt_id) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.student_id) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.student_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.faculty) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.direction) + '</span></td>';
                html += '<td><span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">' + esc(r.semester) + '</span></td>';
                html += '<td><span class="badge" style="background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;">' + esc(r.fan_id) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:600;">' + esc(r.fan_name) + '</span></td>';
                html += '<td style="text-align:center;">';
                if (r.quiz_type && r.quiz_type.indexOf('OSKI') !== -1) {
                    html += '<span class="badge badge-oski">' + esc(r.quiz_type) + '</span>';
                } else {
                    html += '<span class="badge badge-grade">' + esc(r.quiz_type) + '</span>';
                }
                html += '</td>';
                html += '<td><span class="text-cell" style="font-size:11px;">' + esc(r.attempt_name) + '</span></td>';
                html += '<td><span class="text-cell">' + esc(r.shakl) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-grade">' + esc(r.grade) + '</span></td>';
                html += '<td style="font-size:11px;white-space:nowrap;color:#475569;">' + esc(r.date_start) + '</td>';
                html += '<td style="font-size:11px;white-space:nowrap;color:#475569;">' + esc(r.date_finish) + '</td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        // ========== EXCEL ==========
        function downloadExcel() {
            if (!filteredData || filteredData.length === 0) return;

            var headers = ['#', 'Attempt ID', 'Student ID', 'FISH', 'Fakultet', 'Yo\'nalish', 'Semestr', 'Fan ID', 'Fan nomi', 'Quiz turi', 'Urinish nomi', 'Shakl', 'Baho', 'Boshlanish', 'Tugash'];
            var rows = [headers];
            filteredData.forEach(function(r, i) {
                rows.push([
                    i + 1, r.attempt_id, r.student_id, r.student_name, r.faculty, r.direction,
                    r.semester, r.fan_id, r.fan_name, r.quiz_type, r.attempt_name, r.shakl,
                    r.grade, r.date_start, r.date_finish
                ]);
            });

            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(rows);

            // Ustun kengliklarini avtomatik belgilash
            var colWidths = headers.map(function(h, ci) {
                var max = h.length;
                rows.forEach(function(row) {
                    var len = String(row[ci] || '').length;
                    if (len > max) max = len;
                });
                return { wch: Math.min(max + 2, 40) };
            });
            ws['!cols'] = colWidths;

            XLSX.utils.book_append_sheet(wb, ws, 'Natijalar');
            XLSX.writeFile(wb, 'sistemaga_yuklangan_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        }

        // ========== DOCUMENT READY ==========
        $(document).ready(function() {
            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            $(document).on('change', 'select.col-filter', function() { applyColumnFilters(); });
            var filterTimer = null;
            $(document).on('input', 'input.col-filter-input', function() {
                clearTimeout(filterTimer);
                filterTimer = setTimeout(function() { applyColumnFilters(); }, 300);
            });
        });
    </script>

    <style>
        /* === FILTERS === */
        .filter-container { padding: 12px 16px 10px; background: linear-gradient(135deg, #f0f8f4, #e8f5ee); border-bottom: 2px solid #b7e4c7; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { flex: 0 0 auto; }
        .filter-buttons { flex: 0 0 auto; }
        .filter-label { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.78rem; font-weight: 500; color: #1e293b; background: #fff; width: 150px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,0.1); }
        .date-input:focus { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        /* === ACTION BAR === */
        .action-bar { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 8px; }
        .action-left { display: flex; align-items: center; gap: 12px; }
        .action-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .total-info { font-size: 12px; font-weight: 700; color: #166534; padding: 4px 10px; background: #dcfce7; border-radius: 6px; }

        /* === BUTTONS === */
        .btn-tartibga { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 36px; white-space: nowrap; }
        .btn-tartibga:hover { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-tartibga:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-excel { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 32px; white-space: nowrap; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.4; }

        /* === TABLE === */
        .empty-state { padding: 60px 20px; text-align: center; }
        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr:first-child { background: linear-gradient(135deg, #e8f5ee, #d1fae5, #bbf7d0); }
        .journal-table th { padding: 10px 8px; text-align: left; font-weight: 600; font-size: 10.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #86efac; }
        .journal-table th.th-num { width: 40px; }

        /* Filter header row */
        .filter-header-row { background: #f0fdf4 !important; }
        .filter-header-row th { padding: 4px 4px 6px; border-bottom: 2px solid #4ade80; }
        .col-filter { width: 100%; padding: 3px 4px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #334155; background: #fff; cursor: pointer; outline: none; height: 26px; }
        .col-filter:focus { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,0.15); }
        .col-filter-input { width: 100%; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #334155; background: #fff; outline: none; height: 26px; }
        .col-filter-input:focus { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,0.15); }
        .col-filter-input::placeholder { color: #94a3b8; }

        /* === ADVANCED FILTER === */
        .adv-filter-wrap { position: relative; }
        .adv-filter-btn { display: inline-flex; align-items: center; gap: 4px; width: 100%; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #64748b; background: #fff; cursor: pointer; outline: none; height: 26px; transition: all 0.15s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .adv-filter-btn:hover { border-color: #16a34a; background: #f0fdf4; }
        .adv-filter-btn.adv-active { border-color: #16a34a; background: #f0fdf4; color: #166534; font-weight: 700; }
        .adv-active-label { color: #166534 !important; }
        .adv-filter-popup { display: none; position: absolute; top: 30px; right: 0; z-index: 100; min-width: 200px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 10px; }
        .adv-filter-title { font-size: 11px; font-weight: 700; color: #1e293b; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.04em; }
        .adv-filter-select { width: 100%; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; font-weight: 500; color: #334155; background: #f8fafc; cursor: pointer; outline: none; margin-bottom: 6px; }
        .adv-filter-inputs { display: flex; gap: 4px; margin-bottom: 8px; }
        .adv-filter-input { flex: 1; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; font-weight: 500; color: #334155; background: #fff; outline: none; }
        .adv-filter-actions { display: flex; gap: 6px; justify-content: flex-end; }
        .adv-btn-clear { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 10px; font-weight: 600; color: #64748b; background: #f8fafc; cursor: pointer; transition: all 0.15s; }
        .adv-btn-clear:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
        .adv-btn-apply { padding: 4px 10px; border: none; border-radius: 6px; font-size: 10px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #16a34a, #22c55e); cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 4px rgba(22,163,74,0.3); }
        .adv-btn-apply:hover { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #f0fdf4 !important; }
        .journal-table td { padding: 7px 8px; vertical-align: middle; line-height: 1.4; }
        .td-num { font-weight: 700; color: #64748b; font-size: 12px; }

        /* === BADGES === */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
        .badge-grade { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; font-weight: 800; min-width: 32px; text-align: center; }
        .badge-oski { background: #fce7f3; color: #9d174d; border: 1px solid #fbcfe8; font-weight: 800; min-width: 32px; text-align: center; }
        .text-cell { font-size: 12px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 200px; white-space: normal; word-break: break-word; }

        /* === SPINNER === */
        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #16a34a; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</x-app-layout>
