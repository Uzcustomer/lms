<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            74 soat dars qoldirish hisoboti
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
                        <div class="filter-item" style="min-width: 290px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zona Legend -->
                <div id="zone-legend" style="display:none;padding:8px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:none;">
                    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                        <span style="font-size:12px;font-weight:700;color:#475569;">Ogohlantirish zonalari:</span>
                        <span class="zone-label" style="background:#fef9c3;border:1px solid #fde047;color:#854d0e;">Sariq: sababsiz 30+ soat yoki jami 15+ kun</span>
                        <span class="zone-label" style="background:#fce7f3;border:1px solid #f9a8d4;color:#9d174d;">Pink: sababsiz 30-60 soat yoki 15-25 kun</span>
                        <span class="zone-label" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">Qizil: sababsiz 60+ soat yoki 25+ kun</span>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Natijalar shu yerda ko'rsatiladi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:12px;">
                            <span id="total-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 380px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="full_name">Talaba FISH <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="specialty_name">Yo'nalish <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="level_name">Kurs <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="semester_name">Semestr <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="sababli_hours">Sababli (soat) <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="sababsiz_hours">Sababsiz (soat) <span class="sort-icon active">&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="total_hours">Jami (soat) <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="total_days">Jami (kun) <span class="sort-icon">&#9650;&#9660;</span></a></th>
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

    <script>
        let currentSort = 'sababsiz_hours';
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
            return {
                education_type: $('#education_type').val() || '',
                faculty: $('#faculty').val() || '',
                specialty: $('#specialty').val() || '',
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                group: $('#group').val() || '',
                department: $('#department').val() || '',
                subject: $('#subject').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
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
            $('#zone-legend').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.absence_report.data") }}',
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
                        $('#zone-legend').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    $('#total-badge').text('Jami: ' + res.total);
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#zone-legend').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        function zoneClass(r) {
            if (r.sababsiz_hours > 60 || r.total_days > 25) return 'zone-red';
            if (r.sababsiz_hours >= 30 || r.total_days >= 15) {
                if (r.sababsiz_hours > 60 || r.total_days > 25) return 'zone-red';
                return 'zone-pink';
            }
            return '';
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var zc = zoneClass(r);
                html += '<tr class="journal-row ' + zc + '">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
                html += '<td><span class="badge badge-teal">' + esc(r.semester_name) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-sababli">' + r.sababli_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge ' + sababsizBadge(r.sababsiz_hours) + '">' + r.sababsiz_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-total">' + r.total_hours + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-days">' + r.total_days + '</span></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function sababsizBadge(hours) {
            if (hours > 60) return 'badge-grade-red';
            if (hours >= 30) return 'badge-grade-pink';
            return 'badge-grade-yellow';
        }

        function downloadExcel() {
            var params = getFilters();
            params.export = 'excel';
            var query = $.param(params);
            window.location.href = '{{ route("admin.absence_report.data") }}?' + query;
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

            // ScrollCalendar
            var SC_MONTHS = ["Yanvar","Fevral","Mart","Aprel","May","Iyun","Iyul","Avgust","Sentabr","Oktabr","Noyabr","Dekabr"];
            var SC_DAYS = ["Du","Se","Cho","Pa","Ju","Sha","Ya"];
            var SC_ROW = 34, SC_STEP = 17, SC_VISIBLE = 6;

            function scPad(n) { return n < 10 ? '0'+n : ''+n; }
            function scYmd(d) { return d.getFullYear()+'-'+scPad(d.getMonth()+1)+'-'+scPad(d.getDate()); }
            function scDmy(d) { return scPad(d.getDate())+'.'+scPad(d.getMonth()+1)+'.'+d.getFullYear(); }
            function scSame(a,b) { return a&&b&&a.getFullYear()===b.getFullYear()&&a.getMonth()===b.getMonth()&&a.getDate()===b.getDate(); }
            function scMonday(d) {
                var dt = new Date(d); dt.setHours(0,0,0,0);
                var day = dt.getDay(), diff = day===0 ? -6 : 1-day;
                dt.setDate(dt.getDate()+diff); return dt;
            }

            function ScrollCalendar(inputId) {
                this.input = document.getElementById(inputId);
                this.selected = null;
                this.today = new Date(); this.today.setHours(0,0,0,0);
                this.weeks = [];
                this.isOpen = false;
                this._build();
                this._gen();
                this._render();
                this._bind();
            }
            var SCP = ScrollCalendar.prototype;

            SCP._build = function() {
                var wrap = document.createElement('div');
                wrap.className = 'sc-wrap';
                this.input.parentNode.insertBefore(wrap, this.input);

                this.display = document.createElement('input');
                this.display.type = 'text'; this.display.className = 'date-input';
                this.display.placeholder = 'kk.oo.yyyy'; this.display.readOnly = true;
                this.display.style.cursor = 'pointer';
                wrap.appendChild(this.display);

                this.clearBtn = document.createElement('span');
                this.clearBtn.className = 'sc-clear';
                this.clearBtn.innerHTML = '&times;';
                this.clearBtn.style.display = 'none';
                wrap.appendChild(this.clearBtn);

                this.input.type = 'hidden';
                wrap.appendChild(this.input);

                this.dd = document.createElement('div');
                this.dd.className = 'sc-dropdown';

                var hdr = document.createElement('div'); hdr.className = 'sc-header';
                this.prevBtn = document.createElement('span');
                this.prevBtn.className = 'sc-nav'; this.prevBtn.innerHTML = '&#9650;';
                this.monthLabel = document.createElement('span'); this.monthLabel.className = 'sc-month';
                this.nextBtn = document.createElement('span');
                this.nextBtn.className = 'sc-nav'; this.nextBtn.innerHTML = '&#9660;';
                var navBox = document.createElement('span'); navBox.className = 'sc-nav-box';
                navBox.appendChild(this.prevBtn); navBox.appendChild(this.nextBtn);
                hdr.appendChild(this.monthLabel); hdr.appendChild(navBox);

                var wbar = document.createElement('div'); wbar.className = 'sc-wdays';
                for (var i = 0; i < 7; i++) {
                    var s = document.createElement('span');
                    s.textContent = SC_DAYS[i];
                    if (i === 6) s.classList.add('sc-sun');
                    wbar.appendChild(s);
                }

                this.body = document.createElement('div'); this.body.className = 'sc-body';
                this.dd.appendChild(hdr); this.dd.appendChild(wbar); this.dd.appendChild(this.body);
                wrap.appendChild(this.dd);
                this.wrap = wrap;
            };

            SCP._gen = function() {
                var start = new Date(this.today.getFullYear(), this.today.getMonth() - 6, 1);
                var end = new Date(this.today.getFullYear(), this.today.getMonth() + 18, 0);
                var mon = scMonday(start);
                this.weeks = [];
                while (mon <= end) {
                    var wk = [];
                    for (var i = 0; i < 7; i++) { var d = new Date(mon); d.setDate(d.getDate()+i); wk.push(d); }
                    this.weeks.push(wk);
                    mon = new Date(mon); mon.setDate(mon.getDate()+7);
                }
            };

            SCP._render = function() {
                this.body.innerHTML = '';
                for (var w = 0; w < this.weeks.length; w++) {
                    var row = document.createElement('div'); row.className = 'sc-week';
                    for (var d = 0; d < 7; d++) {
                        var date = this.weeks[w][d];
                        var cell = document.createElement('span');
                        cell.className = 'sc-day'; cell.textContent = date.getDate();
                        cell._date = date;
                        if (d === 6) cell.classList.add('sc-sun');
                        if (scSame(date, this.today)) cell.classList.add('sc-today');
                        if (scSame(date, this.selected)) cell.classList.add('sc-selected');
                        if (date.getDate() === 1) {
                            cell.setAttribute('data-month', SC_MONTHS[date.getMonth()].substring(0,3));
                            cell.classList.add('sc-month-start');
                        }
                        row.appendChild(cell);
                    }
                    this.body.appendChild(row);
                }
            };

            SCP._bind = function() {
                var self = this;
                this.display.addEventListener('click', function(e) { e.stopPropagation(); self.isOpen ? self.close() : self.open(); });
                this.clearBtn.addEventListener('click', function(e) { e.stopPropagation(); self.clear(); });
                this.prevBtn.addEventListener('click', function(e) { e.stopPropagation(); self.body.scrollTop -= SC_ROW * 4; });
                this.nextBtn.addEventListener('click', function(e) { e.stopPropagation(); self.body.scrollTop += SC_ROW * 4; });

                this.body.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    self.body.scrollTop += (e.deltaY > 0 ? 1 : -1) * SC_STEP;
                }, { passive: false });

                this.body.addEventListener('scroll', function() { self._updateHeader(); });

                this.body.addEventListener('click', function(e) {
                    var t = e.target;
                    if (t.classList.contains('sc-day') && t._date) self._select(t._date);
                });

                document.addEventListener('click', function(e) {
                    if (self.isOpen && !self.wrap.contains(e.target)) self.close();
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && self.isOpen) self.close();
                });
            };

            SCP.open = function() {
                document.querySelectorAll('.sc-dropdown').forEach(function(el) { el.style.display = 'none'; });
                this.dd.style.display = 'block'; this.isOpen = true;
                this._scrollToDate(this.selected || this.today);
                this._updateHeader();
            };
            SCP.close = function() { this.dd.style.display = 'none'; this.isOpen = false; };

            SCP.clear = function() {
                this.selected = null; this.input.value = ''; this.display.value = '';
                this.clearBtn.style.display = 'none'; this._refreshCells();
            };

            SCP._select = function(d) {
                this.selected = d; this.input.value = scYmd(d); this.display.value = scDmy(d);
                this.clearBtn.style.display = 'block'; this._refreshCells(); this.close();
            };

            SCP._refreshCells = function() {
                var cells = this.body.querySelectorAll('.sc-day'), sel = this.selected;
                for (var i = 0; i < cells.length; i++) {
                    cells[i].classList.toggle('sc-selected', scSame(cells[i]._date, sel));
                }
            };

            SCP._scrollToDate = function(d) {
                for (var w = 0; w < this.weeks.length; w++) {
                    if (this.weeks[w][0] <= d && this.weeks[w][6] >= d) {
                        this.body.scrollTop = w * SC_ROW - SC_ROW * 2;
                        return;
                    }
                }
            };

            SCP._updateHeader = function() {
                var midY = this.body.scrollTop + (SC_VISIBLE * SC_ROW) / 2;
                var idx = Math.max(0, Math.min(Math.floor(midY / SC_ROW), this.weeks.length - 1));
                var thu = this.weeks[idx][3];
                this.monthLabel.textContent = SC_MONTHS[thu.getMonth()] + ' ' + thu.getFullYear();
            };

            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Cascading dropdowns
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

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        .sc-wrap { position: relative; }
        .sc-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 18px; font-weight: bold; color: #94a3b8; cursor: pointer; line-height: 1; padding: 0 4px; border-radius: 50%; z-index: 2; transition: all 0.15s; }
        .sc-clear:hover { color: #fff; background: #ef4444; }
        .sc-dropdown { display: none; position: absolute; top: 100%; left: 0; z-index: 9999; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,0.15); width: 270px; margin-top: 4px; overflow: hidden; }
        .sc-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px 8px; }
        .sc-month { font-weight: 700; font-size: 14px; color: #1e293b; }
        .sc-nav-box { display: flex; flex-direction: column; gap: 1px; }
        .sc-nav { cursor: pointer; color: #64748b; font-size: 10px; padding: 2px 6px; border-radius: 4px; transition: all 0.15s; user-select: none; line-height: 1; }
        .sc-nav:hover { background: #f1f5f9; color: #1e293b; }
        .sc-wdays { display: grid; grid-template-columns: repeat(7, 1fr); padding: 2px 6px 4px; border-bottom: 1px solid #f1f5f9; }
        .sc-wdays span { text-align: center; font-size: 11px; font-weight: 700; color: #64748b; padding: 2px 0; }
        .sc-wdays .sc-sun { color: #dc2626; }
        .sc-body { height: 204px; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
        .sc-body::-webkit-scrollbar { display: none; }
        .sc-week { display: grid; grid-template-columns: repeat(7, 1fr); height: 34px; }
        .sc-day { display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 500; color: #334155; cursor: pointer; border-radius: 50%; width: 30px; height: 30px; margin: 2px auto; transition: background 0.1s; position: relative; }
        .sc-day:hover { background: #eff6ff; }
        .sc-day.sc-sun { color: #dc2626; font-weight: 600; }
        .sc-day.sc-today { background: #e0e7ff; font-weight: 700; color: #2b5ea7; }
        .sc-day.sc-selected { background: #2b5ea7 !important; color: #fff !important; font-weight: 700; }
        .sc-day.sc-selected.sc-sun { background: #dc2626 !important; }
        .sc-day.sc-month-start::after { content: attr(data-month); position: absolute; top: -1px; right: -4px; font-size: 7px; font-weight: 700; color: #2b5ea7; background: #eff6ff; padding: 0 3px; border-radius: 3px; line-height: 1.4; pointer-events: none; }

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

        /* Zona ranglari */
        .journal-table tbody tr.zone-red { background: #fef2f2 !important; }
        .journal-table tbody tr.zone-red:hover { background: #fee2e2 !important; box-shadow: inset 4px 0 0 #dc2626; }
        .journal-table tbody tr.zone-pink { background: #fdf2f8 !important; }
        .journal-table tbody tr.zone-pink:hover { background: #fce7f3 !important; box-shadow: inset 4px 0 0 #ec4899; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-sababli { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-total { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-days { background: #f3e8ff; color: #7c3aed; border: 1px solid #c4b5fd; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-grade-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-grade-pink { background: #fdf2f8; color: #db2777; border: 1px solid #f9a8d4; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-grade-yellow { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }

        .zone-label { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }
    </style>
</x-app-layout>
