<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Kontraktlar ro'yxati
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}">{{ $type->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="max-width:110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#059669;"></span> Guruh</label>
                            <select id="group_filter" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Shartnoma turi</label>
                            <select id="contract_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($contractTypes as $ct)
                                    <option value="{{ $ct->edu_contract_type_code }}">{{ $ct->edu_contract_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#0ea5e9;"></span> Summa turi</label>
                            <select id="sum_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($sumTypes as $st)
                                    <option value="{{ $st->edu_contract_sum_type_code }}">{{ $st->edu_contract_sum_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-item" style="max-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#6366f1;"></span> O'quv yili</label>
                            <select id="education_year" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationYears as $ey)
                                    <option value="{{ $ey->code }}" {{ ($currentEducationYear ?? '') == $ey->code ? 'selected' : '' }}>{{ $ey->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Ta'lim shakli</label>
                            <select id="edu_form" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($eduForms as $ef)
                                    <option value="{{ $ef }}">{{ $ef }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Holat</label>
                            <select id="status_filter" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($statuses as $st)
                                    <option value="{{ $st->status }}">{{ $st->status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#a855f7;"></span> Tashkilot</label>
                            <select id="organization" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org }}">{{ $org }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Qarzdorlik</label>
                            <select id="debt_filter" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="25">25% dan kam to'lagan</option>
                                <option value="50">50% dan kam to'lagan</option>
                                <option value="75">75% dan kam to'lagan</option>
                                <option value="100">To'liq to'lamagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Qidiruv</label>
                            <input type="text" id="search_input" placeholder="FIO yoki shartnoma raqami" class="filter-input">
                        </div>
                        <div class="filter-item" style="max-width:180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Talaba ID</label>
                            <input type="text" id="student_id" placeholder="Talaba HEMIS ID" class="filter-input">
                        </div>
                        <div class="filter-item" style="max-width:170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0d9488;"></span> Joriy kurs</label>
                            <div style="display:flex;align-items:center;height:36px;">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="current_course_toggle">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span id="toggle-label" style="margin-left:8px;font-size:12px;font-weight:600;color:#94a3b8;">O'chirilgan</span>
                            </div>
                        </div>
                        <div class="filter-item" style="max-width:90px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item filter-buttons">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;">
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-search" class="btn-calc" onclick="loadContracts(1)">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                                <button type="button" id="btn-sync" class="btn-sync" onclick="syncContracts()">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Sinxronizatsiya
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Kontraktlar ro'yxatini ko'rish uchun</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="sum-badge" class="badge" style="background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="contract-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th>Talaba</th>
                                        <th>Talaba ID</th>
                                        <th>Shartnoma raqami</th>
                                        <th>Shartnoma turi</th>
                                        <th>Summa turi</th>
                                        <th>Fakultet</th>
                                        <th>Yo'nalish</th>
                                        <th>Kurs</th>
                                        <th>Guruh</th>
                                        <th>Ta'lim turi</th>
                                        <th>Ta'lim shakli</th>
                                        <th>O'quv yili</th>
                                        <th>Tashkilot</th>
                                        <th>Kontrakt summasi</th>
                                        <th>Bosh. debet</th>
                                        <th>Bosh. kredit</th>
                                        <th>Shartnoma debet</th>
                                        <th>To'langan</th>
                                        <th>Qaytarilgan</th>
                                        <th>Oxirgi debet</th>
                                        <th>Oxirgi kredit</th>
                                        <th>To'lanmagan</th>
                                        <th>Holat</th>
                                        <th>Yaratilgan</th>
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
        let currentPage = 1;
        let allItems = [];

        function esc(s) { return $('<span>').text(s || '-').html(); }
        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function formatMoney(val) {
            if (!val && val !== 0) return '-';
            var n = parseFloat(val);
            return n.toLocaleString('uz-UZ', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' so\'m';
        }

        function formatTimestamp(ts) {
            if (!ts) return '-';
            var d = new Date(ts * 1000);
            return d.toLocaleDateString('uz-UZ') + ' ' + d.toLocaleTimeString('uz-UZ', {hour:'2-digit', minute:'2-digit'});
        }

        function getFilters() {
            return {
                page: currentPage,
                limit: $('#per_page').val() || 50,
                _student: $('#student_id').val() || '',
                _education_year: $('#education_year').val() || '',
                _education_type: $('#education_type').val() || '',
                _department: $('#faculty').val() || '',
                _specialty: $('#specialty').val() || '',
                _level: $('#level_code').val() || '',
                _group: $('#group_filter').val() || '',
                _debt: $('#debt_filter').val() || '',
                _contract_type: $('#contract_type').val() || '',
                _sum_type: $('#sum_type').val() || '',
                _edu_form: $('#edu_form').val() || '',
                _status: $('#status_filter').val() || '',
                _organization: $('#organization').val() || '',
                search: $('#search_input').val() || '',
                _current_course: $('#current_course_toggle').is(':checked') ? '1' : ''
            };
        }

        function loadContracts(page) {
            currentPage = page || 1;
            var params = getFilters();
            params.page = currentPage;

            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-search').prop('disabled', true).css('opacity', '0.6');
            var startTime = performance.now();

            $.ajax({
                url: '{{ route("admin.contracts.data") }}',
                type: 'GET',
                data: params,
                timeout: 60000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-search').prop('disabled', false).css('opacity', '1');

                    if (!res.success || !res.data || !res.data.items || res.data.items.length === 0) {
                        var totalInDb = res.data ? (res.data.totalInDb || 0) : 0;
                        if (totalInDb === 0) {
                            $('#empty-state').show()
                                .find('p:first').text("Ma'lumotlar bazasida kontrakt yo'q")
                                .next().html('Iltimos, <strong>Sinxronizatsiya</strong> tugmasini bosib HEMIS dan ma\'lumotlarni yuklang');
                        } else {
                            $('#empty-state').show().find('p:first').text("Kontrakt topilmadi");
                            $('#empty-state p:last').text("Filtrlani o'zgartirib qayta qidiring");
                        }
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        allItems = [];
                        return;
                    }

                    var items = res.data.items;
                    var pagination = res.data.pagination;
                    allItems = items;

                    $('#total-badge').text('Jami: ' + (pagination.totalCount || items.length) + ' ta kontrakt');

                    var totalSum = 0;
                    for (var i = 0; i < items.length; i++) {
                        totalSum += parseFloat(items[i].edu_contract_sum || 0);
                    }
                    $('#sum-badge').text('Sahifa summasi: ' + formatMoney(totalSum));

                    $('#time-badge').text(elapsed + ' soniyada yuklandi');
                    renderTable(items);
                    renderPagination(pagination);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-search').prop('disabled', false).css('opacity', '1');
                    var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg += ' (' + xhr.responseJSON.error + ')';
                    }
                    $('#empty-state').show().find('p:first').text(msg);
                    allItems = [];
                }
            });
        }

        function renderTable(items) {
            var html = '';
            var limit = parseInt($('#per_page').val()) || 50;
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var unpaid = parseFloat(item.unpaid_credit_amount || 0);

                var statusClass = 'badge-green';
                if (item.status && (item.status.toLowerCase().includes('отклон') || item.status_id === 17)) {
                    statusClass = 'badge-red';
                } else if (item.status && item.status.toLowerCase().includes('ожид')) {
                    statusClass = 'badge-yellow';
                }

                html += '<tr>';
                html += '<td class="td-num">' + (i + 1 + ((currentPage - 1) * limit)) + '</td>';
                // Talaba
                html += '<td style="min-width:160px;font-weight:600;color:#0f172a;font-size:12.5px;">' + esc(item.full_name) + '</td>';
                // Talaba ID
                html += '<td style="min-width:110px;font-size:11px;color:#64748b;white-space:nowrap;">' + esc(String(item.student_id_number || item.student_hemis_id)) + '</td>';
                // Shartnoma raqami
                html += '<td style="min-width:130px;"><span class="text-cell" style="font-weight:600;color:#2b5ea7;">' + esc(item.contract_number) + '</span></td>';
                // Shartnoma turi
                html += '<td style="min-width:140px;"><span class="badge badge-cyan">' + esc(item.edu_contract_type_name) + '</span></td>';
                // Summa turi
                html += '<td style="min-width:130px;"><span class="badge badge-indigo">' + esc(item.edu_contract_sum_type_name) + '</span></td>';
                // Fakultet
                html += '<td style="min-width:160px;font-size:12px;font-weight:500;">' + esc(item.faculty_name) + '</td>';
                // Yo'nalish
                html += '<td style="min-width:200px;font-size:11px;color:#475569;">' + esc(item.edu_speciality_name) + '</td>';
                // Kurs
                html += '<td style="text-align:center;min-width:60px;"><span class="badge badge-violet">' + esc(item.edu_course) + '</span></td>';
                // Guruh
                html += '<td style="min-width:120px;font-size:12px;font-weight:500;color:#1e293b;">' + esc(item.group_name) + '</td>';
                // Ta'lim turi
                html += '<td style="min-width:120px;"><span class="badge badge-blue">' + esc(item.edu_type_name) + '</span></td>';
                // Ta'lim shakli
                html += '<td style="min-width:100px;"><span class="badge badge-blue">' + esc(item.edu_form) + '</span></td>';
                // O'quv yili
                html += '<td style="min-width:90px;text-align:center;font-weight:600;font-size:12px;">' + esc(item.education_year) + '</td>';
                // Tashkilot
                html += '<td style="min-width:160px;font-size:11px;">' + esc(item.edu_organization) + '</td>';
                // Kontrakt summasi
                html += '<td style="text-align:right;font-weight:700;white-space:nowrap;min-width:120px;">' + formatMoney(item.edu_contract_sum) + '</td>';
                // Boshlang'ich debet
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#64748b;">' + formatMoney(item.begin_rest_debet_amount) + '</td>';
                // Boshlang'ich kredit
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#64748b;">' + formatMoney(item.begin_rest_credit_amount) + '</td>';
                // Shartnoma debet
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#0369a1;">' + formatMoney(item.contract_debet_amount) + '</td>';
                // To'langan
                html += '<td style="text-align:right;color:#16a34a;font-weight:600;white-space:nowrap;min-width:110px;">' + formatMoney(item.paid_credit_amount) + '</td>';
                // Qaytarilgan
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#7c3aed;">' + formatMoney(item.vozvrat_debet_amount) + '</td>';
                // Oxirgi debet
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#64748b;">' + formatMoney(item.end_rest_debet_amount) + '</td>';
                // Oxirgi kredit
                html += '<td style="text-align:right;white-space:nowrap;min-width:110px;color:#64748b;">' + formatMoney(item.end_rest_credit_amount) + '</td>';
                // To'lanmagan (qoldiq)
                var unpaidColor = unpaid > 0 ? '#dc2626' : '#16a34a';
                html += '<td style="text-align:right;font-weight:700;white-space:nowrap;min-width:110px;color:' + unpaidColor + ';">' + formatMoney(unpaid) + '</td>';
                // Holat
                html += '<td style="min-width:100px;"><span class="badge ' + statusClass + '">' + esc(item.status) + '</span></td>';
                // Yaratilgan
                html += '<td style="min-width:100px;font-size:11px;color:#64748b;white-space:nowrap;">' + formatTimestamp(item.hemis_created_at) + '</td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function renderPagination(pg) {
            if (!pg || pg.pageCount <= 1) { $('#pagination-area').html(''); return; }
            var html = '';
            if (pg.page > 1)
                html += '<button class="pg-btn" onclick="loadContracts(' + (pg.page - 1) + ')">&laquo; Oldingi</button>';
            for (var p = 1; p <= pg.pageCount; p++) {
                if (p === 1 || p === pg.pageCount || (p >= pg.page - 2 && p <= pg.page + 2)) {
                    html += '<button class="pg-btn' + (p === pg.page ? ' pg-active' : '') + '" onclick="loadContracts(' + p + ')">' + p + '</button>';
                } else if (p === pg.page - 3 || p === pg.page + 3) {
                    html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                }
            }
            if (pg.page < pg.pageCount)
                html += '<button class="pg-btn" onclick="loadContracts(' + (pg.page + 1) + ')">Keyingi &raquo;</button>';
            $('#pagination-area').html(html);
        }

        function syncContracts() {
            var btn = $('#btn-sync');
            btn.prop('disabled', true).css('opacity', '0.6');
            btn.html('<svg style="width:14px;height:14px;animation:spin 0.8s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Kutilmoqda...');

            $.ajax({
                url: '{{ route("admin.contracts.sync") }}',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                timeout: 30000,
                success: function(res) {
                    showToast(res.message || 'Sinxronizatsiya boshlandi!', 'success');
                },
                error: function(xhr) {
                    var msg = 'Xatolik yuz berdi';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).css('opacity', '1');
                    btn.html('<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sinxronizatsiya');
                }
            });
        }

        function showToast(msg, type) {
            var color = type === 'success' ? '#16a34a' : '#dc2626';
            var toast = $('<div style="position:fixed;bottom:24px;right:24px;z-index:9999;background:' + color + ';color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.18);max-width:320px;">' + msg + '</div>');
            $('body').append(toast);
            setTimeout(function() { toast.fadeOut(400, function() { $(this).remove(); }); }, 4000);
        }

        function q(v) { return '"' + String(v || '').replace(/"/g, '""') + '"'; }

        function buildCsv(items) {
            var headers = [
                '#','Talaba','Talaba ID','Shartnoma raqami','Shartnoma turi','Summa turi',
                'Fakultet','Yo\'nalish','Kurs','Guruh','Ta\'lim turi','Ta\'lim shakli','O\'quv yili',
                'Tashkilot','Kontrakt summasi','Bosh. debet','Bosh. kredit',
                'Shartnoma debet','To\'langan','Qaytarilgan','Oxirgi debet','Oxirgi kredit',
                'To\'lanmagan','Holat','Yaratilgan'
            ];
            var csv = '\uFEFF' + headers.join(',') + '\n';
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var row = [
                    i + 1, q(item.full_name), item.student_id_number || item.student_hemis_id || '',
                    q(item.contract_number), q(item.edu_contract_type_name), q(item.edu_contract_sum_type_name),
                    q(item.faculty_name), q(item.edu_speciality_name), q(item.edu_course), q(item.group_name),
                    q(item.edu_type_name), q(item.edu_form), q(item.education_year), q(item.edu_organization),
                    item.edu_contract_sum || 0, item.begin_rest_debet_amount || 0, item.begin_rest_credit_amount || 0,
                    item.contract_debet_amount || 0, item.paid_credit_amount || 0, item.vozvrat_debet_amount || 0,
                    item.end_rest_debet_amount || 0, item.end_rest_credit_amount || 0, item.unpaid_credit_amount || 0,
                    q(item.status),
                    item.hemis_created_at ? new Date(item.hemis_created_at * 1000).toLocaleDateString('uz-UZ') : ''
                ];
                csv += row.join(',') + '\n';
            }
            return csv;
        }

        function saveCsv(csv) {
            var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'kontraktlar_' + new Date().toISOString().slice(0,10) + '.csv';
            link.click();
        }

        function downloadExcel() {
            var btn = $('#btn-excel');
            btn.prop('disabled', true).text('Yuklanmoqda...');

            var params = getFilters();
            params.page = 1;
            params.limit = 999999;

            $.ajax({
                url: '{{ route("admin.contracts.data") }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    if (res.success && res.data && res.data.items && res.data.items.length > 0) {
                        saveCsv(buildCsv(res.data.items));
                        showToast(res.data.items.length + ' ta kontrakt yuklandi!', 'success');
                    } else {
                        showToast('Yuklanadigan ma\'lumot yo\'q', 'error');
                    }
                },
                error: function() {
                    showToast('Xatolik yuz berdi, qayta urinib ko\'ring', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Excel');
                }
            });
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            $('#student_id, #search_input').on('keypress', function(e) {
                if (e.which === 13) loadContracts(1);
            });

            $('#current_course_toggle').on('change', function() {
                var label = $('#toggle-label');
                if ($(this).is(':checked')) {
                    label.text('Yoqilgan').css('color', '#0d9488');
                } else {
                    label.text("O'chirilgan").css('color', '#94a3b8');
                }
                loadContracts(1);
            });

            // Cascading filter functions
            function fp() {
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: $('#faculty').val() || '',
                    specialty_id: $('#specialty').val() || '',
                    level_code: $('#level_code').val() || ''
                };
            }
            function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
            function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rGroups() {
                var p = { level_code: $('#level_code').val() || '', faculty_id: $('#faculty').val() || '' };
                rd('#group_filter');
                $.get('{{ route("admin.contracts.get-groups") }}', p, function(d) {
                    $.each(d, function(i, v) { $('#group_filter').append('<option value="'+v+'">'+v+'</option>'); });
                    $('#group_filter').trigger('change.select2');
                });
            }

            $('#education_type').change(function() { rSpec(); });
            $('#faculty').change(function() { rSpec(); rGroups(); });
            $('#level_code').change(function() { rGroups(); });

            // Boshlang'ich yuklash
            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', function() {
                rGroups();
                // Sahifa ochilganda avtomatik qidiruv
                loadContracts(1);
            });
        });
    </script>

    <style>
        .filter-container { padding: 12px 16px 10px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 8px; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-item { flex: 1; min-width: 130px; }
        .filter-buttons { flex: 0 0 auto; min-width: auto !important; }
        .filter-label { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
        .filter-input::placeholder { color: #94a3b8; }

        .btn-calc { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-excel { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 36px; white-space: nowrap; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }
        .btn-sync { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; background: linear-gradient(135deg, #0369a1, #0ea5e9); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(3,105,161,0.3); height: 36px; white-space: nowrap; }
        .btn-sync:hover:not(:disabled) { background: linear-gradient(135deg, #075985, #0369a1); transform: translateY(-1px); }
        .btn-sync:disabled { cursor: not-allowed; opacity: 0.6; }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 8px; padding-right: 48px; color: #1e293b; font-size: 0.78rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 20px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 15px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 5px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.78rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .contract-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .contract-table thead { position: sticky; top: 0; z-index: 10; }
        .contract-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .contract-table th { padding: 14px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .contract-table th.th-num { padding: 14px 10px 14px 16px; width: 44px; }
        .contract-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .contract-table tbody tr:nth-child(even) { background: #f8fafc; }
        .contract-table tbody tr:nth-child(odd) { background: #fff; }
        .contract-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .contract-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; white-space: nowrap; }
        .badge-cyan { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; white-space: nowrap; }
        .badge-green { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; white-space: nowrap; }
        .badge-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; white-space: nowrap; }
        .badge-yellow { background: #fefce8; color: #a16207; border: 1px solid #fef08a; white-space: nowrap; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }

        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 24px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .toggle-switch input:checked + .toggle-slider { background: linear-gradient(135deg, #0d9488, #14b8a6); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
    </style>
</x-app-layout>
