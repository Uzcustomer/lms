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
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Talaba ID</label>
                            <input type="text" id="student_id" placeholder="Talaba HEMIS ID" class="filter-input">
                        </div>
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> O'quv yili</label>
                            <input type="text" id="education_year" placeholder="O'quv yili kodi" class="filter-input">
                        </div>
                        <div class="filter-item" style="min-width: 90px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-search" class="btn-calc" onclick="loadContracts(1)">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Kontraktlarni ko'rish uchun "Qidirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Filtrlarni tanlashingiz mumkin</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;">
                            <span id="total-badge" class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
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

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function formatTimestamp(ts) {
            if (!ts) return '-';
            var d = new Date(ts * 1000);
            return d.toLocaleDateString('uz-UZ') + ' ' + d.toLocaleTimeString('uz-UZ', {hour:'2-digit', minute:'2-digit'});
        }

        function loadContracts(page) {
            currentPage = page || 1;
            var params = {
                page: currentPage,
                limit: $('#per_page').val() || 50,
                _student: $('#student_id').val() || '',
                _education_year: $('#education_year').val() || ''
            };

            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-search').prop('disabled', true).css('opacity', '0.6');

            $.ajax({
                url: '{{ route("admin.contracts.data") }}',
                type: 'GET',
                data: params,
                timeout: 60000,
                success: function(res) {
                    $('#loading-state').hide();
                    $('#btn-search').prop('disabled', false).css('opacity', '1');

                    if (!res.success || !res.data || !res.data.items || res.data.items.length === 0) {
                        $('#empty-state').show().find('p:first').text("Kontrakt topilmadi");
                        $('#table-area').hide();
                        return;
                    }

                    var items = res.data.items;
                    var pagination = res.data.pagination;

                    $('#total-badge').text('Jami: ' + (pagination.totalCount || items.length) + ' ta kontrakt');
                    renderTable(items);
                    renderPagination(pagination);
                    $('#table-area').show();
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
                }
            });
        }

        function renderTable(items) {
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                html += '<tr>';
                html += '<td class="td-num">' + (i + 1 + ((currentPage - 1) * ($('#per_page').val() || 50))) + '</td>';
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

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text() });
            });

            // Enter tugmasini bosib qidirish
            $('#student_id, #education_year').on('keypress', function(e) {
                if (e.which === 13) loadContracts(1);
            });
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
        .filter-input::placeholder { color: #94a3b8; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
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
