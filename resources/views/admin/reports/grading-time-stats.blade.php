<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Davomat va baho qo'yish vaqtlari statistikasi
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
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
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                            <select id="department" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kafedra)
                                    <option value="{{ $kafedra->department_id }}">{{ $kafedra->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 250px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 260px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport()">
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
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Sana oralig'ini tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Davomat va baho qo'yish vaqtlari statistikasi ko'rsatiladi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="report-area" style="display:none; padding: 20px;">

                        <div style="padding:10px 20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>

                        <!-- Tab buttons -->
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="switchTab('overall')">Umumiy</button>
                            <button class="tab-btn" onclick="switchTab('kafedra')">Kafedralar kesimida</button>
                            <button class="tab-btn" onclick="switchTab('subject')">Fanlar kesimida</button>
                        </div>

                        <!-- Umumiy tab -->
                        <div id="tab-overall" class="tab-content active">
                            <div class="stats-summary" id="overall-summary"></div>
                            <div class="chart-section">
                                <h3 class="chart-title">Davomat sinxronizatsiya vaqtlari (soat kesimida) <span style="font-size:11px;font-weight:400;color:#94a3b8;">* HEMIS API da davomat belgilangan vaqt saqlanmaydi</span></h3>
                                <div style="position:relative;height:280px;">
                                    <canvas id="chart-att-overall"></canvas>
                                </div>
                            </div>
                            <div class="chart-section" style="margin-top:24px;">
                                <h3 class="chart-title">Baho qo'yish vaqtlari (soat kesimida) <span style="font-size:11px;font-weight:400;color:#16a34a;">* HEMIS dagi haqiqiy vaqt</span></h3>
                                <div style="position:relative;height:280px;">
                                    <canvas id="chart-grade-overall"></canvas>
                                </div>
                            </div>
                            <div style="margin-top:24px;">
                                <h3 class="chart-title">Soatlar bo'yicha tafsilot</h3>
                                <div style="overflow-x:auto;">
                                    <table class="stats-table" id="overall-table"></table>
                                </div>
                            </div>
                        </div>

                        <!-- Kafedralar tab -->
                        <div id="tab-kafedra" class="tab-content">
                            <div id="kafedra-content"></div>
                        </div>

                        <!-- Fanlar tab -->
                        <div id="tab-subject" class="tab-content">
                            <div id="subject-content"></div>
                        </div>

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
        let reportData = null;
        let charts = {};

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
                department: $('#department').val() || '',
                subject: $('#subject').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
            };
        }

        function loadReport() {
            var params = getFilters();
            if (!params.date_from || !params.date_to) {
                alert("Sana oralig'ini tanlang!");
                return;
            }

            $('#empty-state').hide();
            $('#report-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.reports.grading-time-stats.data") }}',
                type: 'GET',
                data: params,
                timeout: 300000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    if (res.error) {
                        alert(res.error);
                        $('#empty-state').show();
                        return;
                    }

                    reportData = res;
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                    renderOverall(res.overall);
                    renderGroupedData(res.by_kafedra, 'kafedra');
                    renderGroupedData(res.by_subject, 'subject');
                    $('#report-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    var errMsg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.error) errMsg = resp.error;
                    } catch(e) {}
                    $('#empty-state').show().find('p:first').text(errMsg);
                }
            });
        }

        function downloadExcel() {
            var params = getFilters();
            params.export = 'excel';
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.grading-time-stats.data") }}?' + query;
        }

        function switchTab(tab) {
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            $(`.tab-btn[onclick="switchTab('${tab}')"]`).addClass('active');
            $(`#tab-${tab}`).addClass('active');
        }

        function hourLabel(h) {
            return (h < 10 ? '0' : '') + h + ':00';
        }

        function renderOverall(data) {
            // Summary
            $('#overall-summary').html(
                '<div class="summary-card summary-blue">' +
                    '<div class="summary-num">' + data.attendance_total.toLocaleString() + '</div>' +
                    '<div class="summary-label">Jami davomat yozuvlari</div>' +
                '</div>' +
                '<div class="summary-card summary-green">' +
                    '<div class="summary-num">' + data.grades_total.toLocaleString() + '</div>' +
                    '<div class="summary-label">Jami baho yozuvlari</div>' +
                '</div>'
            );

            // Charts
            renderBarChart('chart-att-overall', data.attendance, 'Davomat', '#3b82f6', '#93c5fd');
            renderBarChart('chart-grade-overall', data.grades, 'Baholar', '#10b981', '#6ee7b7');

            // Table
            renderHourlyTable('overall-table', data);
        }

        function renderBarChart(canvasId, data, label, bgColor, hoverColor) {
            if (charts[canvasId]) {
                charts[canvasId].destroy();
            }

            var labels = [];
            var values = [];
            var percents = [];
            for (var h = 0; h < 24; h++) {
                labels.push(hourLabel(h));
                values.push(data[h] ? data[h].count : 0);
                percents.push(data[h] ? data[h].percent : 0);
            }

            var ctx = document.getElementById(canvasId).getContext('2d');
            charts[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: bgColor,
                        hoverBackgroundColor: hoverColor,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.parsed.y.toLocaleString() + ' ta (' + percents[ctx.dataIndex] + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 11 } },
                            grid: { color: '#f1f5f9' },
                        },
                        x: {
                            ticks: { font: { size: 10 }, maxRotation: 45 },
                            grid: { display: false },
                        }
                    }
                }
            });
        }

        function renderHourlyTable(tableId, data) {
            var html = '<thead><tr><th class="th-hour">Soat</th>';
            html += '<th class="th-data" colspan="2">Davomat</th>';
            html += '<th class="th-data" colspan="2">Baholar</th></tr>';
            html += '<tr><th></th><th class="th-sub">Soni</th><th class="th-sub">Foiz</th>';
            html += '<th class="th-sub">Soni</th><th class="th-sub">Foiz</th></tr></thead><tbody>';

            for (var h = 0; h < 24; h++) {
                var att = data.attendance[h] || {count:0, percent:0};
                var gr = data.grades[h] || {count:0, percent:0};
                var rowClass = (att.count > 0 || gr.count > 0) ? '' : ' class="row-empty"';
                html += '<tr' + rowClass + '>';
                html += '<td class="td-hour">' + hourLabel(h) + '</td>';
                html += '<td class="td-num">' + att.count.toLocaleString() + '</td>';
                html += '<td class="td-pct">' + renderPercentBar(att.percent, '#3b82f6') + '</td>';
                html += '<td class="td-num">' + gr.count.toLocaleString() + '</td>';
                html += '<td class="td-pct">' + renderPercentBar(gr.percent, '#10b981') + '</td>';
                html += '</tr>';
            }

            html += '</tbody>';
            $('#' + tableId).html(html);
        }

        function renderPercentBar(pct, color) {
            if (pct <= 0) return '<span class="pct-zero">0%</span>';
            return '<div class="pct-bar-wrap">' +
                '<div class="pct-bar" style="width:' + Math.min(pct, 100) + '%;background:' + color + ';"></div>' +
                '<span class="pct-text">' + pct + '%</span>' +
            '</div>';
        }

        function renderGroupedData(items, prefix) {
            var container = $('#' + prefix + '-content');
            if (!items || items.length === 0) {
                container.html('<div style="padding:40px;text-align:center;color:#94a3b8;">Ma\'lumot topilmadi</div>');
                return;
            }

            var html = '';

            // Summary table
            html += '<div style="overflow-x:auto;margin-bottom:24px;">';
            html += '<table class="stats-table"><thead><tr>';
            html += '<th class="th-hour">#</th><th class="th-hour" style="text-align:left;">Nomi</th>';
            html += '<th class="th-data">Davomat soni</th><th class="th-data">Baho soni</th>';
            html += '<th class="th-data">Eng ko\'p davomat soati</th><th class="th-data">Eng ko\'p baho soati</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var peakAtt = findPeakHour(item.attendance);
                var peakGrade = findPeakHour(item.grades);

                html += '<tr>';
                html += '<td class="td-num">' + (i + 1) + '</td>';
                html += '<td style="text-align:left;font-weight:600;color:#1e293b;padding:8px 12px;">' + esc(item.name) + '</td>';
                html += '<td class="td-num">' + item.attendance_total.toLocaleString() + '</td>';
                html += '<td class="td-num">' + item.grades_total.toLocaleString() + '</td>';
                html += '<td class="td-num">' + (peakAtt ? hourLabel(peakAtt.hour) + ' (' + peakAtt.percent + '%)' : '-') + '</td>';
                html += '<td class="td-num">' + (peakGrade ? hourLabel(peakGrade.hour) + ' (' + peakGrade.percent + '%)' : '-') + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table></div>';

            // Expandable details
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var detId = prefix + '-detail-' + i;
                html += '<div class="detail-block">';
                html += '<div class="detail-header" onclick="toggleDetail(\'' + detId + '\', this)">';
                html += '<svg class="detail-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
                html += '<span class="detail-name">' + esc(item.name) + '</span>';
                html += '<span class="detail-badge badge-blue">' + item.attendance_total.toLocaleString() + ' davomat</span>';
                html += '<span class="detail-badge badge-green">' + item.grades_total.toLocaleString() + ' baho</span>';
                html += '</div>';
                html += '<div class="detail-body" id="' + detId + '" style="display:none;">';
                html += '<table class="stats-table" id="' + detId + '-table"></table>';
                html += '</div></div>';
            }

            container.html(html);

            // Render tables for details
            for (var i = 0; i < items.length; i++) {
                var detId = prefix + '-detail-' + i;
                renderHourlyTable(detId + '-table', items[i]);
            }
        }

        function toggleDetail(id, headerEl) {
            var el = document.getElementById(id);
            var isVisible = el.style.display !== 'none';
            el.style.display = isVisible ? 'none' : 'block';
            headerEl.classList.toggle('expanded', !isVisible);
        }

        function findPeakHour(hourData) {
            var maxH = null, maxP = 0;
            for (var h = 0; h < 24; h++) {
                if (hourData[h] && hourData[h].percent > maxP) {
                    maxP = hourData[h].percent;
                    maxH = h;
                }
            }
            return maxH !== null ? { hour: maxH, percent: maxP } : null;
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        // Load subjects by kafedra
        function loadSubjects() {
            var dept = $('#department').val();
            var params = { department: dept };

            $.get('{{ route("admin.journal.get-subjects") }}', params, function(data) {
                var $sel = $('#subject');
                $sel.empty().append('<option value="">Barchasi</option>');
                if (data && data.length) {
                    for (var i = 0; i < data.length; i++) {
                        $sel.append('<option value="' + data[i].id + '">' + data[i].text + '</option>');
                    }
                }
                $sel.trigger('change.select2');
            });
        }

        $(document).ready(function() {
            $('.select2').select2({
                theme: 'classic',
                allowClear: true,
                placeholder: 'Tanlang...',
                matcher: fuzzyMatcher
            });

            // ScrollCalendar - id ni # siz berish kerak
            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            $('#department').change(function() { loadSubjects(); });
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-item { display: flex; flex-direction: column; }

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

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

        /* Tabs */
        .tab-buttons { display: flex; gap: 4px; margin-bottom: 20px; background: #f1f5f9; padding: 4px; border-radius: 10px; }
        .tab-btn { flex: 1; padding: 10px 16px; border: none; background: transparent; font-size: 13px; font-weight: 600; color: #64748b; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .tab-btn.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .tab-btn:hover:not(.active) { color: #334155; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Summary cards */
        .stats-summary { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { flex: 1; min-width: 200px; padding: 20px 24px; border-radius: 12px; }
        .summary-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #bfdbfe; }
        .summary-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1px solid #a7f3d0; }
        .summary-num { font-size: 28px; font-weight: 800; color: #1e293b; }
        .summary-label { font-size: 13px; font-weight: 600; color: #64748b; margin-top: 4px; }

        /* Chart section */
        .chart-section { background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
        .chart-title { font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 12px; }

        /* Stats table */
        .stats-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
        .stats-table thead tr:first-child { background: linear-gradient(135deg, #e8edf5, #dbe4ef); }
        .stats-table thead tr:nth-child(2) { background: #f1f5f9; }
        .stats-table th { padding: 10px 12px; text-align: center; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
        .stats-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; }
        .stats-table tr:last-child td { border-bottom: none; }
        .th-hour { min-width: 70px; }
        .th-data { min-width: 80px; }
        .th-sub { font-size: 10px; text-transform: none; font-weight: 600; color: #64748b; }
        .td-hour { font-weight: 700; color: #1e293b; font-size: 13px; }
        .td-num { font-weight: 600; color: #475569; }
        .td-pct { min-width: 120px; }
        .row-empty td { opacity: 0.4; }

        /* Percent bar */
        .pct-bar-wrap { display: flex; align-items: center; gap: 6px; }
        .pct-bar { height: 18px; border-radius: 4px; min-width: 2px; transition: width 0.3s; }
        .pct-text { font-size: 11px; font-weight: 700; color: #475569; white-space: nowrap; }
        .pct-zero { font-size: 11px; color: #cbd5e1; }

        /* Detail blocks */
        .detail-block { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; overflow: hidden; }
        .detail-header { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #f8fafc; cursor: pointer; transition: background 0.2s; }
        .detail-header:hover { background: #f1f5f9; }
        .detail-arrow { width: 16px; height: 16px; color: #94a3b8; transition: transform 0.2s; flex-shrink: 0; }
        .detail-header.expanded .detail-arrow { transform: rotate(90deg); }
        .detail-name { font-weight: 700; color: #1e293b; font-size: 13px; flex: 1; }
        .detail-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 6px; white-space: nowrap; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .detail-body { padding: 16px; background: #fff; }
    </style>
</x-app-layout>
