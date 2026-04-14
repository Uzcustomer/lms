<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Test markazi vaqtlari tekshiruvi
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
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Holat</label>
                            <select id="status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="complete">Belgilangan</option>
                                <option value="missing_date">Sana yo'q</option>
                                <option value="missing_time">Vaqt yo'q</option>
                                <option value="na">Kerak emas</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label">&nbsp;</label>
                            <label class="toggle-wrap" title="Joriy semestr bo'yicha">
                                <input type="checkbox" id="current_semester" checked />
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Joriy semestr</span>
                            </label>
                        </div>
                        <div class="filter-item" style="min-width: 260px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport()">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 002-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
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
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Test markazi imtihon sanalari va vaqtlari statistikasi ko'rsatiladi</p>
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
                            <button class="tab-btn" onclick="switchTab('faculty')">Fakultetlar kesimida</button>
                            <button class="tab-btn" onclick="switchTab('kafedra')">Kafedralar kesimida</button>
                            <button class="tab-btn" onclick="switchTab('subject')">Fanlar kesimida</button>
                            <button class="tab-btn" onclick="switchTab('student')">Talabalar kesimida</button>
                        </div>

                        <!-- Umumiy tab -->
                        <div id="tab-overall" class="tab-content active">
                            <div class="stats-summary" id="overall-summary"></div>
                            <h3 class="section-heading">OSKI nazorati</h3>
                            <div class="stats-summary" id="overall-oski"></div>
                            <h3 class="section-heading" style="margin-top:20px;">Test nazorati</h3>
                            <div class="stats-summary" id="overall-test"></div>

                            <div class="chart-section" style="margin-top:24px;">
                                <h3 class="chart-title">Test vaqtlari (soat kesimida)</h3>
                                <div style="position:relative;height:300px;">
                                    <canvas id="chart-hours"></canvas>
                                </div>
                                <p id="chart-note" style="font-size:12px;color:#64748b;margin-top:8px;"></p>
                            </div>

                            <h3 class="section-heading" style="margin-top:24px;">Jadval qachon belgilangan (imtihondan oldinroq)</h3>
                            <p style="font-size:12px;color:#64748b;margin-bottom:10px;">Test markazi tomonidan imtihon sanasi/vaqti imtihondan necha kun oldin belgilangan</p>
                            <div class="stats-summary" id="overall-setup"></div>

                            <h3 class="section-heading" style="margin-top:24px;">Talaba boshlanish vaqti tahlili</h3>
                            <p style="font-size:12px;color:#64748b;margin-bottom:10px;">Talabalar OSKI yoki Test imtihonini belgilangan vaqtda boshlaganmi yoki kechikibmi</p>
                            <div class="stats-summary" id="overall-punctuality"></div>
                        </div>

                        <!-- Fakultet tab -->
                        <div id="tab-faculty" class="tab-content">
                            <div id="faculty-content"></div>
                        </div>

                        <!-- Kafedra tab -->
                        <div id="tab-kafedra" class="tab-content">
                            <div id="kafedra-content"></div>
                        </div>

                        <!-- Fan tab -->
                        <div id="tab-subject" class="tab-content">
                            <div id="subject-content"></div>
                        </div>

                        <!-- Talabalar tab -->
                        <div id="tab-student" class="tab-content">
                            <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:center;">
                                <input type="text" id="student-search" placeholder="Guruh, talaba ismi yoki fan bo'yicha qidirish..." style="flex:1;min-width:250px;height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px;" />
                                <select id="student-type-filter" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px;background:#fff;">
                                    <option value="">Barcha turlar</option>
                                    <option value="TEST">TEST</option>
                                    <option value="OSKI">OSKI</option>
                                </select>
                                <select id="student-status-filter" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px;background:#fff;">
                                    <option value="">Barcha holatlar</option>
                                    <option value="on_time">Vaqtida</option>
                                    <option value="late">Kechikib</option>
                                </select>
                                <span id="student-count" style="font-size:13px;color:#64748b;font-weight:600;"></span>
                            </div>
                            <div id="student-content"></div>
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
        let hourChart = null;

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
                status: $('#status').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
                current_semester: $('#current_semester').is(':checked') ? '1' : '0',
            };
        }

        function loadReport() {
            var params = getFilters();

            $('#empty-state').hide();
            $('#report-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.reports.test-markazi-times.data") }}',
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
                    renderOverall(res.overall, res.hourly, res.hourly_total);
                    renderSetup(res.setup_timing || {});
                    renderPunctuality(res.punctuality || {});
                    renderGrouped(res.by_faculty, 'faculty');
                    renderGrouped(res.by_kafedra, 'kafedra');
                    renderGrouped(res.by_subject, 'subject', true);
                    renderStudents(res.by_student || [], res.by_student_total || 0, res.by_student_truncated || false);
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
            window.location.href = '{{ route("admin.reports.test-markazi-times.data") }}?' + query;
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

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function renderOverall(data, hourly, hourlyTotal) {
            $('#overall-summary').html(
                '<div class="summary-card summary-blue">' +
                    '<div class="summary-num">' + data.total_schedules.toLocaleString() + '</div>' +
                    '<div class="summary-label">Jami jadval yozuvlari</div>' +
                '</div>' +
                '<div class="summary-card summary-gray">' +
                    '<div class="summary-num">' + data.total.toLocaleString() + '</div>' +
                    '<div class="summary-label">Jami nazorat turlari (OSKI + Test)</div>' +
                '</div>'
            );

            // OSKI
            var oskiTotal = data.oski_complete + data.oski_missing + data.oski_na;
            $('#overall-oski').html(
                '<div class="summary-card summary-green">' +
                    '<div class="summary-num">' + data.oski_complete.toLocaleString() + '</div>' +
                    '<div class="summary-label">Sana belgilangan</div>' +
                    '<div class="summary-sub">' + percentOf(data.oski_complete, oskiTotal) + '%</div>' +
                '</div>' +
                '<div class="summary-card summary-red">' +
                    '<div class="summary-num">' + data.oski_missing.toLocaleString() + '</div>' +
                    '<div class="summary-label">Sana belgilanmagan</div>' +
                    '<div class="summary-sub">' + percentOf(data.oski_missing, oskiTotal) + '%</div>' +
                '</div>' +
                '<div class="summary-card summary-yellow">' +
                    '<div class="summary-num">' + data.oski_na.toLocaleString() + '</div>' +
                    '<div class="summary-label">Kerak emas</div>' +
                    '<div class="summary-sub">' + percentOf(data.oski_na, oskiTotal) + '%</div>' +
                '</div>'
            );

            // Test
            var testTotal = data.test_complete + data.test_missing_date + data.test_missing_time + data.test_na;
            var testHtml =
                '<div class="summary-card summary-green">' +
                    '<div class="summary-num">' + data.test_complete.toLocaleString() + '</div>' +
                    '<div class="summary-label">' + (data.has_test_time ? "Sana va vaqti belgilangan" : "Sana belgilangan") + '</div>' +
                    '<div class="summary-sub">' + percentOf(data.test_complete, testTotal) + '%</div>' +
                '</div>' +
                '<div class="summary-card summary-red">' +
                    '<div class="summary-num">' + data.test_missing_date.toLocaleString() + '</div>' +
                    '<div class="summary-label">Sana belgilanmagan</div>' +
                    '<div class="summary-sub">' + percentOf(data.test_missing_date, testTotal) + '%</div>' +
                '</div>';
            if (data.has_test_time) {
                testHtml +=
                '<div class="summary-card summary-orange">' +
                    '<div class="summary-num">' + data.test_missing_time.toLocaleString() + '</div>' +
                    '<div class="summary-label">Vaqti belgilanmagan</div>' +
                    '<div class="summary-sub">' + percentOf(data.test_missing_time, testTotal) + '%</div>' +
                '</div>';
            }
            testHtml +=
                '<div class="summary-card summary-yellow">' +
                    '<div class="summary-num">' + data.test_na.toLocaleString() + '</div>' +
                    '<div class="summary-label">Kerak emas</div>' +
                    '<div class="summary-sub">' + percentOf(data.test_na, testTotal) + '%</div>' +
                '</div>';
            $('#overall-test').html(testHtml);

            // Chart
            if (data.has_test_time) {
                renderHourChart(hourly, hourlyTotal);
                $('#chart-note').text('Test vaqtlari soat kesimida (test_time ustuni asosida). Jami: ' + hourlyTotal.toLocaleString() + ' ta test.');
            } else {
                if (hourChart) { hourChart.destroy(); hourChart = null; }
                $('#chart-note').text("Eslatma: test_time ustuni mavjud emas, soatlar taqsimoti ko'rsatilmaydi.");
            }
        }

        function percentOf(n, total) {
            if (!total) return 0;
            return Math.round(n * 1000 / total) / 10;
        }

        function renderSetup(s) {
            var labels = {
                early: '30+ kun oldin',
                month: '15-30 kun oldin',
                two_weeks: '8-14 kun oldin',
                week_before: '2-7 kun oldin',
                day_before: '1 kun oldin',
                same_day: "Xuddi o'sha kuni",
                late: "Imtihondan keyin o'zgartirilgan",
                no_date: "Sana belgilanmagan"
            };
            var colors = {
                early: 'summary-green',
                month: 'summary-green',
                two_weeks: 'summary-blue',
                week_before: 'summary-blue',
                day_before: 'summary-orange',
                same_day: 'summary-red',
                late: 'summary-red',
                no_date: 'summary-gray'
            };
            var order = ['early', 'month', 'two_weeks', 'week_before', 'day_before', 'same_day', 'late', 'no_date'];
            var total = 0;
            order.forEach(function(k) { total += (s[k] || 0); });
            var html = '';
            order.forEach(function(k) {
                var cnt = s[k] || 0;
                html += '<div class="summary-card ' + colors[k] + '" style="min-width:140px;">' +
                    '<div class="summary-num" style="font-size:20px;">' + cnt.toLocaleString() + '</div>' +
                    '<div class="summary-label">' + labels[k] + '</div>' +
                    '<div class="summary-sub">' + percentOf(cnt, total) + '%</div>' +
                '</div>';
            });
            $('#overall-setup').html(html);
        }

        function renderPunctuality(p) {
            var testTotal = (p.test_on_time || 0) + (p.test_late || 0);
            var oskiTotal = (p.oski_on_time || 0) + (p.oski_late || 0);

            var html = '';
            if (testTotal === 0 && oskiTotal === 0) {
                html = '<div style="padding:20px;text-align:center;color:#94a3b8;flex:1;">Talabalarning boshlanish vaqti bo\'yicha ma\'lumot topilmadi</div>';
                $('#overall-punctuality').html(html);
                return;
            }

            // Test
            html +=
                '<div class="summary-card summary-gray" style="min-width:180px;">' +
                    '<div class="summary-label" style="font-weight:700;color:#334155;">TEST</div>' +
                    '<div class="summary-num" style="font-size:18px;margin-top:4px;">' + testTotal.toLocaleString() + '</div>' +
                    '<div class="summary-sub">Jami urinishlar</div>' +
                '</div>' +
                '<div class="summary-card summary-green">' +
                    '<div class="summary-num">' + (p.test_on_time || 0).toLocaleString() + '</div>' +
                    '<div class="summary-label">Test: vaqtida boshlangan</div>' +
                    '<div class="summary-sub">' + percentOf(p.test_on_time || 0, testTotal) + '%</div>' +
                '</div>' +
                '<div class="summary-card summary-red">' +
                    '<div class="summary-num">' + (p.test_late || 0).toLocaleString() + '</div>' +
                    '<div class="summary-label">Test: kechikib boshlangan</div>' +
                    '<div class="summary-sub">' + percentOf(p.test_late || 0, testTotal) + '%</div>' +
                '</div>';

            // OSKI
            html +=
                '<div class="summary-card summary-gray" style="min-width:180px;">' +
                    '<div class="summary-label" style="font-weight:700;color:#334155;">OSKI</div>' +
                    '<div class="summary-num" style="font-size:18px;margin-top:4px;">' + oskiTotal.toLocaleString() + '</div>' +
                    '<div class="summary-sub">Jami urinishlar</div>' +
                '</div>' +
                '<div class="summary-card summary-green">' +
                    '<div class="summary-num">' + (p.oski_on_time || 0).toLocaleString() + '</div>' +
                    '<div class="summary-label">OSKI: vaqtida boshlangan</div>' +
                    '<div class="summary-sub">' + percentOf(p.oski_on_time || 0, oskiTotal) + '%</div>' +
                '</div>' +
                '<div class="summary-card summary-red">' +
                    '<div class="summary-num">' + (p.oski_late || 0).toLocaleString() + '</div>' +
                    '<div class="summary-label">OSKI: kechikib boshlangan</div>' +
                    '<div class="summary-sub">' + percentOf(p.oski_late || 0, oskiTotal) + '%</div>' +
                '</div>';

            $('#overall-punctuality').html(html);
        }

        function renderHourChart(data, total) {
            if (hourChart) hourChart.destroy();
            var labels = [], values = [], percents = [];
            for (var h = 0; h < 24; h++) {
                labels.push(hourLabel(h));
                values.push(data[h] ? data[h].count : 0);
                percents.push(data[h] ? data[h].percent : 0);
            }
            var ctx = document.getElementById('chart-hours').getContext('2d');
            hourChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Test vaqtlari',
                        data: values,
                        backgroundColor: '#3b82f6',
                        hoverBackgroundColor: '#60a5fa',
                        borderRadius: 4,
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

        var studentsAll = [];
        var studentsTotal = 0;
        var studentsTruncated = false;

        function renderStudents(items, totalCount, truncated) {
            studentsAll = items || [];
            studentsTotal = totalCount;
            studentsTruncated = truncated;
            renderStudentsTable();
        }

        function formatDt(dt, ensureTime, placeholderTime) {
            if (!dt) return '-';
            dt = String(dt);
            // "YYYY-MM-DD HH:MM:SS" yoki "YYYY-MM-DD HH:MM" yoki "YYYY-MM-DD"
            var m = dt.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
            if (!m) return dt;
            var out = m[3] + '.' + m[2] + '.' + m[1];
            if (m[4] && m[5]) {
                out += ' ' + m[4] + ':' + m[5];
            } else if (ensureTime) {
                out += ' ' + (placeholderTime || '--:--');
            }
            return out;
        }

        function renderStudentsTable() {
            var q = ($('#student-search').val() || '').trim().toLowerCase();
            var typeF = $('#student-type-filter').val() || '';
            var statusF = $('#student-status-filter').val() || '';

            var filtered = studentsAll.filter(function(s) {
                if (typeF && s.type !== typeF) return false;
                if (statusF && s.status !== statusF) return false;
                if (q) {
                    var hay = ((s.group || '') + ' ' + (s.student || '') + ' ' + (s.student_id || '') + ' ' + (s.subject || '')).toLowerCase();
                    if (hay.indexOf(q) < 0) return false;
                }
                return true;
            });

            var info = 'Ko\'rsatilmoqda: ' + filtered.length.toLocaleString() + ' / ' + studentsTotal.toLocaleString();
            if (studentsTruncated) info += ' — kechikkanlar to\'liq, vaqtida boshlaganlarning bir qismi (Excel orqali hammasini yuklab oling)';
            $('#student-count').text(info);

            var container = $('#student-content');
            if (filtered.length === 0) {
                container.html('<div style="padding:40px;text-align:center;color:#94a3b8;">Ma\'lumot topilmadi</div>');
                return;
            }

            var html = '<div style="overflow-x:auto;max-height:600px;overflow-y:auto;">';
            html += '<table class="stats-table"><thead style="position:sticky;top:0;z-index:1;"><tr>';
            html += '<th class="th-hour">#</th>';
            html += '<th class="th-hour" style="text-align:left;">Guruh</th>';
            html += '<th class="th-hour" style="text-align:left;">Talaba (FISH)</th>';
            html += '<th class="th-hour" style="text-align:left;">Fan</th>';
            html += '<th class="th-hour">Tur</th>';
            html += '<th class="th-hour">Belgilangan vaqt</th>';
            html += '<th class="th-hour">Boshlangan vaqt</th>';
            html += '<th class="th-hour">Urinish</th>';
            html += '<th class="th-hour">Baho</th>';
            html += '<th class="th-hour">Holat</th>';
            html += '</tr></thead><tbody>';

            // Brauzerni sekinlashuvdan saqlash uchun 1000 qator bilan cheklanamiz
            var limit = Math.min(filtered.length, 1000);
            for (var i = 0; i < limit; i++) {
                var s = filtered[i];
                var statusBadge = s.status === 'on_time'
                    ? '<span class="status-badge status-on-time">Vaqtida</span>'
                    : '<span class="status-badge status-late">Kechikish</span>';
                var typeBadge = s.type === 'TEST'
                    ? '<span class="type-badge type-test">TEST</span>'
                    : '<span class="type-badge type-oski">OSKI</span>';

                html += '<tr>';
                html += '<td class="td-num">' + (i + 1) + '</td>';
                html += '<td style="text-align:left;font-weight:600;color:#1e293b;padding:8px 12px;">' + esc(s.group) + '</td>';
                html += '<td style="text-align:left;color:#1e293b;padding:8px 12px;">' + esc(s.student) + '<br><span style="font-size:11px;color:#94a3b8;">ID: ' + esc(s.student_id) + '</span></td>';
                html += '<td style="text-align:left;color:#475569;padding:8px 12px;font-size:12px;">' + esc(s.subject) + '</td>';
                html += '<td class="td-num">' + typeBadge + '</td>';
                html += '<td class="td-num" style="font-size:12px;font-family:monospace;">' + esc(formatDt(s.scheduled, true)) + '</td>';
                html += '<td class="td-num" style="font-size:12px;font-family:monospace;">' + esc(formatDt(s.date_start, true)) + '</td>';
                html += '<td class="td-num">' + (s.attempt || '-') + '</td>';
                html += '<td class="td-num">' + (s.grade !== null && s.grade !== undefined ? s.grade : '-') + '</td>';
                html += '<td class="td-num">' + statusBadge + '</td>';
                html += '</tr>';
            }
            if (filtered.length > limit) {
                html += '<tr><td colspan="10" style="text-align:center;padding:14px;color:#94a3b8;font-size:12px;">Yana ' + (filtered.length - limit).toLocaleString() + ' ta qator mavjud. To\'liq ro\'yxat uchun Excel yuklab oling.</td></tr>';
            }
            html += '</tbody></table></div>';
            container.html(html);
        }

        function renderGrouped(items, prefix, showKafedra) {
            var container = $('#' + prefix + '-content');
            if (!items || items.length === 0) {
                container.html('<div style="padding:40px;text-align:center;color:#94a3b8;">Ma\'lumot topilmadi</div>');
                return;
            }
            showKafedra = showKafedra || false;

            var html = '<div style="overflow-x:auto;">';
            html += '<table class="stats-table"><thead>';
            html += '<tr><th rowspan="2" class="th-hour">#</th>';
            html += '<th rowspan="2" class="th-hour" style="text-align:left;">Nomi</th>';
            if (showKafedra) html += '<th rowspan="2" class="th-hour" style="text-align:left;">Kafedra</th>';
            html += '<th rowspan="2" class="th-data">Jami</th>';
            html += '<th colspan="3" class="th-data" style="background:#eef2ff;">OSKI</th>';
            html += '<th colspan="4" class="th-data" style="background:#fef3c7;">Test</th>';
            html += '</tr><tr>';
            html += '<th class="th-sub" style="background:#eef2ff;">Belgilangan</th>';
            html += '<th class="th-sub" style="background:#eef2ff;">Yo\'q</th>';
            html += '<th class="th-sub" style="background:#eef2ff;">Kerak emas</th>';
            html += '<th class="th-sub" style="background:#fef3c7;">To\'liq</th>';
            html += '<th class="th-sub" style="background:#fef3c7;">Sana yo\'q</th>';
            html += '<th class="th-sub" style="background:#fef3c7;">Vaqt yo\'q</th>';
            html += '<th class="th-sub" style="background:#fef3c7;">Kerak emas</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                html += '<tr>';
                html += '<td class="td-num">' + (i + 1) + '</td>';
                html += '<td style="text-align:left;font-weight:600;color:#1e293b;padding:8px 12px;">' + esc(item.name) + '</td>';
                if (showKafedra) html += '<td style="text-align:left;color:#475569;padding:8px 12px;">' + esc(item.kafedra) + '</td>';
                html += '<td class="td-num">' + item.total.toLocaleString() + '</td>';
                html += '<td class="td-num" style="color:#16a34a;">' + item.oski_complete + '</td>';
                html += '<td class="td-num" style="color:#dc2626;">' + item.oski_missing + '</td>';
                html += '<td class="td-num" style="color:#ca8a04;">' + item.oski_na + '</td>';
                html += '<td class="td-num" style="color:#16a34a;">' + item.test_complete + '</td>';
                html += '<td class="td-num" style="color:#dc2626;">' + item.test_missing_date + '</td>';
                html += '<td class="td-num" style="color:#ea580c;">' + item.test_missing_time + '</td>';
                html += '<td class="td-num" style="color:#ca8a04;">' + item.test_na + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            container.html(html);
        }

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

            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            $('#department').change(function() { loadSubjects(); });

            // Student tab filterlari
            var searchTimer = null;
            $('#student-search').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(renderStudentsTable, 200);
            });
            $('#student-type-filter, #student-status-filter').change(renderStudentsTable);
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

        /* Toggle */
        .toggle-wrap { display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none; height:36px; padding:0 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
        .toggle-wrap input { display:none; }
        .toggle-slider { width:32px; height:18px; background:#cbd5e1; border-radius:9px; position:relative; transition:background 0.2s; }
        .toggle-slider:after { content:''; position:absolute; top:2px; left:2px; width:14px; height:14px; background:#fff; border-radius:50%; transition:transform 0.2s; }
        .toggle-wrap input:checked + .toggle-slider { background:#2b5ea7; }
        .toggle-wrap input:checked + .toggle-slider:after { transform: translateX(14px); }
        .toggle-label { font-size:12px; font-weight:600; color:#475569; }

        /* Tabs */
        .tab-buttons { display: flex; gap: 4px; margin-bottom: 20px; background: #f1f5f9; padding: 4px; border-radius: 10px; }
        .tab-btn { flex: 1; padding: 10px 16px; border: none; background: transparent; font-size: 13px; font-weight: 600; color: #64748b; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .tab-btn.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .tab-btn:hover:not(.active) { color: #334155; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .section-heading { font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; }

        /* Status and type badges */
        .status-badge { display:inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .status-on-time { background: #d1fae5; color: #065f46; }
        .status-late { background: #fee2e2; color: #991b1b; }
        .type-badge { display:inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .type-test { background: #dbeafe; color: #1e40af; }
        .type-oski { background: #f3e8ff; color: #6b21a8; }

        /* Summary cards */
        .stats-summary { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .summary-card { flex: 1; min-width: 160px; padding: 16px 18px; border-radius: 12px; position: relative; }
        .summary-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #bfdbfe; }
        .summary-gray { background: linear-gradient(135deg, #f8fafc, #e2e8f0); border: 1px solid #cbd5e1; }
        .summary-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1px solid #a7f3d0; }
        .summary-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fecaca; }
        .summary-yellow { background: linear-gradient(135deg, #fefce8, #fef9c3); border: 1px solid #fde68a; }
        .summary-orange { background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1px solid #fed7aa; }
        .summary-num { font-size: 24px; font-weight: 800; color: #1e293b; }
        .summary-label { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 2px; }
        .summary-sub { font-size: 11px; font-weight: 600; color: #94a3b8; margin-top: 4px; }

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
        .td-num { font-weight: 600; color: #475569; }
    </style>
</x-app-layout>
