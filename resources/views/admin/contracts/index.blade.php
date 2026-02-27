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
                        <div class="filter-item" style="max-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Semestr</label>
                            <select id="semester" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
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
                        <div class="filter-item" style="max-width:180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Talaba ID</label>
                            <input type="text" id="student_id" placeholder="Talaba HEMIS ID" class="filter-input">
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
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
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
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="contract-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th>ID</th>
                                        <th>Talaba ID</th>
                                        <th>Kalit</th>
                                        <th>O'quv yili</th>
                                        <th>Ma'lumotlar</th>
                                        <th>Yaratilgan</th>
                                        <th>Yangilangan</th>
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
                _group: $('#group').val() || '',
                _level: $('#level_code').val() || '',
                _semester: $('#semester').val() || ''
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
                        $('#empty-state').show().find('p:first').text("Kontrakt topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        allItems = [];
                        return;
                    }

                    var items = res.data.items;
                    var pagination = res.data.pagination;
                    allItems = items;

                    $('#total-badge').text('Jami: ' + (pagination.totalCount || items.length) + ' ta kontrakt');
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
                    } else if (xhr.status) {
                        msg += ' (HTTP ' + xhr.status + ')';
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
                html += '<tr>';
                html += '<td class="td-num">' + (i + 1 + ((currentPage - 1) * limit)) + '</td>';
                html += '<td style="color:#64748b;">' + esc(String(item.id)) + '</td>';
                html += '<td><span class="badge badge-indigo">' + esc(String(item._student)) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:600;color:#0f172a;">' + esc(item.key) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(String(item._education_year)) + '</span></td>';
                html += '<td>';
                if (item._data && item._data.length > 0) {
                    for (var d = 0; d < item._data.length; d++) {
                        var attr = item._data[d];
                        var keys = Object.keys(attr);
                        for (var k = 0; k < keys.length; k++) {
                            html += '<span class="data-badge">' + esc(keys[k]) + ': ' + esc(String(attr[keys[k]])) + '</span>';
                        }
                    }
                } else {
                    html += '-';
                }
                html += '</td>';
                html += '<td style="color:#64748b;font-size:12px;white-space:nowrap;">' + formatTimestamp(item.created_at) + '</td>';
                html += '<td style="color:#64748b;font-size:12px;white-space:nowrap;">' + formatTimestamp(item.updated_at) + '</td>';
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

        function downloadExcel() {
            if (allItems.length === 0) return;
            var csv = '\uFEFF#,ID,Talaba ID,Kalit,O\'quv yili,Yaratilgan,Yangilangan\n';
            for (var i = 0; i < allItems.length; i++) {
                var item = allItems[i];
                csv += (i+1) + ',' + (item.id||'') + ',' + (item._student||'') + ',"' + (item.key||'').replace(/"/g,'""') + '",' + (item._education_year||'') + ',' + formatTimestamp(item.created_at) + ',' + formatTimestamp(item.updated_at) + '\n';
            }
            var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'kontraktlar_' + new Date().toISOString().slice(0,10) + '.csv';
            link.click();
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Enter tugmasini bosib qidirish
            $('#student_id').on('keypress', function(e) {
                if (e.which === 13) loadContracts(1);
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
            function rSem() { rd('#semester'); pd('{{ route("admin.journal.get-semesters") }}', { level_code: $('#level_code').val() || '' }, '#semester'); }
            function rGrp() { rd('#group'); pd('{{ route("admin.journal.get-groups") }}', fp(), '#group'); }

            $('#education_type').change(function() { rSpec(); rGrp(); });
            $('#faculty').change(function() { rSpec(); rGrp(); });
            $('#specialty').change(function() { rGrp(); });
            $('#level_code').change(function() { rSem(); rGrp(); });
            $('#semester').change(function() { rGrp(); });

            // Boshlang'ich yuklash
            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester');
            pd('{{ route("admin.journal.get-groups") }}', fp(), '#group');
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

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }

        .data-badge { display: inline-block; padding: 2px 8px; margin: 2px 3px; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
        .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }
    </style>
</x-app-layout>
