<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sababli check - Davomat tekshiruvi
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
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0ea5e9;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Talaba FISH</label>
                            <input type="text" id="student_name" placeholder="Ism yoki familya..." style="width:100%;height:36px;padding:0 10px;font-size:0.8rem;font-weight:500;border:1px solid #cbd5e1;border-radius:8px;outline:none;color:#1e293b;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,0.04);transition:all 0.2s;" onfocus="this.style.borderColor='#2b5ea7';this.style.boxShadow='0 0 0 2px rgba(43,94,167,0.1)'" onblur="this.style.borderColor='#cbd5e1';this.style.boxShadow='0 1px 2px rgba(0,0,0,0.04)'">
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Holat filtri</label>
                            <select id="filter_status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="mismatch">Faqat mos emaslar</option>
                                <option value="match">Faqat moslar</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
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
                                    Tekshirish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Tekshirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">HEMIS dagi sababli/sababsiz davomatlarni tasdiqlangan arizalar bilan solishtiradi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Tekshirilmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f0f9ff;border-bottom:1px solid #bae6fd;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#0369a1;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="match-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="mismatch-badge" class="badge" style="background:#dc2626;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                            <div style="margin-left:auto;position:relative;">
                                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#94a3b8;pointer-events:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" id="table-search" placeholder="Qidirish (ism, guruh, fan...)" style="padding:6px 12px 6px 30px;border:1px solid #cbd5e1;border-radius:8px;font-size:12.5px;width:260px;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='#2b5ea7'" onblur="this.style.borderColor='#cbd5e1'">
                                <span id="search-count" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;color:#94a3b8;display:none;"></span>
                            </div>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="full_name">Talaba FISH <span class="sort-icon active">&#9650;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="department_name">Fakultet <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="group_name">Guruh <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="semester_name">Semestr <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="subject_name">Fan <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="total_hours">Nb soati <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="mark-filter-link" id="mark-filter-btn" title="Bosing: faqat sabablilar / hammasi">Mark <span class="sort-icon" id="mark-filter-icon">&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="hemis_status">HEMIS holati <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="match">Natija <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th style="text-align:center;width:50px;">Info</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                        <div id="pagination-area" style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;"></div>

                        <!-- Debug Log -->
                        <div id="debug-log-area" style="display:none;">
                            <div style="border-top:2px solid #fbbf24;background:#fffbeb;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <span style="font-size:13px;font-weight:700;color:#92400e;cursor:pointer;" onclick="$('#debug-log-table').toggle();$(this).find('.debug-arrow').toggleClass('open');">
                                    <span class="debug-arrow" style="display:inline-block;transition:transform 0.2s;">&#9654;</span>
                                    DEBUG LOG — Topilmagan / Muammoli arizalar
                                    (<span id="debug-log-count">0</span> ta)
                                    <span style="font-size:11px;color:#b45309;margin-left:8px;">(bosing ochish/yopish)</span>
                                </span>
                                <div style="position:relative;">
                                    <input type="text" id="debug-search" placeholder="hemis_id, ism, fan..." style="padding:5px 10px 5px 28px;border:1px solid #fbbf24;border-radius:6px;font-size:11.5px;width:220px;outline:none;background:#fff;" oninput="filterDebugLog()">
                                    <svg style="position:absolute;left:8px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:#b45309;pointer-events:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    <span id="debug-search-count" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:10px;color:#92400e;display:none;"></span>
                                </div>
                            </div>
                            <div id="debug-log-table" style="display:none;max-height:400px;overflow:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                                    <thead style="background:#fef3c7;position:sticky;top:0;z-index:1;">
                                        <tr>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">#</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Makeup ID</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Talaba</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Guruh</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Fan nomi</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Asl ID</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Resolved ID</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Qayerdan</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Att?</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #fbbf24;font-size:10px;text-transform:uppercase;color:#92400e;">Sabab</th>
                                        </tr>
                                    </thead>
                                    <tbody id="debug-log-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Resolve Log -->
                        <div id="resolve-log-area" style="display:none;">
                            <div style="border-top:2px solid #60a5fa;background:#eff6ff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <span style="font-size:13px;font-weight:700;color:#1e40af;cursor:pointer;" onclick="$('#resolve-log-table').toggle();$(this).find('.resolve-arrow').toggleClass('open');">
                                    <span class="resolve-arrow" style="display:inline-block;transition:transform 0.2s;">&#9654;</span>
                                    Subject ID o'zgartirilganlar
                                    (<span id="resolve-log-count">0</span> ta)
                                    <span style="font-size:11px;color:#3b82f6;margin-left:8px;">(curriculum_subject_hemis_id → subject_id)</span>
                                </span>
                            </div>
                            <div id="resolve-log-table" style="display:none;max-height:300px;overflow:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                                    <thead style="background:#dbeafe;position:sticky;top:0;z-index:1;">
                                        <tr>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">#</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">Talaba</th>
                                            <th style="padding:8px 6px;text-align:left;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">Fan nomi</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">Asl ID</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">Resolved ID</th>
                                            <th style="padding:8px 6px;text-align:center;border-bottom:2px solid #60a5fa;font-size:10px;text-transform:uppercase;color:#1e40af;">Ariza ID</th>
                                        </tr>
                                    </thead>
                                    <tbody id="resolve-log-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Juftliklar modali -->
    <!-- Attendance Detail Modal -->
    <div id="att-detail-overlay" class="pairs-modal-overlay" style="display:none;" onclick="if(event.target===this) $('#att-detail-overlay').fadeOut(150);">
        <div class="pairs-modal" onclick="event.stopPropagation()" style="max-width:800px;">
            <div class="pairs-modal-header">
                <h3 id="att-detail-title" style="margin:0;font-size:15px;font-weight:700;color:#0f172a;">Attendance tafsiloti</h3>
                <button class="pairs-modal-close" onclick="$('#att-detail-overlay').fadeOut(150)">&times;</button>
            </div>
            <div class="pairs-modal-body">
                <div id="att-detail-loading" style="text-align:center;padding:30px;color:#64748b;">
                    <svg class="animate-spin" style="display:inline;width:20px;height:20px;margin-right:6px;" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Yuklanmoqda...
                </div>
                <table class="pairs-table" id="att-detail-table" style="display:none;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sana</th>
                            <th>Juftlik</th>
                            <th>Fan nomi</th>
                            <th>Subject ID</th>
                            <th>Sababli</th>
                            <th>Sababsiz</th>
                            <th>Semester</th>
                            <th>O'quv yili</th>
                        </tr>
                    </thead>
                    <tbody id="att-detail-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="pairs-modal-overlay" class="pairs-modal-overlay" style="display:none;" onclick="closePairsModal(event)">
        <div class="pairs-modal" onclick="event.stopPropagation()">
            <div class="pairs-modal-header">
                <h3 id="pairs-modal-title" style="margin:0;font-size:15px;font-weight:700;color:#0f172a;"></h3>
                <button class="pairs-modal-close" onclick="closePairsModal()">&times;</button>
            </div>
            <div class="pairs-modal-subtitle" id="pairs-modal-subtitle" style="padding:0 20px 12px;font-size:12px;color:#64748b;"></div>
            <div class="pairs-modal-body">
                <table class="pairs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sana</th>
                            <th>Juftlik</th>
                            <th>Dars turi</th>
                            <th>Sababli (soat)</th>
                            <th>Sababsiz (soat)</th>
                            <th>HEMIS holati</th>
                            <th>Mark holati</th>
                        </tr>
                    </thead>
                    <tbody id="pairs-modal-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let currentSort = 'full_name';
        let currentDirection = 'asc';
        let currentPage = 1;
        let allData = [];  // barcha yuklangan ma'lumotlar
        let lastResponse = null;  // oxirgi server javobi
        let markFilterActive = false; // MARK ustuni filtri: faqat sabablilar

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
                level_code: $('#level_code').val() || '',
                group: $('#group').val() || '',
                semester_code: $('#semester_code').val() || '',
                student_name: ($('#student_name').val() || '').trim(),
                filter_status: $('#filter_status').val() || '',
                search: ($('#table-search').val() || '').trim(),
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
                per_page: $('#per_page').val() || 50,
                sort: currentSort,
                direction: currentDirection,
            };
        }

        function loadReport() {
            currentPage = 1;
            var params = getFilters();
            params.per_page = 999999;
            delete params.search; // search faqat client-side ishlaydi

            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.reports.sababli-check.data") }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    if (res.error) {
                        allData = [];
                        $('#empty-state').show().find('p:first').html('Xatolik: ' + res.error + '<br><small style="color:#94a3b8;">' + (res.error_line || '') + '</small>');
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    if (!res.data || res.data.length === 0) {
                        allData = [];
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    allData = res.data;
                    lastResponse = res;
                    $('#total-badge').text('Jami: ' + res.total + ' ta yozuv');
                    $('#match-badge').text('Mos: ' + (res.match_count || 0));
                    $('#mismatch-badge').text('Mos emas: ' + (res.mismatch_count || 0));
                    $('#time-badge').text(elapsed + ' soniyada tekshirildi');
                    renderPage();
                    renderDebugLog(res.debug_log || []);
                    renderResolveLog(res.resolve_log || []);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    $('#empty-state').show().find('p:first').text("Xatolik yuz berdi. Qayta urinib ko'ring.");
                }
            });
        }

        function renderPage() {
            // Client-side qidirish
            var searchTerm = ($('#table-search').val() || '').trim().toLowerCase();
            var filtered = allData;
            if (searchTerm) {
                filtered = allData.filter(function(r) {
                    return (r.full_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.group_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.subject_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.department_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.student_hemis_id || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.specialty_name || '').toLowerCase().indexOf(searchTerm) > -1;
                });
                $('#search-count').text(filtered.length + ' / ' + allData.length).show();
            } else {
                $('#search-count').hide();
            }

            // MARK filtri: faqat sababli (ariza bor) larni ko'rsatish
            if (markFilterActive) {
                filtered = filtered.filter(function(r) {
                    return (r.mark_status || '').indexOf('Sababli') === 0;
                });
            }

            // Client-side saralash
            var sorted = filtered.slice().sort(function(a, b) {
                var valA = a[currentSort] || '';
                var valB = b[currentSort] || '';
                var cmp;
                if (currentSort === 'total_hours') {
                    cmp = (parseInt(valA) || 0) - (parseInt(valB) || 0);
                } else {
                    cmp = valA.toString().localeCompare(valB.toString(), 'uz', {numeric: true});
                }
                return currentDirection === 'desc' ? -cmp : cmp;
            });

            // Client-side sahifalash
            var perPage = parseInt($('#per_page').val()) || 50;
            var total = sorted.length;
            var lastPage = Math.ceil(total / perPage);
            if (currentPage > lastPage) currentPage = lastPage || 1;
            var offset = (currentPage - 1) * perPage;
            var pageData = sorted.slice(offset, offset + perPage);

            // Raqamlash
            for (var i = 0; i < pageData.length; i++) {
                pageData[i].row_num = offset + i + 1;
            }

            currentPageData = pageData;
            renderTable(pageData);
            renderPagination({current_page: currentPage, last_page: lastPage, total: total});
        }

        function goToPage(page) {
            currentPage = page;
            renderPage();
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function matchBadge(val) {
            if (val === 'match') return '<span class="badge badge-match">Mos</span>';
            return '<span class="badge badge-mismatch">Mos emas</span>';
        }

        function markBadge(val, excuseId, excuseDates) {
            if (val && val.indexOf('Sababli') === 0) {
                var dateText = excuseDates ? ' <span style="font-size:9px;opacity:0.8;">(' + esc(excuseDates) + ')</span>' : '';
                if (excuseId) {
                    return '<a href="/admin/absence-excuses/' + excuseId + '" target="_blank" class="badge badge-lms" style="cursor:pointer;text-decoration:none;" title="Arizani ko\'rish">' + esc(val) + dateText + '</a>';
                }
                return '<span class="badge badge-lms">' + esc(val) + dateText + '</span>';
            }
            return '<span class="badge badge-hemis-bad">Ariza yo\'q</span>';
        }

        function hemisBadge(val, studentId, subjectId, rowIdx) {
            if (val === 'Sababli') return '<span class="badge badge-hemis-ok">Sababli</span>';
            if (val === 'Sababsiz' && studentId && subjectId) return '<span class="badge badge-hemis-bad" style="cursor:pointer;" onclick="openAttDetail(\'' + studentId + '\', \'' + subjectId + '\')" title="Batafsil ko\'rish">Sababsiz</span>';
            if (val === 'Sababsiz') return '<span class="badge badge-hemis-bad">Sababsiz</span>';
            if (val === 'Aralash' && studentId && subjectId) return '<span class="badge badge-hemis-mixed" style="cursor:pointer;" onclick="openAttDetail(\'' + studentId + '\', \'' + subjectId + '\')" title="Batafsil ko\'rish">Aralash</span>';
            if (val === 'Aralash') return '<span class="badge badge-hemis-mixed">Aralash</span>';
            if (val === 'Davomat topilmadi') return '<span class="badge badge-hemis-none">Topilmadi</span>';
            if ((val === 'Fan topilmadi' || val === "Ma'lumot yo'q") && studentId) return '<span class="badge badge-hemis-none" style="cursor:pointer;" onclick="openFanNotFound(' + rowIdx + ')" title="Batafsil">' + esc(val) + '</span>';
            return '<span class="badge badge-hemis-none">' + esc(val) + '</span>';
        }

        function renderTable(data) {
            var html = '';
            var prevKey = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var curKey = r.student_hemis_id + '|' + (r.excuse_id || '');

                // Yangi ariza guruhi bo'lsa — sarlavha qator qo'shish
                if (curKey !== prevKey && r.excuse_id && r.excuse_start) {
                    if (prevKey !== '') {
                        html += '<tr><td colspan="11" style="height:25px;background:#fff;border:none;"></td></tr>';
                    }
                    html += '<tr style="background:#e2e8f0;">';
                    html += '<td colspan="11" style="padding:12px 14px;font-size:13px;font-weight:700;color:#1e293b;text-align:center;">';
                    html += '<span style="margin-right:10px;">Ariza #' + r.excuse_id + '</span>';
                    html += '<span style="font-weight:500;color:#475569;">' + esc(r.full_name) + '</span>';
                    html += '<span style="margin-left:10px;padding:3px 10px;border-radius:6px;font-size:12px;background:#1e40af;color:#fff;font-weight:700;">' + esc(r.excuse_start) + ' — ' + esc(r.excuse_end) + '</span>';
                    html += '</td></tr>';
                }
                prevKey = curKey;

                var rowClass = r.match === 'mismatch' ? 'row-mismatch' : '';
                html += '<tr class="journal-row ' + rowClass + '">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td style="cursor:pointer;" onclick="window.open(\'' + r.journal_url + '\', \'_blank\')"><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
                html += '<td style="text-align:center;font-size:12px;color:#475569;">' + esc(r.semester_name) + '</td>';
                html += '<td><span class="text-cell text-subject">' + esc(r.subject_name) + '</span></td>';
                html += '<td style="text-align:center;font-weight:700;font-size:13px;color:#dc2626;">' + r.total_hours + '</td>';
                var excDates = (r.excuse_start && r.excuse_end) ? r.excuse_start + ' — ' + r.excuse_end : '';
                html += '<td style="text-align:center;">' + markBadge(r.mark_status, r.excuse_id, excDates) + '</td>';
                html += '<td style="text-align:center;">' + hemisBadge(r.hemis_status, r.student_hemis_id, r.subject_id, i) + '</td>';
                html += '<td style="text-align:center;">' + matchBadge(r.match) + '</td>';
                html += '<td style="text-align:center;"><button class="btn-detail" onclick="openPairsModal(' + i + ')" title="Batafsil"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button></td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        var currentPageData = [];

        function openPairsModal(idx) {
            var r = currentPageData[idx];
            if (!r) return;
            $('#pairs-modal-title').text(r.full_name + ' — ' + r.subject_name);
            $('#pairs-modal-subtitle').text(r.group_name + ' | ' + r.semester_name + ' | Jami: ' + r.total_hours + ' soat (sababli: ' + r.total_absent_on + ', sababsiz: ' + r.total_absent_off + ')');
            var html = '';
            var pairs = r.pairs || [];
            for (var i = 0; i < pairs.length; i++) {
                var p = pairs[i];
                html += '<tr>';
                html += '<td style="text-align:center;">' + (i + 1) + '</td>';
                html += '<td style="text-align:center;">' + esc(p.lesson_date) + '</td>';
                html += '<td style="text-align:center;">' + esc(p.lesson_pair) + '</td>';
                html += '<td style="text-align:center;font-size:11px;">' + esc(p.training_type || '-') + '</td>';
                html += '<td style="text-align:center;color:#065f46;font-weight:600;">' + p.absent_on + '</td>';
                html += '<td style="text-align:center;color:#dc2626;font-weight:600;">' + p.absent_off + '</td>';
                html += '<td style="text-align:center;">' + hemisBadge(p.hemis_status) + '</td>';
                html += '<td style="text-align:center;">' + markBadge(p.mark_status) + '</td>';
                html += '</tr>';
            }
            $('#pairs-modal-body').html(html);
            $('#pairs-modal-overlay').fadeIn(150);
        }

        function closePairsModal(e) {
            if (e && e.target !== e.currentTarget) return;
            $('#pairs-modal-overlay').fadeOut(150);
        }

        function openFanNotFound(idx) {
            var r = currentPageData[idx];
            if (!r) return;

            var html = '<div style="padding:20px;">';
            html += '<h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:16px;">Fan ma\'lumotlari</h3>';

            // Ariza ma'lumotlari
            html += '<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">';
            html += '<tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;color:#64748b;width:180px;">Talaba</td><td style="padding:8px 12px;font-weight:700;">' + esc(r.full_name) + ' <span style="color:#94a3b8;">(' + esc(r.student_hemis_id) + ')</span></td></tr>';
            html += '<tr><td style="padding:8px 12px;font-weight:600;color:#64748b;">Guruh</td><td style="padding:8px 12px;">' + esc(r.group_name) + '</td></tr>';
            html += '<tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;color:#64748b;">Semestr</td><td style="padding:8px 12px;">' + esc(r.semester_name) + '</td></tr>';
            html += '<tr><td style="padding:8px 12px;font-weight:600;color:#64748b;">Fan nomi (arizada)</td><td style="padding:8px 12px;font-weight:700;color:#1e40af;">' + esc(r.subject_name) + '</td></tr>';
            html += '<tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;color:#64748b;">Subject ID (resolved)</td><td style="padding:8px 12px;">' + esc(r.subject_id || '-') + '</td></tr>';
            if (r.aem_subject_id && r.aem_subject_id != r.subject_id) {
                html += '<tr><td style="padding:8px 12px;font-weight:600;color:#64748b;">Asl Subject ID (arizada)</td><td style="padding:8px 12px;color:#dc2626;text-decoration:line-through;">' + esc(r.aem_subject_id) + '</td></tr>';
            }
            html += '<tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;color:#64748b;">Ariza sanalari</td><td style="padding:8px 12px;">' + esc(r.excuse_start || '') + ' — ' + esc(r.excuse_end || '') + '</td></tr>';
            html += '<tr><td style="padding:8px 12px;font-weight:600;color:#64748b;">Qidiruv natijasi</td><td style="padding:8px 12px;"><span class="badge badge-hemis-none">' + esc(r.hemis_status) + '</span> <span style="font-size:11px;color:#94a3b8;">(' + esc(r.match_method || '-') + ')</span></td></tr>';
            html += '</table>';

            // Attendance da bor fanlarni ko'rsatish
            html += '<h4 style="font-size:13px;font-weight:700;color:#475569;margin-bottom:8px;">Attendance da shu talabaning mavjud fanlari:</h4>';
            html += '<div id="fan-not-found-att" style="text-align:center;padding:12px;color:#94a3b8;">Yuklanmoqda...</div>';
            html += '</div>';

            $('#att-detail-title').text('Fan topilmadi — ' + r.subject_name);
            $('#att-detail-loading').hide();
            $('#att-detail-table').hide();
            $('.pairs-modal-body', '#att-detail-overlay .pairs-modal').html(html);
            $('.pairs-modal-body', '#att-detail-overlay .pairs-modal').show();
            $('#att-detail-overlay').fadeIn(150);

            // Shu talabaning barcha attendance fanlarini olish
            var cs = document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0';
            $.ajax({
                url: attDetailUrl,
                type: 'GET',
                data: { student_hemis_id: r.student_hemis_id, subject_id: r.subject_id || 0, current_semester: cs },
                success: function(res) {
                    var rows = res.rows || [];
                    // Unique fanlar
                    var subjects = {};
                    rows.forEach(function(a) {
                        var key = a.subject_id;
                        if (!subjects[key]) {
                            subjects[key] = { id: a.subject_id, name: a.subject_name, semester: a.semester_name, count: 0, on: 0, off: 0 };
                        }
                        subjects[key].count++;
                        subjects[key].on += (a.absent_on || 0);
                        subjects[key].off += (a.absent_off || 0);
                    });

                    var tbl = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
                    tbl += '<thead><tr style="background:#f1f5f9;"><th style="padding:6px 8px;text-align:left;">Fan nomi</th><th style="padding:6px 8px;text-align:center;">Subject ID</th><th style="padding:6px 8px;text-align:center;">Yozuvlar</th><th style="padding:6px 8px;text-align:center;">Sababli</th><th style="padding:6px 8px;text-align:center;">Sababsiz</th></tr></thead><tbody>';

                    var keys = Object.keys(subjects);
                    if (keys.length === 0) {
                        tbl += '<tr><td colspan="5" style="text-align:center;padding:16px;color:#94a3b8;">Attendance da hech qanday absent yozuv topilmadi</td></tr>';
                    } else {
                        keys.sort(function(a,b) { return subjects[a].name.localeCompare(subjects[b].name); });
                        keys.forEach(function(k) {
                            var s = subjects[k];
                            var isMatch = (s.id == r.subject_id);
                            var bg = isMatch ? '#dbeafe' : '';
                            tbl += '<tr style="background:' + bg + ';border-bottom:1px solid #f1f5f9;">';
                            tbl += '<td style="padding:6px 8px;font-weight:' + (isMatch ? '700' : '400') + ';">' + esc(s.name) + (isMatch ? ' <span style="color:#16a34a;">&#10004;</span>' : '') + '</td>';
                            tbl += '<td style="padding:6px 8px;text-align:center;">' + esc(s.id) + '</td>';
                            tbl += '<td style="padding:6px 8px;text-align:center;">' + s.count + '</td>';
                            tbl += '<td style="padding:6px 8px;text-align:center;color:#065f46;font-weight:600;">' + s.on + '</td>';
                            tbl += '<td style="padding:6px 8px;text-align:center;color:#dc2626;font-weight:600;">' + s.off + '</td>';
                            tbl += '</tr>';
                        });
                    }
                    tbl += '</tbody></table>';
                    $('#fan-not-found-att').html(tbl);
                },
                error: function() {
                    $('#fan-not-found-att').html('<span style="color:#dc2626;">Xatolik yuz berdi</span>');
                }
            });
        }

        var attDetailUrl = '{{ route("admin.reports.sababli-check.attendance-detail") }}';

        function openAttDetail(studentId, subjectId) {
            $('#att-detail-title').text('Attendance: ' + studentId + ' | Subject ID: ' + subjectId);
            $('#att-detail-loading').show();
            $('#att-detail-table').hide();
            $('#att-detail-overlay').fadeIn(150);

            var cs = document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0';

            $.ajax({
                url: attDetailUrl,
                type: 'GET',
                data: { student_hemis_id: studentId, subject_id: subjectId, current_semester: cs },
                success: function(res) {
                    var rows = res.rows || [];
                    var html = '';
                    for (var i = 0; i < rows.length; i++) {
                        var r = rows[i];
                        var date = r.lesson_date ? r.lesson_date.substring(0, 10).split('-').reverse().join('.') : '-';
                        var pair = (r.lesson_pair_start_time && r.lesson_pair_end_time) ? r.lesson_pair_start_time + '-' + r.lesson_pair_end_time : '-';
                        html += '<tr>';
                        html += '<td style="text-align:center;">' + (i + 1) + '</td>';
                        html += '<td style="text-align:center;">' + date + '</td>';
                        html += '<td style="text-align:center;">' + esc(pair) + '</td>';
                        html += '<td>' + esc(r.subject_name || '-') + '</td>';
                        html += '<td style="text-align:center;">' + esc(r.subject_id || '-') + '</td>';
                        html += '<td style="text-align:center;color:#065f46;font-weight:600;">' + (r.absent_on || 0) + '</td>';
                        html += '<td style="text-align:center;color:#dc2626;font-weight:600;">' + (r.absent_off || 0) + '</td>';
                        html += '<td style="text-align:center;font-size:11px;">' + esc(r.semester_name || r.semester_code || '-') + '</td>';
                        html += '<td style="text-align:center;font-size:11px;">' + esc(r.education_year_name || '-') + (r.education_year_current ? ' <span style="color:#16a34a;">●</span>' : '') + '</td>';
                        html += '</tr>';
                    }
                    if (rows.length === 0) {
                        html = '<tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:20px;">Ma\'lumot topilmadi</td></tr>';
                    }
                    $('#att-detail-body').html(html);
                    $('#att-detail-loading').hide();
                    $('#att-detail-table').show();
                },
                error: function() {
                    $('#att-detail-loading').html('<span style="color:#dc2626;">Xatolik yuz berdi</span>');
                }
            });
        }

        var allDebugLogs = [];

        function renderDebugLog(logs) {
            allDebugLogs = logs || [];
            if (allDebugLogs.length === 0) {
                $('#debug-log-area').hide();
                return;
            }
            $('#debug-log-count').text(allDebugLogs.length);
            $('#debug-search').val('');
            $('#debug-search-count').hide();
            renderDebugRows(allDebugLogs);
            $('#debug-log-area').show();
            $('#debug-log-table').show();
        }

        function filterDebugLog() {
            var term = ($('#debug-search').val() || '').trim().toLowerCase();
            if (!term) {
                renderDebugRows(allDebugLogs);
                $('#debug-search-count').hide();
                $('#debug-log-count').text(allDebugLogs.length);
                return;
            }
            var filtered = allDebugLogs.filter(function(l) {
                return (l.student_hemis_id || '').toString().toLowerCase().indexOf(term) > -1
                    || (l.full_name || '').toLowerCase().indexOf(term) > -1
                    || (l.group_name || '').toLowerCase().indexOf(term) > -1
                    || (l.subject_name || '').toLowerCase().indexOf(term) > -1
                    || (l.original_id || '').toString().indexOf(term) > -1
                    || (l.resolved_id || '').toString().indexOf(term) > -1
                    || (l.reason || '').toLowerCase().indexOf(term) > -1;
            });
            renderDebugRows(filtered);
            $('#debug-search-count').text(filtered.length + ' / ' + allDebugLogs.length).show();
            $('#debug-log-count').text(filtered.length);
        }

        function renderDebugRows(logs) {
            var html = '';
            for (var i = 0; i < logs.length; i++) {
                var l = logs[i];
                var attIcon = l.att_exists ? '<span style="color:#16a34a;">&#10004;</span>' : '<span style="color:#dc2626;">&#10008;</span>';
                var idChanged = l.original_id != l.resolved_id;
                var rowBg = i % 2 === 0 ? '#fff' : '#fffbeb';
                html += '<tr style="background:' + rowBg + ';border-bottom:1px solid #fef3c7;">';
                html += '<td style="padding:6px;color:#92400e;font-weight:600;">' + (i + 1) + '</td>';
                html += '<td style="padding:6px;font-weight:700;color:#0f172a;">' + l.makeup_id + '</td>';
                html += '<td style="padding:6px;">' + esc(l.full_name) + ' <span style="color:#94a3b8;font-size:10px;">(' + l.student_hemis_id + ')</span></td>';
                html += '<td style="padding:6px;">' + esc(l.group_name) + '</td>';
                html += '<td style="padding:6px;font-weight:600;color:#0f172a;">' + esc(l.subject_name) + '</td>';
                html += '<td style="padding:6px;text-align:center;' + (idChanged ? 'color:#dc2626;text-decoration:line-through;' : '') + '">' + l.original_id + '</td>';
                html += '<td style="padding:6px;text-align:center;font-weight:700;' + (idChanged ? 'color:#16a34a;' : 'color:#64748b;') + '">' + l.resolved_id + '</td>';
                html += '<td style="padding:6px;"><span style="background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;">' + esc(l.resolved_via) + '</span></td>';
                html += '<td style="padding:6px;text-align:center;">' + attIcon + '</td>';
                html += '<td style="padding:6px;color:#b45309;font-size:11px;">' + esc(l.reason) + '</td>';
                html += '</tr>';
            }
            $('#debug-log-body').html(html);
        }

        function renderResolveLog(logs) {
            if (!logs || logs.length === 0) {
                $('#resolve-log-area').hide();
                return;
            }
            $('#resolve-log-count').text(logs.length);
            var html = '';
            for (var i = 0; i < logs.length; i++) {
                var l = logs[i];
                var rowBg = i % 2 === 0 ? '#fff' : '#eff6ff';
                html += '<tr style="background:' + rowBg + ';border-bottom:1px solid #dbeafe;">';
                html += '<td style="padding:6px;color:#1e40af;font-weight:600;">' + (i + 1) + '</td>';
                html += '<td style="padding:6px;">' + esc(l.student_hemis_id) + '</td>';
                html += '<td style="padding:6px;font-weight:600;color:#0f172a;">' + esc(l.subject_name) + '</td>';
                html += '<td style="padding:6px;text-align:center;color:#dc2626;text-decoration:line-through;">' + esc(l.original_id) + '</td>';
                html += '<td style="padding:6px;text-align:center;font-weight:700;color:#16a34a;">' + esc(l.resolved_id) + '</td>';
                html += '<td style="padding:6px;text-align:center;color:#64748b;">' + esc(l.excuse_id) + '</td>';
                html += '</tr>';
            }
            $('#resolve-log-body').html(html);
            $('#resolve-log-area').show();
        }

        function downloadExcel() {
            if (!allData || allData.length === 0) return;

            // Client-side filterlarni qo'llash (qidirish + mark filter)
            var searchTerm = ($('#table-search').val() || '').trim().toLowerCase();
            var filtered = allData;
            if (searchTerm) {
                filtered = allData.filter(function(r) {
                    return (r.full_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.group_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.subject_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.department_name || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.student_hemis_id || '').toLowerCase().indexOf(searchTerm) > -1
                        || (r.specialty_name || '').toLowerCase().indexOf(searchTerm) > -1;
                });
            }
            if (markFilterActive) {
                filtered = filtered.filter(function(r) {
                    return (r.mark_status || '').indexOf('Sababli') === 0;
                });
            }

            // Saralash
            var sorted = filtered.slice().sort(function(a, b) {
                var valA = a[currentSort] || '';
                var valB = b[currentSort] || '';
                var cmp;
                if (currentSort === 'total_hours') {
                    cmp = (parseInt(valA) || 0) - (parseInt(valB) || 0);
                } else {
                    cmp = valA.toString().localeCompare(valB.toString(), 'uz', {numeric: true});
                }
                return currentDirection === 'desc' ? -cmp : cmp;
            });

            // Excel uchun ma'lumotlar
            var rows = [['#', 'Talaba FISH', 'HEMIS ID', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Semestr', 'Fan', 'Nb soati', 'Mark', 'HEMIS holati', 'Natija']];
            sorted.forEach(function(r, i) {
                rows.push([
                    i + 1,
                    r.full_name || '',
                    r.student_hemis_id || '',
                    r.department_name || '',
                    r.specialty_name || '',
                    r.level_name || '',
                    r.group_name || '',
                    r.semester_name || '',
                    r.subject_name || '',
                    r.total_hours || 0,
                    r.mark_status || '',
                    r.hemis_status || '',
                    r.match === 'match' ? 'Mos' : 'Mos emas'
                ]);
            });

            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(rows);

            // Ustun kengliklarini sozlash
            ws['!cols'] = [
                {wch: 5}, {wch: 30}, {wch: 10}, {wch: 25}, {wch: 30}, {wch: 8},
                {wch: 15}, {wch: 15}, {wch: 35}, {wch: 10}, {wch: 18}, {wch: 18}, {wch: 12}
            ];

            XLSX.utils.book_append_sheet(wb, ws, 'Sababli check');
            XLSX.writeFile(wb, 'Sababli_check_' + new Date().toISOString().slice(0,10) + '.xlsx');
        }

        function renderPagination(res) {
            if (res.last_page <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            if (res.current_page > 1)
                html += '<button class="pg-btn" onclick="goToPage(' + (res.current_page - 1) + ')">&laquo; Oldingi</button>';
            for (var p = 1; p <= res.last_page; p++) {
                if (p === 1 || p === res.last_page || (p >= res.current_page - 2 && p <= res.current_page + 2)) {
                    html += '<button class="pg-btn' + (p === res.current_page ? ' pg-active' : '') + '" onclick="goToPage(' + p + ')">' + p + '</button>';
                } else if (p === res.current_page - 3 || p === res.current_page + 3) {
                    html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                }
            }
            if (res.current_page < res.last_page)
                html += '<button class="pg-btn" onclick="goToPage(' + (res.current_page + 1) + ')">Keyingi &raquo;</button>';
            $('#pagination-area').html(html);
        }

        $(document).ready(function() {
            var searchTimer = null;
            $('#table-search').on('input', function() {
                if (allData.length > 0) {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function() {
                        currentPage = 1;
                        renderPage();
                    }, 250);
                }
            }).on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimer);
                    currentPage = 1;
                    if (allData.length > 0) {
                        renderPage();
                    } else {
                        loadReport();
                    }
                }
            });

            $(document).on('click', '.mark-filter-link', function(e) {
                e.preventDefault();
                markFilterActive = !markFilterActive;
                var btn = $('#mark-filter-btn');
                var icon = $('#mark-filter-icon');
                if (markFilterActive) {
                    btn.css({'background': '#dbeafe', 'padding': '2px 8px', 'border-radius': '6px', 'color': '#1e40af'});
                    icon.html('&#9650;').addClass('active');
                } else {
                    btn.css({'background': 'transparent', 'padding': '', 'border-radius': '', 'color': ''});
                    icon.html('&#9660;').removeClass('active');
                }
                currentPage = 1;
                renderPage();
            });

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
                currentPage = 1;
                renderPage();
            });

            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            function fp() { return { education_type: $('#education_type').val()||'', faculty_id: $('#faculty').val()||'', specialty_id: $('#specialty').val()||'', level_code: $('#level_code').val()||'', current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0' }; }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rGrp() { rd('#group'); pd('{{ route("admin.journal.get-groups") }}', fp(), '#group'); }
            function rSem() { rd('#semester_code'); pd('{{ route("admin.journal.get-semesters") }}', { level_code: $('#level_code').val() || '' }, '#semester_code'); }

            $('#education_type').change(function() { rSpec(); rGrp(); });
            $('#faculty').change(function() { rSpec(); rGrp(); });
            $('#specialty').change(function() { rGrp(); });
            $('#level_code').change(function() { rGrp(); rSem(); });

            $('#student_name').on('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); loadReport(); }
            });

            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            rSem();
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
        .sort-link, .mark-filter-link { display: inline-flex; align-items: center; gap: 4px; color: #334155; text-decoration: none; cursor: pointer; transition: all 0.15s; }
        .sort-link:hover, .mark-filter-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 8px; opacity: 0.4; }
        .sort-icon.active { font-size: 11px; opacity: 1; color: #2b5ea7; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #f0f9ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .journal-table tbody tr.row-mismatch { background: #fef2f2 !important; }
        .journal-table tbody tr.row-mismatch:hover { background: #fee2e2 !important; box-shadow: inset 4px 0 0 #dc2626; }
        .journal-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-lms { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-hemis-ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-hemis-bad { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .badge-hemis-none { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .badge-match { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; font-weight: 700; }
        .badge-mismatch { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-weight: 700; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-subject { color: #0f172a; font-weight: 700; font-size: 12.5px; max-width: 260px; white-space: normal; word-break: break-word; }

        .badge-hemis-mixed { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        .btn-detail { display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;cursor:pointer;color:#64748b;transition:all 0.15s; }
        .btn-detail:hover { background:#f0f9ff;border-color:#2b5ea7;color:#2b5ea7; }

        .pairs-modal-overlay { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center; }
        .pairs-modal { background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.2);width:90%;max-width:800px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden; }
        .pairs-modal-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc; }
        .pairs-modal-close { background:none;border:none;font-size:24px;color:#94a3b8;cursor:pointer;padding:0 4px;line-height:1; }
        .pairs-modal-close:hover { color:#dc2626; }
        .pairs-modal-body { overflow-y:auto;padding:0; }

        .pairs-table { width:100%;border-collapse:collapse;font-size:12px; }
        .pairs-table thead { background:#f0f4f8;position:sticky;top:0;z-index:1; }
        .pairs-table th { padding:10px 8px;font-weight:700;font-size:10.5px;text-transform:uppercase;letter-spacing:0.04em;color:#475569;border-bottom:2px solid #cbd5e1; }
        .pairs-table td { padding:8px;border-bottom:1px solid #f1f5f9; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #f0f9ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }
        .debug-arrow.open { transform: rotate(90deg); }
        .resolve-arrow.open { transform: rotate(90deg); }
    </style>
</x-app-layout>
